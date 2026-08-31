<?php

use App\Models\OrganizationalUnit;

beforeEach(function () {
    $this->admin = adminUser();
});

it('menonjolkan menu Rekap dengan gradient dan gelombang', function () {
    $html = $this->actingAs($this->admin)->get(route('admin.overview'))->getContent();

    $nav = mb_substr($html, mb_strpos($html, 'Navigasi utama'));
    $nav = mb_substr($nav, 0, mb_strpos($nav, '</nav>'));

    expect($nav)->toContain('bg-brand-gradient')
        ->toContain('animate-gelombang')
        ->toContain('animate-gelombang-balik');
});

it('menyisakan menu lain tetap bergaya biasa', function () {
    $html = $this->actingAs($this->admin)->get(route('admin.overview'))->getContent();

    $nav = mb_substr($html, mb_strpos($html, 'Navigasi utama'));
    $nav = mb_substr($nav, 0, mb_strpos($nav, '</nav>'));

    // Penekanan hanya bermakna kalau tunggal — menandai banyak menu sekaligus
    // akan meniadakannya.
    expect(substr_count($nav, 'animate-gelombang-balik'))->toBe(1);
});

it('tidak menampilkan menu Rekap bergaya khusus kepada operator OPD', function () {
    $operator = operatorUser(OrganizationalUnit::factory()->create());

    $html = $this->actingAs($operator)->get(route('operator.accounts'))->getContent();

    $nav = mb_substr($html, mb_strpos($html, 'Navigasi utama'));
    $nav = mb_substr($nav, 0, mb_strpos($nav, '</nav>'));

    expect($nav)->not->toContain('animate-gelombang');
});
