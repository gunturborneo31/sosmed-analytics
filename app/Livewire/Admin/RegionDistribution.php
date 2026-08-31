<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ReceivesFilters;
use App\Services\Analytics\AudienceAnalytics;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RegionDistribution extends Component
{
    use ReceivesFilters;

    public int $limit = 12;

    /** @return Collection<string, int> */
    #[Computed]
    public function cities(): Collection
    {
        return AudienceAnalytics::make($this->period(), $this->scope())->byCity($this->limit);
    }

    public function render()
    {
        return view('livewire.admin.region-distribution');
    }
}
