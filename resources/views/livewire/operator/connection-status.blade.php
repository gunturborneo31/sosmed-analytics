<div
    class="space-y-6"
    @if ($this->sedangBerjalan) wire:poll.2s @endif
>
    <x-page-header
        title="Akun Media Sosial"
        :description="auth()->user()->organizationalUnit?->name ?? 'Perangkat daerah belum ditentukan'"
    >
        <x-slot:actions>
            @can('connect-social-account')
                <x-button wire:click="$set('showConnect', true)" size="lg">
                    <x-icon name="tambah" class="size-4" />
                    Hubungkan Instagram / Facebook
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($this->metaConfigured)
        <div class="rounded-2xl border border-warning/25 bg-warning/10 px-5 py-4 text-sm text-warning">
            <p class="font-medium">Kredensial Meta belum dikonfigurasi.</p>
            <p class="mt-1 text-xs leading-relaxed">
                Isi <span class="font-mono">META_APP_ID</span> dan <span class="font-mono">META_APP_SECRET</span>
                di berkas <span class="font-mono">.env</span> terlebih dahulu. Tombol hubungkan belum akan berfungsi
                sebelum itu.
            </p>
        </div>
    @endunless

    <x-card title="Akun Terhubung" :padded="false">
        <ul class="divide-y divide-hairline">
            @forelse ($this->accounts as $account)
                <li wire:key="akun-{{ $account->id }}" class="px-5 py-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <x-platform-badge :platform="$account->platform" />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink-strong">
                                {{ $account->display_name ?? $account->username ?? 'Tanpa nama' }}
                            </p>

                            {{-- Nama platform dan username diberi warna mereknya sendiri,
                                 supaya operator langsung tahu kanal mana yang terhubung. --}}
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
                                <span class="font-semibold {{ \App\Support\SocialPlatform::textClass($account->platform) }}">
                                    {{ \App\Support\SocialPlatform::label($account->platform) }}
                                </span>

                                <span class="truncate font-mono {{ $account->username ? \App\Support\SocialPlatform::textClass($account->platform) : 'text-ink-muted' }}">
                                    {{ $account->username ? '@'.$account->username : $account->platform_account_id }}
                                </span>

                                <span class="text-ink-muted">· {{ $account->insight_snapshots_count }} hari data</span>
                            </p>
                        </div>

                        <div class="text-right">
                            <x-status-badge :status="$account->status" />
                            <p class="mt-1 font-mono text-[11px] text-ink-muted">
                                {{ $account->last_synced_at?->diffForHumans() ?? 'belum pernah disinkron' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-1">
                            @php $sedang = isset($this->progress[$account->id]) && $this->progress[$account->id]['status'] !== \App\Support\SyncProgress::STATUS_SELESAI; @endphp
                            <x-button
                                variant="ghost"
                                size="sm"
                                wire:click="syncNow('{{ $account->id }}')"
                                wire:loading.attr="disabled"
                                :disabled="$sedang"
                            >
                                {{ $sedang ? 'Sedang berjalan…' : 'Sinkron sekarang' }}
                            </x-button>

                            <form method="POST" action="{{ route('oauth.meta.disconnect', $account) }}"
                                  onsubmit="return confirm('Putuskan akun ini? Seluruh data insight yang tersimpan akan ikut terhapus.')">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="ghost-danger" size="sm">Putuskan</x-button>
                            </form>
                        </div>
                    </div>

                    @isset($this->progress[$account->id])
                        <x-sync-progress
                            :progress="$this->progress[$account->id]"
                            :account-id="$account->id"
                            class="mt-3"
                        />
                    @endisset
                </li>
            @empty
                <li>
                    <x-empty-state
                        title="Belum ada akun terhubung"
                        description="Hubungkan akun Instagram atau Facebook instansimu untuk mulai memantau performa."
                    >
                        <x-slot:action>
                            {{-- Dulu di sini hanya ada satu tombol "Hubungkan sekarang" yang
                                 langsung menuju jalur Facebook — operator yang ingin Instagram
                                 tidak punya jalan dari kartu ini. Sekarang dipisah per kanal,
                                 dengan syarat kesiapan yang sama seperti tombol di header. --}}
                            @can('connect-social-account')
                                <div class="flex flex-wrap items-center justify-center gap-2.5">
                                    <x-connect-button
                                        platform="instagram"
                                        :href="route('oauth.instagram.redirect')"
                                        :ready="$this->instagramLoginReady"
                                    />
                                    <x-connect-button
                                        platform="facebook"
                                        :href="route('oauth.meta.redirect', ['platform' => 'facebook'])"
                                        :ready="$this->metaConfigured"
                                    />
                                </div>
                            @endcan
                        </x-slot:action>
                    </x-empty-state>
                </li>
            @endforelse
        </ul>
    </x-card>

    @if ($this->recentLogs->isNotEmpty())
        <x-card title="Riwayat Sinkronisasi Terakhir" :padded="false">
            <ul class="divide-y divide-hairline">
                @foreach ($this->recentLogs as $log)
                    <li wire:key="log-{{ $log->id }}" class="flex items-center gap-4 px-5 py-3">
                        <x-badge :tone="$log->status === 'success' ? 'success' : ($log->status === 'partial' ? 'warning' : 'danger')">
                            @switch($log->status)
                                @case('success') Berhasil @break
                                @case('partial') Sebagian @break
                                @default Gagal
                            @endswitch
                        </x-badge>
                        <span class="flex-1 truncate text-xs text-ink">{{ $log->message ?? 'Data berhasil diperbarui.' }}</span>
                        <span class="shrink-0 font-mono text-[11px] text-ink-muted">
                            {{ $log->created_at->translatedFormat('j M, H:i') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @can('connect-social-account')
        {{-- ─────────────── Modal pilih platform ─────────────── --}}
        <x-modal
            property="showConnect"
            title="Hubungkan akun media sosial"
            subtitle="Pilih kanal mana yang datanya akan dibagikan ke Diskominfo."
            width="max-w-lg"
        >
            <div class="space-y-3 px-6 py-5">
                @foreach ([
                    [
                        'platform' => 'instagram',
                        'rute' => route('oauth.instagram.redirect'),
                        'judul' => 'Instagram',
                        'ringkas' => 'Masuk dengan akun Instagram instansi.',
                        'siap' => $this->instagramLoginReady,
                        'syarat' => [
                            'Akun bertipe Business atau Creator — akun personal tidak punya data insight sama sekali.',
                            'Tidak perlu Facebook Page.',
                        ],
                    ],
                    [
                        'platform' => 'facebook',
                        'rute' => route('oauth.meta.redirect', ['platform' => 'facebook']),
                        'judul' => 'Facebook Page',
                        'ringkas' => 'Masuk dengan akun Facebook pengelola Page.',
                        'siap' => $this->metaConfigured,
                        'syarat' => [
                            'Berupa Facebook Page instansi, bukan profil pribadi.',
                            'Kamu terdaftar sebagai admin Page tersebut.',
                        ],
                    ],
                ] as $pilihan)
                    <a
                        href="{{ $pilihan['rute'] }}"
                        @class([
                            'group/pilih flex items-start gap-4 rounded-2xl border p-4 transition',
                            'border-hairline hover:border-brand-300 hover:bg-surface-sunken' => $pilihan['siap'],
                            'pointer-events-none border-hairline opacity-55' => ! $pilihan['siap'],
                        ])
                        @unless ($pilihan['siap']) aria-disabled="true" tabindex="-1" @endunless
                    >
                        <x-platform-badge :platform="$pilihan['platform']" size="size-11" />

                        <div class="min-w-0 flex-1">
                            <p class="font-display text-sm font-semibold {{ \App\Support\SocialPlatform::textClass($pilihan['platform']) }}">
                                {{ $pilihan['judul'] }}
                            </p>
                            <p class="mt-0.5 text-xs text-ink">{{ $pilihan['ringkas'] }}</p>

                            {{-- Syarat ditampilkan per platform, hanya yang benar-benar
                                 berlaku untuk kanal itu. --}}
                            <ul class="mt-2 space-y-1">
                                @foreach ($pilihan['syarat'] as $syarat)
                                    <li class="flex gap-1.5 text-[11px] leading-relaxed text-ink-muted">
                                        <span aria-hidden="true">&bull;</span>
                                        <span>{{ $syarat }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @unless ($pilihan['siap'])
                                <p class="mt-2 text-[11px] font-medium text-warning">
                                    Kredensial untuk jalur ini belum diisi di .env.
                                </p>
                            @endunless
                        </div>

                        <x-icon
                            name="panah-kiri"
                            class="mt-1 size-4 rotate-180 shrink-0 text-ink-muted transition group-hover/pilih:text-brand-700"
                        />
                    </a>
                @endforeach

                {{-- Instagram yang sudah tertaut Facebook Page bisa ikut lewat jalur
                     Facebook — berguna kalau Instagram Login belum disiapkan. --}}
                @if ($this->metaConfigured)
                    <a
                        href="{{ route('oauth.meta.redirect', ['platform' => 'instagram']) }}"
                        class="block rounded-xl border border-dashed border-hairline px-3.5 py-3 text-center text-[11px] leading-relaxed text-ink-muted transition hover:border-brand-300 hover:text-brand-700"
                    >
                        Instagram sudah tertaut Facebook Page?
                        <span class="font-medium">Hubungkan lewat akun Facebook saja &rarr;</span>
                    </a>
                @endif

                <p class="text-[11px] leading-relaxed text-ink-muted">
                    Kamu akan dibawa ke halaman resmi Meta untuk masuk dan menyetujui izinnya.
                    Kata sandi media sosialmu tidak pernah melewati aplikasi ini.
                </p>
            </div>
        </x-modal>
    @endcan
</div>
