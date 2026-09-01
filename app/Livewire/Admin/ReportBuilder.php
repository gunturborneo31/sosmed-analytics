<?php

namespace App\Livewire\Admin;

use App\Exports\CountyRecapExport;
use App\Livewire\Concerns\HasAnalyticsFilters;
use App\Models\OrganizationalUnit;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use App\Services\Analytics\PublicInformationIndex;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Susun & ekspor laporan (§10.3).
 */
#[Layout('components.layouts.app')]
#[Title('Rekap & Laporan')]
class ReportBuilder extends Component
{
    use HasAnalyticsFilters;

    /** @var list<string> */
    public array $units = [];

    /** @var list<string> */
    public array $selectedPlatforms = [];

    /** Pencarian nama OPD — daftarnya panjang, mengetik lebih cepat dari menggulir. */
    public string $unitSearch = '';

    /**
     * Penyebut IKK. Angka kependudukan berubah tiap tahun dan datang dari
     * BPS/Dukcapil, jadi admin bisa menyesuaikannya langsung di halaman ini.
     *
     * Nilainya tetap ada di URL supaya admin bisa mencoba angka lain lalu
     * membagikan tautannya tanpa mengubah apa pun untuk orang lain. Yang
     * berlaku sebagai nilai resmi adalah yang tersimpan lewat tombol Simpan.
     */
    #[Url(as: 'penduduk')]
    public ?int $population = null;

    public function mount(): void
    {
        $this->population ??= Setting::jumlahPenduduk();
    }

    /** Nilai resmi yang tersimpan — pembanding untuk angka di kolom isian. */
    #[Computed]
    public function savedPopulation(): int
    {
        return Setting::jumlahPenduduk();
    }

    /**
     * Menetapkan angka di kolom isian sebagai penyebut resmi kabupaten.
     * Berlaku untuk semua admin dan seluruh laporan berikutnya, jadi dibatasi
     * ke peran yang memang mengelola data tingkat kabupaten.
     */
    public function savePopulation(): void
    {
        abort_unless(auth()->user()->can('manage-organizational-units'), 403);

        if ($this->population === null || $this->population < 1) {
            $this->dispatch('toast', type: 'error', message: 'Jumlah penduduk harus lebih dari nol.');

            return;
        }

        Setting::put(Setting::JUMLAH_PENDUDUK, $this->population, auth()->id());

        unset($this->savedPopulation, $this->ikk);

        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Jumlah penduduk disimpan: '.number_format($this->population, 0, ',', '.').'.',
        );
    }

    /** OPD yang boleh dipilih — dipersempit hanya oleh kata pencarian. */
    #[Computed]
    public function selectableUnits(): Collection
    {
        return OrganizationalUnit::active()
            ->when($this->unitSearch !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.$this->unitSearch.'%']))
            ->orderBy('name')
            ->get();
    }

    /** Nama OPD terpilih, untuk dibacakan ulang sebelum berkas diunduh. */
    #[Computed]
    public function selectedUnitNames(): Collection
    {
        if ($this->units === []) {
            return collect();
        }

        return OrganizationalUnit::whereIn('id', $this->units)->orderBy('name')->pluck('name');
    }

    public function selectAllUnits(): void
    {
        $this->units = $this->selectableUnits->pluck('id')->all();
    }

    public function clearUnits(): void
    {
        $this->units = [];
    }

    public function resetFilters(): void
    {
        $this->reset('period', 'from', 'until', 'platform', 'selectedPlatforms', 'units', 'unitSearch');
    }

    public function isWebsitePlatformFor(string $platform): bool
    {
        return in_array($platform, ['website-opd', 'website-media-partner'], true);
    }

    public function hasWebsitePlatformSelected(): bool
    {
        return ! empty(array_intersect(['website-opd', 'website-media-partner'], $this->selectedPlatforms));
    }

    /** @return list<string> */
    public function visibleSelectedPlatforms(): array
    {
        return $this->selectedPlatforms !== []
            ? $this->selectedPlatforms
            : array_values(array_filter(array_keys($this->platformOptions()), fn (string $key): bool => $key !== ''));
    }

    /** @return list<array{platform:string, label:string, pembilang:int, penyebut:int, persentase:float}> */
    public function selectedPlatformCalculations(): array
    {
        $platforms = $this->visibleSelectedPlatforms();

        $rows = array_values(array_map(function (string $platform): array {
            $summary = PublicInformationIndex::make(
                $this->period(),
                AccountScope::make()->platform($platform)->units($this->units),
                $this->population > 0 ? $this->population : null,
            )->summary();

            return [
                'platform' => $platform,
                'label' => $this->platformOptions()[$platform] ?? $platform,
                'pembilang' => (int) $summary['pembilang'],
                'penyebut' => (int) $summary['penyebut'],
                'persentase' => (float) $summary['persentase'],
            ];
        }, $platforms));

        $totalPembilang = array_sum(array_map(fn (array $row): int => (int) $row['pembilang'], $rows));
        $denominator = $this->population > 0
            ? $this->population
            : (int) collect($rows)->first()['penyebut'] ?? 0;
        $totalPersentase = $denominator > 0 ? round(($totalPembilang / $denominator) * 100, 2) : 0.0;

        $rows[] = [
            'platform' => 'all',
            'label' => 'Akumulasi semua',
            'pembilang' => $totalPembilang,
            'penyebut' => $denominator,
            'persentase' => $totalPersentase,
        ];

        return $rows;
    }

    /** @return list<array{platform:string, label:string, age:list<array{label:string, count:int}>, gender:list<array{label:string, count:int, percent:float}>}> */
    #[Computed]
    public function platformAudienceSummaries(): array
    {
        $platforms = [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'combined' => 'Akumulasi Instagram + Facebook',
        ];

        $summaries = [];

        foreach ($platforms as $key => $label) {
            $scope = match ($key) {
                'instagram' => AccountScope::make()->platform('instagram')->units($this->units),
                'facebook' => AccountScope::make()->platform('facebook')->units($this->units),
                'combined' => AccountScope::make()->platforms(['instagram', 'facebook'])->units($this->units),
            };

            $age = AudienceAnalytics::make($this->period(), $scope)->byAge();
            $gender = AudienceAnalytics::make($this->period(), $scope)->byGender();
            $genderTotal = max($gender->sum(), 1);

            $summaries[] = [
                'platform' => $key,
                'label' => $label,
                'age' => $age
                    ->map(fn (int $count, string $bucket): array => ['label' => $bucket, 'count' => $count])
                    ->values()
                    ->all(),
                'gender' => [
                    ['label' => 'Perempuan', 'count' => (int) $gender['F'], 'percent' => round(($gender['F'] / $genderTotal) * 100, 1)],
                    ['label' => 'Laki-laki', 'count' => (int) $gender['M'], 'percent' => round(($gender['M'] / $genderTotal) * 100, 1)],
                    ['label' => 'Tidak diketahui', 'count' => (int) $gender['U'], 'percent' => round(($gender['U'] / $genderTotal) * 100, 1)],
                ],
            ];
        }

        return $summaries;
    }

    /** @return list<array{label:string, count:int, penyebut:int, persentase:float}> */
    #[Computed]
    public function combinedAgeCalculations(): array
    {
        $scope = AccountScope::make()->platforms(['instagram', 'facebook'])->units($this->units);
        $age = AudienceAnalytics::make($this->period(), $scope)->byAge();
        $penyebut = $this->population > 0 ? $this->population : 1;

        return $age
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
                'penyebut' => $penyebut,
                'persentase' => $penyebut > 0 ? round(($count / $penyebut) * 100, 2) : 0.0,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{platform:string, label:string, age:list<array{label:string, count:int}>}> */
    #[Computed]
    public function websiteAudienceSummaries(): array
    {
        $platforms = [
            'website-opd' => 'Website OPD',
            'website-media-partner' => 'Website Media Partner',
            'combined' => 'Akumulasi Website OPD + Website Media Partner',
        ];

        $summaries = [];

        foreach ($platforms as $key => $label) {
            $scope = match ($key) {
                'website-opd' => AccountScope::make()->platform('website-opd')->units($this->units),
                'website-media-partner' => AccountScope::make()->platform('website-media-partner')->units($this->units),
                'combined' => AccountScope::make()->platforms(['website-opd', 'website-media-partner'])->units($this->units),
            };

            $age = AudienceAnalytics::make($this->period(), $scope)->byAge();

            $summaries[] = [
                'platform' => $key,
                'label' => $label,
                'age' => $age
                    ->map(fn (int $count, string $bucket): array => ['label' => $bucket, 'count' => $count])
                    ->values()
                    ->all(),
            ];
        }

        return $summaries;
    }

    /**
     * Cakupan laporan. Jenis OPD sengaja tidak ikut: halaman ini sudah
     * mempersempit lewat daftar centang perangkat daerah, dan dua saringan yang
     * bekerja bersamaan gampang menghasilkan rekap kosong tanpa penjelasan.
     */
    public function scope(): AccountScope
    {
        return AccountScope::make()->units($this->units);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function ikk(): array
    {
        return PublicInformationIndex::make(
            $this->period(),
            $this->scope(),
            $this->population > 0 ? $this->population : null,
        )->summary();
    }

    /** @return Collection<int, object> */
    #[Computed]
    public function preview(): Collection
    {
        return CountyAnalytics::make($this->period(), $this->scope())->ranking()->take(10);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return CountyAnalytics::make($this->period(), $this->scope())->summary();
    }

    public function exportExcel(): BinaryFileResponse|StreamedResponse
    {
        abort_unless(auth()->user()->can('export-report'), 403);

        $period = $this->period();

        return (new CountyRecapExport($period, $this->scope()))->download(
            'rekap-medsos-kutim-'.$period->fromDate().'-sd-'.$period->untilDate().'.xlsx',
        );
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->can('export-report'), 403);

        $period = $this->period();
        $context = (new CountyRecapExport($period, $this->scope()))->context();
        $context['generatedBy'] = auth()->user()->name;
        $context['logo'] = $this->embeddedLogo();
        $context['ikk'] = $this->ikk;

        $pdf = Pdf::loadView('exports.county-recap', $context)->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'laporan-medsos-kutim-'.$period->fromDate().'-sd-'.$period->untilDate().'.pdf',
        );
    }

    /**
     * dompdf tidak mengambil berkas lewat URL, jadi logo ditanam sebagai data URI.
     */
    private function embeddedLogo(): ?string
    {
        $path = public_path('img/logo.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    public function render()
    {
        return view('livewire.admin.report-builder', [
            'platforms' => $this->platformOptions(),
        ]);
    }
}
