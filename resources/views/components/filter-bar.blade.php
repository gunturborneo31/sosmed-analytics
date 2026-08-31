@props(['period' => '30', 'unitTypes' => [], 'platforms' => [], 'showUnitType' => true])

{{-- Filter §10.2 — periode, jenis OPD, platform. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3 rounded-2xl border border-hairline bg-surface p-4 shadow-card']) }}>
    <x-select
        label="Periode"
        wire:model.live="period"
        :options="\App\Support\Period::OPTIONS"
        class="min-w-44"
    />

    @if ($period === 'custom')
        <label class="space-y-1.5">
            <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Dari</span>
            <input type="date" wire:model.live="from" class="h-11 rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
        </label>
        <label class="space-y-1.5">
            <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Sampai</span>
            <input type="date" wire:model.live="until" class="h-11 rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
        </label>
    @endif

    {{-- Saringan jenis OPD hanya dipasang di halaman yang tidak punya cara lain
         mempersempit daftar. Di halaman yang sudah punya pemilih OPD sendiri, dua
         saringan yang bekerja bersamaan membingungkan dan gampang menghasilkan
         hasil kosong tanpa penjelasan. --}}
    @if ($showUnitType)
        <x-select label="Jenis OPD" wire:model.live="unitType" :options="$unitTypes" class="min-w-40" />
    @endif
    <x-select label="Platform" wire:model.live="platform" :options="$platforms" class="min-w-40" />

    @isset($extra)
        {{ $extra }}
    @endisset

    <x-button variant="ghost" size="md" wire:click="resetFilters" class="ml-auto">Atur ulang</x-button>
</div>
