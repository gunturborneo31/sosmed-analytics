<x-card title="Spektrum Usia" subtitle="Usia 16–64 tahun · perempuan di kiri, laki-laki di kanan">
    <div class="mb-4 flex flex-wrap items-baseline gap-x-6 gap-y-1">
        <p class="text-sm text-ink">
            Kelompok terbesar
            <span class="font-display font-semibold text-ink-strong">{{ $this->highlight['dominant'] }}</span>
            <span class="font-mono text-xs text-ink-muted">({{ number_format($this->highlight['share'], 1, ',', '.') }}%)</span>
        </p>
        <p class="font-mono text-xs text-ink-muted">
            {{ number_format($this->highlight['total'], 0, ',', '.') }} pengikut terprofilkan
        </p>
    </div>

    <x-chart :id="'spektrum-'.$this->getId()" :options="$this->chart" :height="340" />
</x-card>
