<?php

namespace App\Services\Meta;

use RuntimeException;

class MetaGraphException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        public readonly ?int $errorSubcode = null,
        public readonly ?string $type = null,
    ) {
        parent::__construct($message);
    }

    /** @param array<string, mixed> $error */
    public static function fromGraphError(array $error): self
    {
        return new self(
            $error['message'] ?? 'Graph API menolak permintaan tanpa keterangan.',
            isset($error['code']) ? (int) $error['code'] : null,
            isset($error['error_subcode']) ? (int) $error['error_subcode'] : null,
            $error['type'] ?? null,
        );
    }

    /** Token dicabut, kedaluwarsa, atau user mengubah kata sandi. */
    public function isAuthError(): bool
    {
        return in_array($this->errorCode, [190, 102, 463, 467], true);
    }

    /** Kuota panggilan terlampaui — mundur, jangan ulangi segera. */
    public function isRateLimited(): bool
    {
        return in_array($this->errorCode, [4, 17, 32, 613], true);
    }

    /**
     * Pesan Meta ditulis untuk pengembang, bukan untuk operator OPD yang sedang
     * mencoba menghubungkan akun instansinya. Yang paling sering muncul
     * diterjemahkan di sini jadi langkah yang bisa benar-benar dikerjakan —
     * sisanya tetap diteruskan apa adanya, karena pesan mentah masih jauh lebih
     * berguna daripada "terjadi kesalahan".
     */
    public function petunjuk(): ?string
    {
        $pesan = mb_strtolower($this->getMessage());

        return match (true) {
            str_contains($pesan, 'insufficient developer role') => 'Akun Instagram ini belum diberi peran di aplikasi Meta. '
                .'Selama aplikasi masih berstatus Development, hanya akun yang terdaftar sebagai Admin, Developer, '
                .'atau Tester yang boleh memberi izin. Minta admin Diskominfo menambahkan akunmu di dashboard Meta '
                .'(App roles → Roles → Add people → Instagram tester), lalu terima undangannya dari aplikasi '
                .'Instagram: Pengaturan → Situs web dan izin → Undangan penguji.',

            str_contains($pesan, 'invalid redirect_uri') => 'Alamat callback aplikasi ini belum didaftarkan di dashboard Meta. '
                .'Hubungi admin Diskominfo untuk menambahkannya di Business login settings.',

            str_contains($pesan, 'invalid platform app') => 'Kredensial aplikasi Meta tidak cocok dengan jalur login ini. '
                .'Instagram Login memakai App ID tersendiri, berbeda dari App ID Facebook.',

            str_contains($pesan, 'sudah dipakai') || str_contains($pesan, 'has been used') => 'Kode otorisasinya sudah terpakai. '
                .'Mulai ulang proses menghubungkan dari halaman Akun.',

            default => null,
        };
    }

    /** Pesan siap tampil: petunjuk bila ada, kalau tidak pesan asli dari Meta. */
    public function pesanUntukOperator(): string
    {
        return $this->petunjuk() ?? $this->getMessage();
    }
}
