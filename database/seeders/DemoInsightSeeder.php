<?php

namespace Database\Seeders;

use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk pengembangan lokal — bukan bagian dari DatabaseSeeder.
 *
 *   php artisan db:seed --class=DemoInsightSeeder
 *
 * Menghasilkan sebagian OPD terhubung (sisanya sengaja dibiarkan kosong agar
 * kartu "Perlu Perhatian" di §8.3 punya isi), 30 hari snapshot, dan demografi.
 */
class DemoInsightSeeder extends Seeder
{
    public function run(): void
    {
        $units = OrganizationalUnit::active()->inRandomOrder()->limit(34)->get();

        foreach ($units as $index => $unit) {
            $account = SocialAccount::factory()
                ->when($index % 11 === 0, fn ($f) => $f->expiring())
                ->when($index % 13 === 0, fn ($f) => $f->stale())
                ->create([
                    'organizational_unit_id' => $unit->id,
                    'username' => str($unit->name)->slug('')->limit(24, ''),
                    'display_name' => $unit->name,
                ]);

            $this->seedTimeline($account);

            // Beberapa OPD juga punya Facebook Page. Riwayatnya ikut diisi —
            // tanpa itu, menyaring laporan ke Facebook selalu menghasilkan nol
            // dan filternya terlihat rusak padahal datanya yang tidak ada.
            if ($index % 3 === 0) {
                $this->seedTimeline(
                    SocialAccount::factory()->facebook()->create([
                        'organizational_unit_id' => $unit->id,
                        'display_name' => $unit->name,
                    ])
                );
            }
        }
    }

    private function seedTimeline(SocialAccount $account): void
    {
        $followers = fake()->numberBetween(400, 42000);

        for ($day = 29; $day >= 0; $day--) {
            $date = now()->subDays($day)->toDateString();
            $followers += fake()->numberBetween(-8, 120);
            $reach = (int) round($followers * fake()->randomFloat(2, 0.4, 3.2));
            // Interaksi harian, bukan rasio engagement — lihat catatan di factory.
            $interactions = (int) round(max($followers, 0) * fake()->randomFloat(5, 0.0001, 0.003));

            InsightSnapshot::factory()->create([
                'social_account_id' => $account->id,
                'snapshot_date' => $date,
                'followers_count' => max($followers, 0),
                'reach' => $reach,
                'impressions' => (int) round($reach * fake()->randomFloat(2, 1.1, 2.4)),
                'profile_views' => (int) round($reach * fake()->randomFloat(2, 0.01, 0.09)),
                'interactions' => $interactions,
                // Harus diturunkan dari pengikut yang sudah ditimpa di sini,
                // bukan dari angka acak milik factory.
                'engagement_rate' => $followers > 0
                    ? round(min($interactions / $followers * 100, 999.99), 2)
                    : 0.0,
            ]);
        }

        $today = now()->toDateString();

        AudienceBreakdown::factory()->age()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => $today,
        ]);
        AudienceBreakdown::factory()->gender()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => $today,
        ]);
        AudienceBreakdown::factory()->ageGender()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => $today,
        ]);
        AudienceBreakdown::factory()->city()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => $today,
        ]);

        SyncLog::factory()->count(3)->create(['social_account_id' => $account->id]);

        if (fake()->boolean(15)) {
            SyncLog::factory()->failed()->create(['social_account_id' => $account->id]);
        }
    }
}
