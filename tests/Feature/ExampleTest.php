<?php

use App\Models\OrganizationalUnit;

it('mengarahkan akar aplikasi ke dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

it('mengantar tamu dari dashboard ke halaman masuk', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('memberi tiap peran pintu masuknya sendiri', function () {
    $unit = OrganizationalUnit::factory()->create();

    $this->actingAs(adminUser($unit))->get('/')->assertRedirect('/dashboard');
    $this->actingAs(operatorUser($unit))->get('/dashboard')->assertRedirect(route('operator.accounts'));
});

it('menjawab health check', function () {
    $this->get('/up')->assertOk();
});
