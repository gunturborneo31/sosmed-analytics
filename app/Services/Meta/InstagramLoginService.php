<?php

namespace App\Services\Meta;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Instagram Business Login — jalur kedua di samping Facebook Login.
 *
 * Bedanya mendasar, bukan sekadar tampilan:
 *
 * - Operator masuk dengan akun Instagram-nya sendiri, bukan akun Facebook.
 * - Akun Instagram TIDAK perlu tertaut Facebook Page.
 * - Kredensialnya terpisah (Instagram App ID/Secret), bukan App ID Facebook.
 * - Panggilan API-nya ke graph.instagram.com, bukan graph.facebook.com.
 *
 * Yang tetap sama: akun harus bertipe Business atau Creator. Akun personal
 * tidak menyediakan endpoint insight sama sekali, apa pun jalur masuknya.
 */
class InstagramLoginService
{
    /**
     * Izin untuk Instagram Login. Namanya berbeda dari izin Graph API lewat
     * Facebook (`instagram_basic`, `instagram_manage_insights`).
     */
    public const SCOPES = [
        'instagram_business_basic',
        'instagram_business_manage_insights',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.meta.instagram_app_id'))
            && filled(config('services.meta.instagram_app_secret'));
    }

    /** URL dialog izin Instagram. `state` diverifikasi lagi di callback (§12). */
    public function authorizationUrl(string $state): string
    {
        return config('services.meta.instagram_auth_url').'?'.http_build_query([
            'client_id' => config('services.meta.instagram_app_id'),
            'redirect_uri' => config('services.meta.instagram_redirect'),
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
            'state' => $state,
        ]);
    }

    /**
     * Tukar kode otorisasi jadi token panjang (~60 hari), lewat dua langkah
     * yang diwajibkan Instagram: code → token pendek → token panjang.
     *
     * @return array{token:string, user_id:string, expires_at:CarbonImmutable}
     */
    public function exchangeCodeForLongLivedToken(string $code): array
    {
        // Langkah 1 — endpoint ini menerima form, bukan query string.
        $pendek = Http::asForm()
            ->timeout(20)
            ->post(config('services.meta.instagram_token_url'), [
                'client_id' => config('services.meta.instagram_app_id'),
                'client_secret' => config('services.meta.instagram_app_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.meta.instagram_redirect'),
                'code' => $code,
            ]);

        $data = $this->decode($pendek);

        if (! isset($data['access_token'])) {
            throw new MetaGraphException('Instagram tidak mengembalikan access token untuk kode otorisasi ini.');
        }

        // Langkah 2 — token pendek hanya berlaku 1 jam, jadi wajib ditukar.
        $panjang = $this->decode(
            Http::acceptJson()->timeout(20)->get(config('services.meta.instagram_graph_url').'/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => config('services.meta.instagram_app_secret'),
                'access_token' => $data['access_token'],
            ])
        );

        return [
            'token' => (string) ($panjang['access_token'] ?? $data['access_token']),
            'user_id' => (string) ($data['user_id'] ?? ''),
            'expires_at' => isset($panjang['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $panjang['expires_in'])
                : CarbonImmutable::now()->addDays(60),
        ];
    }

    /**
     * Perpanjang token yang belum kedaluwarsa. Instagram memakai endpoint
     * tersendiri untuk ini — bukan `fb_exchange_token` seperti jalur Facebook.
     *
     * @return array{token:string, expires_at:CarbonImmutable}
     */
    public function refreshToken(string $token): array
    {
        $data = $this->decode(
            Http::acceptJson()->timeout(20)->get(config('services.meta.instagram_graph_url').'/refresh_access_token', [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $token,
            ])
        );

        return [
            'token' => (string) ($data['access_token'] ?? $token),
            'expires_at' => isset($data['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $data['expires_in'])
                : CarbonImmutable::now()->addDays(60),
        ];
    }

    /**
     * Profil akun yang baru saja memberi izin.
     *
     * @return array<string, mixed>
     */
    public function profile(string $token): array
    {
        return $this->decode(
            Http::acceptJson()->timeout(20)->get(config('services.meta.instagram_graph_url').'/me', [
                'fields' => 'user_id,username,name,profile_picture_url,followers_count,media_count',
                'access_token' => $token,
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (isset($body['error']) && is_array($body['error'])) {
            throw MetaGraphException::fromGraphError($body['error']);
        }

        // Endpoint token Instagram memakai bentuk error yang berbeda dari Graph API.
        if (isset($body['error_message'])) {
            throw new MetaGraphException(
                (string) $body['error_message'],
                isset($body['code']) ? (int) $body['code'] : null,
            );
        }

        if ($response->failed()) {
            throw new MetaGraphException("Instagram membalas status {$response->status()}.");
        }

        return $body;
    }
}
