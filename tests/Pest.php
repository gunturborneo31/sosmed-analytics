<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * Buat user dengan peran tertentu. Role & permission di-seed sekali per test
 * yang membutuhkannya, bukan di setiap pemanggilan.
 */
function userWithRole(string $role, ?OrganizationalUnit $unit = null): User
{
    if (Role::where('name', $role)->doesntExist()) {
        (new RolePermissionSeeder)->run();
    }

    $user = User::factory()->create([
        'organizational_unit_id' => $unit?->id,
    ]);

    $user->assignRole($role);

    return $user;
}

function adminUser(?OrganizationalUnit $unit = null): User
{
    return userWithRole('admin-kominfo', $unit ?? OrganizationalUnit::factory()->create());
}

function superAdminUser(?OrganizationalUnit $unit = null): User
{
    return userWithRole('super-admin', $unit ?? OrganizationalUnit::factory()->create());
}

function operatorUser(?OrganizationalUnit $unit = null): User
{
    return userWithRole('operator-opd', $unit ?? OrganizationalUnit::factory()->create());
}
