@props([
    'platform',
    'href',
    'ready' => true,
    'size' => 'md',
])

@php
    use App\Support\SocialPlatform;

    $gaya = SocialPlatform::tileStyle($platform);
    $label = SocialPlatform::label($platform);

    $ukuran = match ($size) {
        'lg' => 'h-12 px-5 text-sm',
        default => 'h-10 px-4 text-sm',
    };

    // Latar memakai warna merek asli — gradien untuk Instagram, biru untuk
    // Facebook — bukan token warna aplikasi, supaya kanalnya langsung dikenali.
    $kelas = 'inline-flex items-center justify-center gap-2 rounded-xl font-medium text-white transition '.$ukuran;
@endphp

@if ($ready)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas.' shadow-card hover:brightness-[1.08] active:brightness-95']) }} style="{{ $gaya }}">
        <x-platform-icon :platform="$platform" class="size-[18px]" />
        Hubungkan {{ $label }}
    </a>
@else
    {{-- Kredensial jalur ini belum diisi, jadi tautannya dimatikan tapi tetap
         terlihat — operator perlu tahu kanalnya ada, bukan mengira hilang. --}}
    <span
        {{ $attributes->merge(['class' => $kelas.' cursor-not-allowed opacity-45']) }}
        style="{{ $gaya }}"
        aria-disabled="true"
        title="Kredensial {{ $label }} belum diisi di .env"
    >
        <x-platform-icon :platform="$platform" class="size-[18px]" />
        Hubungkan {{ $label }}
    </span>
@endif
