<?php

use App\Livewire\Admin\DemographicsPanel;
use App\Livewire\Admin\UnitComparison;
use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Livewire\Livewire;

/** OPD dengan akun Instagram dan Facebook, keduanya berisi data. */
function opdDuaKanal(string $nama): OrganizationalUnit
{
    $unit = OrganizationalUnit::factory()->create(['name' => $nama]);

    foreach (['instagram' => 3_000, 'facebook' => 1_000] as $platform => $pengikut) {
        $akun = SocialAccount::factory()->create([
            'organizational_unit_id' => $unit->id,
            'platform' => $platform,
        ]);

        InsightSnapshot::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDay()->toDateString(),
            'followers_count' => $pengikut,
            'reach' => 100,
            'interactions' => 10,
        ]);

        AudienceBreakdown::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDay()->toDateString(),
            'dimension' => AudienceBreakdown::DIMENSION_AGE,
            'data' => ['25-34' => $pengikut],
        ]);
    }

    return $unit;
}

it('memisahkan demografi per kanal lalu menampilkan gabungannya', function () {
    opdDuaKanal('Dinas Kesehatan');

    $c = Livewire::actingAs(adminUser())->test(DemographicsPanel::class);

    // Usia pengikut Instagram condong lebih muda daripada Facebook; menggabung
    // sejak awal menutupi perbedaan yang justru jadi bahan keputusan.
    expect($c->get('platforms'))->toBe(['instagram', 'facebook'])
        ->and($c->html())->toContain('Gabungan Seluruh Kanal');
});

it('tidak lagi menyaring demografi lewat jenis OPD', function () {
    opdDuaKanal('Dinas Kesehatan');

    expect(Livewire::actingAs(adminUser())->test(DemographicsPanel::class)->html())
        ->not->toContain('Jenis OPD');
});

it('tidak memecah kanal saat platform sudah disaring', function () {
    opdDuaKanal('Dinas Kesehatan');

    $c = Livewire::actingAs(adminUser())->test(DemographicsPanel::class)->set('platform', 'instagram');

    // Memecah satu kanal dari dirinya sendiri hanya mengulang angka yang sama.
    expect($c->get('platforms'))->toBe([])
        ->and($c->html())->not->toContain('Gabungan Seluruh Kanal');
});

it('merinci perbandingan per kanal di samping angka gabungannya', function () {
    $unit = opdDuaKanal('Dinas Kesehatan');
    $lain = opdDuaKanal('Dinas Pariwisata');

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$unit->id, $lain->id]);

    $baris = $c->get('comparison')->firstWhere('unit.id', $unit->id);

    expect(array_keys($baris['per_kanal']))->toBe(['instagram', 'facebook'])
        ->and($baris['per_kanal']['instagram']['followers'])->toBe(3_000)
        ->and($baris['per_kanal']['facebook']['followers'])->toBe(1_000)
        // Gabungannya tetap ada dan memang penjumlahan keduanya.
        ->and($baris['summary']['followers'])->toBe(4_000)
        ->and($c->html())->toContain('Pengikut (gabungan)');
});

it('tidak lagi menyaring kandidat perbandingan lewat jenis OPD', function () {
    OrganizationalUnit::factory()->create(['name' => 'Dinas Kesehatan', 'type' => 'dinas']);
    OrganizationalUnit::factory()->kecamatan('Bengalon')->create();

    $c = Livewire::actingAs(adminUser())->test(UnitComparison::class)->set('unitType', 'kecamatan');

    // Halaman ini sudah punya pemilih OPD sendiri; saringan kedua hanya
    // mengosongkan daftar tanpa penjelasan.
    expect($c->get('candidates')->pluck('name'))->toContain('Dinas Kesehatan')
        ->and($c->html())->not->toContain('Jenis OPD');
});

it('menandai kartu perbandingan dengan kanal yang dimiliki tiap OPD', function () {
    $dua = opdDuaKanal('Dinas Kesehatan');

    $hanyaIg = OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);
    $akun = SocialAccount::factory()->create([
        'organizational_unit_id' => $hanyaIg->id,
        'platform' => 'instagram',
    ]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $akun->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'followers_count' => 500,
    ]);

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$dua->id, $hanyaIg->id]);

    $baris = $c->get('comparison')->keyBy(fn ($r) => $r['unit']->name);

    expect($baris['Dinas Kesehatan']['kanal'])->toBe(['instagram', 'facebook'])
        ->and($baris['Dinas Pariwisata']['kanal'])->toBe(['instagram'])
        // OPD satu kanal tidak perlu dipecah dari gabungannya.
        ->and($baris['Dinas Pariwisata']['per_kanal'])->toBe([]);
});

it('menjelaskan saat OPD tidak punya akun di platform yang disaring', function () {
    // Kartu perbandingan baru muncul setelah minimal dua OPD dipilih.
    $unit = collect(['Dinas Pariwisata', 'Dinas Perhubungan'])->map(function (string $nama) {
        $u = OrganizationalUnit::factory()->create(['name' => $nama]);
        SocialAccount::factory()->create(['organizational_unit_id' => $u->id, 'platform' => 'instagram']);

        return $u->id;
    })->all();

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', $unit)
        ->set('platform', 'facebook');

    // Deretan nol terbaca seperti performa buruk, padahal akunnya memang tidak ada.
    expect($c->get('comparison')->first()['kanal'])->toBe([])
        ->and($c->html())->toContain('Belum punya akun');
});

it('menyebutkan cakupan kanal pada grafik tren perbandingan', function () {
    $unit = opdDuaKanal('Dinas Kesehatan');
    $lain = opdDuaKanal('Dinas Pariwisata');

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$unit->id, $lain->id]);

    expect($c->html())->toContain('Instagram + Facebook digabung');

    $c->set('platform', 'instagram');

    // Grafik yang disaring ke satu kanal terlihat sama saja dengan gabungan.
    expect($c->html())->toContain('Instagram saja');
});

it('menggambar ulang grafik tren saat OPD dipilih lewat klik', function () {
    $a = opdDuaKanal('Dinas Kesehatan');
    $b = opdDuaKanal('Dinas Pariwisata');

    $c = Livewire::actingAs(adminUser())->test(UnitComparison::class);

    // Memilih OPD memakai aksi toggle(), bukan wire:model — hook `updated`
    // tidak terpicu, jadi grafiknya harus disegarkan dari aksi itu sendiri.
    $c->call('toggle', $a->id)->call('toggle', $b->id);

    $c->assertDispatched('chart:update', function (string $event, array $params): bool {
        return $params['id'] === 'tren-perbandingan'
            && count($params['options']['series']) === 2;
    });
});

it('menggambar grafik tren tanpa wire:ignore supaya Livewire menginisialisasinya', function () {
    $a = opdDuaKanal('Dinas Kesehatan');
    $b = opdDuaKanal('Dinas Pariwisata');

    $html = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$a->id, $b->id])
        ->html();

    $i = mb_strpos($html, 'apexChart');
    $wadah = mb_substr($html, max(0, $i - 400), 500);

    /*
     | Grafik ini baru muncul setelah OPD kedua dipilih. Livewire TIDAK
     | menginisialisasi elemen ber-`wire:ignore` yang disisipkannya belakangan,
     | sehingga ApexCharts tidak pernah dijalankan dan yang tampil kotak kosong.
    */
    expect($wadah)->not->toContain('wire:ignore')
        // wire:key yang memuat pilihan memaksa gambar ulang saat pilihan berubah.
        ->and($wadah)->toContain('wire:key="tren-');
});

it('mengganti kunci grafik saat pilihan atau saringan berubah', function () {
    $a = opdDuaKanal('Dinas Kesehatan');
    $b = opdDuaKanal('Dinas Pariwisata');

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$a->id, $b->id]);

    $kunciAwal = $c->html();
    $c->set('platform', 'instagram');

    // Kunci yang sama akan membuat Livewire mempertahankan grafik lama.
    expect($c->html())->not->toBe($kunciAwal)
        ->and($c->html())->toContain('instagram');
});

it('menyediakan dua cara ukur tren: jumlah dan pertumbuhan relatif', function () {
    $besar = OrganizationalUnit::factory()->create(['name' => 'Dinas Besar']);
    $kecil = OrganizationalUnit::factory()->create(['name' => 'Dinas Kecil']);

    foreach ([[$besar, 1_000, 1_437], [$kecil, 4_000, 4_024]] as [$unit, $awal, $akhir]) {
        $akun = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

        InsightSnapshot::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDays(10)->toDateString(),
            'followers_count' => $awal,
        ]);
        InsightSnapshot::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDay()->toDateString(),
            'followers_count' => $akhir,
        ]);
    }

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$besar->id, $kecil->id]);

    $jumlah = collect($c->get('trendChart')['series'])->keyBy('name');

    expect($jumlah['Dinas Besar']['data'])->toBe([1_000, 1_437])
        ->and($c->get('trendChart')['satuanNilai'])->toBe('jumlah');

    $c->call('setMetric', 'pertumbuhan');
    $relatif = collect($c->get('trendChart')['series'])->keyBy('name');

    /*
     | Semua berangkat dari 0%, jadi laju bisa diadu meski ukurannya berbeda:
     | +43,7% jelas terpisah dari +0,6%, padahal pada sumbu absolut keduanya
     | bertumpuk di area yang sama.
    */
    expect($relatif['Dinas Besar']['data'])->toBe([0.0, 43.7])
        ->and($relatif['Dinas Kecil']['data'])->toBe([0.0, 0.6])
        ->and($c->get('trendChart')['satuanNilai'])->toBe('persen');
});

it('menuliskan perubahan tiap OPD sebagai angka, bukan hanya garis', function () {
    $unit = OrganizationalUnit::factory()->create(['name' => 'Dinas Kecil']);
    $akun = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    InsightSnapshot::factory()->create([
        'social_account_id' => $akun->id,
        'snapshot_date' => now()->subDays(10)->toDateString(),
        'followers_count' => 4_327,
    ]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $akun->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'followers_count' => 4_353,
    ]);

    $lain = OrganizationalUnit::factory()->create(['name' => 'Dinas Lain']);

    $c = Livewire::actingAs(adminUser())
        ->test(UnitComparison::class)
        ->set('selected', [$unit->id, $lain->id]);

    $ringkas = $c->get('trendSummary')->keyBy('nama');

    // Pertumbuhan sekecil ini terlihat rata pada grafik; angkanya tetap harus terbaca.
    expect($ringkas['Dinas Kecil']['selisih'])->toBe(26)
        ->and($ringkas['Dinas Kecil']['persen'])->toBe(0.6)
        // OPD tanpa data sama sekali tidak boleh dilaporkan sebagai 0%.
        ->and($ringkas['Dinas Lain']['persen'])->toBeNull()
        ->and($c->html())->toContain('+0,60%');
});
