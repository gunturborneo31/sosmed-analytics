<?php

namespace App\Services\Meta;

use Illuminate\Support\Carbon;

/**
 * Pengambilan metrik harian yang tahan terhadap metrik yang dihentikan Meta.
 *
 * MASALAH YANG DIPECAHKAN DI SINI:
 *
 * Graph API menolak SELURUH permintaan bila satu saja nama metrik di dalamnya
 * tidak dikenal. Sebelumnya `reach,impressions,profile_views` diminta sekaligus,
 * jadi ketika Meta menghentikan `impressions` untuk Instagram, jangkauan dan
 * kunjungan profil ikut hilang — ketiganya tersimpan sebagai nol dan
 * sinkronisasinya tetap tercatat "berhasil".
 *
 * Sekarang: satu panggilan gabungan dulu (murah, ini jalur normalnya), dan hanya
 * bila ada yang tidak terbaca barulah metrik itu diminta sendiri-sendiri untuk
 * memastikan mana yang benar-benar tidak tersedia. Yang tetap gagal dilaporkan
 * lewat `dilewati` supaya Job bisa menandainya sebagai sinkronisasi sebagian.
 */
trait FetchesDailyMetrics
{
    /**
     * @param  array<string, list<string>>  $peta  kolom tujuan => nama metrik Meta, urut prioritas
     * @param  array<string, mixed>  $params
     * @return array{reach:int, impressions:int, profile_views:int, interactions:int, dilewati:list<string>, raw:array<string, mixed>}
     */
    protected function fetchDaily(
        MetaGraphClient $client,
        string $path,
        array $peta,
        array $params,
        ?string $token,
    ): array {
        $utama = array_map(fn (array $kandidat): string => $kandidat[0], $peta);

        $raw = $client->tryGet($path, array_merge($params, ['metric' => implode(',', $utama)]), $token);
        $terbaca = $this->pluckMetrics($raw ?? []);

        $nilai = [];
        $dilewati = [];

        foreach ($peta as $kolom => $kandidat) {
            $angka = $this->firstAvailable($terbaca, $kandidat);

            if ($angka !== null) {
                $nilai[$kolom] = $angka;

                continue;
            }

            // Tidak terbaca dari panggilan gabungan — pisahkan penyebabnya.
            [$angka, $tambahan, $direspons] = $this->fetchSingle($client, $path, $kandidat, $params, $token);

            if ($angka === null) {
                $nilai[$kolom] = 0;

                /*
                 | Meta membalas 200 dengan `data` kosong bila rentangnya memang
                 | belum punya titik data — metriknya ada, angkanya saja yang
                 | belum. Itu nol yang sebenarnya, bukan metrik yang hilang, jadi
                 | tidak boleh ikut menandai sinkronisasi sebagai "sebagian".
                 | Yang ditandai hanya metrik yang benar-benar DITOLAK.
                */
                if (! $direspons) {
                    $dilewati[] = $kolom;
                }

                continue;
            }

            $nilai[$kolom] = $angka;
            $raw['data'] = array_merge($raw['data'] ?? [], $tambahan);
        }

        return [
            'reach' => $nilai['reach'] ?? 0,
            'impressions' => $nilai['impressions'] ?? 0,
            'profile_views' => $nilai['profile_views'] ?? 0,
            'interactions' => $nilai['interactions'] ?? 0,
            'dilewati' => $dilewati,
            'raw' => $raw ?? [],
        ];
    }

    /**
     * Deret harian sekaligus untuk satu rentang tanggal.
     *
     * Meta mengembalikan satu titik per hari dalam SATU panggilan — 30 hari
     * riwayat cukup sekali minta, bukan 30 kali. Dipakai saat akun baru
     * terhubung supaya filter 7/30/90 hari langsung ada isinya, alih-alih
     * menunggu sebulan sinkronisasi harian menumpuk.
     *
     * @param  array<string, list<string>>  $peta
     * @param  array<string, mixed>  $params
     * @return array<string, array<string, int>> tanggal (Y-m-d) => kolom => nilai
     */
    protected function fetchRange(
        MetaGraphClient $client,
        string $path,
        array $peta,
        array $params,
        ?string $token,
        ?Carbon $from = null,
        ?Carbon $until = null,
    ): array {
        $harian = [];

        foreach ($peta as $kolom => $kandidat) {
            foreach ($kandidat as $nama) {
                $response = $client->tryGet($path, array_merge($params, ['metric' => $nama]), $token);

                $titik = $response === null ? [] : $this->pluckSeries($response, $nama);

                /*
                 | Sebagian metrik Meta TIDAK PERNAH tersedia sebagai deret harian
                 | — `total_interactions`, misalnya, hanya dilayani sebagai nilai
                 | total untuk satu rentang. Meminta bentuk deret membuahkan
                 | `data` kosong tanpa galat, dan itulah sebabnya interaksi
                 | seluruh riwayat sempat tersimpan nol dan engagement terbaca 0%.
                 |
                 | Jadi kalau deretnya kosong, tiap harinya diminta sendiri-sendiri
                 | sebagai nilai total. Jumlahnya terbukti sama persis dengan total
                 | satu-rentang, jadi bukan perkiraan.
                */
                if ($titik === [] && $from && $until) {
                    $titik = $this->fetchDailyTotals($client, $path, $nama, $params, $token, $from, $until);
                }

                if ($titik === []) {
                    continue;
                }

                foreach ($titik as $tanggal => $angka) {
                    $harian[$tanggal][$kolom] = $angka;
                }

                break;
            }
        }

        ksort($harian);

        return $harian;
    }

    /**
     * Nilai total per hari, diminta satu per satu.
     *
     * Mahal, jadi dijaga dua hal: hanya dipakai setelah bentuk deret terbukti
     * kosong, dan dihentikan lebih awal bila hari pertama pun tidak menjawab —
     * metrik yang memang tidak tersedia tidak perlu ditembak puluhan kali.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, int>
     */
    private function fetchDailyTotals(
        MetaGraphClient $client,
        string $path,
        string $nama,
        array $params,
        ?string $token,
        Carbon $from,
        Carbon $until,
    ): array {
        $titik = [];

        /*
         | Dibatasi beberapa hari ke belakang saja: satu panggilan per hari per
         | metrik cepat menghabiskan kuota Meta (~200/jam per pengguna). Jangkauan
         | dan tayangan tidak terkena batas ini — keduanya datang sebagai deret,
         | cukup sekali minta untuk seluruh rentang.
        */
        $batas = now()->subDays(max(1, (int) config('meta.riwayat_interaksi_hari')))->startOfDay();
        $hari = $from->copy()->startOfDay()->max($batas);
        $pertama = true;

        while ($hari->lte($until)) {
            $response = $client->tryGet($path, array_merge($params, [
                'metric' => $nama,
                'metric_type' => 'total_value',
                'since' => $hari->copy()->startOfDay()->timestamp,
                'until' => $hari->copy()->addDay()->startOfDay()->timestamp,
            ]), $token);

            $angka = $response['data'][0]['total_value']['value'] ?? null;

            if ($angka === null && $pertama) {
                return [];
            }

            $pertama = false;

            if ($angka !== null) {
                // Meta bisa mengirim angka negatif (suka yang dibatalkan);
                // kolomnya unsigned, jadi ditahan di nol.
                $titik[$hari->toDateString()] = max(0, (int) $angka);
            }

            $hari = $hari->addDay();
        }

        return $titik;
    }

    /**
     * Membaca deret `data[].values[]` jadi tanggal => nilai.
     *
     * `end_time` dipakai apa adanya sebagai tanggal snapshot — itu tanggal yang
     * sama dengan yang dihasilkan sinkronisasi satu-hari, jadi pengisian riwayat
     * tidak akan bertabrakan dengan data yang sudah tersimpan.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, int>
     */
    private function pluckSeries(array $response, string $nama): array
    {
        $titik = [];

        foreach ($response['data'] ?? [] as $metrik) {
            if (($metrik['name'] ?? null) !== $nama) {
                continue;
            }

            foreach ($metrik['values'] ?? [] as $nilai) {
                if (! isset($nilai['end_time'], $nilai['value']) || is_array($nilai['value'])) {
                    continue;
                }

                $tanggal = Carbon::parse($nilai['end_time'])
                    ->timezone(config('app.timezone'))
                    ->toDateString();

                $titik[$tanggal] = (int) $nilai['value'];
            }
        }

        return $titik;
    }

    /**
     * Meminta satu metrik tersendiri, mencoba tiap nama alternatif — dan untuk
     * tiap nama, bentuk deret harian lebih dulu lalu bentuk nilai total. Meta
     * mewajibkan `metric_type=total_value` untuk sebagian metrik yang lebih baru
     * dan menolaknya tanpa itu.
     *
     * Nilai ketiga yang dikembalikan menandakan apakah Meta sempat MENERIMA
     * permintaannya — itu yang membedakan "metrik ditolak" dari "metriknya ada,
     * datanya saja belum".
     *
     * @param  list<string>  $kandidat
     * @param  array<string, mixed>  $params
     * @return array{0:?int, 1:array<int, mixed>, 2:bool}
     */
    private function fetchSingle(
        MetaGraphClient $client,
        string $path,
        array $kandidat,
        array $params,
        ?string $token,
    ): array {
        $direspons = false;

        foreach ($kandidat as $nama) {
            foreach ([[], ['metric_type' => 'total_value']] as $ekstra) {
                $response = $client->tryGet(
                    $path,
                    array_merge($params, ['metric' => $nama], $ekstra),
                    $token,
                );

                if ($response === null) {
                    continue;
                }

                $direspons = true;
                $angka = $this->firstAvailable($this->pluckMetrics($response), [$nama]);

                if ($angka !== null) {
                    return [$angka, $response['data'] ?? [], true];
                }
            }
        }

        return [null, [], $direspons];
    }

    /**
     * @param  array<string, int>  $terbaca
     * @param  list<string>  $kandidat
     */
    private function firstAvailable(array $terbaca, array $kandidat): ?int
    {
        foreach ($kandidat as $nama) {
            if (array_key_exists($nama, $terbaca)) {
                return $terbaca[$nama];
            }
        }

        return null;
    }

    /**
     * Meta memakai dua bentuk respons insight, tergantung metriknya:
     *
     *   deret harian → data[].values[0].value
     *   nilai total  → data[].total_value.value   (saat metric_type=total_value)
     *
     * Keduanya dibaca di sini supaya pemanggil tidak perlu tahu bedanya.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, int>
     */
    private function pluckMetrics(array $response): array
    {
        $nilai = [];

        foreach ($response['data'] ?? [] as $metrik) {
            $nama = $metrik['name'] ?? null;

            if (! is_string($nama)) {
                continue;
            }

            $angka = $metrik['values'][0]['value'] ?? $metrik['total_value']['value'] ?? null;

            // Metrik yang dikembalikan tanpa nilai sama sekali dianggap tidak
            // terbaca, bukan nol — supaya bisa dicoba ulang sendiri-sendiri.
            if ($angka === null || is_array($angka)) {
                continue;
            }

            // Meta bisa mengirim angka negatif (mis. suka yang dibatalkan),
            // sedangkan kolom penyimpanannya unsigned — ditahan di nol.
            $nilai[$nama] = max(0, (int) $angka);
        }

        return $nilai;
    }
}
