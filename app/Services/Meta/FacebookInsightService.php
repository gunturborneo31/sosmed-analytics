<?php

namespace App\Services\Meta;

use App\Models\AudienceBreakdown;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;

/**
 * Menarik insight Facebook Page (§9.4).
 */
class FacebookInsightService
{
    use FetchesDailyMetrics;

    public function __construct(private readonly MetaGraphClient $client) {}

    public static function make(): self
    {
        return new self(MetaGraphClient::make());
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(SocialAccount $account): array
    {
        return $this->client->get($account->platform_account_id, [
            'fields' => 'name,username,fan_count,picture{url}',
        ], $account->access_token);
    }

    /**
     * @return array{reach:int, impressions:int, profile_views:int, interactions:int, dilewati:list<string>, raw:array<string, mixed>}
     */
    public function dailyMetrics(SocialAccount $account, Carbon $date): array
    {
        return $this->fetchDaily(
            $this->client,
            $account->platform_account_id.'/insights',
            (array) config('meta.metrik.facebook'),
            [
                'period' => 'day',
                'since' => $date->copy()->startOfDay()->timestamp,
                'until' => $date->copy()->addDay()->startOfDay()->timestamp,
            ],
            $account->access_token,
        );
    }

    /**
     * Deret harian untuk satu rentang — dipakai mengisi riwayat akun baru.
     *
     * @return array<string, array<string, int>> tanggal => kolom => nilai
     */
    public function metricsRange(SocialAccount $account, Carbon $from, Carbon $until): array
    {
        return $this->fetchRange(
            $this->client,
            $account->platform_account_id.'/insights',
            (array) config('meta.metrik.facebook'),
            [
                'period' => 'day',
                'since' => $from->copy()->startOfDay()->timestamp,
                'until' => $until->copy()->addDay()->startOfDay()->timestamp,
            ],
            $account->access_token,
            $from,
            $until,
        );
    }

    /**
     * Pertambahan pengikut per hari — bahan merekonstruksi kurva pengikut
     * historis, yang tidak disediakan Meta secara langsung.
     *
     * @return array<string, int> tanggal => pengikut baru hari itu
     */
    public function newFollowerSeries(SocialAccount $account, Carbon $from, Carbon $until): array
    {
        $harian = $this->fetchRange(
            $this->client,
            $account->platform_account_id.'/insights',
            ['baru' => [(string) config('meta.pengikut_baru.facebook')]],
            [
                'period' => 'day',
                'since' => $from->copy()->startOfDay()->timestamp,
                'until' => $until->copy()->addDay()->startOfDay()->timestamp,
            ],
            $account->access_token,
        );

        return array_map(fn (array $nilai): int => $nilai['baru'] ?? 0, $harian);
    }

    /**
     * Demografi penggemar Page. Kunci Meta berbentuk "F.25-34" / nama kota.
     *
     * @return array<string, array<string, int>>
     */
    public function fanDemographics(SocialAccount $account): array
    {
        $metrics = (array) config('meta.demografi_facebook');
        $byMetric = [];

        // Diminta satu per satu: Meta menolak seluruh permintaan bila satu nama
        // metrik di dalamnya sudah dihentikan, sehingga meminta ketiganya
        // sekaligus membuat demografi hilang seluruhnya begitu salah satu mati.
        foreach ($metrics as $metric) {
            $response = $this->client->tryGet($account->platform_account_id.'/insights', [
                'metric' => $metric,
                'period' => 'lifetime',
            ], $account->access_token);

            foreach ($response['data'] ?? [] as $row) {
                $byMetric[$row['name']] = $row['values'][0]['value'] ?? [];
            }
        }

        $ageGender = $this->intValues($byMetric[$metrics['age_gender'] ?? ''] ?? []);
        $result = [];

        if ($ageGender !== []) {
            $result[AudienceBreakdown::DIMENSION_AGE_GENDER] = $ageGender;
            $result[AudienceBreakdown::DIMENSION_AGE] = $this->collapse($ageGender, fn (string $k): string => explode('.', $k)[1] ?? $k);
            $result[AudienceBreakdown::DIMENSION_GENDER] = $this->collapse($ageGender, fn (string $k): string => explode('.', $k)[0] ?? $k);
        }

        foreach ([
            AudienceBreakdown::DIMENSION_CITY => $metrics['city'] ?? '',
            AudienceBreakdown::DIMENSION_COUNTRY => $metrics['country'] ?? '',
        ] as $dimension => $metric) {
            $values = $this->intValues($byMetric[$metric] ?? []);

            if ($values !== []) {
                $result[$dimension] = $values;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, int>
     */
    private function intValues(array $values): array
    {
        return array_map(intval(...), array_filter($values, is_numeric(...)));
    }

    /**
     * @param  array<string, int>  $values
     * @return array<string, int>
     */
    private function collapse(array $values, callable $keyFor): array
    {
        $collapsed = [];

        foreach ($values as $key => $value) {
            $bucket = $keyFor($key);
            $collapsed[$bucket] = ($collapsed[$bucket] ?? 0) + $value;
        }

        return $collapsed;
    }
}
