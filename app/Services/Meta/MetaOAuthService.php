<?php

namespace App\Services\Meta;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Alur izin Meta (§9.3): code → token pendek → token panjang → daftar Page & akun IG.
 */
class MetaOAuthService
{
    /** Izin yang diminta (§4.3). */
    public const SCOPES = [
        'instagram_basic',
        'instagram_manage_insights',
        'pages_show_list',
        'pages_read_engagement',
        'business_management',
    ];

    /**
     * Izin yang diminta per platform — operator tidak perlu menyetujui akses
     * Instagram kalau yang dihubungkan cuma Facebook Page, dan sebaliknya.
     *
     * `pages_show_list` tetap ada di keduanya: akun Instagram Business
     * ditemukan lewat Facebook Page yang menaunginya, bukan langsung.
     *
     * @var array<string, list<string>>
     */
    public const SCOPES_PER_PLATFORM = [
        'instagram' => [
            'instagram_basic',
            'instagram_manage_insights',
            'pages_show_list',
        ],
        'facebook' => [
            'pages_show_list',
            'pages_read_engagement',
            'business_management',
        ],
    ];

    /** @return list<string> */
    public static function scopesFor(?string $platform): array
    {
        return self::SCOPES_PER_PLATFORM[$platform] ?? self::SCOPES;
    }

    public function __construct(private readonly MetaGraphClient $client) {}

    public static function make(): self
    {
        return new self(MetaGraphClient::make());
    }

    public function isConfigured(): bool
    {
        return filled(config('services.meta.app_id')) && filled(config('services.meta.app_secret'));
    }

    /**
     * URL dialog izin. `state` diverifikasi lagi di callback untuk mencegah CSRF (§12).
     */
    public function authorizationUrl(string $state, ?string $platform = null): string
    {
        $params = array_filter([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect'),
            'state' => $state,
            'response_type' => 'code',
            // config_id berasal dari Facebook Login for Business (§4.4). Kalau belum
            // dibuat, Meta menerima daftar scope biasa sebagai gantinya.
            'config_id' => config('services.meta.config_id'),
            'scope' => config('services.meta.config_id')
                ? null
                : implode(',', self::scopesFor($platform)),
        ]);

        return 'https://www.facebook.com/'.config('services.meta.graph_version')
            .'/dialog/oauth?'.http_build_query($params);
    }

    /**
     * Langkah 5–6: code → token pendek → token panjang (~60 hari).
     *
     * @return array{token:string, expires_at:?CarbonImmutable}
     */
    public function exchangeCodeForLongLivedToken(string $code): array
    {
        $short = $this->client->get('oauth/access_token', [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'redirect_uri' => config('services.meta.redirect'),
            'code' => $code,
        ]);

        if (! isset($short['access_token'])) {
            throw new MetaGraphException('Meta tidak mengembalikan access token untuk kode otorisasi ini.');
        }

        return $this->exchangeForLongLived((string) $short['access_token']);
    }

    /**
     * Menukar token apa pun jadi token panjang — juga dipakai RefreshExpiringTokens.
     *
     * @return array{token:string, expires_at:?CarbonImmutable}
     */
    public function exchangeForLongLived(string $token): array
    {
        $long = $this->client->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'fb_exchange_token' => $token,
        ]);

        return [
            'token' => (string) ($long['access_token'] ?? $token),
            'expires_at' => isset($long['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $long['expires_in'])
                : CarbonImmutable::now()->addDays(60),
        ];
    }

    /**
     * Langkah 7–8: daftar Page yang dikelola, beserta akun IG Business yang tertaut.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function managedAccounts(string $userToken): Collection
    {
        $response = $this->client->get('me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url}',
            'limit' => 100,
        ], $userToken);

        return collect($response['data'] ?? [])
            ->flatMap(function (array $page): array {
                $accounts = [[
                    'platform' => 'facebook',
                    'platform_account_id' => (string) $page['id'],
                    'username' => null,
                    'display_name' => $page['name'] ?? null,
                    'avatar_url' => null,
                    // Page token: inilah yang dipakai memanggil insight, bukan token user.
                    'access_token' => $page['access_token'] ?? null,
                ]];

                if (isset($page['instagram_business_account']['id'])) {
                    $ig = $page['instagram_business_account'];

                    $accounts[] = [
                        'platform' => 'instagram',
                        'platform_account_id' => (string) $ig['id'],
                        'username' => $ig['username'] ?? null,
                        'display_name' => $ig['name'] ?? ($page['name'] ?? null),
                        'avatar_url' => $ig['profile_picture_url'] ?? null,
                        'access_token' => $page['access_token'] ?? null,
                    ];
                }

                return $accounts;
            })
            ->filter(fn (array $account): bool => filled($account['access_token']))
            ->values();
    }
}
