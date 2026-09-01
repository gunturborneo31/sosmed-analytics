<div class="space-y-6">
    <x-page-header :title="$this->pageTitle" :description="$this->pageDescription">
        <x-slot:actions>
            <x-button
                variant="danger"
                wire:click="confirmClearArticles"
                title="Hapus semua berita hasil scraping"
            >
                <x-icon name="sampah" class="size-4" />
                Bersihkan berita
            </x-button>
            <x-button wire:click="scrape" wire:loading.attr="disabled" wire:target="scrape">
                <x-icon name="sinkron" class="size-4" wire:loading.remove wire:target="scrape" />
                <svg wire:loading wire:target="scrape" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span wire:loading.remove wire:target="scrape">Scrape sekarang</span>
                <span wire:loading wire:target="scrape">Mengambil berita...</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2 border-b border-hairline">
        @foreach (['berita' => 'Berita terkumpul', 'media' => 'Media sumber', 'topik' => 'Topik pencarian', 'jadwal' => 'Jadwal'] as $value => $label)
            <button type="button" wire:click="$set('tab', '{{ $value }}')" @class(['border-b-2 px-3 py-2 text-sm font-medium', 'border-brand-500 text-brand-700' => $tab === $value, 'border-transparent text-ink-muted hover:text-ink' => $tab !== $value])>{{ $label }}</button>
        @endforeach
    </div>
        <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid grid-cols-2 gap-4">

                <div class="rounded-2xl border border-hairline bg-surface px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">Total website</p>
                <p class="mt-1 font-display text-xl font-bold text-ink-strong">{{ number_format($mediaSources->count(), 0, ',', '.') }}</p>
            </div>
            <div class="flex items-center justify-between rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-brand-700">Total berita berhasil didapatkan</p>
                    <p class="mt-0.5 text-xs text-ink-muted">Seluruh berita tersimpan, tidak terpengaruh filter tabel.</p>
                </div>
                <p class="font-display text-2xl font-bold text-brand-700">{{ number_format($this->metricCoverage['total'], 0, ',', '.') }}</p>
            </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                
            <div class="rounded-2xl border border-hairline bg-surface px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">View terbaca</p>
                <p class="mt-1 font-display text-xl font-bold text-ink-strong">{{ number_format($this->metricCoverage['view'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-hairline bg-surface px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">Pengunjung terbaca</p>
                <p class="mt-1 font-display text-xl font-bold text-ink-strong">{{ number_format($this->metricCoverage['visitor'], 0, ',', '.') }}</p>
            </div>
            </div>
        </div>

        @if ($this->currentRun && in_array($this->currentRun->status, ['queued', 'running'], true))
            <div wire:poll.2s class="rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <svg class="size-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-brand-900">Scrape sedang berjalan...</p>
                            <p class="text-xs text-ink-muted">{{ $this->currentRun->new_articles }} berita baru ditemukan · langkah {{ $this->currentRun->processed_steps }} dari {{ $this->currentRun->total_steps }}</p>
                        </div>
                    </div>
                    <x-button type="button" variant="danger" wire:click="requestStopScrape" wire:loading.attr="disabled">
                        <x-icon name="silang" class="size-4" /> Stop scrape
                    </x-button>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/70"><div class="h-full rounded-full bg-brand-gradient transition-all duration-500" style="width: {{ $this->currentRun->total_steps > 0 ? min(100, round($this->currentRun->processed_steps / $this->currentRun->total_steps * 100)) : 0 }}%"></div></div>
            </div>
        @elseif ($this->currentRun && in_array($this->currentRun->status, ['completed', 'stopped', 'failed'], true))
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-hairline bg-surface-sunken px-4 py-3 text-sm text-ink">
                <span>{{ $this->currentRun->message }} {{ $this->currentRun->new_articles }} berita baru.</span>
                <span class="text-xs text-ink-muted">{{ $this->currentRun->updated_at?->translatedFormat('d M Y, H:i') }}</span>
            </div>
        @endif

    @if ($tab === 'berita')
        <div class="grid gap-3 rounded-2xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari judul atau isi..." class="h-10 rounded-xl border border-hairline bg-surface px-3 text-sm lg:col-span-2">
            <select wire:model.live="mediaFilter" class="h-10 rounded-xl border border-hairline bg-surface px-3 text-sm"><option value="">Semua media</option>@foreach ($mediaSources as $source)<option value="{{ $source->id }}">{{ $source->name }}</option>@endforeach</select>
            <select wire:model.live="topicFilter" class="h-10 rounded-xl border border-hairline bg-surface px-3 text-sm"><option value="">Semua topik</option>@foreach ($topics as $topic)<option value="{{ $topic->id }}">{{ $topic->keyword }}</option>@endforeach</select>
            <div class="flex gap-2"><input type="date" wire:model.live="publishedFrom" title="Dari tanggal" class="h-10 min-w-0 w-full rounded-xl border border-hairline bg-surface px-2 text-sm"><input type="date" wire:model.live="publishedUntil" title="Sampai tanggal" class="h-10 min-w-0 w-full rounded-xl border border-hairline bg-surface px-2 text-sm"></div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
                <select wire:model.live="sortBy" class="h-10 rounded-xl border border-hairline bg-surface px-3 text-sm" aria-label="Urutkan berdasarkan">
                    <option value="published_at">Urutkan: tanggal terbit</option>
                    <option value="title">Urutkan: judul</option>
                    <option value="media">Urutkan: media</option>
                    <option value="topic">Urutkan: topik</option>
                    <option value="view_count">Urutkan: jumlah dilihat</option>
                    <option value="visitor_count">Urutkan: jumlah pengunjung</option>
                </select>
                <button type="button" wire:click="sort('{{ $sortBy }}')" class="h-10 rounded-xl border border-hairline px-3 text-sm text-ink hover:bg-surface-sunken" title="Ubah arah pengurutan">
                    {{ $sortDirection === 'asc' ? 'Naik ↑' : 'Turun ↓' }}
                </button>
                <x-button type="button" variant="secondary" wire:click="checkMetricReadability">
                    Cek keterbacaan metrik
                </x-button>
                <x-button type="button" variant="secondary" wire:click="resetArticleFilters">Reset filter</x-button>
            </div>
        </div>
        <x-card :padded="false">
            <div class="overflow-x-auto"><table class="w-full min-w-[64rem] text-sm"><thead><tr class="border-b border-hairline bg-surface-sunken/60 text-left text-[11px] uppercase tracking-wide text-ink-muted"><th class="px-5 py-3"><button type="button" wire:click="sort('title')" class="hover:text-ink-strong">Berita {{ $sortBy === 'title' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}</button></th><th class="px-5 py-3"><button type="button" wire:click="sort('media')" class="hover:text-ink-strong">Media {{ $sortBy === 'media' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}</button></th><th class="px-5 py-3"><button type="button" wire:click="sort('topic')" class="hover:text-ink-strong">Topik {{ $sortBy === 'topic' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}</button></th><th class="px-5 py-3"><button type="button" wire:click="sort('view_count')" class="hover:text-ink-strong">View {{ $sortBy === 'view_count' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}</button></th><th class="px-5 py-3"><button type="button" wire:click="sort('visitor_count')" class="hover:text-ink-strong">Pengunjung {{ $sortBy === 'visitor_count' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}</button></th><th class="px-5 py-3"><button type="button" wire:click="sort('published_at')" class="hover:text-ink-strong">Terbit {{ $sortBy === 'published_at' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}</button></th></tr></thead><tbody class="divide-y divide-hairline">
                @forelse ($this->articles as $article)<tr class="transition hover:bg-surface-sunken"><td class="max-w-xl px-5 py-3"><a href="{{ $article->article_url }}" target="_blank" rel="noopener" class="font-medium text-ink-strong hover:text-brand-700 hover:underline">{{ $article->title }}</a>@if ($article->summary)<p class="mt-1 line-clamp-3 text-xs text-ink-muted">{{ strip_tags($article->summary) }}</p>@endif</td><td class="px-5 py-3 text-xs text-ink">{{ $article->mediaSource->name }}</td><td class="px-5 py-3"><div class="flex flex-wrap gap-1">@foreach ($article->searchTopics as $topic)<x-badge tone="brand">{{ $topic->keyword }}</x-badge>@endforeach</div></td><td class="whitespace-nowrap px-5 py-3 text-xs text-ink">{{ $article->view_count !== null ? number_format($article->view_count, 0, ',', '.') : '—' }}</td><td class="whitespace-nowrap px-5 py-3 text-xs text-ink">{{ $article->visitor_count !== null ? number_format($article->visitor_count, 0, ',', '.') : '—' }}</td><td class="whitespace-nowrap px-5 py-3 text-xs text-ink-muted">{{ $article->published_at?->translatedFormat('d M Y, H:i') ?? 'Tanggal tidak tersedia' }}</td></tr>@empty<tr><td colspan="6"><x-empty-state title="Belum ada berita" description="Tambahkan media dan topik, lalu jalankan scrape." /></td></tr>@endforelse
            </tbody></table></div>@if ($this->articles->hasPages())<div class="border-t border-hairline px-5 py-3">{{ $this->articles->links('vendor.pagination.kutim') }}</div>@endif
        </x-card>
    @elseif ($tab === 'media')
        @php
            $metricRun = $this->currentMediaMetricCheckRun;
            $metricRunActive = $metricRun && in_array($metricRun->status, ['queued', 'running'], true);
        @endphp
        <x-card title="Media sumber" subtitle="RSS akan diprioritaskan; URL utama dipakai sebagai fallback HTML">
            <x-slot:actions>
                <x-button
                    type="button"
                    variant="secondary"
                    wire:click="checkAllMediaSourceMetrics"
                    wire:loading.attr="disabled"
                    wire:target="checkAllMediaSourceMetrics"
                    :disabled="$metricRunActive"
                >
                    <span wire:loading.remove wire:target="checkAllMediaSourceMetrics">{{ $metricRunActive ? 'Pemeriksaan berjalan...' : 'Cek ulang sekaligus' }}</span>
                    <span wire:loading wire:target="checkAllMediaSourceMetrics">Menyiapkan pemeriksaan...</span>
                </x-button>
                <x-button wire:click="create('media')">
                    <x-icon name="tambah" class="size-4" />
                    Tambah media
                </x-button>
            </x-slot:actions>

            @if ($metricRunActive)
                <div wire:poll.2s class="mb-4 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <svg class="size-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3" />
                                <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-brand-900">Sedang memeriksa keterbacaan metrik media...</p>
                                <p class="text-xs text-ink-muted">
                                    Selesai {{ $metricRun->processed_sources }} dari {{ $metricRun->total_sources }}
                                    · View terbaca {{ $metricRun->view_readable_sources }}
                                    · Pengunjung terbaca {{ $metricRun->visitor_readable_sources }}
                                </p>
                            </div>
                        </div>
                        <x-button
                            type="button"
                            variant="danger"
                            wire:click="requestStopMediaMetricCheck"
                            wire:loading.attr="disabled"
                            wire:target="requestStopMediaMetricCheck"
                        >
                            <span wire:loading.remove wire:target="requestStopMediaMetricCheck">Stop pemeriksaan</span>
                            <span wire:loading wire:target="requestStopMediaMetricCheck">Menghentikan...</span>
                        </x-button>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/70">
                        <div
                            class="h-full rounded-full bg-brand-gradient transition-all duration-500"
                            style="width: {{ $metricRun->total_sources > 0 ? min(100, round($metricRun->processed_sources / $metricRun->total_sources * 100)) : 0 }}%"
                        ></div>
                    </div>
                </div>
            @elseif ($metricRun && in_array($metricRun->status, ['completed', 'failed', 'stopped'], true))
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-hairline bg-surface-sunken px-4 py-3 text-sm text-ink">
                    <span>
                        {{ $metricRun->message }}
                        View terbaca {{ $metricRun->view_readable_sources }}/{{ $metricRun->total_sources }},
                        pengunjung terbaca {{ $metricRun->visitor_readable_sources }}/{{ $metricRun->total_sources }}.
                    </span>
                    <span class="text-xs text-ink-muted">{{ $metricRun->updated_at?->translatedFormat('d M Y, H:i') }}</span>
                </div>
            @endif

            <div class="divide-y divide-hairline">
                @forelse ($mediaSources as $source)
                    <div class="flex flex-wrap items-start justify-between gap-4 py-3">
                        <div class="min-w-0 space-y-1.5">
                            <p class="truncate font-medium text-ink-strong">{{ $source->name }}</p>
                            <p class="truncate text-xs text-ink-muted">{{ $source->feed_url ?: $source->base_url }}</p>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <x-badge :tone="$source->can_read_view_count === null ? 'neutral' : ($source->can_read_view_count ? 'success' : 'danger')">
                                    View: {{ $source->can_read_view_count === null ? 'belum dicek' : ($source->can_read_view_count ? 'bisa dibaca' : 'tidak terbaca') }}
                                </x-badge>
                                <x-badge :tone="$source->can_read_visitor_count === null ? 'neutral' : ($source->can_read_visitor_count ? 'success' : 'danger')">
                                    Pengunjung: {{ $source->can_read_visitor_count === null ? 'belum dicek' : ($source->can_read_visitor_count ? 'bisa dibaca' : 'tidak terbaca') }}
                                </x-badge>
                            </div>
                            @if ($source->metrics_checked_at)
                                <p class="text-[11px] text-ink-muted">
                                    Cek terakhir: {{ $source->metrics_checked_at->translatedFormat('d M Y, H:i') }}
                                    @if ($source->metrics_check_message)
                                        · {{ $source->metrics_check_message }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 gap-1">
                            <x-button
                                type="button"
                                size="sm"
                                variant="secondary"
                                wire:click="checkMediaSourceMetrics('{{ $source->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="checkMediaSourceMetrics"
                                :disabled="$metricRunActive"
                            >
                                <span wire:loading.remove wire:target="checkMediaSourceMetrics">Cek ulang</span>
                                <span wire:loading wire:target="checkMediaSourceMetrics">Mengecek...</span>
                            </x-button>
                            <button wire:click="edit('media', '{{ $source->id }}')" title="Ubah media" class="grid size-8 place-items-center rounded-lg hover:bg-brand-50"><x-icon name="pensil" class="size-4" /></button>
                            <button wire:click="delete('media', '{{ $source->id }}')" wire:confirm="Hapus media ini?" title="Hapus media" class="grid size-8 place-items-center rounded-lg hover:bg-danger/10 hover:text-danger"><x-icon name="sampah" class="size-4" /></button>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-muted">Belum ada media sumber.</p>
                @endforelse
            </div>
        </x-card>
    @elseif ($tab === 'topik')
        <x-card title="Topik pencarian" subtitle="Kata kunci yang dipakai scraper; waktu ditentukan pada filter berita."><x-slot:actions><x-button wire:click="create('topic')"><x-icon name="tambah" class="size-4" />Tambah topik</x-button></x-slot:actions><div class="divide-y divide-hairline">@forelse ($topics as $topic)<div class="flex items-center justify-between gap-4 py-3"><div><p class="font-medium text-ink-strong">{{ $topic->keyword }}</p>@if ($topic->description)<p class="text-xs text-ink-muted">{{ $topic->description }}</p>@endif</div><div class="flex shrink-0 gap-1"><button wire:click="edit('topic', '{{ $topic->id }}')" title="Ubah topik" class="grid size-8 place-items-center rounded-lg hover:bg-brand-50"><x-icon name="pensil" class="size-4" /></button><button wire:click="delete('topic', '{{ $topic->id }}')" wire:confirm="Hapus topik ini?" title="Hapus topik" class="grid size-8 place-items-center rounded-lg hover:bg-danger/10 hover:text-danger"><x-icon name="sampah" class="size-4" /></button></div></div>@empty<p class="py-4 text-sm text-ink-muted">Belum ada topik pencarian.</p>@endforelse</div></x-card>
    @else
        <x-card title="Jadwal scraping" subtitle="Scheduler Laravel memeriksa jadwal ini setiap menit."><form wire:submit="saveSchedule" class="max-w-xl space-y-4"><x-input label="Interval (menit)" name="frequencyMinutes" type="number" min="1" wire:model="frequencyMinutes" required /><label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" wire:model="scheduleActive" class="rounded border-hairline text-brand-600"> Aktifkan scraping berkala</label><x-button type="submit"><x-icon name="tambah" class="size-4" />Simpan jadwal</x-button></form></x-card>
    @endif

    <x-modal
        property="confirmingClearArticles"
        title="Bersihkan semua berita?"
        subtitle="Media, topik, dan jadwal scraping tidak akan dihapus."
        tone="danger"
        icon="peringatan"
        width="max-w-md"
    >
        <div class="px-6 py-5">
            <p class="text-sm leading-relaxed text-ink">
                Semua berita hasil scraping akan dihapus permanen dari daftar agregator.
            </p>
            <p class="mt-3 text-xs leading-relaxed text-ink-muted">
                Scraping berikutnya tetap bisa dijalankan untuk mengisi daftar berita kembali.
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-hairline bg-surface-sunken/40 px-6 py-4">
            <x-button type="button" variant="ghost" wire:click="$set('confirmingClearArticles', false)">Batal</x-button>
            <x-button type="button" variant="danger" wire:click="clearArticles" wire:loading.attr="disabled">
                <x-icon name="sampah" class="size-4" wire:loading.remove wire:target="clearArticles" />
                <svg wire:loading wire:target="clearArticles" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span wire:loading.remove wire:target="clearArticles">Ya, bersihkan</span>
                <span wire:loading wire:target="clearArticles">Membersihkan...</span>
            </x-button>
        </div>
    </x-modal>

    <x-modal property="showForm" :title="$editingId ? ($formType === 'media' ? 'Ubah Media' : 'Ubah Topik') : ($formType === 'media' ? 'Media Baru' : 'Topik Baru')" :icon="$editingId ? 'pensil' : 'tambah'" width="max-w-2xl">
        <form wire:submit="save" class="space-y-4 px-6 py-5">
            @if ($formType === 'media')
                <x-input label="Nama media" name="name" wire:model="name" required placeholder="Suara Kutim" /><x-input label="URL utama" name="baseUrl" wire:model="baseUrl" type="url" required placeholder="https://contoh.com" /><x-input label="URL RSS (opsional)" name="feedUrl" wire:model="feedUrl" type="url" placeholder="https://contoh.com/feed/" />
            @else
                <x-input label="Kata kunci" name="keyword" wire:model="keyword" required placeholder="bupati" /><x-input label="Deskripsi" name="description" wire:model="description" placeholder="Catatan isu" />
            @endif
            <div class="flex justify-end gap-2 pt-2"><x-button type="button" variant="secondary" wire:click="$set('showForm', false)">Batal</x-button><x-button type="submit">Simpan</x-button></div>
        </form>
    </x-modal>
</div>