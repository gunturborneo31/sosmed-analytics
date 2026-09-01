<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Media Sosial Kutai Timur</title>
    <style>
        @page { margin: 22mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #4A5A73; margin: 0; }
        h1, h2, h3 { color: #16233A; margin: 0; }
        .kop { border-bottom: 3px solid #3E68B2; padding-bottom: 12px; margin-bottom: 18px; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .kop img { width: 52px; height: 52px; }
        .kop h1 { font-size: 16px; }
        .kop p { margin: 3px 0 0; font-size: 10px; color: #8494AC; }
        .grid { width: 100%; margin-bottom: 18px; }
        .grid td { width: 33.33%; padding: 8px; background: #F5F8FC; border: 1px solid #E2E9F2; }
        .grid .label { font-size: 8px; text-transform: uppercase; letter-spacing: .6px; color: #8494AC; }
        .grid .value { font-size: 14px; font-weight: bold; color: #16233A; padding-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.data th { background: #3E68B2; color: #fff; font-size: 9px; text-align: left; padding: 6px 7px; }
        table.data td { border-bottom: 1px solid #E2E9F2; padding: 5px 7px; }
        table.data td.num { text-align: right; }
        tr:nth-child(even) td { background: #F9FBFD; }
        h2 { font-size: 12px; margin: 16px 0 8px; }
        .bar-track { background: #E2E9F2; height: 7px; width: 100%; }
        .bar-fill { background: #3E68B2; height: 7px; }
        .catatan { background: #FEF6E8; border-left: 3px solid #E0A03A; padding: 9px 11px; font-size: 9px; }
        .rumus { border: 1px solid #E2E9F2; background: #F5F8FC; padding: 12px; margin-bottom: 14px; }
        .rumus .pecahan { text-align: center; font-size: 9px; }
        .rumus .atas { padding-bottom: 4px; }
        .rumus .garis { border-top: 1.5px solid #3E68B2; margin: 0 auto 4px; width: 70%; }
        .rumus .hasil { text-align: center; font-size: 13px; font-weight: bold; color: #16233A; padding-top: 8px; }
        .kaki { margin-top: 16px; border-top: 1px solid #E2E9F2; padding-top: 8px; font-size: 8px; color: #8494AC; }
        table.cakupan { width: 100%; border: 1px solid #C9D8EC; background: #F4F8FD; border-collapse: collapse; margin-bottom: 18px; }
        table.cakupan td { padding: 5px 9px; vertical-align: top; font-size: 9px; line-height: 1.5; }
        table.cakupan td.label { width: 96px; color: #5C6E8A; text-transform: uppercase; letter-spacing: .04em; font-size: 8px; }
        .catatan-kecil { font-size: 8px; color: #8494AC; margin: -10px 0 18px; }
    </style>
</head>
<body>
    <div class="kop">
        <table>
            <tr>
                @isset($logo)
                    <td style="width:64px">
                        <img src="{{ $logo }}" alt="Logo Diskominfo Kutai Timur">
                    </td>
                @endisset
                <td>
                    <h1>Laporan Performa Media Sosial Perangkat Daerah</h1>
                    {{-- Periodenya tidak diulang di sini: tabel cakupan tepat di
                         bawah sudah memuatnya lengkap dengan jumlah harinya. --}}
                    <p>Pemerintah Kabupaten Kutai Timur &middot; Dinas Komunikasi dan Informatika</p>
                </td>
            </tr>
        </table>
    </div>

    {{-- Cakupan ditulis terbuka supaya angka di halaman ini tidak pernah terbaca
         sebagai "seluruh kabupaten, seluruh kanal" padahal laporannya disaring. --}}
    <table class="cakupan">
        <tr>
            <td class="label">Cakupan</td>
            <td>
                @if (($selectedUnits ?? collect())->isEmpty())
                    Seluruh perangkat daerah aktif se-kabupaten.
                @else
                    Dibatasi pada {{ $selectedUnits->count() }} perangkat daerah terpilih:
                    {{ $selectedUnits->join('; ') }}.
                @endif
            </td>
        </tr>
    </table>

    @if (($perPlatform ?? collect())->isNotEmpty())
        <h2>Rincian per Platform</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Platform</th>
                    <th style="text-align:right">Akun</th>
                    <th style="text-align:right">Pengikut</th>
                    <th style="text-align:right">Jangkauan</th>
                    <th style="text-align:right">Engagement</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perPlatform as $baris)
                    <tr>
                        <td>{{ $baris['label'] }}</td>
                        <td style="text-align:right">{{ number_format($baris['akun'], 0, ',', '.') }}</td>
                        <td style="text-align:right">{{ number_format($baris['pengikut'], 0, ',', '.') }}</td>
                        <td style="text-align:right">{{ number_format($baris['jangkauan'], 0, ',', '.') }}</td>
                        <td style="text-align:right">{{ number_format($baris['engagement'], 2, ',', '.') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="catatan-kecil">
            Angka di atas memecah laporan gabungan ini per kanal. Seluruh tabel lain pada laporan
            menjumlahkan kedua kanal menjadi satu.
        </p>
    @endif

    @isset($ikk)
        <h2>Indikator Kinerja Kunci (IKK)</h2>
        <div class="rumus">
            <div class="pecahan">
                <div class="atas">
                    Jumlah masyarakat yang menjadi sasaran penyebaran informasi publik, mengetahui kebijakan
                    dan program prioritas pemerintah dan pemerintah daerah kabupaten/kota
                </div>
                <div class="garis"></div>
                <div>Jumlah penduduk</div>
                <div style="padding-top:6px">&times; 100%</div>
            </div>

            <div class="hasil">
                {{ number_format($ikk['pembilang'], 0, ',', '.') }} &divide;
                {{ number_format($ikk['penyebut'], 0, ',', '.') }} &times; 100% =
                {{ number_format($ikk['persentase'], 2, ',', '.') }}%
            </div>
        </div>

        <div class="catatan">
            <strong>Cara membaca angka ini.</strong>
            Pembilang dihitung dari pengikut berusia 16&ndash;64 tahun pada akun media sosial perangkat daerah;
            pengikut di luar usia itu tidak ikut dihitung.
            @if (($ikk['dari_perkiraan'] ?? 0) > 0)
                Meta tidak menyediakan kelompok yang dimulai persis pada usia 16, sehingga
                {{ number_format($ikk['dari_perkiraan'], 0, ',', '.') }} pengikut termuda pada pembilang berasal
                dari perkiraan dengan asumsi sebaran usia merata, bukan hitungan langsung.
            @endif
            Warga yang mengikuti lebih dari satu akun terhitung berkali-kali, sehingga angkanya cenderung lebih
            besar dari jumlah orang sebenarnya. Definisi IKK mencakup enam kanal Media Komunikasi Publik,
            sedangkan aplikasi ini hanya mengukur kanal media sosial. IKK resmi diukur lewat survei &mdash;
            angka ini adalah estimasi pendukung, bukan pengganti hasil survei.
        </div>
    @endisset

    {{-- Tanpa judul: menyebutnya "Ringkasan Kabupaten" menyesatkan ketika laporan
         disaring ke sebagian OPD atau satu platform saja. Cakupannya sudah
         dinyatakan di kotak paling atas, dan tiap angka sudah berlabel sendiri. --}}
    <table class="grid">
        <tr>
            <td>
                <div class="label">Total Pengikut</div>
                <div class="value">{{ number_format($summary['followers'], 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Jangkauan Warga</div>
                <div class="value">{{ number_format($summary['reach'], 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Rerata Engagement</div>
                <div class="value">{{ number_format($summary['engagement_rate'], 2, ',', '.') }}%</div>
            </td>
        </tr>
    </table>

    <h2>Peringkat Perangkat Daerah</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:26px">#</th>
                <th>Perangkat Daerah</th>
                <th style="width:56px">Jenis</th>
                <th style="width:62px; text-align:right">Pengikut</th>
                <th style="width:48px; text-align:right">Δ</th>
                <th style="width:66px; text-align:right">Jangkauan</th>
                <th style="width:56px; text-align:right">Engag.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ranking as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->unit_name }}</td>
                    <td>{{ $row->unit_type }}</td>
                    <td class="num">{{ number_format($row->followers, 0, ',', '.') }}</td>
                    <td class="num">
                        @if ($row->growth === null)
                            &ndash;
                        @else
                            {{ $row->growth > 0 ? '+' : '' }}{{ number_format($row->growth, 1, ',', '.') }}%
                        @endif
                    </td>
                    <td class="num">{{ number_format($row->reach, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row->engagement_rate, 2, ',', '.') }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Profil Audiens Agregat</h2>
    @php
        $ageTotal = max($age->sum(), 1);
        $ageMax = max($age->max() ?: 1, 1);
        $genderKnown = max($gender['F'] + $gender['M'], 1);
    @endphp
    <table class="data">
        <thead>
            <tr>
                <th style="width:70px">Kelompok Usia</th>
                <th style="width:80px; text-align:right">Jumlah</th>
                <th style="width:50px; text-align:right">Porsi</th>
                <th>Sebaran</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($age as $group => $count)
                <tr>
                    <td>{{ $group }}</td>
                    <td class="num">{{ number_format($count, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($count / $ageTotal * 100, 1, ',', '.') }}%</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ round($count / $ageMax * 100, 1) }}%"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-bottom:14px">
        Rasio gender: <strong>{{ number_format($gender['F'] / $genderKnown * 100, 1, ',', '.') }}% perempuan</strong>
        &middot; <strong>{{ number_format($gender['M'] / $genderKnown * 100, 1, ',', '.') }}% laki-laki</strong>
        @if ($gender['U'] > 0)
            ({{ number_format($gender['U'], 0, ',', '.') }} pengikut tanpa data gender tidak dihitung)
        @endif
    </p>

    @if ($cities->isNotEmpty())
        <h2>Sebaran Wilayah Audiens</h2>
        <table class="data">
            <thead>
                <tr><th>Kota / Kabupaten</th><th style="width:90px; text-align:right">Jumlah Pengikut</th></tr>
            </thead>
            <tbody>
                @foreach ($cities as $city => $count)
                    <tr><td>{{ $city }}</td><td class="num">{{ number_format($count, 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Catatan pendampingan sengaja tidak dimuat di laporan: itu urusan
         operasional internal (token kedaluwarsa, akun belum tersinkron), bukan
         bahan yang pantas ikut beredar bersama laporan capaian. Tempatnya di
         halaman Perangkat Daerah, di mana tindak lanjutnya memang dikerjakan. --}}

    <div class="kaki">
        Tanda &ndash; pada kolom &Delta; berarti periode pembanding belum memiliki data,
        bukan pertumbuhan nol.<br>
        Disusun otomatis oleh {{ config('app.name') }} pada
        {{ now()->translatedFormat('j F Y, H:i') }} WITA
        @isset($generatedBy) oleh {{ $generatedBy }} @endisset.
        Sumber data: Meta Graph API.
    </div>
</body>
</html>
