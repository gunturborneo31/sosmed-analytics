<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncLog>
 */
class SyncLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'status' => SyncLog::STATUS_SUCCESS,
            'trigger' => SyncLog::TRIGGER_SCHEDULED,
            'message' => null,
            'duration_ms' => fake()->numberBetween(220, 4800),
        ];
    }

    public function failed(string $message = 'Graph API error: (#190) Access token has expired.'): static
    {
        return $this->state(fn () => [
            'status' => SyncLog::STATUS_FAILED,
            'message' => $message,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => ['trigger' => SyncLog::TRIGGER_MANUAL]);
    }
}
