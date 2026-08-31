<?php

namespace Database\Factories;

use App\Models\AudienceBreakdown;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudienceBreakdown>
 */
class AudienceBreakdownFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'snapshot_date' => now()->toDateString(),
            'dimension' => AudienceBreakdown::DIMENSION_AGE,
            'data' => $this->ageData(),
        ];
    }

    public function age(): static
    {
        return $this->state(fn () => [
            'dimension' => AudienceBreakdown::DIMENSION_AGE,
            'data' => $this->ageData(),
        ]);
    }

    public function gender(): static
    {
        return $this->state(fn () => [
            'dimension' => AudienceBreakdown::DIMENSION_GENDER,
            'data' => [
                'F' => fake()->numberBetween(200, 12000),
                'M' => fake()->numberBetween(200, 12000),
                'U' => fake()->numberBetween(5, 400),
            ],
        ]);
    }

    /**
     * Bentuk kunci mengikuti Facebook: "F.25-34" (gender lebih dulu). Instagram
     * mengembalikan urutan sebaliknya dan dinormalkan saat diambil, supaya
     * seluruh aplikasi hanya mengenal satu bentuk.
     */
    public function ageGender(): static
    {
        return $this->state(function (): array {
            $data = [];

            foreach ($this->ageData() as $kelompok => $jumlah) {
                $perempuan = (int) round($jumlah * fake()->randomFloat(2, 0.35, 0.65));
                $data["F.{$kelompok}"] = $perempuan;
                $data["M.{$kelompok}"] = $jumlah - $perempuan;
            }

            return ['dimension' => AudienceBreakdown::DIMENSION_AGE_GENDER, 'data' => $data];
        });
    }

    public function city(): static
    {
        return $this->state(fn () => [
            'dimension' => AudienceBreakdown::DIMENSION_CITY,
            'data' => [
                'Sangatta' => fake()->numberBetween(500, 9000),
                'Bontang' => fake()->numberBetween(100, 3000),
                'Samarinda' => fake()->numberBetween(100, 4000),
                'Balikpapan' => fake()->numberBetween(50, 2500),
                'Muara Wahau' => fake()->numberBetween(20, 900),
            ],
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['snapshot_date' => $date]);
    }

    /** @return array<string, int> */
    private function ageData(): array
    {
        return [
            '13-17' => fake()->numberBetween(10, 700),
            '18-24' => fake()->numberBetween(200, 6000),
            '25-34' => fake()->numberBetween(400, 9000),
            '35-44' => fake()->numberBetween(200, 5000),
            '45-54' => fake()->numberBetween(80, 2200),
            '55-64' => fake()->numberBetween(20, 800),
            '65+' => fake()->numberBetween(5, 300),
        ];
    }
}
