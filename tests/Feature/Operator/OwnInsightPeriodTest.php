<?php

use App\Livewire\Operator\OwnInsight;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Livewire\Livewire;

beforeEach(function () {
    $this->unit = OrganizationalUnit::factory()->create();
    $this->operator = operatorUser($this->unit);
    $this->account = SocialAccount::factory()->create(['organizational_unit_id' => $this->unit->id]);

    // 90 hari riwayat, jangkauan 10 per hari.
    foreach (range(0, 89) as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $this->account->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'followers_count' => 4_353,
            'reach' => 10,
        ]);
    }
});

it('memperbarui angka ringkasan saat periode diganti', function () {
    $c = Livewire::actingAs($this->operator)->test(OwnInsight::class);

    $c->set('period', '7');
    expect($c->get('summary')['reach'])->toBe(70);

    $c->set('period', '90');
    expect($c->get('summary')['reach'])->toBe(900);

    // Kembali ke rentang semula harus memberi angka semula, bukan nol.
    $c->set('period', '7');
    expect($c->get('summary')['reach'])->toBe(70)
        ->and($c->get('summary')['followers'])->toBe(4_353);
});

it('tidak lagi 500 saat rentang kustom dipilih tanpa tanggal', function () {
    $this->actingAs($this->operator)
        ->get(route('operator.insight', ['periode' => 'custom']))
        ->assertOk();
});

it('memakai rentang kustom setelah kedua tanggalnya terisi', function () {
    $c = Livewire::actingAs($this->operator)
        ->test(OwnInsight::class)
        ->set('period', 'custom')
        // Sebelum tanggal terisi, dipakai rentang bawaan — bukan galat.
        ->assertOk()
        ->set('from', now()->subDays(4)->toDateString())
        ->set('until', now()->toDateString());

    expect($c->get('summary')['reach'])->toBe(50);
});

it('menampilkan angka ringkasan langsung di HTML, bukan bergantung pada animasi', function () {
    $html = Livewire::actingAs($this->operator)->test(OwnInsight::class)->set('period', '7')->html();

    // Kalau isinya "0" dan hanya JavaScript yang mengisinya, angka akan
    // tertinggal nol setiap kali Livewire memperbarui DOM.
    expect($html)->toContain('>4.353<')
        ->and($html)->not->toContain('x-count-up="4353">0<');
});

it('memisahkan insight per kanal dan menaruh gabungan di paling bawah', function () {
    SocialAccount::factory()->create([
        'organizational_unit_id' => $this->unit->id,
        'platform' => 'facebook',
    ]);

    $html = Livewire::actingAs($this->operator)->test(OwnInsight::class)->html();

    $posisiInstagram = mb_strpos($html, 'tren-instagram');
    $posisiFacebook = mb_strpos($html, 'tren-facebook');
    $posisiGabungan = mb_strpos($html, 'Gabungan Seluruh Kanal');

    // Instagram dan Facebook berdiri sendiri lebih dulu; angka gabungan
    // berguna untuk laporan tapi menutupi perbedaan antar kanal kalau di atas.
    expect($posisiInstagram)->not->toBeFalse()
        ->and($posisiFacebook)->not->toBeFalse()
        ->and($posisiGabungan)->not->toBeFalse()
        ->and($posisiGabungan)->toBeGreaterThan($posisiInstagram)
        ->and($posisiGabungan)->toBeGreaterThan($posisiFacebook);
});

it('tidak menampilkan bagian gabungan saat hanya satu kanal terhubung', function () {
    $html = Livewire::actingAs($this->operator)->test(OwnInsight::class)->html();

    // Menggabungkan satu kanal dengan dirinya sendiri hanya mengulang angka.
    expect($html)->toContain('tren-instagram')
        ->and($html)->not->toContain('Gabungan Seluruh Kanal');
});

it('menghitung tiap kanal dari akunnya sendiri', function () {
    $fb = SocialAccount::factory()->create([
        'organizational_unit_id' => $this->unit->id,
        'platform' => 'facebook',
    ]);

    InsightSnapshot::factory()->create([
        'social_account_id' => $fb->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'followers_count' => 700,
        'reach' => 99,
    ]);

    $c = Livewire::actingAs($this->operator)->test(OwnInsight::class)->set('period', '7');

    $instagram = $c->instance()->summaryFor('instagram');
    $facebook = $c->instance()->summaryFor('facebook');
    $gabungan = $c->get('summary');

    expect($instagram['followers'])->toBe(4_353)
        ->and($facebook['followers'])->toBe(700)
        ->and($gabungan['followers'])->toBe(5_053);
});

it('menampilkan keterangan saat demografi sebuah kanal belum tersedia', function () {
    $html = Livewire::actingAs($this->operator)->test(OwnInsight::class)->html();

    // Bagan kosong tanpa keterangan terbaca seperti aplikasi yang rusak.
    expect($html)->toContain('Data usia belum tersedia');
});
