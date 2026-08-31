<x-card title="Sebaran Wilayah" subtitle="Kota/kabupaten asal audiens">
    @php
        $cities = $this->cities;
        $max = max($cities->max() ?? 1, 1);
        $total = max($cities->sum(), 1);
    @endphp

    <ul class="space-y-3">
        @forelse ($cities as $city => $count)
            <li>
                <div class="flex items-baseline justify-between gap-3">
                    <span class="truncate text-sm text-ink">{{ $city }}</span>
                    <span class="shrink-0 font-mono text-xs text-ink-muted">
                        {{ number_format($count, 0, ',', '.') }}
                        <span class="text-ink-muted/70">· {{ number_format($count / $total * 100, 1, ',', '.') }}%</span>
                    </span>
                </div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-sunken">
                    {{-- Lebar dari server, animasi lewat transform — lihat catatan
                         yang sama di unit-detail. --}}
                    <div
                        class="h-full origin-left rounded-full bg-brand-500 animate-tumbuh"
                        style="width: {{ round($count / $max * 100, 1) }}%"
                    ></div>
                </div>
            </li>
        @empty
            <li>
                <x-empty-state
                    title="Belum ada data wilayah"
                    description="Breakdown kota baru tersedia setelah sinkronisasi demografi berjalan."
                />
            </li>
        @endforelse
    </ul>
</x-card>
