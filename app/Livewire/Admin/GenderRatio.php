<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ReceivesFilters;
use App\Services\Analytics\AudienceAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GenderRatio extends Component
{
    use ReceivesFilters;

    /** @return array{female:int, male:int, unknown:int, total:int, female_pct:float, male_pct:float} */
    #[Computed]
    public function ratio(): array
    {
        if (in_array($this->platform, ['website-opd', 'website-media-partner'], true)) {
            return ['female' => 0, 'male' => 0, 'unknown' => 0, 'total' => 0, 'female_pct' => 0.0, 'male_pct' => 0.0];
        }

        $gender = AudienceAnalytics::make($this->period(), $this->scope())->byGender();
        $known = max($gender['F'] + $gender['M'], 1);

        return [
            'female' => $gender['F'],
            'male' => $gender['M'],
            'unknown' => $gender['U'],
            'total' => $gender->sum(),
            'female_pct' => round($gender['F'] / $known * 100, 1),
            'male_pct' => round($gender['M'] / $known * 100, 1),
        ];
    }

    public function render()
    {
        return view('livewire.admin.gender-ratio');
    }
}
