<?php

namespace App\Livewire\Admin;

use App\Models\MediaSource;
use App\Models\ScrapedArticle;
use App\Models\ScrapingRun;
use App\Models\ScrapingSchedule;
use App\Models\SearchTopic;
use App\Jobs\ScrapeNewsJob;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Media Partner')]
class NewsAggregator extends Component
{
    use WithPagination;

    public string $tab = 'berita';
    public string $search = '';
    public string $mediaFilter = '';
    public string $topicFilter = '';
    public string $publishedFrom = '';
    public string $publishedUntil = '';
    public string $sortBy = 'published_at';
    public string $sortDirection = 'desc';
    public bool $showForm = false;

    public bool $confirmingClearArticles = false;
    public bool $confirmingStopScrape = false;
    public string $formType = 'media';
    public ?string $editingId = null;
    public string $name = '';
    public string $baseUrl = '';
    public string $feedUrl = '';
    public string $keyword = '';
    public string $description = '';
    public int $frequencyMinutes = 60;
    public bool $scheduleActive = true;

    public function mount(): void
    {
        $schedule = ScrapingSchedule::query()->first();
        $this->frequencyMinutes = $schedule?->frequency_minutes ?? 60;
        $this->scheduleActive = $schedule?->is_active ?? true;
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedMediaFilter(): void { $this->resetPage(); }
    public function updatedTopicFilter(): void { $this->resetPage(); }
    public function updatedPublishedFrom(): void { $this->resetPage(); }
    public function updatedPublishedUntil(): void { $this->resetPage(); }

    public function sort(string $column): void
    {
        if (! in_array($column, ['title', 'media', 'topic', 'published_at'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function resetArticleFilters(): void
    {
        $this->reset('search', 'mediaFilter', 'topicFilter', 'publishedFrom', 'publishedUntil');
        $this->resetPage();
    }

    public function create(string $type): void
    {
        $this->authorizeAdmin();
        $this->reset('editingId', 'name', 'baseUrl', 'feedUrl', 'keyword', 'description');
        $this->formType = $type;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(string $type, string $id): void
    {
        $this->authorizeAdmin();
        $this->formType = $type;
        $record = $type === 'media' ? MediaSource::findOrFail($id) : SearchTopic::findOrFail($id);
        $this->editingId = $id;
        if ($type === 'media') {
            $this->name = $record->name; $this->baseUrl = $record->base_url; $this->feedUrl = $record->feed_url ?? '';
        } else {
            $this->keyword = $record->keyword; $this->description = $record->description ?? '';
        }
        $this->resetErrorBag(); $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        if ($this->formType === 'media') {
            $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'baseUrl' => ['required', 'url', 'max:500'], 'feedUrl' => ['nullable', 'url', 'max:500']]);
            MediaSource::updateOrCreate(['id' => $this->editingId], ['name' => $data['name'], 'base_url' => $data['baseUrl'], 'feed_url' => $data['feedUrl'] ?: null]);
        } else {
            $data = $this->validate(['keyword' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string']]);
            SearchTopic::updateOrCreate(['id' => $this->editingId], ['keyword' => $data['keyword'], 'description' => $data['description'] ?: null, 'time_filter_type' => 'all', 'start_date' => null, 'end_date' => null]);
        }
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Data agregator disimpan.');
    }

    public function delete(string $type, string $id): void
    {
        $this->authorizeAdmin();
        ($type === 'media' ? MediaSource::findOrFail($id) : SearchTopic::findOrFail($id))->delete();
        $this->dispatch('toast', type: 'success', message: 'Data dihapus.');
    }

    public function scrape(): void
    {
        $this->authorizeAdmin();
        if ($this->currentRun?->status === ScrapingRun::RUNNING || $this->currentRun?->status === ScrapingRun::QUEUED) {
            $this->dispatch('toast', type: 'error', message: 'Scrape sedang berjalan.');

            return;
        }

        $total = MediaSource::query()->where('is_active', true)->count()
            * SearchTopic::query()->where('is_active', true)->count();
        $run = ScrapingRun::create(['status' => ScrapingRun::QUEUED, 'total_steps' => $total]);
        ScrapeNewsJob::dispatch($run->id);
        unset($this->currentRun);
        $this->dispatch('toast', type: 'success', message: 'Scrape diantrekan di background.');
    }

    public function requestStopScrape(): void
    {
        $this->authorizeAdmin();
        $run = $this->currentRun;
        if ($run && in_array($run->status, [ScrapingRun::QUEUED, ScrapingRun::RUNNING], true)) {
            $run->update(['stop_requested' => true]);
            unset($this->currentRun);
        }
    }

    #[Computed]
    public function currentRun(): ?ScrapingRun
    {
        return ScrapingRun::query()->latest('id')->first();
    }

    public function confirmClearArticles(): void
    {
        $this->authorizeAdmin();

        $this->confirmingClearArticles = true;
    }

    public function clearArticles(): void
    {
        $this->authorizeAdmin();

        $deleted = ScrapedArticle::query()->delete();
        $this->confirmingClearArticles = false;
        $this->resetPage();
        unset($this->articles);
        $this->dispatch('toast', type: 'success', message: $deleted
            ? "{$deleted} berita berhasil dibersihkan."
            : 'Daftar berita sudah kosong.');
    }

    public function saveSchedule(): void
    {
        $this->authorizeAdmin();
        $data = $this->validate(['frequencyMinutes' => ['required', 'integer', 'min:1', 'max:10080']]);
        ScrapingSchedule::updateOrCreate(['id' => 1], ['frequency_minutes' => $data['frequencyMinutes'], 'is_active' => $this->scheduleActive]);
        $this->dispatch('toast', type: 'success', message: 'Interval scraper diperbarui.');
    }

    #[Computed]
    public function totalArticles(): int
    {
        return ScrapedArticle::query()->count();
    }

    #[Computed]
    public function articles(): LengthAwarePaginator
    {
        $sortColumn = match ($this->sortBy) {
            'title' => 'scraped_articles.title',
            'media' => 'media_sources.name',
            'topic' => 'search_topics.keyword',
            default => 'scraped_articles.published_at',
        };

        return ScrapedArticle::query()
            ->select('scraped_articles.*')
            ->leftJoin('media_sources', 'media_sources.id', '=', 'scraped_articles.media_source_id')
            ->leftJoin('search_topics', 'search_topics.id', '=', 'scraped_articles.search_topic_id')
            ->with(['mediaSource', 'searchTopic', 'searchTopics'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('scraped_articles.title', 'like', "%{$this->search}%")->orWhere('scraped_articles.summary', 'like', "%{$this->search}%")))
            ->when($this->mediaFilter, fn ($q) => $q->where('scraped_articles.media_source_id', $this->mediaFilter))
            ->when($this->topicFilter, fn ($q) => $q->whereHas('searchTopics', fn ($topic) => $topic->whereKey($this->topicFilter)))
            ->when($this->publishedFrom, fn ($q) => $q->whereDate('scraped_articles.published_at', '>=', $this->publishedFrom))
            ->when($this->publishedUntil, fn ($q) => $q->whereDate('scraped_articles.published_at', '<=', $this->publishedUntil))
            ->orderBy($sortColumn, $this->sortDirection)
            ->paginate(15);
    }

    private function authorizeAdmin(): void { abort_unless(auth()->user()->can('view-all-insights'), 403); }

    public function render()
    {
        return view('livewire.admin.news-aggregator', ['mediaSources' => MediaSource::orderBy('name')->get(), 'topics' => SearchTopic::orderBy('keyword')->get()]);
    }
}