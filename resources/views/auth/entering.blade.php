<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menyiapkan dashboard — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}" type="image/png">

    {{-- Tanpa JavaScript, halaman ini tetap mengantar ke tujuan. --}}
    <noscript><meta http-equiv="refresh" content="1;url={{ $target }}"></noscript>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Keyframes khusus halaman ini — tidak dipakai di tempat lain, jadi
           sengaja tidak menambah beban app.css. */
        @keyframes lembarNaik {
            0%   { opacity: 0; transform: translateY(26px) rotate(var(--miring)) scale(.94); }
            18%  { opacity: 1; }
            55%  { opacity: 1; transform: translateY(0) rotate(var(--miring)) scale(1); }
            88%  { opacity: 1; transform: translateY(0) rotate(var(--miring)) scale(1); }
            100% { opacity: 0; transform: translateY(-14px) rotate(var(--miring)) scale(1.02); }
        }

        @keyframes garisTeks {
            0%, 12%  { transform: scaleX(0); }
            42%,100% { transform: scaleX(1); }
        }

        @keyframes batangTumbuh {
            0%, 100% { transform: scaleY(.18); }
            50%      { transform: scaleY(1); }
        }

        @keyframes cincinPutar {
            to { transform: rotate(360deg); }
        }

        @keyframes majuTerus {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .lembar   { animation: lembarNaik 2.4s cubic-bezier(.16,.84,.44,1) infinite; transform-origin: 50% 100%; }
        .garis    { animation: garisTeks 2.4s cubic-bezier(.16,.84,.44,1) infinite; transform-origin: left center; }
        .batang   { animation: batangTumbuh 1.6s cubic-bezier(.4,0,.6,1) infinite; transform-origin: 50% 100%; }
        .cincin   { animation: cincinPutar 3.2s linear infinite; transform-origin: 50% 50%; }
        .pita     { animation: majuTerus 1.4s cubic-bezier(.4,0,.6,1) infinite; }

        @media (prefers-reduced-motion: reduce) {
            .lembar, .garis, .batang, .cincin, .pita { animation: none !important; }
            .lembar { opacity: 1; transform: none; }
            .garis, .batang { transform: none; }
        }
    </style>
</head>
<body class="relative min-h-dvh overflow-hidden bg-brand-gradient">
    {{-- Latar yang sama dengan halaman masuk agar transisinya terasa menyambung. --}}
    <canvas x-data="dataDocuments()" class="absolute inset-0 h-full w-full" aria-hidden="true"></canvas>

    <main
        class="relative z-10 flex min-h-dvh items-center justify-center p-6"
        x-data="enteringScreen(@js($target))"
        role="status"
        aria-live="polite"
    >
        <div class="w-full max-w-sm rounded-3xl border border-white/15 bg-surface p-8 text-center shadow-2xl sm:p-10">

            {{-- Data mengalir menjadi dokumen: lembar naik menumpuk, teksnya
                 tergambar baris demi baris, batang data tumbuh di bawahnya. --}}
            <svg viewBox="0 0 120 120" class="mx-auto h-32 w-32" aria-hidden="true">
                <defs>
                    <linearGradient id="grad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#3E68B2"/>
                        <stop offset="1" stop-color="#3DC8F4"/>
                    </linearGradient>
                </defs>

                {{-- Cincin putus-putus yang berputar pelan --}}
                <circle
                    class="cincin"
                    cx="60" cy="60" r="52"
                    fill="none" stroke="url(#grad)" stroke-width="2"
                    stroke-linecap="round" stroke-dasharray="4 14" opacity=".45"
                />

                {{-- Tiga lembar dokumen, saling menyusul --}}
                @foreach ([
                    ['miring' => '-7deg', 'x' => 30, 'delay' => '0s',    'opacity' => '.4'],
                    ['miring' => '4deg',  'x' => 36, 'delay' => '.28s',  'opacity' => '.65'],
                    ['miring' => '0deg',  'x' => 33, 'delay' => '.56s',  'opacity' => '1'],
                ] as $lembar)
                    <g class="lembar" style="--miring: {{ $lembar['miring'] }}; animation-delay: {{ $lembar['delay'] }};" opacity="{{ $lembar['opacity'] }}">
                        <rect x="{{ $lembar['x'] }}" y="26" width="54" height="50" rx="6" fill="#FBFDFF" stroke="#A8D5F2" stroke-width="1.6"/>
                        @foreach ([34, 42, 50] as $i => $y)
                            <rect
                                class="garis"
                                x="{{ $lembar['x'] + 7 }}" y="{{ $y }}"
                                width="{{ [34, 40, 26][$i] }}" height="3.4" rx="1.7"
                                fill="url(#grad)" opacity=".72"
                                style="animation-delay: calc({{ $lembar['delay'] }} + {{ $i * 0.1 }}s);"
                            />
                        @endforeach
                    </g>
                @endforeach

                {{-- Batang data di kaki tumpukan --}}
                @foreach ([0, 1, 2, 3, 4] as $i)
                    <rect
                        class="batang"
                        x="{{ 35 + $i * 10 }}" y="82" width="7" height="22" rx="2.5"
                        fill="url(#grad)"
                        style="animation-delay: {{ $i * 0.12 }}s;"
                    />
                @endforeach
            </svg>

            <p class="mt-6 font-display text-lg font-bold tracking-[-0.01em] text-ink-strong">
                Menyiapkan dashboard
            </p>

            {{-- Teks bergilir; murni penanda bahwa proses masih berjalan. --}}
            <p class="mt-1 h-5 text-xs text-ink-muted transition-opacity duration-150"
               :class="{ 'opacity-0': berganti }"
               x-text="tahapan[langkah]">Menghubungkan sesi</p>

            <div class="mt-6 h-1 overflow-hidden rounded-full bg-surface-sunken">
                <div class="pita h-full w-1/2 rounded-full bg-brand-gradient"></div>
            </div>

            {{-- Kalau JS lambat atau gagal, pengguna tetap punya jalan keluar. --}}
            <a href="{{ $target }}" class="mt-5 inline-block text-xs font-medium text-brand-700 hover:underline">
                Lanjutkan sekarang &rarr;
            </a>
        </div>
    </main>
</body>
</html>
