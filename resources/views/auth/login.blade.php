<x-layouts.guest title="Masuk">
    <x-flash />

    {{-- Tombol mengunci diri saat dikirim: mencegah kiriman ganda sekaligus
         menutup jeda antara klik dan layar transisi. --}}
    <form
        method="POST" action="{{ route('login.store') }}" class="space-y-5"
        x-data="{ mengirim: false }"
        @submit="mengirim = true"
    >
        @csrf

        <x-input label="Surel" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" placeholder="nama@kutimkab.go.id" />
        <x-input label="Kata sandi" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" />

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-xs text-ink">
                <input type="checkbox" name="remember" class="size-4 rounded border-hairline text-brand-500 focus:ring-brand-500/25">
                Ingat saya
            </label>

            <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-700 hover:underline">
                Lupa kata sandi?
            </a>
        </div>

        <x-button type="submit" size="lg" class="w-full" x-bind:disabled="mengirim">
            <svg x-show="mengirim" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".3"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span x-text="mengirim ? 'Memeriksa…' : 'Masuk'">Masuk</span>
        </x-button>
    </form>
</x-layouts.guest>
