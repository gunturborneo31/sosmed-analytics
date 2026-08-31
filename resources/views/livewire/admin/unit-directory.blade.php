<div class="space-y-6">
    <x-page-header
        title="Perangkat Daerah"
        description="Kelola daftar OPD dan pantau status koneksi akun media sosialnya."
    >
        <x-slot:actions>
            @can('manage-organizational-units')
                <x-button wire:click="create">
                    <x-icon name="tambah" class="size-4" />
                    Tambah perangkat daerah
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Catatan pendampingan dipindah ke sini dari laporan PDF: isinya urusan
         operasional internal, dan di halaman inilah tindak lanjutnya dikerjakan.
         Tiap angka menautkan langsung ke daftar yang sudah tersaring. --}}
    @php
        $perhatian = $this->attention;
        $totalPerhatian = array_sum($perhatian);
    @endphp

    <x-card
        title="Perlu Pendampingan"
        subtitle="Bahan pendampingan teknis, bukan penilaian kinerja"
    >
        <x-slot:actions>
            @can('trigger-manual-sync')
                <x-button
                    variant="secondary"
                    wire:click="syncAll"
                    wire:loading.attr="disabled"
                    wire:target="syncAll"
                >
                    <x-icon name="sinkron" class="size-4" />
                    <span wire:loading.remove wire:target="syncAll">Sinkron semua akun</span>
                    <span wire:loading wire:target="syncAll">Mengantrekan…</span>
                </x-button>
            @endcan
        </x-slot:actions>

        @if ($totalPerhatian === 0)
            <p class="rounded-xl bg-success/10 px-4 py-3 text-sm text-success">
                Semua perangkat daerah sudah terhubung dan akunnya sehat. Tidak ada yang perlu didampingi saat ini.
            </p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['unconnected', 'Belum menghubungkan akun', 'perangkat daerah', 'warning', route('admin.units', ['status' => 'belum'])],
                    ['expiring', 'Token kedaluwarsa dalam 7 hari', 'akun', 'warning', route('admin.units', ['status' => 'bermasalah'])],
                    ['stale', 'Tanpa sinkronisasi 30 hari', 'akun', 'neutral', route('admin.sync-logs')],
                    ['failed_syncs', 'Sinkronisasi gagal 7 hari terakhir', 'kejadian', 'danger', route('admin.sync-logs', ['status' => 'failed'])],
                ] as [$kunci, $label, $satuan, $nada, $tautan])
                    @php $jumlah = $perhatian[$kunci]; @endphp
                    <a
                        href="{{ $tautan }}"
                        @class([
                            'block rounded-xl border p-3.5 transition hover:bg-surface-sunken',
                            'border-warning/30 bg-warning/5' => $jumlah > 0 && $nada === 'warning',
                            'border-danger/30 bg-danger/5' => $jumlah > 0 && $nada === 'danger',
                            'border-hairline' => $jumlah === 0 || $nada === 'neutral',
                        ])
                    >
                        <p class="flex items-baseline gap-1.5">
                            <span @class([
                                'font-display text-2xl font-bold',
                                'text-warning' => $jumlah > 0 && $nada === 'warning',
                                'text-danger' => $jumlah > 0 && $nada === 'danger',
                                'text-ink-strong' => $jumlah > 0 && $nada === 'neutral',
                                'text-ink-muted' => $jumlah === 0,
                            ])>{{ $jumlah }}</span>
                            <span class="text-[11px] text-ink-muted">{{ $satuan }}</span>
                        </p>
                        <p class="mt-0.5 text-xs leading-snug text-ink">{{ $label }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-hairline bg-surface p-4 shadow-card">
        <label class="min-w-64 flex-1 space-y-1.5">
            <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Cari</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Nama dinas, badan, atau kecamatan…"
                class="h-11 w-full rounded-xl border border-hairline bg-surface px-3.5 text-sm text-ink-strong placeholder:text-ink-muted/70 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12"
            >
        </label>

        <x-select label="Jenis" wire:model.live="unitType" :options="$unitTypes" class="min-w-40" />
        <x-select label="Status" wire:model.live="status" :options="$statuses" class="min-w-44" />
    </div>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-3xl text-sm">
                <thead>
                    <tr class="border-b border-hairline bg-surface-sunken/60 text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                        <th class="px-5 py-3 font-medium">Perangkat Daerah</th>
                        <th class="w-32 px-5 py-3 font-medium">Jenis</th>
                        <th class="w-56 px-5 py-3 font-medium">Status Koneksi</th>
                        <th class="w-20 px-5 py-3 text-center font-medium">Akun</th>
                        @can('manage-organizational-units')
                            <th class="w-36 px-5 py-3 text-right font-medium">Aksi</th>
                        @endcan
                    </tr>
                </thead>

                <tbody class="divide-y divide-hairline">
                    @forelse ($this->units as $unit)
                        <tr wire:key="opd-{{ $unit->id }}" class="group/baris transition hover:bg-surface-sunken">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ route('admin.units.show', $unit) }}"
                                        class="truncate font-medium text-ink-strong transition hover:text-brand-700 hover:underline"
                                    >{{ $unit->name }}</a>

                                    @unless ($unit->is_active)
                                        <x-badge tone="neutral">nonaktif</x-badge>
                                    @endunless
                                </div>

                                @if ($unit->contact_person)
                                    <span class="mt-0.5 block truncate text-[11px] text-ink-muted">
                                        PIC: {{ $unit->contact_person }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-3">
                                <span class="text-xs uppercase tracking-wide text-ink-muted">{{ $unit->type }}</span>
                            </td>

                            <td class="px-5 py-3">
                                @if ($unit->problem_accounts > 0)
                                    <x-badge tone="warning">{{ $unit->problem_accounts }} akun perlu perbaikan</x-badge>
                                @elseif ($unit->connected_accounts > 0)
                                    <x-badge tone="success">Terhubung</x-badge>
                                @else
                                    <x-badge tone="neutral">Belum terhubung</x-badge>
                                @endif
                            </td>

                            <td class="px-5 py-3 text-center font-mono text-ink">{{ $unit->connected_accounts }}</td>

                            @can('manage-organizational-units')
                                <td class="px-5 py-3">
                                    {{-- Aksi berupa ikon: baris tetap ringkas walau daftarnya panjang.
                                         Tiap tombol tetap punya label tersembunyi untuk pembaca layar. --}}
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            wire:click="edit('{{ $unit->id }}')"
                                            class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-brand-50 hover:text-brand-700"
                                            title="Ubah data {{ $unit->name }}"
                                        >
                                            <x-icon name="pensil" class="size-[17px]" />
                                            <span class="sr-only">Ubah {{ $unit->name }}</span>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="toggleActive('{{ $unit->id }}')"
                                            class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-surface-sunken hover:text-ink-strong"
                                            title="{{ $unit->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }} {{ $unit->name }}"
                                        >
                                            <x-icon :name="$unit->is_active ? 'gembok-buka' : 'gembok-kunci'" class="size-[17px]" />
                                            <span class="sr-only">
                                                {{ $unit->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $unit->name }}
                                            </span>
                                        </button>

                                        {{-- Menghapus OPD yang masih punya akun akan memusnahkan
                                             seluruh riwayat insight-nya, jadi tombolnya sengaja
                                             dinonaktifkan, bukan disembunyikan — supaya alasannya
                                             tetap bisa dibaca lewat tooltip. --}}
                                        @if ($unit->total_accounts === 0 && $unit->total_users === 0)
                                            <button
                                                type="button"
                                                wire:click="confirmDelete('{{ $unit->id }}')"
                                                class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-danger/10 hover:text-danger"
                                                title="Hapus {{ $unit->name }}"
                                            >
                                                <x-icon name="sampah" class="size-[17px]" />
                                                <span class="sr-only">Hapus {{ $unit->name }}</span>
                                            </button>
                                        @else
                                            <span
                                                class="grid size-8 cursor-not-allowed place-items-center rounded-lg text-ink-muted/35"
                                                title="Tidak bisa dihapus: masih punya {{ $unit->total_accounts }} akun medsos dan {{ $unit->total_users }} pengguna. Nonaktifkan saja agar riwayat datanya utuh."
                                            >
                                                <x-icon name="sampah" class="size-[17px]" />
                                                <span class="sr-only">
                                                    {{ $unit->name }} tidak bisa dihapus karena masih punya akun atau pengguna
                                                </span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state title="Tidak ada OPD yang cocok" description="Coba ubah kata kunci atau longgarkan filter." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->units->hasPages())
            <div class="border-t border-hairline px-5 py-3">{{ $this->units->links('vendor.pagination.kutim') }}</div>
        @endif
    </x-card>

    @can('manage-organizational-units')
        {{-- ─────────────── Modal tambah / ubah ─────────────── --}}
        <x-modal
            property="showForm"
            :title="$editingId ? 'Ubah Perangkat Daerah' : 'Perangkat Daerah Baru'"
            :subtitle="$editingId
                ? 'Perubahan nama tidak mengubah tautan detail yang sudah dibagikan.'
                : 'Slug tautan dibuat otomatis dari nama.'"
            :icon="$editingId ? 'pensil' : 'tambah'"
            width="max-w-2xl"
        >
            <form wire:submit="save">
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input label="Nama" name="name" wire:model="name" required
                            placeholder="Dinas Kesehatan / Kecamatan Bengalon" />
                    </div>

                    <div class="space-y-1.5">
                        <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Jenis</span>
                        <select wire:model.live="type" class="block h-11 w-full rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                            @foreach ($formTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    @if ($type === 'kecamatan')
                        <div>
                            <x-input
                                label="Nama kecamatan" name="district" wire:model="district" required
                                list="daftar-kecamatan"
                                hint="Harus sama persis dengan nama di Peta Denyut agar titiknya muncul."
                            />
                            <datalist id="daftar-kecamatan">
                                @foreach ($this->knownDistricts as $kecamatan)
                                    <option value="{{ $kecamatan }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    @endif

                    <x-input label="Nama PIC" name="contactPerson" wire:model="contactPerson" placeholder="Opsional" />
                    <x-input label="Telepon PIC" name="contactPhone" wire:model="contactPhone" placeholder="Opsional" />

                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-hairline bg-surface-sunken/60 px-3.5 py-3 text-sm text-ink sm:col-span-2">
                        <input type="checkbox" wire:model="isActive" class="mt-0.5 size-4 rounded border-hairline text-brand-500 focus:ring-brand-500/25">
                        <span>
                            <span class="block font-medium text-ink-strong">Aktif</span>
                            <span class="block text-xs text-ink-muted">Ikut dihitung dalam rekap, peringkat</span>
                        </span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-hairline bg-surface-sunken/40 px-6 py-4">
                    <x-button type="button" variant="ghost" wire:click="cancel">Batal</x-button>
                    <x-button type="submit" wire:loading.attr="disabled">Simpan</x-button>
                </div>
            </form>
        </x-modal>

        {{-- ─────────────── Modal konfirmasi hapus ─────────────── --}}
        <x-modal
            property="confirmingDelete"
            title="Hapus perangkat daerah?"
            tone="danger"
            icon="peringatan"
            width="max-w-md"
        >
            <div class="px-6 py-5">
                <p class="text-sm leading-relaxed text-ink">
                    @if ($this->deletingUnit)
                        <strong class="font-semibold text-ink-strong">{{ $this->deletingUnit->name }}</strong>
                        akan dihapus permanen dari daftar perangkat daerah.
                    @else
                        Perangkat daerah ini akan dihapus permanen.
                    @endif
                </p>
                <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                    Tindakan ini tidak bisa dibatalkan. Kalau OPD ini hanya sedang tidak dipakai,
                    lebih aman menonaktifkannya agar datanya tetap tersimpan.
                </p>
            </div>

            <div class="flex justify-end gap-2 border-t border-hairline bg-surface-sunken/40 px-6 py-4">
                <x-button type="button" variant="ghost" wire:click="$set('confirmingDelete', false)">Batal</x-button>
                @if ($this->deletingUnit)
                    <x-button
                        type="button" variant="danger"
                        wire:click="delete('{{ $this->deletingUnit->id }}')"
                        wire:loading.attr="disabled"
                    >
                        <x-icon name="sampah" class="size-4" />
                        Ya, hapus
                    </x-button>
                @endif
            </div>
        </x-modal>
    @endcan
</div>
