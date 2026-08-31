<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Perangkat daerah Kabupaten Kutai Timur.
 *
 * Kutai Timur terdiri dari 18 kecamatan (§5.4). Daftar dinas/badan di bawah
 * mengikuti nomenklatur umum SOTK kabupaten — sesuaikan bila ada perubahan
 * struktur organisasi.
 */
class OrganizationalUnitSeeder extends Seeder
{
    /** @var list<string> */
    private array $sekretariat = [
        'Sekretariat Daerah',
        'Sekretariat DPRD',
    ];

    /** @var list<string> */
    private array $dinas = [
        'Dinas Pendidikan dan Kebudayaan',
        'Dinas Kesehatan',
        'Dinas Pekerjaan Umum dan Penataan Ruang',
        'Dinas Perumahan dan Kawasan Permukiman',
        'Dinas Sosial',
        'Dinas Tenaga Kerja dan Transmigrasi',
        'Dinas Pemberdayaan Perempuan dan Perlindungan Anak',
        'Dinas Ketahanan Pangan',
        'Dinas Lingkungan Hidup',
        'Dinas Kependudukan dan Pencatatan Sipil',
        'Dinas Pemberdayaan Masyarakat dan Desa',
        'Dinas Pengendalian Penduduk dan Keluarga Berencana',
        'Dinas Perhubungan',
        'Dinas Komunikasi dan Informatika',
        'Dinas Koperasi, Usaha Kecil dan Menengah',
        'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu',
        'Dinas Kepemudaan dan Olahraga',
        'Dinas Perpustakaan dan Kearsipan',
        'Dinas Perikanan',
        'Dinas Pariwisata',
        'Dinas Pertanian',
        'Dinas Perkebunan',
        'Dinas Peternakan dan Kesehatan Hewan',
        'Dinas Perindustrian dan Perdagangan',
        'Dinas Pemadam Kebakaran dan Penyelamatan',
    ];

    /** @var list<string> */
    private array $badan = [
        'Badan Perencanaan Pembangunan Daerah',
        'Badan Pengelola Keuangan dan Aset Daerah',
        'Badan Pendapatan Daerah',
        'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia',
        'Badan Penanggulangan Bencana Daerah',
        'Badan Kesatuan Bangsa dan Politik',
        'Badan Riset dan Inovasi Daerah',
    ];

    /** @var list<string> 18 kecamatan Kutai Timur */
    private array $kecamatan = [
        'Batu Ampar',
        'Bengalon',
        'Busang',
        'Kaliorang',
        'Karangan',
        'Kaubun',
        'Kongbeng',
        'Long Mesangat',
        'Muara Ancalong',
        'Muara Bengkal',
        'Muara Wahau',
        'Rantau Pulung',
        'Sandaran',
        'Sangatta Selatan',
        'Sangatta Utara',
        'Sangkulirang',
        'Telen',
        'Teluk Pandan',
    ];

    public function run(): void
    {
        foreach ($this->sekretariat as $name) {
            $this->upsert($name, 'sekretariat');
        }

        foreach ($this->dinas as $name) {
            $this->upsert($name, 'dinas');
        }

        foreach ($this->badan as $name) {
            $this->upsert($name, 'badan');
        }

        foreach ($this->kecamatan as $district) {
            $this->upsert("Kecamatan {$district}", 'kecamatan', $district);
        }
    }

    private function upsert(string $name, string $type, ?string $district = null): void
    {
        OrganizationalUnit::updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'type' => $type,
                'district' => $district,
                'is_active' => true,
            ],
        );
    }
}
