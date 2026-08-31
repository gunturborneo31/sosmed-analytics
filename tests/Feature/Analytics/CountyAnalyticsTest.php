<?php

use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use App\Support\Period;

/** Bangun satu akun dengan deret snapshot harian. */
function akunDenganSnapshot(OrganizationalUnit $unit, array $followersPerDay, array $overrides = []): SocialAccount
{
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id] + $overrides);

    foreach ($followersPerDay as $offset => $followers) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays(count($followersPerDay) - 1 - $offset)->toDateString(),
            'followers_count' => $followers,
            'reach' => $followers * 2,
            'engagement_rate' => 4.0,
        ]);
    }

    return $account;
}

it('menjumlahkan pengikut dari snapshot terbaru tiap akun, bukan seluruh baris', function () {
    $unit = OrganizationalUnit::factory()->create();
    akunDenganSnapshot($unit, [100, 200, 300]);
    akunDenganSnapshot($unit, [50, 60, 70]);

    $summary = CountyAnalytics::make(Period::fromKey('7'))->summary();

    // 300 + 70 — bukan 780 (jumlah seluruh baris).
    expect($summary['followers'])->toBe(370);
});

it('menghitung pertumbuhan terhadap periode sebelumnya yang sama panjang', function () {
    $unit = OrganizationalUnit::factory()->create();

    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    // Periode 7 hari: hari ini mundur 6. Periode sebelumnya: hari ke-7 s/d ke-13.
    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->subDays(7)->toDateString(),
        'followers_count' => 1000,
    ]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 1200,
    ]);

    $summary = CountyAnalytics::make(Period::fromKey('7'))->summary();

    expect($summary['followers'])->toBe(1200)
        ->and($summary['followers_delta'])->toBe(20.0);
});

it('hanya menghitung akun yang statusnya terhubung', function () {
    $unit = OrganizationalUnit::factory()->create();
    akunDenganSnapshot($unit, [500]);
    akunDenganSnapshot($unit, [900], ['status' => SocialAccount::STATUS_REVOKED]);

    expect(CountyAnalytics::make(Period::fromKey('7'))->summary()['followers'])->toBe(500);
});

it('menghormati filter jenis OPD dan platform', function () {
    $dinas = OrganizationalUnit::factory()->create(['type' => 'dinas']);
    $kecamatan = OrganizationalUnit::factory()->kecamatan('Bengalon')->create();

    akunDenganSnapshot($dinas, [1000]);
    akunDenganSnapshot($kecamatan, [400]);
    akunDenganSnapshot($kecamatan, [250], ['platform' => SocialAccount::PLATFORM_FACEBOOK]);

    $period = Period::fromKey('7');

    expect(CountyAnalytics::make($period, AccountScope::make()->unitType('kecamatan'))->summary()['followers'])
        ->toBe(650)
        ->and(CountyAnalytics::make($period, AccountScope::make()->unitType('kecamatan')->platform('instagram'))->summary()['followers'])
        ->toBe(400)
        ->and(CountyAnalytics::make($period, AccountScope::make()->unitType('dinas'))->summary()['followers'])
        ->toBe(1000);
});

it('mengurutkan peringkat OPD sesuai kolom yang diminta', function () {
    $besar = OrganizationalUnit::factory()->create(['name' => 'Dinas Besar']);
    $kecil = OrganizationalUnit::factory()->create(['name' => 'Dinas Kecil']);

    akunDenganSnapshot($besar, [5000]);
    akunDenganSnapshot($kecil, [800]);

    $ranking = CountyAnalytics::make(Period::fromKey('7'))->ranking('followers', 'desc');

    expect($ranking->first()->unit_name)->toBe('Dinas Besar')
        ->and($ranking->last()->unit_name)->toBe('Dinas Kecil');

    $naik = CountyAnalytics::make(Period::fromKey('7'))->ranking('followers', 'asc');

    expect($naik->first()->unit_name)->toBe('Dinas Kecil');
});

it('menghitung satu baris per OPD walau punya beberapa akun', function () {
    $unit = OrganizationalUnit::factory()->create();
    akunDenganSnapshot($unit, [1000]);
    akunDenganSnapshot($unit, [500], ['platform' => SocialAccount::PLATFORM_FACEBOOK]);

    $ranking = CountyAnalytics::make(Period::fromKey('7'))->ranking();

    expect($ranking)->toHaveCount(1)
        ->and($ranking->first()->accounts)->toBe(2)
        ->and($ranking->first()->followers)->toBe(1500);
});

it('mendaftar hal yang perlu didampingi', function () {
    $terhubung = OrganizationalUnit::factory()->create();
    SocialAccount::factory()->create(['organizational_unit_id' => $terhubung->id]);

    OrganizationalUnit::factory()->count(3)->create();                       // belum terhubung
    SocialAccount::factory()->expiring(2)->create();                         // token hampir habis
    SocialAccount::factory()->stale()->create();                             // lama tidak sinkron

    $attention = CountyAnalytics::make(Period::fromKey('30'))->attention();

    expect($attention['unconnected'])->toBe(3)
        ->and($attention['expiring'])->toBe(1)
        ->and($attention['stale'])->toBeGreaterThanOrEqual(1);
});

it('membangun tren harian sepanjang periode yang dipilih', function () {
    $unit = OrganizationalUnit::factory()->create();
    akunDenganSnapshot($unit, [100, 110, 120, 130, 140, 150, 160]);

    $trend = CountyAnalytics::make(Period::fromKey('7'))->trend();

    expect($trend)->toHaveCount(7)
        ->and($trend->first()->followers)->toBe(100)
        ->and($trend->last()->followers)->toBe(160);
});

it('menggabungkan demografi lintas akun jadi satu profil kabupaten', function () {
    $unit = OrganizationalUnit::factory()->create();
    $a = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);
    $b = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    foreach ([$a, $b] as $account) {
        AudienceBreakdown::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->toDateString(),
            'dimension' => AudienceBreakdown::DIMENSION_AGE,
            'data' => ['18-24' => 100, '25-34' => 200],
        ]);
    }

    $age = AudienceAnalytics::make(Period::fromKey('30'))->byAge();

    expect($age['18-24'])->toBe(200)
        ->and($age['25-34'])->toBe(400)
        // Kelompok tanpa data tetap tampil sebagai nol agar grafik utuh.
        ->and($age['55-64'])->toBe(0)
        // Hanya kelompok di dalam rentang 16–64 yang boleh muncul.
        ->and($age->keys()->all())->toBe(['16-17', '18-24', '25-34', '35-44', '45-54', '55-64']);
});

it('tidak menampilkan kelompok usia di luar 16-64 tahun', function () {
    $account = SocialAccount::factory()->create();

    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['13-17' => 1_000, '25-34' => 500, '65+' => 700],
    ]);

    $age = AudienceAnalytics::make(Period::fromKey('30'))->byAge();

    // Label kelompok Meta di luar rentang tidak boleh sampai ke layar.
    expect($age->keys())->not->toContain('13-17')
        ->and($age->keys())->not->toContain('65+')
        // Kelompok termuda tampil sebagai 16-17, diambil 40% dari data Meta.
        ->and($age['16-17'])->toBe(400)
        ->and($age['25-34'])->toBe(500);
});

it('memperkirakan spektrum usia per gender saat Meta tidak mengirim breakdown gabungan', function () {
    $unit = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['25-34' => 1000],
    ]);
    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_GENDER,
        'data' => ['F' => 600, 'M' => 400, 'U' => 0],
    ]);

    ['female' => $female, 'male' => $male] = AudienceAnalytics::make(Period::fromKey('30'))->ageByGender();

    expect($female['25-34'])->toBe(600)
        ->and($male['25-34'])->toBe(400);
});

it('memakai satu tanggal snapshot untuk demografi, bukan menjumlah tiap hari', function () {
    $unit = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['25-34' => 500],
    ]);
    AudienceBreakdown::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['25-34' => 900],
    ]);

    expect(AudienceAnalytics::make(Period::fromKey('30'))->byAge()['25-34'])->toBe(900);
});

it('melaporkan tidak ada pembanding, bukan pertumbuhan +100%, saat periode sebelumnya kosong', function () {
    $unit = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    // Hanya ada data di periode berjalan; 7 hari sebelumnya kosong sama sekali.
    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 40922,
        'reach' => 90000,
    ]);

    $summary = CountyAnalytics::make(Period::fromKey('7'))->summary();

    expect($summary['followers'])->toBe(40922)
        ->and($summary['followers_delta'])->toBeNull()
        ->and($summary['reach_delta'])->toBeNull();

    expect(CountyAnalytics::make(Period::fromKey('7'))->ranking()->first()->growth)->toBeNull();
});

it('tetap melaporkan nol ketika sebelum dan sesudah sama-sama kosong', function () {
    $unit = OrganizationalUnit::factory()->create();
    SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    expect(CountyAnalytics::make(Period::fromKey('7'))->summary()['followers_delta'])->toBe(0.0);
});

it('menaruh OPD tanpa pembanding di ujung peringkat, bukan di puncak', function () {
    $lama = OrganizationalUnit::factory()->create(['name' => 'Dinas Lama']);
    $akunLama = SocialAccount::factory()->create(['organizational_unit_id' => $lama->id]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $akunLama->id,
        'snapshot_date' => now()->subDays(7)->toDateString(),
        'followers_count' => 1000,
    ]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $akunLama->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 1500,
    ]);

    $baru = OrganizationalUnit::factory()->create(['name' => 'Dinas Baru']);
    $akunBaru = SocialAccount::factory()->create(['organizational_unit_id' => $baru->id]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $akunBaru->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 9000,
    ]);

    $peringkat = CountyAnalytics::make(Period::fromKey('7'))->ranking('growth', 'desc');

    expect($peringkat->first()->unit_name)->toBe('Dinas Lama')
        ->and($peringkat->first()->growth)->toBe(50.0)
        ->and($peringkat->last()->unit_name)->toBe('Dinas Baru')
        ->and($peringkat->last()->growth)->toBeNull();
});

it('menghitung penyebut "X dari Y OPD" dengan filter yang sama seperti pembilangnya', function () {
    // Satu OPD terhubung, empat lainnya tidak — tanpa filter: 1 dari 5.
    $terpilih = OrganizationalUnit::factory()->create(['type' => 'dinas']);
    akunDenganSnapshot($terpilih, [1000]);
    OrganizationalUnit::factory()->count(4)->create(['type' => 'dinas']);

    $tanpaFilter = CountyAnalytics::make(Period::fromKey('7'))->summary();

    expect($tanpaFilter['units_connected'])->toBe(1)
        ->and($tanpaFilter['units_total'])->toBe(5);

    /*
     | Begitu difilter ke satu OPD, penyebutnya harus ikut menyempit.
     | Dulu bagian ini menghitung seluruh OPD aktif tanpa peduli filter,
     | sehingga hasilnya "1 / 5" — terbaca seolah 4 OPD lain gagal terhubung,
     | padahal mereka memang tidak ikut dipilih.
    */
    $terfilter = CountyAnalytics::make(
        Period::fromKey('7'),
        AccountScope::make()->units([$terpilih->id]),
    )->summary();

    expect($terfilter['units_connected'])->toBe(1)
        ->and($terfilter['units_total'])->toBe(1);
});

it('mempersempit penyebut sesuai jenis OPD yang dipilih', function () {
    $kecamatan = OrganizationalUnit::factory()->kecamatan('Bengalon')->create();
    akunDenganSnapshot($kecamatan, [500]);
    OrganizationalUnit::factory()->kecamatan('Telen')->create();
    OrganizationalUnit::factory()->count(3)->create(['type' => 'dinas']);

    $summary = CountyAnalytics::make(
        Period::fromKey('7'),
        AccountScope::make()->unitType('kecamatan'),
    )->summary();

    // Hanya dua kecamatan yang dihitung; tiga dinas tidak ikut penyebut.
    expect($summary['units_connected'])->toBe(1)
        ->and($summary['units_total'])->toBe(2);
});

it('tidak mempersempit penyebut hanya karena filter platform', function () {
    $unit = OrganizationalUnit::factory()->create();
    akunDenganSnapshot($unit, [800]);
    OrganizationalUnit::factory()->count(2)->create();

    // Memilih Instagram mempersempit akun yang dihitung, bukan jumlah OPD
    // yang ada di kabupaten.
    $summary = CountyAnalytics::make(
        Period::fromKey('7'),
        AccountScope::make()->platform('instagram'),
    )->summary();

    expect($summary['units_total'])->toBe(3);
});

it('mengabaikan OPD nonaktif dari penyebut', function () {
    $aktif = OrganizationalUnit::factory()->create();
    akunDenganSnapshot($aktif, [400]);
    OrganizationalUnit::factory()->create(['is_active' => false]);

    expect(CountyAnalytics::make(Period::fromKey('7'))->summary()['units_total'])->toBe(1);
});

it('tidak membandingkan jangkauan saat periode pembanding datanya belum lengkap', function () {
    $account = SocialAccount::factory()->create();

    // 30 hari penuh di periode berjalan, hanya satu hari di periode pembanding.
    foreach (range(0, 29) as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'reach' => 1_000,
        ]);
    }

    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->subDays(31)->toDateString(),
        'reach' => 1_000,
    ]);

    $summary = CountyAnalytics::make(Period::fromKey('30'))->summary();

    // 30.000 vs 1.000 bukan pertumbuhan 2.900% — yang berbeda hanya banyaknya
    // hari yang tercatat, bukan capaiannya.
    expect($summary['reach'])->toBe(30_000)
        ->and($summary['reach_delta'])->toBeNull();
});

it('tetap membandingkan jangkauan saat kedua periode sama-sama lengkap', function () {
    $account = SocialAccount::factory()->create();

    foreach (range(0, 59) as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'reach' => $mundur < 30 ? 200 : 100,
        ]);
    }

    $summary = CountyAnalytics::make(Period::fromKey('30'))->summary();

    expect($summary['reach_delta'])->not->toBeNull()
        ->and($summary['reach_delta'])->toBeGreaterThan(0.0);
});

it('memakai demografi terbaru milik tiap akun, bukan satu tanggal terbaru se-kabupaten', function () {
    $lamaDisinkron = SocialAccount::factory()->create();
    $baruDisinkron = SocialAccount::factory()->create();

    // Akun pertama tersinkron kemarin, akun kedua hari ini — hal biasa ketika
    // satu sinkronisasi gagal lalu diulang esoknya.
    AudienceBreakdown::factory()->create([
        'social_account_id' => $lamaDisinkron->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['25-34' => 8_000],
    ]);

    AudienceBreakdown::factory()->create([
        'social_account_id' => $baruDisinkron->id,
        'snapshot_date' => now()->toDateString(),
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['25-34' => 2_000],
    ]);

    // Dulu akun yang tanggalnya tertinggal terbuang seluruhnya dari hitungan,
    // sehingga angkanya anjlok jadi 2.000 tanpa penjelasan apa pun.
    expect(AudienceAnalytics::make(Period::fromKey('30'))->byAge()['25-34'])->toBe(10_000);
});

it('memakai snapshot demografi terakhir tiap akun, bukan menjumlah lintas hari', function () {
    $account = SocialAccount::factory()->create();

    foreach ([2, 1, 0] as $mundur) {
        AudienceBreakdown::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'dimension' => AudienceBreakdown::DIMENSION_AGE,
            'data' => ['25-34' => 5_000],
        ]);
    }

    // Demografi Meta bersifat lifetime — menjumlah tiga hari akan melipatkan
    // pengikut yang sama tiga kali.
    expect(AudienceAnalytics::make(Period::fromKey('30'))->byAge()['25-34'])->toBe(5_000);
});

it('menghitung engagement dari total interaksi sepanjang periode', function () {
    $account = SocialAccount::factory()->create();

    foreach (range(0, 29) as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'followers_count' => 4_000,
            'interactions' => 1,
        ]);
    }

    /*
     | 30 interaksi / 4.000 pengikut = 0,75%.
     |
     | Dulu ini merata-ratakan rasio harian: (1/4000)×100 = 0,025% per hari,
     | yang membulat jadi 0,03% — terbaca seolah nyaris tidak ada interaksi.
    */
    expect(CountyAnalytics::make(Period::fromKey('30'))->summary()['engagement_rate'])
        ->toBe(0.75);
});
