<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasAnalyticsFilters;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Demografi Audiens')]
class DemographicsPanel extends Component
{
    use HasAnalyticsFilters;

    #[Computed]
    public function snapshotDate(): ?string
    {
        return AudienceAnalytics::make($this->period(), $this->scope())->latestDate();
    }

    /**
     * Cakupan halaman ini selalu seluruh Kutai Timur — saringan jenis OPD sudah
     * dihapus karena membingungkan dan tidak punya pasangan pemilih OPD di sini.
     */
    public function scope(): AccountScope
    {
        return AccountScope::make()->platform($this->platform);
    }

    /**
     * Kanal yang benar-benar dipakai, urut Instagram lalu Facebook.
     *
     * Demografi Instagram dan Facebook bisa berbeda jauh — usia pengikut
     * Instagram condong lebih muda. Menggabungkannya sejak awal menutupi
     * perbedaan itu, jadi tiap kanal ditampilkan sendiri lebih dulu.
     *
     * @return list<string>
     */
    #[Computed]
    public function platforms(): array
    {
        // Saat pengguna sudah menyaring ke satu platform, tidak perlu dipecah lagi.
        if ($this->platform !== '') {
            return [];
        }

        $dimiliki = SocialAccount::whereIn('id', AccountScope::make()->accountIds())
            ->distinct()
            ->pluck('platform')
            ->all();

        $ada = array_values(array_filter(
            [SocialAccount::PLATFORM_INSTAGRAM, SocialAccount::PLATFORM_FACEBOOK],
            fn (string $p): bool => in_array($p, $dimiliki, true),
        ));

        // Satu kanal saja tidak perlu dipisah dari gabungannya — angkanya sama.
        return count($ada) > 1 ? $ada : [];
    }

    public function isWebsitePlatform(): bool
    {
        return in_array($this->platform, ['website-opd', 'website-media-partner'], true);
    }

    public function isWebsitePlatformFor(string $platform): bool
    {
        return in_array($platform, ['website-opd', 'website-media-partner'], true);
    }

    public function websitePlatformLabel(): string
    {
        return $this->platform === 'website-media-partner'
            ? 'Website Media Partner'
            : 'Website OPD';
    }

    public function websiteAgeRangeLabel(): string
    {
        return 'Rentang usia yang dipakai untuk demografi website adalah 16–64 tahun. Data website diambil dari total views hasil scrape di halaman web terkait.';
    }

    public function render()
    {
        return view('livewire.admin.demographics-panel', [
            'platforms' => $this->platformOptions(),
        ]);
    }
}
