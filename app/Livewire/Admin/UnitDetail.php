<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasAnalyticsFilters;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class UnitDetail extends Component
{
    use HasAnalyticsFilters;

    public OrganizationalUnit $unit;

    public function mount(OrganizationalUnit $unit): void
    {
        $this->unit = $unit;
    }

    public function scope(): AccountScope
    {
        return AccountScope::make()
            ->platform($this->platform)
            ->units([$this->unit->id]);
    }

    public function updated(): void
    {
        $this->dispatch('chart:update', id: 'tren-opd', options: $this->trendChart);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return CountyAnalytics::make($this->period(), $this->scope())->summary();
    }

    /** @return Collection<int, SocialAccount> */
    #[Computed]
    public function accounts(): Collection
    {
        return $this->unit->socialAccounts()->orderBy('platform')->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function trendChart(): array
    {
        $trend = CountyAnalytics::make($this->period(), $this->scope())->trend();
        $pengikut = $trend->pluck('followers')->all();

        /*
         | Sumbu pengikut sengaja TIDAK dipaksa mulai dari nol.
         |
         | Akun dengan 4.353 pengikut yang bertambah 26 orang dalam 90 hari
         | bergerak 0,6% — pada sumbu 0–4.400 gerakan itu tak terlihat sama
         | sekali dan grafiknya terbaca sebagai garis lurus, seolah tidak
         | seorang pun pernah mengikuti. Rentangnya dirapatkan ke data supaya
         | pertumbuhan yang nyata benar-benar terbaca.
         |
         | Jangkauan tetap dari nol: itu hitungan harian yang wajar dibandingkan
         | terhadap nol, bukan saldo berjalan.
        */
        $bawah = $pengikut === [] ? 0 : min($pengikut);
        $atas = $pengikut === [] ? 0 : max($pengikut);
        $jeda = max((int) ceil(max($atas - $bawah, 1) * 0.3), 1);

        return [
            'chart' => ['type' => 'line', 'height' => 280],
            'series' => [
                ['name' => 'Pengikut', 'data' => $trend->pluck('followers')->all()],
                ['name' => 'Jangkauan', 'data' => $trend->pluck('reach')->all()],
            ],
            'colors' => ['#3E68B2', '#3DC8F4'],
            'stroke' => ['curve' => 'smooth', 'width' => [2.5, 2]],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'categories' => $trend->map(fn ($r) => Carbon::parse($r->snapshot_date)->translatedFormat('j M'))->all(),
                'tickAmount' => 7,
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            'yaxis' => [
                [
                    'seriesName' => 'Pengikut',
                    'title' => ['text' => 'Pengikut'],
                    'min' => max(0, $bawah - $jeda),
                    'max' => $atas + $jeda,
                    'decimalsInFloat' => 0,
                ],
                [
                    'seriesName' => 'Jangkauan',
                    'opposite' => true,
                    'title' => ['text' => 'Jangkauan'],
                    'min' => 0,
                    'decimalsInFloat' => 0,
                ],
            ],
            'legend' => ['position' => 'top', 'horizontalAlign' => 'left'],
        ];
    }

    /** @return Collection<string, int> */
    #[Computed]
    public function ageProfile(): Collection
    {
        return AudienceAnalytics::make($this->period(), $this->scope())->byAge();
    }

    /** @return Collection<string, int> */
    #[Computed]
    public function cityProfile(): Collection
    {
        return AudienceAnalytics::make($this->period(), $this->scope())->byCity(8);
    }

    public function render()
    {
        return view('livewire.admin.unit-detail', [
            'platforms' => $this->platformOptions(),
        ])->title($this->unit->name);
    }
}
