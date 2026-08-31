<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Menyebar sinkronisasi seluruh akun.
 *
 * Meta membatasi ~200 panggilan per user per jam (§9.5). Dengan 40+ akun yang
 * masing-masing memanggil beberapa endpoint, semua job dijeda 20 detik agar
 * beban tersebar rata alih-alih menumpuk di detik yang sama.
 */
class DispatchAllAccountSyncs implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $trigger = SyncLog::TRIGGER_SCHEDULED,
        public int $spacingSeconds = 20,
    ) {}

    public function handle(): void
    {
        SocialAccount::active()
            ->orderBy('id')
            ->get()
            ->each(function (SocialAccount $account, int $index): void {
                SyncSocialAccountInsights::dispatch($account, $this->trigger)
                    ->delay(now()->addSeconds($index * $this->spacingSeconds));
            });
    }
}
