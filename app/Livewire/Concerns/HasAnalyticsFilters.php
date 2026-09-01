<?php

namespace App\Livewire\Concerns;

use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Support\Period;
use Livewire\Attributes\Url;

/**
 * Filter yang wajib ada di seluruh halaman rekap (§10.2).
 * Disimpan di query string supaya tautan hasil filter bisa dibagikan apa adanya.
 */
trait HasAnalyticsFilters
{
    #[Url(as: 'periode')]
    public string $period = '30';

    #[Url(as: 'dari')]
    public ?string $from = null;

    #[Url(as: 'sampai')]
    public ?string $until = null;

    #[Url(as: 'jenis')]
    public string $unitType = '';

    #[Url(as: 'platform')]
    public string $platform = '';

    public function period(): Period
    {
        try {
            return Period::fromKey($this->period, $this->from, $this->until);
        } catch (\InvalidArgumentException) {
            // Rentang kustom belum lengkap diisi — pakai bawaan sampai user selesai.
            return Period::default();
        }
    }

    public function scope(): AccountScope
    {
        return AccountScope::make()
            ->unitType($this->unitType)
            ->platform($this->platform);
    }

    public function resetFilters(): void
    {
        $this->reset('period', 'from', 'until', 'unitType', 'platform');
    }

    /** @return array<string, string> */
    public function unitTypeOptions(): array
    {
        return [
            '' => 'Semua jenis',
            'dinas' => 'Dinas',
            'badan' => 'Badan',
            'kecamatan' => 'Kecamatan',
            'sekretariat' => 'Sekretariat',
        ];
    }

    /** @return array<string, string> */
    public function platformOptions(): array
    {
        return [
            '' => 'Semua platform',
            SocialAccount::PLATFORM_INSTAGRAM => 'Instagram',
            SocialAccount::PLATFORM_FACEBOOK => 'Facebook',
            'website-opd' => 'Website OPD',
            'website-media-partner' => 'Website Media Partner',
        ];
    }

    /** @return array<int, string> */
    protected function unitOptions(): array
    {
        return OrganizationalUnit::active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
