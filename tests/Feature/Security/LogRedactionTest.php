<?php

use App\Logging\RedactsSecrets;
use Monolog\Level;
use Monolog\LogRecord;

function catat(string $pesan, array $konteks = []): LogRecord
{
    return (new RedactsSecrets)(new LogRecord(
        new DateTimeImmutable,
        'testing',
        Level::Error,
        $pesan,
        $konteks,
    ));
}

it('menyamarkan token terenkripsi yang terbawa binding query', function () {
    // Bentuk nyata: QueryException menyusun pesan berisi seluruh binding,
    // termasuk kolom access_token yang sudah terenkripsi.
    $terenkripsi = 'eyJpdiI6IjJ6UTVHVlBmZjVUenNwaXRNa3VBdWc9PSIsInZhbHVlIjoiUGhnQWFlbzQrZzFud2hNT2grNXh0c050WFk4SDFzQSsxWllJYTc2WGlOU3l6R000YzJFeEYrT2g4TWsr';

    $hasil = catat("insert into social_accounts values (instagram, {$terenkripsi}, connected)");

    expect($hasil->message)->not->toContain($terenkripsi)
        ->and($hasil->message)->toContain('[token-disamarkan]')
        // Bagian lain pesannya tetap utuh supaya masih bisa ditelusuri.
        ->and($hasil->message)->toContain('insert into social_accounts');
});

it('menyamarkan token akses Meta dan kode otorisasi', function () {
    $hasil = catat('gagal memanggil IGQVJXbGFhaFprTm5zdmR0YzFhWDBsY0VoVFRtMkE dengan code=AQIfqRr8UJT4iW1BSJWesmWAYQZkR77fAN43pUJPELkD');

    expect($hasil->message)->toContain('[token-disamarkan]')
        ->and($hasil->message)->toContain('code=[disamarkan]')
        ->and($hasil->message)->not->toContain('AQIfqRr8UJT4');
});

it('ikut menyamarkan rahasia yang bersarang di konteks', function () {
    $hasil = catat('otorisasi gagal', [
        'unit' => 'kominfo',
        'detail' => ['pesan' => 'token EAAJZAbCdEfGhIjKlMnOpQrStUvWxYz0123456789 ditolak'],
    ]);

    expect($hasil->context['detail']['pesan'])->toContain('[token-disamarkan]')
        // Data biasa tidak ikut disamarkan.
        ->and($hasil->context['unit'])->toBe('kominfo');
});

it('membiarkan pesan biasa apa adanya', function () {
    $hasil = catat('Sinkronisasi akun 01a027c8 selesai dalam 1240 ms.');

    expect($hasil->message)->toBe('Sinkronisasi akun 01a027c8 selesai dalam 1240 ms.');
});
