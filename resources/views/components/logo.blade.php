@props(['size' => 'h-10'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img
        src="{{ asset('img/logo.png') }}"
        alt="Diskominfo Kabupaten Kutai Timur"
        class="{{ $size }} w-auto shrink-0"
    >
    <span class="leading-tight">
        <span class="block font-display text-sm font-bold tracking-tight text-ink-strong">
            Social Media Analytics
        </span>
        <span class="block text-[10px] font-medium uppercase tracking-[0.12em] text-ink-muted">
            Diskominfo Kutim
        </span>
    </span>
</span>
