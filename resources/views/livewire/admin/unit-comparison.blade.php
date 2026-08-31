<div class="space-y-6">
    <x-page-header
        title="Perbandingan Perangkat Daerah"
        description="Pilih 2–5 OPD untuk melihat tren, profil usia, dan sebaran wilayahnya berdampingan."
    />

    <x-filter-bar :period="$period" :platforms="$platforms" :show-unit-type="false" />

    <x-card title="Pilih Perangkat Daerah" :subtitle="count($selected).' dari '.\App\Livewire\Admin\UnitComparison::MAX_UNITS.' terpilih'">
        <x-slot:actions>
            @if ($selected)
                <x-button variant="ghost" size="sm" wire:click="clearSelection">Kosongkan</x-button>
            @endif
        </x-slot:actions>

        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari perangkat daerah…"
            class="mb-4 h-10 w-full max-w-sm rounded-xl border border-hairline bg-surface px-3.5 text-sm text-ink-strong placeholder:text-ink-muted/70 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12"
        >

        @error('selected')
            <p class="mb-3 text-xs text-danger">{{ $message }}</p>
        @enderror

        <div class="flex flex-wrap gap-2">
            @foreach ($this->candidates as $unit)
                @php $isOn = in_array($unit->id, $selected, true); @endphp
                <button
                    type="button"
                    wire:key="pilih-{{ $unit->id }}"
                    wire:click="toggle('{{ $unit->id }}')"
                    @class([
                        'rounded-full border px-3 py-1.5 text-xs font-medium transition',
                        'border-brand-500 bg-brand-50 text-brand-700' => $isOn,
                        'border-hairline text-ink hover:bg-surface-sunken' => ! $isOn,
                    ])
                    aria-pressed="{{ $isOn ? 'true' : 'false' }}"
                >{{ $unit->name }}</button>
            @endforeach
        </div>
    </x-card>

    @php $cukup = count($selected) >= 2; @endphp

    @unless ($cukup)
        <x-card>
            <x-empty-state
                title="Pilih minimal dua perangkat daerah"
                description="Perbandingan berguna untuk melihat apakah dinas dengan audiens muda perlu strategi konten berbeda dari kecamatan dengan audiens lebih tua."
            />
        </x-card>
    @endunless

    @if ($cukup)
        {{-- Grafik ini muncul di tengah jalan (setelah OPD kedua dipilih), jadi
             dipakai mode digambar-ulang: `wire:ignore` membuat Livewire tidak
             pernah menginisialisasinya, dan menyembunyikannya lebih dulu membuat
             ApexCharts terkunci pada ukuran nol. Kuncinya memuat pilihan dan
             saringan supaya grafiknya digambar ulang setiap keduanya berubah.

             Cakupan kanal ikut disebut: grafik yang sedang disaring ke satu
             kanal terlihat sama saja dengan grafik gabungan. --}}
        <x-card
            title="Tren Pengikut"
            :subtitle="$this->period()->label().' · '.($platform === '' ? 'Instagram + Facebook digabung' : \App\Support\SocialPlatform::label($platform).' saja')"
        >
            <x-slot:actions>
                {{-- Dua cara ukur, karena keduanya menjawab pertanyaan berbeda:
                     "siapa yang lebih besar" versus "siapa yang tumbuh lebih
                     cepat". Pada sumbu absolut, OPD yang naik 0,6% tampak lurus
                     di sebelah yang naik 43% — bukan karena datanya salah. --}}
                <div class="inline-flex rounded-xl border border-hairline p-0.5">
                    @foreach (['jumlah' => 'Jumlah pengikut', 'pertumbuhan' => 'Pertumbuhan'] as $nilai => $label)
                        <button
                            type="button"
                            wire:click="setMetric('{{ $nilai }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                'bg-brand-gradient text-white shadow-card' => $metric === $nilai,
                                'text-ink hover:text-ink-strong' => $metric !== $nilai,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </x-slot:actions>

            <x-chart
                id="tren-perbandingan"
                :options="$this->trendChart"
                :height="320"
                :morph="false"
                :key="'tren-'.implode('-', $selected).$period.$platform.$metric.$from.$until"
            />

            {{-- Angkanya ditulis apa adanya. Pertumbuhan 0,6% memang terlihat
                 rata di sebelah 43%, dan tidak ada penyetelan sumbu yang bisa
                 mengubah itu tanpa berbohong — jadi yang kecil tetap bisa
                 dibaca di sini. --}}
            <ul class="mt-4 flex flex-wrap gap-2 border-t border-hairline pt-3">
                @foreach ($this->trendSummary as $baris)
                    <li class="flex items-baseline gap-2 rounded-lg border border-hairline px-2.5 py-1.5">
                        <span class="truncate text-[11px] text-ink">{{ $baris['nama'] }}</span>
                        @if ($baris['persen'] === null)
                            <span class="font-mono text-[11px] text-ink-muted" title="Belum ada pengikut di awal periode">&mdash;</span>
                        @else
                            <span @class([
                                'font-mono text-[11px] font-medium',
                                'text-success' => $baris['selisih'] > 0,
                                'text-danger' => $baris['selisih'] < 0,
                                'text-ink-muted' => $baris['selisih'] === 0,
                            ])>
                                {{ $baris['selisih'] > 0 ? '+' : '' }}{{ number_format($baris['selisih'], 0, ',', '.') }}
                                ({{ $baris['persen'] > 0 ? '+' : '' }}{{ number_format($baris['persen'], 2, ',', '.') }}%)
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($this->growthMode())
                <p class="mt-3 text-[11px] leading-relaxed text-ink-muted">
                    Setiap garis berangkat dari 0% pada awal periode, jadi yang dibandingkan lajunya —
                    bukan besarnya. OPD kecil yang tumbuh cepat akan terlihat naik lebih tajam daripada
                    OPD besar yang stagnan.
                </p>
            @endif
        </x-card>


        <div class="grid gap-6 @if(count($selected) > 2) xl:grid-cols-3 @else md:grid-cols-2 @endif">
            @foreach ($this->comparison as $row)
                @php
                    $ageMax = max($row['age']->max() ?? 1, 1);
                    $genderKnown = max($row['gender']['F'] + $row['gender']['M'], 1);
                @endphp
                <x-card wire:key="banding-{{ $row['unit']->id }}">
                    <h3 class="font-display text-base font-semibold text-ink-strong">{{ $row['unit']->name }}</h3>

                    {{-- Lencana kanal menempel pada nama OPD supaya angka di
                         bawahnya tidak pernah terbaca sebagai total instansi
                         padahal sedang disaring ke satu kanal saja. --}}
                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] uppercase tracking-wide text-ink-muted">
                        <span>{{ $row['unit']->type }}</span>

                        @foreach ($row['kanal'] as $kanal)
                            <span class="inline-flex items-center gap-1 rounded-full border border-hairline px-1.5 py-0.5 normal-case tracking-normal {{ \App\Support\SocialPlatform::textClass($kanal) }}">
                                <x-platform-icon :platform="$kanal" class="size-3" />
                                {{ \App\Support\SocialPlatform::label($kanal) }}
                            </span>
                        @endforeach
                    </p>

                    @if ($row['kanal'] === [])
                        {{-- Deretan nol terbaca seperti performa buruk, padahal
                             OPD ini memang belum punya akunnya. Dibedakan: belum
                             terhubung sama sekali, atau hanya tidak punya akun di
                             kanal yang sedang disaring. --}}
                        <p class="mt-4 rounded-xl border border-hairline bg-surface-sunken px-3.5 py-3 text-xs leading-relaxed text-ink-muted">
                            @if ($platform === '')
                                Belum menghubungkan akun media sosial apa pun, jadi tidak ada angka yang bisa
                                dibandingkan — termasuk garisnya pada grafik tren di atas.
                            @else
                                Belum punya akun
                                <span class="font-medium text-ink">{{ \App\Support\SocialPlatform::label($platform) }}</span>.
                                Ganti saringan platform ke <span class="font-medium text-ink">Semua platform</span>
                                untuk melihat kanal lain yang dimilikinya.
                            @endif
                        </p>
                    @else

                    {{-- Rincian per kanal lebih dulu: angka gabungan menutupi
                         kanal mana yang sebenarnya bekerja di OPD ini. --}}
                    @if ($row['per_kanal'] !== [])
                        <ul class="mt-4 space-y-2 border-t border-hairline pt-3">
                            @foreach ($row['per_kanal'] as $kanal => $ringkas)
                                <li class="flex items-center gap-2.5">
                                    <x-platform-badge :platform="$kanal" size="size-6" />
                                    <span class="w-16 shrink-0 text-[11px] font-medium {{ \App\Support\SocialPlatform::textClass($kanal) }}">
                                        {{ \App\Support\SocialPlatform::label($kanal) }}
                                    </span>
                                    <span class="flex-1 text-right font-mono text-xs text-ink-strong">
                                        {{ number_format($ringkas['followers'], 0, ',', '.') }}
                                    </span>
                                    <span class="w-14 shrink-0 text-right font-mono text-[11px] text-ink-muted">
                                        {{ number_format($ringkas['engagement_rate'], 2, ',', '.') }}%
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <dl class="mt-3 grid grid-cols-2 gap-3 border-y border-hairline py-3">
                        <div>
                            <dt class="text-[10px] uppercase tracking-[0.06em] text-ink-muted">
                                {{ $row['per_kanal'] !== [] ? 'Pengikut (gabungan)' : 'Pengikut' }}
                            </dt>
                            <dd class="font-display text-xl font-bold text-ink-strong">
                                {{ number_format($row['summary']['followers'], 0, ',', '.') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase tracking-[0.06em] text-ink-muted">
                                {{ $row['per_kanal'] !== [] ? 'Engagement (gabungan)' : 'Engagement' }}
                            </dt>
                            <dd class="font-display text-xl font-bold text-ink-strong">
                                {{ number_format($row['summary']['engagement_rate'], 2, ',', '.') }}%
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-4 mb-2 text-[10px] uppercase tracking-[0.06em] text-ink-muted">Profil usia</p>
                    <ul class="space-y-1.5">
                        @foreach ($row['age'] as $group => $count)
                            <li class="flex items-center gap-2">
                                <span class="w-12 shrink-0 font-mono text-[10px] text-ink-muted">{{ $group }}</span>
                                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-sunken">
                                    <span class="block h-full rounded-full bg-brand-500" style="width: {{ round($count / $ageMax * 100, 1) }}%"></span>
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 mb-2 text-[10px] uppercase tracking-[0.06em] text-ink-muted">Gender</p>
                    <div class="flex h-2 overflow-hidden rounded-full bg-surface-sunken">
                        <div class="bg-brand-bright" style="width: {{ round($row['gender']['F'] / $genderKnown * 100, 1) }}%"></div>
                        <div class="bg-brand-500" style="width: {{ round($row['gender']['M'] / $genderKnown * 100, 1) }}%"></div>
                    </div>
                    <p class="mt-1 font-mono text-[10px] text-ink-muted">
                        ♀ {{ number_format($row['gender']['F'] / $genderKnown * 100, 0) }}% ·
                        ♂ {{ number_format($row['gender']['M'] / $genderKnown * 100, 0) }}%
                    </p>

                    <p class="mt-4 mb-2 text-[10px] uppercase tracking-[0.06em] text-ink-muted">Wilayah teratas</p>
                    <ul class="space-y-1 font-mono text-[11px] text-ink">
                        @forelse ($row['cities'] as $city => $count)
                            <li class="flex justify-between gap-2">
                                <span class="truncate">{{ $city }}</span>
                                <span class="shrink-0 text-ink-muted">{{ number_format($count, 0, ',', '.') }}</span>
                            </li>
                        @empty
                            <li class="text-ink-muted">—</li>
                        @endforelse
                    </ul>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
