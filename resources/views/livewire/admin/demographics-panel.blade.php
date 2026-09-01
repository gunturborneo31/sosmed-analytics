<div class="space-y-6">
    <x-page-header
        title="Demografi Audiens"
        description="Siapa sebenarnya warga yang terjangkau komunikasi pemerintah daerah."
    >
        <x-slot:actions>
            @if ($this->snapshotDate)
                <x-badge tone="neutral" class="font-mono">
                    data per {{ \Illuminate\Support\Carbon::parse($this->snapshotDate)->translatedFormat('j M Y') }}
                </x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Tanpa saringan jenis OPD: halaman ini tidak punya pemilih OPD sebagai
         pasangannya, dan dua saringan yang bekerja bersamaan membingungkan. --}}
    <x-filter-bar :period="$period" :platforms="$platforms" :show-unit-type="false" />

    @if ($this->snapshotDate === null)
        <x-card>
            <x-empty-state
                :title="$this->isWebsitePlatform() ? 'Belum ada data demografi untuk '. $this->websitePlatformLabel() : 'Belum ada data demografi'"
                :description="$this->isWebsitePlatform()
                    ? $this->websiteAgeRangeLabel() . ' Saat ini belum ada artikel dengan view_count yang tersimpan untuk kategori '. strtolower($this->websitePlatformLabel()) .'. Jalankan scrape di halaman terkait agar data website bisa muncul di sini.'
                    : 'Demografi ditarik dari izin instagram_manage_insights. Data akan muncul setelah minimal satu akun terhubung dan sinkronisasi pertama selesai.'"
            >
                @unless ($this->isWebsitePlatform())
                    <x-slot:action>
                        <x-button :href="route('admin.units', ['status' => 'belum'])" variant="secondary">
                            Lihat OPD yang belum terhubung
                        </x-button>
                    </x-slot:action>
                @endunless
            </x-empty-state>
        </x-card>
    @else
        {{-- ───────── Per kanal ─────────
             Usia pengikut Instagram condong lebih muda daripada Facebook.
             Menggabungkannya sejak awal menutupi perbedaan itu, padahal justru
             itu yang menentukan kanal mana dipakai untuk pesan yang mana. --}}
        @foreach ($this->platforms as $kanal)
            <section wire:key="demografi-{{ $kanal }}" class="space-y-4">
                <div class="flex items-center gap-3">
                    <x-platform-badge :platform="$kanal" size="size-8" />
                    <h2 class="font-display text-base font-semibold {{ \App\Support\SocialPlatform::textClass($kanal) }}">
                        {{ \App\Support\SocialPlatform::label($kanal) }}
                    </h2>
                </div>

                <div class="grid gap-6 @if ($this->isWebsitePlatformFor($kanal)) lg:grid-cols-1 @else lg:grid-cols-[1.5fr_1fr] @endif">
                    <livewire:admin.age-spectrum
                        :period="$period" :from="$from" :until="$until"
                        unit-type="" :platform="$kanal"
                        :key="'usia-'.$kanal.$period.$from.$until"
                    />

                    @unless ($this->isWebsitePlatformFor($kanal))
                        <livewire:admin.gender-ratio
                            :period="$period" :from="$from" :until="$until"
                            unit-type="" :platform="$kanal"
                            :key="'gender-'.$kanal.$period.$from.$until"
                        />
                    @endunless
                </div>

                <livewire:admin.region-distribution
                    :period="$period" :from="$from" :until="$until"
                    unit-type="" :platform="$kanal"
                    :key="'wilayah-'.$kanal.$period.$from.$until"
                />
            </section>
        @endforeach

        {{-- ───────── Gabungan ─────────
             Ditaruh setelah rincian per kanal, sama seperti Dashboard dan
             Insight, supaya perbedaan antar kanal terbaca lebih dulu. --}}
        <section class="space-y-4 @if ($this->platforms !== []) rounded-2xl border border-hairline bg-surface-sunken/50 p-5 @endif">
            @if ($this->platforms !== [])
                <div>
                    <h2 class="font-display text-base font-semibold text-ink-strong">Gabungan Seluruh Kanal</h2>
                    <p class="text-[11px] text-ink-muted">
                        Penjumlahan Instagram dan Facebook. Warga yang mengikuti kedua kanal terhitung dua kali.
                    </p>
                </div>
            @endif

            <div class="grid gap-6 @if ($this->isWebsitePlatform()) lg:grid-cols-1 @else lg:grid-cols-[1.5fr_1fr] @endif">
                <livewire:admin.age-spectrum
                    :period="$period" :from="$from" :until="$until"
                    unit-type="" :platform="$platform"
                    :key="'usia-gabungan-'.$period.$platform.$from.$until"
                />

                @unless ($this->isWebsitePlatform())
                    <livewire:admin.gender-ratio
                        :period="$period" :from="$from" :until="$until"
                        unit-type="" :platform="$platform"
                        :key="'gender-gabungan-'.$period.$platform.$from.$until"
                    />
                @endunless
            </div>

            <livewire:admin.region-distribution
                :period="$period" :from="$from" :until="$until"
                unit-type="" :platform="$platform"
                :key="'wilayah-gabungan-'.$period.$platform.$from.$until"
            />
        </section>

        <p class="text-xs leading-relaxed text-ink-muted">
            Angka demografi bersifat <span class="font-mono">lifetime</span> di Meta — mencerminkan
            komposisi pengikut saat data ditarik, bukan akumulasi periode yang dipilih. Filter periode
            di atas menentukan tanggal snapshot mana yang dipakai.
        </p>
    @endif
</div>
