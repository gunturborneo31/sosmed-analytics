<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jumlah Penduduk (penyebut IKK)
    |--------------------------------------------------------------------------
    |
    | Dipakai sebagai penyebut rumus IKK "Persentase Masyarakat yang Menjadi
    | Sasaran Penyebaran Informasi Publik". Angka acuan diambil dari data
    | kependudukan resmi (BPS/Dukcapil) dan berubah tiap tahun, jadi nilai di
    | sini hanya bawaan — admin masih bisa menggantinya langsung di halaman
    | Rekap tanpa menyentuh berkas .env.
    |
    */
    'jumlah_penduduk' => (int) env('IKK_JUMLAH_PENDUDUK', 456333),

    /*
    |--------------------------------------------------------------------------
    | Kelompok Usia yang Ditampilkan (16–64 tahun)
    |--------------------------------------------------------------------------
    |
    | Definisi operasional IKK membatasi sasaran pada masyarakat berusia 16–64
    | tahun. Daftar di bawah ini jadi satu-satunya sumber kebenaran: seluruh
    | grafik, tabel, dan laporan hanya menampilkan kelompok yang terdaftar di
    | sini — tidak ada kelompok usia di luar 16–64 yang muncul di layar maupun
    | di berkas unduhan.
    |
    | Kuncinya nama kelompok bawaan Meta, nilainya cara aplikasi menampilkannya:
    |
    |     label  → nama yang dilihat pengguna
    |     porsi  → bagian kelompok Meta yang termasuk rentang 16–64
    |     alasan → keterangan bila angkanya perkiraan, bukan hitungan langsung
    |
    | Kelompok remaja Meta dimulai sebelum usia 16, jadi hanya diambil 2/5 (40%)
    | — porsi tahun yang benar-benar termasuk sasaran, dengan asumsi sebaran usia
    | di dalamnya merata. Asumsi itu ditandai terbuka di halaman Rekap maupun
    | laporan PDF supaya pembaca tahu bagian mana yang bukan hitungan langsung.
    |
    | Kelompok lansia Meta sengaja tidak didaftarkan sama sekali: seluruh isinya
    | berada di luar rentang, sehingga tidak ikut dihitung dan tidak ditampilkan.
    |
    */
    'kelompok_usia' => [
        '13-17' => [
            'label' => '16-17',
            'porsi' => 0.4,
            'alasan' => 'Meta tidak menyediakan kelompok yang dimulai persis pada usia 16, '
                .'sehingga jumlah usia 16–17 diperkirakan dari kelompok remaja dengan asumsi '
                .'sebaran usia merata.',
        ],
        '18-24' => ['label' => '18-24', 'porsi' => 1.0],
        '25-34' => ['label' => '25-34', 'porsi' => 1.0],
        '35-44' => ['label' => '35-44', 'porsi' => 1.0],
        '45-54' => ['label' => '45-54', 'porsi' => 1.0],
        '55-64' => ['label' => '55-64', 'porsi' => 1.0],
    ],

];
