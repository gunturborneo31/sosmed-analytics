<?php

use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('membuat 18 kecamatan Kutai Timur', function () {
    $this->seed(OrganizationalUnitSeeder::class);

    expect(OrganizationalUnit::ofType('kecamatan')->count())->toBe(18)
        ->and(OrganizationalUnit::ofType('kecamatan')->pluck('district'))
        ->toContain('Sangatta Utara', 'Muara Wahau', 'Busang', 'Teluk Pandan');
});

it('mengisi dinas, badan, dan sekretariat', function () {
    $this->seed(OrganizationalUnitSeeder::class);

    expect(OrganizationalUnit::ofType('dinas')->count())->toBeGreaterThan(20)
        ->and(OrganizationalUnit::ofType('badan')->count())->toBeGreaterThan(5)
        ->and(OrganizationalUnit::ofType('sekretariat')->count())->toBe(2);
});

it('aman dijalankan dua kali tanpa menggandakan data', function () {
    $this->seed(OrganizationalUnitSeeder::class);
    $pertama = OrganizationalUnit::count();

    $this->seed(OrganizationalUnitSeeder::class);

    expect(OrganizationalUnit::count())->toBe($pertama);
});

it('membuat 3 role dan 7 permission sesuai brief', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(3)
        ->and(Permission::count())->toBe(7)
        ->and(Role::pluck('name'))->toContain('super-admin', 'admin-kominfo', 'operator-opd');
});

it('menyiapkan akun demo dengan role dan OPD yang benar', function () {
    $this->seed(DatabaseSeeder::class);

    $operator = User::where('email', 'operator@kutimkab.go.id')->firstOrFail();

    expect($operator->hasRole('operator-opd'))->toBeTrue()
        ->and($operator->organizationalUnit->name)->toBe('Kecamatan Sangatta Utara')
        ->and(User::where('email', 'super@kutimkab.go.id')->first()->hasRole('super-admin'))->toBeTrue();
});
