<?php

namespace Database\Factories;

use App\Models\InsightSnapshot;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsightSnapshot>
 */
class InsightSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $followers = fake()->numberBetween(400, 42000);
        $reach = (int) round($followers * fake()->randomFloat(2, 0.4, 3.2));
        /*
         | Interaksi HARIAN, bukan rasio engagement. Akun pemerintah daerah
         | wajarnya menerima segelintir suka/komentar per hari — sekitar
         | 0,01%–0,3% dari pengikut. Dijumlahkan sebulan, angkanya jatuh di
         | kisaran engagement yang masuk akal (0,3%–9%), bukan ratusan persen.
        */
        $interactions = (int) round($followers * fake()->randomFloat(5, 0.0001, 0.003));

        return [
            'social_account_id' => SocialAccount::factory(),
            'snapshot_date' => now()->toDateString(),
            'followers_count' => $followers,
            'reach' => $reach,
            'impressions' => (int) round($reach * fake()->randomFloat(2, 1.1, 2.4)),
            'profile_views' => (int) round($reach * fake()->randomFloat(2, 0.01, 0.09)),
            'interactions' => $interactions,
            'engagement_rate' => $followers > 0
                ? round(min($interactions / $followers * 100, 999.99), 2)
                : 0.0,
            'raw_payload' => ['source' => 'factory'],
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['snapshot_date' => $date]);
    }
}
