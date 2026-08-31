<?php

namespace App\Jobs;

use App\Models\InsightSnapshot;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\Meta\FacebookInsightService;
use App\Services\Meta\InstagramInsightService;
use App\Services\Meta\MetaGraphException;
use App\Support\SyncProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Menarik riwayat harian sekaligus saat sebuah akun baru terhubung.
 *
 * Tanpa ini, akun yang baru dihubungkan hanya punya data satu hari, sehingga
 * saringan "30 hari" sampai "1 tahun" tampak kosong sampai sinkronisasi harian
 * menumpuk berbulan-bulan. Meta memberi deret harian penuh dalam SATU panggilan
 * per metrik, jadi setahun riwayat pun cukup beberapa panggilan.
 *
 * BATAS YANG PERLU DIKETAHUI:
 *
 * Meta tidak menyediakan jumlah pengikut historis — yang ada hanya totalnya
 * saat ini ditambah pertambahan per hari. Kurva pengikut masa lalu karena itu
 * direkonstruksi mundur, dan hasilnya perkiraan: yang berhenti mengikuti tidak
 * ikut terhitung. Jangkauan, tayangan, kunjungan profil, dan interaksi semuanya
 * angka harian yang sebenarnya.
 */
class BackfillAccountHistory implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public array $backoff = [120, 600];

    /**
     * Setahun penuh.
     *
     * Terverifikasi langsung ke Meta: satu panggilan boleh meminta 365 hari
     * sekaligus, dan datanya tersedia sampai 2 tahun ke belakang (hari ke-730
     * ditolak dengan pesan "Metrics data is available for the last 2 years").
     *
     * Jangkauan dan pertambahan pengikut cukup satu panggilan masing-masing
     * untuk seluruh rentang, jadi memperpanjang dari 90 ke 365 hari nyaris
     * tidak menambah biaya. Tayangan, kunjungan profil, dan interaksi tetap
     * dibatasi `meta.riwayat_interaksi_hari` karena ketiganya harus diminta
     * satu panggilan per hari.
     */
    public function __construct(
        public SocialAccount $account,
        public int $days = 365,
    ) {}

    public function handle(
        InstagramInsightService $instagram,
        FacebookInsightService $facebook,
    ): void {
        $mulai = microtime(true);
        $from = now()->subDays($this->days);
        $until = now()->subDay();

        try {
            SyncProgress::tahap($this->account->id, "Menarik riwayat {$this->days} hari terakhir", 40);

            $harian = $this->account->platform === SocialAccount::PLATFORM_INSTAGRAM
                ? $instagram->metricsRange($this->account, $from, $until)
                : $facebook->metricsRange($this->account, $from, $until);

            $pengikut = $this->followersNow($instagram, $facebook);

            SyncProgress::tahap($this->account->id, 'Menyusun kurva pengikut', 70);

            $baru = $this->account->platform === SocialAccount::PLATFORM_INSTAGRAM
                ? $instagram->newFollowerSeries($this->account, $from, $until)
                : $facebook->newFollowerSeries($this->account, $from, $until);

            $kurva = $this->rekonstruksiPengikut(array_keys($harian), $pengikut, $baru);

            SyncProgress::tahap($this->account->id, 'Menyimpan '.count($harian).' hari riwayat', 85);

            foreach ($harian as $tanggal => $nilai) {
                $this->simpan($tanggal, $kurva[$tanggal] ?? $pengikut, $nilai);
            }

            SyncProgress::selesai(
                $this->account->id,
                SyncLog::STATUS_SUCCESS,
                count($harian).' hari riwayat berhasil ditarik.',
            );

            SyncLog::create([
                'social_account_id' => $this->account->id,
                'status' => SyncLog::STATUS_SUCCESS,
                'trigger' => SyncLog::TRIGGER_MANUAL,
                'message' => 'Riwayat '.count($harian).' hari ditarik dari Meta.',
                'duration_ms' => (int) round((microtime(true) - $mulai) * 1000),
            ]);
        } catch (MetaGraphException $e) {
            SyncProgress::selesai($this->account->id, SyncLog::STATUS_FAILED, $e->getMessage());

            SyncLog::create([
                'social_account_id' => $this->account->id,
                'status' => SyncLog::STATUS_FAILED,
                'trigger' => SyncLog::TRIGGER_MANUAL,
                'message' => 'Gagal menarik riwayat: '.$e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $mulai) * 1000),
            ]);

            Log::warning('Pengisian riwayat gagal', [
                'akun' => $this->account->id,
                'pesan' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Menyusun jumlah pengikut tiap hari, mundur dari total hari ini.
     *
     * Meta hanya memberi total saat ini plus pertambahan harian, jadi:
     *
     *     pengikut(kemarin) = pengikut(hari ini) − pertambahan(hari ini)
     *
     * Perkiraan, karena yang berhenti mengikuti tidak terhitung. Tapi jauh
     * lebih jujur daripada menaruh total hari ini di seluruh riwayat — itu
     * membuat grafik trennya datar sempurna, seolah tidak seorang pun pernah
     * mengikuti selama 90 hari.
     *
     * @param  list<string>  $tanggal  urut menaik
     * @param  array<string, int>  $baru
     * @return array<string, int>
     */
    private function rekonstruksiPengikut(array $tanggal, int $sekarang, array $baru): array
    {
        $kurva = [];
        $berjalan = $sekarang;

        foreach (array_reverse($tanggal) as $hari) {
            $kurva[$hari] = max(0, $berjalan);
            $berjalan -= (int) ($baru[$hari] ?? 0);
        }

        return $kurva;
    }

    private function followersNow(
        InstagramInsightService $instagram,
        FacebookInsightService $facebook,
    ): int {
        $profil = $this->account->platform === SocialAccount::PLATFORM_INSTAGRAM
            ? $instagram->profile($this->account)
            : $facebook->profile($this->account);

        return (int) ($profil['followers_count'] ?? $profil['fan_count'] ?? 0);
    }

    /**
     * Menyimpan satu hari — HANYA metrik yang benar-benar ditarik.
     *
     * Interaksi dan kunjungan profil hanya tersedia lewat panggilan per hari,
     * dan itu dibatasi beberapa puluh hari terakhir. Kalau kolomnya tetap
     * ditulis nol untuk hari di luar batas itu, penarikan riwayat akan MENGHAPUS
     * angka yang sudah dikumpulkan sinkronisasi harian selama berbulan-bulan.
     * Karena itu kolom yang tidak ikut ditarik dibiarkan apa adanya.
     *
     * @param  array<string, int>  $nilai
     */
    private function simpan(string $tanggal, int $pengikut, array $nilai): void
    {
        $snapshot = InsightSnapshot::firstOrNew([
            'social_account_id' => $this->account->id,
            'snapshot_date' => $tanggal,
        ]);

        // Baris baru berangkat dari nol; baris lama mempertahankan isinya.
        if (! $snapshot->exists) {
            $snapshot->fill(['reach' => 0, 'impressions' => 0, 'profile_views' => 0, 'interactions' => 0]);
        }

        $snapshot->followers_count = $pengikut;

        foreach (['reach', 'impressions', 'profile_views', 'interactions'] as $kolom) {
            if (array_key_exists($kolom, $nilai)) {
                $snapshot->{$kolom} = $nilai[$kolom];
            }
        }

        $snapshot->engagement_rate = $pengikut > 0
            ? round(min((int) $snapshot->interactions / $pengikut * 100, 999.99), 2)
            : 0.0;

        $snapshot->raw_payload = ['sumber' => 'riwayat'];
        $snapshot->save();
    }
}
