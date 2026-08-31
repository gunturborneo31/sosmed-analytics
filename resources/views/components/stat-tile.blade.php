@props([
    'label',
    'value',
    'numeric' => null,
    'delta' => null,
    'showDelta' => false,   // bedakan "tidak punya delta" dari "delta tak terhitung"
    'caption' => null,
    'index' => 0,
])

@php
    $deltaTone = match (true) {
        $delta === null => 'text-ink-muted',
        $delta > 0 => 'text-success',
        $delta < 0 => 'text-danger',
        default => 'text-ink-muted',
    };
@endphp

{{-- Kemunculannya dianimasikan lewat CSS, bukan JavaScript.
     Direktif Alpine sebelumnya menyetel `opacity: 0` lalu mengandalkan
     animasi untuk memunculkannya kembali — begitu Livewire memperbarui DOM
     (misalnya periode filter diganti) kartunya disembunyikan ulang dan lenyap
     dari layar. Animasi CSS dengan fill-mode `both` selalu berakhir pada
     keadaan terlihat, dan tidak pernah dijalankan ulang oleh morph. --}}
<article
    style="animation-delay: {{ min((int) $index, 8) * 60 }}ms"
    {{ $attributes->merge(['class' => 'animate-slide-up rounded-2xl border border-hairline bg-surface p-5 shadow-card']) }}
>
    <p class="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">{{ $label }}</p>

    <p class="mt-2 font-display text-3xl font-bold tracking-[-0.02em] text-ink-strong">
        @if ($numeric !== null)
            {{-- Angka sebenarnya ditulis langsung sebagai isi elemen, bukan "0".
                 Animasinya cuma hiasan yang dijalankan Alpine saat elemen ini
                 pertama dipasang; setelah Livewire memperbarui DOM, direktifnya
                 tidak dijalankan ulang — kalau isinya "0", angkanya tertinggal
                 nol selamanya setiap kali filter diganti. --}}
            <span x-count-up="{{ $numeric }}">{{ number_format((float) $numeric, 0, ',', '.') }}</span>{{ $value }}
        @else
            {{ $value }}
        @endif
    </p>

    @if ($showDelta || $caption)
        <p class="mt-1.5 flex items-center gap-1.5 text-xs">
            @if ($showDelta)
                @if ($delta === null)
                    <span class="font-mono font-medium text-ink-muted" title="Periode pembanding belum punya data">&mdash;</span>
                @else
                    <span class="font-mono font-medium {{ $deltaTone }}">
                        {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1, ',', '.') }}%
                    </span>
                @endif
            @endif
            @if ($caption)
                <span class="text-ink-muted">{{ $showDelta && $delta === null ? 'belum ada pembanding' : $caption }}</span>
            @endif
        </p>
    @endif
</article>
