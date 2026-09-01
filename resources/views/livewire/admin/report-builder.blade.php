<div class="space-y-6">
    <x-page-header
        title="Rekap & Laporan"
        description="Susun rekap sesuai kebutuhan, lalu unduh sebagai Excel atau PDF siap cetak."
    />

    {{-- Rumus IKK disematkan paling atas — jadi acuan pertama yang dibaca
         sebelum melihat angka apa pun di bawahnya. --}}
    <x-ikk-formula :ikk="$this->ikk" />

    {{-- Satu kartu berisi seluruh proses menyusun laporan: mengatur saringan,
         memilih perangkat daerah, sampai menekan tombol unduh. Sebelumnya
         ketiganya terpisah di kartu berbeda, sehingga admin memilih OPD di satu
         tempat lalu harus mencari tombol unduhnya di tempat lain. --}}
    <x-card
        title="Susun Laporan"
        subtitle="Dua langkah — atur cakupan, lalu unduh berkasnya"
    >
        <div class="space-y-7">
            {{-- Langkah 1 --}}
            <section>
                <x-step-heading number="1" title="Cakupan laporan">
                    Menentukan rentang tanggal dan jenis data yang ikut dihitung.
                </x-step-heading>

                @php
                    $allCalculations = $this->selectedPlatformCalculations();
                    $platformCalculations = collect($allCalculations)->reject(fn (array $calculation): bool => $calculation['platform'] === 'all')->values();
                    $aggregateCalculation = collect($allCalculations)->firstWhere('platform', 'all');
                @endphp

                <div class="mt-4 rounded-2xl border border-brand-100 bg-brand-50/70 p-4">

                    <div class="mt-3 grid gap-3 grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
                       @foreach ($platformCalculations as $calculation)
                           <div class="rounded-xl border border-brand-100 bg-surface px-3 py-2.5 text-center font-mono text-xs">
                               <p class="mb-1 text-left text-[10px] font-sans font-semibold uppercase tracking-[0.08em] text-brand-700">
                                   {{ $calculation['label'] }}
                               </p>
                               <div class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-0.5">
                                   <span class="text-ink-strong">{{ number_format($calculation['pembilang'], 0, ',', '.') }}</span>
                                   <span class="text-ink-muted">÷</span>
                                   <span class="text-ink-strong">{{ number_format($calculation['penyebut'], 0, ',', '.') }}</span>
                                   <span class="text-ink-muted">× 100%</span>
                               </div>
                               <div class="mt-1 text-base font-display font-bold text-brand-700">
                                   {{ number_format($calculation['persentase'], 2, ',', '.') }}%
                               </div>
                           </div>
                       @endforeach
                    </div>

                    @if ($aggregateCalculation)
                       <div class="mt-4">
                           <div class="w-full rounded-xl border border-brand-200 bg-brand-100/70 px-4 py-3 text-center font-mono text-sm shadow-sm">
                               <p class="mb-1 text-left text-[10px] font-sans font-semibold uppercase tracking-[0.08em] text-brand-700">
                                   {{ $aggregateCalculation['label'] }}
                               </p>
                               <div class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-0.5">
                                   <span class="text-ink-strong">{{ number_format($aggregateCalculation['pembilang'], 0, ',', '.') }}</span>
                                   <span class="text-ink-muted">÷</span>
                                   <span class="text-ink-strong">{{ number_format($aggregateCalculation['penyebut'], 0, ',', '.') }}</span>
                                   <span class="text-ink-muted">× 100%</span>
                               </div>
                               <div class="mt-1 text-xl font-display font-bold text-brand-700">
                                   {{ number_format($aggregateCalculation['persentase'], 2, ',', '.') }}%
                               </div>
                           </div>
                       </div>
                    @endif

                    @php
                       $audienceSummaries = $this->platformAudienceSummaries;
                    @endphp

                    <div class="mt-6 rounded-2xl border border-hairline bg-surface p-4">
                       <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand-700">Rekapan demografi Instagram &amp; Facebook</p>

                       <div class="mt-4 grid gap-4 xl:grid-cols-3">
                           @foreach ($audienceSummaries as $summary)
                               <div class="rounded-xl border border-hairline bg-surface-sunken p-3.5">
                                   <p class="text-sm font-semibold text-ink-strong">{{ $summary['label'] }}</p>

                                   <div class="mt-3 space-y-3">
                                       <div>
                                           <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Rentang umur</p>
                                           <dl class="mt-2 space-y-1.5">
                                               @foreach ($summary['age'] as $age)
                                                   <div class="flex items-center justify-between gap-3 text-xs">
                                                       <dt class="text-ink-muted">{{ $age['label'] }}</dt>
                                                       <dd class="font-mono text-ink-strong">{{ number_format($age['count'], 0, ',', '.') }}</dd>
                                                   </div>
                                               @endforeach
                                           </dl>
                                       </div>

                                       <div>
                                           <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Jenis kelamin</p>
                                           <dl class="mt-2 space-y-1.5">
                                               @foreach ($summary['gender'] as $gender)
                                                   <div class="flex items-center justify-between gap-3 text-xs">
                                                       <dt class="text-ink-muted">{{ $gender['label'] }}</dt>
                                                       <dd class="font-mono text-ink-strong">
                                                           {{ number_format($gender['count'], 0, ',', '.') }}
                                                           <span class="text-ink-muted">({{ number_format($gender['percent'], 1, ',', '.') }}%)</span>
                                                       </dd>
                                                   </div>
                                               @endforeach
                                           </dl>
                                       </div>
                                   </div>
                               </div>
                           @endforeach
                       </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50/60 p-4">
                       <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand-700">Perhitungan per rentang umur dari akumulasi</p>

                       <div class="mt-4 grid gap-3 grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
                           @foreach ($this->combinedAgeCalculations as $ageCalc)
                               <div class="rounded-xl border border-brand-100 bg-surface px-3 py-2.5 text-center font-mono text-xs">
                                   <p class="mb-1 text-left text-[10px] font-sans font-semibold uppercase tracking-[0.08em] text-brand-700">
                                       {{ $ageCalc['label'] }}
                                   </p>
                                   <div class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-0.5">
                                       <span class="text-ink-strong">{{ number_format($ageCalc['count'], 0, ',', '.') }}</span>
                                       <span class="text-ink-muted">÷</span>
                                       <span class="text-ink-strong">{{ number_format($ageCalc['penyebut'], 0, ',', '.') }}</span>
                                       <span class="text-ink-muted">× 100%</span>
                                   </div>
                                   <div class="mt-1 text-base font-display font-bold text-brand-700">
                                       {{ number_format($ageCalc['persentase'], 2, ',', '.') }}%
                                   </div>
                               </div>
                           @endforeach
                       </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-hairline bg-surface p-4">
                       <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand-700">Rekapan demografi Website OPD &amp; Website Media Partner</p>

                       <div class="mt-4 grid gap-4 xl:grid-cols-3">
                           @foreach ($this->websiteAudienceSummaries as $summary)
                               <div class="rounded-xl border border-hairline bg-surface-sunken p-3.5">
                                   <p class="text-sm font-semibold text-ink-strong">{{ $summary['label'] }}</p>

                                   <div class="mt-3 space-y-3">
                                       <div>
                                           <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Rentang umur</p>
                                           <dl class="mt-2 space-y-1.5">
                                               @foreach ($summary['age'] as $age)
                                                   <div class="flex items-center justify-between gap-3 text-xs">
                                                       <dt class="text-ink-muted">{{ $age['label'] }}</dt>
                                                       <dd class="font-mono text-ink-strong">{{ number_format($age['count'], 0, ',', '.') }}</dd>
                                                   </div>
                                               @endforeach
                                           </dl>
                                       </div>
                                   </div>
                               </div>
                           @endforeach
                       </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50/60 p-4">
                       <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand-700">Perhitungan dari akumulasi</p>

                       @php
                           $websiteAggregate = collect($this->websiteAudienceSummaries)->firstWhere('platform', 'combined');
                           $websiteTotal = $websiteAggregate['age'][0]['count'] ?? 0;
                           $websitePenyebut = $this->population > 0 ? $this->population : 1;
                           $websitePersentase = $websitePenyebut > 0 ? round(($websiteTotal / $websitePenyebut) * 100, 2) : 0.0;
                       @endphp

                       <div class="mt-4">
                           <div class="w-full rounded-xl border border-brand-200 bg-brand-100/70 px-4 py-3 text-center font-mono text-sm shadow-sm">
                               <p class="mb-1 text-left text-[10px] font-sans font-semibold uppercase tracking-[0.08em] text-brand-700">
                                   Akumulasi Website OPD + Website Media Partner
                               </p>
                               <div class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-0.5">
                                   <span class="text-ink-strong">{{ number_format($websiteTotal, 0, ',', '.') }}</span>
                                   <span class="text-ink-muted">÷</span>
                                   <span class="text-ink-strong">{{ number_format($websitePenyebut, 0, ',', '.') }}</span>
                                   <span class="text-ink-muted">× 100%</span>
                               </div>
                               <div class="mt-1 text-xl font-display font-bold text-brand-700">
                                   {{ number_format($websitePersentase, 2, ',', '.') }}%
                               </div>
                           </div>
                       </div>
                    </div>

                    <p class="mt-3 text-xs leading-relaxed text-ink">
                       Pembilang adalah jumlah warga usia 16–64 tahun yang terpilih pada platform yang aktif.
                       Penyebut adalah total penduduk kabupaten berdasarkan data yang berlaku.
                    </p>
                </div>

            </section>

            {{-- Langkah 2 dilepas sesuai permintaan: tidak ada pemilihan perangkat daerah lagi. --}}

            {{-- Langkah 3 --}}
            <section class="border-t border-hairline pt-6">
                <x-step-heading number="3" title="Unduh berkasnya">
                    Periksa sekali lagi isi laporan sebelum diunduh.
                </x-step-heading>

                @php
                    $platformDipilih = $this->selectedPlatforms !== [] ? $this->selectedPlatforms : [
                        \App\Models\SocialAccount::PLATFORM_INSTAGRAM,
                        \App\Models\SocialAccount::PLATFORM_FACEBOOK,
                    ];
                @endphp

                <div class="mt-3.5 rounded-xl border border-hairline bg-surface-sunken p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.06em] text-ink-muted">Isi berkas nanti</p>

                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                            <dt class="w-36 shrink-0 text-ink-muted">Cakupan</dt>
                            <dd class="font-medium text-ink-strong">
                                @if ($this->units === [])
                                    Seluruh kabupaten
                                @else
                                    {{ $this->selectedUnitNames->implode(', ') ?: 'Perangkat daerah terpilih' }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                @can('export-report')
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <x-button wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf,exportExcel" size="lg">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                            </svg>
                            <span wire:loading.remove wire:target="exportPdf">Unduh PDF</span>
                            <span wire:loading wire:target="exportPdf">Menyiapkan berkas…</span>
                        </x-button>

                        <x-button variant="secondary" size="lg" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportPdf,exportExcel">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                            </svg>
                            <span wire:loading.remove wire:target="exportExcel">Unduh Excel</span>
                            <span wire:loading wire:target="exportExcel">Menyiapkan berkas…</span>
                        </x-button>

                        <p class="text-xs text-ink-muted">
                            PDF untuk dicetak dan ditandatangani; Excel bila angkanya masih akan diolah lagi.
                        </p>
                    </div>
                @else
                    <p class="mt-4 rounded-xl border border-hairline bg-surface-sunken px-4 py-3 text-sm text-ink-muted">
                        Akun Anda tidak memiliki izin mengunduh laporan.
                    </p>
                @endcan
            </section>
        </div>
    </x-card>

</div>
