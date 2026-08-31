<div class="space-y-6">
    <x-page-header
        title="Log Sinkronisasi"
        description="Riwayat penarikan data dari Meta beserta kegagalannya."
    />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-tile :index="0" label="Berhasil · 7 hari" :value="number_format($this->tally['success'] ?? 0, 0, ',', '.')" />
        <x-stat-tile :index="1" label="Gagal · 7 hari" :value="number_format($this->tally['failed'] ?? 0, 0, ',', '.')" />
        <x-stat-tile :index="2" label="Sebagian · 7 hari" :value="number_format($this->tally['partial'] ?? 0, 0, ',', '.')" />
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-hairline bg-surface p-4 shadow-card">
        <x-select label="Status" wire:model.live="status" :options="$statuses" class="min-w-48" />
    </div>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-3xl text-sm">
                <thead>
                    <tr class="border-b border-hairline text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                        <th class="px-5 py-3 font-medium">Waktu</th>
                        <th class="px-5 py-3 font-medium">Akun</th>
                        <th class="px-5 py-3 font-medium">Pemicu</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Keterangan</th>
                        <th class="px-5 py-3 text-right font-medium">Durasi</th>
                        @can('trigger-manual-sync')
                            <th class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->logs as $log)
                        <tr wire:key="log-{{ $log->id }}" class="transition hover:bg-surface-sunken">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-ink-muted">
                                {{ $log->created_at->translatedFormat('j M, H:i') }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="block text-ink-strong">{{ $log->socialAccount?->display_name ?? '—' }}</span>
                                <span class="block text-[11px] text-ink-muted">
                                    {{ $log->socialAccount?->organizationalUnit?->name }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-ink-muted">{{ $log->trigger }}</td>
                            <td class="px-5 py-3">
                                <x-badge :tone="match ($log->status) {
                                    'success' => 'success',
                                    'partial' => 'warning',
                                    default => 'danger',
                                }">@switch($log->status)
                                    @case('success') Berhasil @break
                                    @case('partial') Sebagian @break
                                    @default Gagal
                                @endswitch</x-badge>
                            </td>
                            <td class="max-w-md px-5 py-3 text-xs text-ink">{{ $log->message ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-mono text-xs text-ink-muted">
                                {{ $log->duration_ms ? number_format($log->duration_ms, 0, ',', '.').' ms' : '—' }}
                            </td>
                            @can('trigger-manual-sync')
                                <td class="px-5 py-3 text-right">
                                    @if ($log->socialAccount)
                                        <x-button
                                            variant="ghost" size="sm"
                                            wire:click="retry('{{ $log->social_account_id }}')"
                                            wire:loading.attr="disabled"
                                        >Sinkron ulang</x-button>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state
                                    title="Belum ada riwayat sinkronisasi"
                                    description="Riwayat muncul setelah penjadwal atau sinkronisasi manual pertama berjalan."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->logs->hasPages())
            <div class="border-t border-hairline px-5 py-3">{{ $this->logs->links('vendor.pagination.kutim') }}</div>
        @endif
    </x-card>

    <p class="text-xs text-ink-muted">
        Log sengaja hanya menyimpan pesan kesalahan — payload berisi data pribadi tidak pernah ikut dicatat (§12).
    </p>
</div>
