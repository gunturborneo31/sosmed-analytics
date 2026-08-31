<?php

namespace Database\Factories;

use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'connected_by' => null,
            'platform' => SocialAccount::PLATFORM_INSTAGRAM,
            'platform_account_id' => (string) fake()->unique()->numerify('178########'),
            'username' => fake()->unique()->userName(),
            'display_name' => fake()->company(),
            'avatar_url' => null,
            'access_token' => 'EAA'.fake()->regexify('[A-Za-z0-9]{80}'),
            'token_expires_at' => now()->addDays(60),
            'status' => SocialAccount::STATUS_CONNECTED,
            'last_synced_at' => now()->subHours(fake()->numberBetween(1, 12)),
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn () => [
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'platform_account_id' => (string) fake()->unique()->numerify('1015########'),
        ]);
    }

    public function expiring(int $inDays = 3): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->addDays($inDays),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SocialAccount::STATUS_EXPIRED,
            'token_expires_at' => now()->subDay(),
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn () => [
            'last_synced_at' => now()->subDays(45),
        ]);
    }
}
