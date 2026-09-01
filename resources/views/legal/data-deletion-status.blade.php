<x-layouts.guest title="Status Penghapusan Data">
    <div class="space-y-4 text-center">
        <h1 class="font-display text-lg font-bold text-ink-strong">Permintaan hapus data diterima</h1>

        @if ($kode !== '')
            <p class="text-sm text-ink">
                Kode konfirmasi:
                <span class="block mt-1 font-mono text-base font-semibold text-brand-700">{{ $kode }}</span>
            </p>

            <p class="text-xs text-ink-muted">
                Data akun yang cocok dengan permintaan ini sudah dihapus dari sistem Social Media
                Analytics Diskominfo Kutai Timur. Simpan kode di atas sebagai bukti bila diperlukan.
            </p>
        @else
            <p class="text-sm text-ink-muted">
                Kode konfirmasi tidak ditemukan pada tautan ini. Pastikan Anda membuka tautan yang
                persis sama seperti yang diberikan saat mengajukan permintaan hapus data.
            </p>
        @endif
    </div>
</x-layouts.guest>
