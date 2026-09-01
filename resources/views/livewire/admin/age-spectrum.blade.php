<x-card title="Spektrum Usia" subtitle="{{ in_array($this->platform, ['website-opd', 'website-media-partner'], true) ? 'Usia 16–64 tahun · data website dihitung dari total views' : 'Usia 16–64 tahun · perempuan di kiri, laki-laki di kanan' }}">
    <div class="mb-4 flex flex-wrap items-baseline gap-x-6 gap-y-1">
        <p class="text-sm text-ink">
            Kelompok usia
            <span class="font-display font-semibold text-ink-strong">{{ in_array($this->platform, ['website-opd', 'website-media-partner'], true) ? '16-64' : $this->highlight['dominant'] }}</span>
            @unless (in_array($this->platform, ['website-opd', 'website-media-partner'], true))
                <span class="font-mono text-xs text-ink-muted">({{ number_format($this->highlight['share'], 1, ',', '.') }}%)</span>
            @endunless
        </p>
        <p class="font-mono text-xs text-ink-muted">
            {{ number_format($this->highlight['total'], 0, ',', '.') }} {{ in_array($this->platform, ['website-opd', 'website-media-partner'], true) ? 'views terhitung' : 'pengikut terprofilkan' }}
        </p>
    </div>

    <x-chart :id="'spektrum-'.$this->getId()" :options="$this->chart" :height="340" />
</x-card>
