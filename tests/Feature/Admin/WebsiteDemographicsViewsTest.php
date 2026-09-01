<?php

use App\Models\MediaSource;
use App\Models\ScrapedArticle;
use App\Models\SearchTopic;
use App\Services\Analytics\AccountScope;
use App\Services\Analytics\AudienceAnalytics;
use App\Support\Period;

it('menggunakan view_count dari website opd sebagai data demografi website', function () {
    $source = MediaSource::query()->create([
        'name' => 'Diskominfo Kutim',
        'base_url' => 'https://diskominfo.kutaitimurkab.go.id/',
        'feed_url' => null,
        'is_active' => true,
    ]);

    $topic = SearchTopic::query()->create([
        'keyword' => 'kutim',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    ScrapedArticle::query()->create([
        'media_source_id' => $source->id,
        'search_topic_id' => $topic->id,
        'title' => 'Berita pertama',
        'article_url' => 'https://diskominfo.kutaitimurkab.go.id/berita-1',
        'summary' => 'Ringkasan',
        'published_at' => now()->subDay(),
        'view_count' => 1200,
        'visitor_count' => null,
    ]);

    $scope = AccountScope::make()->platform('website-opd');
    $analytics = AudienceAnalytics::make(Period::default(), $scope);

    expect($analytics->latestDate())->not->toBeNull()
        ->and($analytics->byAge()->sum())->toBe(1200)
        ->and($analytics->byGender()->sum())->toBe(0);
});

it('menggunakan view_count dari website media partner sebagai data demografi website', function () {
    $source = MediaSource::query()->create([
        'name' => 'Kaltim Today',
        'base_url' => 'https://kaltimtoday.com/',
        'feed_url' => null,
        'is_active' => true,
    ]);

    $topic = SearchTopic::query()->create([
        'keyword' => 'kutim',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    ScrapedArticle::query()->create([
        'media_source_id' => $source->id,
        'search_topic_id' => $topic->id,
        'title' => 'Berita partner',
        'article_url' => 'https://kaltimtoday.com/berita-partner',
        'summary' => 'Ringkasan',
        'published_at' => now()->subDay(),
        'view_count' => 950,
        'visitor_count' => null,
    ]);

    $scope = AccountScope::make()->platform('website-media-partner');
    $analytics = AudienceAnalytics::make(Period::default(), $scope);

    expect($analytics->latestDate())->not->toBeNull()
        ->and($analytics->byAge()->sum())->toBe(950)
        ->and($analytics->byGender()->sum())->toBe(0);
});

it('menggunakan created_at sebagai fallback tanggal ketika published_at belum tersedia pada website media partner', function () {
    $source = MediaSource::query()->create([
        'name' => 'Media Partner Tanpa Tanggal',
        'base_url' => 'https://example-partner.com/',
        'feed_url' => null,
        'is_active' => true,
    ]);

    $topic = SearchTopic::query()->create([
        'keyword' => 'kutim',
        'time_filter_type' => 'all',
        'is_active' => true,
    ]);

    ScrapedArticle::query()->create([
        'media_source_id' => $source->id,
        'search_topic_id' => $topic->id,
        'title' => 'Berita tanpa published_at',
        'article_url' => 'https://example-partner.com/berita-tanpa-tanggal',
        'summary' => 'Ringkasan',
        'published_at' => null,
        'view_count' => 210,
        'visitor_count' => null,
        'created_at' => now()->subHours(3),
        'updated_at' => now()->subHours(3),
    ]);

    $scope = AccountScope::make()->platform('website-media-partner');
    $analytics = AudienceAnalytics::make(Period::default(), $scope);

    expect($analytics->latestDate())->not->toBeNull()
        ->and($analytics->byAge()->sum())->toBe(210)
        ->and($analytics->byGender()->sum())->toBe(0);
});
