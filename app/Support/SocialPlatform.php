<?php

namespace App\Support;

/**
 * Identitas visual platform media sosial.
 *
 * Ini merek pihak lain, jadi warnanya sengaja tidak diambil dari token desain
 * aplikasi — Instagram tetap gradiennya, Facebook tetap birunya, di mode terang
 * maupun gelap. Yang disesuaikan hanya nada teks: warna asli terlalu gelap untuk
 * dibaca di latar gelap, sehingga ada varian yang lebih terang khusus mode gelap.
 *
 * Dikumpulkan di satu kelas supaya lencana dan teks username tidak menyimpan
 * salinan peta warna masing-masing.
 */
final class SocialPlatform
{
    /** @var array<string, array{label: string, tile: string, text: string}> */
    private const MEREK = [
        'instagram' => [
            'label' => 'Instagram',
            'tile' => 'background-image: linear-gradient(45deg,#F09433 0%,#E6683C 25%,#DC2743 50%,#CC2366 75%,#BC1888 100%);',
            'text' => 'text-[#C13584] dark:text-[#E981B4]',
        ],
        'facebook' => [
            'label' => 'Facebook',
            'tile' => 'background-color: #1877F2;',
            'text' => 'text-[#1877F2] dark:text-[#6BAAF9]',
        ],
    ];

    public static function label(?string $platform): string
    {
        return self::MEREK[$platform]['label'] ?? ucfirst((string) $platform);
    }

    /** Gaya latar untuk kotak lencana; kosong bila platformnya tidak dikenali. */
    public static function tileStyle(?string $platform): string
    {
        return self::MEREK[$platform]['tile'] ?? '';
    }

    /** Kelas warna teks untuk username, sudah mencakup penyesuaian mode gelap. */
    public static function textClass(?string $platform): string
    {
        return self::MEREK[$platform]['text'] ?? 'text-ink-muted';
    }

    /**
     * Warna tunggal untuk grafik. Instagram tidak bisa memakai gradiennya di
     * sini, jadi diambil warna yang paling mewakili; gabungan seluruh kanal
     * memakai warna merek aplikasi.
     */
    public static function chartColor(?string $platform): string
    {
        return match ($platform) {
            'instagram' => '#C13584',
            'facebook' => '#1877F2',
            default => '#3E68B2',
        };
    }

    public static function isKnown(?string $platform): bool
    {
        return isset(self::MEREK[$platform]);
    }
}
