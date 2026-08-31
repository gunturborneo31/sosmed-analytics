<div class="space-y-6" x-data="{ salinTeks(teks) { navigator.clipboard?.writeText(teks) } }">
    <x-page-header
        title="Kelola Pengguna"
        description="Akun operator dibuat di sini berdasarkan surat penunjukan admin medsos tiap OPD."
    >
        <x-slot:actions>
            @if ($this->unitsWithoutAccount->isNotEmpty())
                <x-button
                    variant="secondary"
                    wire:click="generateForAllUnits"
                    wire:loading.attr="disabled"
                >
                    <x-icon name="pengguna" class="size-4" />
                    Buatkan akun untuk {{ $this->unitsWithoutAccount->count() }} OPD
                </x-button>
            @endif

            <x-button wire:click="create">
                <x-icon name="tambah" class="size-4" />
                Tambah pengguna
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Kredensial baru — satu-satunya kesempatan kata sandinya terbaca,
         karena setelah ini hanya tersimpan sebagai hash. --}}
    @if ($justGenerated)
        {{-- Nilai dihitung lebih dulu di sini — meletakkan akses array
             ber-kutip langsung di dalam atribut HTML membentur kutip
             pembungkus atribut itu sendiri, apa pun kutip yang dipakai. --}}
        @php $teksSalinSemua = collect($justGenerated)->map(fn ($g) => $g['unit'].' — '.$g['email'].' — '.$g['password'])->join(PHP_EOL); @endphp

        <x-card class="border-warning/30 bg-warning/5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-display text-base font-semibold text-ink-strong">
                        {{ count($justGenerated) }} akun baru dibuat
                    </h2>
                    <p class="mt-1 text-xs leading-relaxed text-ink-muted">
                        Catat atau bagikan kata sandi ini sekarang — setelah halaman ini ditutup atau dimuat ulang,
                        kata sandinya tidak bisa dilihat lagi (hanya tersimpan sebagai hash).
                    </p>
                </div>
                <x-button
                    variant="ghost" size="sm"
                    x-on:click="salinTeks(@js($teksSalinSemua))"
                >Salin semua</x-button>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-hairline">
                <table class="w-full min-w-2xl text-sm">
                    <thead>
                        <tr class="border-b border-hairline bg-surface-sunken text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                            <th class="px-4 py-2.5 font-medium">Perangkat Daerah</th>
                            <th class="px-4 py-2.5 font-medium">Surel</th>
                            <th class="px-4 py-2.5 font-medium">Kata Sandi</th>
                            <th class="w-16 px-4 py-2.5"><span class="sr-only">Salin</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($justGenerated as $akun)
                            @php $teksBaris = $akun['email'].' / '.$akun['password']; @endphp
                            <tr wire:key="baru-{{ $loop->index }}">
                                <td class="px-4 py-2.5 text-ink-strong">{{ $akun['unit'] }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-ink">{{ $akun['email'] }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs font-semibold text-ink-strong">{{ $akun['password'] }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <x-button
                                        variant="ghost" size="sm"
                                        x-on:click="salinTeks(@js($teksBaris))"
                                    >Salin</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-button variant="ghost" size="sm" wire:click="dismissGenerated" class="mt-3">
                Sudah dicatat, sembunyikan
            </x-button>
        </x-card>
    @endif

    @if ($this->unitsWithoutAccount->isNotEmpty())
        <x-card
            title="Perangkat Daerah Belum Punya Akun"
            :subtitle="$this->unitsWithoutAccount->count().' OPD belum bisa masuk untuk menghubungkan medsosnya'"
            :padded="false"
        >
            <ul class="max-h-64 divide-y divide-hairline overflow-y-auto">
                @foreach ($this->unitsWithoutAccount as $unit)
                    <li wire:key="tanpa-akun-{{ $unit->id }}" class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <div class="min-w-0">
                            <span class="block truncate text-sm text-ink-strong">{{ $unit->name }}</span>
                            <span class="text-[11px] uppercase tracking-wide text-ink-muted">{{ $unit->type }}</span>
                        </div>
                        <x-button
                            variant="ghost" size="sm"
                            wire:click="generateForUnit('{{ $unit->id }}')"
                            wire:loading.attr="disabled"
                        >Buatkan akun</x-button>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="rounded-2xl border border-hairline bg-surface p-4 shadow-card">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari nama atau surel…"
            class="h-10 w-full max-w-sm rounded-xl border border-hairline bg-surface px-3.5 text-sm text-ink-strong placeholder:text-ink-muted/70 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12"
        >
    </div>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-4xl table-fixed text-sm">
                <thead>
                    <tr class="border-b border-hairline bg-surface-sunken/60 text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                        <th class="w-[26%] px-5 py-3 font-medium">Nama</th>
                        <th class="w-[28%] px-5 py-3 font-medium">Surel</th>
                        <th class="w-[14%] px-5 py-3 font-medium">Peran</th>
                        <th class="w-[24%] px-5 py-3 font-medium">Perangkat Daerah</th>
                        <th class="w-[8%] px-5 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hairline">
                    @foreach ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-surface-sunken">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium text-ink-strong" title="{{ $user->name }}">{{ $user->name }}</span>
                                    @if ($user->id === auth()->id())
                                        <x-badge tone="brand">akun Anda</x-badge>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-3">
                                {{-- Surel OPD hasil generate bisa sangat panjang; dipotong agar
                                     kolom lain tidak terdorong, isi penuhnya tetap ada di title. --}}
                                <span class="block truncate font-mono text-xs text-ink" title="{{ $user->email }}">{{ $user->email }}</span>
                            </td>

                            <td class="px-5 py-3">
                                <x-badge tone="brand">{{ $user->roles->first()?->name ?? 'tanpa peran' }}</x-badge>
                            </td>

                            <td class="px-5 py-3">
                                <span class="block truncate text-ink" title="{{ $user->organizationalUnit?->name ?? 'Tanpa OPD' }}">{{ $user->organizationalUnit?->name ?? '—' }}</span>
                            </td>

                            <td class="px-5 py-3">
                                {{-- Aksi berupa ikon: baris tetap ringkas walau daftarnya panjang.
                                     Tiap tombol tetap punya label tersembunyi untuk pembaca layar. --}}
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        wire:click="edit('{{ $user->id }}')"
                                        class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-brand-50 hover:text-brand-700"
                                        title="Ubah data {{ $user->name }}"
                                    >
                                        <x-icon name="pensil" class="size-[17px]" />
                                        <span class="sr-only">Ubah {{ $user->name }}</span>
                                    </button>

                                    {{-- Akun sendiri tidak boleh dihapus. Tombolnya sengaja
                                         dinonaktifkan, bukan disembunyikan, supaya alasannya
                                         tetap bisa dibaca lewat tooltip. --}}
                                    @if ($user->id === auth()->id())
                                        <span
                                            class="grid size-8 cursor-not-allowed place-items-center rounded-lg text-ink-muted/35"
                                            title="Tidak bisa dihapus: ini akun yang sedang Anda pakai."
                                        >
                                            <x-icon name="sampah" class="size-[17px]" />
                                            <span class="sr-only">Akun Anda sendiri tidak bisa dihapus</span>
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $user->id }}')"
                                            class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-danger/10 hover:text-danger"
                                            title="Hapus {{ $user->name }}"
                                        >
                                            <x-icon name="sampah" class="size-[17px]" />
                                            <span class="sr-only">Hapus {{ $user->name }}</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->users->hasPages())
            <div class="border-t border-hairline px-5 py-3">{{ $this->users->links('vendor.pagination.kutim') }}</div>
        @endif
    </x-card>

    {{-- ─────────────── Modal tambah / ubah ─────────────── --}}
    <x-modal
        property="showForm"
        :title="$editingId ? 'Ubah Pengguna' : 'Pengguna Baru'"
        :subtitle="$editingId
            ? 'Kosongkan kata sandi bila tidak ingin menggantinya.'
            : 'Pilih perangkat daerah, lalu surel dan kata sandinya bisa dibuatkan otomatis.'"
        :icon="$editingId ? 'pensil' : 'tambah'"
        width="max-w-2xl"
    >
        <form wire:submit="save">
            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <x-input label="Nama" name="name" wire:model="name" required />

                <div class="space-y-1.5">
                    <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Peran</span>
                    <select wire:model="role" class="block h-11 w-full rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5 sm:col-span-2">
                    <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">Perangkat Daerah</span>
                    <select wire:model="organizationalUnitId" class="block h-11 w-full rounded-xl border border-hairline bg-surface px-3 text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12">
                        @foreach ($units as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('organizationalUnitId') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="text-xs text-ink-muted">
                        Operator OPD hanya bisa melihat data instansi yang dipilih di sini.
                    </p>
                </div>

                @unless ($editingId)
                    <div class="sm:col-span-2">
                        <x-button
                            type="button" variant="secondary" size="sm"
                            wire:click="autofillCredentials"
                            wire:loading.attr="disabled"
                        >
                            Buatkan surel &amp; kata sandi otomatis dari OPD
                        </x-button>
                    </div>
                @endunless

                <x-input label="Surel" name="email" type="email" wire:model="email" required
                    hint="Untuk operator OPD, biasanya dibuatkan otomatis dari tombol di atas." />

                <div x-data="{ tampil: false }">
                    <label class="block space-y-1.5">
                        <span class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">
                            Kata sandi{{ $editingId ? ' (kosongkan bila tidak diubah)' : ' awal' }}
                        </span>
                        <div class="relative">
                            {{-- Ruang di kanan disisakan untuk dua tombol: buatkan ulang
                                 dan tampilkan/sembunyikan. --}}
                            <input
                                :type="tampil ? 'text' : 'password'"
                                wire:model="password"
                                class="block h-11 w-full rounded-xl border border-hairline bg-surface px-3.5 pr-[4.75rem] font-mono text-sm text-ink-strong focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/12"
                            >

                            <div class="absolute inset-y-0 right-1.5 flex items-center gap-0.5">
                                <button
                                    type="button"
                                    wire:click="generatePassword"
                                    wire:loading.attr="disabled"
                                    x-on:click="tampil = true"
                                    class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-brand-50 hover:text-brand-700"
                                    title="Buatkan kata sandi {{ \App\Livewire\Admin\UserDirectory::PANJANG_SANDI }} karakter"
                                >
                                    <x-icon name="sinkron" class="size-[15px]" />
                                    <span class="sr-only">Buatkan kata sandi otomatis</span>
                                </button>

                                <button
                                    type="button" x-on:click="tampil = !tampil"
                                    class="grid size-8 place-items-center rounded-lg text-ink transition hover:bg-surface-sunken hover:text-ink-strong"
                                    :title="tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                >
                                    <x-icon name="gembok-buka" class="size-[15px]" x-show="tampil" />
                                    <x-icon name="gembok-kunci" class="size-[15px]" x-show="!tampil" />
                                    <span class="sr-only" x-text="tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">Tampilkan kata sandi</span>
                                </button>
                            </div>
                        </div>
                    </label>
                    @error('password')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1 text-xs text-ink-muted">
                            Minimal {{ \App\Livewire\Admin\UserDirectory::PANJANG_SANDI }} karakter.
                            Tombol <span class="font-medium text-ink">↻</span> membuatkannya otomatis.
                        </p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-hairline bg-surface-sunken/40 px-6 py-4">
                <x-button type="button" variant="ghost" wire:click="$set('showForm', false)">Batal</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Simpan</x-button>
            </div>
        </form>
    </x-modal>

    {{-- ─────────────── Modal konfirmasi hapus ─────────────── --}}
    <x-modal
        property="confirmingDelete"
        title="Hapus pengguna?"
        tone="danger"
        icon="peringatan"
        width="max-w-md"
    >
        <div class="px-6 py-5">
            <p class="text-sm leading-relaxed text-ink">
                @if ($this->deletingUser)
                    <strong class="font-semibold text-ink-strong">{{ $this->deletingUser->name }}</strong>
                    akan kehilangan akses masuk ke aplikasi ini.
                @else
                    Pengguna ini akan kehilangan akses masuk ke aplikasi ini.
                @endif
            </p>

            @if ($this->deletingUser)
                <dl class="mt-3 space-y-1 rounded-xl bg-surface-sunken px-3.5 py-3 text-xs">
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0 text-ink-muted">Surel</dt>
                        <dd class="min-w-0 flex-1 truncate font-mono text-ink-strong">{{ $this->deletingUser->email }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0 text-ink-muted">Instansi</dt>
                        <dd class="min-w-0 flex-1 truncate text-ink-strong">
                            {{ $this->deletingUser->organizationalUnit?->name ?? 'Tanpa OPD' }}
                        </dd>
                    </div>
                </dl>
            @endif

            <p class="mt-3 text-xs leading-relaxed text-ink-muted">
                Tindakan ini tidak bisa dibatalkan. Data insight instansinya tetap utuh —
                yang hilang hanya akun untuk masuk.
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-hairline bg-surface-sunken/40 px-6 py-4">
            <x-button type="button" variant="ghost" wire:click="$set('confirmingDelete', false)">Batal</x-button>
            @if ($this->deletingUser)
                <x-button
                    type="button" variant="danger"
                    wire:click="delete('{{ $this->deletingUser->id }}')"
                    wire:loading.attr="disabled"
                >
                    <x-icon name="sampah" class="size-4" />
                    Ya, hapus
                </x-button>
            @endif
        </div>
    </x-modal>
</div>
