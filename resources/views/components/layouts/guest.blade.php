<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Masuk' }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}" type="image/png">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        /* Kartu naik sekali saat halaman dibuka — menandai titik fokus. */
        @keyframes kartuMasuk {
            from { opacity: 0; transform: translateY(18px) scale(.985); }
            to   { opacity: 1; transform: none; }
        }

        .kartu-masuk { animation: kartuMasuk .55s cubic-bezier(.16,.84,.44,1) both; }

        /* Lingkaran cahaya yang bernapas di belakang kartu. */
        @keyframes bernapas {
            0%, 100% { opacity: .38; transform: scale(1); }
            50%      { opacity: .58; transform: scale(1.06); }
        }

        .halo { animation: bernapas 7s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .kartu-masuk, .halo { animation: none; opacity: 1; transform: none; }
        }
    </style>
</head>
<body class="relative min-h-dvh overflow-hidden bg-brand-gradient">
    {{-- Arsip laporan yang melayang: lembar dokumen, grafik batang, dan garis
         tren yang bergeser mengikuti kursor (§7.1 — gradient penuh hanya di sini). --}}
    <canvas
        x-data="dataDocuments()"
        class="absolute inset-0 h-full w-full"
        aria-hidden="true"
    ></canvas>

    {{-- Peredam di tepi layar supaya lembaran tidak bersaing dengan kartu. --}}
    <div
        class="pointer-events-none absolute inset-0"
        style="background: radial-gradient(ellipse 58% 55% at 50% 50%, rgba(20,40,70,.46) 0%, rgba(20,40,70,.12) 55%, rgba(20,40,70,0) 78%);"
        aria-hidden="true"
    ></div>

    <div class="relative z-10 flex min-h-dvh items-center justify-center p-5 sm:p-6">
        <div class="relative w-full max-w-[25rem]">
            {{-- Halo lembut di belakang kartu --}}
            <div
                class="halo pointer-events-none absolute -inset-10 -z-10 rounded-[3rem] bg-white/25 blur-3xl"
                aria-hidden="true"
            ></div>

            <div class="kartu-masuk relative overflow-hidden rounded-[1.75rem] border border-white/50 bg-white/88 p-8 shadow-[0_28px_80px_-16px_rgba(16,35,58,.5)] backdrop-blur-2xl sm:p-10">
                {{-- Pantulan tipis di bibir atas kartu, meniru tepi kaca. --}}
                <span
                    class="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-white to-transparent"
                    aria-hidden="true"
                ></span>
                <div class="flex flex-col items-center text-center">
                    <span class="relative inline-flex">
                        <span class="absolute inset-0 -z-10 rounded-2xl bg-brand-gradient opacity-25 blur-lg" aria-hidden="true"></span>
                        <img
                            src="{{ asset('img/logo.png') }}"
                            alt="Diskominfo Kabupaten Kutai Timur"
                            class="h-16 w-16"
                        >
                    </span>

                    <h1 class="mt-5 font-display text-[1.35rem] font-bold leading-tight tracking-[-0.02em] text-ink-strong">
                        Social Media Analytics
                    </h1>
                    <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-500">
                        Diskominfo Kutim
                    </p>

                    <span class="mt-5 block h-px w-14 bg-gradient-to-r from-transparent via-hairline to-transparent" aria-hidden="true"></span>
                </div>

                <div class="mt-7">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    @livewireScriptConfig
</body>
</html>
