@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-surface-sunken text-ink-muted border-hairline',
        'brand' => 'bg-brand-50 text-brand-700 border-brand-100',
        'success' => 'bg-success/10 text-success border-success/20',
        'warning' => 'bg-warning/10 text-warning border-warning/25',
        'danger' => 'bg-danger/10 text-danger border-danger/20',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium '
        .($tones[$tone] ?? $tones['neutral']),
]) }}>{{ $slot }}</span>
