<div class="space-y-6">
    <x-page-header
        title="Ringkasan Kutai Timur"
        description="Performa media sosial seluruh perangkat daerah Kutai Timur."
    >
        <x-slot:actions>
            <x-badge tone="brand">{{ $this->period()->label() }}</x-badge>
            @can('view-all-insights')
                <x-button
                    type="button"
                    variant="danger"
                    wire:click="confirmClearData"
                    title="Kosongkan data analitik dan hasil scraping"
                >
                    <x-icon name="sampah" class="size-4" />
                    Bersihkan data
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :period="$period" :unit-types="$unitTypes" :platforms="$platforms" />

    {{-- ───────── Per kanal ─────────
         Instagram dan Facebook punya perilaku audiens yang berbeda. Angka
         gabungan menyembunyikan kanal mana yang sebenarnya bekerja, jadi
         masing-masing berdiri sendiri lebih dulu. --}}
    @foreach ($this->platforms as $platform)
        @php $kanal = $this->summaryFor($platform); @endphp

        <section wire:key="kanal-{{ $platform }}">
            <div class="mb-3 flex items-center gap-3">
                <x-platform-badge :platform="$platform" size="size-8" />
                <div>
                    <h2 class="font-display text-base font-semibold {{ \App\Support\SocialPlatform::textClass($platform) }}">
                        {{ \App\Support\SocialPlatform::label($platform) }}
                    </h2>
                    <p class="text-[11px] text-ink-muted">
                        {{ $kanal['accounts_connected'] }} akun di {{ $kanal['units_connected'] }} perangkat daerah
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-tile :index="0" label="Pengikut" value="" :numeric="$kanal['followers']"
                             :delta="$kanal['followers_delta']" show-delta caption="dibanding periode sebelumnya" />
                <x-stat-tile :index="1" label="Jangkauan Warga" value="" :numeric="$kanal['reach']"
                             :delta="$kanal['reach_delta']" show-delta caption="akumulasi periode ini" />
                <x-stat-tile :index="2" label="Engagement"
                             :value="number_format($kanal['engagement_rate'], 2, ',', '.').'%'"
                             caption="interaksi dibagi pengikut" />
            </div>
        </section>
    @endforeach

    {{-- ───────── Gabungan ─────────
         Tetap ditampilkan karena inilah angka yang masuk laporan, tapi setelah
         rincian per kanal supaya perbedaan antar kanal terbaca lebih dulu. --}}
    <section class="rounded-2xl border border-hairline bg-surface-sunken/50 p-5">
        <div class="mb-3">
            <h2 class="font-display text-base font-semibold text-ink-strong">Gabungan Seluruh Kanal</h2>
            <p class="text-[11px] text-ink-muted">
                Penjumlahan Instagram dan Facebook. Warga yang mengikuti kedua kanal terhitung dua kali.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                :index="0"
                label="Akun Aktif"
                :value="' / '.$this->summary['units_total']"
                :numeric="$this->summary['units_connected']"
                :caption="$this->summary['accounts_connected'].' akun medsos terhubung'"
            />
            <x-stat-tile :index="1" label="Total Pengikut" value="" :numeric="$this->summary['followers']"
                         :delta="$this->summary['followers_delta']" show-delta caption="dibanding periode sebelumnya" />
            <x-stat-tile :index="2" label="Jangkauan Warga" value="" :numeric="$this->summary['reach']"
                         :delta="$this->summary['reach_delta']" show-delta caption="akumulasi periode ini" />
            <x-stat-tile :index="3" label="Rerata Engagement"
                         :value="number_format($this->summary['engagement_rate'], 2, ',', '.').'%'"
                         caption="rata-rata seluruh akun" />
        </div>
    </section>

    {{-- Peta Denyut dihapus dari halaman ini: sebarannya tidak menjawab
         pertanyaan yang biasa dibawa admin ke dashboard, dan justru menyita
         ruang paling atas. Kelas PulseMap sendiri tetap ada karena daftar
         koordinat kecamatannya masih dipakai formulir Perangkat Daerah. --}}

    {{-- Copy sengaja mengarahkan pendampingan, bukan menyalahkan OPD (§8.3) --}}
    <x-card title="Perlu Perhatian" subtitle="Hal yang bisa didampingi minggu ini">
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['unconnected', 'OPD belum menghubungkan akun', 'warning', route('admin.units', ['status' => 'belum'])],
                ['expiring', 'token akan kedaluwarsa dalam 7 hari', 'warning', route('admin.units')],
                ['stale', 'akun tanpa sinkronisasi 30 hari terakhir', 'neutral', route('admin.sync-logs')],
                ['failed_syncs', 'sinkronisasi gagal dalam 7 hari terakhir', 'danger', route('admin.sync-logs', ['status' => 'failed'])],
            ] as [$key, $label, $tone, $link])
                <li>
                    <a href="{{ $link }}" class="flex items-start gap-3 rounded-xl border border-hairline p-3 transition hover:bg-surface-sunken">
                        <x-badge :tone="$this->attention[$key] > 0 ? $tone : 'success'" class="mt-0.5 shrink-0 font-mono">
                            {{ $this->attention[$key] }}
                        </x-badge>
                        <span class="text-xs leading-snug text-ink">{{ $label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if (array_sum($this->attention) === 0)
            <p class="mt-4 rounded-xl bg-success/10 px-3 py-2 text-xs text-success">
                Semua akun sehat. Tidak ada yang perlu didampingi saat ini.
            </p>
        @endif
    </x-card>

    {{-- Profil audiens seluruh Kutai Timur --}}
    <x-card title="Profil Audiens Kutai Timur" subtitle="Gabungan demografi seluruh akun terhubung">
        <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            <div>
                <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    Spektrum usia 16–64 · perempuan | laki-laki
                </p>
                <x-chart id="spektrum-usia" :options="$this->ageChart" :height="300" />
            </div>

            <div class="space-y-6">
                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">Rasio gender</p>
                    @php
                        $gender = $this->genderSplit;
                        $genderTotal = max($gender->sum(), 1);
                    @endphp
                    <div class="flex h-3 overflow-hidden rounded-full bg-surface-sunken">
                        <div class="bg-brand-bright transition-all duration-700" style="width: {{ round($gender['F'] / $genderTotal * 100, 1) }}%"></div>
                        <div class="bg-brand-500 transition-all duration-700" style="width: {{ round($gender['M'] / $genderTotal * 100, 1) }}%"></div>
                    </div>
                    <div class="mt-2 flex justify-between font-mono text-xs text-ink">
                        <span>♀ {{ number_format($gender['F'] / $genderTotal * 100, 1, ',', '.') }}%</span>
                        <span>♂ {{ number_format($gender['M'] / $genderTotal * 100, 1, ',', '.') }}%</span>
                    </div>
                </div>

                <div>
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">Wilayah teratas</p>
                    @php $cityMax = max($this->topCities->max() ?? 1, 1); @endphp
                    <ul class="space-y-2">
                        @forelse ($this->topCities as $city => $count)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-xs">
                                    <span class="truncate text-ink">{{ $city }}</span>
                                    <span class="shrink-0 font-mono text-ink-muted">{{ number_format($count, 0, ',', '.') }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                                    <div class="h-full rounded-full bg-brand-300" style="width: {{ round($count / $cityMax * 100, 1) }}%"></div>
                                </div>
                            </li>
                        @empty
                            <li class="text-xs text-ink-muted">Belum ada data wilayah.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Tren jangkauan --}}
    <x-card title="Tren Jangkauan Kutai Timur" :subtitle="$this->period()->label()">
        <x-chart id="tren-kabupaten" :options="$this->trendChart" :height="260" />
    </x-card>

    {{-- Peringkat OPD --}}
    <x-card title="Peringkat Perangkat Daerah" :subtitle="'Seluruh '.$this->ranking->total().' perangkat daerah, diurut berdasarkan kolom yang dipilih'" :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-3xl text-sm">
                <thead>
                    <tr class="border-b border-hairline text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                        <th class="px-5 py-3 font-medium">#</th>
                        @foreach ([
                            'name' => 'Perangkat Daerah',
                            'followers' => 'Pengikut',
                            'growth' => 'Δ',
                            'reach' => 'Jangkauan',
                            'engagement' => 'Engagement',
                        ] as $column => $label)
                            <th class="px-5 py-3 font-medium {{ $column === 'name' ? '' : 'text-right' }}">
                                <button type="button" wire:click="sort('{{ $column }}')" class="inline-flex items-center gap-1 hover:text-ink-strong">
                                    {{ $label }}
                                    @if ($sortBy === $column)
                                        <span aria-hidden="true">{{ $direction === 'desc' ? '↓' : '↑' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->ranking as $i => $row)
                        <tr wire:key="peringkat-{{ $row->unit_id }}" class="transition hover:bg-surface-sunken">
                            {{-- Nomor mengikuti posisi di seluruh daftar, bukan
                                 mulai dari 1 lagi tiap halaman. --}}
                            <td class="px-5 py-3 font-mono text-xs text-ink-muted">{{ $this->ranking->firstItem() + $i }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.units.show', $row->unit_slug) }}" class="font-medium text-ink-strong hover:text-brand-700 hover:underline">
                                    {{ $row->unit_name }}
                                </a>
                                <span class="ml-2 text-[11px] uppercase tracking-wide text-ink-muted">{{ $row->unit_type }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono">{{ number_format($row->followers, 0, ',', '.') }}</td>
                            <td @class([
                                'px-5 py-3 text-right font-mono',
                                'text-success' => $row->growth > 0,
                                'text-danger' => $row->growth < 0,
                                'text-ink-muted' => $row->growth === null || $row->growth == 0,
                            ])>
                                @if ($row->growth === null)
                                    <span title="Periode pembanding belum punya data">&mdash;</span>
                                @else
                                    {{ $row->growth > 0 ? '+' : '' }}{{ number_format($row->growth, 1, ',', '.') }}%
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-mono">{{ number_format($row->reach, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-mono">{{ number_format($row->engagement_rate, 2, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state
                                    title="Belum ada data untuk filter ini"
                                    description="Coba longgarkan filter, atau tunggu sinkronisasi pertama selesai berjalan."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->ranking->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-hairline px-5 py-3">
                <p class="text-xs text-ink-muted">
                    Menampilkan {{ $this->ranking->firstItem() }}–{{ $this->ranking->lastItem() }}
                    dari {{ $this->ranking->total() }} perangkat daerah
                </p>

                <a href="{{ route('admin.units') }}" class="text-xs font-medium text-brand-700 hover:underline">
                    Kelola perangkat daerah &rarr;
                </a>
            </div>

            @if ($this->ranking->hasPages())
                <div class="border-t border-hairline px-5 py-3">
                    {{ $this->ranking->links('vendor.pagination.kutim') }}
                </div>
            @endif
        @endif
    </x-card>

    <x-modal
        property="confirmingClearData"
        title="Bersihkan semua data?"
        subtitle="Akun login dan master perangkat daerah tetap dipertahankan."
        tone="danger"
        icon="peringatan"
        width="max-w-md"
    >
        <div class="px-6 py-5">
            <p class="text-sm leading-relaxed text-ink">
                Data akun media sosial, snapshot insight, demografi, log sinkronisasi, dan hasil scraping berita akan dihapus permanen.
            </p>
            <p class="mt-3 text-xs leading-relaxed text-ink-muted">
                Tindakan ini tidak bisa dibatalkan. Setelahnya akun media sosial harus dihubungkan kembali untuk mengisi data baru.
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-hairline bg-surface-sunken/40 px-6 py-4">
            <x-button type="button" variant="ghost" wire:click="$set('confirmingClearData', false)">Batal</x-button>
            <x-button type="button" variant="danger" wire:click="clearData" wire:loading.attr="disabled">
                <x-icon name="sampah" class="size-4" />
                Ya, bersihkan
            </x-button>
        </div>
    </x-modal>
</div>
