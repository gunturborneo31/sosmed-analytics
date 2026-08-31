<?php

namespace App\Services\Analytics;

use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Support\Period;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap agregat se-kabupaten (§10.1) — inti panel admin.
 */
final class CountyAnalytics
{
    public function __construct(
        private readonly Period $period,
        private readonly AccountScope $scope,
    ) {}

    public static function make(Period $period, ?AccountScope $scope = null): self
    {
        return new self($period, $scope ?? AccountScope::make());
    }

    /**
     * Empat angka di kartu ringkasan (§8.3).
     *
     * @return array{
     *     accounts_connected:int, units_total:int, units_connected:int,
     *     followers:int, followers_delta:?float, reach:int, reach_delta:?float,
     *     engagement_rate:float
     * }
     */
    public function summary(): array
    {
        $followers = $this->followersAsOf($this->period->untilDate());
        $followersBefore = $this->followersAsOf($this->period->previous()->untilDate());

        $reach = $this->reachBetween($this->period);

        /*
         | Penyebut harus mengikuti filter yang sama dengan pembilangnya.
         | Sebelumnya ini menghitung seluruh OPD aktif tanpa peduli filter,
         | sehingga memilih satu OPD menghasilkan "1 / 51" — terbaca seolah
         | hanya 1 dari 51 OPD yang terhubung, padahal yang lain memang tidak
         | ikut dipilih.
        */
        $unitsTotal = (int) $this->scope->unitQuery()->count();

        return [
            'accounts_connected' => (int) $this->scope->query()->count(),
            'units_total' => $unitsTotal,
            'units_connected' => (int) $this->scope->query()->distinct()->count('ou.id'),
            'followers' => $followers,
            'followers_delta' => $this->percentChange($followersBefore, $followers),
            'reach' => $reach,
            'reach_delta' => $this->reachDelta($reach),
            'engagement_rate' => $this->averageEngagement(),
        ];
    }

    /**
     * Peringkat perangkat daerah (§8.3).
     *
     * @return Collection<int, object>
     */
    public function ranking(string $sortBy = 'followers', string $direction = 'desc'): Collection
    {
        $latest = $this->latestSnapshotSub($this->period->untilDate());
        $baseline = $this->latestSnapshotSub($this->period->previous()->untilDate());
        $platforms = DB::getDriverName() === 'pgsql'
            ? "STRING_AGG(DISTINCT akun.platform, ', ')"
            : 'GROUP_CONCAT(DISTINCT akun.platform)';

        $rows = DB::query()
            ->fromSub($this->scope->query()->select([
                'sa.id as account_id',
                'ou.id as unit_id',
                'ou.name as unit_name',
                'ou.slug as unit_slug',
                'ou.type as unit_type',
                'sa.platform',
            ]), 'akun')
            ->leftJoinSub($latest, 'kini', 'kini.social_account_id', '=', 'akun.account_id')
            ->leftJoinSub($baseline, 'awal', 'awal.social_account_id', '=', 'akun.account_id')
            ->leftJoinSub($this->periodTotalsSub(), 'total', 'total.social_account_id', '=', 'akun.account_id')
            ->groupBy('akun.unit_id', 'akun.unit_name', 'akun.unit_slug', 'akun.unit_type')
            ->selectRaw("\n                akun.unit_id,
                akun.unit_name,
                akun.unit_slug,
                akun.unit_type,
                COUNT(akun.account_id) AS accounts,
                {$platforms} AS platforms,
                COALESCE(SUM(kini.followers_count), 0) AS followers,
                COALESCE(SUM(awal.followers_count), 0) AS followers_before,
                COALESCE(SUM(total.reach), 0) AS reach,
                COALESCE(SUM(total.interactions), 0) AS interactions
            ")
            ->get();

        return $rows
            ->map(function (object $row): object {
                $row->followers = (int) $row->followers;
                $row->followers_before = (int) $row->followers_before;
                $row->reach = (int) $row->reach;
                $row->accounts = (int) $row->accounts;
                $row->interactions = (int) $row->interactions;
                // Definisi yang sama dengan kartu ringkasan: interaksi sepanjang
                // periode dibagi pengikut, bukan rerata rasio harian.
                $row->engagement_rate = $row->followers > 0
                    ? round(min($row->interactions / $row->followers * 100, 999.99), 2)
                    : 0.0;
                $row->growth = $this->percentChange($row->followers_before, $row->followers);

                return $row;
            })
            ->sortBy(
                // Baris tanpa pembanding (growth null) selalu di ujung, tidak
                // pernah menempati puncak peringkat.
                fn (object $row) => $row->{$this->sortColumn($sortBy)} ?? -INF,
                SORT_REGULAR,
                $direction === 'desc',
            )
            ->values();
    }

    /**
     * Tren harian se-kabupaten untuk grafik garis.
     *
     * @return Collection<int, object>
     */
    public function trend(): Collection
    {
        return DB::table('insight_snapshots as s')
            ->whereIn('s.social_account_id', $this->scope->accountIds())
            ->whereBetween('s.snapshot_date', [$this->period->fromDate(), $this->period->untilDate()])
            ->groupBy('s.snapshot_date')
            ->orderBy('s.snapshot_date')
            ->selectRaw('
                s.snapshot_date,
                SUM(s.followers_count) AS followers,
                SUM(s.reach) AS reach,
                SUM(s.interactions) AS interactions
            ')
            ->get()
            ->map(fn (object $row): object => tap($row, function (object $r): void {
                $r->followers = (int) $r->followers;
                $r->reach = (int) $r->reach;
                $r->interactions = (int) $r->interactions;
                $r->engagement_rate = $r->followers > 0
                    ? round(min($r->interactions / $r->followers * 100, 999.99), 2)
                    : 0.0;
            }));
    }

    /**
     * Kartu "Perlu Perhatian" (§8.3) — mengarahkan pendampingan, bukan menyalahkan.
     *
     * @return array{unconnected:int, expiring:int, stale:int, failed_syncs:int}
     */
    public function attention(): array
    {
        return [
            'unconnected' => OrganizationalUnit::active()->unconnected()->count(),
            'expiring' => SocialAccount::active()->expiringWithin(7)->count(),
            'stale' => SocialAccount::active()
                ->where(fn ($q) => $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subDays(30)))
                ->count(),
            'failed_syncs' => DB::table('sync_logs')
                ->whereIn('status', ['failed', 'partial'])
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    private function followersAsOf(string $date): int
    {
        return (int) DB::query()
            ->fromSub($this->latestSnapshotSub($date), 'terakhir')
            ->sum('terakhir.followers_count');
    }

    /**
     * Perubahan jangkauan, atau null bila periode pembandingnya belum punya
     * cukup hari untuk dibandingkan.
     *
     * Jangkauan diakumulasi sepanjang periode, jadi 30 hari data dibandingkan
     * dengan periode pembanding yang hanya berisi satu-dua hari menghasilkan
     * lonjakan semu — akun yang baru terhubung ke Meta akan terlihat melesat
     * ribuan persen padahal yang berbeda cuma banyaknya hari yang tercatat.
     */
    private function reachDelta(int $reach): ?float
    {
        $previous = $this->period->previous();
        $harusnya = $this->coveredDays($this->period);

        if ($harusnya > 0 && $this->coveredDays($previous) < $harusnya / 2) {
            return null;
        }

        return $this->percentChange($this->reachBetween($previous), $reach);
    }

    /** Banyaknya hari yang benar-benar punya snapshot di dalam satu periode. */
    private function coveredDays(Period $period): int
    {
        return (int) DB::table('insight_snapshots')
            ->whereIn('social_account_id', $this->scope->accountIds())
            ->whereBetween('snapshot_date', [$period->fromDate(), $period->untilDate()])
            ->distinct()
            ->count('snapshot_date');
    }

    private function reachBetween(Period $period): int
    {
        return (int) DB::table('insight_snapshots')
            ->whereIn('social_account_id', $this->scope->accountIds())
            ->whereBetween('snapshot_date', [$period->fromDate(), $period->untilDate()])
            ->sum('reach');
    }

    /**
     * Engagement rate untuk SATU PERIODE: seluruh interaksi selama periode itu
     * dibagi jumlah pengikut.
     *
     * Sebelumnya ini merata-ratakan rasio harian. Itu besaran yang berbeda dan
     * jauh lebih kecil — akun dengan 7 interaksi dalam 14 hari menghasilkan
     * rerata harian ~0,005% yang membulat jadi 0,00%, seolah tidak ada
     * interaksi sama sekali. Yang lazim dikutip orang adalah interaksi
     * sepanjang periode dibagi pengikut, dan itu yang dipakai sekarang.
     */
    private function averageEngagement(): float
    {
        $followers = $this->followersAsOf($this->period->untilDate());

        if ($followers <= 0) {
            return 0.0;
        }

        $interaksi = (int) DB::table('insight_snapshots')
            ->whereIn('social_account_id', $this->scope->accountIds())
            ->whereBetween('snapshot_date', [$this->period->fromDate(), $this->period->untilDate()])
            ->sum('interactions');

        return round(min($interaksi / $followers * 100, 999.99), 2);
    }

    /**
     * Snapshot terbaru per akun sampai tanggal tertentu.
     *
     * NOT EXISTS dipakai agar query ini juga berjalan pada SQLite lokal, bukan
     * hanya PostgreSQL yang menyediakan DISTINCT ON.
     */
    private function latestSnapshotSub(string $date): Builder
    {
        return DB::table('insight_snapshots')
            ->from('insight_snapshots as current_snapshot')
            ->select([
                'current_snapshot.social_account_id',
                'current_snapshot.followers_count',
                'current_snapshot.snapshot_date',
            ])
            ->whereIn('social_account_id', $this->scope->accountIds())
            ->where('current_snapshot.snapshot_date', '<=', $date)
            ->whereNotExists(function (Builder $newer) use ($date): void {
                $newer->selectRaw('1')
                    ->from('insight_snapshots as newer_snapshot')
                    ->whereColumn('newer_snapshot.social_account_id', 'current_snapshot.social_account_id')
                    ->where('newer_snapshot.snapshot_date', '<=', $date)
                    ->whereColumn('newer_snapshot.snapshot_date', '>', 'current_snapshot.snapshot_date');
            });
    }

    private function periodTotalsSub(): Builder
    {
        return DB::table('insight_snapshots')
            ->whereBetween('snapshot_date', [$this->period->fromDate(), $this->period->untilDate()])
            ->groupBy('social_account_id')
            ->selectRaw('social_account_id, SUM(reach) AS reach, SUM(interactions) AS interactions');
    }

    private function sortColumn(string $sortBy): string
    {
        return match ($sortBy) {
            'growth' => 'growth',
            'reach' => 'reach',
            'engagement' => 'engagement_rate',
            'name' => 'unit_name',
            default => 'followers',
        };
    }

    /**
     * Persentase perubahan, atau null bila tidak ada pembanding.
     *
     * Naik dari nol bukan "+100%" — itu pertumbuhan tak terhingga, dan menampilkannya
     * sebagai angka membuat OPD yang baru terhubung terlihat melesat. Periode tanpa
     * data pembanding dilaporkan apa adanya: tidak ada perbandingan.
     */
    private function percentChange(int|float $before, int|float $after): ?float
    {
        if ($before <= 0) {
            return $after > 0 ? null : 0.0;
        }

        return round((($after - $before) / $before) * 100, 1);
    }
}
