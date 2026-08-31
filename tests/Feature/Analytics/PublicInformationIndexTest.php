<?php

use App\Livewire\Admin\ReportBuilder;
use App\Models\AudienceBreakdown;
use App\Models\OrganizationalUnit;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Services\Analytics\PublicInformationIndex;
use App\Support\Period;
use Livewire\Livewire;

/** Pasang demografi usia pada satu akun di tanggal hari ini. */
function demografiUsia(array $data, ?OrganizationalUnit $unit = null): SocialAccount
{
    $account = SocialAccount::factory()->create([
        'organizational_unit_id' => ($unit ?? OrganizationalUnit::factory()->create())->id,
    ]);

    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => $data,
    ]);

    return $account;
}

it('menghitung persentase persis seperti contoh resmi IKK', function () {
    // 319.580 / 456.333 x 100% = 70,03%
    demografiUsia([
        '18-24' => 100_000,
        '25-34' => 150_000,
        '35-44' => 50_000,
        '45-54' => 15_000,
        '55-64' => 4_580,
    ]);

    $ikk = PublicInformationIndex::make(Period::fromKey('30'), null, 456_333);

    expect($ikk->numerator())->toBe(319_580)
        ->and($ikk->denominator())->toBe(456_333)
        ->and($ikk->percentage())->toBe(70.03);
});

it('mengecualikan usia di luar 16-64 tahun dari pembilang', function () {
    demografiUsia([
        '13-17' => 9_000,   // hanya 40% masuk: usia 16 dan 17 saja
        '18-24' => 10_000,
        '25-34' => 20_000,
        '65+' => 7_000,     // tidak dihitung: di luar rentang IKK
    ]);

    $ikk = PublicInformationIndex::make(Period::fromKey('30'), null, 100_000);

    // 30.000 dari kelompok utuh + 3.600 (40% × 9.000) dari kelompok termuda.
    expect($ikk->numerator())->toBe(33_600)
        // Sisa kelompok termuda (5.400) + seluruh kelompok tertua (7.000).
        ->and($ikk->excludedCount())->toBe(12_400)
        ->and($ikk->estimatedCount())->toBe(3_600);
});

it('menampilkan kelompok termuda sebagai 16-17, bukan kelompok mentah Meta', function () {
    demografiUsia(['13-17' => 1_000]);

    $rincian = PublicInformationIndex::make(Period::fromKey('30'), null, 10_000)
        ->ageBreakdown()
        ->keyBy('kelompok');

    expect($rincian['16-17']['jumlah'])->toBe(400)
        ->and($rincian['16-17']['perkiraan'])->toBeTrue()
        ->and($rincian->keys())->not->toContain('13-17');
});

it('tidak memuat satu pun kelompok usia di luar 16-64 pada rincian', function () {
    demografiUsia(['13-17' => 500, '25-34' => 1_000, '65+' => 200]);

    $rincian = PublicInformationIndex::make(Period::fromKey('30'), null, 10_000)
        ->ageBreakdown()
        ->keyBy('kelompok');

    expect($rincian->keys()->all())->toBe(['16-17', '18-24', '25-34', '35-44', '45-54', '55-64'])
        ->and($rincian['25-34']['perkiraan'])->toBeFalse()
        ->and($rincian['25-34']['alasan'])->toBeNull()
        // Keterangan perkiraan pun tidak boleh menyebut usia di luar rentang.
        ->and($rincian['16-17']['alasan'])->not->toContain('13')
        ->and($rincian['16-17']['alasan'])->not->toContain('65');
});

it('tidak menampilkan label usia di luar 16-64 di halaman Rekap', function () {
    demografiUsia(['13-17' => 1_000, '25-34' => 5_000, '65+' => 300]);

    Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->assertOk()
        ->assertSee('16-17')
        ->assertSee('Perkiraan')
        ->assertSee('Data langsung')
        ->assertDontSee('13-17')
        ->assertDontSee('65+');
});

it('menjumlahkan pengikut lintas OPD ke dalam satu pembilang', function () {
    demografiUsia(['25-34' => 40_000]);
    demografiUsia(['25-34' => 60_000]);

    expect(PublicInformationIndex::make(Period::fromKey('30'), null, 200_000)->numerator())
        ->toBe(100_000);
});

it('memperingatkan saat pembilang melampaui jumlah penduduk', function () {
    demografiUsia(['25-34' => 120_000]);

    $ikk = PublicInformationIndex::make(Period::fromKey('30'), null, 100_000);

    // Tanda penghitungan ganda lintas akun sudah signifikan — angkanya tidak
    // boleh disajikan apa adanya sebagai capaian kinerja.
    expect($ikk->exceedsPopulation())->toBeTrue()
        ->and($ikk->percentage())->toBe(120.0);
});

it('tidak membagi dengan nol saat jumlah penduduk belum diisi', function () {
    demografiUsia(['25-34' => 10_000]);

    expect(PublicInformationIndex::make(Period::fromKey('30'), null, 0)->percentage())
        ->toBe(0.0);
});

it('melaporkan nol saat belum ada data demografi sama sekali', function () {
    $ikk = PublicInformationIndex::make(Period::fromKey('30'), null, 456_333);

    expect($ikk->numerator())->toBe(0)
        ->and($ikk->percentage())->toBe(0.0)
        ->and($ikk->exceedsPopulation())->toBeFalse();
});

it('menyematkan rumus IKK di halaman Rekap', function () {
    demografiUsia(['25-34' => 50_000]);

    Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->assertOk()
        ->assertSee('Rumus Perhitungan IKK')
        ->assertSee('Jumlah penduduk')
        // Contoh resmi ikut ditampilkan sebagai acuan baca.
        ->assertSee('319.580')
        ->assertSee('456.333')
        ->assertSee('70,03%');
});

it('memakai jumlah penduduk yang diubah admin pada halaman Rekap', function () {
    demografiUsia(['25-34' => 50_000]);

    $component = Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->set('population', 200_000);

    expect($component->get('ikk')['penyebut'])->toBe(200_000)
        ->and($component->get('ikk')['persentase'])->toBe(25.0);
});

it('mengikuti perangkat daerah yang dipilih saat menghitung pembilang', function () {
    $dinas = OrganizationalUnit::factory()->create(['type' => 'dinas']);
    $kecamatan = OrganizationalUnit::factory()->kecamatan('Bengalon')->create();

    demografiUsia(['25-34' => 80_000], $dinas);
    demografiUsia(['25-34' => 20_000], $kecamatan);

    $component = Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->set('population', 100_000)
        ->set('units', [$kecamatan->id]);

    expect($component->get('ikk')['pembilang'])->toBe(20_000);
});

it('menyimpan jumlah penduduk supaya berlaku untuk admin lain', function () {
    demografiUsia(['25-34' => 50_000]);

    Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->set('population', 470_000)
        ->call('savePopulation')
        ->assertDispatched('toast');

    expect(Setting::jumlahPenduduk())->toBe(470_000);

    // Admin lain yang membuka halaman tanpa parameter URL ikut memakai nilainya.
    expect(Livewire::actingAs(adminUser())->test(ReportBuilder::class)->get('population'))
        ->toBe(470_000);
});

it('memakai jumlah penduduk tersimpan pada laporan yang dibuat di luar halaman Rekap', function () {
    demografiUsia(['25-34' => 100_000]);

    Setting::put(Setting::JUMLAH_PENDUDUK, 400_000);

    expect(PublicInformationIndex::make(Period::fromKey('30'))->denominator())->toBe(400_000);
});

it('menolak menyimpan jumlah penduduk nol atau negatif', function () {
    Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->set('population', 0)
        ->call('savePopulation');

    // Penyebut nol membuat seluruh persentase jadi nol tanpa penjelasan.
    expect(Setting::get(Setting::JUMLAH_PENDUDUK))->toBeNull();
});

it('hanya mengizinkan peran berwenang menyimpan jumlah penduduk', function () {
    // Izin datang dari peran, jadi peranlah yang dilepas sebelum izin langsung
    // dipasang — kalau tidak, izin bawaan peran tetap berlaku.
    $terbatas = adminUser();
    $terbatas->syncRoles([]);
    $terbatas->syncPermissions(['view-all-insights', 'export-report']);

    Livewire::actingAs($terbatas)
        ->test(ReportBuilder::class)
        ->set('population', 999_999)
        ->call('savePopulation')
        ->assertForbidden();

    expect(Setting::get(Setting::JUMLAH_PENDUDUK))->toBeNull();
});

it('tetap membiarkan angka di URL dipakai tanpa mengubah nilai tersimpan', function () {
    demografiUsia(['25-34' => 50_000]);

    Setting::put(Setting::JUMLAH_PENDUDUK, 456_333);

    $component = Livewire::actingAs(adminUser())
        ->test(ReportBuilder::class)
        ->set('population', 200_000);

    // Mencoba angka lain tidak boleh diam-diam mengubah nilai resmi.
    expect($component->get('ikk')['penyebut'])->toBe(200_000)
        ->and(Setting::jumlahPenduduk())->toBe(456_333);
});
