@props(['status'])

@php
    [$tone, $label] = match ($status) {
        'connected' => ['success', 'Terhubung'],
        'expired' => ['warning', 'Token kedaluwarsa'],
        'revoked' => ['danger', 'Izin dicabut'],
        'error' => ['danger', 'Bermasalah'],
        default => ['neutral', ucfirst((string) $status)],
    };
@endphp

<x-badge :tone="$tone" {{ $attributes }}>
    <span class="size-1.5 rounded-full bg-current"></span>{{ $label }}
</x-badge>
