{{-- Paginasi ringkas berbahasa Indonesia.

     Tampilan bawaan Laravel menyisipkan "Showing X to Y of Z results" dalam
     bahasa Inggris di tengah aplikasi yang seluruhnya berbahasa Indonesia.
     Keterangan jumlahnya sudah ditulis sendiri di kartu, jadi di sini cukup
     tombol pindah halaman. --}}
@if ($paginator->hasPages())
    <nav class="flex items-center justify-end gap-1" aria-label="Navigasi halaman">
        @if ($paginator->onFirstPage())
            <span class="grid size-8 place-items-center rounded-lg text-ink-muted/40" aria-disabled="true">
                <x-icon name="panah-kiri" class="size-4" />
            </span>
        @else
            <button type="button" wire:click="previousPage" rel="prev"
                    class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-surface-sunken hover:text-ink-strong"
                    aria-label="Halaman sebelumnya">
                <x-icon name="panah-kiri" class="size-4" />
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1.5 text-xs text-ink-muted">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                              class="grid size-8 place-items-center rounded-lg bg-brand-gradient text-xs font-semibold text-white">
                            {{ $page }}
                        </span>
                    @else
                        <button type="button" wire:click="gotoPage({{ $page }})"
                                class="grid size-8 place-items-center rounded-lg text-xs font-medium text-ink transition hover:bg-surface-sunken hover:text-ink-strong">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" rel="next"
                    class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-surface-sunken hover:text-ink-strong"
                    aria-label="Halaman berikutnya">
                <x-icon name="panah-kiri" class="size-4 rotate-180" />
            </button>
        @else
            <span class="grid size-8 place-items-center rounded-lg text-ink-muted/40" aria-disabled="true">
                <x-icon name="panah-kiri" class="size-4 rotate-180" />
            </span>
        @endif
    </nav>
@endif
