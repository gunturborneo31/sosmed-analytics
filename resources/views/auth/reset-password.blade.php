<x-layouts.guest title="Atur ulang kata sandi">
    <h1 class="font-display text-2xl font-semibold tracking-[-0.01em] text-ink-strong">Kata sandi baru</h1>

    <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-input label="Surel" name="email" type="email" :value="old('email', $email)" required readonly />
        <x-input label="Kata sandi baru" name="password" type="password" required autofocus autocomplete="new-password" />
        <x-input label="Ulangi kata sandi" name="password_confirmation" type="password" required autocomplete="new-password" />

        <x-button type="submit" size="lg" class="w-full">Simpan kata sandi</x-button>
    </form>
</x-layouts.guest>
