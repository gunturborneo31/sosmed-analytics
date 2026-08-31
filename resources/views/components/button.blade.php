@props(['variant' => 'primary', 'size' => 'md', 'href' => null])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-medium transition
             disabled:cursor-not-allowed disabled:opacity-55';

    $variants = [
        // Gradient penuh hanya untuk aksi utama (§7.1).
        'primary' => 'bg-brand-gradient text-white shadow-glow hover:brightness-[1.06] active:brightness-95',
        'secondary' => 'border border-hairline bg-surface text-ink-strong hover:bg-surface-sunken',
        'ghost' => 'text-ink hover:bg-surface-sunken hover:text-ink-strong',
        // Aksi merusak yang tampil ringan. Dibuat sebagai varian tersendiri
        // karena menimpanya lewat class="text-danger" tidak berhasil: keduanya
        // utilitas berspesifisitas sama, jadi urutan di stylesheet yang menang,
        // bukan urutan di atribut.
        'ghost-danger' => 'text-danger hover:bg-danger/10',
        'danger' => 'bg-danger text-white hover:brightness-105',
    ];

    $sizes = [
        'sm' => 'h-8 px-3 text-xs',
        'md' => 'h-10 px-4 text-sm',
        'lg' => 'h-12 px-6 text-sm',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
