<?php

use App\Livewire\Admin\UserDirectory;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->unit = OrganizationalUnit::factory()->create(['name' => 'Kecamatan Kaubun']);
    $this->super = superAdminUser($this->unit);
});

it('membuat akun operator baru beserta peran dan OPD-nya', function () {
    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->set('name', 'Budi Petugas')
        ->set('email', 'budi@kutimkab.go.id')
        ->set('password', 'sandi-awal-123')
        ->set('role', 'operator-opd')
        ->set('organizationalUnitId', $this->unit->id)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'budi@kutimkab.go.id')->firstOrFail();

    expect($user->hasRole('operator-opd'))->toBeTrue()
        ->and($user->organizational_unit_id)->toBe($this->unit->id)
        ->and(Hash::check('sandi-awal-123', $user->password))->toBeTrue();
});

it('menolak surel ganda', function () {
    User::factory()->create(['email' => 'sudah@kutimkab.go.id']);

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->set('name', 'Nama')
        ->set('email', 'sudah@kutimkab.go.id')
        ->set('password', 'sandi-awal-123')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

it('mewajibkan kata sandi sepanjang batas minimum', function () {
    $terlaluPendek = str_repeat('a', UserDirectory::PANJANG_SANDI - 1);

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->set('name', 'Nama')
        ->set('email', 'baru@kutimkab.go.id')
        ->set('password', $terlaluPendek)
        ->call('save')
        ->assertHasErrors(['password' => 'min']);
});

it('membiarkan kata sandi lama saat mengubah data tanpa mengisinya', function () {
    $user = User::factory()->create([
        'email' => 'lama@kutimkab.go.id',
        'password' => Hash::make('sandi-lama-123'),
    ]);
    $user->assignRole('operator-opd');

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('edit', $user->id)
        ->set('name', 'Nama Diperbarui')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Nama Diperbarui')
        ->and(Hash::check('sandi-lama-123', $user->password))->toBeTrue();
});

it('mengganti peran, bukan menumpuknya', function () {
    $user = User::factory()->create();
    $user->assignRole('operator-opd');

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('edit', $user->id)
        ->set('role', 'admin-kominfo')
        ->call('save');

    expect($user->fresh()->getRoleNames()->all())->toBe(['admin-kominfo']);
});

it('mencegah super-admin menghapus akunnya sendiri', function () {
    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('delete', $this->super->id)
        ->assertDispatched('toast', type: 'error', message: 'Kamu tidak bisa menghapus akunmu sendiri.');

    expect(User::whereKey($this->super->id)->exists())->toBeTrue();
});

it('menghapus pengguna lain', function () {
    $lain = User::factory()->create();

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('delete', $lain->id);

    expect(User::whereKey($lain->id)->exists())->toBeFalse();
});

it('mencari pengguna berdasarkan nama atau surel', function () {
    User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@kutimkab.go.id']);
    User::factory()->create(['name' => 'Joko Susilo', 'email' => 'joko@kutimkab.go.id']);

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->set('search', 'siti');

    expect($component->get('users')->pluck('email')->all())->toBe(['siti@kutimkab.go.id']);
});

it('membuatkan satu akun operator langsung dari daftar OPD tanpa login', function () {
    $unit = OrganizationalUnit::factory()->create([
        'name' => 'Kecamatan Muara Wahau',
        'slug' => 'kecamatan-muara-wahau',
    ]);

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('generateForUnit', $unit->id);

    $user = User::where('organizational_unit_id', $unit->id)->firstOrFail();

    expect($user->name)->toBe('Operator Kecamatan Muara Wahau')
        ->and($user->email)->toBe('kecamatan-muara-wahau@'.config('app.opd_account_domain'))
        ->and($user->hasRole('operator-opd'))->toBeTrue();

    $tampil = $component->get('justGenerated');
    expect($tampil)->toHaveCount(1)
        ->and($tampil[0]['email'])->toBe($user->email)
        // Kata sandi mentah harus sungguh-sungguh berfungsi untuk masuk —
        // bukan sekadar string acak yang tidak cocok dengan hash-nya.
        ->and(Hash::check($tampil[0]['password'], $user->password))->toBeTrue();
});

it('membuatkan akun untuk seluruh OPD yang belum punya login sekaligus', function () {
    OrganizationalUnit::factory()->count(3)->create();
    // OPD ini sudah punya user — tidak boleh ikut dibuatkan lagi.
    $sudahPunya = OrganizationalUnit::factory()->create();
    User::factory()->create(['organizational_unit_id' => $sudahPunya->id]);

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('generateForAllUnits');

    expect($component->get('justGenerated'))->toHaveCount(3)
        ->and(User::where('organizational_unit_id', $sudahPunya->id)->count())->toBe(1)
        ->and(User::whereHas('roles', fn ($q) => $q->where('name', 'operator-opd'))->count())->toBe(3);
});

it('tidak melakukan apa pun saat seluruh OPD sudah punya akun', function () {
    $unit = OrganizationalUnit::factory()->create();
    User::factory()->create(['organizational_unit_id' => $unit->id]);

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('generateForAllUnits')
        ->assertDispatched('toast', type: 'success', message: 'Semua perangkat daerah sudah punya akun.');

    expect(User::count())->toBe(2); // super-admin yang login + satu yang sudah ada
});

it('menghindari tabrakan surel saat surelnya sudah dipakai akun lain', function () {
    $unit = OrganizationalUnit::factory()->create(['name' => 'Dinas Sosial', 'slug' => 'dinas-sosial']);
    $domain = config('app.opd_account_domain');

    // Akun lain (dibuat manual, tidak tertaut ke OPD ini) kebetulan sudah
    // memakai surel yang seharusnya jadi milik OPD ini.
    User::factory()->create(['email' => "dinas-sosial@{$domain}"]);

    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('generateForUnit', $unit->id);

    expect(User::where('organizational_unit_id', $unit->id)->value('email'))
        ->toBe("dinas-sosial-2@{$domain}");
});

it('mengisi surel dan kata sandi formulir dari OPD yang dipilih tanpa langsung menyimpan', function () {
    $unit = OrganizationalUnit::factory()->create(['name' => 'Badan Riset Daerah', 'slug' => 'badan-riset-daerah']);

    $sebelum = User::count();

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->set('organizationalUnitId', $unit->id)
        ->call('autofillCredentials');

    expect($component->get('email'))->toBe('badan-riset-daerah@'.config('app.opd_account_domain'))
        ->and(strlen($component->get('password')))->toBe(UserDirectory::PANJANG_SANDI)
        // Belum tersimpan — admin masih boleh meninjau sebelum klik Simpan.
        ->and(User::count())->toBe($sebelum);
});

it('menolak mengisi otomatis sebelum OPD dipilih', function () {
    Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->call('autofillCredentials')
        ->assertHasErrors(['organizationalUnitId']);
});

it('menutup seluruh aksi generate dari peran tanpa izin kelola pengguna', function () {
    $unit = OrganizationalUnit::factory()->create();
    $admin = adminUser();

    Livewire::actingAs($admin)->test(UserDirectory::class)
        ->call('generateForUnit', $unit->id)
        ->assertForbidden();

    Livewire::actingAs($admin)->test(UserDirectory::class)
        ->call('generateForAllUnits')
        ->assertForbidden();

    expect(User::where('organizational_unit_id', $unit->id)->exists())->toBeFalse();
});

it('menampilkan daftar OPD yang belum punya akun beserta tombol buatkan', function () {
    $terhubung = OrganizationalUnit::factory()->create(['name' => 'Dinas Punya Akun']);
    User::factory()->create(['organizational_unit_id' => $terhubung->id]);

    $kosong = OrganizationalUnit::factory()->create(['name' => 'Dinas Belum Punya Akun']);

    $component = Livewire::actingAs($this->super)->test(UserDirectory::class);

    $daftar = $component->get('unitsWithoutAccount')->pluck('name');

    expect($daftar)->toContain('Dinas Belum Punya Akun')
        ->and($daftar)->not->toContain('Dinas Punya Akun');

    $component->assertSee('Dinas Belum Punya Akun')
        ->assertSee('Buatkan akun untuk 1 OPD');
});

it('membuka konfirmasi lebih dulu, tanpa langsung menghapus pengguna', function () {
    $lain = User::factory()->create(['name' => 'Petugas Lama', 'email' => 'lama@kutimkab.go.id']);

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('confirmDelete', $lain->id);

    expect($component->get('confirmingDelete'))->toBeTrue()
        ->and($component->get('deletingId'))->toBe($lain->id)
        ->and(User::whereKey($lain->id)->exists())->toBeTrue();

    // Identitas tampil di modal agar admin tahu persis akun mana yang dihapus.
    $component->assertSee('Petugas Lama')
        ->assertSee('lama@kutimkab.go.id');
});

it('menutup konfirmasi dan membersihkan sisa state setelah menghapus', function () {
    $lain = User::factory()->create();

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('confirmDelete', $lain->id)
        ->call('delete', $lain->id);

    expect($component->get('confirmingDelete'))->toBeFalse()
        ->and($component->get('deletingId'))->toBeNull()
        ->and(User::whereKey($lain->id)->exists())->toBeFalse();
});

it('menutup konfirmasi juga saat penghapusan ditolak', function () {
    // Akun sendiri: konfirmasi tetap harus tertutup, bukan menggantung terbuka.
    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('confirmDelete', $this->super->id)
        ->call('delete', $this->super->id);

    expect($component->get('confirmingDelete'))->toBeFalse()
        ->and(User::whereKey($this->super->id)->exists())->toBeTrue();
});

it('menutup konfirmasi hapus dari peran tanpa izin kelola pengguna', function () {
    $lain = User::factory()->create();

    Livewire::actingAs(adminUser())
        ->test(UserDirectory::class)
        ->call('confirmDelete', $lain->id)
        ->assertForbidden();
});

it('membuatkan kata sandi sepanjang yang ditetapkan lewat tombol di formulir', function () {
    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->call('generatePassword');

    expect(strlen($component->get('password')))->toBe(UserDirectory::PANJANG_SANDI);
});

it('membuatkan kata sandi berbeda tiap kali tombol ditekan', function () {
    $component = Livewire::actingAs($this->super)->test(UserDirectory::class)->call('create');

    $sandi = collect(range(1, 5))->map(function () use ($component) {
        $component->call('generatePassword');

        return $component->get('password');
    });

    expect($sandi->unique())->toHaveCount(5);
});

it('membuatkan kata sandi tanpa simbol maupun spasi, agar mudah didiktekan', function () {
    $component = Livewire::actingAs($this->super)->test(UserDirectory::class)->call('create');

    foreach (range(1, 20) as $ignored) {
        $component->call('generatePassword');

        expect($component->get('password'))->toMatch('/^[A-Za-z0-9]+$/');
    }
});

it('juga bisa membuatkan kata sandi saat sedang mengubah pengguna', function () {
    $lain = User::factory()->create();

    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('edit', $lain->id);

    // Saat mengubah, kolom sandi sengaja dikosongkan lebih dulu.
    expect($component->get('password'))->toBe('');

    $component->call('generatePassword');

    expect(strlen($component->get('password')))->toBe(UserDirectory::PANJANG_SANDI);
});

it('kata sandi buatan itu benar-benar lolos validasi dan bisa dipakai masuk', function () {
    $component = Livewire::actingAs($this->super)
        ->test(UserDirectory::class)
        ->call('create')
        ->set('name', 'Petugas Baru')
        ->set('email', 'petugas.baru@kutimkab.go.id')
        ->set('role', 'operator-opd')
        ->call('generatePassword');

    $sandi = $component->get('password');

    $component->call('save')->assertHasNoErrors();

    $user = User::where('email', 'petugas.baru@kutimkab.go.id')->firstOrFail();

    expect(Hash::check($sandi, $user->password))->toBeTrue();
});

it('menutup pembuatan kata sandi dari peran tanpa izin kelola pengguna', function () {
    Livewire::actingAs(adminUser())
        ->test(UserDirectory::class)
        ->call('generatePassword')
        ->assertForbidden();
});
