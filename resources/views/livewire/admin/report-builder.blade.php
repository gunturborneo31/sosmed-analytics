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
        subtitle="Tiga langkah — atur cakupan, pilih perangkat daerah, lalu unduh berkasnya"
    >
        <div class="space-y-7">
            {{-- Langkah 1 --}}
            <section>
                <x-step-heading number="1" title="Atur cakupan laporan">
                    Menentukan rentang tanggal dan jenis data yang ikut dihitung.
                </x-step-heading>

                <div class="mt-3.5 flex flex-wrap items-end gap-3">
                    <x-select label="Periode" wire:model.live="period" :options="\App\Support\Period::OPTIONS" class="min-w-44" />

                    @if ($period === 'custom')
                        <label class="space-y-1.5">
                            <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Dari</span>
                            <input type="date" wire:model.live="from" class="h-11 rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                        </label>
                        <label class="space-y-1.5">
                            <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Sampai</span>
                            <input type="date" wire:model.live="until" class="h-11 rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                        </label>
                    @endif

                    {{-- "Jenis OPD" sengaja tidak ada di sini: menyaring dua kali —
                         sekali lewat jenis, sekali lewat daftar centang di langkah 2 —
                         membingungkan dan gampang menghasilkan rekap kosong. Pembatasan
                         perangkat daerah cukup lewat langkah 2. --}}
                    <x-select label="Platform" wire:model.live="platform" :options="$platforms" class="min-w-40" />

                    <x-button variant="ghost" wire:click="resetFilters" class="ml-auto">Atur ulang semua</x-button>
                </div>
            </section>

            {{-- Langkah 2 --}}
            <section class="border-t border-hairline pt-6">
                <x-step-heading number="2" title="Pilih perangkat daerah">
                    Boleh memilih lebih dari satu.
                </x-step-heading>

                {{-- Penjelasan ditulis sebagai kalimat utuh berukuran normal.
                     Sebelumnya hanya keterangan kecil di bawah daftar, dan
                     hampir tidak terbaca. --}}
                <div class="mt-3.5 rounded-xl border border-brand-100 bg-brand-50/50 px-4 py-3.5">
                    <p class="text-sm leading-relaxed text-ink">
                        <strong class="font-semibold text-ink-strong">Tidak memilih apa pun berarti seluruh kabupaten.</strong>
                        Berkas yang diunduh akan memuat semua perangkat daerah yang aktif.
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-ink">
                        Centang beberapa perangkat daerah bila laporan hanya perlu memuat mereka — misalnya satu dinas
                        untuk bahan rapat, atau beberapa kecamatan sekaligus untuk dibandingkan berdampingan.
                        Pratinjau dan berkas unduhan selalu mengikuti persis pilihan di sini.
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                    <label class="relative min-w-56 flex-1">
                        <span class="sr-only">Cari perangkat daerah</span>
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                        </svg>
                        <input
                            type="search"
                            placeholder="Cari nama perangkat daerah…"
                            wire:model.live.debounce.300ms="unitSearch"
                            class="h-11 w-full rounded-xl border border-hairline bg-surface pl-10 pr-3.5 text-sm text-ink-strong placeholder:text-ink-muted focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12"
                        >
                    </label>

                    <x-button variant="secondary" size="md" wire:click="selectAllUnits">
                        Pilih semua ({{ $this->selectableUnits->count() }})
                    </x-button>

                    {{-- Dipasang lewat atribut terikat, bukan @disabled: direktif Blade
                         di dalam tag komponen membuat tag pembukanya tidak ikut
                         dikompilasi, sehingga pasangan buka/tutupnya pecah. --}}
                    <x-button
                        variant="ghost"
                        size="md"
                        wire:click="clearUnits"
                        :disabled="$units === []"
                    >
                        Kosongkan pilihan
                    </x-button>
                </div>

                <div class="mt-3 max-h-60 overflow-y-auto rounded-xl border border-hairline p-3">
                    @if ($this->selectableUnits->isEmpty())
                        <p class="px-1 py-6 text-center text-sm text-ink-muted">
                            Tidak ada perangkat daerah yang cocok dengan pencarian ini.
                        </p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->selectableUnits as $unit)
                                @php $terpilih = in_array($unit->id, $units, true); @endphp
                                <label
                                    wire:key="rekap-{{ $unit->id }}"
                                    @class([
                                        'inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition',
                                        'border-brand-500 bg-brand-50 text-brand-700' => $terpilih,
                                        'border-hairline text-ink hover:bg-surface-sunken' => ! $terpilih,
                                    ])
                                >
                                    <input type="checkbox" value="{{ $unit->id }}" wire:model.live="units" class="sr-only">

                                    <svg class="size-3.5 shrink-0 {{ $terpilih ? 'text-brand-600' : 'text-ink-muted/50' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        @if ($terpilih)
                                            <path d="m5 12.5 4.5 4.5L19 7"/>
                                        @else
                                            <circle cx="12" cy="12" r="8.5" stroke-width="1.6"/>
                                        @endif
                                    </svg>

                                    {{ $unit->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- Langkah 3 --}}
            <section class="border-t border-hairline pt-6">
                <x-step-heading number="3" title="Unduh berkasnya">
                    Periksa sekali lagi isi laporan sebelum diunduh.
                </x-step-heading>

                @php
                    $namaTerpilih = $this->selectedUnitNames;
                    $ditampilkan = $namaTerpilih->take(6);
                    $sisa = $namaTerpilih->count() - $ditampilkan->count();
                @endphp

                <div class="mt-3.5 rounded-xl border border-hairline bg-surface-sunken p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.06em] text-ink-muted">Isi berkas nanti</p>

                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                            <dt class="w-36 shrink-0 text-ink-muted">Periode</dt>
                            <dd class="font-medium text-ink-strong">{{ $this->period()->label() }}</dd>
                        </div>

                        <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                            <dt class="w-36 shrink-0 text-ink-muted">Platform</dt>
                            <dd class="font-medium text-ink-strong">{{ $platforms[$platform] }}</dd>
                        </div>

                        <div class="flex flex-wrap gap-x-2 gap-y-1">
                            <dt class="w-36 shrink-0 text-ink-muted">Perangkat daerah</dt>
                            <dd class="min-w-0 flex-1 font-medium text-ink-strong">
                                @if ($namaTerpilih->isEmpty())
                                    Seluruh kabupaten
                                    <span class="font-normal text-ink-muted">
                                        — semua perangkat daerah aktif
                                    </span>
                                @else
                                    {{ $namaTerpilih->count() }} perangkat daerah
                                    <span class="mt-1 block font-normal leading-relaxed text-ink">
                                        {{ $ditampilkan->join(', ') }}{{ $sisa > 0 ? ', dan '.$sisa.' lainnya' : '' }}
                                    </span>
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

    <x-card title="Pratinjau Rekap" :subtitle="'Isi berkas · '.$this->period()->label()">
        <dl class="mb-6 grid gap-4 sm:grid-cols-4">
            @foreach ([
                'Akun terhubung' => $this->summary['accounts_connected'],
                'Total pengikut' => number_format($this->summary['followers'], 0, ',', '.'),
                'Jangkauan' => number_format($this->summary['reach'], 0, ',', '.'),
                'Rerata engagement' => number_format($this->summary['engagement_rate'], 2, ',', '.').'%',
            ] as $label => $value)
                <div class="rounded-xl bg-surface-sunken p-3">
                    <dt class="text-[10px] uppercase tracking-[0.06em] text-ink-muted">{{ $label }}</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold text-ink-strong">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="overflow-x-auto rounded-xl border border-hairline">
            <table class="w-full min-w-2xl text-sm">
                <thead class="bg-surface-sunken">
                    <tr class="text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                        <th class="px-4 py-2.5 font-medium">Perangkat Daerah</th>
                        <th class="px-4 py-2.5 text-right font-medium">Pengikut</th>
                        <th class="px-4 py-2.5 text-right font-medium">Δ</th>
                        <th class="px-4 py-2.5 text-right font-medium">Jangkauan</th>
                        <th class="px-4 py-2.5 text-right font-medium">Engagement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->preview as $row)
                        <tr wire:key="pratinjau-{{ $row->unit_id }}">
                            <td class="px-4 py-2.5 text-ink-strong">{{ $row->unit_name }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ number_format($row->followers, 0, ',', '.') }}</td>
                            <td @class([
                                'px-4 py-2.5 text-right font-mono',
                                'text-success' => $row->growth > 0,
                                'text-danger' => $row->growth < 0,
                                'text-ink-muted' => $row->growth === null,
                            ])>
                                @if ($row->growth === null)
                                    <span title="Periode pembanding belum punya data">&mdash;</span>
                                @else
                                    {{ $row->growth > 0 ? '+' : '' }}{{ number_format($row->growth, 1, ',', '.') }}%
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ number_format($row->reach, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ number_format($row->engagement_rate, 2, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="Tidak ada data untuk kombinasi filter ini" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-sm text-ink-muted">
            Tabel ini menampilkan 10 baris teratas sebagai contoh. Berkas yang diunduh berisi seluruh perangkat
            daerah yang sesuai pilihan di atas.
        </p>
    </x-card>

    {{-- Rincian: dari mana pembilangnya, dan apa yang tidak ikut dihitung. --}}
    <x-card title="Rincian Pembilang" subtitle="Pengikut berusia 16–64 tahun yang jadi sasaran penyebaran informasi publik">
        @php $tersimpan = $this->savedPopulation; @endphp

        <div class="mb-5">
            <div class="flex flex-wrap items-end gap-3">
                <label class="block max-w-xs flex-1 space-y-1.5">
                    <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">
                        Jumlah penduduk (penyebut)
                    </span>
                    <input
                        type="number" min="1" inputmode="numeric"
                        wire:model.live.debounce.500ms="population"
                        class="h-11 w-full rounded-xl border border-hairline bg-surface px-3.5 font-mono text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12"
                    >
                </label>

                @can('manage-organizational-units')
                    <x-button
                        variant="secondary"
                        wire:click="savePopulation"
                        wire:loading.attr="disabled"
                        :disabled="(int) $population === $tersimpan"
                    >
                        Simpan sebagai nilai tetap
                    </x-button>
                @endcan
            </div>

            {{-- Angka di kolom ini bisa diubah siapa saja untuk mencoba-coba, tapi
                 yang berlaku bagi admin lain hanya yang sudah disimpan. Bedanya
                 harus terlihat, kalau tidak admin mengira angkanya sudah berlaku. --}}
            <p class="mt-2 max-w-xl text-xs leading-relaxed text-ink-muted">
                @if ((int) $population === $tersimpan)
                    Nilai tersimpan dan berlaku untuk seluruh admin.
                    Sesuaikan dengan data BPS/Dukcapil terbaru.
                @else
                    Sedang mencoba <span class="font-mono text-ink-strong">{{ number_format((int) $population, 0, ',', '.') }}</span>.
                    Nilai yang tersimpan masih <span class="font-mono text-ink-strong">{{ number_format($tersimpan, 0, ',', '.') }}</span>
                    @can('manage-organizational-units')
                        — tekan <span class="font-medium text-ink">Simpan sebagai nilai tetap</span> bila angka baru ini yang benar.
                    @else
                        , dan hanya admin yang berwenang bisa mengubahnya.
                    @endcan
                @endif
            </p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-hairline">
            <table class="w-full min-w-lg text-sm">
                <thead class="bg-surface-sunken">
                    <tr class="text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                        <th class="px-4 py-2.5 font-medium">Kelompok Usia</th>
                        <th class="px-4 py-2.5 text-right font-medium">Pengikut</th>
                        <th class="px-4 py-2.5 font-medium">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach ($this->ikk['rincian_usia'] as $baris)
                        <tr wire:key="usia-{{ $baris['kelompok'] }}">
                            <td class="px-4 py-2.5 font-mono text-ink-strong">{{ $baris['kelompok'] }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-ink-strong">
                                {{ number_format($baris['jumlah'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($baris['perkiraan'])
                                    <x-badge tone="warning">Perkiraan</x-badge>
                                    <span class="mt-1 block text-[11px] leading-snug text-ink-muted">
                                        {{ $baris['alasan'] }}
                                    </span>
                                @else
                                    <x-badge tone="success">Data langsung</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-hairline bg-surface-sunken">
                        <td class="px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.06em] text-ink-muted">
                            Pembilang
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold text-ink-strong">
                            {{ number_format($this->ikk['pembilang'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-[11px] text-ink-muted">
                            {{ number_format($this->ikk['tidak_dihitung'], 0, ',', '.') }} pengikut di luar usia sasaran
                            tidak ikut dihitung
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Batas metodologis yang wajib diketahui sebelum angka ini dipakai sebagai
             capaian kinerja resmi. Ditata sebagai tiga kartu berdampingan, bukan satu
             paragraf padat — baris pendek dan renggang jauh lebih mudah dipindai
             daripada teks rata kanan-kiri yang rapat. --}}
        <div class="mt-5 rounded-xl border border-warning/25 bg-warning/5 p-3.5">
            <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.06em] text-warning">
                <x-icon name="peringatan" class="size-3" />
                Yang perlu diketahui sebelum angka ini dilaporkan
            </p>

            <div class="mt-2.5 grid gap-2.5 sm:grid-cols-3">
                <div class="rounded-lg border border-warning/20 bg-surface px-3 py-2.5">
                    <p class="text-[11px] font-semibold text-ink-strong">Bisa terhitung ganda</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-ink-muted">
                        Warga yang mengikuti lebih dari satu akun OPD terhitung berkali-kali — Meta tidak
                        menyediakan cara mengenali pengikut yang sama di dua akun berbeda.
                    </p>
                </div>

                <div class="rounded-lg border border-warning/20 bg-surface px-3 py-2.5">
                    <p class="text-[11px] font-semibold text-ink-strong">Hanya satu dari enam kanal</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-ink-muted">
                        Definisi IKK mencakup enam kanal Media Komunikasi Publik (cetak, penyiaran, online,
                        media sosial, luar ruang, tatap muka). Aplikasi ini hanya mengukur media sosial.
                    </p>
                </div>

                <div class="rounded-lg border border-warning/20 bg-surface px-3 py-2.5">
                    <p class="text-[11px] font-semibold text-ink-strong">Estimasi, bukan survei</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-ink-muted">
                        IKK resmi diukur lewat survei. Angka di sini adalah estimasi pendukung dari data
                        media sosial, bukan pengganti hasil survei.
                    </p>
                </div>
            </div>
        </div>

        @if ($this->ikk['melampaui_penduduk'])
            <div class="mt-3 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-xs leading-relaxed text-danger">
                <strong class="font-semibold">Pembilang melampaui jumlah penduduk.</strong>
                Ini tanda kuat bahwa penghitungan ganda lintas akun sudah signifikan, sehingga persentase di atas
                tidak layak disajikan apa adanya sebagai capaian. Periksa kembali angka penduduk, atau gunakan hasil
                survei sebagai rujukan utama.
            </div>
        @endif
    </x-card>
</div>
