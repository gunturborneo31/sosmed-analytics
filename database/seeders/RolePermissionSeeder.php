<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'view-all-insights',
        'view-own-insights',
        'connect-social-account',
        'export-report',
        'manage-organizational-units',
        'manage-users',
        'trigger-manual-sync',
    ];

    /** @var array<string, list<string>> */
    public const ROLES = [
        // Diskominfo — akses penuh
        'super-admin' => self::PERMISSIONS,

        // Diskominfo — lihat & rekap semua, tidak bisa kelola user
        'admin-kominfo' => [
            'view-all-insights',
            'view-own-insights',
            'export-report',
            'trigger-manual-sync',
            'manage-organizational-units',
        ],

        // Petugas OPD — hanya akunnya sendiri
        'operator-opd' => [
            'view-own-insights',
            'connect-social-account',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
