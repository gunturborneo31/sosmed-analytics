<?php

use App\Livewire\Admin\CountyOverview;
use App\Livewire\Admin\PulseMap;
use App\Livewire\Admin\UnitComparison;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Livewire\Livewire;

beforeEach(function () {
    $this->kominfo = OrganizationalUnit::factory()->create(['name' => 'Dinas Kominfo']);
    $this->admin = adminUser($this->kominfo);
});

function unitDenganPengikut(string $name, int $followers, string $type = 'dinas'): OrganizationalUnit
{
    $unit = OrganizationalUnit::factory()->create(['name' => $name, 'type' => $type]);
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    foreach (range(0, 6) as $back) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($back)->toDateString(),
            'followers_count' => $followers - ($back * 10),
            'reach' => $followers * 2,
            'engagement_rate' => 3.5,
        ]);
    }

    return $unit;
}

it('menampilkan ringkasan, peringkat, dan kartu perlu perhatian', function () {
    unitDenganPengikut('Dinas Kesehatan', 5000);
    unitDenganPengikut('Kecamatan Bengalon', 1200, 'kecamatan');

    Livewire::actingAs($this->admin)
        ->test(CountyOverview::class)
        ->assertOk()
        ->assertSee('Ringkasan Kutai Timur')
        ->assertSee('Perlu Perhatian')
        ->assertSee('Peringkat Perangkat Daerah')
        ->assertSee('Dinas Kesehatan')
        ->assertSee('Kecamatan Bengalon');
});

it('menyaring peringkat sesuai jenis OPD yang dipilih', function () {
    unitDenganPengikut('Dinas Kesehatan', 5000);
    unitDenganPengikut('Kecamatan Bengalon', 1200, 'kecamatan');

    Livewire::actingAs($this->admin)
        ->test(CountyOverview::class)
        ->set('unitType', 'kecamatan')
        ->assertSee('Kecamatan Bengalon')
        ->assertDontSee('Dinas Kesehatan');
});

it('membalik arah urutan saat kolom yang sama diklik dua kali', function () {
    unitDenganPengikut('Dinas Besar', 9000);
    unitDenganPengikut('Dinas Kecil', 300);

    $component = Livewire::actingAs($this->admin)->test(CountyOverview::class);

    expect($component->get('ranking')->first()->unit_name)->toBe('Dinas Besar');

    $component->call('sort', 'followers');

    expect($component->get('direction'))->toBe('asc')
        ->and($component->get('ranking')->first()->unit_name)->toBe('Dinas Kecil');
});

it('mengirim data baru ke grafik saat filter diganti, bukan menggambar ulang', function () {
    unitDenganPengikut('Dinas Kesehatan', 5000);

    Livewire::actingAs($this->admin)
        ->test(CountyOverview::class)
        ->set('period', '7')
        ->assertDispatched('chart:update');
});

it('menyimpan filter di query string agar tautan hasil filter bisa dibagikan', function () {
    Livewire::actingAs($this->admin)
        ->withQueryParams(['periode' => '90', 'jenis' => 'kecamatan'])
        ->test(CountyOverview::class)
        ->assertSet('period', '90')
        ->assertSet('unitType', 'kecamatan');
});

it('tetap tampil rapi saat belum ada satu pun data', function () {
    Livewire::actingAs($this->admin)
        ->test(CountyOverview::class)
        ->assertOk()
        ->assertSee('Belum ada data untuk filter ini');
});

it('menandai 18 kecamatan di peta denyut', function () {
    $component = Livewire::actingAs($this->admin)->test(PulseMap::class)->assertOk();

    $districts = $component->get('districts');

    expect($districts)->toHaveCount(18)
        ->and($districts->pluck('district'))->toContain('Sangatta Utara', 'Busang', 'Sandaran');
});

it('membuat kecamatan aktif berdenyut lebih terang daripada yang sepi', function () {
    $ramai = OrganizationalUnit::factory()->kecamatan('Sangatta Utara')->create();
    $sepi = OrganizationalUnit::factory()->kecamatan('Busang')->create();

    foreach ([[$ramai, 40000], [$sepi, 800]] as [$unit, $reach]) {
        $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->toDateString(),
            'reach' => $reach,
            'followers_count' => $reach,
        ]);
    }

    $districts = Livewire::actingAs($this->admin)->test(PulseMap::class)->get('districts')->keyBy('district');

    expect($districts['Sangatta Utara']['intensity'])->toBeGreaterThan($districts['Busang']['intensity'])
        ->and($districts['Sangatta Utara']['duration'])->toBeLessThan($districts['Busang']['duration'])
        // Kecamatan tanpa akun tetap muncul, tapi redup dan tidak berdenyut.
        ->and($districts['Telen']['connected'])->toBeFalse()
        ->and($districts['Telen']['intensity'])->toBe(0.0);
});

it('membawa admin ke detail kecamatan saat titik peta diklik', function () {
    $unit = OrganizationalUnit::factory()->kecamatan('Kaubun')->create();

    Livewire::actingAs($this->admin)
        ->test(PulseMap::class)
        ->call('goToUnit', $unit->slug)
        ->assertRedirect(route('admin.units.show', $unit->slug));
});

it('tetap menampilkan OPD terpilih walau di luar batas daftar kandidat', function () {
    // 45 OPD dengan nama berawalan A–B mendorong yang berawalan Z keluar
    // dari batas 40 kandidat pertama.
    foreach (range(1, 45) as $n) {
        OrganizationalUnit::factory()->create(['name' => 'Dinas A'.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]);
    }

    $jauh = OrganizationalUnit::factory()->create(['name' => 'Zeta Dinas Terakhir']);

    $component = Livewire::actingAs($this->admin)
        ->test(UnitComparison::class)
        ->set('selected', [$jauh->id]);

    expect($component->get('candidates')->pluck('id')->all())->toContain($jauh->id);

    // Chip-nya harus benar-benar tergambar, agar pilihannya bisa dibatalkan.
    $component->assertSee('Zeta Dinas Terakhir');
});

it('membagi ringkasan per kanal lalu menampilkan gabungannya', function () {
    $unit = unitDenganPengikut('Dinas Kesehatan', 5000);

    $fb = SocialAccount::factory()->create([
        'organizational_unit_id' => $unit->id,
        'platform' => 'facebook',
    ]);
    InsightSnapshot::factory()->create([
        'social_account_id' => $fb->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'followers_count' => 800,
    ]);

    $html = Livewire::actingAs($this->admin)->test(CountyOverview::class)->html();

    $posisiGabungan = mb_strpos($html, 'Gabungan Seluruh Kanal');

    expect($html)->toContain('Instagram')
        ->and($html)->toContain('Facebook')
        ->and($posisiGabungan)->not->toBeFalse()
        // Rincian per kanal dibaca lebih dulu; gabungan menyusul.
        ->and($posisiGabungan)->toBeGreaterThan(mb_strpos($html, 'Gabungan Seluruh Kanal') - 1);
});

it('tidak lagi menampilkan Peta Denyut di dashboard', function () {
    unitDenganPengikut('Dinas Kesehatan', 5000);

    $html = Livewire::actingAs($this->admin)->test(CountyOverview::class)->html();

    expect($html)->not->toContain('Peta Denyut');
});

it('memecah peringkat jadi sepuluh baris per halaman', function () {
    foreach (range(1, 23) as $n) {
        unitDenganPengikut("Dinas Nomor {$n}", 1000 + $n);
    }

    $c = Livewire::actingAs($this->admin)->test(CountyOverview::class);

    // Halaman pertama sepuluh baris, dan totalnya utuh — bukan dipotong 15.
    expect($c->get('ranking')->count())->toBe(10)
        ->and($c->get('ranking')->total())->toBe(23)
        ->and($c->get('ranking')->lastPage())->toBe(3);

    $c->call('setPage', 3);

    expect($c->get('ranking')->count())->toBe(3);
});

it('kembali ke halaman pertama saat urutan atau filter diganti', function () {
    foreach (range(1, 23) as $n) {
        unitDenganPengikut("Dinas Nomor {$n}", 1000 + $n);
    }

    $c = Livewire::actingAs($this->admin)->test(CountyOverview::class)->call('setPage', 3);

    $c->call('sort', 'reach');

    // Urutan baru berarti daftar berbeda; bertahan di halaman 3 membuat pembaca
    // mendarat di tengah data tanpa konteks.
    expect($c->get('ranking')->currentPage())->toBe(1);
});
