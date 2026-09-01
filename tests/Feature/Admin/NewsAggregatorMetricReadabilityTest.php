<?php

use App\Livewire\Admin\NewsAggregator;
use App\Jobs\CheckAllMediaSourceMetricsJob;
use App\Models\MediaMetricCheckRun;
use App\Models\MediaSource;
use App\Models\ScrapedArticle;
use App\Models\SearchTopic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('mengecek keterbacaan metrik view dan pengunjung dari berita yang tersimpan', function () {
    $media = MediaSource::query()->create([
        'name' => 'Portal Kutim',
        'base_url' => 'https://setkab.kutaitimurkab.go.id/',
        'is_active' => true,
    ]);

    $topic = SearchTopic::query()->create([
        'keyword' => 'bupati',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    ScrapedArticle::query()->create([
        'media_source_id' => $media->id,
        'search_topic_id' => $topic->id,
        'title' => 'Berita dengan metrik lengkap',
        'article_url' => 'https://setkab.kutaitimurkab.go.id/a',
        'view_count' => 1000,
        'visitor_count' => 750,
    ]);

    ScrapedArticle::query()->create([
        'media_source_id' => $media->id,
        'search_topic_id' => $topic->id,
        'title' => 'Berita tanpa pengunjung',
        'article_url' => 'https://setkab.kutaitimurkab.go.id/b',
        'view_count' => 420,
        'visitor_count' => null,
    ]);

    Livewire::actingAs(superAdminUser())
        ->test(NewsAggregator::class)
        ->call('checkMetricReadability')
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'success'
            && str_contains($params['message'], 'view 2/2')
            && str_contains($params['message'], 'pengunjung 1/2'));
});

it('mengecek ulang status keterbacaan metrik pada media sumber', function () {
    $media = MediaSource::query()->create([
        'name' => 'Kutim Test',
        'base_url' => 'https://setkab.kutaitimurkab.go.id/',
        'is_active' => true,
    ]);

    Http::fake([
        'https://setkab.kutaitimurkab.go.id/' => Http::response('<html><body><h2><a href="/berita-1">Kabar terbaru Kutim</a></h2></body></html>'),
        'https://setkab.kutaitimurkab.go.id/berita-1' => Http::response('<html><body><span>2.100 dilihat</span><span>1.500 pengunjung</span></body></html>'),
    ]);

    Livewire::actingAs(superAdminUser())
        ->test(NewsAggregator::class)
        ->call('checkMediaSourceMetrics', (string) $media->id)
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'success');

    $media->refresh();

    expect($media->can_read_view_count)->toBeTrue()
        ->and($media->can_read_visitor_count)->toBeTrue()
        ->and($media->metrics_checked_at)->not->toBeNull();
});

it('mengecek ulang sekaligus untuk semua media sumber', function () {
    $sourceA = MediaSource::query()->create([
        'name' => 'Sumber A',
        'base_url' => 'https://setkab.kutaitimurkab.go.id/',
        'feed_url' => 'https://setkab.kutaitimurkab.go.id/feed.xml',
        'is_active' => true,
    ]);
    $sourceB = MediaSource::query()->create([
        'name' => 'Sumber B',
        'base_url' => 'https://bapenda.kutaitimurkab.go.id/',
        'feed_url' => 'https://bapenda.kutaitimurkab.go.id/feed.xml',
        'is_active' => true,
    ]);

    Queue::fake();

    Livewire::actingAs(superAdminUser())
        ->test(NewsAggregator::class)
        ->call('checkAllMediaSourceMetrics')
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'success'
            && str_contains($params['message'], 'media')
            && str_contains($params['message'], 'diantrekan'));

    Queue::assertPushed(CheckAllMediaSourceMetricsJob::class, 1);

    $run = MediaMetricCheckRun::query()->latest('id')->firstOrFail();
    expect($run->status)->toBe('queued')
        ->and($run->total_sources)->toBeGreaterThanOrEqual(2);
});

it('memproses antrean cek ulang sekaligus dan menyimpan progres hasil', function () {
    $sourceA = MediaSource::query()->create([
        'name' => 'Sumber A',
        'base_url' => 'https://setkab.kutaitimurkab.go.id/',
        'feed_url' => 'https://setkab.kutaitimurkab.go.id/feed.xml',
        'is_active' => true,
    ]);
    $sourceB = MediaSource::query()->create([
        'name' => 'Sumber B',
        'base_url' => 'https://bapenda.kutaitimurkab.go.id/',
        'feed_url' => 'https://bapenda.kutaitimurkab.go.id/feed.xml',
        'is_active' => true,
    ]);

    Http::fake([
        'https://setkab.kutaitimurkab.go.id/feed.xml' => Http::response('<rss><channel><item><title>A</title><link>https://setkab.kutaitimurkab.go.id/berita</link></item></channel></rss>'),
        'https://setkab.kutaitimurkab.go.id/berita' => Http::response('<html><body><span>3.200 dilihat</span><span>2.000 pengunjung</span></body></html>'),
        'https://setkab.kutaitimurkab.go.id/get-visitor-stats' => Http::response(['status' => 'failed'], 200),
        'https://bapenda.kutaitimurkab.go.id/feed.xml' => Http::response('<rss><channel><item><title>B</title><link>https://bapenda.kutaitimurkab.go.id/berita</link></item></channel></rss>'),
        'https://bapenda.kutaitimurkab.go.id/berita' => Http::response('<html><body><span>Tanpa metrik</span></body></html>'),
        'https://bapenda.kutaitimurkab.go.id/get-visitor-stats' => Http::response(['status' => 'failed'], 200),
    ]);

    $run = MediaMetricCheckRun::query()->create([
        'status' => MediaMetricCheckRun::QUEUED,
        'total_sources' => 2,
    ]);

    (new CheckAllMediaSourceMetricsJob($run->id))->handle(app(\App\Services\NewsScraperService::class));

    $run->refresh();
    expect($run->status)->toBe(MediaMetricCheckRun::COMPLETED)
        ->and($run->processed_sources)->toBe(2)
        ->and($run->view_readable_sources)->toBe(1)
        ->and($run->visitor_readable_sources)->toBe(1);

    expect($sourceA->fresh()->can_read_view_count)->toBeTrue()
        ->and($sourceA->fresh()->can_read_visitor_count)->toBeTrue()
        ->and($sourceB->fresh()->can_read_view_count)->toBeFalse()
        ->and($sourceB->fresh()->can_read_visitor_count)->toBeFalse();
});

it('mengirim permintaan stop untuk cek metrik massal yang sedang berjalan', function () {
    $run = MediaMetricCheckRun::query()->create([
        'status' => MediaMetricCheckRun::RUNNING,
        'total_sources' => 10,
    ]);

    Livewire::actingAs(superAdminUser())
        ->test(NewsAggregator::class)
        ->call('requestStopMediaMetricCheck')
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'success'
            && str_contains($params['message'], 'Permintaan stop pemeriksaan metrik'));

    expect($run->fresh()->stop_requested)->toBeTrue();
});

it('menghentikan job cek metrik massal saat stop diminta', function () {
    MediaSource::query()->create([
        'name' => 'Sumber A',
        'base_url' => 'https://sumber-a.kutaitimurkab.go.id/',
        'feed_url' => 'https://sumber-a.kutaitimurkab.go.id/feed.xml',
        'is_active' => true,
    ]);

    $run = MediaMetricCheckRun::query()->create([
        'status' => MediaMetricCheckRun::QUEUED,
        'total_sources' => 1,
        'stop_requested' => true,
    ]);

    (new CheckAllMediaSourceMetricsJob($run->id))->handle(app(\App\Services\NewsScraperService::class));

    $run->refresh();
    expect($run->status)->toBe(MediaMetricCheckRun::STOPPED)
        ->and($run->processed_sources)->toBe(0)
        ->and($run->message)->toBe('Pemeriksaan metrik dihentikan oleh pengguna.');
});

it('menyimpan media partner baru dan tetap menampilkannya di daftar media partner', function () {
    Livewire::actingAs(superAdminUser())
        ->test(NewsAggregator::class)
        ->set('aggregatorMode', 'media-partner')
        ->set('formType', 'media')
        ->set('name', 'Portal Kutim Baru')
        ->set('baseUrl', 'https://portal-kutim-baru.com/')
        ->set('feedUrl', '')
        ->call('save')
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'success')
        ->set('tab', 'media')
        ->assertSee('Portal Kutim Baru');

    expect(
        MediaSource::query()
            ->where('base_url', 'https://portal-kutim-baru.com/')
            ->exists()
    )->toBeTrue();
});

it('menandai pengunjung terbaca dari endpoint statistik website dinamis', function () {
    $media = MediaSource::query()->create([
        'name' => 'Dishub Dynamic',
        'base_url' => 'https://dishub-dynamic.test/',
        'is_active' => true,
    ]);

    Http::fake([
        'https://dishub-dynamic.test/' => Http::response('<html><body><div id="app"></div></body></html>'),
        'https://dishub-dynamic.test/get-list-article*' => Http::response(['status' => 'success', 'data' => []]),
        'https://dishub-dynamic.test/get-visitor-stats' => Http::response([
            'status' => 'success',
            'data' => [
                'total' => 4372,
            ],
        ]),
    ]);

    Livewire::actingAs(superAdminUser())
        ->test(NewsAggregator::class)
        ->set('aggregatorMode', 'media-partner')
        ->call('checkMediaSourceMetrics', (string) $media->id)
        ->assertDispatched('toast', fn ($name, $params) => $params['type'] === 'success'
            && str_contains($params['message'], 'pengunjung terbaca'));

    $media->refresh();
    expect($media->can_read_view_count)->toBeFalse()
        ->and($media->can_read_visitor_count)->toBeTrue();
});
