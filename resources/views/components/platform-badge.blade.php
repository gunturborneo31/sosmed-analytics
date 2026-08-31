@props([
    'platform',
    'size' => 'size-10',
])

@php
    use App\Support\SocialPlatform;

    $gaya = SocialPlatform::tileStyle($platform);
    $label = SocialPlatform::label($platform);
@endphp

<span
    {{ $attributes->merge([
        'class' => 'grid '.$size.' shrink-0 place-items-center rounded-xl shadow-card '
            .($gaya === '' ? 'bg-surface-sunken text-ink-muted' : 'text-white'),
    ]) }}
    style="{{ $gaya }}"
    title="{{ $label }}"
>
    <x-platform-icon :platform="$platform" class="size-[55%]" />

    <span class="sr-only">{{ $label }}</span>
</span>
