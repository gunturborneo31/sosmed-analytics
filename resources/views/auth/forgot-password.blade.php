<x-layouts.guest title="Lupa kata sandi">
    <h1 class="font-display text-2xl font-semibold tracking-[-0.01em] text-ink-strong">Lupa kata sandi</h1>
    <p class="mt-1.5 text-sm text-ink">
        Masukkan surel akunmu, kami kirimkan tautan untuk membuat kata sandi baru.
    </p>

    <x-flash class="mt-6" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
        @csrf
        <x-input label="Surel" name="email" type="email" :value="old('email')" required autofocus />
        <x-button type="submit" size="lg" class="w-full">Kirim tautan</x-button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 inline-block text-xs font-medium text-brand-700 hover:underline">
        &larr; Kembali ke halaman masuk
    </a>
</x-layouts.guest>
