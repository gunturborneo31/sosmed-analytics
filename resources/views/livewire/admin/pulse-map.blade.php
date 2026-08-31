<x-card padded="false" class="h-full">
    <div class="flex items-start justify-between gap-4 border-b border-hairline px-5 py-4">
        <div>
            <h2 class="font-display text-lg font-semibold text-ink-strong">Peta Denyut Kutim</h2>
            <p class="mt-0.5 text-xs text-ink-muted">
                {{ $this->connectedCount }} dari 18 kecamatan aktif berkomunikasi periode ini
            </p>
        </div>
        <x-badge tone="brand" class="shrink-0">18 kecamatan</x-badge>
    </div>

    <div class="p-5" x-data="{ hovered: null }">
        <div class="relative">
            <svg
                viewBox="0 0 100 85"
                class="w-full"
                role="img"
                aria-label="Peta skematis 18 kecamatan Kutai Timur beserta tingkat aktivitas media sosialnya"
            >
                {{-- Bentuk wilayah disederhanakan — penanda orientasi, bukan peta ukur. --}}
                <path
                    d="M26 34 L34 20 L48 14 L62 12 L74 14 L86 12 L92 20 L88 32 L82 40 L78 50
                       L72 62 L64 72 L54 74 L44 70 L36 60 L30 50 Z"
                    fill="var(--color-brand-50)"
                    stroke="var(--color-hairline)"
                    stroke-width="0.6"
                    stroke-linejoin="round"
                />

                @foreach ($this->districts as $d)
                    <g
                        wire:key="titik-{{ Str::slug($d['district']) }}"
                        @if ($d['connected'] && $d['slug'])
                            class="cursor-pointer"
                            @click="$wire.goToUnit('{{ $d['slug'] }}')"
                        @endif
                        @mouseenter="hovered = @js($d)"
                        @mouseleave="hovered = null"
                        @focus="hovered = @js($d)"
                        @blur="hovered = null"
                        tabindex="0"
                        role="{{ $d['connected'] ? 'button' : 'img' }}"
                        aria-label="{{ $d['district'] }}{{ $d['connected']
                            ? ': jangkauan '.number_format($d['reach'], 0, ',', '.')
                            : ': belum ada akun terhubung' }}"
                    >
                        @if ($d['connected'])
                            {{-- Halo berdenyut: menandakan "hidup" tanpa mengganggu (§7.5). --}}
                            <circle
                                cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="{{ $d['radius'] * 1.9 }}"
                                fill="{{ $d['color'] }}" opacity="0.22"
                                style="transform-origin: {{ $d['x'] }}px {{ $d['y'] }}px;
                                       animation: pulseDot {{ $d['duration'] }}s cubic-bezier(0.4,0,0.6,1) infinite;"
                            />
                            <circle
                                cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="{{ $d['radius'] }}"
                                fill="{{ $d['color'] }}"
                            />
                        @else
                            {{-- Tanpa akun: abu-abu redup, tidak berdenyut. --}}
                            <circle
                                cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="1.9"
                                fill="var(--color-ink-muted)" opacity="0.35"
                            />
                        @endif
                    </g>
                @endforeach
            </svg>

            <div
                x-show="hovered"
                x-cloak
                x-transition.opacity.duration.150ms
                class="pointer-events-none absolute left-1/2 top-2 w-56 -translate-x-1/2 rounded-xl border border-hairline bg-surface p-3 shadow-card"
            >
                <p class="font-display text-sm font-semibold text-ink-strong" x-text="hovered?.district"></p>
                <template x-if="hovered?.connected">
                    <dl class="mt-1.5 space-y-0.5 font-mono text-[11px] text-ink">
                        <div class="flex justify-between"><dt class="text-ink-muted">Pengikut</dt><dd x-text="hovered.followers.toLocaleString('id-ID')"></dd></div>
                        <div class="flex justify-between"><dt class="text-ink-muted">Jangkauan</dt><dd x-text="hovered.reach.toLocaleString('id-ID')"></dd></div>
                    </dl>
                </template>
                <template x-if="hovered && !hovered.connected">
                    <p class="mt-1 text-[11px] leading-snug text-ink-muted">
                        Belum ada akun terhubung — kecamatan ini perlu didampingi.
                    </p>
                </template>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-4 border-t border-hairline pt-3">
            <div class="flex items-center gap-2 text-[11px] text-ink-muted">
                <span>Aktivitas</span>
                <span class="h-1.5 w-20 rounded-full bg-brand-gradient"></span>
                <span>rendah &rarr; tinggi</span>
            </div>
            <div class="flex items-center gap-1.5 text-[11px] text-ink-muted">
                <span class="size-1.5 rounded-full bg-ink-muted/40"></span>
                <span>belum terhubung</span>
            </div>
        </div>
    </div>
</x-card>
