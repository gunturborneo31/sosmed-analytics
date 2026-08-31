<?php

namespace App\Livewire\Admin;

use App\Jobs\SyncSocialAccountInsights;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Log Sinkronisasi')]
class SyncLogTable extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'akun')]
    public string $accountId = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, SyncLog> */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return SyncLog::query()
            ->with('socialAccount.organizationalUnit')
            ->when($this->status === 'failed', fn ($q) => $q->failed())
            ->when($this->status && $this->status !== 'failed', fn ($q) => $q->where('status', $this->status))
            ->when($this->accountId, fn ($q, $id) => $q->where('social_account_id', $id))
            ->latest('created_at')
            ->paginate(25);
    }

    /** @return array<string, int> */
    #[Computed]
    public function tally(): array
    {
        return SyncLog::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /**
     * Sinkronisasi manual — hanya untuk peran yang punya izin trigger-manual-sync (§6).
     */
    public function retry(string $accountId): void
    {
        abort_unless(auth()->user()->can('trigger-manual-sync'), 403);

        $account = SocialAccount::findOrFail($accountId);

        SyncSocialAccountInsights::dispatch($account, SyncLog::TRIGGER_MANUAL);

        $this->dispatch('toast', type: 'success', message: "Sinkronisasi manual untuk {$account->display_name} sudah diantrekan.");
    }

    public function render()
    {
        return view('livewire.admin.sync-log-table', [
            'statuses' => [
                '' => 'Semua status',
                'success' => 'Berhasil',
                'failed' => 'Gagal & sebagian',
                'partial' => 'Sebagian',
            ],
        ]);
    }
}
