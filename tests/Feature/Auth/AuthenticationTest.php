<?php

use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('menampilkan halaman masuk', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Social Media Analytics')
        ->assertSee('Diskominfo Kutim');
});

it('tidak menyediakan pendaftaran mandiri', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

it('meloloskan kredensial yang benar', function () {
    $user = User::factory()->create([
        'email' => 'petugas@kutimkab.go.id',
        'password' => Hash::make('rahasia-kuat'),
    ]);

    $this->post('/login', [
        'email' => 'petugas@kutimkab.go.id',
        'password' => 'rahasia-kuat',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('menolak kata sandi yang salah tanpa membocorkan mana yang keliru', function () {
    User::factory()->create(['email' => 'petugas@kutimkab.go.id']);

    $this->from('/login')->post('/login', [
        'email' => 'petugas@kutimkab.go.id',
        'password' => 'salah',
    ])->assertSessionHasErrors(['email' => 'Surel atau kata sandi tidak cocok.']);

    $this->assertGuest();
});

it('mengunci setelah lima percobaan gagal', function () {
    User::factory()->create(['email' => 'petugas@kutimkab.go.id']);

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => 'petugas@kutimkab.go.id', 'password' => 'salah']);
    }

    $this->post('/login', ['email' => 'petugas@kutimkab.go.id', 'password' => 'salah'])
        ->assertSessionHasErrorsIn('default', ['email']);

    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan masuk');
});

it('mengarahkan admin ke panel kabupaten dan operator ke halaman akunnya', function () {
    $unit = OrganizationalUnit::factory()->create();

    $this->actingAs(adminUser($unit))->get('/dashboard')->assertRedirect(route('admin.overview'));
    $this->actingAs(operatorUser($unit))->get('/dashboard')->assertRedirect(route('operator.accounts'));
});

it('menampilkan layar transisi tepat setelah masuk', function () {
    $unit = OrganizationalUnit::factory()->create();
    $admin = adminUser($unit);

    $this->actingAs($admin)
        ->withSession(['baru_masuk' => true])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Menyiapkan dashboard')
        // Tujuan tetap tertulis sebagai tautan sungguhan: tanpa JavaScript pun
        // pengguna tidak terjebak di layar ini.
        ->assertSee(route('admin.overview'));
});

it('tidak mengulang layar transisi saat /dashboard dibuka lagi', function () {
    $unit = OrganizationalUnit::factory()->create();
    $admin = adminUser($unit);

    // Penanda bersifat sekali-pakai — kunjungan berikutnya langsung dialihkan.
    $this->actingAs($admin)->withSession(['baru_masuk' => true])->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.overview'));
});

it('mengantar operator ke halaman akunnya lewat layar transisi', function () {
    $unit = OrganizationalUnit::factory()->create();

    $this->actingAs(operatorUser($unit))
        ->withSession(['baru_masuk' => true])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('operator.accounts'));
});

it('menandai sesi sebagai baru masuk sesudah kredensial diterima', function () {
    User::factory()->create([
        'email' => 'petugas@kutimkab.go.id',
        'password' => Hash::make('rahasia-kuat'),
    ]);

    $this->post('/login', [
        'email' => 'petugas@kutimkab.go.id',
        'password' => 'rahasia-kuat',
    ])->assertSessionHas('baru_masuk', true);
});

it('mengeluarkan pengguna dan membuang sesinya', function () {
    $this->actingAs(operatorUser())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('menahan tamu di seluruh halaman terlindungi', function () {
    foreach (['/dashboard', '/akun', '/insight', '/admin', '/admin/rekap'] as $path) {
        $this->get($path)->assertRedirect(route('login'));
    }
});
