<?php

use App\Exports\CountyRecapExport;
use App\Livewire\Admin\DemographicsPanel;
use App\Livewire\Admin\ReportBuilder;
use App\Livewire\Admin\UnitComparison;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Support\Period;
use Livewire\Livewire;

/** Satu OPD dengan riwayat setahun penuh, 10 jangkauan per hari. */
function opdSetahun(string $nama): OrganizationalUnit
{
    $unit = OrganizationalUnit::factory()->create(['name' => $nama]);
    $akun = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    foreach (range(1, 365) as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'followers_count' => 4_000 + (365 - $mundur),
            'reach' => 10,
            'interactions' => 1,
        ]);
    }

    return $unit;
}

it('menyediakan opsi 1 tahun pada daftar periode', function () {
    expect(Period::OPTIONS)->toHaveKey('365')
        ->and(Period::OPTIONS['365'])->toBe('1 tahun terakhir');

    $p = Period::fromKey('365');

    expect($p->days())->toBe(365)
        ->and($p->fromDate())->toBe(now()->subDays(364)->toDateString())
        ->and($p->label())->toBe('1 tahun terakhir');
});

it('menghitung rekap sepanjang satu tahun penuh', function () {
    $unit = opdSetahun('Dinas Kesehatan');

    $c = Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->set('period', '365')
        ->set('units', [$unit->id]);

    // 364 hari di dalam rentang (hari ini belum punya snapshot) × 10 jangkauan.
    expect($c->get('summary')['reach'])->toBe(3_640)
        ->and($c->get('preview')->first()->reach)->toBe(3_640);
});

it('menyebut cakupan satu tahun pada laporan PDF', function () {
    $unit = opdSetahun('Dinas Kesehatan');

    $periode = Period::fromKey('365');
    $scope = AccountScope::make()->units([$unit->id]);

    $html = view('exports.county-recap', array_merge(
        (new CountyRecapExport($periode, $scope))->context(),
        ['generatedBy' => 'Penguji', 'logo' => null],
    ))->render();

    $teks = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

    /*
     | Pembaca laporan harus tahu rentangnya setahun, bukan menebak dari dua
     | tanggal. Jumlah harinya ikut ditulis supaya rentang kustom yang panjang
     | pun tetap terbaca jelas.
    */
    expect($teks)->toContain('365 hari')
        ->and($teks)->toContain('1 tahun terakhir')
        ->and($teks)->toContain($periode->from->translatedFormat('j F Y'))
        ->and($teks)->toContain($periode->until->translatedFormat('j F Y'));
});

it('menerima periode satu tahun di Perbandingan dan Demografi', function () {
    $a = opdSetahun('Dinas Kesehatan');
    $b = opdSetahun('Dinas Pariwisata');

    $banding = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('period', '365')
        ->set('selected', [$a->id, $b->id]);

    expect($banding->get('trendChart')['series'])->toHaveCount(2)
        // Satu titik per hari yang punya data sepanjang setahun.
        ->and($banding->get('trendChart')['series'][0]['data'])->toHaveCount(364);

    Livewire::actingAs(adminUser())
        ->test(DemographicsPanel::class)
        ->set('period', '365')
        ->assertOk()
        ->assertSee('1 tahun terakhir');
});

it('menampilkan pertumbuhan setahun pada peringkat, bukan hanya rentang pendek', function () {
    $unit = opdSetahun('Dinas Kesehatan');

    $pendek = Livewire::actingAs(adminUser())->test(ReportBuilder::class)
        ->set('period', '30')->set('units', [$unit->id])->get('preview')->first();

    $setahun = Livewire::actingAs(adminUser())->test(ReportBuilder::class)
        ->set('period', '365')->set('units', [$unit->id])->get('preview')->first();

    // Jangkauan setahun harus jauh melampaui jangkauan sebulan.
    expect($setahun->reach)->toBeGreaterThan($pendek->reach * 10);
});
