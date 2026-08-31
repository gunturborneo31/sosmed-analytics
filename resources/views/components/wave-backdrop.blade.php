@props(['height' => 'h-9'])

{{-- Latar gelombang untuk permukaan bergradient.

     Tiap SVG selebar 200% dan path-nya memuat DUA periode yang identik, jadi
     bergeser -50% membawa gambarnya persis ke posisi semula — perulangannya
     mulus tanpa loncatan. Dua lapis berlawanan arah memberi kesan kedalaman.

     Hanya `transform` yang dianimasikan supaya kerjanya di compositor, tidak
     memicu layout ulang di setiap frame. --}}
<span {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-x-0 bottom-0 '.$height.' overflow-hidden']) }} aria-hidden="true">
    <svg
        class="absolute bottom-0 left-0 h-full w-[200%] animate-gelombang-balik"
        viewBox="0 0 1200 100" preserveAspectRatio="none"
    >
        <path
            d="M0,58 C75,28 225,28 300,58 C375,88 525,88 600,58 C675,28 825,28 900,58 C975,88 1125,88 1200,58 L1200,100 L0,100 Z"
            fill="rgb(255 255 255 / 0.12)"
        />
    </svg>

    <svg
        class="absolute bottom-0 left-0 h-full w-[200%] animate-gelombang"
        viewBox="0 0 1200 100" preserveAspectRatio="none"
    >
        <path
            d="M0,70 C75,45 225,45 300,70 C375,95 525,95 600,70 C675,45 825,45 900,70 C975,95 1125,95 1200,70 L1200,100 L0,100 Z"
            fill="rgb(255 255 255 / 0.2)"
        />
    </svg>
</span>
