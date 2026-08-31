<?php

namespace App\Services\Meta;

use App\Models\AudienceBreakdown;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;

/**
 * Menarik insight Instagram Business (§9.4).
 */
class InstagramInsightService
{
    use FetchesDailyMetrics;

    public function __construct(private readonly MetaGraphClient $client) {}

    public static function make(): self
    {
        return new self(MetaGraphClient::make());
    }

    /**
     * Klien yang cocok untuk akun ini.
     *
     * Akun lewat Facebook Login memegang token Page dan dipanggil di
     * graph.facebook.com; akun lewat Instagram Login memegang token pengguna
     * Instagram yang hanya berlaku di graph.instagram.com. Menukar keduanya
     * membuat token ditolak.
     */
    private function clientFor(SocialAccount $account): MetaGraphClient
    {
        return $account->auth_source === SocialAccount::AUTH_INSTAGRAM
            ? MetaGraphClient::makeForInstagramLogin()
            : $this->client;
    }

    /**
     * Instagram Login memanggil profil lewat `me`; jalur Facebook memakai ID
     * akun IG yang ditemukan dari Page.
     */
    private function profilePath(SocialAccount $account): string
    {
        return $account->auth_source === SocialAccount::AUTH_INSTAGRAM
            ? 'me'
            : $account->platform_account_id;
    }

    /**
     * Profil akun — sekaligus penyegar username/avatar yang bisa berubah.
     *
     * @return array<string, mixed>
     */
    public function profile(SocialAccount $account): array
    {
        return $this->clientFor($account)->get($this->profilePath($account), [
            'fields' => 'username,name,profile_picture_url,followers_count,media_count',
        ], $account->access_token);
    }

    /**
     * Metrik harian. Meta mengembalikan deret per hari, kita ambil nilai untuk tanggal diminta.
     *
     * Nama metriknya diambil dari `config/meta.php` karena Meta rutin
     * menggantinya — `impressions` sudah dihentikan dan digantikan `views`.
     *
     * @return array{reach:int, impressions:int, profile_views:int, interactions:int, dilewati:list<string>, raw:array<string, mixed>}
     */
    public function dailyMetrics(SocialAccount $account, Carbon $date): array
    {
        return $this->fetchDaily(
            $this->clientFor($account),
            $account->platform_account_id.'/insights',
            (array) config('meta.metrik.instagram'),
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
            $this->clientFor($account),
            $account->platform_account_id.'/insights',
            (array) config('meta.metrik.instagram'),
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
            $this->clientFor($account),
            $account->platform_account_id.'/insights',
            ['baru' => [(string) config('meta.pengikut_baru.instagram')]],
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
     * Demografi pengikut — data usia, gender, dan wilayah. Ini yang utama (§4.3).
     *
     * @return array<string, array<string, int>> dimension => {bucket: jumlah}
     */
    public function followerDemographics(SocialAccount $account): array
    {
        $breakdowns = [
            AudienceBreakdown::DIMENSION_AGE => 'age',
            AudienceBreakdown::DIMENSION_GENDER => 'gender',
            AudienceBreakdown::DIMENSION_AGE_GENDER => 'age,gender',
            AudienceBreakdown::DIMENSION_CITY => 'city',
            AudienceBreakdown::DIMENSION_COUNTRY => 'country',
        ];

        $result = [];

        foreach ($breakdowns as $dimension => $breakdown) {
            $response = $this->clientFor($account)->tryGet($account->platform_account_id.'/insights', [
                'metric' => 'follower_demographics',
                'period' => 'lifetime',
                'metric_type' => 'total_value',
                // Wajib untuk follower_demographics — tanpa ini Meta menolak
                // permintaannya, dan seluruh demografi gagal tersimpan.
                'timeframe' => config('meta.demografi_timeframe'),
                'breakdown' => $breakdown,
            ], $account->access_token);

            $parsed = $this->parseBreakdown($response ?? [], $dimension);

            if ($parsed !== []) {
                $result[$dimension] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Bentuk respons breakdown Meta:
     * data[0].total_value.breakdowns[0].results[] = { dimension_values: [...], value: n }
     *
     * @param  array<string, mixed>  $response
     * @return array<string, int>
     */
    private function parseBreakdown(array $response, string $dimension): array
    {
        $results = $response['data'][0]['total_value']['breakdowns'][0]['results'] ?? [];
        $parsed = [];

        foreach ($results as $row) {
            $values = $row['dimension_values'] ?? [];

            if ($values === []) {
                continue;
            }

            $key = $dimension === AudienceBreakdown::DIMENSION_AGE_GENDER
                ? $this->genderFirst($values)
                : implode('.', $values);

            $parsed[$key] = (int) ($row['value'] ?? 0);
        }

        return $parsed;
    }

    /**
     * Instagram mengembalikan nilai breakdown mengikuti urutan yang diminta —
     * `breakdown=age,gender` menghasilkan ["13-17", "F"]. Facebook memakai
     * urutan sebaliknya ("F.13-17"), dan seluruh aplikasi mengikuti bentuk
     * Facebook. Tanpa dibalik di sini, grafik cermin usia×gender tidak pernah
     * menemukan kuncinya dan diam-diam jatuh ke perkiraan dari rasio gender.
     *
     * @param  list<string>  $values  [usia, gender]
     */
    private function genderFirst(array $values): string
    {
        [$usia, $gender] = [$values[0] ?? '', $values[1] ?? ''];

        return $gender === '' ? $usia : "{$gender}.{$usia}";
    }
}
