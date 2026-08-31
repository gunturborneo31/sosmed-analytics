@props(['progress', 'accountId'])

@php
    use App\Support\SyncProgress;

    $status = $progress['status'];
    $persen = (int) $progress['persen'];
    $selesai = $status === SyncProgress::STATUS_SELESAI;
    $hasil = $progress['hasil'] ?? null;

    // Nada mengikuti hasil sebenarnya: "sebagian" tidak boleh terlihat sama
    // dengan "berhasil", karena sebagian datanya memang tidak terambil.
    $nada = match (true) {
        ! $selesai => 'brand',
        $hasil === 'success' => 'success',
        $hasil === 'partial' => 'warning',
        default => 'danger',
    };
@endphp

<div
    {{ $attributes->merge(['class' => 'rounded-xl border p-3.5 '.match ($nada) {
        'success' => 'border-success/25 bg-success/5',
        'warning' => 'border-warning/30 bg-warning/5',
        'danger' => 'border-danger/25 bg-danger/5',
        default => 'border-brand-100 bg-brand-50/50',
    }]) }}
    role="status"
    aria-live="polite"
>
    <div class="flex items-start gap-3">
        {{-- Ikon keadaan: berputar selama berjalan, membeku saat selesai. --}}
        <span class="mt-0.5 shrink-0">
            @if (! $selesai)
                <svg class="size-4 animate-spin text-brand-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25"/>
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            @elseif ($hasil === 'success')
                <svg class="size-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 12.5 4.5 4.5L19 7"/>
                </svg>
            @else
                <x-icon name="peringatan" class="size-4 {{ $hasil === 'partial' ? 'text-warning' : 'text-danger' }}" />
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-baseline gap-x-2 text-xs font-semibold text-ink-strong">
                <span>
                    @if ($selesai)
                        {{ match ($hasil) {
                            'success' => 'Sinkronisasi selesai',
                            'partial' => 'Selesai sebagian',
                            default => 'Sinkronisasi gagal',
                        } }}
                    @else
                        Menyinkronkan data…
                    @endif
                </span>

                @unless ($selesai)
                    <span class="font-mono text-[11px] font-normal text-ink-muted">{{ $persen }}%</span>
                @endunless
            </p>

            <p class="mt-0.5 text-[11px] leading-relaxed text-ink">{{ $progress['tahap'] }}</p>

            @unless ($selesai)
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                    {{-- Lebar mengikuti tahap yang benar-benar dilewati Job, bukan
                         penghitung waktu — bilah yang bergerak sendiri saat pekerjaan
                         macet justru paling menyesatkan. --}}
                    <div
                        class="h-full rounded-full bg-brand-gradient transition-[width] duration-700 ease-out"
                        style="width: {{ max($persen, 3) }}%"
                    ></div>
                </div>
            @endunless

            {{-- Pekerjaan yang tak kunjung diambil hampir selalu berarti worker
                 antrean tidak berjalan. Dikatakan terus terang supaya operator
                 tidak mengira masalahnya ada di akunnya. --}}
            @if ($progress['tertahan'] ?? false)
                <p class="mt-2 rounded-lg bg-warning/10 px-2.5 py-1.5 text-[11px] leading-relaxed text-warning">
                    Sudah {{ $progress['umur'] }} detik menunggu tanpa mulai diproses. Biasanya ini berarti
                    pemroses antrean di server sedang tidak berjalan — kabari admin Diskominfo.
                </p>
            @endif
        </div>

        @if ($selesai)
            <button
                type="button"
                wire:click="dismissProgress('{{ $accountId }}')"
                class="shrink-0 rounded-lg p-1 text-ink-muted transition hover:bg-surface-sunken hover:text-ink-strong"
                aria-label="Tutup keterangan sinkronisasi"
            >
                <x-icon name="silang" class="size-3.5" />
            </button>
        @endif
    </div>
</div>
