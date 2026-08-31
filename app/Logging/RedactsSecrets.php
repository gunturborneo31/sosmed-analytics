<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Jaring pengaman terakhir agar rahasia tidak pernah mendarat di berkas log
 * (§12: "Token tidak pernah dikirim ke frontend atau muncul di log").
 *
 * Kode aplikasi memang sudah berhati-hati tidak mencatat token. Yang bocor
 * datang dari jalur yang tak terduga: saat sebuah query gagal, Laravel menyusun
 * pesan QueryException berisi SELURUH binding — termasuk kolom access_token —
 * lalu handler mencatatnya utuh. Itu benar-benar terjadi ketika penyimpanan
 * akun Instagram gagal karena panjang avatar_url.
 *
 * Tokennya memang tersimpan terenkripsi, tapi berkas log tidak pantas ikut
 * memegangnya: log dikirim ke mesin lain, dibagikan saat menelusuri masalah,
 * dan hidup jauh lebih lama dari kejadiannya.
 */
class RedactsSecrets implements ProcessorInterface
{
    /**
     * Pola yang disamarkan. Sengaja mengenali BENTUK rahasianya, bukan nama
     * kolomnya — pesan galat menyusun ulang teksnya dengan cara yang tak selalu
     * bisa ditebak.
     */
    private const POLA = [
        // Payload terenkripsi Laravel: JSON {iv,value,mac} yang di-base64-kan.
        '/eyJpdiI6[A-Za-z0-9+\/=]{40,}/' => '[token-disamarkan]',
        // Token akses Meta: panjang, diawali penanda khas mereka.
        '/\b(EAA|IGQ|IGAA)[A-Za-z0-9_\-]{30,}/' => '[token-disamarkan]',
        // Kode otorisasi OAuth pada URL callback.
        '/\bcode=[A-Za-z0-9_\-]{20,}/' => 'code=[disamarkan]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->bersihkan($record->message),
            context: $this->bersihkanArray($record->context),
        );
    }

    private function bersihkan(string $teks): string
    {
        return (string) preg_replace(array_keys(self::POLA), array_values(self::POLA), $teks);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function bersihkanArray(array $data): array
    {
        foreach ($data as $kunci => $nilai) {
            $data[$kunci] = match (true) {
                is_string($nilai) => $this->bersihkan($nilai),
                is_array($nilai) => $this->bersihkanArray($nilai),
                // Exception dibiarkan utuh: Monolog merangkainya jadi teks
                // belakangan, dan formatter-lah yang melewati proses ini lagi.
                default => $nilai,
            };
        }

        return $data;
    }
}
