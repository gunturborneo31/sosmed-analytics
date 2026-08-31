@props(['label' => null, 'options' => [], 'compact' => false])

<label class="block {{ $compact ? '' : 'space-y-1.5' }}">
    @if ($label)
        <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">{{ $label }}</span>
    @endif

    <select {{ $attributes->merge([
        'class' => 'block w-full rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong
                    transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12 '
            .($compact ? 'h-9' : 'h-11'),
    ]) }}>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
</label>
