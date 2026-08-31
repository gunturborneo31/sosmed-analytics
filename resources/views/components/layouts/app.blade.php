@php
    $user = auth()->user();
    $jam = (int) now()->format('G');
    $sapaan = match (true) {
        $jam < 11 => 'Selamat pagi',
        $jam < 15 => 'Selamat siang',
        $jam < 19 => 'Selamat sore',
        default => 'Selamat malam',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '' }}{{ isset($title) ? ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}" type="image/png">

    {{-- Dipasang sebelum halaman digambar supaya sidebar tidak berkedip
         melebar-lalu-menyempit setiap kali pindah halaman. --}}
    <script>
        (function () {
            var el = document.documentElement;

            if (localStorage.getItem('sidebar') === 'ciut') {
                el.classList.add('sidebar-ciut');
            }

            // Tanpa pilihan tersimpan, ikuti setelan sistem.
            var tema = localStorage.getItem('tema');
            var gelap = tema ? tema === 'gelap'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (gelap) el.classList.add('dark');
        })();
    </script>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh" x-data="sidebarShell()">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-card">
        Lompat ke konten
    </a>

    {{-- Notifikasi aksi Livewire — lihat catatan pada `toaster` di app.js
         soal kenapa ini tidak bisa memakai session()->flash() biasa. --}}
    <div
        x-data="toaster()"
        x-on:toast.window="tambah($event.detail.type, $event.detail.message)"
        class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-show="true" x-cloak
                x-transition:enter="transition duration-200 ease-out"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition duration-150 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border bg-surface px-4 py-3 text-sm shadow-2xl"
                :class="toast.tipe === 'error' ? 'border-danger/25 text-danger' : 'border-success/25 text-success'"
            >
                <span class="flex-1" x-text="toast.pesan"></span>
                <button
                    type="button" x-on:click="hapus(toast.id)"
                    class="shrink-0 text-ink-muted opacity-70 hover:opacity-100"
                    aria-label="Tutup notifikasi"
                >&times;</button>
            </div>
        </template>
    </div>

    {{-- Tirai untuk laci di layar kecil --}}
    <div
        x-show="laci" x-cloak x-transition.opacity.duration.200ms
        @click="laci = false"
        class="fixed inset-0 z-40 bg-ink-strong/40 backdrop-blur-sm lg:hidden"
        aria-hidden="true"
    ></div>

    {{-- ───────────────────────── Sidebar ───────────────────────── --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-hairline bg-surface
               transition-[width,transform] duration-300 ease-[cubic-bezier(.16,.84,.44,1)]
               ciut:lg:w-[4.75rem] max-lg:-translate-x-full"
        :class="laci && 'max-lg:translate-x-0'"
        aria-label="Sidebar"
    >
        <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-hairline px-4 ciut:justify-center ciut:px-0">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                <img src="{{ asset('img/logo.png') }}" alt="Diskominfo Kutai Timur" class="h-9 w-9 shrink-0">
                <span class="min-w-0 leading-tight ciut:hidden">
                    <span class="block truncate font-display text-[13px] font-bold tracking-tight text-ink-strong">Social Media Analytics</span>
                    <span class="block text-[9px] font-semibold uppercase tracking-[0.14em] text-brand-500">Diskominfo Kutim</span>
                </span>
            </a>

            <button
                type="button" @click="laci = false"
                class="ml-auto rounded-lg p-1.5 text-ink-muted hover:bg-surface-sunken hover:text-ink-strong lg:hidden"
            >
                <x-icon name="silang" class="size-5" />
                <span class="sr-only">Tutup menu</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden py-5">
            @include('partials.nav')
        </div>

        <div class="shrink-0 border-t border-hairline p-3 ciut:px-2">
            <div class="flex items-center gap-2.5 rounded-xl bg-surface-sunken px-3 py-2.5 ciut:justify-center ciut:px-0">
                <span class="size-2 shrink-0 rounded-full bg-success" aria-hidden="true"></span>
                <span class="min-w-0 leading-tight ciut:hidden">
                    <span class="block truncate text-[11px] font-medium text-ink-strong">{{ $user->getRoleNames()->first() ?? 'tanpa peran' }}</span>
                    <span class="block truncate text-[10px] text-ink-muted">{{ $user->organizationalUnit?->name ?? 'Tanpa OPD' }}</span>
                </span>
            </div>
        </div>

        {{-- Tombol lipat: menumpang di garis tepi sidebar, sejajar baris logo. --}}
        <button
            type="button"
            @click="toggle()"
            class="absolute right-0 top-8 hidden size-7 -translate-y-1/2 translate-x-1/2 place-items-center
                   rounded-full border border-hairline bg-surface text-ink-muted shadow-card transition
                   hover:border-brand-300 hover:text-brand-700 lg:grid"
            :aria-expanded="!ciut"
            aria-controls="konten"
        >
            <x-icon name="panah-kiri" class="size-4 transition-transform duration-300 ciut:rotate-180" />
            <span class="sr-only" x-text="ciut ? 'Bentangkan menu' : 'Lipat menu'">Lipat menu</span>
        </button>
    </aside>

    {{-- ───────────────────────── Isi ───────────────────────── --}}
    <div class="flex min-h-dvh flex-col transition-[padding] duration-300 ease-[cubic-bezier(.16,.84,.44,1)] lg:pl-64 ciut:lg:pl-[4.75rem]">
        <header class="sticky top-0 z-30 border-b border-hairline bg-surface/85 backdrop-blur">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                <button
                    type="button" @click="laci = true"
                    class="rounded-lg p-2 text-ink hover:bg-surface-sunken lg:hidden"
                >
                    <x-icon name="menu" class="size-5" />
                    <span class="sr-only">Buka menu</span>
                </button>

                <div class="ml-auto flex items-center gap-3">
                    @isset($toolbar)
                        {{ $toolbar }}
                    @endisset

                    <button
                        type="button"
                        @click="gantiTema()"
                        class="grid size-9 shrink-0 place-items-center rounded-xl border border-hairline text-ink-muted transition hover:bg-surface-sunken hover:text-ink-strong"
                        :aria-pressed="gelap"
                        :title="gelap ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
                    >
                        <x-icon name="matahari" class="size-[18px] dark:hidden" />
                        <x-icon name="bulan" class="hidden size-[18px] dark:block" />
                        <span class="sr-only" x-text="gelap ? 'Mode gelap aktif' : 'Mode terang aktif'">Ganti tema</span>
                    </button>

                    <div x-data="{ buka: false }" class="relative">
                        <button
                            type="button"
                            @click="buka = !buka"
                            @click.outside="buka = false"
                            @keydown.escape.window="buka = false"
                            class="flex items-center gap-2 rounded-xl border border-hairline px-2 py-1.5 text-left transition hover:bg-surface-sunken"
                            :aria-expanded="buka"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-gradient font-display text-xs font-bold text-white">
                                {{ str($user->name)->substr(0, 1)->upper() }}
                            </span>
                            {{-- Sapaan & tanggal menggantikan nama/OPD di sini — identitas
                                 lengkapnya tetap ada begitu panel di bawah ini dibuka. --}}
                            <span class="hidden min-w-0 leading-tight sm:block">
                                <span class="block truncate text-xs font-medium text-ink-strong">
                                    {{ $sapaan }}, {{ str($user->name)->before(' ') }}
                                </span>
                                <span class="block truncate text-[10px] text-ink-muted">
                                    {{ now()->translatedFormat('l, j F Y') }}
                                </span>
                            </span>
                        </button>

                        <div
                            x-show="buka" x-cloak
                            x-transition.origin.top.right.duration.150ms
                            class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-hairline bg-surface shadow-card"
                        >
                            <div class="border-b border-hairline px-3 py-3">
                                <p class="truncate text-sm font-medium text-ink-strong">{{ $user->name }}</p>
                                <p class="truncate text-[11px] text-ink-muted">{{ $user->email }}</p>
                                <x-badge tone="brand" class="mt-2">{{ $user->getRoleNames()->first() ?? 'tanpa peran' }}</x-badge>
                            </div>

                            <form method="POST" action="{{ route('logout') }}" class="p-1.5">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-ink transition hover:bg-danger/10 hover:text-danger">
                                    <x-icon name="keluar" class="size-4" />
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="konten" class="flex-1 px-4 py-8 sm:px-6">
            <div class="mx-auto max-w-7xl">
                <x-flash />
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScriptConfig
</body>
</html>
