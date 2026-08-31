<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pemetaan Metrik Graph API
    |--------------------------------------------------------------------------
    |
    | Kunci di sebelah kiri adalah nama kolom di tabel `insight_snapshots`;
    | nilainya daftar nama metrik Meta yang dicoba berurutan, dari yang paling
    | baru ke yang lama. Meta rutin mengganti nama dan menghapus metrik —
    | `impressions` Instagram, misalnya, dihentikan dan digantikan `views`.
    |
    | Daftar berjenjang ini dipakai supaya nama metrik yang berubah cukup
    | dikoreksi di berkas ini, tanpa menyentuh kode. Bila seluruh nama pada satu
    | baris ditolak Meta, metrik itu dilewati, sinkronisasinya ditandai
    | "sebagian", dan alasannya muncul di Log Sinkronisasi — bukan diam-diam
    | tersimpan sebagai nol.
    |
    */
    'metrik' => [

        'instagram' => [
            'reach' => ['reach'],
            // `impressions` dihapus Meta pada 21 April 2025 untuk seluruh versi.
            'impressions' => ['views', 'impressions'],
            'profile_views' => ['profile_views'],
            // Interaksi sungguhan: suka + komentar + simpan + bagikan.
            'interactions' => ['total_interactions', 'accounts_engaged'],
        ],

        'facebook' => [
            'reach' => ['page_impressions_unique'],
            'impressions' => ['page_impressions'],
            'profile_views' => ['page_views_total'],
            'interactions' => ['page_post_engagements'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rentang Waktu Demografi Instagram
    |--------------------------------------------------------------------------
    |
    | `follower_demographics` mewajibkan parameter `timeframe`; tanpa itu Meta
    | menolak permintaannya. Pilihan yang diterima: last_14_days, last_30_days,
    | last_90_days, this_week, this_month, prev_month.
    |
    */
    'demografi_timeframe' => env('META_DEMOGRAFI_TIMEFRAME', 'this_month'),

    /*
    |--------------------------------------------------------------------------
    | Metrik Pertambahan Pengikut Harian
    |--------------------------------------------------------------------------
    |
    | Meta tidak menyediakan JUMLAH pengikut historis — hanya totalnya saat ini.
    | Yang tersedia adalah pertambahan per hari, dan dari situ kurva masa lalu
    | direkonstruksi mundur dari total hari ini.
    |
    | Hasilnya perkiraan: angka ini hanya menghitung yang MULAI mengikuti, tidak
    | yang berhenti. Tetap jauh lebih jujur daripada memakai total hari ini untuk
    | seluruh riwayat, yang membuat grafik trennya datar sempurna dan terbaca
    | seolah tidak seorang pun pernah mengikuti.
    |
    */
    'pengikut_baru' => [
        'instagram' => 'follower_count',
        'facebook' => 'page_fan_adds',
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas Riwayat Metrik Per-Hari
    |--------------------------------------------------------------------------
    |
    | Sebagian metrik — `total_interactions` dan `profile_views` — tidak pernah
    | dilayani Meta sebagai deret harian, hanya sebagai nilai total untuk satu
    | rentang. Supaya tetap punya angka per hari, tiap harinya diminta
    | sendiri-sendiri: satu panggilan per hari per metrik.
    |
    | Karena itu mahal (Meta membatasi sekitar 200 panggilan per jam per
    | pengguna), penarikannya dibatasi sekian hari ke belakang. Jangkauan dan
    | tayangan tetap ditarik penuh sepanjang riwayat karena keduanya datang
    | sebagai deret — cukup satu panggilan untuk seluruh rentang.
    |
    */
    'riwayat_interaksi_hari' => (int) env('META_RIWAYAT_INTERAKSI_HARI', 30),

    /*
    |--------------------------------------------------------------------------
    | Metrik Demografi Facebook
    |--------------------------------------------------------------------------
    |
    | Diminta satu per satu, bukan sekaligus, supaya satu metrik yang sudah
    | dihentikan Meta tidak ikut menggagalkan yang lain.
    |
    */
    'demografi_facebook' => [
        'age_gender' => 'page_fans_gender_age',
        'city' => 'page_fans_city',
        'country' => 'page_fans_country',
    ],

];
