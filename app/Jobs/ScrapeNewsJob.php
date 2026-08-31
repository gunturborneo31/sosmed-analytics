<?php

namespace App\Jobs;

use App\Models\ScrapingRun;
use App\Services\NewsScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeNewsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $runId) {}

    public function handle(NewsScraperService $scraper): void
    {
        $run = ScrapingRun::find($this->runId);
        if (! $run) {
            return;
        }

        $run->update(['status' => ScrapingRun::RUNNING]);

        try {
            $saved = $scraper->scrape(
                function (int $processed, int $newArticles, int $total) use ($run): void {
                    $run->update(['processed_steps' => $processed, 'new_articles' => $newArticles, 'total_steps' => $total]);
                },
                fn (): bool => (bool) $run->fresh()?->stop_requested,
            );

            $run->update([
                'status' => $run->fresh()->stop_requested ? ScrapingRun::STOPPED : ScrapingRun::COMPLETED,
                'processed_steps' => $run->total_steps,
                'new_articles' => $saved,
                'message' => $run->fresh()->stop_requested ? 'Scrape dihentikan oleh pengguna.' : 'Scrape selesai.',
            ]);
        } catch (\Throwable $exception) {
            Log::error('News scraping job gagal', ['run_id' => $run->id, 'error' => $exception->getMessage()]);
            $run->update(['status' => ScrapingRun::FAILED, 'message' => 'Scrape gagal: '.$exception->getMessage()]);
        }
    }
}