<?php

use Illuminate\Support\Facades\Blade;

it('menulis angka akhir langsung di HTML, bukan nol yang menunggu JavaScript', function () {
    $html = Blade::render(
        '<x-stat-tile label="Pengikut" value="" :numeric="4353" />',
    );

    /*
     | Dulu isinya "0" dan hanya animasi Alpine yang mengisinya. Begitu Livewire
     | memperbarui DOM — misalnya periode filter diganti — direktifnya tidak
     | dijalankan ulang dan angkanya tersangkut di nol selamanya. Angka yang
     | benar tidak boleh bergantung pada JavaScript yang selesai berjalan.
    */
    expect($html)->toContain('4.353')
        ->and($html)->not->toContain('>0</span>');
});

it('memformat angka mengikuti kelaziman Indonesia', function () {
    expect(Blade::render('<x-stat-tile label="Jangkauan" value="" :numeric="17034" />'))
        ->toContain('17.034');
});
