<?php

namespace Database\Factories;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationalUnit>
 */
class OrganizationalUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Dinas '.fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'type' => 'dinas',
            'district' => null,
            'contact_person' => fake()->name(),
            'contact_phone' => '08'.fake()->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function kecamatan(?string $district = null): static
    {
        return $this->state(function () use ($district) {
            $district ??= Str::title(fake()->unique()->words(2, true));

            return [
                'name' => 'Kecamatan '.$district,
                'slug' => Str::slug('kecamatan-'.$district),
                'type' => 'kecamatan',
                'district' => $district,
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
