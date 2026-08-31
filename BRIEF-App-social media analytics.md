Aplikasi Social Media analytics

Brief teknis lengkap untuk pengembangan lokal.
Dokumen ini dibuat untuk dieksekusi berurutan dari atas ke bawah.

---

## 0. Catatan Awal

**Logo belum masuk.** Kamu menyebut sudah menyediakan logo, tapi tidak ada file yang terkirim ke sesi ini. Di dokumen ini logo diperlakukan sebagai *placeholder* dengan path `public/img/logo-kutim.svg`. Silakan taruh file logomu di path itu — seluruh referensi di brief sudah mengarah ke sana, jadi tidak perlu ubah kode. Kalau nanti kamu kirim file logonya, aku bisa sesuaikan palet turunan dan penempatannya di header.

**Asumsi yang dipakai:**
- Laravel 11/12 kosong sudah ter-install di lokal
- PHP 8.3+, Composer, Node.js 20+ sudah tersedia
- Kamu akan pakai PostgreSQL (alasan di §2)

---

## 1. Ringkasan Produk

### Masalah
Diskominfo Kutai Timur perlu memantau performa media sosial seluruh perangkat daerah (Dinas, Badan, Kecamatan) tapi datanya tersebar di masing-masing akun. Tidak ada satu tempat untuk melihat rekap: siapa yang audiensnya bertumbuh, siapa yang stagnan, dan seperti apa profil warga yang benar-benar terjangkau.

### Solusi
Satu dashboard terpusat. Setiap perangkat daerah menghubungkan akun Instagram/Facebook-nya sekali lewat izin resmi Meta. Setelah itu, sistem menarik data insight secara terjadwal, dan admin Diskominfo bisa melihat rekap lintas OPD kapan saja.

### Dua sudut pandang berbeda

| | **Operator OPD** | **Admin Diskominfo** |
|---|---|---|
| Siapa | Petugas medsos di masing-masing dinas/kecamatan | Tim Diskominfo Kutim |
| Yang dilihat | Hanya akun instansinya sendiri | Seluruh akun se-kabupaten |
| Halaman utama | Status koneksi + insight akun sendiri | Rekap agregat + peringkat + perbandingan antar OPD |
| Aksi utama | Hubungkan akun, cek performa | Rekap, filter, ekspor laporan |

> **Fokus utama pengembangan ada di sisi Admin.** Halaman operator OPD sengaja dibuat sederhana — cukup tombol hubungkan akun, indikator status, dan ringkasan singkat. Seluruh kedalaman analitik ada di panel admin.

---

## 2. Keputusan Teknis

### Frontend: **Livewire 3 + Alpine.js**

Alasan memilih ini dibanding Vue/Inertia:

- Dashboard ini pada dasarnya adalah *data display* dengan filter — bukan aplikasi dengan interaksi kompleks seperti editor atau kolaborasi real-time. Livewire menutup kebutuhan itu tanpa perlu membangun layer API terpisah.
- Seluruh logic tetap di PHP. Untuk tim kecil atau developer tunggal, ini memangkas banyak waktu.
- Data insight datang dari Job terjadwal, bukan dari input user. `wire:poll` sudah cukup untuk refresh otomatis — tidak butuh WebSocket.
- Animasi dan interaktivitas tetap bisa maksimal lewat Alpine.js + ApexCharts. Livewire tidak membatasi kualitas UI.

**Kapan sebaiknya pindah ke Vue/Inertia:** kalau nanti aplikasi ini berkembang jadi produk multi-kabupaten dengan ratusan concurrent user dan UX yang jauh lebih kompleks.

### Database: **PostgreSQL 16**

- Respons Meta Graph API berbentuk JSON bersarang dengan struktur yang bisa berubah sewaktu-waktu (Meta rutin deprecate/tambah field). Tipe **JSONB** di PostgreSQL menyimpan ini apa adanya, lalu bisa di-query langsung ke dalamnya dengan indeks GIN.
- Rekap lintas 40+ akun × 12 bulan × beberapa jenis breakdown akan menghasilkan agregasi yang cukup berat. Window function dan CTE di PostgreSQL jauh lebih nyaman untuk ini.
- Cocok dipasangkan dengan **TimescaleDB** kalau nanti butuh time-series yang lebih serius (opsional, tidak wajib di fase awal).

MySQL tetap bisa dipakai kalau hosting instansi hanya menyediakan itu — tapi query rekapnya akan lebih repot ditulis.

### Stack lengkap

```
Laravel 12          — framework
Livewire 3          — komponen UI reaktif
Alpine.js 3         — interaksi client-side ringan
Tailwind CSS 3      — styling
ApexCharts          — grafik
Motion One          — animasi transisi (ringan, ~4kb)
PostgreSQL 16       — database
Redis               — queue driver + cache
Laravel Horizon     — monitor queue
Spatie Permission   — role & permission
Laravel Excel       — ekspor rekap
```

---

## 3. Instalasi

Jalankan berurutan dari root project Laravel kosongmu.

### 3.1 Package PHP

```bash
# Autentikasi + UI dasar
composer require livewire/livewire
composer require laravel/breeze --dev
php artisan breeze:install blade
# pilih: Blade with Alpine, Dark mode = No, PHPUnit

# Role & permission
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Queue monitoring
composer require laravel/horizon
php artisan horizon:install

# Ekspor laporan
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf

# HTTP client helper untuk Meta API (opsional tapi memudahkan)
composer require guzzlehttp/guzzle

# Utilitas pengembangan
composer require --dev laravel/pint
composer require --dev barryvdh/laravel-debugbar
```

### 3.2 Package JavaScript

```bash
npm install
npm install -D tailwindcss postcss autoprefixer
npm install apexcharts
npm install motion
npm install @alpinejs/collapse @alpinejs/intersect
```

### 3.3 Konfigurasi PostgreSQL

Buat database dulu:

```bash
createdb simedia_kutim
# atau lewat psql:
# psql -U postgres -c "CREATE DATABASE simedia_kutim;"
```

Lalu isi `.env`:

```env
APP_NAME="SIMEDIA Kutim"
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=simedia_kutim
DB_USERNAME=postgres
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Meta / Facebook Developer
META_APP_ID=
META_APP_SECRET=
META_REDIRECT_URI="${APP_URL}/oauth/meta/callback"
META_GRAPH_VERSION=v21.0
META_CONFIG_ID=
```

### 3.4 Verifikasi

```bash
php artisan migrate
php artisan serve
```

Buka `http://localhost:8000` — halaman login Breeze harus muncul.

---

## 4. Setup Meta for Developers

Ini dikerjakan paralel dengan coding, karena App Review memakan waktu.

### 4.1 Buat App

1. Masuk ke `developers.facebook.com/apps` → **Create App**
2. Use case: **Other** → Tipe: **Business**
3. Hubungkan ke Business Manager milik Diskominfo Kutim

### 4.2 Tambahkan Produk

- **Facebook Login for Business**
- **Instagram Graph API**

### 4.3 Permission yang dibutuhkan

| Permission | Untuk apa |
|---|---|
| `instagram_basic` | Info dasar akun IG |
| `instagram_manage_insights` | **Data usia, gender, wilayah** — ini yang utama |
| `pages_show_list` | Daftar Page yang dikelola |
| `pages_read_engagement` | Insight Facebook Page |
| `business_management` | Akses via Business Manager |

### 4.4 Konfigurasi OAuth

- **Valid OAuth Redirect URIs**: `http://localhost:8000/oauth/meta/callback` (untuk dev)
- Buat **Configuration ID** di menu Facebook Login for Business → simpan ke `META_CONFIG_ID`

### 4.5 Catatan penting

- Selama **Development Mode**, hanya akun yang terdaftar sebagai Tester/Developer di app yang bisa authorize. Cukup untuk pengembangan.
- Untuk produksi, wajib lewat **App Review** + **Business Verification**. Siapkan: video screencast alur penggunaan, kebijakan privasi (URL publik), dan penjelasan use case.
- Prasyarat di sisi OPD: akun Instagram harus bertipe **Business** atau **Creator**, dan tertaut ke sebuah **Facebook Page**. Akun personal tidak akan bisa dihubungkan. Ini perlu disosialisasikan lebih dulu ke seluruh perangkat daerah.

---

## 5. Struktur Database

### 5.1 Daftar tabel

```
users                  — akun aplikasi (operator OPD & admin)
roles / permissions    — dari Spatie
organizational_units    — data perangkat daerah (OPD)
social_accounts        — akun medsos yang sudah terhubung
insight_snapshots      — snapshot metrik harian per akun
audience_breakdowns    — rincian demografi (umur/gender/wilayah)
sync_logs              — riwayat & error sinkronisasi
```

### 5.2 Migration

```bash
php artisan make:migration create_organizational_units_table
php artisan make:migration create_social_accounts_table
php artisan make:migration create_insight_snapshots_table
php artisan make:migration create_audience_breakdowns_table
php artisan make:migration create_sync_logs_table
```

**`organizational_units`**

```php
$table->id();
$table->string('name');                    // "Dinas Kesehatan"
$table->string('slug')->unique();
$table->string('type');                    // dinas | badan | kecamatan | sekretariat
$table->string('district')->nullable();    // untuk tipe kecamatan
$table->string('contact_person')->nullable();
$table->string('contact_phone')->nullable();
$table->boolean('is_active')->default(true);
$table->timestamps();
```

**`social_accounts`**

```php
$table->id();
$table->foreignId('organizational_unit_id')->constrained()->cascadeOnDelete();
$table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
$table->string('platform');                // instagram | facebook
$table->string('platform_account_id');     // IG User ID / Page ID
$table->string('username')->nullable();
$table->string('display_name')->nullable();
$table->string('avatar_url')->nullable();
$table->text('access_token');              // encrypted cast
$table->timestamp('token_expires_at')->nullable();
$table->string('status')->default('connected'); // connected | expired | revoked | error
$table->timestamp('last_synced_at')->nullable();
$table->timestamps();

$table->unique(['platform', 'platform_account_id']);
$table->index(['organizational_unit_id', 'status']);
```

**`insight_snapshots`** — metrik harian

```php
$table->id();
$table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
$table->date('snapshot_date');
$table->unsignedBigInteger('followers_count')->default(0);
$table->unsignedBigInteger('reach')->default(0);
$table->unsignedBigInteger('impressions')->default(0);
$table->unsignedBigInteger('profile_views')->default(0);
$table->decimal('engagement_rate', 5, 2)->default(0);
$table->jsonb('raw_payload')->nullable();  // respons mentah dari Meta
$table->timestamps();

$table->unique(['social_account_id', 'snapshot_date']);
$table->index('snapshot_date');
```

**`audience_breakdowns`** — demografi

```php
$table->id();
$table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
$table->date('snapshot_date');
$table->string('dimension');       // age | gender | age_gender | city | country | locale
$table->jsonb('data');             // {"25-34": 4820, "35-44": 2310, ...}
$table->timestamps();

$table->unique(['social_account_id', 'snapshot_date', 'dimension']);
```

Tambahkan indeks GIN untuk query ke dalam JSONB:

```php
DB::statement('CREATE INDEX idx_breakdowns_data ON audience_breakdowns USING GIN (data)');
DB::statement('CREATE INDEX idx_snapshots_payload ON insight_snapshots USING GIN (raw_payload)');
```

**`sync_logs`**

```php
$table->id();
$table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
$table->string('status');          // success | failed | partial
$table->string('trigger');         // scheduled | manual
$table->text('message')->nullable();
$table->unsignedInteger('duration_ms')->nullable();
$table->timestamps();
```

### 5.3 Enkripsi token

Di model `SocialAccount`:

```php
protected function casts(): array
{
    return [
        'access_token'     => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at'   => 'datetime',
    ];
}
```

Access token tidak boleh disimpan sebagai teks biasa. Ini bukan opsional.

### 5.4 Seeder OPD

Buat seeder berisi 18 kecamatan Kutai Timur plus dinas/badan:

<cite index="41-1">Kabupaten Kutai Timur terdiri dari 18 kecamatan, 2 kelurahan, dan 139 desa</cite>. Daftar kecamatannya: Batu Ampar, Bengalon, Busang, Kaliorang, Karangan, Kaubun, Kongbeng, Long Mesangat, Muara Ancalong, Muara Bengkal, Muara Wahau, Rantau Pulung, Sandaran, Sangatta Selatan, Sangatta Utara, Sangkulirang, Telen, Teluk Pandan.

```bash
php artisan make:seeder OrganizationalUnitSeeder
```

---

## 6. Role & Permission

```bash
php artisan make:seeder RolePermissionSeeder
```

```php
// Roles
'super-admin'    // Diskominfo — akses penuh
'admin-kominfo'  // Diskominfo — lihat & rekap semua, tidak bisa kelola user
'operator-opd'   // Petugas OPD — hanya akunnya sendiri

// Permissions
'view-all-insights'
'view-own-insights'
'connect-social-account'
'export-report'
'manage-organizational-units'
'manage-users'
'trigger-manual-sync'
```

Scoping data di model, pakai Global Scope atau helper:

```php
// Di controller / Livewire component
$accounts = auth()->user()->can('view-all-insights')
    ? SocialAccount::query()
    : SocialAccount::where('organizational_unit_id', auth()->user()->organizational_unit_id);
```

---

## 7. Sistem Desain

### 7.1 Palet warna

Gradient utama sesuai ketentuan: **`#3E68B2` → `#3DC8F4`**

```css
:root {
  /* Brand — gradient utama */
  --brand-deep:    #3E68B2;
  --brand-bright:  #3DC8F4;
  --brand-gradient: linear-gradient(135deg, #3E68B2 0%, #3DC8F4 100%);

  /* Turunan brand untuk kebutuhan UI */
  --brand-50:   #EEF6FD;
  --brand-100:  #D6EBFA;
  --brand-300:  #7FBEEA;
  --brand-500:  #3E68B2;
  --brand-700:  #2E4E86;
  --brand-900:  #1C3253;

  /* Permukaan — light mode */
  --surface:        #FFFFFF;
  --surface-sunken: #F5F8FC;
  --surface-raised: #FFFFFF;
  --border:         #E2E9F2;

  /* Teks */
  --text-strong: #16233A;
  --text-body:   #4A5A73;
  --text-muted:  #8494AC;

  /* Status */
  --success: #16A46B;
  --warning: #E0A03A;
  --danger:  #E05252;
}
```

**Aturan pemakaian gradient:** gradient penuh hanya dipakai di tiga tempat — logo/header, tombol aksi utama, dan elemen tanda tangan (§7.4). Sisanya pakai warna solid dari turunan brand. Kalau gradient dipakai di semua kartu, dashboard akan terlihat ramai dan datanya justru sulit dibaca.

### 7.2 Tipografi

```
Display / Angka  : Plus Jakarta Sans (600, 700)
Body / UI        : Inter (400, 500, 600)
Data & label     : JetBrains Mono (400, 500)
```

Plus Jakarta Sans dipilih karena dirancang di Indonesia dan punya karakter yang lebih hangat dari Inter — pas untuk konteks pemerintah daerah tanpa terkesan kaku. Angka besar (jumlah follower, reach) pakai face ini agar punya bobot visual.

Skala tipe:

```
Display  32px / 700 / -0.02em    — angka utama
H1       24px / 600 / -0.01em    — judul halaman
H2       18px / 600              — judul kartu
Body     14px / 400 / 1.6        — teks umum
Label    12px / 500 / 0.06em     — label uppercase
Mono     12px / 400              — data numerik dalam tabel
```

### 7.3 Konfigurasi Tailwind

`tailwind.config.js`:

```js
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/Livewire/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#EEF6FD', 100: '#D6EBFA', 300: '#7FBEEA',
          500: '#3E68B2', 700: '#2E4E86', 900: '#1C3253',
          bright: '#3DC8F4',
        },
        surface: { DEFAULT: '#FFFFFF', sunken: '#F5F8FC' },
      },
      fontFamily: {
        display: ['Plus Jakarta Sans', 'sans-serif'],
        sans: ['Inter', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      backgroundImage: {
        'brand-gradient': 'linear-gradient(135deg, #3E68B2 0%, #3DC8F4 100%)',
      },
      boxShadow: {
        card: '0 1px 3px rgba(22,35,58,0.06), 0 8px 24px rgba(22,35,58,0.04)',
        glow: '0 8px 32px rgba(61,200,244,0.28)',
      },
      animation: {
        'count-up': 'countUp 0.8s cubic-bezier(0.16,0.84,0.44,1)',
        'slide-up': 'slideUp 0.5s cubic-bezier(0.16,0.84,0.44,1) both',
        'shimmer':  'shimmer 1.6s linear infinite',
      },
    },
  },
}
```

### 7.4 Elemen tanda tangan: **Peta Denyut Kutim**

Satu elemen yang membuat dashboard ini tidak terlihat seperti template admin generik.

Di halaman utama admin, tampilkan peta sederhana 18 kecamatan Kutai Timur (SVG). Setiap kecamatan yang punya akun medsos aktif ditandai titik yang **berdenyut** dengan intensitas mengikuti tingkat aktivitasnya — semakin tinggi engagement minggu itu, semakin terang dan cepat denyutnya. Kecamatan tanpa akun terhubung ditampilkan abu-abu redup.

Fungsinya bukan dekorasi: dalam satu pandangan, admin langsung tahu wilayah mana yang aktif berkomunikasi dengan warganya dan mana yang perlu didampingi. Klik titik → langsung masuk ke detail akun kecamatan itu.

Gradient brand dipakai persis di sini sebagai warna denyut, dari `#3E68B2` (aktivitas rendah) ke `#3DC8F4` (aktivitas tinggi).

### 7.5 Prinsip animasi

Animasi harus punya alasan. Yang dipakai:

| Momen | Animasi | Alasan |
|---|---|---|
| Halaman dimuat | Kartu naik bertahap, jeda 60ms antar kartu | Mengarahkan mata dari atas ke bawah |
| Angka statistik muncul | Hitung naik dari 0, 800ms | Menandai bahwa ini angka yang baru diperbarui |
| Grafik batang muncul | Tumbuh dari 0 saat masuk viewport | Memberi arti pada panjang batang |
| Filter diganti | Grafik morph, bukan hilang lalu muncul | Menjaga konteks perbandingan |
| Data sedang disinkron | Shimmer di kartu, bukan spinner | Menunjukkan bentuk konten yang akan datang |
| Titik peta aktif | Denyut lambat, 2.4s | Menandakan "hidup" tanpa mengganggu |

Semua animasi wajib menghormati preferensi sistem:

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 8. Halaman & Komponen

### 8.1 Peta rute

```
/login                             — masuk
/dashboard                         — pengalihan berdasarkan role

# Operator OPD
/akun                              — status koneksi akun instansi
/akun/hubungkan                    — mulai alur OAuth
/insight                           — insight akun sendiri

# Admin Diskominfo
/admin                             — ringkasan se-kabupaten (halaman utama)
/admin/perangkat-daerah            — daftar OPD & status koneksi
/admin/perangkat-daerah/{unit}     — detail per OPD
/admin/demografi                   — analisis usia, gender, wilayah agregat
/admin/perbandingan                — bandingkan antar OPD
/admin/rekap                       — susun & ekspor laporan
/admin/pengguna                    — kelola user (super-admin)
/admin/log-sinkronisasi            — riwayat sync & error

# OAuth
/oauth/meta/redirect
/oauth/meta/callback
```

### 8.2 Komponen Livewire

```bash
# Operator
php artisan make:livewire Operator/ConnectionStatus
php artisan make:livewire Operator/OwnInsight

# Admin
php artisan make:livewire Admin/CountyOverview
php artisan make:livewire Admin/PulseMap
php artisan make:livewire Admin/UnitDirectory
php artisan make:livewire Admin/UnitDetail
php artisan make:livewire Admin/DemographicsPanel
php artisan make:livewire Admin/AgeSpectrum
php artisan make:livewire Admin/GenderRatio
php artisan make:livewire Admin/RegionDistribution
php artisan make:livewire Admin/UnitComparison
php artisan make:livewire Admin/ReportBuilder
php artisan make:livewire Admin/SyncLogTable

# Bersama
php artisan make:livewire Shared/StatTile
php artisan make:livewire Shared/PeriodFilter
```

### 8.3 Susunan halaman utama admin (`/admin`)

```
┌──────────────────────────────────────────────────────────┐
│  [logo]  SIMEDIA KUTIM              [periode ▾]  [admin] │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  Ringkasan Kabupaten                    30 hari terakhir │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐             │
│  │ Akun   │ │ Total  │ │ Jangk. │ │ Rerata │             │
│  │ Aktif  │ │ Pengik.│ │ Warga  │ │ Engag. │             │
│  │  34/42 │ │ 248.9K │ │ 1.2 Jt │ │  3.4%  │             │
│  └────────┘ └────────┘ └────────┘ └────────┘             │
│                                                          │
│  ┌─────────────────────────┐ ┌────────────────────────┐  │
│  │  PETA DENYUT KUTIM      │ │  Perlu Perhatian       │  │
│  │  ◦ 18 kecamatan         │ │  ─────────────────     │  │
│  │  ● titik berdenyut      │ │  ⚠ 8 akun belum        │  │
│  │    sesuai aktivitas     │ │    terhubung           │  │
│  │                         │ │  ⚠ 3 token kedaluwarsa │  │
│  │                         │ │  ⚠ 5 OPD tanpa unggah  │  │
│  │                         │ │    30 hari terakhir    │  │
│  └─────────────────────────┘ └────────────────────────┘  │
│                                                          │
│  ┌───────────────────────────────────────────────────┐   │
│  │  Profil Audiens Se-Kabupaten                      │   │
│  │  Spektrum usia (cermin ♀ | ♂) + rasio + wilayah   │   │
│  └───────────────────────────────────────────────────┘   │
│                                                          │
│  ┌───────────────────────────────────────────────────┐   │
│  │  Peringkat Perangkat Daerah        [urutkan ▾]    │   │
│  │  Tabel: OPD · pengikut · Δ · jangkauan · engag.   │   │
│  └───────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

**Catatan copy:** kartu "Perlu Perhatian" sengaja tidak diberi judul "Peringatan" atau "Error" — tujuannya mengarahkan tindakan pendampingan, bukan menyalahkan OPD.

---

## 9. Integrasi Meta

### 9.1 Config

`config/services.php`:

```php
'meta' => [
    'app_id'        => env('META_APP_ID'),
    'app_secret'    => env('META_APP_SECRET'),
    'redirect'      => env('META_REDIRECT_URI'),
    'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    'config_id'     => env('META_CONFIG_ID'),
    'graph_url'     => 'https://graph.facebook.com/',
],
```

### 9.2 Service class

```bash
mkdir -p app/Services/Meta
```

```
app/Services/Meta/
├── MetaOAuthService.php       — tukar code jadi token, refresh token
├── MetaGraphClient.php        — wrapper HTTP + retry + rate limit handling
├── InstagramInsightService.php— tarik insight IG
└── FacebookInsightService.php — tarik insight FB Page
```

### 9.3 Alur OAuth

```
1. User klik "Hubungkan Instagram"
2. Redirect ke dialog Meta dengan config_id + state (CSRF token)
3. User menyetujui izin di halaman Meta
4. Meta redirect balik ke /oauth/meta/callback dengan ?code=
5. Tukar code → short-lived token
6. Tukar short-lived → long-lived token (berlaku ~60 hari)
7. Ambil daftar Page yang dikelola
8. Untuk tiap Page, cek apakah ada IG Business Account tertaut
9. Simpan ke social_accounts (token ter-enkripsi)
10. Dispatch job sinkronisasi pertama
11. Redirect ke /akun dengan notifikasi berhasil
```

### 9.4 Endpoint yang dipakai

```php
// Profil akun
GET /{ig-user-id}?fields=username,name,profile_picture_url,followers_count,media_count

// Metrik harian
GET /{ig-user-id}/insights
    ?metric=reach,impressions,profile_views
    &period=day

// Demografi audiens — INI YANG UTAMA
GET /{ig-user-id}/insights
    ?metric=follower_demographics
    &period=lifetime
    &metric_type=total_value
    &breakdown=age,gender      // atau: city, country

// Facebook Page
GET /{page-id}/insights
    ?metric=page_fans_gender_age,page_fans_city,page_impressions_unique
    &period=day
```

### 9.5 Job & Scheduler

```bash
php artisan make:job SyncSocialAccountInsights
php artisan make:job RefreshExpiringTokens
php artisan make:job BuildDailyAggregates
```

`routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Sinkronisasi insight — 2x sehari, jam sepi
Schedule::job(new DispatchAllAccountSyncs)->twiceDailyAt(3, 15, 0);

// Refresh token yang akan kedaluwarsa dalam 7 hari
Schedule::job(new RefreshExpiringTokens)->dailyAt('02:00');

// Bangun agregat untuk halaman admin
Schedule::job(new BuildDailyAggregates)->dailyAt('04:00');
```

**Penting soal rate limit:** Meta membatasi sekitar 200 panggilan per user per jam. Dengan 40+ akun, sebar job pakai delay:

```php
SocialAccount::active()->get()->each(function ($account, $index) {
    SyncSocialAccountInsights::dispatch($account)
        ->delay(now()->addSeconds($index * 20));
});
```

Jalankan worker saat development:

```bash
php artisan queue:work --queue=default
# atau pakai Horizon:
php artisan horizon
```

---

## 10. Fitur Rekap Admin

Ini inti dari kebutuhanmu — pastikan tidak terlewat.

### 10.1 Rekap agregat se-kabupaten

Gabungkan demografi seluruh akun jadi satu profil audiens kabupaten:

```php
// Contoh query agregasi umur, memanfaatkan JSONB PostgreSQL
DB::table('audience_breakdowns')
    ->join('social_accounts', 'social_accounts.id', '=', 'audience_breakdowns.social_account_id')
    ->where('dimension', 'age')
    ->where('snapshot_date', $date)
    ->selectRaw("
        key AS age_group,
        SUM(value::bigint) AS total
    ")
    ->crossJoinSub(
        DB::raw("jsonb_each_text(audience_breakdowns.data)"), 'kv'
    )
    ->groupBy('key')
    ->orderBy('key')
    ->get();
```

### 10.2 Filter yang wajib ada

- **Periode**: 7 hari / 30 hari / 90 hari / kustom
- **Jenis OPD**: semua / dinas / badan / kecamatan
- **Platform**: semua / Instagram / Facebook
- **OPD spesifik**: pilih satu atau beberapa untuk dibandingkan

### 10.3 Ekspor laporan

```bash
php artisan make:export CountyRecapExport
```

Format yang disediakan:
- **Excel** — data mentah per OPD, untuk diolah lebih lanjut
- **PDF** — laporan siap cetak dengan kop dan logo Kutim, untuk keperluan pelaporan resmi

Isi laporan PDF: ringkasan kabupaten, tabel peringkat OPD, grafik demografi agregat, dan catatan OPD yang belum terhubung.

### 10.4 Perbandingan antar OPD

Pilih 2–5 OPD, tampilkan berdampingan: tren pengikut, profil usia, dan sebaran wilayah. Berguna untuk melihat apakah dinas dengan audiens muda perlu strategi konten berbeda dari kecamatan dengan audiens lebih tua.

---

## 11. Roadmap Pengerjaan

| Tahap | Fokus | Estimasi |
|---|---|---|
| **1** | Instalasi, konfigurasi DB, Breeze, role & permission, seeder OPD | 2–3 hari |
| **2** | Sistem desain: Tailwind config, font, komponen dasar (kartu, tombol, tabel) | 2–3 hari |
| **3** | Alur OAuth Meta + halaman operator (hubungkan akun, status koneksi) | 4–5 hari |
| **4** | Service Meta API + Job sinkronisasi + penyimpanan ke DB | 4–5 hari |
| **5** | Dashboard admin: ringkasan, peringkat OPD, filter | 5–6 hari |
| **6** | Panel demografi: spektrum usia, rasio gender, sebaran wilayah | 3–4 hari |
| **7** | Peta Denyut Kutim (elemen tanda tangan) | 2–3 hari |
| **8** | Rekap & ekspor Excel/PDF | 3–4 hari |
| **9** | Penghalusan animasi, responsif, aksesibilitas | 2–3 hari |
| **10** | Pengujian, penanganan error, persiapan App Review Meta | 3–4 hari |

Total kasar: **6–8 minggu** untuk satu developer, di luar waktu tunggu App Review Meta.

---

## 12. Daftar Periksa Keamanan

- [ ] Access token disimpan dengan cast `encrypted`, tidak pernah plain text
- [ ] Token tidak pernah dikirim ke frontend atau muncul di log
- [ ] Parameter `state` diverifikasi di callback OAuth (cegah CSRF)
- [ ] Rate limiting di route OAuth
- [ ] Operator OPD hanya bisa mengakses data instansinya — uji dengan mencoba akses ID milik OPD lain
- [ ] `APP_DEBUG=false` di produksi
- [ ] HTTPS wajib di produksi (Meta menolak redirect URI non-HTTPS)
- [ ] Halaman kebijakan privasi tersedia publik (syarat App Review)
- [ ] Log sinkronisasi tidak menyimpan payload berisi data pribadi
- [ ] Backup database terjadwal

---

## 13. Yang Perlu Disiapkan di Luar Kode

Bagian ini sering terlupa dan justru jadi penghambat terbesar:

1. **Sosialisasi ke perangkat daerah** — jelaskan bahwa akun IG harus Business/Creator dan tertaut Facebook Page. Banyak OPD masih pakai akun personal.
2. **Surat tugas / SK** — penunjukan admin medsos di tiap OPD, supaya jelas siapa yang berwenang menghubungkan akun.
3. **Business Verification Meta** — butuh dokumen legal instansi. Mulai proses ini lebih awal karena bisa makan waktu berminggu-minggu.
4. **Kebijakan privasi** — harus terbit di domain resmi sebelum App Review.
5. **Domain & hosting** — App Review Meta tidak menerima `localhost`. Siapkan minimal subdomain staging dengan HTTPS.

---

*Dokumen ini disusun sebagai panduan kerja. Sesuaikan estimasi dan urutan dengan kondisi timmu.*
