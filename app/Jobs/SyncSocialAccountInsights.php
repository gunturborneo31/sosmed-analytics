<?php

namespace App\Jobs;

use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\Meta\FacebookInsightService;
use App\Services\Meta\InstagramInsightService;
use App\Services\Meta\MetaGraphException;
use App\Support\SyncProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncSocialAccountInsights implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Mundur makin lama — kalau Meta sedang membatasi, jangan menghantam terus. */
    public array $backoff = [60, 300, 900];

    /**
     * Metrik yang Meta tolak pada sinkronisasi ini. Isinya menentukan apakah
     * hasilnya dicatat "berhasil" atau "sebagian".
     *
     * @var list<string>
     */
    private array $skipped = [];

    public function __construct(
        public SocialAccount $account,
        public string $trigger = SyncLog::TRIGGER_SCHEDULED,
        public ?string $date = null,
    ) {}

    /**
     * Hari kemarin, bukan hari ini.
     *
     * Insight harian Meta baru terisi untuk hari yang sudah SELESAI. Meminta
     * hari berjalan membuahkan `{"data":[]}` — bukan galat, sekadar kosong —
     * sehingga jangkauan, tayangan, dan interaksi semuanya tersimpan nol.
     * Itu terjadi pada setiap sinkronisasi, jam berapa pun dijalankan.
     */
    public static function tanggalBawaan(): string
    {
        return now()->subDay()->toDateString();
    }

    public function handle(
        InstagramInsightService $instagram,
        FacebookInsightService $facebook,
    ): void {
        $startedAt = microtime(true);
        $date = Carbon::parse($this->date ?? self::tanggalBawaan());
        $status = SyncLog::STATUS_SUCCESS;
        $message = null;

        try {
            SyncProgress::tahap($this->account->id, 'Menghubungi server Meta', 10);

            $this->account->platform === SocialAccount::PLATFORM_INSTAGRAM
                ? $this->syncInstagram($instagram, $date)
                : $this->syncFacebook($facebook, $date);

            // Metrik yang ditolak Meta tidak boleh lewat sebagai "berhasil" —
            // angkanya tersimpan nol dan pembaca laporan tidak punya cara tahu
            // bedanya dengan nol yang sebenarnya.
            if ($this->skipped !== []) {
                $status = SyncLog::STATUS_PARTIAL;
                $message = 'Metrik tidak tersedia dari Meta: '.implode(', ', $this->skipped).'.';
            }

            $this->account->update([
                'last_synced_at' => now(),
                'status' => SocialAccount::STATUS_CONNECTED,
            ]);
        } catch (MetaGraphException $e) {
            $status = SyncLog::STATUS_FAILED;
            $message = $e->getMessage();

            if ($e->isAuthError()) {
                $this->account->update(['status' => SocialAccount::STATUS_EXPIRED]);
                $this->fail($e);
            } elseif ($e->isRateLimited()) {
                $this->release(900);
            } else {
                $this->account->update(['status' => SocialAccount::STATUS_ERROR]);
            }
        } finally {
            SyncProgress::selesai($this->account->id, $status, $message);

            // §12: log hanya menyimpan pesan, tidak pernah payload berisi data pribadi.
            SyncLog::create([
                'social_account_id' => $this->account->id,
                'status' => $status,
                'trigger' => $this->trigger,
                'message' => $message,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }
    }

    private function syncInstagram(InstagramInsightService $instagram, Carbon $date): void
    {
        SyncProgress::tahap($this->account->id, 'Mengambil profil akun', 25);
        $profile = $instagram->profile($this->account);

        SyncProgress::tahap($this->account->id, 'Mengambil jangkauan & interaksi harian', 50);
        $metrics = $instagram->dailyMetrics($this->account, $date);

        $this->account->fill(array_filter([
            'username' => $profile['username'] ?? null,
            'display_name' => $profile['name'] ?? null,
            'avatar_url' => $profile['profile_picture_url'] ?? null,
        ]))->save();

        $this->storeSnapshot(
            $date,
            (int) ($profile['followers_count'] ?? 0),
            $metrics,
        );

        SyncProgress::tahap($this->account->id, 'Mengambil demografi pengikut', 75);
        $this->storeDemographics($date, $instagram->followerDemographics($this->account));

        SyncProgress::tahap($this->account->id, 'Menyimpan hasil', 95);
    }

    private function syncFacebook(FacebookInsightService $facebook, Carbon $date): void
    {
        SyncProgress::tahap($this->account->id, 'Mengambil profil Page', 25);
        $profile = $facebook->profile($this->account);

        SyncProgress::tahap($this->account->id, 'Mengambil jangkauan & interaksi harian', 50);
        $metrics = $facebook->dailyMetrics($this->account, $date);

        $this->account->fill(array_filter([
            'display_name' => $profile['name'] ?? null,
            'username' => $profile['username'] ?? null,
            'avatar_url' => $profile['picture']['data']['url'] ?? null,
        ]))->save();

        $this->storeSnapshot($date, (int) ($profile['fan_count'] ?? 0), $metrics);
        SyncProgress::tahap($this->account->id, 'Mengambil demografi penggemar', 75);
        $this->storeDemographics($date, $facebook->fanDemographics($this->account));

        SyncProgress::tahap($this->account->id, 'Menyimpan hasil', 95);
    }

    /**
     * @param  array{reach:int, impressions:int, profile_views:int, interactions:int, dilewati:list<string>, raw:array<string, mixed>}  $metrics
     */
    private function storeSnapshot(Carbon $date, int $followers, array $metrics): void
    {
        $this->skipped = $metrics['dilewati'] ?? [];

        InsightSnapshot::updateOrCreate(
            [
                'social_account_id' => $this->account->id,
                'snapshot_date' => $date->toDateString(),
            ],
            [
                'followers_count' => $followers,
                'reach' => $metrics['reach'],
                'impressions' => $metrics['impressions'],
                'profile_views' => $metrics['profile_views'],
                'interactions' => $metrics['interactions'],
                'engagement_rate' => $this->engagementRate($followers, $metrics['interactions']),
                'raw_payload' => $metrics['raw'],
            ],
        );
    }

    /**
     * @param  array<string, array<string, int>>  $demographics
     */
    private function storeDemographics(Carbon $date, array $demographics): void
    {
        foreach ($demographics as $dimension => $data) {
            if (! in_array($dimension, AudienceBreakdown::DIMENSIONS, true) || $data === []) {
                continue;
            }

            AudienceBreakdown::updateOrCreate(
                [
                    'social_account_id' => $this->account->id,
                    'snapshot_date' => $date->toDateString(),
                    'dimension' => $dimension,
                ],
                ['data' => $data],
            );
        }
    }

    /**
     * Engagement rate = interaksi terhadap jumlah pengikut.
     *
     * Sebelumnya ini memakai jangkauan sebagai pembilang, yang sebenarnya
     * "reach rate", bukan engagement — angkanya bahkan rutin melampaui 100%
     * karena satu akun bisa menjangkau lebih banyak orang daripada pengikutnya.
     * Meta menyediakan angka interaksi sungguhan (Instagram `total_interactions`,
     * Facebook `page_post_engagements`), jadi itu yang dipakai sekarang.
     */
    private function engagementRate(int $followers, int $interactions): float
    {
        if ($followers <= 0) {
            return 0.0;
        }

        return round(min(($interactions / $followers) * 100, 999.99), 2);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Sinkronisasi akun gagal permanen', [
            'akun' => $this->account->id,
            'platform' => $this->account->platform,
            'pesan' => $e->getMessage(),
        ]);
    }
}
