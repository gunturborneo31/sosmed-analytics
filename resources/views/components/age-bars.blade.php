@props(['profile'])

{{-- Dipakai berulang oleh tiap kanal dan bagian gabungan, jadi bentuknya
     dikumpulkan di satu tempat, bukan disalin per bagian. --}}
@if ($profile->sum() === 0)
    <p class="py-6 text-center text-sm text-ink-muted">
        Data usia belum tersedia. Meta baru mengirimkannya setelah akun punya cukup pengikut.
    </p>
@else
    @php $puncak = max($profile->max() ?? 1, 1); @endphp

    <ul class="space-y-3">
        @foreach ($profile as $kelompok => $jumlah)
            <li class="flex items-center gap-3">
                <span class="w-14 shrink-0 font-mono text-xs text-ink-muted">{{ $kelompok }}</span>
                <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-sunken">
                    <span
                        class="block h-full rounded-full bg-brand-500"
                        style="width: {{ round($jumlah / $puncak * 100, 1) }}%"
                    ></span>
                </span>
                <span class="w-20 shrink-0 text-right font-mono text-xs text-ink">
                    {{ number_format($jumlah, 0, ',', '.') }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
