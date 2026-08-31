<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationalUnit;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\CountyAnalytics;
use App\Support\Period;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Peta Denyut Kutim (§7.4).
 *
 * Peta skematis — posisi kecamatan disusun mendekati letak relatif sebenarnya
 * (Sangatta di pesisir timur, Busang di barat jauh), bukan hasil proyeksi
 * kartografis. Tujuannya bukan navigasi, melainkan sekali pandang tahu wilayah
 * mana yang sedang aktif berkomunikasi dengan warganya.
 */
class PulseMap extends Component
{
    public string $period = '30';

    /**
     * Koordinat relatif dalam viewBox 100×100.
     *
     * @var array<string, array{float, float}>
     */
    public const COORDINATES = [
        'Sandaran' => [87, 17],
        'Sangkulirang' => [83, 25],
        'Kaliorang' => [80, 32],
        'Kaubun' => [75, 27],
        'Karangan' => [72, 34],
        'Bengalon' => [75, 43],
        'Sangatta Utara' => [71, 52],
        'Sangatta Selatan' => [69, 60],
        'Teluk Pandan' => [66, 67],
        'Rantau Pulung' => [61, 57],
        'Kongbeng' => [55, 35],
        'Muara Wahau' => [51, 28],
        'Telen' => [45, 37],
        'Long Mesangat' => [45, 45],
        'Muara Ancalong' => [39, 51],
        'Muara Bengkal' => [43, 59],
        'Batu Ampar' => [51, 63],
        'Busang' => [28, 39],
    ];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function districts(): Collection
    {
        $period = Period::fromKey($this->period);

        $ranking = CountyAnalytics::make($period, AccountScope::make()->unitType('kecamatan'))
            ->ranking()
            ->keyBy('unit_name');

        $units = OrganizationalUnit::active()
            ->ofType('kecamatan')
            ->get()
            ->keyBy('district');

        // Skala denyut dinormalisasi terhadap kecamatan paling aktif periode ini,
        // supaya peta tetap informatif baik saat angka besar maupun kecil.
        $peak = max((float) $ranking->max('reach'), 1);

        return collect(self::COORDINATES)
            ->map(function (array $point, string $district) use ($units, $ranking, $peak): array {
                $unit = $units->get($district);
                $row = $unit ? $ranking->get($unit->name) : null;

                $intensity = $row ? min((float) $row->reach / $peak, 1.0) : 0.0;

                return [
                    'district' => $district,
                    'x' => $point[0],
                    'y' => $point[1],
                    'slug' => $unit?->slug,
                    'connected' => $row !== null,
                    'intensity' => round($intensity, 3),
                    'reach' => $row ? (int) $row->reach : 0,
                    'followers' => $row ? (int) $row->followers : 0,
                    // Titik makin terang dan makin besar seiring aktivitas (§7.4).
                    'radius' => round(2.1 + $intensity * 2.9, 2),
                    'color' => $this->blend($intensity),
                    // Denyut lambat 2.4s; makin aktif makin cepat, tapi tidak pernah gelisah.
                    'duration' => round(2.4 - $intensity * 0.9, 2),
                ];
            })
            ->values();
    }

    #[Computed]
    public function connectedCount(): int
    {
        return $this->districts()->where('connected', true)->count();
    }

    /**
     * Interpolasi gradient brand: #3E68B2 (aktivitas rendah) → #3DC8F4 (tinggi).
     */
    private function blend(float $t): string
    {
        $from = [0x3E, 0x68, 0xB2];
        $to = [0x3D, 0xC8, 0xF4];

        $channels = array_map(
            fn (int $a, int $b): int => (int) round($a + ($b - $a) * $t),
            $from,
            $to,
        );

        return sprintf('#%02X%02X%02X', ...$channels);
    }

    /** Klik titik &rarr; langsung masuk ke detail kecamatan itu (§7.4). */
    public function goToUnit(string $slug): void
    {
        $this->redirectRoute('admin.units.show', $slug, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.pulse-map');
    }
}
