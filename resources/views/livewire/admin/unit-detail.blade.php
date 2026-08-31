<div class="space-y-6">
    <div>
        <a href="{{ route('admin.units') }}" class="text-xs font-medium text-brand-700 hover:underline">
            &larr; Perangkat Daerah
        </a>
        <x-page-header
            class="mt-2"
            :title="$unit->name"
            :description="ucfirst($unit->type).($unit->contact_person ? ' · PIC: '.$unit->contact_person : '')"
        >
            <x-slot:actions>
                <x-badge tone="brand">{{ $this->period()->label() }}</x-badge>
            </x-slot:actions>
        </x-page-header>
    </div>

    {{-- Tanpa saringan jenis OPD: halaman ini sudah terkunci pada satu OPD,
         jadi saringan itu tidak pernah mengubah apa pun di layar. --}}
    <x-filter-bar :period="$period" :platforms="$platforms" :show-unit-type="false" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile :index="0" label="Akun Terhubung" :value="(string) $this->summary['accounts_connected']" />
        <x-stat-tile :index="1" label="Pengikut" value="" :numeric="$this->summary['followers']" :delta="$this->summary['followers_delta']" show-delta caption="vs periode lalu" />
        <x-stat-tile :index="2" label="Jangkauan" value="" :numeric="$this->summary['reach']" :delta="$this->summary['reach_delta']" show-delta caption="akumulasi" />
        <x-stat-tile :index="3" label="Engagement" :value="number_format($this->summary['engagement_rate'], 2, ',', '.').'%'" />
    </div>

    <x-card title="Akun Media Sosial" :padded="false">
        <ul class="divide-y divide-hairline">
            @forelse ($this->accounts as $account)
                <li wire:key="akun-{{ $account->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <x-platform-badge :platform="$account->platform" size="size-9" />

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink-strong">
                            {{ $account->display_name ?? $account->username ?? 'Tanpa nama' }}
                        </p>
                        <p class="flex flex-wrap items-center gap-x-2 text-[11px]">
                            <span class="font-semibold {{ \App\Support\SocialPlatform::textClass($account->platform) }}">
                                {{ \App\Support\SocialPlatform::label($account->platform) }}
                            </span>
                            <span class="truncate font-mono {{ $account->username ? \App\Support\SocialPlatform::textClass($account->platform) : 'text-ink-muted' }}">
                                {{ $account->username ? '@'.$account->username : $account->platform_account_id }}
                            </span>
                        </p>
                    </div>
                    <x-status-badge :status="$account->status" />
                    <span class="font-mono text-[11px] text-ink-muted">
                        {{ $account->last_synced_at?->diffForHumans() ?? 'belum pernah disinkron' }}
                    </span>
                </li>
            @empty
                <li>
                    <x-empty-state
                        title="Belum ada akun terhubung"
                        description="OPD ini perlu didampingi menghubungkan akun Instagram/Facebook-nya."
                    />
                </li>
            @endforelse
        </ul>
    </x-card>

    <x-card title="Tren Performa" :subtitle="$this->period()->label()">
        <x-chart id="tren-opd" :options="$this->trendChart" :height="280" />
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Profil Usia Audiens" subtitle="Usia 16–64 tahun">
            @php $ageMax = max($this->ageProfile->max() ?? 1, 1); @endphp
            <ul class="space-y-3">
                @foreach ($this->ageProfile as $group => $count)
                    <li class="flex items-center gap-3">
                        <span class="w-14 shrink-0 font-mono text-xs text-ink-muted">{{ $group }}</span>
                        <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-sunken">
                            {{-- Lebar ditulis server; animasinya memakai transform,
                                 jadi batangnya tetap benar walau animasi tak jalan.
                                 Dulu lebarnya diisi JavaScript dan hilang tiap kali
                                 filter diganti. --}}
                            <span
                                class="block h-full origin-left rounded-full bg-brand-500 animate-tumbuh"
                                style="width: {{ round($count / $ageMax * 100, 1) }}%"
                            ></span>
                        </span>
                        <span class="w-20 shrink-0 text-right font-mono text-xs text-ink">
                            {{ number_format($count, 0, ',', '.') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Sebaran Wilayah">
            @php $cityMax = max($this->cityProfile->max() ?? 1, 1); @endphp
            <ul class="space-y-3">
                @forelse ($this->cityProfile as $city => $count)
                    <li class="flex items-center gap-3">
                        <span class="w-28 shrink-0 truncate text-xs text-ink">{{ $city }}</span>
                        <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-sunken">
                            <span
                                class="block h-full origin-left rounded-full bg-brand-300 animate-tumbuh"
                                style="width: {{ round($count / $cityMax * 100, 1) }}%"
                            ></span>
                        </span>
                        <span class="w-20 shrink-0 text-right font-mono text-xs text-ink">
                            {{ number_format($count, 0, ',', '.') }}
                        </span>
                    </li>
                @empty
                    <li><x-empty-state title="Belum ada data wilayah" /></li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
