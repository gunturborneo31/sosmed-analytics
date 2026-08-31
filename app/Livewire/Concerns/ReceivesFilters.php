<?php

namespace App\Livewire\Concerns;

use App\Services\Analytics\AccountScope;
use App\Support\Period;

/**
 * Dipakai komponen anak yang menerima filter dari halaman induk.
 */
trait ReceivesFilters
{
    public string $period = '30';

    public ?string $from = null;

    public ?string $until = null;

    public string $unitType = '';

    public string $platform = '';

    public function period(): Period
    {
        try {
            return Period::fromKey($this->period, $this->from, $this->until);
        } catch (\InvalidArgumentException) {
            return Period::default();
        }
    }

    public function scope(): AccountScope
    {
        return AccountScope::make()
            ->unitType($this->unitType)
            ->platform($this->platform);
    }
}
