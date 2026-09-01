<?php

use App\Models\MediaSource;
use App\Models\ScrapedArticle;
use App\Models\SearchTopic;
use App\Services\NewsScraperService;
use Illuminate\Support\Facades\Http;

it('menyimpan jumlah view dan pengunjung dari halaman artikel saat scraping berita', function () {
    $source = MediaSource::query()->create([
        'name' => 'Kutim Daily',
        'base_url' => 'https://media.test',
        'feed_url' => 'https://media.test/feed.xml',
        'is_active' => true,
    ]);

    SearchTopic::query()->create([
        'keyword' => 'bupati',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    Http::fake([
        'https://media.test/feed.xml' => Http::response(<<<XML
            <rss><channel>
                <item>
                    <title>Bupati Kutim meresmikan layanan baru</title>
                    <link>https://media.test/berita-1</link>
                    <description>Ringkasan singkat</description>
                </item>
            </channel></rss>
            XML),
        'https://media.test/berita-1' => Http::response(<<<HTML
            <html><body>
            <script type="application/ld+json">
            {"@type":"NewsArticle","interactionStatistic":{"@type":"InteractionCounter","interactionType":{"@type":"WatchAction"},"userInteractionCount":"12.345"}}
            </script>
            <span>8.765 pengunjung</span>
            </body></html>
            HTML),
    ]);

    $saved = app(NewsScraperService::class)->scrape();

    expect($saved)->toBe(1);

    $article = ScrapedArticle::query()->firstOrFail();

    expect($article->media_source_id)->toBe($source->id)
        ->and($article->view_count)->toBe(12345)
        ->and($article->visitor_count)->toBe(8765);
});

it('memperbarui jumlah view dan pengunjung untuk artikel yang sudah pernah tersimpan', function () {
    $source = MediaSource::query()->create([
        'name' => 'Suara Kutim',
        'base_url' => 'https://suara.test',
        'feed_url' => 'https://suara.test/feed.xml',
        'is_active' => true,
    ]);

    $topic = SearchTopic::query()->create([
        'keyword' => 'kesehatan',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    $existing = ScrapedArticle::query()->create([
        'media_source_id' => $source->id,
        'search_topic_id' => $topic->id,
        'title' => 'Kesehatan warga meningkat',
        'article_url' => 'https://suara.test/berita-utama',
        'summary' => null,
        'view_count' => null,
        'visitor_count' => null,
    ]);
    $existing->searchTopics()->syncWithoutDetaching([$topic->id]);

    Http::fake([
        'https://suara.test/feed.xml' => Http::response(<<<XML
            <rss><channel>
                <item>
                    <title>Kesehatan warga meningkat pesat</title>
                    <link>https://suara.test/berita-utama</link>
                    <description>Data terbaru</description>
                </item>
            </channel></rss>
            XML),
        'https://suara.test/berita-utama' => Http::response('<html><body><span>1,2k dilihat</span><span>980 pengunjung</span></body></html>'),
    ]);

    $saved = app(NewsScraperService::class)->scrape();

    expect($saved)->toBe(0)
        ->and(ScrapedArticle::query()->count())->toBe(1)
        ->and($existing->fresh()->view_count)->toBe(1200)
        ->and($existing->fresh()->visitor_count)->toBe(980);
});

it('mengambil view artikel dari endpoint dinamis get-list-article', function () {
    $source = MediaSource::query()->create([
        'name' => 'Dishub Dynamic',
        'base_url' => 'https://dishub-dynamic.test/',
        'feed_url' => null,
        'is_active' => true,
    ]);

    SearchTopic::query()->create([
        'keyword' => 'kutim',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    Http::fake([
        'https://dishub-dynamic.test/' => Http::response('<html><body><div id="app"></div></body></html>'),
        'https://dishub-dynamic.test/get-list-article*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'title' => 'Info Kutim terbaru',
                    'slug' => 'info-kutim-terbaru',
                    'content' => '<p>Ringkas berita</p>',
                    'view_count' => 3210,
                    'date' => '2026-08-31',
                ],
            ],
        ]),
        'https://dishub-dynamic.test/detail-article/info-kutim-terbaru' => Http::response('<html><body>Tanpa metrik tambahan</body></html>'),
    ]);

    $saved = app(NewsScraperService::class)->scrape();

    expect($saved)->toBe(1);

    $article = ScrapedArticle::query()->firstOrFail();
    expect($article->media_source_id)->toBe($source->id)
        ->and($article->view_count)->toBe(3210)
        ->and($article->visitor_count)->toBeNull();
});
