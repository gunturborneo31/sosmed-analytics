<?php

namespace App\Services\Analytics;

use App\Models\Setting;
use App\Support\Period;
use Illuminate\Support\Collection;

/**
 * IKK "Persentase Masyarakat yang Menjadi Sasaran Penyebaran Informasi Publik,
 * Mengetahui Kebijakan dan Program Prioritas Pemerintah dan Pemerintah Daerah
 * Kabupaten/Kota".
 *
 * Rumus:
 *
 *      jumlah masyarakat sasaran penyebaran informasi publik (usia 16–64)
 *      ---------------------------------------------------------------- × 100%
 *                            jumlah penduduk
 *
 * Pembilang diambil dari demografi pengikut akun media sosial seluruh OPD —
 * merekalah "masyarakat yang menjadi sasaran penyebaran informasi publik"
 * melalui Media Komunikasi Publik Pemerintah Daerah kanal media sosial.
 *
 * BATAS YANG HARUS DIPAHAMI PEMBACA LAPORAN:
 *
 * 1. Angka ini menjumlahkan pengikut lintas akun OPD. Satu warga yang mengikuti
 *    beberapa akun terhitung berkali-kali, sehingga pembilang cenderung LEBIH
 *    BESAR dari jumlah orang sebenarnya. Meta tidak menyediakan cara untuk
 *    mengenali pengikut yang sama di dua akun berbeda.
 * 2. Definisi operasional IKK mencakup enam kanal Media Komunikasi Publik
 *    (cetak, penyiaran, online, media sosial, luar ruang, tatap muka).
 *    Aplikasi ini hanya mengukur SATU di antaranya: media sosial.
 * 3. IKK resmi diukur lewat survei. Angka di sini adalah estimasi pendukung
 *    dari data media sosial, bukan pengganti hasil survei.
 */
final class PublicInformationIndex
{
    public function __construct(
        private readonly Period $period,
        private readonly AccountScope $scope,
        private readonly int $population,
    ) {}

    public static function make(Period $period, ?AccountScope $scope = null, ?int $population = null): self
    {
        return new self(
            $period,
            $scope ?? AccountScope::make(),
            // Nilai tersimpan dulu; bawaan config hanya dipakai bila admin
            // belum pernah menetapkannya.
            $population ?? Setting::jumlahPenduduk(),
        );
    }

    /**
     * Rincian pembilang per kelompok usia. Seluruh baris di sini sudah berada
     * di dalam rentang 16–64 tahun — kelompok di luar rentang tidak ikut
     * dihitung maupun ditampilkan (§config/ikk.php).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ageBreakdown(): Collection
    {
        $spesifikasi = AudienceAnalytics::ageGroups()->keyBy('label');

        return AudienceAnalytics::make($this->period, $this->scope)
            ->byAge()
            ->map(fn (int $jumlah, string $kelompok): array => [
                'kelompok' => $kelompok,
                'jumlah' => $jumlah,
                // Ditandai bila angkanya perkiraan, bukan hitungan langsung.
                'perkiraan' => ($spesifikasi[$kelompok]['porsi'] ?? 1.0) < 1.0,
                'alasan' => $spesifikasi[$kelompok]['alasan'] ?? null,
            ])
            ->values();
    }

    /** Pembilang: masyarakat sasaran berusia 16–64 tahun. */
    public function numerator(): int
    {
        return (int) $this->ageBreakdown()->sum('jumlah');
    }

    /**
     * Bagian pembilang yang berasal dari kelompok usia yang angkanya diperkirakan
     * — jadi pembaca laporan tahu seberapa besar porsi perkiraan di dalam angka
     * akhirnya.
     */
    public function estimatedCount(): int
    {
        return (int) $this->ageBreakdown()->where('perkiraan', true)->sum('jumlah');
    }

    /** Penyebut: jumlah penduduk. */
    public function denominator(): int
    {
        return $this->population;
    }

    /**
     * Hasil IKK dalam persen, dibulatkan dua desimal seperti contoh resmi
     * (319.580 / 456.333 × 100% = 70,03%).
     */
    public function percentage(): float
    {
        if ($this->population <= 0) {
            return 0.0;
        }

        return round($this->numerator() / $this->population * 100, 2);
    }

    /**
     * Persentase bisa melampaui 100% justru karena pengikut terhitung ganda
     * lintas akun. Kalau itu terjadi, angkanya tidak boleh disajikan begitu
     * saja sebagai capaian kinerja.
     */
    public function exceedsPopulation(): bool
    {
        return $this->numerator() > $this->population;
    }

    /**
     * Pengikut yang tidak masuk pembilang karena berada di luar rentang usia
     * sasaran. Jumlahnya tetap dilaporkan sebagai satu angka supaya pembaca
     * tahu ada data yang dikesampingkan — tanpa menampilkan kelompok usianya,
     * yang memang di luar cakupan IKK.
     */
    public function excludedCount(): int
    {
        $mentah = AudienceAnalytics::make($this->period, $this->scope)->byAgeRaw()->sum();

        return max(0, (int) $mentah - $this->numerator());
    }

    /**
     * Seluruh angka yang dipakai halaman Rekap maupun laporan PDF.
     *
     * @return array{
     *     pembilang:int, penyebut:int, persentase:float,
     *     melampaui_penduduk:bool, tidak_dihitung:int, dari_perkiraan:int,
     *     rincian_usia:Collection<int, array<string, mixed>>
     * }
     */
    public function summary(): array
    {
        return [
            'pembilang' => $this->numerator(),
            'penyebut' => $this->denominator(),
            'persentase' => $this->percentage(),
            'melampaui_penduduk' => $this->exceedsPopulation(),
            'tidak_dihitung' => $this->excludedCount(),
            'dari_perkiraan' => $this->estimatedCount(),
            'rincian_usia' => $this->ageBreakdown(),
        ];
    }
}
