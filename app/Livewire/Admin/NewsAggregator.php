<?php

namespace App\Livewire\Admin;

use App\Models\MediaSource;
use App\Models\MediaMetricCheckRun;
use App\Models\ScrapedArticle;
use App\Models\ScrapingRun;
use App\Models\ScrapingSchedule;
use App\Models\SearchTopic;
use App\Jobs\CheckAllMediaSourceMetricsJob;
use App\Jobs\ScrapeNewsJob;
use App\Services\NewsScraperService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Web Agregator')]
class NewsAggregator extends Component
{
    use WithPagination;
    private const OPD_DOMAIN = 'kutaitimurkab.go.id';
    private const OPD_SOURCES = [
        ['name' => 'Sekretariat Daerah', 'base_url' => 'https://setkab.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Sekretariat Dewan Perwakilan Rakyat Daaerah', 'base_url' => 'https://dprdkutaitimur.id/', 'feed_url' => null],
        ['name' => 'Inspektorat Wilayah Daerah (ITWIL)', 'base_url' => 'https://inspektoratwilayah.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Badan Penanggulangan Bencana Daerah', 'base_url' => 'https://bpbd.damkarkutaitimur.com/', 'feed_url' => null],
        ['name' => 'Badan Perencanaan dan Pembangunan Daerah', 'base_url' => 'https://bappeda-kutaitimur.com/', 'feed_url' => null],
        ['name' => 'Badan Pendapatan Daerah', 'base_url' => 'https://bapenda.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia', 'base_url' => 'https://bkpsdm.kutaitimurkab.go.id/id', 'feed_url' => null],
        ['name' => 'Badan Pengelola Keuangan dan Aset Daerah', 'base_url' => 'https://bpkad.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Badan Kesatuan Bangsa dan Politik', 'base_url' => 'https://kesbangpol.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Badan Riset dan Inovasi Daerah', 'base_url' => 'https://brida-kutai-timur.id/', 'feed_url' => null],
        ['name' => 'Satuan Polisi Pamong Praja', 'base_url' => 'https://satpolpp.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Pemerintah Kabupaten Kutai Timur', 'base_url' => 'https://kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Komunikasi dan Informatika, Statistik dan Persandian', 'base_url' => 'https://diskominfo.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pariwisata', 'base_url' => 'https://dispar.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Perikanan', 'base_url' => 'https://diskan.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu', 'base_url' => 'https://dpmptsp.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pendidikan dan Kebudayaan', 'base_url' => 'https://disdikbud.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pemuda dan Olahraga', 'base_url' => 'https://disporakutim.com/', 'feed_url' => null],
        ['name' => 'Dinas Kesehatan', 'base_url' => 'https://dinkes.kutaitimurkab.go.id/ppid', 'feed_url' => null],
        ['name' => 'Dinas Sosial', 'base_url' => 'https://dinsos.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Tenaga Kerja dan Transmigrasi', 'base_url' => 'https://disnakerkabkutaitimur.com/', 'feed_url' => null],
        ['name' => 'Dinas Perhubungan', 'base_url' => 'https://dishub.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'DINAS PEKERJAAN UMUM', 'base_url' => 'https://dinaspu.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Perindustrian dan Perdagangan', 'base_url' => 'https://disperindagkutim.com/', 'feed_url' => null],
        ['name' => 'Dinas Perkebunan', 'base_url' => 'https://disbun.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Lingkungan Hidup', 'base_url' => 'https://dlh.kutaitiimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pemberdayaan Masyarakat Desa', 'base_url' => 'https://dispemas.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pemberdayaan Perempuan dan Perlindungan Anak', 'base_url' => 'https://dp3a.kpai-kabkutaitimur.com/', 'feed_url' => null],
        ['name' => 'Dinas Perpustakaan dan Kearsipan', 'base_url' => 'https://perpustakaan.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Kependudukan dan Pencatatan Sipil', 'base_url' => 'https://disdukcapil.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Koperasi,Usaha Kecil dan Menengah', 'base_url' => 'https://dinkop.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pemadam Kebakaran dan Penyelamatan', 'base_url' => 'https://damkarkutaitimur.com/category/berita-informasi/', 'feed_url' => null],
        ['name' => 'Dinas Ketahanan Pangan', 'base_url' => 'https://diskp.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Perumahan Rakyat dan Kawasan Permukiman', 'base_url' => 'https://disperkim.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Tanaman Pangan Hortikultura dan Peternakan', 'base_url' => 'https://distan.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pertanahan dan Penataan Ruang', 'base_url' => 'http://dispertanru.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Dinas Pengendalian Penduduk dan Keluarga Berencana', 'base_url' => 'https://dp2kb.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Sangatta Utara', 'base_url' => 'https://sangattautara.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Sangatta Selatan', 'base_url' => 'https://sangattaselatan.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Rantau Pulung', 'base_url' => 'https://rantaupulung.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Teluk Pandan', 'base_url' => 'https://telukpandan.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Bengalon', 'base_url' => 'https://bengalon.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Kaliorang', 'base_url' => 'https://kaliorang.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Kaubun', 'base_url' => 'https://kaubun.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Karangan', 'base_url' => 'https://karangan.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Sangkulirang', 'base_url' => 'https://sangkulirang.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Sandaran', 'base_url' => 'https://sandaran.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Telen', 'base_url' => 'https://telen.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Wahau', 'base_url' => 'https://wahau.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Kongbeng', 'base_url' => 'https://kongbeng.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Batu Ampar', 'base_url' => 'https://batuampar.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Muara Bengkal', 'base_url' => 'https://muarabengkal.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Muara Ancalong', 'base_url' => 'https://muaraancalong.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Long Mesangat', 'base_url' => 'https://longmesangat.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kecamatan Busang', 'base_url' => 'https://busang.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Desa Sangatta Utara', 'base_url' => 'https://desa-sangut.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Desa Singa Gembara', 'base_url' => 'https://desa-singagembara.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Desa Swarga bara', 'base_url' => 'https://desa-swargabara.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Kelurahan Teluk Lingga', 'base_url' => 'https://kel-teluklingga.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Rumah Sakit Umum Daerah Kudungga Kutai Timur', 'base_url' => 'https://rsudkudungga.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Perusda PDAM Kabupaten Kutai Timur', 'base_url' => 'https://perumdamttb.kutaitimurkab.go.id/', 'feed_url' => null],
        ['name' => 'Bank Perkreditan Rakyat Kutai Timur (BPR)', 'base_url' => 'https://bprkutim.co.id/', 'feed_url' => null],
    ];
    private const MEDIA_PARTNER_SOURCES = [
        ['name' => 'Kliksangatta.com (Jaringan Klikkaltim)', 'base_url' => 'https://kliksangatta.com/', 'feed_url' => null],
        ['name' => 'Prokutim.co (Jaringan Pro Kaltim / Jawa Pos Group)', 'base_url' => 'https://prokutim.co/', 'feed_url' => null],
        ['name' => 'Kutimpress.com', 'base_url' => 'https://kutimpress.com/', 'feed_url' => null],
        ['name' => 'Niaga.asia', 'base_url' => 'https://niaga.asia/', 'feed_url' => null],
        ['name' => 'Korankaltim.com', 'base_url' => 'https://korankaltim.com/', 'feed_url' => null],
    ];

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
    public string $aggregatorMode = 'opd';
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
        $this->aggregatorMode = request()->routeIs('admin.web-media-partner') ? 'media-partner' : 'opd';
        $schedule = ScrapingSchedule::query()->first();
        $this->frequencyMinutes = $schedule?->frequency_minutes ?? 60;
        $this->scheduleActive = $schedule?->is_active ?? true;

        if ($this->isOpdMode()) {
            $this->syncOpdMediaSources();
        }

        if ($this->isMediaPartnerMode()) {
            $this->syncMediaPartnerSources();
        }
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedMediaFilter(): void { $this->resetPage(); }
    public function updatedTopicFilter(): void { $this->resetPage(); }
    public function updatedPublishedFrom(): void { $this->resetPage(); }
    public function updatedPublishedUntil(): void { $this->resetPage(); }

    public function sort(string $column): void
    {
        if (! in_array($column, ['title', 'media', 'topic', 'published_at', 'view_count', 'visitor_count'], true)) {
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
            $host = (string) parse_url($data['baseUrl'], PHP_URL_HOST);
            if ($this->isOpdMode() && ! in_array($this->normalizeBaseUrl($data['baseUrl']), $this->normalizedOpdBaseUrls(), true)) {
                $this->dispatch('toast', type: 'error', message: 'Media OPD harus sesuai daftar OPD yang ditetapkan.');

                return;
            }

            if ($this->isMediaPartnerMode() && str_contains($host, self::OPD_DOMAIN)) {
                $this->dispatch('toast', type: 'error', message: 'Media partner tidak boleh menggunakan domain kutaitimurkab.go.id.');

                return;
            }
            if ($this->isMediaPartnerMode() && in_array($this->normalizeBaseUrl($data['baseUrl']), $this->normalizedOpdBaseUrls(), true)) {
                $this->dispatch('toast', type: 'error', message: 'Media partner tidak boleh menggunakan URL yang termasuk daftar OPD.');

                return;
            }
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

        $total = $this->mediaSourcesQuery()->where('is_active', true)->count()
            * SearchTopic::query()->where('is_active', true)->count();
        $run = ScrapingRun::create(['status' => ScrapingRun::QUEUED, 'total_steps' => $total]);
        ScrapeNewsJob::dispatch(
            $run->id,
            $this->scrapeDomainFilter(),
            $this->isMediaPartnerMode(),
            $this->mediaSourcesQuery()->pluck('id')->all(),
        );
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

        $deleted = $this->scopedArticlesQuery()->delete();
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

    public function checkMediaSourceMetrics(string $sourceId, NewsScraperService $scraper): void
    {
        $this->authorizeAdmin();

        $source = $this->mediaSourcesQuery()->findOrFail($sourceId);
        $result = $scraper->refreshMediaMetricStatus($source);

        $this->dispatch(
            'toast',
            type: 'success',
            message: "{$source->name}: view ".($result['can_read_view_count'] ? 'terbaca' : 'belum terbaca')
                .", pengunjung ".($result['can_read_visitor_count'] ? 'terbaca' : 'belum terbaca')
                ." ({$result['checked_articles']} artikel).",
        );
    }

    public function checkAllMediaSourceMetrics(): void
    {
        $this->authorizeAdmin();

        $activeRun = $this->currentMediaMetricCheckRun;
        if ($activeRun && in_array($activeRun->status, [MediaMetricCheckRun::QUEUED, MediaMetricCheckRun::RUNNING], true)) {
            $this->dispatch('toast', type: 'error', message: 'Cek metrik media sudah berjalan.');

            return;
        }

        $total = $this->mediaSourcesQuery()->count();
        if ($total === 0) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada media sumber untuk dicek.');

            return;
        }

        $run = MediaMetricCheckRun::query()->create([
            'status' => MediaMetricCheckRun::QUEUED,
            'total_sources' => $total,
        ]);
        CheckAllMediaSourceMetricsJob::dispatch(
            $run->id,
            $this->scrapeDomainFilter(),
            $this->isMediaPartnerMode(),
            $this->mediaSourcesQuery()->pluck('id')->all(),
        );
        unset($this->currentMediaMetricCheckRun);
        $this->dispatch('toast', type: 'success', message: "Cek ulang {$total} media diantrekan di background.");
    }

    public function requestStopMediaMetricCheck(): void
    {
        $this->authorizeAdmin();

        $run = $this->currentMediaMetricCheckRun;
        if (! $run || ! in_array($run->status, [MediaMetricCheckRun::QUEUED, MediaMetricCheckRun::RUNNING], true)) {
            return;
        }

        $run->update(['stop_requested' => true]);
        $this->dispatch('toast', type: 'success', message: 'Permintaan stop pemeriksaan metrik sudah dikirim.');
    }

    public function checkMetricReadability(): void
    {
        $this->authorizeAdmin();

        $query = $this->filteredArticlesQuery();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada berita untuk diperiksa.');

            return;
        }

        $viewReadable = (clone $query)->whereNotNull('scraped_articles.view_count')->count();
        $visitorReadable = (clone $query)->whereNotNull('scraped_articles.visitor_count')->count();

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Keterbacaan metrik: view {$viewReadable}/{$total}, pengunjung {$visitorReadable}/{$total}.",
        );
    }

    #[Computed]
    public function totalArticles(): int
    {
        return $this->scopedArticlesQuery()->count();
    }

    #[Computed]
    public function metricCoverage(): array
    {
        $base = $this->scopedArticlesQuery();
        $total = (clone $base)->count();
        $view = (clone $base)->whereNotNull('view_count')->count();
        $visitor = (clone $base)->whereNotNull('visitor_count')->count();

        return ['total' => $total, 'view' => $view, 'visitor' => $visitor];
    }

    #[Computed]
    public function pageTitle(): string
    {
        return $this->isOpdMode() ? 'Web OPD' : 'Web Media Partner';
    }

    #[Computed]
    public function pageDescription(): string
    {
        return $this->isOpdMode()
            ? 'Kumpulkan berita dari daftar website OPD Kutai Timur yang telah ditetapkan.'
            : 'Kumpulkan berita dari media swasta yang aktif memberitakan Kutai Timur.';
    }

    #[Computed]
    public function currentMediaMetricCheckRun(): ?MediaMetricCheckRun
    {
        return MediaMetricCheckRun::query()->latest('id')->first();
    }

    #[Computed]
    public function articles(): LengthAwarePaginator
    {
        $sortColumn = match ($this->sortBy) {
            'title' => 'scraped_articles.title',
            'media' => 'media_sources.name',
            'topic' => 'search_topics.keyword',
            'view_count' => 'scraped_articles.view_count',
            'visitor_count' => 'scraped_articles.visitor_count',
            default => 'scraped_articles.published_at',
        };

        return $this->filteredArticlesQuery()
            ->orderBy($sortColumn, $this->sortDirection)
            ->paginate(15);
    }

    private function filteredArticlesQuery(): Builder
    {
        return $this->scopedArticlesQuery()
            ->select('scraped_articles.*')
            ->leftJoin('media_sources', 'media_sources.id', '=', 'scraped_articles.media_source_id')
            ->leftJoin('search_topics', 'search_topics.id', '=', 'scraped_articles.search_topic_id')
            ->with(['mediaSource', 'searchTopic', 'searchTopics'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('scraped_articles.title', 'like', "%{$this->search}%")->orWhere('scraped_articles.summary', 'like', "%{$this->search}%")))
            ->when($this->mediaFilter, fn ($q) => $q->where('scraped_articles.media_source_id', $this->mediaFilter))
            ->when($this->topicFilter, fn ($q) => $q->whereHas('searchTopics', fn ($topic) => $topic->whereKey($this->topicFilter)))
            ->when($this->publishedFrom, fn ($q) => $q->whereDate('scraped_articles.published_at', '>=', $this->publishedFrom))
            ->when($this->publishedUntil, fn ($q) => $q->whereDate('scraped_articles.published_at', '<=', $this->publishedUntil));
    }

    private function authorizeAdmin(): void { abort_unless(auth()->user()->can('view-all-insights'), 403); }

    private function mediaSourcesQuery(): Builder
    {
        return MediaSource::query()
            ->when($this->isOpdMode(), fn (Builder $q) => $q->whereIn('base_url', $this->opdBaseUrls()))
            ->when(
                $this->isMediaPartnerMode(),
                fn (Builder $q) => $q->whereNotIn('base_url', $this->opdBaseUrls())
            );
    }

    private function scopedArticlesQuery(): Builder
    {
        return ScrapedArticle::query()
            ->when(
                $this->isOpdMode(),
                fn (Builder $q) => $q->whereHas(
                    'mediaSource',
                    fn (Builder $media) => $media->whereIn('base_url', $this->opdBaseUrls())
                )
            )
            ->when(
                $this->isMediaPartnerMode(),
                fn (Builder $q) => $q->whereHas(
                    'mediaSource',
                    fn (Builder $media) => $media->whereNotIn('base_url', $this->opdBaseUrls())
                )
            );
    }

    private function scrapeDomainFilter(): ?string
    {
        return $this->isMediaPartnerMode() ? self::OPD_DOMAIN : null;
    }

    private function isOpdMode(): bool
    {
        return $this->aggregatorMode === 'opd';
    }

    private function isMediaPartnerMode(): bool
    {
        return $this->aggregatorMode === 'media-partner';
    }

    private function syncOpdMediaSources(): void
    {
        MediaSource::query()
            ->where('base_url', 'like', '%'.self::OPD_DOMAIN.'%')
            ->whereNotIn('base_url', $this->opdBaseUrls())
            ->delete();

        foreach (self::OPD_SOURCES as $source) {
            MediaSource::updateOrCreate(
                ['base_url' => $source['base_url']],
                ['name' => $source['name'], 'feed_url' => $source['feed_url'], 'is_active' => true],
            );
        }
    }

    private function syncMediaPartnerSources(): void
    {
        foreach (self::MEDIA_PARTNER_SOURCES as $source) {
            MediaSource::updateOrCreate(
                ['base_url' => $source['base_url']],
                ['name' => $source['name'], 'feed_url' => $source['feed_url'], 'is_active' => true],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function mediaPartnerBaseUrls(): array
    {
        return array_column(self::MEDIA_PARTNER_SOURCES, 'base_url');
    }

    /**
     * @return list<string>
     */
    private function opdBaseUrls(): array
    {
        return array_column(self::OPD_SOURCES, 'base_url');
    }

    private function normalizeBaseUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    /**
     * @return list<string>
     */
    private function normalizedOpdBaseUrls(): array
    {
        return array_map(fn (string $url): string => $this->normalizeBaseUrl($url), $this->opdBaseUrls());
    }

    public function render()
    {
        return view('livewire.admin.news-aggregator', ['mediaSources' => $this->mediaSourcesQuery()->orderBy('name')->get(), 'topics' => SearchTopic::orderBy('keyword')->get()]);
    }
}