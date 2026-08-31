<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Kemajuan sinkronisasi satu akun, supaya operator tidak menunggu di depan
 * layar yang diam.
 *
 * Disimpan di cache, bukan di basis data: umurnya pendek, ditulis berkali-kali
 * dalam hitungan detik, dan kalau hilang tidak ada yang rusak — riwayat yang
 * sesungguhnya tetap tercatat di tabel sync_logs.
 *
 * Persentasenya sengaja diturunkan dari TAHAP yang benar-benar dilewati Job,
 * bukan dari penghitung waktu. Bilah yang bergerak sendiri tanpa hubungan
 * dengan pekerjaan nyata hanya menipu — dan paling menyesatkan justru ketika
 * pekerjaannya macet.
 */
final class SyncProgress
{
    public const STATUS_ANTRE = 'antre';

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_SELESAI = 'selesai';

    /** Cukup lama untuk sinkronisasi paling lambat, cukup pendek untuk tidak menumpuk. */
    private const UMUR = 900;

    /** Hasil akhir hanya perlu bertahan sebentar untuk dibaca operator. */
    private const UMUR_HASIL = 90;

    private static function kunci(string $accountId): string
    {
        return "sinkron:{$accountId}";
    }

    /** Dicatat saat tombol ditekan — sebelum worker sempat mengambilnya. */
    public static function antre(string $accountId): void
    {
        Cache::put(self::kunci($accountId), [
            'status' => self::STATUS_ANTRE,
            'tahap' => 'Menunggu giliran di antrean',
            'persen' => 0,
            'sejak' => now()->timestamp,
        ], self::UMUR);
    }

    public static function tahap(string $accountId, string $tahap, int $persen): void
    {
        $sebelumnya = Cache::get(self::kunci($accountId));

        Cache::put(self::kunci($accountId), [
            'status' => self::STATUS_BERJALAN,
            'tahap' => $tahap,
            'persen' => max(0, min(100, $persen)),
            'sejak' => $sebelumnya['sejak'] ?? now()->timestamp,
        ], self::UMUR);
    }

    public static function selesai(string $accountId, string $status, ?string $pesan = null): void
    {
        Cache::put(self::kunci($accountId), [
            'status' => self::STATUS_SELESAI,
            'hasil' => $status,
            'tahap' => $pesan ?? 'Data berhasil diperbarui.',
            'persen' => 100,
            'sejak' => now()->timestamp,
        ], self::UMUR_HASIL);
    }

    /** @return array<string, mixed>|null */
    public static function ambil(string $accountId): ?array
    {
        $data = Cache::get(self::kunci($accountId));

        if (! is_array($data)) {
            return null;
        }

        $umur = now()->timestamp - (int) ($data['sejak'] ?? 0);

        /*
         | Pekerjaan yang tak kunjung diambil hampir selalu berarti tidak ada
         | worker antrean yang berjalan. Itu perlu dikatakan terus terang —
         | tanpa ini operator menatap bilah 0% tanpa tahu bahwa yang salah ada
         | di server, bukan di akunnya.
        */
        $data['tertahan'] = $data['status'] === self::STATUS_ANTRE && $umur > 25;
        $data['umur'] = $umur;

        return $data;
    }

    public static function bersihkan(string $accountId): void
    {
        Cache::forget(self::kunci($accountId));
    }
}
