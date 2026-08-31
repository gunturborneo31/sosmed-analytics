@props(['ikk' => null])

{{-- Kartu rumus IKK yang disematkan di paling atas halaman Rekap.
     Rumusnya digambar sebagai pecahan sungguhan (pembilang di atas garis,
     penyebut di bawah) supaya terbaca seperti di dokumen aslinya. --}}
<section
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-brand-100 bg-brand-50/60 shadow-card']) }}
    aria-labelledby="judul-rumus-ikk"
>
    <div class="flex items-start gap-3 border-b border-brand-100 bg-brand-50 px-5 py-4">
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-brand-gradient text-white" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]">
                <path d="M9 3v2.5a2 2 0 0 1-.6 1.4L4 11.5V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7.5l-4.4-4.6a2 2 0 0 1-.6-1.4V3"/>
                <path d="M8 3h8M8 14h8"/>
            </svg>
        </span>

        <div class="min-w-0">
            <h2 id="judul-rumus-ikk" class="font-display text-base font-bold text-ink-strong">
                Rumus Perhitungan IKK
            </h2>
            <p class="mt-0.5 text-xs leading-relaxed text-ink">
                Persentase Masyarakat yang Menjadi Sasaran Penyebaran Informasi Publik,
                Mengetahui Kebijakan dan Program Prioritas Pemerintah dan Pemerintah Daerah Kabupaten/Kota
            </p>
        </div>
    </div>

    <div class="space-y-5 px-5 py-5">
        {{-- Rumus sebagai pecahan --}}
        <div class="rounded-xl border border-brand-100 bg-surface px-5 py-5">
            {{-- Pecahan + "× 100%" diperlakukan sebagai satu kesatuan yang
                 dipusatkan, supaya garis bagi benar-benar berada di tengah. --}}
            <div class="mx-auto flex w-fit max-w-full flex-wrap items-center justify-center gap-x-4 gap-y-2">
                <div class="max-w-md text-center">
                    <p class="text-[13px] font-medium leading-snug text-ink-strong">
                        Jumlah masyarakat yang menjadi sasaran penyebaran informasi publik,
                        mengetahui kebijakan dan program prioritas pemerintah dan pemerintah daerah kabupaten/kota
                    </p>
                    <span class="my-2.5 block h-0.5 rounded-full bg-brand-500" aria-hidden="true"></span>
                    <p class="text-[13px] font-medium text-ink-strong">Jumlah penduduk</p>
                </div>

                <span class="shrink-0 font-display text-lg font-bold text-brand-700" aria-label="dikali seratus persen">
                    &times; 100%
                </span>
            </div>
        </div>

        {{-- Contoh perhitungan resmi --}}
        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 rounded-xl bg-surface-sunken px-4 py-3 text-center font-mono text-sm">
            <span class="text-[11px] font-sans font-semibold uppercase tracking-[0.08em] text-ink-muted">Contoh</span>
            <span class="text-ink-strong">319.580</span>
            <span class="text-ink-muted">&divide;</span>
            <span class="text-ink-strong">456.333</span>
            <span class="text-ink-muted">&times; 100%</span>
            <span class="text-ink-muted">=</span>
            <span class="font-semibold text-brand-700">70,03%</span>
        </div>

        {{-- Perhitungan memakai data yang sedang difilter --}}
        @if ($ikk)
            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                    Perhitungan dari data terpilih
                </p>
                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 rounded-xl border border-brand-100 bg-surface px-4 py-3.5 text-center font-mono text-sm">
                    <span class="text-ink-strong">{{ number_format($ikk['pembilang'], 0, ',', '.') }}</span>
                    <span class="text-ink-muted">&divide;</span>
                    <span class="text-ink-strong">{{ number_format($ikk['penyebut'], 0, ',', '.') }}</span>
                    <span class="text-ink-muted">&times; 100%</span>
                    <span class="text-ink-muted">=</span>
                    <span class="font-display text-xl font-bold text-brand-700">
                        {{ number_format($ikk['persentase'], 2, ',', '.') }}%
                    </span>
                </div>
            </div>
        @endif

        {{-- Apa yang dihitung, dalam bahasa sehari-hari --}}
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-hairline bg-surface p-3.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand-700">Pembilang (atas)</p>
                <p class="mt-1 text-xs leading-relaxed text-ink">
                    Warga <strong class="font-semibold text-ink-strong">usia 16–64 tahun</strong> yang mengikuti
                    akun media sosial perangkat daerah — merekalah sasaran penyebaran informasi publik lewat
                    Media Komunikasi Publik Pemerintah Daerah.
                </p>
            </div>
            <div class="rounded-xl border border-hairline bg-surface p-3.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand-700">Penyebut (bawah)</p>
                <p class="mt-1 text-xs leading-relaxed text-ink">
                    Jumlah penduduk Kabupaten Kutai Timur menurut data kependudukan resmi.
                    Angkanya bisa disesuaikan pada kolom di bawah tiap kali data BPS/Dukcapil diperbarui.
                </p>
            </div>
        </div>
    </div>
</section>
