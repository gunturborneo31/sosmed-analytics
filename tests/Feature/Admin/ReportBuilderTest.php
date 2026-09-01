<?php

use App\Exports\CountyRecapExport;
use App\Livewire\Admin\ReportBuilder;
use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Support\Period;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->admin = adminUser();

    $this->unit = OrganizationalUnit::factory()->create(['name' => 'Dinas Kesehatan']);
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $this->unit->id]);

    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 4200,
        'reach' => 9000,
        'engagement_rate' => 3.2,
    ]);

    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['25-34' => 2100, '35-44' => 900],
    ]);
    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_GENDER,
        'data' => ['F' => 1800, 'M' => 1200, 'U' => 30],
    ]);
});

it('menampilkan ringkasan per platform dan akumulasi di kartu laporan', function () {
    Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->assertOk()
        ->assertSee('Cakupan laporan')
        ->assertSee('Rekapan demografi Instagram & Facebook')
        ->assertSee('Unduh berkasnya');
});

it('mengunduh rekap Excel berisi seluruh OPD yang lolos filter', function () {
    Excel::fake();

    Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->call('exportExcel');

    Excel::assertDownloaded('rekap-medsos-kutim-'.now()->subDays(29)->toDateString().'-sd-'.now()->toDateString().'.xlsx');
});

it('menghasilkan berkas PDF yang benar-benar terbentuk', function () {
    $this->actingAs($this->admin);

    $component = new ReportBuilder;
    $component->period = '30';

    $stream = $component->exportPdf();

    expect($stream)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $stream->sendContent();
    $pdf = ob_get_clean();

    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(2000);
});

it('menolak ekspor dari peran yang tidak punya izin', function () {
    Livewire::actingAs(operatorUser())
        ->test(ReportBuilder::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('membatasi cakupan laporan ke OPD yang dipilih', function () {
    $lain = OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $lain->id]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 999,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->set('units', [$this->unit->id]);

    expect($component->get('selectedUnitNames')->all())->toBe(['Dinas Kesehatan']);

    $component->assertSee('Dinas Kesehatan')
        ->assertSee('Cakupan')
        ->assertDontSee('Dinas Pariwisata');
});

it('menaruh tombol unduh di kartu penyusun laporan, bukan kartu terpisah', function () {
    $html = Livewire::actingAs($this->admin)->test(ReportBuilder::class)->html();

    $mulai = mb_strpos($html, 'Susun Laporan');
    $penyusun = $mulai === false ? $html : mb_substr($html, $mulai);

    // Pada halaman final, cakupan diatur di satu kartu dan tombol unduh tetap
    // berada di sana tanpa menampilkan pemilihan periode atau platform yang tidak lagi dipakai.
    expect($penyusun)->toContain('Cakupan laporan')
        ->and($penyusun)->toContain('Unduh berkasnya')
        ->and($penyusun)->toContain('wire:click="exportPdf"')
        ->and($penyusun)->toContain('wire:click="exportExcel"')
        ->and($penyusun)->not->toContain('Pilih perangkat daerah')
        ->and($penyusun)->not->toContain('Rincian Pembilang');
});

it('membacakan ulang isi laporan sebelum diunduh', function () {
    $lain = OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);

    Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        // Tanpa memilih apa pun, laporan mencakup seluruh kabupaten.
        ->assertSee('Seluruh kabupaten')
        ->set('units', [$this->unit->id, $lain->id])
        ->assertSee('Dinas Kesehatan, Dinas Pariwisata');
});

it('memilih dan mengosongkan seluruh perangkat daerah lewat satu tombol', function () {
    OrganizationalUnit::factory()->count(3)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->call('selectAllUnits');

    expect($component->get('units'))->toHaveCount(OrganizationalUnit::active()->count());

    $component->call('clearUnits');

    expect($component->get('units'))->toBe([]);
});

it('mempersempit daftar pilihan lewat pencarian nama', function () {
    OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);

    $component = Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->set('unitSearch', 'pariwisata');

    expect($component->get('selectableUnits')->pluck('name')->all())
        ->toBe(['Dinas Pariwisata']);

    // "Pilih semua" hanya mengambil yang sedang tampil, bukan seluruh OPD.
    $component->call('selectAllUnits');

    expect($component->get('units'))->toHaveCount(1);
});

it('tidak lagi menyaring lewat jenis OPD di halaman Rekap', function () {
    $kecamatan = OrganizationalUnit::factory()->kecamatan('Bengalon')->create();

    $component = Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->set('units', [$this->unit->id, $kecamatan->id]);

    // Pilihan OPD di langkah 2 sudah cukup; saringan jenis dihapus karena dua
    // saringan yang bekerja bersamaan gampang menghasilkan rekap kosong.
    $component->assertDontSee('Jenis OPD')
        ->assertSet('units', [$this->unit->id, $kecamatan->id]);

    expect($component->get('selectableUnits')->pluck('id'))
        ->toContain($this->unit->id)
        ->toContain($kecamatan->id);
});

it('mengembalikan pilihan OPD dan pencarian saat filter diatur ulang', function () {
    Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->set('units', [$this->unit->id])
        ->set('unitSearch', 'kesehatan')
        ->set('platform', 'instagram')
        ->call('resetFilters')
        ->assertSet('units', [])
        ->assertSet('unitSearch', '')
        ->assertSet('platform', '');
});

it('menyebutkan cakupan OPD di laporan PDF tanpa menampilkan periode atau platform', function () {
    $konteks = Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->set('period', '7')
        ->set('platform', 'instagram')
        ->set('units', [$this->unit->id])
        ->instance();

    $html = view('exports.county-recap', array_merge(
        (new CountyRecapExport($konteks->period(), $konteks->scope()))->context(),
        ['generatedBy' => 'Penguji', 'logo' => null, 'ikk' => $konteks->ikk],
    ))->render();

    $teks = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

    // Pembaca laporan cukup tahu cakupan OPD yang dipilih; detail periode dan platform
    // tidak lagi ditampilkan di ringkasan PDF supaya fokus pada hasil akhir.
    expect($teks)->toContain('Dinas Kesehatan')
        ->and($teks)->toContain('Dibatasi pada 1 perangkat daerah')
        ->and($teks)->not->toContain('Instagram saja');
});

it('memberi tahu bahwa angka Instagram dan Facebook digabung saat semua platform dipilih', function () {
    SocialAccount::factory()->create([
        'organizational_unit_id' => $this->unit->id,
        'platform' => 'facebook',
    ]);

    $konteks = Livewire::actingAs($this->admin)->test(ReportBuilder::class)->instance();

    $html = view('exports.county-recap', array_merge(
        (new CountyRecapExport($konteks->period(), $konteks->scope()))->context(),
        ['generatedBy' => 'Penguji', 'logo' => null, 'ikk' => $konteks->ikk],
    ))->render();

    $teks = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

    expect($teks)->toContain('Rincian per Platform')
        ->and($teks)->toContain('Seluruh perangkat daerah aktif')
        ->and($teks)->not->toContain('Instagram dan Facebook digabung');
});

it('mencantumkan platform tiap baris pada berkas Excel', function () {
    SocialAccount::factory()->create([
        'organizational_unit_id' => $this->unit->id,
        'platform' => 'facebook',
    ]);

    $ekspor = new CountyRecapExport(
        Period::fromKey('30'),
        AccountScope::make(),
    );

    $baris = $ekspor->map($ekspor->collection()->firstOrFail());

    expect($ekspor->headings())->toContain('Platform')
        // Satu baris bisa berisi penjumlahan dua kanal — itu harus terbaca.
        ->and($baris[2])->toBe('Facebook + Instagram');
});

it('mempersempit isi berkas Excel sesuai platform yang dipilih', function () {
    $lain = OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);
    SocialAccount::factory()->create([
        'organizational_unit_id' => $lain->id,
        'platform' => 'facebook',
    ]);

    $hanyaFacebook = new CountyRecapExport(
        Period::fromKey('30'),
        AccountScope::make()->platform('facebook'),
    );

    expect($hanyaFacebook->collection()->pluck('unit_name')->all())->toBe(['Dinas Pariwisata']);
});
