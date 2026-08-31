@props(['name', 'class' => 'size-[18px]'])

@php
    // Ikon bergaya garis, 24×24, mewarisi currentColor.
    $paths = [
        'ringkasan' => '<rect x="3" y="3" width="7.5" height="8.5" rx="1.8"/><rect x="13.5" y="3" width="7.5" height="5" rx="1.8"/><rect x="13.5" y="11.5" width="7.5" height="9.5" rx="1.8"/><rect x="3" y="15" width="7.5" height="6" rx="1.8"/>',
        'gedung' => '<path d="M3 21h18"/><path d="M5 21V5.5A1.5 1.5 0 0 1 6.5 4h7A1.5 1.5 0 0 1 15 5.5V21"/><path d="M15 10h3.5A1.5 1.5 0 0 1 20 11.5V21"/><path d="M8 8h4M8 12h4M8 16h4"/>',
        'demografi' => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7" r="3.2"/><path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16.5 4.13a3.2 3.2 0 0 1 0 5.74"/>',
        'perbandingan' => '<path d="M4 20V9"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20V7"/><path d="M2 20h20"/>',
        'rekap' => '<path d="M14.5 2.5H7A2 2 0 0 0 5 4.5v15a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2.5V7h5"/><path d="M9 13h6M9 17h4"/>',
        'sinkron' => '<path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"/><path d="M3 21v-5h5"/>',
        'pengguna' => '<path d="M15 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="8.5" cy="7" r="3.2"/><circle cx="18" cy="8" r="2.4"/><path d="M18 3.6V5M18 11v1.4M21.1 5.8l-1.2.7M16.1 8.5l-1.2.7M21.1 10.2l-1.2-.7M16.1 7.5l-1.2-.7"/>',
        'akun' => '<path d="M9.5 14.5a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.66-5.66l-1.2 1.2"/><path d="M14.5 9.5a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.66 5.66l1.2-1.2"/>',
        'insight' => '<path d="M3 3v16.5A1.5 1.5 0 0 0 4.5 21H21"/><path d="M7 15l3.5-4 3 2.5L20 7"/><path d="M20 11V7h-4"/>',
        'panah-kiri' => '<path d="M14.5 6 9 12l5.5 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'keluar' => '<path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6"/>',
        'silang' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'matahari' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'bulan' => '<path d="M20.5 14.3A8.5 8.5 0 0 1 9.7 3.5a8.5 8.5 0 1 0 10.8 10.8z"/>',
        'pensil' => '<path d="M16.9 3.8a2.4 2.4 0 0 1 3.4 3.4L8.1 19.4l-4.6 1.2 1.2-4.6z"/><path d="M15.2 5.5l3.4 3.4"/>',
        'sampah' => '<path d="M3.5 6h17"/><path d="M8.5 6V4.5A1.5 1.5 0 0 1 10 3h4a1.5 1.5 0 0 1 1.5 1.5V6"/><path d="M18.5 6l-.8 13a2 2 0 0 1-2 1.9H8.3a2 2 0 0 1-2-1.9L5.5 6"/><path d="M10 10.5v6M14 10.5v6"/>',
        // Gembok terbuka = OPD aktif (belum dikunci); gembok terkunci = nonaktif.
        'gembok-buka' => '<rect x="3.5" y="10.5" width="17" height="10.5" rx="2.2"/><path d="M7.5 10.5V7a4.5 4.5 0 0 1 8.6-1.8"/><path d="M12 14.8v2.4"/>',
        'gembok-kunci' => '<rect x="3.5" y="10.5" width="17" height="10.5" rx="2.2"/><path d="M7.5 10.5V7a4.5 4.5 0 0 1 9 0v3.5"/><path d="M12 14.8v2.4"/>',
        'tambah' => '<path d="M12 5v14M5 12h14"/>',
        'peringatan' => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'berita' => '<path d="M4 4h16v16H4z"/><path d="M7 8h10M7 12h10M7 16h6"/>',
    ];
@endphp

<svg
    viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true" focusable="false"
    {{ $attributes->merge(['class' => $class.' shrink-0']) }}
>{!! $paths[$name] ?? '' !!}</svg>
