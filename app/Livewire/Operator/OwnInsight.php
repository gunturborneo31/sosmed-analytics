<?php

namespace App\Livewire\Operator;

use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use App\Support\Period;
use App\Support\SocialPlatform;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Insight akun sendiri — ringkas, tanpa kedalaman analitik panel admin (§1).
 *
 * Angkanya dipisah per kanal lebih dulu, baru gabungannya di bagian bawah.
 * Instagram dan Facebook punya perilaku audiens yang berbeda; menjumlahkannya
 * sejak awal menyembunyikan kanal mana yang sebenarnya bekerja.
 */
#[Layout('components.layouts.app')]
#[Title('Insight Akun')]
class OwnInsight extends Component
{
    #[Url(as: 'periode')]
    public string $period = '30';

    #[Url(as: 'dari')]
    public ?string $from = null;

    #[Url(as: 'sampai')]
    public ?string $until = null;

    /**
     * Rentang kustom baru sah setelah kedua tanggalnya terisi. Sebelum itu —
     * termasuk saat pengguna baru memilih "Rentang kustom" dan kolom tanggalnya
     * belum muncul — dipakai rentang bawaan, bukan melempar galat yang berujung
     * 500 di halaman operator.
     */
    public function period(): Period
    {
        try {
            return Period::fromKey($this->period, $this->from, $this->until);
        } catch (\InvalidArgumentException) {
            return Period::default();
        }
    }

    /**
     * Cakupan dikunci ke OPD milik user — operator tidak pernah bisa
     * memperluasnya lewat parameter apa pun (§12).
     */
    public function scope(?string $platform = null): AccountScope
    {
        $user = auth()->user();

        $scope = $user->seesAllUnits()
            ? AccountScope::make()
            : AccountScope::make()->units([$user->organizational_unit_id]);

        return $platform ? $scope->platform($platform) : $scope;
    }

    /**
     * Kanal yang benar-benar dimiliki OPD ini, urut Instagram lalu Facebook.
     * Kanal yang belum dihubungkan tidak perlu jadi bagian kosong.
     *
     * @return list<string>
     */
    #[Computed]
    public function platforms(): array
    {
        $dimiliki = SocialAccount::whereIn('id', $this->scope()->accountIds())
            ->distinct()
            ->pluck('platform')
            ->all();

        return array_values(array_filter(
            [SocialAccount::PLATFORM_INSTAGRAM, SocialAccount::PLATFORM_FACEBOOK],
            fn (string $p): bool => in_array($p, $dimiliki, true),
        ));
    }

    /**
     * Grafik dibungkus `wire:ignore` supaya ApexCharts tidak dibongkar-pasang
     * tiap render, jadi isinya HARUS diperbarui lewat event — kalau tidak,
     * mengganti periode hanya mengubah angka di kartu sementara grafiknya
     * tertinggal menampilkan periode lama.
     *
     * Cache properti terhitung dibuang lebih dulu; tanpa itu event membawa
     * susunan grafik periode sebelumnya.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['period', 'from', 'until'], true)) {
            return;
        }

        unset($this->summary, $this->trendChart, $this->ageProfile, $this->genderSplit, $this->platforms);

        foreach ($this->platforms as $platform) {
            $this->dispatch('chart:update', id: "tren-{$platform}", options: $this->trendChartFor($platform));
        }

        $this->dispatch('chart:update', id: 'tren-sendiri', options: $this->trendChart);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return $this->summaryFor(null);
    }

    /** @return array<string, mixed> */
    public function summaryFor(?string $platform): array
    {
        return CountyAnalytics::make($this->period(), $this->scope($platform))->summary();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function trendChart(): array
    {
        return $this->trendChartFor(null);
    }

    /** @return array<string, mixed> */
    public function trendChartFor(?string $platform): array
    {
        $trend = CountyAnalytics::make($this->period(), $this->scope($platform))->trend();

        return [
            'chart' => ['type' => 'area', 'height' => 260],
            'series' => [['name' => 'Jangkauan', 'data' => $trend->pluck('reach')->all()]],
            // Warna mengikuti merek kanalnya supaya grafik langsung dikenali.
            'colors' => [SocialPlatform::chartColor($platform)],
            'stroke' => ['curve' => 'smooth', 'width' => 2.5],
            'fill' => [
                'type' => 'gradient',
                'gradient' => ['opacityFrom' => 0.35, 'opacityTo' => 0.02, 'stops' => [0, 100]],
            ],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'categories' => $trend->map(fn ($r) => Carbon::parse($r->snapshot_date)->translatedFormat('j M'))->all(),
                'tickAmount' => 6,
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            'legend' => ['show' => false],
        ];
    }

    /** @return Collection<string, int> */
    #[Computed]
    public function ageProfile(): Collection
    {
        return $this->ageProfileFor(null);
    }

    /** @return Collection<string, int> */
    public function ageProfileFor(?string $platform): Collection
    {
        return AudienceAnalytics::make($this->period(), $this->scope($platform))->byAge();
    }

    /** @return Collection<string, int> */
    #[Computed]
    public function genderSplit(): Collection
    {
        return $this->genderSplitFor(null);
    }

    /** @return Collection<string, int> */
    public function genderSplitFor(?string $platform): Collection
    {
        return AudienceAnalytics::make($this->period(), $this->scope($platform))->byGender();
    }

    public function render()
    {
        return view('livewire.operator.own-insight');
    }
}
