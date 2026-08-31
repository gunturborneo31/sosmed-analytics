@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 px-6 py-12 text-center']) }}>
    <p class="font-display text-base font-semibold text-ink-strong">{{ $title }}</p>

    @if ($description)
        <p class="max-w-sm text-xs text-ink-muted">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
