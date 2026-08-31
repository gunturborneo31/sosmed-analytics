<?php

namespace App\Jobs;

use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Services\Analytics\CountyAnalytics;
use App\Support\Period;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Menghangatkan cache agregat sebelum jam kerja (§9.5), supaya halaman admin
 * tidak menghitung ulang rekap 40+ akun pada permintaan pertama tiap pagi.
 */
class BuildDailyAggregates implements ShouldQueue
{
    use Queueable;

    public const TTL_MINUTES = 180;

    public function handle(): void
    {
        foreach (['7', '30', '90'] as $key) {
            $period = Period::fromKey($key);
            $scope = AccountScope::make();

            Cache::put(
                self::summaryKey($key),
                CountyAnalytics::make($period, $scope)->summary(),
                now()->addMinutes(self::TTL_MINUTES),
            );

            Cache::put(
                self::audienceKey($key),
                [
                    'age' => AudienceAnalytics::make($period, $scope)->byAge()->all(),
                    'gender' => AudienceAnalytics::make($period, $scope)->byGender()->all(),
                ],
                now()->addMinutes(self::TTL_MINUTES),
            );
        }
    }

    public static function summaryKey(string $periodKey): string
    {
        return "agregat:ringkasan:{$periodKey}";
    }

    public static function audienceKey(string $periodKey): string
    {
        return "agregat:audiens:{$periodKey}";
    }
}
