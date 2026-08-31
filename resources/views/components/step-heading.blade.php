@props(['number', 'title'])

{{-- Penanda langkah untuk alur yang dikerjakan berurutan. Nomornya sengaja
     dibuat mencolok supaya urutan kerjanya terbaca sekali lihat. --}}
<div {{ $attributes->merge(['class' => 'flex items-start gap-3']) }}>
    <span
        class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-brand-gradient font-display text-sm font-bold text-white"
        aria-hidden="true"
    >{{ $number }}</span>

    <div class="min-w-0">
        <h3 class="font-display text-base font-semibold text-ink-strong">
            <span class="sr-only">Langkah {{ $number }}:</span>{{ $title }}
        </h3>

        @if (trim($slot) !== '')
            <p class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ $slot }}</p>
        @endif
    </div>
</div>
