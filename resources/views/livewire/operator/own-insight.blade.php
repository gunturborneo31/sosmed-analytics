<div class="space-y-6">
    <x-page-header
        title="Insight Akun"
        :description="auth()->user()->organizationalUnit?->name ?? 'Perangkat daerah belum ditentukan'"
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-end gap-2">
                <x-select wire:model.live="period" :options="\App\Support\Period::OPTIONS" compact class="min-w-44" />

                {{-- Kolom tanggal hanya muncul saat dibutuhkan. Sebelumnya
                     "Rentang kustom" bisa dipilih tanpa ada kolomnya sama sekali,
                     sehingga halaman langsung 500. --}}
                @if ($period === 'custom')
                    <label class="space-y-1">
                        <span class="block text-[10px] font-medium uppercase tracking-[0.06em] text-ink-muted">Dari</span>
                        <input type="date" wire:model.live="from" max="{{ now()->toDateString() }}"
                               class="h-9 rounded-xl border border-hairline bg-surface px-2.5 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                    </label>
                    <label class="space-y-1">
                        <span class="block text-[10px] font-medium uppercase tracking-[0.06em] text-ink-muted">Sampai</span>
                        <input type="date" wire:model.live="until" max="{{ now()->toDateString() }}"
                               class="h-9 rounded-xl border border-hairline bg-surface px-2.5 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                    </label>
                @endif
            </div>
        </x-slot:actions>
    </x-page-header>

    @if ($this->platforms === [])
        <x-card>
            <x-empty-state
                title="Belum ada akun terhubung"
                description="Hubungkan akun instansimu lebih dulu, lalu insight akan muncul di sini setelah sinkronisasi pertama."
            >
                <x-slot:action>
                    <x-button :href="route('operator.accounts')">Ke halaman akun</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        {{-- ───────── Per kanal ─────────
             Instagram dan Facebook punya perilaku audiens yang berbeda.
             Menjumlahkannya sejak awal menyembunyikan kanal mana yang
             sebenarnya bekerja, jadi masing-masing berdiri sendiri lebih dulu. --}}
        @foreach ($this->platforms as $platform)
            @php
                $ringkas = $this->summaryFor($platform);
                $usia = $this->ageProfileFor($platform);
                $gender = $this->genderSplitFor($platform);
                $warna = \App\Support\SocialPlatform::textClass($platform);
            @endphp

            <section wire:key="kanal-{{ $platform }}" class="space-y-4">
                <div class="flex items-center gap-3">
                    <x-platform-badge :platform="$platform" size="size-9" />
                    <div>
                        <h2 class="font-display text-lg font-semibold {{ $warna }}">
                            {{ \App\Support\SocialPlatform::label($platform) }}
                        </h2>
                        <p class="text-xs text-ink-muted">
                            {{ $ringkas['accounts_connected'] }} akun terhubung · {{ $this->period()->label() }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-stat-tile :index="0" label="Pengikut" value="" :numeric="$ringkas['followers']"
                                 :delta="$ringkas['followers_delta']" show-delta caption="vs periode lalu" />
                    <x-stat-tile :index="1" label="Jangkauan" value="" :numeric="$ringkas['reach']"
                                 :delta="$ringkas['reach_delta']" show-delta caption="akumulasi periode" />
                    <x-stat-tile :index="2" label="Engagement"
                                 :value="number_format($ringkas['engagement_rate'], 2, ',', '.').'%'"
                                 caption="interaksi dibagi pengikut" />
                </div>

                <x-card title="Tren Jangkauan" :subtitle="\App\Support\SocialPlatform::label($platform)">
                    <x-chart :id="'tren-'.$platform" :options="$this->trendChartFor($platform)" :height="260" />
                </x-card>

                <div class="grid gap-4 lg:grid-cols-2">
                    <x-card title="Usia Pengikut" subtitle="Usia 16–64 tahun">
                        <x-age-bars :profile="$usia" />
                    </x-card>

                    <x-card title="Gender Pengikut">
                        <x-gender-split :split="$gender" />
                    </x-card>
                </div>
            </section>
        @endforeach

        {{-- ───────── Gabungan ─────────
             Sengaja paling bawah: angka gabungan berguna untuk laporan, tapi
             kalau ditaruh di atas ia menutupi perbedaan antar kanal yang justru
             jadi bahan keputusan sehari-hari. --}}
        @if (count($this->platforms) > 1)
            <section class="space-y-4 rounded-2xl border border-hairline bg-surface-sunken/50 p-5">
                <div>
                    <h2 class="font-display text-lg font-semibold text-ink-strong">Gabungan Seluruh Kanal</h2>
                    <p class="text-xs text-ink-muted">
                        Penjumlahan Instagram dan Facebook. Warga yang mengikuti kedua kanal terhitung dua kali.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
                    <x-stat-tile :index="0" label="Akun Terhubung" :value="(string) $this->summary['accounts_connected']" />
                    <x-stat-tile :index="1" label="Total Pengikut" value="" :numeric="$this->summary['followers']"
                                 :delta="$this->summary['followers_delta']" show-delta caption="vs periode lalu" />
                    <x-stat-tile :index="2" label="Total Jangkauan" value="" :numeric="$this->summary['reach']"
                                 :delta="$this->summary['reach_delta']" show-delta caption="akumulasi periode" />
                    <x-stat-tile :index="3" label="Rerata Engagement"
                                 :value="number_format($this->summary['engagement_rate'], 2, ',', '.').'%'"
                                 caption="rata-rata seluruh akun" />
                </div>

                <x-card title="Tren Jangkauan Gabungan" :subtitle="$this->period()->label()">
                    <x-chart id="tren-sendiri" :options="$this->trendChart" :height="260" />
                </x-card>

                <div class="grid gap-4 lg:grid-cols-2">
                    <x-card title="Usia Pengikut" subtitle="Seluruh kanal · usia 16–64 tahun">
                        <x-age-bars :profile="$this->ageProfile" />
                    </x-card>

                    <x-card title="Gender Pengikut" subtitle="Seluruh kanal">
                        <x-gender-split :split="$this->genderSplit" />
                    </x-card>
                </div>
            </section>
        @endif
    @endif
</div>
