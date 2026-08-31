@props([
    'property',        // nama properti boolean di komponen Livewire
    'title' => null,
    'subtitle' => null,
    'tone' => 'brand',  // brand | danger — mewarnai ikon judul
    'icon' => null,
    'width' => 'max-w-xl',
])

{{--
    Modal umum untuk seluruh panel admin.

    Kondisi buka/tutup di-entangle ke properti Livewire, bukan dirender lewat
    @if — dengan begitu elemennya tetap ada di DOM sehingga transisi keluar
    ikut berjalan, dan menutup modal tidak perlu bolak-balik ke server.
--}}
<div
    x-data="{ terbuka: $wire.entangle('{{ $property }}') }"
    x-effect="document.body.style.overflow = terbuka ? 'hidden' : ''"
    x-on:keydown.escape.window="terbuka = false"
    x-show="terbuka"
    x-cloak
    class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div
        x-show="terbuka"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="terbuka = false"
        class="fixed inset-0 bg-ink-strong/50 backdrop-blur-md"
        aria-hidden="true"
    ></div>

    <div
        x-show="terbuka"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0 translate-y-3 scale-[.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-[.98]"
        {{-- Fokus papan tik ditahan di dalam modal selama terbuka, lalu
             dikembalikan ke tombol pemicunya saat ditutup. --}}
        x-trap.noscroll="terbuka"
        class="relative my-auto w-full {{ $width }} overflow-hidden rounded-2xl border border-hairline bg-surface shadow-2xl"
    >
        @if ($title)
            <div class="flex items-start gap-3.5 border-b border-hairline px-6 py-5">
                @if ($icon)
                    <span @class([
                        'mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl',
                        'bg-brand-50 text-brand-700' => $tone === 'brand',
                        'bg-danger/10 text-danger' => $tone === 'danger',
                    ])>
                        <x-icon :name="$icon" class="size-[18px]" />
                    </span>
                @endif

                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-lg font-semibold leading-tight text-ink-strong">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-1 text-xs leading-relaxed text-ink-muted">{{ $subtitle }}</p>
                    @endif
                </div>

                <button
                    type="button"
                    x-on:click="terbuka = false"
                    class="-mr-1.5 -mt-0.5 shrink-0 rounded-lg p-1.5 text-ink-muted transition hover:bg-surface-sunken hover:text-ink-strong"
                >
                    <x-icon name="silang" class="size-5" />
                    <span class="sr-only">Tutup</span>
                </button>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
