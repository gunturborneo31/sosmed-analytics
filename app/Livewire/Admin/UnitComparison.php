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
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Bandingkan 2–5 OPD berdampingan (§10.4).
 */
#[Layout('components.layouts.app')]
#[Title('Perbandingan OPD')]
class UnitComparison extends Component
{
    use HasAnalyticsFilters;

    public const MAX_UNITS = 5;

    /** @var list<string> */
    #[Url(as: 'opd')]
    public array $selected = [];

    public string $search = '';

    /**
     * Cara mengukur tren: 'jumlah' (pengikut apa adanya) atau 'pertumbuhan'
     * (persentase terhadap awal periode).
     *
     * Dua OPD berukuran mirip tapi berbeda laju — misalnya +26 pengikut lawan
     * +1.848 — mustahil dibandingkan pada satu sumbu absolut: garis yang naik
     * 0,6% tampak lurus di sebelah garis yang naik 43%. Mode pertumbuhan
     * menyamakan titik berangkat semua OPD di 0% sehingga lajunya bisa diadu.
     */
    #[Url(as: 'ukur')]
    public string $metric = 'jumlah';

    public function toggle(string $unitId): void
    {
        if (in_array($unitId, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$unitId]));
            $this->segarkanGrafik();

            return;
        }

        if (count($this->selected) >= self::MAX_UNITS) {
            $this->addError('selected', 'Maksimal '.self::MAX_UNITS.' perangkat daerah agar grafik tetap terbaca.');

            return;
        }

        $this->resetErrorBag('selected');
        $this->selected[] = $unitId;
        $this->segarkanGrafik();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->segarkanGrafik();
    }

    /** @return Collection<int, OrganizationalUnit> */
    #[Computed]
    public function candidates(): Collection
    {
        // Jenis OPD tidak lagi menyaring: halaman ini sudah punya pemilih OPD
        // sendiri, dan dua saringan bersamaan gampang mengosongkan daftar.
        $daftar = OrganizationalUnit::active()
            ->when($this->search, fn ($q, $search) => $q->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"]))
            ->orderBy('name')
            ->limit(40)
            ->get();

        /*
         | OPD yang sedang terpilih selalu ikut ditampilkan, walau tersaring
         | keluar oleh pencarian atau terlempar ke luar 40 kandidat pertama.
         | Tidak cukup melebarkan klausa WHERE — LIMIT tetap memotongnya
         | berdasarkan urutan nama, jadi barisnya diambil terpisah lalu digabung.
         */
        $terpilih = $this->selected === []
            ? collect()
            : OrganizationalUnit::whereIn('id', $this->selected)->get();

        return $daftar->concat($terpilih)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Satu baris per OPD terpilih, siap ditampilkan berdampingan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function comparison(): Collection
    {
        return OrganizationalUnit::whereIn('id', $this->selected)
            ->orderBy('name')
            ->get()
            ->map(function (OrganizationalUnit $unit): array {
                $scope = AccountScope::make()->platform($this->platform)->units([$unit->id]);
                $period = $this->period();

                return [
                    'unit' => $unit,
                    'summary' => CountyAnalytics::make($period, $scope)->summary(),
                    // Angka gabungan menutupi kanal mana yang sebenarnya bekerja
                    // di OPD ini, jadi rincian per kanal ikut dibawa.
                    'per_kanal' => $this->perKanal($unit),
                    // Kanal yang benar-benar dimiliki OPD ini DI BAWAH saringan
                    // yang sedang berlaku — dipakai menandai kartunya, dan
                    // membedakan "belum punya akun" dari "angkanya nol".
                    'kanal' => $this->kanalDimiliki($unit),
                    'age' => AudienceAnalytics::make($period, $scope)->byAge(),
                    'gender' => AudienceAnalytics::make($period, $scope)->byGender(),
                    'cities' => AudienceAnalytics::make($period, $scope)->byCity(5),
                ];
            });
    }

    /**
     * Kanal yang dimiliki OPD ini, sudah dipersempit oleh saringan platform.
     *
     * @return list<string>
     */
    private function kanalDimiliki(OrganizationalUnit $unit): array
    {
        $punya = SocialAccount::whereIn(
            'id',
            AccountScope::make()->platform($this->platform)->units([$unit->id])->accountIds(),
        )->distinct()->pluck('platform')->all();

        return array_values(array_filter(
            [SocialAccount::PLATFORM_INSTAGRAM, SocialAccount::PLATFORM_FACEBOOK],
            fn (string $p): bool => in_array($p, $punya, true),
        ));
    }

    /**
     * Ringkasan tiap kanal milik satu OPD. Kanal yang tidak dipakai OPD itu
     * tidak dimunculkan sebagai baris kosong.
     *
     * @return array<string, array<string, mixed>>
     */
    private function perKanal(OrganizationalUnit $unit): array
    {
        // Sudah disaring ke satu platform — tidak ada yang perlu dipecah.
        if ($this->platform !== '') {
            return [];
        }

        $hasil = [];

        foreach ([SocialAccount::PLATFORM_INSTAGRAM, SocialAccount::PLATFORM_FACEBOOK] as $kanal) {
            $scope = AccountScope::make()->platform($kanal)->units([$unit->id]);
            $ringkas = CountyAnalytics::make($this->period(), $scope)->summary();

            if ($ringkas['accounts_connected'] > 0) {
                $hasil[$kanal] = $ringkas;
            }
        }

        // Satu kanal saja tidak perlu dipisah dari gabungannya — angkanya sama.
        return count($hasil) > 1 ? $hasil : [];
    }

    public function setMetric(string $metric): void
    {
        $this->metric = in_array($metric, ['jumlah', 'pertumbuhan'], true) ? $metric : 'jumlah';
        $this->segarkanGrafik();
    }

    public function growthMode(): bool
    {
        return $this->metric === 'pertumbuhan';
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function trendChart(): array
    {
        $period = $this->period();

        $series = OrganizationalUnit::whereIn('id', $this->selected)
            ->orderBy('name')
            ->get()
            ->map(function (OrganizationalUnit $unit) use ($period): array {
                $scope = AccountScope::make()->platform($this->platform)->units([$unit->id]);
                $nilai = CountyAnalytics::make($period, $scope)->trend()->pluck('followers')->all();

                return [
                    'name' => $unit->name,
                    'data' => $this->growthMode() ? $this->keRelatif($nilai) : $nilai,
                ];
            })
            ->values()
            ->all();

        $labels = CountyAnalytics::make($period, AccountScope::make()->units($this->selected))
            ->trend()
            ->map(fn ($r) => Carbon::parse($r->snapshot_date)->translatedFormat('j M'))
            ->all();

        return [
            'chart' => ['type' => 'line', 'height' => 320],
            'series' => $series,
            'colors' => ['#3E68B2', '#3DC8F4', '#7FBEEA', '#2E4E86', '#16A46B'],
            'stroke' => ['curve' => 'smooth', 'width' => 2.5],
            'dataLabels' => ['enabled' => false],
            'xaxis' => [
                'categories' => $labels,
                'tickAmount' => 7,
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            'yaxis' => $this->sumbuNilai($series),
            // Penanda satuan; formatter-nya dipasang di app.js karena JSON
            // tidak bisa membawa fungsi.
            'satuanNilai' => $this->growthMode() ? 'persen' : 'jumlah',
            'legend' => ['position' => 'top', 'horizontalAlign' => 'left'],
        ];
    }

    /**
     * Perubahan pengikut tiap OPD sepanjang periode, sebagai angka.
     *
     * Garis yang naik 0,6% memang terlihat rata di sebelah garis yang naik 43%,
     * dan tidak ada penyetelan sumbu yang bisa mengubah itu tanpa berbohong.
     * Jadi angkanya disajikan sebagai teks — supaya pertumbuhan kecil tetap
     * terbaca walau grafiknya tampak lurus.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function trendSummary(): Collection
    {
        return OrganizationalUnit::whereIn('id', $this->selected)
            ->orderBy('name')
            ->get()
            ->map(function (OrganizationalUnit $unit): array {
                $nilai = CountyAnalytics::make(
                    $this->period(),
                    AccountScope::make()->platform($this->platform)->units([$unit->id]),
                )->trend()->pluck('followers')->all();

                $awal = collect($nilai)->first(fn (int $n): bool => $n > 0) ?? 0;
                $akhir = $nilai === [] ? 0 : (int) end($nilai);

                return [
                    'nama' => $unit->name,
                    'selisih' => $akhir - $awal,
                    'persen' => $awal > 0 ? round(($akhir - $awal) / $awal * 100, 2) : null,
                ];
            })
            ->values();
    }

    /**
     * Deret pengikut diubah jadi persentase terhadap titik awal periode.
     *
     * @param  list<int>  $nilai
     * @return list<float>
     */
    private function keRelatif(array $nilai): array
    {
        $awal = 0;

        foreach ($nilai as $n) {
            if ($n > 0) {
                $awal = $n;

                break;
            }
        }

        // Tanpa titik berangkat yang sah, persentase tidak punya arti.
        if ($awal === 0) {
            return array_map(fn (): float => 0.0, $nilai);
        }

        return array_map(fn (int $n): float => round(($n - $awal) / $awal * 100, 2), $nilai);
    }

    /**
     * Sumbu nilai yang merapat ke datanya.
     *
     * Tidak dipaksa mulai dari nol: OPD dengan 4.353 pengikut yang bertambah 26
     * orang bergerak 0,6%, dan pada sumbu 0–7.000 gerakan itu hilang sama sekali.
     *
     * @param  list<array<string, mixed>>  $series
     * @return array<string, mixed>
     */
    private function sumbuNilai(array $series): array
    {
        $semua = collect($series)->flatMap(fn (array $s): array => $s['data'])->all();

        if ($semua === []) {
            return ['decimalsInFloat' => 0];
        }

        $bawah = min($semua);
        $atas = max($semua);
        $jeda = max(abs($atas - $bawah) * 0.15, $this->growthMode() ? 0.5 : 1);

        return [
            'min' => $this->growthMode() ? $bawah - $jeda : max(0, $bawah - $jeda),
            'max' => $atas + $jeda,
            'decimalsInFloat' => $this->growthMode() ? 1 : 0,
        ];
    }

    /**
     * Grafik hidup di luar siklus render Livewire (`wire:ignore`), jadi datanya
     * HARUS dikirim lewat event.
     *
     * Hook `updated` hanya terpicu oleh perubahan properti lewat wire:model —
     * memilih OPD memakai aksi `toggle()`, yang tidak melewatinya sama sekali.
     * Karena itu setiap jalur yang mengubah pilihan memanggil segarkanGrafik()
     * sendiri; tanpa itu grafiknya tertinggal menampilkan pilihan lama.
     */
    public function updated(): void
    {
        $this->segarkanGrafik();
    }

    private function segarkanGrafik(): void
    {
        unset($this->comparison, $this->trendChart, $this->trendSummary, $this->candidates);

        $this->dispatch('chart:update', id: 'tren-perbandingan', options: $this->trendChart);
    }

    public function render()
    {
        return view('livewire.admin.unit-comparison', [
            'platforms' => $this->platformOptions(),
        ]);
    }
}
