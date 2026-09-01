<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ReceivesFilters;
use App\Services\Analytics\AudienceAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Spektrum usia sebagai grafik cermin ♀ | ♂ (§8.3).
 */
class AgeSpectrum extends Component
{
    use ReceivesFilters;

    /** @return array<string, mixed> */
    #[Computed]
    public function chart(): array
    {
        $websitePlatform = in_array($this->platform, ['website-opd', 'website-media-partner'], true);
        $analytics = AudienceAnalytics::make($this->period(), $this->scope());

        if ($websitePlatform) {
            $age = $analytics->byAge();

            return [
                'chart' => ['type' => 'bar', 'height' => 220, 'stacked' => false],
                'series' => [
                    ['name' => '16-64', 'data' => [$age->get('16-64', 0)]],
                ],
                'colors' => ['#3E68B2'],
                'plotOptions' => ['bar' => ['horizontal' => false, 'columnWidth' => '60%', 'borderRadius' => 8]],
                'dataLabels' => ['enabled' => false],
                'xaxis' => [
                    'categories' => ['16-64'],
                    'labels' => ['style' => ['fontFamily' => 'JetBrains Mono, monospace', 'fontSize' => '11px']],
                    'axisBorder' => ['show' => false],
                    'axisTicks' => ['show' => false],
                ],
                'yaxis' => ['labels' => ['style' => ['fontFamily' => 'JetBrains Mono, monospace', 'fontSize' => '11px']]],
                'legend' => ['show' => false],
            ];
        }

        ['female' => $female, 'male' => $male] = $analytics->ageByGender();

        return [
            'chart' => ['type' => 'bar', 'height' => 340, 'stacked' => true],
            'series' => [
                ['name' => 'Perempuan', 'data' => $female->map(fn (int $v): int => -$v)->values()->all()],
                ['name' => 'Laki-laki', 'data' => $male->values()->all()],
            ],
            'colors' => ['#3DC8F4', '#3E68B2'],
            'plotOptions' => ['bar' => ['horizontal' => true, 'barHeight' => '74%', 'borderRadius' => 4]],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'categories' => $female->keys()->all(),
                'labels' => ['show' => false],
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            'yaxis' => ['labels' => ['style' => ['fontFamily' => 'JetBrains Mono, monospace', 'fontSize' => '11px']]],
            'legend' => ['position' => 'top', 'horizontalAlign' => 'left'],
        ];
    }

    /** @return array{total:int, dominant:string, share:float} */
    #[Computed]
    public function highlight(): array
    {
        $age = AudienceAnalytics::make($this->period(), $this->scope())->byAge();
        $total = max($age->sum(), 1);
        $dominant = $age->sortDesc()->keys()->first() ?? '—';

        return [
            'total' => $age->sum(),
            'dominant' => $dominant,
            'share' => round(($age[$dominant] ?? 0) / $total * 100, 1),
        ];
    }

    public function render()
    {
        return view('livewire.admin.age-spectrum');
    }
}
