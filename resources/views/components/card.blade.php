@props(['title' => null, 'subtitle' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-hairline bg-surface shadow-card']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-hairline px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="font-display text-lg font-semibold text-ink-strong">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-ink-muted">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">{{ $slot }}</div>
</section>
