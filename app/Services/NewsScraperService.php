<?php

namespace App\Services;

use App\Models\MediaSource;
use App\Models\ScrapedArticle;
use App\Models\SearchTopic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class NewsScraperService
{
    private const SOURCE_FETCH_TIMEOUT = 15;
    private const SOURCE_FETCH_RETRIES = 2;
    private const ARTICLE_METRIC_TIMEOUT = 15;
    private const ARTICLE_METRIC_RETRIES = 1;
    private const QUICK_CHECK_TIMEOUT = 6;
    private const QUICK_CHECK_RETRIES = 0;
    private const QUICK_CHECK_MAX_ARTICLES = 2;

    public function scrape(
        ?callable $onProgress = null,
        ?callable $shouldStop = null,
        ?string $domainFilter = null,
        bool $excludeDomainFilter = false,
        ?array $sourceIds = null
    ): int
    {
        $saved = 0;
        $processed = 0;
        $itemCache = [];
        $metricsCache = [];
        $sourcesQuery = MediaSource::query()
            ->where('is_active', true)
            ->when(
                $domainFilter && ! $excludeDomainFilter,
                fn ($q) => $q->where('base_url', 'like', '%'.$domainFilter.'%')
            )
            ->when(
                $domainFilter && $excludeDomainFilter,
                fn ($q) => $q->where('base_url', 'not like', '%'.$domainFilter.'%')
            )
            ->when(
                is_array($sourceIds),
                fn ($q) => $q->whereIn('id', $sourceIds)
            );
        $total = (clone $sourcesQuery)->count()
            * SearchTopic::query()->where('is_active', true)->count();

        $sourcesQuery->each(function (MediaSource $source) use (&$saved, &$processed, &$itemCache, &$metricsCache, $total, $onProgress, $shouldStop) {
            if ($shouldStop && $shouldStop()) {
                return false;
            }

            SearchTopic::query()->where('is_active', true)->each(function (SearchTopic $topic) use ($source, &$saved, &$processed, &$itemCache, &$metricsCache, $total, $onProgress, $shouldStop) {
                if ($shouldStop && $shouldStop()) {
                    return false;
                }

                if (! array_key_exists($source->id, $itemCache)) {
                    try {
                        $itemCache[$source->id] = $source->feed_url
                            ? $this->fromFeed($source->feed_url)
                            : $this->fromHtml($source->base_url);
                    } catch (\Throwable $exception) {
                        Log::warning('News scraping gagal', ['source' => $source->name, 'error' => $exception->getMessage()]);
                        $itemCache[$source->id] = [];
                    }
                }

                foreach ($itemCache[$source->id] as $item) {
                    if (! $item['url'] || ! $this->matchesTopic($item, $topic) || ! $this->matchesDate($item['published_at'], $topic)) {
                        continue;
                    }

                    $itemViewCount = $item['view_count'] ?? null;
                    $itemVisitorCount = $item['visitor_count'] ?? null;

                    if (($itemViewCount === null || $itemVisitorCount === null) && ! array_key_exists($item['url'], $metricsCache)) {
                        $metricsCache[$item['url']] = $this->fetchArticleMetrics($item['url']);
                    }

                    $metrics = $metricsCache[$item['url']] ?? ['view_count' => null, 'visitor_count' => null];
                    $viewCount = $metrics['view_count'] ?? $itemViewCount;
                    $visitorCount = $metrics['visitor_count'] ?? $itemVisitorCount;
                    $article = ScrapedArticle::where('article_url', $item['url'])->first();

                    if ($article) {
                        $article->searchTopics()->syncWithoutDetaching([$topic->id]);
                        $updates = [];
                        if ($viewCount !== null && $article->view_count !== $viewCount) {
                            $updates['view_count'] = $viewCount;
                        }
                        if ($visitorCount !== null && $article->visitor_count !== $visitorCount) {
                            $updates['visitor_count'] = $visitorCount;
                        }
                        if ($updates !== []) {
                            $article->update($updates);
                        }
                        continue;
                    }

                    $article = ScrapedArticle::create([
                        'media_source_id' => $source->id,
                        'search_topic_id' => $topic->id,
                        'title' => $item['title'] ?: 'Tanpa judul',
                        'article_url' => $item['url'],
                        'summary' => $item['summary'],
                        'view_count' => $viewCount,
                        'visitor_count' => $visitorCount,
                        'published_at' => $item['published_at'],
                    ]);
                    $article->searchTopics()->syncWithoutDetaching([$topic->id]);
                    $saved++;
                }

                $processed++;
                if ($onProgress) {
                    $onProgress($processed, $saved, $total);
                }
            });
        });

        return $saved;
    }

    /**
     * @return array{can_read_view_count: bool, can_read_visitor_count: bool, checked_articles: int, message: string}
     */
    public function checkMediaMetricReadability(MediaSource $source): array
    {
        try {
            $items = $source->feed_url
                ? $this->fromFeed($source->feed_url, self::QUICK_CHECK_TIMEOUT, self::QUICK_CHECK_RETRIES, self::QUICK_CHECK_MAX_ARTICLES)
                : $this->fromHtml($source->base_url, self::QUICK_CHECK_TIMEOUT, self::QUICK_CHECK_RETRIES, self::QUICK_CHECK_MAX_ARTICLES);
        } catch (\Throwable $exception) {
            Log::warning('Cek metrik media gagal', ['source' => $source->name, 'error' => $exception->getMessage()]);

            return [
                'can_read_view_count' => false,
                'can_read_visitor_count' => false,
                'checked_articles' => 0,
                'message' => 'Gagal mengambil daftar artikel: '.$exception->getMessage(),
            ];
        }

        $urls = collect($items)
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->unique()
            ->values();

        $canReadView = collect($items)->contains(fn (array $item) => ($item['view_count'] ?? null) !== null);
        $canReadVisitor = collect($items)->contains(fn (array $item) => ($item['visitor_count'] ?? null) !== null);
        $checked = 0;

        if ($urls->isEmpty()) {
            $siteVisitorCount = $this->fetchSiteVisitorCount($source->base_url, self::QUICK_CHECK_TIMEOUT, self::QUICK_CHECK_RETRIES);

            return [
                'can_read_view_count' => $canReadView,
                'can_read_visitor_count' => $canReadVisitor || $siteVisitorCount !== null,
                'checked_articles' => 0,
                'message' => $siteVisitorCount !== null
                    ? 'Tidak ditemukan URL artikel, tetapi statistik pengunjung website berhasil dibaca.'
                    : 'Tidak ditemukan URL artikel untuk diuji.',
            ];
        }

        foreach ($urls as $url) {
            $checked++;
            $metrics = $this->fetchArticleMetrics($url, self::QUICK_CHECK_TIMEOUT, self::QUICK_CHECK_RETRIES);
            $canReadView = $canReadView || $metrics['view_count'] !== null;
            $canReadVisitor = $canReadVisitor || $metrics['visitor_count'] !== null;

            if ($canReadView && $canReadVisitor) {
                break;
            }
        }

        if (! $canReadVisitor && $this->fetchSiteVisitorCount($source->base_url, self::QUICK_CHECK_TIMEOUT, self::QUICK_CHECK_RETRIES) !== null) {
            $canReadVisitor = true;
        }

        return [
            'can_read_view_count' => $canReadView,
            'can_read_visitor_count' => $canReadVisitor,
            'checked_articles' => $checked,
            'message' => "Diperiksa {$checked} artikel contoh (mode cepat).",
        ];
    }

    /**
     * @return array{can_read_view_count: bool, can_read_visitor_count: bool, checked_articles: int, message: string}
     */
    public function refreshMediaMetricStatus(MediaSource $source): array
    {
        $result = $this->checkMediaMetricReadability($source);

        $source->update([
            'can_read_view_count' => $result['can_read_view_count'],
            'can_read_visitor_count' => $result['can_read_visitor_count'],
            'metrics_checked_at' => now(),
            'metrics_check_message' => $result['message'],
        ]);

        return $result;
    }

    /** @return list<array{title: string, url: ?string, summary: ?string, published_at: ?CarbonImmutable, view_count: ?int, visitor_count: ?int}> */
    private function fromFeed(
        string $url,
        int $timeout = self::SOURCE_FETCH_TIMEOUT,
        int $retries = self::SOURCE_FETCH_RETRIES,
        ?int $limit = null
    ): array
    {
        $xml = $this->httpRequest($timeout, $retries)->get($url)->throw()->body();
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (! $feed) {
            return [];
        }

        $items = [];
        foreach (($feed->channel->item ?? $feed->entry ?? []) as $entry) {
            $link = (string) ($entry->link['href'] ?? $entry->link ?? '');
            $published = $entry->pubDate ?? $entry->published ?? $entry->updated ?? null;
            $items[] = [
                'title' => trim((string) ($entry->title ?? '')),
                'url' => $link ?: null,
                'summary' => trim((string) ($entry->description ?? $entry->summary ?? '')) ?: null,
                'published_at' => $this->parseDate($published ? (string) $published : null),
                'view_count' => null,
                'visitor_count' => null,
            ];

            if ($limit !== null && count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /** @return list<array{title: string, url: ?string, summary: ?string, published_at: ?CarbonImmutable, view_count: ?int, visitor_count: ?int}> */
    private function fromHtml(
        string $url,
        int $timeout = self::SOURCE_FETCH_TIMEOUT,
        int $retries = self::SOURCE_FETCH_RETRIES,
        ?int $limit = null
    ): array
    {
        $html = $this->httpRequest($timeout, $retries)->get($url)->throw()->body();
        $crawler = new Crawler($html, $url);
        $items = [];

        $crawler->filter('article, .post, .berita, h1, h2, h3')->each(function (Crawler $node) use (&$items, $url, $limit): void {
            if ($limit !== null && count($items) >= $limit) {
                return;
            }

            $anchor = $node->filter('a')->first();
            $title = trim($anchor->count() ? $anchor->text() : $node->text());
            $href = $anchor->count() ? $anchor->attr('href') : null;
            if (! $title || ! $href) {
                return;
            }

            $items[] = [
                'title' => $title,
                'url' => $this->absoluteUrl($href, $url),
                'summary' => null,
                'published_at' => $this->parseDate($node->filter('time')->count() ? $node->filter('time')->first()->attr('datetime') : null),
                'view_count' => null,
                'visitor_count' => null,
            ];
        });

        if ($items === []) {
            return $this->fromArticleListApi($url, $timeout, $retries, $limit);
        }

        return $items;
    }

    /** @return list<array{title: string, url: ?string, summary: ?string, published_at: ?CarbonImmutable, view_count: ?int, visitor_count: ?int}> */
    private function fromArticleListApi(
        string $baseUrl,
        int $timeout = self::SOURCE_FETCH_TIMEOUT,
        int $retries = self::SOURCE_FETCH_RETRIES,
        ?int $limit = null
    ): array
    {
        $endpoint = rtrim($baseUrl, '/').'/get-list-article';
        $response = $this->httpRequest($timeout, $retries)->get($endpoint, $limit !== null ? ['limit' => $limit] : []);
        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success' || ! is_array($payload['data'] ?? null)) {
            return [];
        }

        $items = [];
        foreach ($payload['data'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $title = trim((string) ($entry['title'] ?? ''));
            $slug = trim((string) ($entry['slug'] ?? ''));
            $apiUrl = trim((string) ($entry['url'] ?? ''));
            $articleUrl = $apiUrl !== '' ? $apiUrl : ($slug !== '' ? rtrim($baseUrl, '/').'/detail-article/'.$slug : null);

            if ($articleUrl === null) {
                continue;
            }

            $summary = trim(strip_tags((string) ($entry['content'] ?? '')));
            $items[] = [
                'title' => $title !== '' ? $title : 'Tanpa judul',
                'url' => $articleUrl,
                'summary' => $summary !== '' ? $summary : null,
                'published_at' => $this->parseDate(is_string($entry['date'] ?? null) ? $entry['date'] : null),
                'view_count' => $this->normalizeMetricCount(isset($entry['view_count']) ? (string) $entry['view_count'] : null),
                'visitor_count' => null,
            ];

            if ($limit !== null && count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function matchesTopic(array $item, SearchTopic $topic): bool
    {
        return str_contains(mb_strtolower($item['title'].' '.($item['summary'] ?? '')), mb_strtolower($topic->keyword));
    }

    private function matchesDate(?CarbonImmutable $publishedAt, SearchTopic $topic): bool
    {
        return true;
    }

    private function parseDate(?string $date): ?CarbonImmutable
    {
        if (! $date) {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function absoluteUrl(string $href, string $base): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim($base, '/').'/'.ltrim($href, '/');
    }

    /**
     * @return array{view_count: ?int, visitor_count: ?int}
     */
    private function fetchArticleMetrics(
        string $url,
        int $timeout = self::ARTICLE_METRIC_TIMEOUT,
        int $retries = self::ARTICLE_METRIC_RETRIES
    ): array
    {
        try {
            $html = $this->httpRequest($timeout, $retries)->get($url)->throw()->body();
        } catch (\Throwable $exception) {
            Log::notice('Metrik artikel tidak dapat diambil', ['url' => $url, 'error' => $exception->getMessage()]);

            return ['view_count' => null, 'visitor_count' => null];
        }

        return [
            'view_count' => $this->extractViewCount($html),
            'visitor_count' => $this->extractVisitorCount($html),
        ];
    }

    private function httpRequest(int $timeout, int $retries): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout($timeout);

        if ($retries > 0) {
            $request = $request->retry($retries, 250);
        }

        return $request;
    }

    private function fetchSiteVisitorCount(string $baseUrl, int $timeout, int $retries): ?int
    {
        try {
            $response = $this->httpRequest($timeout, $retries)->get(rtrim($baseUrl, '/').'/get-visitor-stats');
            $payload = $response->json();
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            return null;
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data) || ! array_key_exists('total', $data)) {
            return null;
        }

        return $this->normalizeMetricCount((string) $data['total']);
    }

    private function extractViewCount(string $html): ?int
    {
        $fromStructuredData = $this->extractFromStructuredData($html, ['WatchAction', 'ViewAction', 'ReadAction']);
        if ($fromStructuredData !== null) {
            return $fromStructuredData;
        }

        $patterns = [
            '/"view_count"\s*:\s*"?([\d\.,]+(?:\s*[kKmM])?)"?/',
            '/"views?"\s*:\s*"?([\d\.,]+(?:\s*[kKmM])?)"?/',
            '/data-view-count\s*=\s*"([\d\.,]+(?:\s*[kKmM])?)"/i',
            '/(?:dilihat|dibaca|views?)\s*[:\-]?\s*([\d\.,]+(?:\s*[kKmM])?)/iu',
            '/([\d\.,]+(?:\s*[kKmM])?)\s*(?:kali\s*)?(?:dilihat|dibaca|views?)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $html, $matches)) {
                continue;
            }

            $number = $this->normalizeMetricCount($matches[1] ?? null);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function extractVisitorCount(string $html): ?int
    {
        $fromStructuredData = $this->extractFromStructuredData($html, ['UserPageVisits', 'VisitAction']);
        if ($fromStructuredData !== null) {
            return $fromStructuredData;
        }

        $patterns = [
            '/"visitor_count"\s*:\s*"?([\d\.,]+(?:\s*[kKmM])?)"?/',
            '/"visitors?"\s*:\s*"?([\d\.,]+(?:\s*[kKmM])?)"?/i',
            '/data-visitor-count\s*=\s*"([\d\.,]+(?:\s*[kKmM])?)"/i',
            '/(?:pengunjung|pembaca unik|unique visitors?)\s*[:\-]?\s*([\d\.,]+(?:\s*[kKmM])?)/iu',
            '/([\d\.,]+(?:\s*[kKmM])?)\s*(?:pengunjung|pembaca unik|unique visitors?)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $html, $matches)) {
                continue;
            }

            $number = $this->normalizeMetricCount($matches[1] ?? null);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $acceptedInteractionTypes
     */
    private function extractFromStructuredData(string $html, array $acceptedInteractionTypes): ?int
    {
        preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches);

        foreach ($matches[1] ?? [] as $block) {
            $decoded = json_decode($block, true);
            if (! is_array($decoded)) {
                continue;
            }

            $count = $this->findInteractionCount($decoded, $acceptedInteractionTypes);
            if ($count !== null) {
                return $count;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $node
     * @param array<int, string> $acceptedInteractionTypes
     */
    private function findInteractionCount(array $node, array $acceptedInteractionTypes): ?int
    {
        if (array_key_exists('userInteractionCount', $node) && array_key_exists('interactionType', $node)) {
            $interactionType = $this->resolveInteractionType($node['interactionType']);
            if ($interactionType !== null && in_array($interactionType, $acceptedInteractionTypes, true)) {
                $count = $this->normalizeMetricCount((string) $node['userInteractionCount']);
                if ($count !== null) {
                    return $count;
                }
            }
        }

        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }

            $count = $this->findInteractionCount($value, $acceptedInteractionTypes);
            if ($count !== null) {
                return $count;
            }
        }

        return null;
    }

    /**
     * @param mixed $rawType
     */
    private function resolveInteractionType(mixed $rawType): ?string
    {
        if (is_string($rawType) && $rawType !== '') {
            return $rawType;
        }

        if (! is_array($rawType)) {
            return null;
        }

        $type = $rawType['@type'] ?? $rawType['name'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    private function normalizeMetricCount(?string $raw): ?int
    {
        if (! $raw) {
            return null;
        }

        $value = trim(mb_strtolower($raw));
        $multiplier = 1;

        if (str_ends_with($value, 'k')) {
            $multiplier = 1000;
            $value = rtrim($value, 'k');
        } elseif (str_ends_with($value, 'm')) {
            $multiplier = 1000000;
            $value = rtrim($value, 'm');
        }

        $value = preg_replace('/[^\d,\.]/', '', $value) ?? '';
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $value = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0].'.'.$parts[1]
                : implode('', $parts);
        } elseif (substr_count($value, '.') === 1) {
            $parts = explode('.', $value);
            $value = strlen($parts[1]) === 3
                ? implode('', $parts)
                : $value;
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value * $multiplier);
    }
}