<x-card title="Rasio Gender" subtitle="Berdasarkan data yang dikenali Meta">
    @php $r = $this->ratio; @endphp

    <div class="flex h-4 overflow-hidden rounded-full bg-surface-sunken">
        <div class="bg-brand-bright transition-[width] duration-700 ease-out" style="width: {{ $r['female_pct'] }}%"></div>
        <div class="bg-brand-500 transition-[width] duration-700 ease-out" style="width: {{ $r['male_pct'] }}%"></div>
    </div>

    <dl class="mt-5 grid grid-cols-2 gap-4">
        <div>
            <dt class="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">♀ Perempuan</dt>
            <dd class="mt-1 font-display text-2xl font-bold text-ink-strong">
                {{ number_format($r['female_pct'], 1, ',', '.') }}<span class="text-base">%</span>
            </dd>
            <dd class="font-mono text-[11px] text-ink-muted">{{ number_format($r['female'], 0, ',', '.') }} pengikut</dd>
        </div>
        <div>
            <dt class="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">♂ Laki-laki</dt>
            <dd class="mt-1 font-display text-2xl font-bold text-ink-strong">
                {{ number_format($r['male_pct'], 1, ',', '.') }}<span class="text-base">%</span>
            </dd>
            <dd class="font-mono text-[11px] text-ink-muted">{{ number_format($r['male'], 0, ',', '.') }} pengikut</dd>
        </div>
    </dl>

    @if ($r['unknown'] > 0)
        <p class="mt-4 border-t border-hairline pt-3 text-[11px] text-ink-muted">
            {{ number_format($r['unknown'], 0, ',', '.') }} pengikut tidak mencantumkan gender —
            tidak dihitung dalam persentase di atas.
        </p>
    @endif
</x-card>
