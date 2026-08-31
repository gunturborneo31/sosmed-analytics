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
    public function scrape(?callable $onProgress = null, ?callable $shouldStop = null): int
    {
        $saved = 0;
        $processed = 0;
        $total = MediaSource::query()->where('is_active', true)->count()
            * SearchTopic::query()->where('is_active', true)->count();

        MediaSource::query()->where('is_active', true)->each(function (MediaSource $source) use (&$saved, &$processed, $total, $onProgress, $shouldStop) {
            if ($shouldStop && $shouldStop()) {
                return false;
            }

            SearchTopic::query()->where('is_active', true)->each(function (SearchTopic $topic) use ($source, &$saved, &$processed, $total, $onProgress, $shouldStop) {
                if ($shouldStop && $shouldStop()) {
                    return false;
                }

                try {
                    $items = $source->feed_url
                        ? $this->fromFeed($source->feed_url)
                        : $this->fromHtml($source->base_url);
                } catch (\Throwable $exception) {
                    Log::warning('News scraping gagal', ['source' => $source->name, 'error' => $exception->getMessage()]);
                }

                foreach ($items ?? [] as $item) {
                    if (! $item['url'] || ! $this->matchesTopic($item, $topic) || ! $this->matchesDate($item['published_at'], $topic)) {
                        continue;
                    }

                    $article = ScrapedArticle::where('article_url', $item['url'])->first();

                    if ($article) {
                        $article->searchTopics()->syncWithoutDetaching([$topic->id]);
                        continue;
                    }

                    $article = ScrapedArticle::create([
                        'media_source_id' => $source->id,
                        'search_topic_id' => $topic->id,
                        'title' => $item['title'] ?: 'Tanpa judul',
                        'article_url' => $item['url'],
                        'summary' => $item['summary'],
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

    /** @return list<array{title: string, url: ?string, summary: ?string, published_at: ?CarbonImmutable}> */
    private function fromFeed(string $url): array
    {
        $xml = Http::timeout(15)->retry(2, 250)->get($url)->throw()->body();
        $feed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);

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
            ];
        }

        return $items;
    }

    /** @return list<array{title: string, url: ?string, summary: ?string, published_at: ?CarbonImmutable}> */
    private function fromHtml(string $url): array
    {
        $html = Http::timeout(15)->retry(2, 250)->get($url)->throw()->body();
        $crawler = new Crawler($html, $url);
        $items = [];

        $crawler->filter('article, .post, .berita, h1, h2, h3')->each(function (Crawler $node) use (&$items, $url): void {
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
            ];
        });

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
}