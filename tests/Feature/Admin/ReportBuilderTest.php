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

it('menampilkan pratinjau rekap sebelum diunduh', function () {
    Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        ->assertOk()
        ->assertSee('Pratinjau Rekap')
        ->assertSee('Dinas Kesehatan');
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

it('mempersempit rekap ke OPD yang dipilih', function () {
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

    // Daftar centang tetap memuat semua OPD; yang menyempit adalah isi rekapnya.
    expect($component->get('preview')->pluck('unit_name')->all())
        ->toBe(['Dinas Kesehatan']);

    $component->assertSeeHtml('wire:key="pratinjau-'.$this->unit->id.'"')
        ->assertDontSeeHtml('wire:key="pratinjau-'.$lain->id.'"');
});

it('menaruh tombol unduh di kartu penyusun laporan, bukan kartu terpisah', function () {
    $html = Livewire::actingAs($this->admin)->test(ReportBuilder::class)->html();

    $penyusun = mb_substr($html, mb_strpos($html, 'Susun Laporan'));
    $penyusun = mb_substr($penyusun, 0, mb_strpos($penyusun, 'Pratinjau Rekap'));

    // Ketiga langkah dan kedua tombol unduh harus berada di satu kartu, supaya
    // admin tidak memilih OPD di satu tempat lalu mencari tombolnya di tempat lain.
    expect($penyusun)->toContain('Atur cakupan laporan')
        ->and($penyusun)->toContain('Pilih perangkat daerah')
        ->and($penyusun)->toContain('Unduh berkasnya')
        ->and($penyusun)->toContain('wire:click="exportPdf"')
        ->and($penyusun)->toContain('wire:click="exportExcel"');
});

it('membacakan ulang isi laporan sebelum diunduh', function () {
    $lain = OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);

    Livewire::actingAs($this->admin)
        ->test(ReportBuilder::class)
        // Tanpa memilih apa pun, laporan mencakup seluruh kabupaten.
        ->assertSee('Seluruh kabupaten')
        ->set('units', [$this->unit->id, $lain->id])
        ->assertSee('2 perangkat daerah')
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

it('menyebutkan cakupan periode, platform, dan OPD di laporan PDF', function () {
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

    // Pembaca laporan harus tahu angkanya disaring, bukan cakupan penuh.
    expect($teks)->toContain('Instagram saja')
        ->and($teks)->toContain('Dinas Kesehatan')
        ->and($teks)->toContain('Dibatasi pada 1 perangkat daerah');
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

    expect($teks)->toContain('Instagram dan Facebook digabung')
        // Porsi tiap kanal ikut dipecah supaya angka gabungan bisa dibaca.
        ->and($teks)->toContain('Rincian per Platform')
        ->and($teks)->toContain('Seluruh perangkat daerah aktif');
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
