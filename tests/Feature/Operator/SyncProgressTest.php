<?php

use App\Jobs\SyncSocialAccountInsights;
use App\Livewire\Operator\ConnectionStatus;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Support\SyncProgress;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->unit = OrganizationalUnit::factory()->create();
    $this->operator = operatorUser($this->unit);
    $this->account = SocialAccount::factory()->create(['organizational_unit_id' => $this->unit->id]);
});

it('menandai antre begitu tombol ditekan, sebelum worker mengambilnya', function () {
    Queue::fake();

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->call('syncNow', $this->account->id)
        // Bilah kemajuan harus langsung muncul; menunggu worker berarti layar
        // diam beberapa detik tanpa keterangan apa pun.
        ->assertSee('Menunggu giliran di antrean')
        ->assertSee('Sedang berjalan…');

    Queue::assertPushed(SyncSocialAccountInsights::class);
});

it('memperingatkan saat pekerjaan tak kunjung diambil worker', function () {
    Queue::fake();

    SyncProgress::antre($this->account->id);

    // Diundur supaya terlihat sudah lama menunggu.
    $this->travel(40)->seconds();

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->assertSee('pemroses antrean di server sedang tidak berjalan');
});

it('menampilkan tahap yang sedang dikerjakan berikut persentasenya', function () {
    SyncProgress::tahap($this->account->id, 'Mengambil demografi pengikut', 75);

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->assertSee('Mengambil demografi pengikut')
        ->assertSee('75%')
        ->assertSee('Menyinkronkan data');
});

it('membedakan hasil berhasil, sebagian, dan gagal', function () {
    foreach ([
        ['success', 'Sinkronisasi selesai'],
        ['partial', 'Selesai sebagian'],
        ['failed', 'Sinkronisasi gagal'],
    ] as [$hasil, $judul]) {
        SyncProgress::selesai($this->account->id, $hasil, 'Keterangan hasil.');

        Livewire::actingAs($this->operator)
            ->test(ConnectionStatus::class)
            ->assertSee($judul)
            ->assertSee('Keterangan hasil.');
    }
});

it('berhenti memantau setelah semua sinkronisasi rampung', function () {
    SyncProgress::tahap($this->account->id, 'Mengambil profil akun', 25);

    expect(Livewire::actingAs($this->operator)->test(ConnectionStatus::class)->get('sedangBerjalan'))
        ->toBeTrue();

    SyncProgress::selesai($this->account->id, 'success');

    // Halaman yang menganggur tidak perlu memanggil server tiap dua detik.
    expect(Livewire::actingAs($this->operator)->test(ConnectionStatus::class)->get('sedangBerjalan'))
        ->toBeFalse();
});

it('menutup kartu hasil saat operator menekan tutup', function () {
    SyncProgress::selesai($this->account->id, 'success');

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->call('dismissProgress', $this->account->id)
        ->assertDontSee('Sinkronisasi selesai');

    expect(SyncProgress::ambil($this->account->id))->toBeNull();
});

it('tidak membocorkan kemajuan akun milik OPD lain', function () {
    $lain = SocialAccount::factory()->create([
        'organizational_unit_id' => OrganizationalUnit::factory()->create()->id,
    ]);

    SyncProgress::tahap($lain->id, 'Mengambil profil akun', 25);

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->assertDontSee('Mengambil profil akun');
});
