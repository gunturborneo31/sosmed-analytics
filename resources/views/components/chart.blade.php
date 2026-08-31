@props(['id', 'options', 'height' => 300, 'morph' => true, 'key' => null])

@if ($morph)
    {{-- wire:ignore agar Livewire tidak menimpa DOM milik ApexCharts;
         pembaruan data dikirim lewat event supaya grafik morph, bukan hilang lalu muncul (§7.5). --}}
    <div
        wire:ignore
        x-data="apexChart(@js($id), @js($options))"
        style="min-height: {{ $height }}px"
        {{ $attributes }}
    >
        <div x-ref="canvas"></div>
    </div>
@else
    {{-- Mode digambar ulang, untuk grafik yang baru muncul di tengah jalan.

         Livewire TIDAK menginisialisasi elemen ber-`wire:ignore` yang
         disisipkannya belakangan, jadi ApexCharts tidak pernah dijalankan dan
         yang tampil kotak kosong. Menyiasatinya dengan menyembunyikan-lalu-
         menampilkan juga gagal: grafik yang terlanjur digambar pada wadah 0×0
         menyimpan kisi berukuran nol dan tidak pernah pulih.

         Di sini elemennya dibiarkan ikut morph, dan `wire:key` yang berubah
         membuatnya digambar ulang dari awal — selalu pada wadah yang sudah
         punya ukuran. --}}
    <div
        wire:key="{{ $key ?? $id }}"
        x-data="apexChart(@js($id), @js($options))"
        style="min-height: {{ $height }}px"
        {{ $attributes }}
    >
        <div x-ref="canvas"></div>
    </div>
@endif
