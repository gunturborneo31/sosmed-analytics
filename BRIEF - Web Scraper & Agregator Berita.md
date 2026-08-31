Instruksi Pengembangan Fitur: Web Scraper & Media Partner Terjadwal (Laravel) - Update V2
Dokumen ini telah diperbarui untuk menghapus modul notifikasi dan menambahkan fitur Filter Rentang Waktu / Tanggal Pencarian pada proses scraping serta dashboard.
1. Isi File: PROMPT.md
# Prompt Pengembang: Fitur Scraping & Media Partner Terjadwal dengan Filter Waktu (Laravel)

## Ringkasan Tugas
Saya ingin mengimplementasikan fitur **Web Scraper & Media Partner Terjadwal** di dalam aplikasi Laravel saya. Fitur ini berfungsi untuk mengumpulkan artikel/berita secara otomatis dari daftar portal berita yang dikonfigurasi berdasarkan kata kunci/isu yang ditentukan pengguna.

**Ketentuan Utama:**
- **TANPA NOTIFIKASI**: Tidak perlu mengirim notifikasi (email, telegram, dll). Berita yang diserap langsung disimpan ke database dan ditampilkan di dashboard.
- **FILTER RENTANG WAKTU / TANGGAL**: Menambahkan pengaturan rentang waktu/tanggal pencarian (misalnya: hanya mengambil berita 24 jam terakhir, 7 hari terakhir, atau rentang tanggal spesifik `start_date` sampai `end_date`).
- **SCRAPING BERKALA**: Scraping berjalan otomatis di latar belakang sesuai interval waktu yang dapat diatur (misal: setiap 1 jam).

---

## 1. Desain Database & Model (Migration & Models)

Buatlah migration dan model untuk tabel-tabel berikut:

### a. `media_sources`
Menyimpan daftar media atau website target.
- `id` (Primary Key)
- `name` (string): Nama Media (contoh: Suara Kutim)
- `base_url` (string): URL Utama (contoh: https://suarakutim.com)
- `feed_url` (string, nullable): URL RSS Feed jika ada (contoh: https://suarakutim.com/feed/)
- `is_active` (boolean, default: true): Status aktif/tidak
- `timestamps`

### b. `search_topics`
Menyimpan topik atau kata kunci isu beserta pengaturan rentang waktu pencariannya.
- `id` (Primary Key)
- `keyword` (string): Kata Kunci (contoh: "bupati", "DPRD Kutim")
- `description` (text, nullable): Catatan / Deskripsi topik
- `time_filter_type` (enum: 'all', 'last_24h', 'last_7d', 'last_30d', 'custom'): Jenis filter waktu.
- `start_date` (date, nullable): Tanggal mulai (jika `time_filter_type` = 'custom')
- `end_date` (date, nullable): Tanggal selesai (jika `time_filter_type` = 'custom')
- `is_active` (boolean, default: true)
- `timestamps`

### c. `scraped_articles`
Menyimpan berita hasil scraping yang sudah disaring.
- `id` (Primary Key)
- `media_source_id` (foreign key -> `media_sources.id`, onDelete cascade)
- `search_topic_id` (foreign key -> `search_topics.id`, onDelete cascade)
- `title` (string): Judul berita
- `article_url` (string, unique): Link asli berita (mencegah duplikasi data)
- `summary` (text, nullable): Ringkasan berita
- `published_at` (datetime, nullable): Tanggal terbit berita dari sumber
- `timestamps`

### d. `scraping_schedules`
Menyimpan pengaturan interval pencarian otomatis.
- `id` (Primary Key)
- `frequency_minutes` (integer, default: 60): Interval running dalam menit (misal 60 untuk tiap 1 jam)
- `is_active` (boolean, default: true)
- `timestamps`

---

## 2. Service Class (Scraping Engine Logic)

Buatlah service class di `App\Services\NewsScraperService.php`.

### Spesifikasi Logic Scraping & Filtering:
1. **Pemeriksaan Filter Waktu**:
   - Sebelum memproses item berita, periksa tanggal publikasi berita (`published_at`).
   - Saring artikel berdasarkan `time_filter_type` pada `search_topics`:
     - `last_24h`: Hanya ambil artikel yang terbit <= 24 jam terakhir dari waktu eksekusi.
     - `last_7d`: Hanya ambil artikel yang terbit <= 7 hari terakhir.
     - `last_30d`: Hanya ambil artikel yang terbit <= 30 hari terakhir.
     - `custom`: Hanya ambil artikel yang terbit di antara `start_date` dan `end_date`.
     - `all`: Ambil semua artikel tanpa membatasi tanggal publikasi.
2. **Parsing Feed & HTML**:
   - Utamakan RSS Feed jika `media_sources.feed_url` terisi. Gunakan HTTP Client Laravel dan parser SimpleXML.
   - Fallback ke HTML Crawler (`Symfony\Component\DomCrawler\Crawler`) jika RSS tidak tersedia.
3. **Filtering Kata Kunci**: Bandingkan judul dan isi berita dengan `keyword` pada `search_topics` (case-insensitive).
4. **Deduplikasi**: Pastikan `article_url` belum pernah tersimpan di database sebelum melakukan `insert`.

---

## 3. Scheduled Command (Console Command & Cron Task)

Buatlah Console Command Laravel: `php artisan news:scrape`
- Class: `App\Console\Commands\ScrapeNewsCommand.php`

### Flow Eksekusi Command:
1. Panggil `NewsScraperService` untuk menjalankan pencarian pada semua media & kata kunci aktif.
2. Jalankan logika pencarian dan penyaringan waktu.
3. Simpan data artikel yang memenuhi kriteria langsung ke database `scraped_articles`.
4. Catat log ringkas hasil scraping (jumlah artikel baru yang berhasil disimpan) ke storage log (`Log::info(...)`).

---

## 4. Pengaturan Scheduler di Laravel (`routes/console.php` atau `App\Console\Kernel.php`)

Daftarkan command agar berjalan secara otomatis berdasarkan konfigurasi interval di `scraping_schedules` (misalnya `->hourly()`).

---

## 5. UI/Dashboard Pengaturan & Hasil Berita (Controller & Views)

Sediakan CRUD dan tampilan antarmuka (Blade / Livewire / Inertia):

1. **Kelola Media**: Tambah/Edit/Hapus daftar website target beserta URL Feed-nya.
2. **Kelola Topik & Filter Waktu**:
   - Form untuk menambah kata kunci beserta opsi filter waktu (24 jam terakhir, 7 hari terakhir, 30 hari terakhir, atau kustom tanggal `start_date` & `end_date`).
3. **Pengaturan Interval Scraper**: Form untuk mengubah interval eksekusi scheduler (misal 1 jam, 3 jam, 6 jam).
4. **Dashboard Hasil Scraping**:
   - Tabel/Grid daftar berita hasil scraping.
   - Filter interaktif berdasarkan:
     - Media Source
     - Kata Kunci / Topik
     - Rentang Tanggal Publikasi Berita (`published_at`)
   - Fitur pencarian kata kunci di judul/isi.
   - Pagination untuk daftar berita.

---

## 6. Contoh Data Seed (DatabaseSeeder)

Buatkan Seeder untuk mengisi data awal media target:
- `https://suarakutim.com/` (Feed: `https://suarakutim.com/feed/`)
- `https://kutimdaily.com/` (Feed: `https://kutimdaily.com/feed/`)
- `https://ceritasangattaku.com/` (Feed: `https://ceritasangattaku.com/feed/`)
- `https://kutimpost.com/` (Feed: `https://kutimpost.com/feed/`)
- `https://www.upnews.id/` (Feed: `https://www.upnews.id/feed/`)

Serta kata kunci awal: `"bupati"` dengan `time_filter_type` default `last_7d`.

---

Tolong buatkan struktur file, code migration, model, service class, command, controller, view, dan seeder secara lengkap.


2. Ringkasan Perubahan Utama (V2)
Komponen
Versi Sebelumnya (V1)
Versi Terbaru (V2)
 
Sistem Notifikasi
Mengirim Email / Telegram / Database Notification saat ada berita baru.
Dihapus Sepenuhnya. Berita langsung disimpan ke database dan diakses via Dashboard.
Filter Waktu Pencarian
Belum ada filter waktu (semua berita yang ditemukan dimasukkan).
Dibatasi berdasarkan Tanggal / Waktu:
24 Jam Terakhir (`last_24h`)
7 Hari Terakhir (`last_7d`)
30 Hari Terakhir (`last_30d`)
Kustom Tanggal Mula & Selesai (`custom`)
Database Schema
Memiliki kolom `is_notified` dan `notification_recipient`.
Kolom notifikasi dihapus. Menambahkan kolom `time_filter_type`, `start_date`, `end_date` pada tabel `search_topics`.


