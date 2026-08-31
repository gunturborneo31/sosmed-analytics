@php
    $user = auth()->user();

    // Menu dikelompokkan agar tetap terbaca saat daftarnya memanjang.
    $groups = $user->seesAllUnits()
        ? [
            'Pantau' => [
                ['admin.overview', 'Dashboard', 'ringkasan'],
                ['admin.units', 'Perangkat Daerah', 'gedung'],
                ['admin.news', 'Media Partner', 'berita'],
            ],
            'Analisis' => [
                ['admin.demographics', 'Demografi', 'demografi'],
                ['admin.comparison', 'Perbandingan', 'perbandingan'],
            ],
            'Kelola' => [
                ['admin.recap', 'Rekap', 'rekap'],
                ['admin.sync-logs', 'Log Sinkronisasi', 'sinkron'],
            ],
        ]
        : [
            'Instansi' => [
                ['operator.accounts', 'Akun', 'akun'],
                ['operator.insight', 'Insight', 'insight'],
            ],
        ];

    if ($user->can('manage-users')) {
        $groups['Kelola'][] = ['admin.users', 'Pengguna', 'pengguna'];
    }
@endphp

<nav class="flex flex-1 flex-col gap-6 px-3" aria-label="Navigasi utama">
    @foreach ($groups as $judul => $items)
        <div>
            {{-- Judul kelompok menyusut jadi garis tipis saat sidebar dilipat. --}}
            <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-muted ciut:hidden">
                {{ $judul }}
            </p>
            <span class="mx-auto mb-2 hidden h-px w-6 bg-hairline ciut:block" aria-hidden="true"></span>

            <ul class="space-y-1">
                @foreach ($items as [$route, $label, $icon])
                    @php
                        $aktif = request()->routeIs($route);
                        // Rekap adalah keluaran akhir aplikasi ini — laporan yang
                        // ditandatangani dan dikirim keluar. Satu-satunya menu yang
                        // sengaja dibedakan, supaya tetap istimewa; menandai banyak
                        // menu sekaligus akan meniadakan penekanannya.
                        $istimewa = $route === 'admin.recap';
                    @endphp
                    <li class="group/item relative">
                        @if ($istimewa)
                            <a
                                href="{{ route($route) }}"
                                @if ($aktif) aria-current="page" @endif
                                @class([
                                    'group/rekap relative flex items-center gap-3 overflow-hidden rounded-xl bg-brand-gradient px-3 py-2.5',
                                    'text-sm font-semibold text-white transition-shadow duration-300 ciut:justify-center ciut:px-0',
                                    'shadow-glow' => $aktif,
                                    'shadow-card hover:shadow-glow' => ! $aktif,
                                ])
                            >
                                <x-wave-backdrop />

                                {{-- Kilau tipis di tepi atas: memberi kesan permukaan
                                     cembung, bukan kotak datar berwarna. --}}
                                <span class="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/35" aria-hidden="true"></span>

                                <x-icon :name="$icon" class="relative size-[18px] drop-shadow-sm" />
                                <span class="relative truncate ciut:hidden">{{ $label }}</span>

                                @if ($aktif)
                                    <span class="relative ml-auto size-1.5 shrink-0 rounded-full bg-white animate-pulse-dot ciut:hidden" aria-hidden="true"></span>
                                @endif
                            </a>
                        @else
                            <a
                                href="{{ route($route) }}"
                                @if ($aktif) aria-current="page" @endif
                                @class([
                                    'relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ciut:justify-center ciut:px-0',
                                    'bg-brand-50 text-brand-700' => $aktif,
                                    'text-ink hover:bg-surface-sunken hover:text-ink-strong' => ! $aktif,
                                ])
                            >
                                {{-- Penanda halaman aktif: batang gradient di tepi kiri. --}}
                                @if ($aktif)
                                    <span class="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-r-full bg-brand-gradient" aria-hidden="true"></span>
                                @endif

                                <x-icon :name="$icon" class="size-[18px]" />
                                <span class="truncate ciut:hidden">{{ $label }}</span>
                            </a>
                        @endif

                        {{-- Saat dilipat, nama menu muncul sebagai tooltip. --}}
                        <span
                            class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 hidden -translate-y-1/2 whitespace-nowrap rounded-lg bg-ink-strong px-2.5 py-1.5 text-xs font-medium text-surface opacity-0 shadow-card transition-opacity ciut:block group-hover/item:opacity-100"
                            role="tooltip"
                        >{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
