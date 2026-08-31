<?php

use App\Models\OrganizationalUnit;

beforeEach(function () {
    $this->unit = OrganizationalUnit::factory()->create();
});

$adminPages = [
    '/admin',
    '/admin/perangkat-daerah',
    '/admin/demografi',
    '/admin/perbandingan',
    '/admin/rekap',
    '/admin/log-sinkronisasi',
];

it('membuka seluruh halaman admin untuk admin Diskominfo', function (string $path) {
    $this->actingAs(adminUser($this->unit))->get($path)->assertOk();
})->with($adminPages);

it('menutup seluruh halaman admin dari operator OPD', function (string $path) {
    $this->actingAs(operatorUser($this->unit))->get($path)->assertForbidden();
})->with($adminPages);

it('hanya mengizinkan super-admin mengelola pengguna', function () {
    $this->actingAs(superAdminUser($this->unit))->get('/admin/pengguna')->assertOk();
    $this->actingAs(adminUser($this->unit))->get('/admin/pengguna')->assertForbidden();
    $this->actingAs(operatorUser($this->unit))->get('/admin/pengguna')->assertForbidden();
});

it('membuka halaman operator untuk petugas OPD', function () {
    $operator = operatorUser($this->unit);

    $this->actingAs($operator)->get('/akun')->assertOk();
    $this->actingAs($operator)->get('/insight')->assertOk();
});

it('menampilkan detail OPD lewat slug', function () {
    $this->actingAs(adminUser($this->unit))
        ->get('/admin/perangkat-daerah/'.$this->unit->slug)
        ->assertOk()
        ->assertSee($this->unit->name);
});
