<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Services\Meta\InstagramLoginService;
use App\Services\Meta\MetaGraphException;
use App\Services\Meta\MetaOAuthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Memperpanjang token yang akan kedaluwarsa dalam 7 hari (§9.5).
 *
 * Kalau Meta menolak, akun ditandai `expired` supaya muncul di kartu
 * "Perlu Perhatian" dan operatornya bisa didampingi menghubungkan ulang.
 */
class RefreshExpiringTokens implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $withinDays = 7) {}

    public function handle(MetaOAuthService $oauth, InstagramLoginService $instagram): void
    {
        if (! $oauth->isConfigured() && ! $instagram->isConfigured()) {
            Log::warning('Perpanjangan token dilewati: kredensial Meta belum diisi.');

            return;
        }

        SocialAccount::active()
            ->expiringWithin($this->withinDays)
            ->each(function (SocialAccount $account) use ($oauth, $instagram): void {
                try {
                    /*
                     | Tiap jalur punya cara perpanjangan sendiri: Facebook
                     | memakai fb_exchange_token, Instagram Login memakai
                     | ig_refresh_token di server yang berbeda. Memakai yang
                     | keliru membuat tokennya ditolak.
                    */
                    $fresh = $account->auth_source === SocialAccount::AUTH_INSTAGRAM
                        ? $instagram->refreshToken($account->access_token)
                        : $oauth->exchangeForLongLived($account->access_token);

                    $account->update([
                        'access_token' => $fresh['token'],
                        'token_expires_at' => $fresh['expires_at'],
                    ]);
                } catch (MetaGraphException $e) {
                    $account->update(['status' => SocialAccount::STATUS_EXPIRED]);

                    Log::warning('Token gagal diperpanjang', [
                        'akun' => $account->id,
                        'pesan' => $e->getMessage(),
                    ]);
                }
            });
    }
}
