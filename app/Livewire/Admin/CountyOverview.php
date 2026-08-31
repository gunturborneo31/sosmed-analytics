<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasAnalyticsFilters;
use App\Models\ScrapedArticle;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Ringkasan Kutai Timur')]
class CountyOverview extends Component
{
    use HasAnalyticsFilters, WithPagination;

    /** Peringkat dibaca sepuluh-sepuluh; daftar sepanjang 51 OPD tidak terbaca sekaligus. */
    private const PER_HALAMAN = 10;

    #[Url(as: 'urut')]
    public string $sortBy = 'followers';

    #[Url(as: 'arah')]
    public string $direction = 'desc';

    public bool $confirmingClearData = false;

    public function sort(string $column): void
    {
        $this->direction = $this->sortBy === $column && $this->direction === 'desc' ? 'asc' : 'desc';
        $this->sortBy = $column;

        // Urutan baru berarti daftar yang berbeda — bertahan di halaman 4
        // hanya membuat pembaca mendarat di tengah data tanpa konteks.
        $this->resetPage();
    }

    public function confirmClearData(): void
    {
        abort_unless(auth()->user()->seesAllUnits(), 403);

        $this->confirmingClearData = true;
    }

    public function clearData(): void
    {
        abort_unless(auth()->user()->seesAllUnits(), 403);

        DB::transaction(function (): void {
            ScrapedArticle::query()->delete();
            SocialAccount::query()->delete();
        });

        $this->confirmingClearData = false;
        unset($this->analytics, $this->audience, $this->summary, $this->attention, $this->ranking);
        $this->dispatch('toast', type: 'success', message: 'Data analitik dan hasil scraping sudah dikosongkan.');
    }

    /**
     * Setiap kali filter berubah, grafik dikirimi data baru untuk di-morph —
     * konteks perbandingan tetap terjaga (§7.5).
     */
    public function updated(): void
    {
        $this->resetPage();

        $this->dispatch('chart:update', id: 'tren-kabupaten', options: $this->trendChart);
        $this->dispatch('chart:update', id: 'spektrum-usia', options: $this->ageChart);
    }

    /**
     * Kanal yang benar-benar dipakai OPD, urut Instagram lalu Facebook.
     *
     * Angka gabungan menyembunyikan kanal mana yang sebenarnya bekerja, jadi
     * tiap kanal berdiri sendiri lebih dulu sebelum totalnya disajikan.
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

    /** @return array<string, mixed> */
    public function summaryFor(string $platform): array
    {
        return CountyAnalytics::make(
            $this->period(),
            AccountScope::make()->unitType($this->unitType)->platform($platform),
        )->summary();
    }

    #[Computed]
    public function analytics(): CountyAnalytics
    {
        return CountyAnalytics::make($this->period(), $this->scope());
    }

    #[Computed]
    public function audience(): AudienceAnalytics
    {
        return AudienceAnalytics::make($this->period(), $this->scope());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return $this->analytics()->summary();
    }

    /** @return array<string, int> */
    #[Computed]
    public function attention(): array
    {
        return $this->analytics()->attention();
    }

    /**
     * Seluruh OPD yang lolos filter, dibaca sepuluh-sepuluh.
     *
     * Peringkatnya dihitung di PHP (bukan SQL) karena kolom `growth` dan
     * `engagement` diturunkan setelah query, jadi paginasinya pun dikerjakan
     * atas koleksi yang sudah jadi.
     *
     * @return LengthAwarePaginator<int, object>
     */
    #[Computed]
    public function ranking(): LengthAwarePaginator
    {
        $semua = $this->analytics()->ranking($this->sortBy, $this->direction);
        $halaman = max(1, (int) $this->getPage());

        return new LengthAwarePaginator(
            $semua->forPage($halaman, self::PER_HALAMAN)->values(),
            $semua->count(),
            self::PER_HALAMAN,
            $halaman,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function trendChart(): array
    {
        $trend = $this->analytics()->trend();

        return [
            'chart' => ['type' => 'area', 'height' => 260, 'sparkline' => ['enabled' => false]],
            'series' => [
                ['name' => 'Jangkauan', 'data' => $trend->pluck('reach')->all()],
            ],
            'colors' => ['#3E68B2'],
            'stroke' => ['curve' => 'smooth', 'width' => 2.5],
            'fill' => [
                'type' => 'gradient',
                'gradient' => ['shadeIntensity' => 0.2, 'opacityFrom' => 0.35, 'opacityTo' => 0.02, 'stops' => [0, 100]],
            ],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'categories' => $trend->map(fn ($r) => Carbon::parse($r->snapshot_date)->translatedFormat('j M'))->all(),
                'tickAmount' => 6,
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            // yaxis sengaja tidak diisi — formatter angka Indonesia dipasang di app.js.
            'legend' => ['show' => false],
        ];
    }

    /** Spektrum usia cermin ♀ | ♂ (§8.3). */
    #[Computed]
    public function ageChart(): array
    {
        ['female' => $female, 'male' => $male] = $this->audience()->ageByGender();

        return [
            'chart' => ['type' => 'bar', 'height' => 300, 'stacked' => true],
            'series' => [
                ['name' => 'Perempuan', 'data' => $female->map(fn (int $v): int => -$v)->values()->all()],
                ['name' => 'Laki-laki', 'data' => $male->values()->all()],
            ],
            'colors' => ['#3DC8F4', '#3E68B2'],
            'plotOptions' => ['bar' => ['horizontal' => true, 'barHeight' => '72%', 'borderRadius' => 4]],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'categories' => $female->keys()->all(),
                'labels' => ['show' => false],
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            'yaxis' => ['labels' => ['style' => ['fontFamily' => 'JetBrains Mono, monospace', 'fontSize' => '11px']]],
            'legend' => ['position' => 'top', 'horizontalAlign' => 'left', 'markers' => ['radius' => 12]],
            'grid' => ['xaxis' => ['lines' => ['show' => true]], 'yaxis' => ['lines' => ['show' => false]]],
        ];
    }

    /** @return Collection<string, int> */
    #[Computed]
    public function genderSplit(): Collection
    {
        return $this->audience()->byGender();
    }

    /** @return Collection<string, int> */
    #[Computed]
    public function topCities(): Collection
    {
        return $this->audience()->byCity(6);
    }

    public function render()
    {
        return view('livewire.admin.county-overview', [
            'unitTypes' => $this->unitTypeOptions(),
            'platforms' => $this->platformOptions(),
        ]);
    }
}
