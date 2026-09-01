<x-layouts.guest title="Kebijakan Privasi">
    <div class="prose prose-sm max-w-none text-left">
        <p class="text-xs text-ink-muted">Terakhir diperbarui: {{ now()->translatedFormat('j F Y') }}</p>

        <p>
            Social Media Analytics adalah aplikasi internal Dinas Komunikasi dan Informatika
            (Diskominfo) Kabupaten Kutai Timur untuk merekap performa akun Instagram dan Facebook
            resmi perangkat daerah (OPD) di lingkungan Pemerintah Kabupaten Kutai Timur.
        </p>

        <h2 class="text-sm font-semibold text-ink-strong">Data yang dikumpulkan</h2>
        <p>
            Saat operator OPD menghubungkan akun Instagram atau Facebook resmi instansinya, aplikasi
            ini menyimpan:
        </p>
        <ul>
            <li>Nama pengguna, nama tampilan, dan foto profil akun yang dihubungkan.</li>
            <li>Metrik performa akun: jangkauan, tayangan, kunjungan profil, dan interaksi.</li>
            <li>Pertambahan jumlah pengikut harian.</li>
            <li>Demografi pengikut secara agregat (rentang umur, jenis kelamin, kota, negara) — bukan data pribadi masing-masing pengikut.</li>
            <li>Token akses yang disimpan dalam keadaan terenkripsi, dipakai hanya untuk mengambil data di atas dari Meta Graph API / Instagram Graph API.</li>
        </ul>

        <h2 class="text-sm font-semibold text-ink-strong">Yang TIDAK dikumpulkan</h2>
        <p>
            Aplikasi ini tidak mengakses pesan pribadi, daftar teman/pengikut individual, maupun data
            pribadi pengunjung akun. Akun yang dihubungkan juga wajib bertipe Business atau Creator —
            akun pribadi tidak pernah diminta izinnya.
        </p>

        <h2 class="text-sm font-semibold text-ink-strong">Bagaimana data dipakai</h2>
        <p>
            Data di atas hanya dipakai untuk menyusun rekap dan laporan kinerja media sosial internal
            pemerintah daerah — misalnya untuk memantau capaian Indeks Kinerja Komunikasi (IKK).
            Data ini <strong>tidak pernah dijual, dibagikan ke pihak ketiga, atau dipakai untuk
            iklan</strong>.
        </p>

        <h2 class="text-sm font-semibold text-ink-strong">Siapa yang bisa melihat</h2>
        <p>
            Hanya operator OPD pemilik akun dan admin Diskominfo yang mempunyai akses ke data ini di
            dalam aplikasi. Token akses tidak pernah ditampilkan ke siapa pun, termasuk di dalam log
            sistem.
        </p>

        <h2 class="text-sm font-semibold text-ink-strong">Menghapus data / mencabut izin</h2>
        <p>
            Operator dapat memutus koneksi akun kapan saja dari halaman <strong>Akun</strong> di
            aplikasi ini. Operator juga dapat mencabut izin langsung lewat pengaturan aplikasi pihak
            ketiga di Facebook atau Instagram — begitu izin dicabut lewat kanal Meta, aplikasi ini
            otomatis diberi tahu dan menandai akun tersebut sebagai terputus.
        </p>

        <h2 class="text-sm font-semibold text-ink-strong">Kontak</h2>
        <p>
            Pertanyaan seputar kebijakan ini dapat disampaikan ke Diskominfo Kabupaten Kutai Timur
            melalui kanal resmi instansi.
        </p>
    </div>
</x-layouts.guest>
