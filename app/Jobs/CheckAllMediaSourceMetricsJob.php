<?php

namespace App\Jobs;

use App\Models\MediaMetricCheckRun;
use App\Models\MediaSource;
use App\Services\NewsScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckAllMediaSourceMetricsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public int $runId,
        public ?string $domainFilter = null,
        public bool $excludeDomainFilter = false,
        public ?array $sourceIds = null
    ) {}

    public function handle(NewsScraperService $scraper): void
    {
        $run = MediaMetricCheckRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        if ($run->stop_requested) {
            $run->update([
                'status' => MediaMetricCheckRun::STOPPED,
                'message' => 'Pemeriksaan metrik dihentikan oleh pengguna.',
            ]);

            return;
        }

        $run->update(['status' => MediaMetricCheckRun::RUNNING]);

        try {
            $sources = MediaSource::query()
                ->when(
                    $this->domainFilter && ! $this->excludeDomainFilter,
                    fn ($q) => $q->where('base_url', 'like', '%'.$this->domainFilter.'%')
                )
                ->when(
                    $this->domainFilter && $this->excludeDomainFilter,
                    fn ($q) => $q->where('base_url', 'not like', '%'.$this->domainFilter.'%')
                )
                ->when(
                    is_array($this->sourceIds),
                    fn ($q) => $q->whereIn('id', $this->sourceIds)
                )
                ->orderBy('name')
                ->get();
            $total = $sources->count();
            $processed = 0;
            $viewReadable = 0;
            $visitorReadable = 0;

            $run->update(['total_sources' => $total]);

            foreach ($sources as $source) {
                if ((bool) $run->fresh()?->stop_requested) {
                    $run->update([
                        'status' => MediaMetricCheckRun::STOPPED,
                        'message' => 'Pemeriksaan metrik dihentikan oleh pengguna.',
                    ]);

                    return;
                }

                $result = $scraper->refreshMediaMetricStatus($source);
                $processed++;
                $viewReadable += $result['can_read_view_count'] ? 1 : 0;
                $visitorReadable += $result['can_read_visitor_count'] ? 1 : 0;

                $run->update([
                    'processed_sources' => $processed,
                    'view_readable_sources' => $viewReadable,
                    'visitor_readable_sources' => $visitorReadable,
                ]);
            }

            $run->update([
                'status' => MediaMetricCheckRun::COMPLETED,
                'message' => "Cek ulang selesai untuk {$total} media.",
            ]);
        } catch (\Throwable $exception) {
            Log::error('Cek metrik seluruh media gagal', ['run_id' => $run->id, 'error' => $exception->getMessage()]);
            $run->update([
                'status' => MediaMetricCheckRun::FAILED,
                'message' => 'Cek metrik gagal: '.$exception->getMessage(),
            ]);
        }
    }
}
