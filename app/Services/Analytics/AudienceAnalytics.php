<?php

namespace App\Services\Analytics;

use App\Models\AudienceBreakdown;
use App\Support\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
    * Menggabungkan demografi seluruh akun jadi satu profil audiens kabupaten (§10.1).
    *
    * Nilai disimpan sebagai JSON; agregasi dilakukan setelah snapshot terbaru tiap
    * akun dipilih agar query berjalan baik pada SQLite maupun PostgreSQL.
 */
final class AudienceAnalytics
{
    /**
     * Kelompok umur bawaan Meta, dalam urutan usia — bukan urutan alfabet.
     * Dipakai untuk membaca data mentah; yang ditampilkan ke pengguna disaring
     * lebih dulu lewat {@see self::ageGroups()}.
     */
    public const AGE_ORDER = ['13-17', '18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

    public function __construct(
        private readonly Period $period,
        private readonly AccountScope $scope,
    ) {}

    public static function make(Period $period, ?AccountScope $scope = null): self
    {
        return new self($period, $scope ?? AccountScope::make());
    }

    /**
     * Kelompok usia yang boleh tampil di aplikasi — hanya 16–64 tahun, sesuai
     * definisi operasional IKK. Daftarnya diatur di `config/ikk.php`.
     *
     * @return Collection<string, array{label:string, porsi:float, alasan?:string}>
     */
    public static function ageGroups(): Collection
    {
        return collect(config('ikk.kelompok_usia'));
    }

    /**
     * Spektrum usia agregat, sudah dibatasi pada rentang 16–64 tahun.
     *
     * @return Collection<string, int>
     */
    public function byAge(): Collection
    {
        return self::withinRange($this->aggregate(AudienceBreakdown::DIMENSION_AGE));
    }

    /**
     * Spektrum usia apa adanya dari Meta, termasuk kelompok di luar rentang.
     * Hanya untuk hitungan internal — jangan dipakai menampilkan label ke
     * pengguna, karena kelompok di luar 16–64 tidak boleh muncul di layar.
     *
     * @return Collection<string, int>
     */
    public function byAgeRaw(): Collection
    {
        $totals = $this->aggregate(AudienceBreakdown::DIMENSION_AGE);

        return collect(self::AGE_ORDER)
            ->mapWithKeys(fn (string $group): array => [$group => (int) ($totals[$group] ?? 0)]);
    }

    /**
     * Saring agregat mentah jadi kelompok 16–64 tahun. Kelompok yang batasnya
     * tidak jatuh persis di usia 16 diambil sesuai porsinya, dan kelompok yang
     * seluruhnya di luar rentang hilang berikut labelnya.
     *
     * @param  Collection<string, int>  $totals
     * @return Collection<string, int>
     */
    private static function withinRange(Collection $totals, string $prefix = ''): Collection
    {
        return self::ageGroups()->mapWithKeys(fn (array $spec, string $bucket): array => [
            $spec['label'] => (int) round((int) ($totals[$prefix.$bucket] ?? 0) * $spec['porsi']),
        ]);
    }

    /**
     * Rasio gender. Kunci Meta: F / M / U (unknown).
     *
     * @return Collection<string, int>
     */
    public function byGender(): Collection
    {
        $totals = $this->aggregate(AudienceBreakdown::DIMENSION_GENDER);

        return collect(['F', 'M', 'U'])
            ->mapWithKeys(fn (string $key): array => [$key => (int) ($totals[$key] ?? 0)]);
    }

    /**
     * Sebaran wilayah, diurutkan dari yang terbesar.
     *
     * @return Collection<string, int>
     */
    public function byCity(int $limit = 12): Collection
    {
        return $this->aggregate(AudienceBreakdown::DIMENSION_CITY)
            ->sortDesc()
            ->take($limit);
    }

    /**
     * Spektrum usia dipecah per gender — bahan grafik cermin ♀ | ♂ (§8.3).
     *
     * @return array{female:Collection<string,int>, male:Collection<string,int>}
     */
    public function ageByGender(): array
    {
        $totals = $this->aggregate(AudienceBreakdown::DIMENSION_AGE_GENDER);

        $split = fn (string $prefix): Collection => self::withinRange($totals, "{$prefix}.");

        $female = $split('F');
        $male = $split('M');

        // Meta tidak selalu mengirim breakdown age_gender; jatuhkan ke perkiraan
        // dari rasio gender agar grafik tetap punya bentuk, bukan kosong.
        if ($female->sum() === 0 && $male->sum() === 0) {
            $gender = $this->byGender();
            $totalGender = max($gender['F'] + $gender['M'], 1);
            $age = $this->byAge();

            $female = $age->map(fn (int $v): int => (int) round($v * $gender['F'] / $totalGender));
            $male = $age->map(fn (int $v): int => (int) round($v * $gender['M'] / $totalGender));
        }

        return ['female' => $female, 'male' => $male];
    }

    /**
     * Tanggal snapshot demografi terbaru dalam rentang — demografi bersifat
     * `lifetime` di Meta, jadi yang dipakai satu tanggal, bukan dijumlah harian.
     */
    public function latestDate(): ?string
    {
        $date = DB::table('audience_breakdowns')
            ->whereIn('social_account_id', $this->scope->accountIds())
            ->where('snapshot_date', '<=', $this->period->untilDate())
            ->max('snapshot_date');

        return $date ? (string) $date : null;
    }

    /**
     * @return Collection<string, int>
     */
    private function aggregate(string $dimension): Collection
    {
        /*
         | Dipakai snapshot terbaru MILIK TIAP AKUN, bukan satu tanggal terbaru
         | se-kabupaten.
         |
         | Sebelumnya seluruh akun disaring ke satu tanggal yang sama. Begitu satu
         | akun tersinkron lebih dulu — hal biasa: jadwal meleset, sinkronisasi
         | gagal lalu diulang esoknya — tanggal terbarunya maju sendirian, dan
         | SELURUH akun lain terbuang dari hitungan tanpa jejak. Demografi,
         | pembilang IKK, dan perbandingan ikut anjlok tanpa penjelasan.
         |
         | Demografi Meta bersifat `lifetime`, jadi menjumlahkan snapshot terbaru
         | tiap akun memang cara yang benar — bukan menjumlah lintas hari.
        */
        $terbaru = DB::table('audience_breakdowns as current_breakdown')
            ->select(['current_breakdown.social_account_id', 'current_breakdown.data'])
            ->whereIn('current_breakdown.social_account_id', $this->scope->accountIds())
            ->where('current_breakdown.dimension', $dimension)
            ->where('current_breakdown.snapshot_date', '<=', $this->period->untilDate())
            ->whereNotExists(function ($newer) use ($dimension): void {
                $newer->selectRaw('1')
                    ->from('audience_breakdowns as newer_breakdown')
                    ->whereColumn('newer_breakdown.social_account_id', 'current_breakdown.social_account_id')
                    ->where('newer_breakdown.dimension', $dimension)
                    ->where('newer_breakdown.snapshot_date', '<=', $this->period->untilDate())
                    ->whereColumn('newer_breakdown.snapshot_date', '>', 'current_breakdown.snapshot_date');
            })
            ->get();

        return $terbaru->reduce(function (Collection $totals, object $snapshot): Collection {
            foreach ((array) $snapshot->data as $bucket => $value) {
                $totals[$bucket] = ($totals[$bucket] ?? 0) + (int) $value;
            }

            return $totals;
        }, collect());
    }
}
