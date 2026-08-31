<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pembungkus HTTP untuk Graph API: menyatukan versi, retry, dan penerjemahan error.
 *
 * Meta membatasi sekitar 200 panggilan per user per jam (§9.5) — penyebaran beban
 * ditangani di sisi Job lewat delay, kelas ini hanya memastikan satu panggilan
 * gagal dengan pesan yang bisa dibaca manusia.
 */
class MetaGraphClient
{
    public function __construct(
        private readonly string $graphUrl,
        private readonly string $version,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('services.meta.graph_url'), '/'),
            (string) config('services.meta.graph_version'),
        );
    }

    /**
     * Klien untuk akun yang dihubungkan lewat Instagram Business Login.
     *
     * Akun itu memegang token pengguna Instagram, yang hanya berlaku di
     * graph.instagram.com — dipakai di graph.facebook.com token itu ditolak.
     */
    public static function makeForInstagramLogin(): self
    {
        return new self(
            rtrim((string) config('services.meta.instagram_graph_url'), '/'),
            (string) config('services.meta.graph_version'),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = [], ?string $accessToken = null): array
    {
        if ($accessToken !== null) {
            $query['access_token'] = $accessToken;
        }

        $response = $this->request()->get($this->url($endpoint), $query);

        return $this->decode($response, $endpoint);
    }

    /**
     * Versi yang tidak melempar exception — untuk metrik opsional yang wajar saja
     * kalau tidak tersedia (Meta rutin men-deprecate metrik).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function tryGet(string $endpoint, array $query = [], ?string $accessToken = null): ?array
    {
        try {
            return $this->get($endpoint, $query, $accessToken);
        } catch (MetaGraphException $e) {
            if ($e->isAuthError() || $e->isRateLimited()) {
                throw $e;
            }

            Log::info('Metrik Meta dilewati', ['endpoint' => $endpoint, 'pesan' => $e->getMessage()]);

            return null;
        }
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(8)
            // Jangan ulangi error auth/permission — hanya kegagalan sesaat.
            ->retry(3, 400, fn ($exception, $request) => ! $exception instanceof RequestException
                || in_array($exception->response->status(), [429, 500, 502, 503, 504], true), throw: false);
    }

    private function url(string $endpoint): string
    {
        return $this->graphUrl.'/'.$this->version.'/'.ltrim($endpoint, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $endpoint): array
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (isset($body['error']) && is_array($body['error'])) {
            throw MetaGraphException::fromGraphError($body['error']);
        }

        if ($response->failed()) {
            throw new MetaGraphException(
                "Graph API membalas status {$response->status()} untuk {$endpoint}.",
            );
        }

        return $body;
    }
}
