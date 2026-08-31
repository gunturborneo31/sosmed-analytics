# Cara Mendapatkan & Memasang Kredensial Meta

Panduan ini melengkapi §4 brief. Aplikasi sudah siap menerima kredensial — yang
tersisa hanya mengisi lima nilai di `.env`.

> **Catatan:** tata letak menu di `developers.facebook.com` cukup sering berubah.
> Nama menu di bawah bisa sedikit berbeda; yang penting adalah produk dan izin
> yang dituju, bukan jalur kliknya.

---

## 0. Prasyarat yang harus beres lebih dulu

Ini bagian yang paling sering menghambat, dan penyelesaiannya bukan di kode:

| Prasyarat | Kenapa perlu |
|---|---|
| Akun IG tiap OPD bertipe **Business** atau **Creator** | Akun personal tidak punya endpoint insight sama sekali |
| Akun IG **tertaut ke sebuah Facebook Page** instansi | Token insight IG diambil lewat Page, bukan lewat akun IG langsung |
| **Business Manager** milik Diskominfo | Wadah legal untuk App Review & Business Verification |
| Kamu **admin** Page yang bersangkutan | Hanya admin Page yang bisa memberi izin |

Sosialisasikan tiga poin pertama ke seluruh perangkat daerah **sebelum** aplikasi
dibagikan — banyak OPD masih memakai akun personal.

---

## 1. Buat App di Meta for Developers

1. Buka <https://developers.facebook.com/apps> → **Create App**
2. Use case: **Other** → tipe app: **Business**
3. Nama app: `Social Media Analytics - Diskominfo Kutim` (atau nama lain yang mudah dikenali)
4. Hubungkan ke **Business Manager** Diskominfo Kutim

---

## 2. Tambahkan produk

Di dashboard app → **Add products**:

- **Facebook Login for Business**
- **Instagram** (Instagram Graph API / Instagram API with Facebook Login)

---

## 3. Minta izin yang dibutuhkan

Di **App Review → Permissions and Features**, ajukan lima izin ini:

| Izin | Untuk apa |
|---|---|
| `instagram_basic` | Info dasar akun IG (username, foto, jumlah pengikut) |
| `instagram_manage_insights` | **Data usia, gender, wilayah** — inti aplikasi ini |
| `pages_show_list` | Daftar Page yang dikelola operator |
| `pages_read_engagement` | Insight Facebook Page |
| `business_management` | Akses lewat Business Manager |

Selama **Development Mode**, kelima izin ini sudah bisa dipakai tanpa review —
tapi hanya oleh akun yang terdaftar sebagai Tester/Developer (lihat §6).

---

## 4. Konfigurasi OAuth redirect

**Facebook Login for Business → Settings → Valid OAuth Redirect URIs**, isi:

```
http://localhost:8000/oauth/meta/callback
```

Kalau kamu menjalankan `php artisan serve` di port lain, sesuaikan. Nilai ini
harus **sama persis** dengan `META_REDIRECT_URI` di `.env` — beda satu karakter
saja (termasuk garis miring di akhir) akan ditolak Meta.

Untuk staging/produksi, tambahkan versi HTTPS-nya:

```
https://simedia.kutimkab.go.id/oauth/meta/callback
```

Meta **menolak redirect URI non-HTTPS** di luar `localhost`.

---

## 5. Ambil kelima nilai untuk `.env`

### `META_APP_ID` dan `META_APP_SECRET`

**App settings → Basic**. App Secret tersembunyi — klik **Show** dan masukkan
kata sandi akunmu.

> App Secret setara kunci induk. Jangan pernah masuk ke git, screenshot grup
> WhatsApp, atau tiket. `.env` sudah ada di `.gitignore`.

### `META_CONFIG_ID` (opsional tapi disarankan)

**Facebook Login for Business → Configurations → Create configuration**:

- Login variation: **General**
- Assets: **Pages** dan **Instagram accounts**
- Permissions: centang kelima izin di §3

Simpan, lalu salin **Configuration ID**-nya.

Kalau `META_CONFIG_ID` dikosongkan, aplikasi otomatis jatuh ke daftar `scope`
biasa — tetap jalan, hanya alur persetujuannya kurang rapi bagi operator.

### `META_GRAPH_VERSION`

Biarkan `v21.0` kecuali kamu punya alasan spesifik. Meta menghentikan dukungan
versi lama sekitar dua tahun sekali; naikkan versinya lewat `.env` saja, kode
tidak perlu disentuh.

---

## 6. Isi `.env`

```env
META_APP_ID=1234567890123456
META_APP_SECRET=abcdef0123456789abcdef0123456789
META_REDIRECT_URI="${APP_URL}/oauth/meta/callback"
META_GRAPH_VERSION=v21.0
META_CONFIG_ID=987654321098765
```

Lalu bersihkan cache konfigurasi:

```bash
php artisan config:clear
```

---

## 7. Daftarkan penguji (Development Mode)

Selama app belum lolos review, **hanya akun terdaftar yang bisa memberi izin**.

**App roles → Roles → Add People** → tambahkan akun Facebook petugas sebagai
**Tester** atau **Developer**. Orang yang ditambahkan harus menerima undangan
lewat <https://developers.facebook.com/requests>.

Ini sudah cukup untuk seluruh pengembangan dan uji coba internal.

---

## 8. Uji alurnya

```bash
php artisan serve          # terminal 1
php artisan horizon        # terminal 2 — job sinkronisasi
npm run dev                # terminal 3 (opsional, untuk hot reload)
```

1. Masuk sebagai operator (`operator@kutimkab.go.id` / `password`)
2. Buka `/akun` → peringatan "Kredensial Meta belum dikonfigurasi" harus **hilang**
3. Klik **Hubungkan Instagram / Facebook**
4. Setujui izin di halaman Meta
5. Kembali ke `/akun` — akun muncul dengan status **Terhubung**
6. Pantau sinkronisasi pertama di `/horizon` atau `/admin/log-sinkronisasi`

### Kalau gagal

| Pesan | Artinya |
|---|---|
| "Sesi otorisasi tidak cocok" | `state` tidak sama — biasanya karena sesi kedaluwarsa. Ulangi dari `/akun`. |
| "URL Blocked" di halaman Meta | Redirect URI di `.env` ≠ yang didaftarkan di §4 |
| "Tidak ada Facebook Page yang kamu kelola" | Prasyarat §0 belum terpenuhi, atau akunmu bukan admin Page |
| `(#190)` di log sinkronisasi | Token kedaluwarsa/dicabut — operator perlu menghubungkan ulang |
| `(#4)` atau `(#17)` | Kuota panggilan Meta terlampaui; job otomatis mundur 15 menit |

---

## 9. Menuju produksi

Ini yang memakan waktu paling lama — mulai lebih awal, paralel dengan coding.

- [ ] **Business Verification** — butuh dokumen legal instansi, bisa berminggu-minggu
- [ ] **Kebijakan privasi** terbit di domain resmi (syarat mutlak App Review)
- [ ] **Video screencast** alur penggunaan aplikasi untuk tiap izin yang diminta
- [ ] **Penjelasan use case** per izin — jelaskan bahwa data dipakai untuk rekap
      internal pemerintah daerah, bukan iklan atau penjualan data
- [ ] **Domain + HTTPS** — Meta tidak menerima `localhost` saat review
- [ ] `APP_DEBUG=false` di `.env` produksi
- [ ] Redirect URI produksi didaftarkan di §4

---

## Ringkasan: lima nilai yang dicari

| Variabel | Lokasi di dashboard Meta |
|---|---|
| `META_APP_ID` | App settings → Basic → App ID |
| `META_APP_SECRET` | App settings → Basic → App Secret (klik *Show*) |
| `META_REDIRECT_URI` | Kamu yang tentukan; daftarkan di FB Login → Settings |
| `META_GRAPH_VERSION` | Biarkan `v21.0` |
| `META_CONFIG_ID` | FB Login for Business → Configurations |
