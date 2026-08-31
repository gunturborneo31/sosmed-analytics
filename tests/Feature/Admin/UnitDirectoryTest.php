<?php

use App\Exports\CountyRecapExport;
use App\Jobs\DispatchAllAccountSyncs;
use App\Livewire\Admin\UnitDirectory;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Analytics\AccountScope;
use App\Support\Period;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->super = superAdminUser();
});

it('menambahkan perangkat daerah baru beserta slug-nya', function () {
    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('create')
        ->set('name', 'Dinas Ketahanan Pangan')
        ->set('type', 'dinas')
        ->set('contactPerson', 'Budi')
        ->set('contactPhone', '081234567890')
        ->call('save')
        ->assertHasNoErrors();

    $unit = OrganizationalUnit::where('name', 'Dinas Ketahanan Pangan')->firstOrFail();

    expect($unit->slug)->toBe('dinas-ketahanan-pangan')
        ->and($unit->type)->toBe('dinas')
        ->and($unit->contact_person)->toBe('Budi')
        ->and($unit->is_active)->toBeTrue()
        ->and($unit->district)->toBeNull();
});

it('memberi akhiran saat slug sudah terpakai', function () {
    OrganizationalUnit::factory()->create(['name' => 'Dinas Sosial', 'slug' => 'dinas-sosial']);

    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('create')
        ->set('name', 'Dinas Sosial')
        ->set('type', 'dinas')
        ->call('save')
        ->assertHasNoErrors();

    expect(OrganizationalUnit::where('name', 'Dinas Sosial')->pluck('slug')->all())
        ->toBe(['dinas-sosial', 'dinas-sosial-2']);
});

it('mempertahankan slug saat nama diubah, agar tautan detail tidak patah', function () {
    $unit = OrganizationalUnit::factory()->create([
        'name' => 'Dinas Kesehatan',
        'slug' => 'dinas-kesehatan',
    ]);

    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('edit', $unit->id)
        ->set('name', 'Dinas Kesehatan dan Keluarga Berencana')
        ->call('save')
        ->assertHasNoErrors();

    $unit->refresh();

    expect($unit->name)->toBe('Dinas Kesehatan dan Keluarga Berencana')
        ->and($unit->slug)->toBe('dinas-kesehatan');
});

it('mewajibkan nama kecamatan saat jenisnya kecamatan', function () {
    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('create')
        ->set('name', 'Kecamatan Baru')
        ->set('type', 'kecamatan')
        ->set('district', '')
        ->call('save')
        ->assertHasErrors(['district' => 'required_if']);
});

it('mengosongkan nama kecamatan bila jenisnya bukan kecamatan', function () {
    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('create')
        ->set('name', 'Badan Riset dan Inovasi Daerah')
        ->set('type', 'badan')
        ->set('district', 'Bengalon')
        ->call('save')
        ->assertHasNoErrors();

    expect(OrganizationalUnit::where('name', 'Badan Riset dan Inovasi Daerah')->first()->district)
        ->toBeNull();
});

it('menolak nama yang kosong', function () {
    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('menonaktifkan dan mengaktifkan kembali tanpa menghapus data', function () {
    $unit = OrganizationalUnit::factory()->create(['is_active' => true]);

    $component = Livewire::actingAs($this->super)->test(UnitDirectory::class);

    $component->call('toggleActive', $unit->id);
    expect($unit->fresh()->is_active)->toBeFalse();

    $component->call('toggleActive', $unit->id);
    expect($unit->fresh()->is_active)->toBeTrue();
});

it('menghapus perangkat daerah yang belum punya akun maupun pengguna', function () {
    $unit = OrganizationalUnit::factory()->create();

    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('delete', $unit->id);

    expect(OrganizationalUnit::whereKey($unit->id)->exists())->toBeFalse();
});

it('menolak menghapus OPD yang masih punya akun, agar riwayat insight tidak ikut musnah', function () {
    $unit = OrganizationalUnit::factory()->create(['name' => 'Dinas Perhubungan']);
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);
    foreach (range(0, 2) as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
        ]);
    }

    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('delete', $unit->id)
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'error'
            && str_contains($params['message'], 'Putuskan akunnya lebih dulu'));

    expect(OrganizationalUnit::whereKey($unit->id)->exists())->toBeTrue()
        ->and(SocialAccount::count())->toBe(1)
        ->and(InsightSnapshot::count())->toBe(3);
});

it('menolak menghapus OPD yang masih menaungi pengguna', function () {
    $unit = OrganizationalUnit::factory()->create();
    User::factory()->create(['organizational_unit_id' => $unit->id]);

    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('delete', $unit->id)
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'error'
            && str_contains($params['message'], 'Pindahkan pengguna'));

    expect(OrganizationalUnit::whereKey($unit->id)->exists())->toBeTrue();
});

it('menutup seluruh aksi ubah-data dari peran tanpa izin kelola', function () {
    $unit = OrganizationalUnit::factory()->create();
    // admin-kominfo kini juga boleh mengelola OPD (§ keputusan pengguna); yang
    // benar-benar tidak punya izin ini hanya operator-opd.
    $operator = operatorUser();

    foreach ([
        fn ($c) => $c->call('create'),
        fn ($c) => $c->call('edit', $unit->id),
        fn ($c) => $c->call('save'),
        fn ($c) => $c->call('toggleActive', $unit->id),
        fn ($c) => $c->call('delete', $unit->id),
    ] as $aksi) {
        $aksi(Livewire::actingAs($operator)->test(UnitDirectory::class))->assertForbidden();
    }

    expect(OrganizationalUnit::whereKey($unit->id)->exists())->toBeTrue();
});

it('menampilkan tombol kelola untuk admin-kominfo', function () {
    OrganizationalUnit::factory()->create(['name' => 'Dinas Pariwisata']);

    Livewire::actingAs(adminUser())
        ->test(UnitDirectory::class)
        ->assertOk()
        ->assertSee('Dinas Pariwisata')
        ->assertSee('Tambah perangkat daerah');
});

it('menyaring OPD yang dinonaktifkan', function () {
    OrganizationalUnit::factory()->create(['name' => 'Dinas Aktif', 'is_active' => true]);
    OrganizationalUnit::factory()->create(['name' => 'Dinas Nonaktif', 'is_active' => false]);

    Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->set('status', 'nonaktif')
        ->assertSee('Dinas Nonaktif')
        ->assertDontSee('Dinas Aktif');
});

it('membuka konfirmasi lebih dulu, tanpa langsung menghapus', function () {
    $unit = OrganizationalUnit::factory()->create(['name' => 'Dinas Perikanan']);

    $component = Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('confirmDelete', $unit->id);

    expect($component->get('confirmingDelete'))->toBeTrue()
        ->and($component->get('deletingId'))->toBe($unit->id)
        // Baru konfirmasi — datanya harus masih utuh.
        ->and(OrganizationalUnit::whereKey($unit->id)->exists())->toBeTrue();

    // Nama OPD tampil di modal agar admin tahu persis apa yang akan dihapus.
    $component->assertSee('Dinas Perikanan');
});

it('menutup konfirmasi dan membersihkan sisa state setelah menghapus', function () {
    $unit = OrganizationalUnit::factory()->create();

    $component = Livewire::actingAs($this->super)
        ->test(UnitDirectory::class)
        ->call('confirmDelete', $unit->id)
        ->call('delete', $unit->id);

    expect($component->get('confirmingDelete'))->toBeFalse()
        ->and($component->get('deletingId'))->toBeNull()
        ->and(OrganizationalUnit::whereKey($unit->id)->exists())->toBeFalse();
});

it('menutup konfirmasi hapus dari peran tanpa izin kelola', function () {
    $unit = OrganizationalUnit::factory()->create();

    Livewire::actingAs(operatorUser())
        ->test(UnitDirectory::class)
        ->call('confirmDelete', $unit->id)
        ->assertForbidden();
});

it('menaruh catatan pendampingan di kartu paling atas halaman Perangkat Daerah', function () {
    OrganizationalUnit::factory()->count(3)->create();

    $html = Livewire::actingAs(adminUser())->test(UnitDirectory::class)->html();

    expect($html)->toContain('Perlu Pendampingan')
        ->toContain('Belum menghubungkan akun')
        ->toContain('Token kedaluwarsa dalam 7 hari')
        // Kartunya harus mendahului saringan dan tabelnya.
        ->and(mb_strpos($html, 'Perlu Pendampingan'))->toBeLessThan(mb_strpos($html, 'Nama dinas, badan, atau kecamatan'));
});

it('tidak lagi memuat catatan pendampingan di laporan PDF', function () {
    $periode = Period::fromKey('30');
    $scope = AccountScope::make();

    $html = view('exports.county-recap', array_merge(
        (new CountyRecapExport($periode, $scope))->context(),
        ['generatedBy' => 'Penguji', 'logo' => null],
    ))->render();

    // Urusan operasional internal tidak ikut beredar bersama laporan capaian.
    expect($html)->not->toContain('Catatan Pendampingan')
        ->not->toContain('bahan pendampingan teknis');
});

it('mengantrekan sinkronisasi seluruh akun dari halaman Perangkat Daerah', function () {
    Queue::fake();

    SocialAccount::factory()->count(3)->create();

    Livewire::actingAs(adminUser())
        ->test(UnitDirectory::class)
        ->call('syncAll')
        ->assertDispatched('toast');

    Queue::assertPushed(DispatchAllAccountSyncs::class);
});

it('menolak sinkron semua bagi yang tidak berwenang', function () {
    Queue::fake();

    $terbatas = adminUser();
    $terbatas->syncRoles([]);
    $terbatas->syncPermissions(['view-all-insights']);

    Livewire::actingAs($terbatas)
        ->test(UnitDirectory::class)
        ->call('syncAll')
        ->assertForbidden();

    Queue::assertNotPushed(DispatchAllAccountSyncs::class);
});

it('memberi tahu saat belum ada akun yang bisa disinkronkan', function () {
    Queue::fake();

    Livewire::actingAs(adminUser())
        ->test(UnitDirectory::class)
        ->call('syncAll');

    Queue::assertNotPushed(DispatchAllAccountSyncs::class);
});
