<?php

namespace App\Exports;

use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use App\Support\Period;
use App\Support\SocialPlatform;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Data mentah per OPD untuk diolah lebih lanjut (§10.3).
 */
class CountyRecapExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    public function __construct(
        private readonly Period $period,
        private readonly AccountScope $scope,
    ) {}

    /** @return Collection<int, object> */
    public function collection(): Collection
    {
        return CountyAnalytics::make($this->period, $this->scope)->ranking();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Perangkat Daerah',
            'Jenis',
            'Platform',
            'Jumlah Akun',
            'Pengikut',
            'Pengikut Awal Periode',
            'Pertumbuhan (%)',
            'Jangkauan',
            'Engagement Rate (%)',
        ];
    }

    /**
     * @param  object  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->unit_name,
            $row->unit_type,
            // Tanpa kolom ini pembaca tidak bisa tahu satu baris berasal dari
            // satu kanal atau penjumlahan Instagram dan Facebook.
            $this->platformLabels($row->platforms ?? ''),
            $row->accounts,
            $row->followers,
            $row->followers_before,
            // Sel dikosongkan bila tidak ada pembanding — jangan menyodorkan
            // angka yang akan ikut terhitung saat diolah lebih lanjut.
            $row->growth,
            $row->reach,
            $row->engagement_rate,
        ];
    }

    /** "facebook, instagram" → "Facebook + Instagram" */
    private function platformLabels(string $raw): string
    {
        return collect(explode(',', $raw))
            ->map(fn (string $p): string => SocialPlatform::label(trim($p)))
            ->filter()
            ->sort()
            ->join(' + ');
    }

    public function title(): string
    {
        return 'Rekap '.$this->period->from->format('Y-m-d').' sd '.$this->period->until->format('Y-m-d');
    }

    /** @return array<string, mixed> Ringkasan untuk laporan PDF. */
    public function context(): array
    {
        $analytics = CountyAnalytics::make($this->period, $this->scope);
        $audience = AudienceAnalytics::make($this->period, $this->scope);

        return [
            'period' => $this->period,
            'summary' => $analytics->summary(),
            'ranking' => $analytics->ranking(),
            'age' => $audience->byAge(),
            'gender' => $audience->byGender(),
            'cities' => $audience->byCity(10),
            'platformFilter' => $this->scope->platformFilter(),
            'selectedUnits' => $this->selectedUnitNames(),
            'perPlatform' => $this->perPlatform(),
        ];
    }

    /**
     * Nama OPD yang dipilih; kosong berarti laporan mencakup seluruh kabupaten.
     *
     * @return Collection<int, string>
     */
    private function selectedUnitNames(): Collection
    {
        $ids = $this->scope->unitFilter();

        return $ids === []
            ? collect()
            : OrganizationalUnit::whereIn('id', $ids)->orderBy('name')->pluck('name');
    }

    /**
     * Angka per platform saat laporan mencakup seluruh platform.
     *
     * Tabel utama menjumlahkan Instagram dan Facebook jadi satu baris per OPD,
     * jadi pembaca perlu tahu porsi masing-masing — tanpa ini angkanya terbaca
     * seolah berasal dari satu kanal saja.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function perPlatform(): Collection
    {
        if ($this->scope->platformFilter() !== null) {
            return collect();
        }

        return collect([SocialAccount::PLATFORM_INSTAGRAM, SocialAccount::PLATFORM_FACEBOOK])
            ->map(function (string $platform): array {
                $ringkas = CountyAnalytics::make($this->period, $this->scope->withPlatform($platform))->summary();

                return [
                    'platform' => $platform,
                    'label' => SocialPlatform::label($platform),
                    'akun' => $ringkas['accounts_connected'],
                    'pengikut' => $ringkas['followers'],
                    'jangkauan' => $ringkas['reach'],
                    'engagement' => $ringkas['engagement_rate'],
                ];
            })
            // Platform yang belum dipakai satu OPD pun tidak perlu jadi baris kosong.
            ->filter(fn (array $baris): bool => $baris['akun'] > 0)
            ->values();
    }
}
