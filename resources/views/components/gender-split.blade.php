@props(['split'])

@php
    $perempuan = (int) ($split['F'] ?? 0);
    $lakiLaki = (int) ($split['M'] ?? 0);
    $dikenali = $perempuan + $lakiLaki;
@endphp

@if ($dikenali === 0)
    <p class="py-6 text-center text-sm text-ink-muted">
        Data gender belum tersedia dari Meta untuk kanal ini.
    </p>
@else
    <div class="flex h-4 overflow-hidden rounded-full bg-surface-sunken">
        <div class="bg-brand-bright" style="width: {{ round($perempuan / $dikenali * 100, 1) }}%"></div>
        <div class="bg-brand-500" style="width: {{ round($lakiLaki / $dikenali * 100, 1) }}%"></div>
    </div>

    <dl class="mt-5 grid grid-cols-2 gap-4">
        <div>
            <dt class="text-[11px] uppercase tracking-[0.06em] text-ink-muted">&#9792; Perempuan</dt>
            <dd class="font-display text-2xl font-bold text-ink-strong">
                {{ number_format($perempuan / $dikenali * 100, 1, ',', '.') }}%
            </dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-[0.06em] text-ink-muted">&#9794; Laki-laki</dt>
            <dd class="font-display text-2xl font-bold text-ink-strong">
                {{ number_format($lakiLaki / $dikenali * 100, 1, ',', '.') }}%
            </dd>
        </div>
    </dl>

    @if (($split['U'] ?? 0) > 0)
        <p class="mt-3 text-[11px] leading-relaxed text-ink-muted">
            {{ number_format($split['U'], 0, ',', '.') }} pengikut tanpa data gender tidak ikut dihitung dalam persentase di atas.
        </p>
    @endif
@endif
