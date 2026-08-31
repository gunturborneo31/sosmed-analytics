<?php

namespace App\Console\Commands;

use App\Services\NewsScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeNewsCommand extends Command
{
    protected $signature = 'news:scrape';

    protected $description = 'Mengambil berita dari media aktif sesuai topik pencarian';

    public function handle(NewsScraperService $scraper): int
    {
        $saved = $scraper->scrape();
        Log::info('News scraping selesai', ['new_articles' => $saved]);
        $this->info("{$saved} artikel baru disimpan.");

        return self::SUCCESS;
    }
}