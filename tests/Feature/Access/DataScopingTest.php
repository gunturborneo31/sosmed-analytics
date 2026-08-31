<?php

use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->kominfo = OrganizationalUnit::factory()->create(['name' => 'Dinas Kominfo']);
    $this->kecamatan = OrganizationalUnit::factory()->kecamatan('Sangatta Utara')->create();

    $this->akunKominfo = SocialAccount::factory()->create(['organizational_unit_id' => $this->kominfo->id]);
    $this->akunKecamatan = SocialAccount::factory()->create(['organizational_unit_id' => $this->kecamatan->id]);
});

it('memberi admin Diskominfo akses ke seluruh akun se-kabupaten', function () {
    $admin = userWithRole('admin-kominfo', $this->kominfo);

    expect($admin->seesAllUnits())->toBeTrue()
        ->and($admin->visibleSocialAccounts()->pluck('id'))
        ->toContain($this->akunKominfo->id, $this->akunKecamatan->id);
});

it('membatasi operator OPD hanya pada akun instansinya sendiri', function () {
    $operator = userWithRole('operator-opd', $this->kecamatan);

    $terlihat = $operator->visibleSocialAccounts()->pluck('id');

    expect($operator->seesAllUnits())->toBeFalse()
        ->and($terlihat)->toContain($this->akunKecamatan->id)
        ->and($terlihat)->not->toContain($this->akunKominfo->id);
});

it('menutup akses operator saat ia menebak ID akun OPD lain', function () {
    $operator = userWithRole('operator-opd', $this->kecamatan);

    $dicuri = $operator->visibleSocialAccounts()->find($this->akunKominfo->id);

    expect($dicuri)->toBeNull();
});

it('memberi super-admin seluruh permission', function () {
    $super = userWithRole('super-admin', $this->kominfo);

    foreach (RolePermissionSeeder::PERMISSIONS as $permission) {
        expect($super->can($permission))->toBeTrue("super-admin seharusnya bisa {$permission}");
    }
});

it('melarang admin-kominfo mengelola user, tapi mengizinkan kelola OPD', function () {
    $admin = userWithRole('admin-kominfo', $this->kominfo);

    expect($admin->can('manage-users'))->toBeFalse()
        ->and($admin->can('manage-organizational-units'))->toBeTrue()
        ->and($admin->can('export-report'))->toBeTrue();
});

it('melarang operator OPD merekap lintas OPD atau mengekspor laporan', function () {
    $operator = userWithRole('operator-opd', $this->kecamatan);

    expect($operator->can('view-all-insights'))->toBeFalse()
        ->and($operator->can('export-report'))->toBeFalse()
        ->and($operator->can('connect-social-account'))->toBeTrue();
});
