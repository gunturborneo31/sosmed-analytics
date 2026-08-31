<?php

namespace App\Livewire\Operator;

use App\Jobs\SyncSocialAccountInsights;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\Meta\InstagramLoginService;
use App\Services\Meta\MetaOAuthService;
use App\Support\SyncProgress;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman utama operator OPD: tombol hubungkan, indikator status, ringkasan singkat (§1).
 */
#[Layout('components.layouts.app')]
#[Title('Akun Media Sosial')]
class ConnectionStatus extends Component
{
    /** Modal pemilih platform sebelum diarahkan ke halaman izin Meta. */
    public bool $showConnect = false;

    /** @return Collection<int, SocialAccount> */
    #[Computed]
    public function accounts(): Collection
    {
        return auth()->user()
            ->visibleSocialAccounts()
            ->with('organizationalUnit')
            ->withCount('insightSnapshots')
            ->orderBy('platform')
            ->get();
    }

    #[Computed]
    public function metaConfigured(): bool
    {
        return app(MetaOAuthService::class)->isConfigured();
    }

    /** Jalur Instagram Login punya kredensial sendiri, terpisah dari Facebook. */
    #[Computed]
    public function instagramLoginReady(): bool
    {
        return app(InstagramLoginService::class)->isConfigured();
    }

    /** @return Collection<int, SyncLog> */
    #[Computed]
    public function recentLogs(): Collection
    {
        return SyncLog::whereIn('social_account_id', $this->accounts()->pluck('id'))
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Kemajuan sinkronisasi tiap akun, dibaca ulang tiap kali komponen digambar.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function progress(): array
    {
        return $this->accounts()
            ->mapWithKeys(fn (SocialAccount $akun): array => [$akun->id => SyncProgress::ambil($akun->id)])
            ->filter()
            ->all();
    }

    /**
     * Perlu terus memantau selama masih ada yang berjalan. Begitu semuanya
     * rampung polling berhenti sendiri — halaman yang menganggur tidak perlu
     * memanggil server tiap dua detik.
     */
    #[Computed]
    public function sedangBerjalan(): bool
    {
        return collect($this->progress())
            ->contains(fn (array $p): bool => $p['status'] !== SyncProgress::STATUS_SELESAI);
    }

    public function syncNow(string $accountId): void
    {
        $account = auth()->user()->visibleSocialAccounts()->findOrFail($accountId);

        // Ditandai sebelum dikirim ke antrean supaya bilah kemajuannya langsung
        // muncul, tidak menunggu worker sempat mengambilnya.
        SyncProgress::antre($account->id);

        SyncSocialAccountInsights::dispatch($account, SyncLog::TRIGGER_MANUAL);

        unset($this->progress, $this->sedangBerjalan);
    }

    /** Menutup kartu hasil setelah dibaca. */
    public function dismissProgress(string $accountId): void
    {
        SyncProgress::bersihkan($accountId);

        unset($this->progress, $this->sedangBerjalan, $this->accounts, $this->recentLogs);
    }

    public function render()
    {
        return view('livewire.operator.connection-status');
    }
}
