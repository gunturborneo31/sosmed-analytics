@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div>
        <h1 class="font-display text-2xl font-semibold tracking-[-0.01em] text-ink-strong">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-ink">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
