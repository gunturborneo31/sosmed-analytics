import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';
import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';
import ApexCharts from 'apexcharts';
import { animate } from 'motion';

window.ApexCharts = ApexCharts;

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Angka statistik menghitung naik dari 0 — menandai data yang baru diperbarui (§7.5).
 */
Alpine.directive('count-up', (el, { expression }, { evaluateLater, effect }) => {
    const getTarget = evaluateLater(expression);
    const format = new Intl.NumberFormat('id-ID');

    effect(() => {
        getTarget((target) => {
            const value = Number(target) || 0;

            /*
             | Elemen SUDAH berisi angka akhir dari server. Animasi hanyalah
             | hiasan, jadi tidak boleh ada satu baris pun di sini yang membuat
             | angka yang benar bergantung pada JavaScript yang selesai berjalan.
             |
             | Dijalankan sekali saja per elemen. Saat Livewire memperbarui DOM
             | — misalnya periode diganti — atribut ini ikut berubah, dan
             | mengulang animasinya justru pernah membuat angkanya tersangkut
             | di nol ketika animasinya tidak jalan.
            */
            if (reducedMotion || el.dataset.sudahDihitung === '1') {
                el.textContent = format.format(value);

                return;
            }

            el.dataset.sudahDihitung = '1';

            animate(0, value, {
                duration: 0.8,
                ease: [0.16, 0.84, 0.44, 1],
                onUpdate: (latest) => {
                    el.textContent = format.format(Math.round(latest));
                },
            });
        });
    });
});


/**
 * Pembungkus ApexCharts. Grafik hidup di luar siklus render Livewire (wire:ignore),
 * jadi pembaruan datang lewat event `chart:update` dan di-morph, bukan digambar ulang (§7.5).
 */
Alpine.data('apexChart', (id, initialOptions) => ({
    chart: null,

    init() {
        this.opsiTerakhir = initialOptions;
        this.chart = new ApexCharts(this.$refs.canvas, this.withDefaults(initialOptions));
        this.chart.render();

        this.onUpdate = (event) => {
            if (event.detail?.id !== id) return;

            this.opsiTerakhir = event.detail.options;
            this.chart.updateOptions(this.withDefaults(event.detail.options), false, !reducedMotion);
        };

        // Warna sumbu, grid, dan tooltip diambil dari token tema. Saat tema
        // berganti, grafik digambar ulang dengan opsi terakhir yang sama —
        // datanya tidak berubah, hanya warnanya.
        this.onTema = () => this.chart.updateOptions(this.withDefaults(this.opsiTerakhir), false, false);

        window.addEventListener('chart:update', this.onUpdate);
        window.addEventListener('tema:ganti', this.onTema);
    },

    destroy() {
        window.removeEventListener('chart:update', this.onUpdate);
        window.removeEventListener('tema:ganti', this.onTema);
        this.chart?.destroy();
    },

    /** Baca satu token warna dari CSS, sehingga grafik ikut palet aktif. */
    token(nama) {
        return getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
    },

    /**
     * Formatter tidak bisa dikirim dari PHP (JSON tidak memuat fungsi), jadi
     * penyingkatan angka dipasang di sini: 1.700.000 → "1,7 jt".
     */
    abbreviate(value) {
        const n = Math.abs(Number(value) || 0);
        const sign = Number(value) < 0 ? '-' : '';
        const fmt = (v, unit) => sign + v.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + unit;

        if (n >= 1e9) return fmt(n / 1e9, ' M');
        if (n >= 1e6) return fmt(n / 1e6, ' jt');
        if (n >= 1e4) return fmt(n / 1e3, ' rb');

        return sign + n.toLocaleString('id-ID');
    },

    withDefaults(options) {
        // Hanya untuk grafik deret waktu; pada bar horizontal sumbu Y memuat
        // label kategori, bukan angka.
        const numericAxis = ['area', 'line'].includes(options.chart?.type);

        if (numericAxis) {
            /*
             | Satuan dikirim dari PHP sebagai penanda, bukan fungsi — JSON tidak
             | bisa membawa fungsi, jadi formatter-nya dipilih di sini. Tanpa ini,
             | grafik yang memasang `yaxis` sendiri (mis. untuk merapatkan
             | rentang) kehilangan format angka Indonesia.
            */
            const persen = options.satuanNilai === 'persen';

            const format = persen
                ? (v) => (Number(v) > 0 ? '+' : '') + Number(v).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%'
                : (v) => this.abbreviate(v);

            /*
             | `yaxis` bisa berbentuk objek tunggal ATAU array beberapa sumbu —
             | grafik detail OPD memakai dua sumbu (pengikut & jangkauan).
             | Menyebarkan array ke dalam objek mengubahnya jadi {0:…, 1:…},
             | dan seluruh setelan min/max ikut hilang: sumbunya balik
             | menghitung dari nol, sehingga kenaikan 26 pengikut di atas 4.353
             | tampak sebagai garis lurus.
            */
            const pasangFormatter = (sumbu) => ({
                ...sumbu,
                labels: { ...(sumbu.labels ?? {}), formatter: format },
            });

            options = {
                ...options,
                yaxis: Array.isArray(options.yaxis)
                    ? options.yaxis.map(pasangFormatter)
                    : pasangFormatter(options.yaxis ?? {}),
                tooltip: {
                    y: {
                        formatter: (v) => persen
                            ? format(v)
                            : Number(v).toLocaleString('id-ID'),
                    },
                    ...(options.tooltip ?? {}),
                },
            };
        }

        return {
            ...options,
            chart: {
                fontFamily: 'Inter, sans-serif',
                foreColor: this.token('--color-ink-muted'),
                toolbar: { show: false },
                animations: {
                    enabled: !reducedMotion,
                    easing: 'easeout',
                    speed: 600,
                    dynamicAnimation: { enabled: !reducedMotion, speed: 400 },
                },
                ...(options.chart ?? {}),
            },
            grid: { borderColor: this.token('--color-hairline'), strokeDashArray: 4, ...(options.grid ?? {}) },
            tooltip: {
                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                ...(options.tooltip ?? {}),
            },
        };
    },
}));

/**
 * Latar halaman masuk: arsip laporan yang hidup.
 *
 * Lembar-lembar dokumen melayang dalam tiga lapis kedalaman — sebagian berisi
 * baris teks, sebagian grafik batang atau garis tren. Kursor menggeser tiap
 * lapisan dengan laju berbeda (paralaks), dan lembar yang terdekat dengan
 * kursor ikut terangkat. Sesekali seberkas data mengalir dari satu lembar ke
 * lembar lain. Semuanya digambar di canvas agar tetap ringan meski elemennya
 * banyak.
 */
Alpine.data('dataDocuments', () => ({
    ctx: null,
    raf: null,
    docs: [],
    berkas: [],
    w: 0,
    h: 0,
    pointer: { x: null, y: null, tx: null, ty: null },

    // Tiga lapisan: makin dekat, makin besar, cepat, dan pekat.
    LAPIS: [
        { skala: 0.52, laju: 0.09, alfa: 0.055, paralaks: 0.008, jumlah: 10 },
        { skala: 0.86, laju: 0.17, alfa: 0.105, paralaks: 0.026, jumlah: 7 },
        { skala: 1.24, laju: 0.28, alfa: 0.185, paralaks: 0.052, jumlah: 5 },
    ],

    init() {
        this.ctx = this.$el.getContext('2d')
        this.ukurUlang()
        this.susunDokumen()

        this.onResize = () => {
            this.ukurUlang()
            this.susunDokumen()
            if (reducedMotion) this.gambar()
        }
        this.onMove = (e) => {
            let r = this.$el.getBoundingClientRect()
            this.pointer.tx = e.clientX - r.left
            this.pointer.ty = e.clientY - r.top
        }
        this.onLeave = () => { this.pointer.tx = null; this.pointer.ty = null }

        window.addEventListener('resize', this.onResize)
        this.$el.addEventListener('pointermove', this.onMove)
        this.$el.addEventListener('pointerleave', this.onLeave)

        reducedMotion ? this.gambar() : this.putar()
    },

    destroy() {
        cancelAnimationFrame(this.raf)
        window.removeEventListener('resize', this.onResize)
    },

    ukurUlang() {
        let ratio = Math.min(window.devicePixelRatio || 1, 2)
        this.w = this.$el.clientWidth
        this.h = this.$el.clientHeight
        this.$el.width = this.w * ratio
        this.$el.height = this.h * ratio
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
    },

    susunDokumen() {
        let jenis = ['teks', 'batang', 'tren', 'teks', 'batang']
        this.docs = []

        this.LAPIS.forEach((lapis, i) => {
            for (let n = 0; n < lapis.jumlah; n++) {
                this.docs.push({
                    lapis: i,
                    x: Math.random() * this.w,
                    y: Math.random() * (this.h + 240) - 120,
                    w: 74 * lapis.skala,
                    h: 96 * lapis.skala,
                    rot: (Math.random() - 0.5) * 0.34,
                    putar: (Math.random() - 0.5) * 0.00035,
                    laju: lapis.laju * (0.75 + Math.random() * 0.5),
                    jenis: jenis[Math.floor(Math.random() * jenis.length)],
                    benih: Math.random(),
                    angkat: 0,
                })
            }
        })

        // Urutkan agar lapisan jauh tergambar lebih dulu.
        this.docs.sort((a, b) => a.lapis - b.lapis)
    },

    putar() {
        this.langkah()
        this.gambar()
        this.raf = requestAnimationFrame(() => this.putar())
    },

    langkah() {
        // Kursor dikejar perlahan supaya paralaksnya mengalir, bukan menyentak.
        if (this.pointer.tx === null) {
            this.pointer.x = null
            this.pointer.y = null
        } else {
            this.pointer.x = this.pointer.x === null ? this.pointer.tx : this.pointer.x + (this.pointer.tx - this.pointer.x) * 0.08
            this.pointer.y = this.pointer.y === null ? this.pointer.ty : this.pointer.y + (this.pointer.ty - this.pointer.y) * 0.08
        }

        for (let d of this.docs) {
            d.y -= d.laju
            d.rot += d.putar

            if (d.y + d.h < -60) {
                d.y = this.h + 60 + Math.random() * 120
                d.x = Math.random() * this.w
            }

            // Lembar di dekat kursor terangkat sedikit.
            let target = 0
            if (this.pointer.x !== null) {
                let jarak = Math.hypot(d.x - this.pointer.x, d.y - this.pointer.y)
                if (jarak < 190) target = (1 - jarak / 190) ** 2
            }
            d.angkat += (target - d.angkat) * 0.09
        }

        this.aliranData()
    },

    /** Sesekali seberkas data melintas antar dua lembar terdekat. */
    aliranData() {
        if (this.berkas.length < 3 && Math.random() < 0.012) {
            let a = this.docs[Math.floor(Math.random() * this.docs.length)]
            let b = this.docs[Math.floor(Math.random() * this.docs.length)]

            if (a !== b && Math.hypot(a.x - b.x, a.y - b.y) < 420) {
                this.berkas.push({ a, b, t: 0 })
            }
        }

        for (let i = this.berkas.length - 1; i >= 0; i--) {
            this.berkas[i].t += 0.011
            if (this.berkas[i].t >= 1) this.berkas.splice(i, 1)
        }
    },

    geser(d) {
        if (this.pointer.x === null) return { dx: 0, dy: 0 }

        let p = this.LAPIS[d.lapis].paralaks

        return {
            dx: (this.pointer.x - this.w / 2) * -p,
            dy: (this.pointer.y - this.h / 2) * -p,
        }
    },

    gambar() {
        let ctx = this.ctx
        ctx.clearRect(0, 0, this.w, this.h)

        for (let d of this.docs) this.gambarDokumen(d)

        // Berkas data digambar di atas lembar agar terlihat mengalir di antaranya.
        for (let f of this.berkas) this.gambarBerkas(f)
    },

    gambarBerkas(f) {
        let ga = this.geser(f.a)
        let gb = this.geser(f.b)
        let x1 = f.a.x + ga.dx
        let y1 = f.a.y + ga.dy
        let x2 = f.b.x + gb.dx
        let y2 = f.b.y + gb.dy

        // Muncul dan surut, tidak pernah berhenti mendadak.
        let kuat = Math.sin(f.t * Math.PI)
        let ctx = this.ctx

        ctx.save()
        ctx.strokeStyle = `rgba(255,255,255,${kuat * 0.20})`
        ctx.lineWidth = 1
        ctx.setLineDash([2, 6])
        ctx.beginPath()
        ctx.moveTo(x1, y1)
        ctx.lineTo(x2, y2)
        ctx.stroke()

        // Butir data yang menempuh jalur itu.
        let px = x1 + (x2 - x1) * f.t
        let py = y1 + (y2 - y1) * f.t
        ctx.setLineDash([])
        ctx.fillStyle = `rgba(255,255,255,${kuat * 0.85})`
        ctx.beginPath()
        ctx.arc(px, py, 2.2, 0, Math.PI * 2)
        ctx.fill()
        ctx.restore()
    },

    gambarDokumen(d) {
        let ctx = this.ctx
        let { dx, dy } = this.geser(d)
        let lapis = this.LAPIS[d.lapis]
        let alfa = lapis.alfa + d.angkat * 0.16
        let skala = 1 + d.angkat * 0.07

        ctx.save()
        ctx.translate(d.x + dx, d.y + dy)
        ctx.rotate(d.rot)
        ctx.scale(skala, skala)
        ctx.translate(-d.w / 2, -d.h / 2)

        // Lembaran
        ctx.fillStyle = `rgba(255,255,255,${alfa})`
        ctx.strokeStyle = `rgba(255,255,255,${alfa + 0.16})`
        ctx.lineWidth = 1
        this.kotakBulat(0, 0, d.w, d.h, 6 * lapis.skala)
        ctx.fill()
        ctx.stroke()

        let isi = `rgba(255,255,255,${alfa + 0.30})`
        let pad = 9 * lapis.skala
        let lebar = d.w - pad * 2

        // Kepala dokumen — selalu ada, menandai "ini sebuah laporan".
        ctx.fillStyle = `rgba(255,255,255,${alfa + 0.42})`
        this.kotakBulat(pad, pad, lebar * 0.55, 4 * lapis.skala, 2)
        ctx.fill()

        ctx.fillStyle = isi

        if (d.jenis === 'teks') {
            for (let i = 0; i < 5; i++) {
                let w = lebar * (0.55 + ((d.benih * (i + 3)) % 0.45))
                this.kotakBulat(pad, pad + 14 * lapis.skala + i * 9 * lapis.skala, w, 3 * lapis.skala, 1.5)
                ctx.fill()
            }
        } else if (d.jenis === 'batang') {
            let n = 5
            let celah = 3 * lapis.skala
            let wb = (lebar - celah * (n - 1)) / n
            let dasar = d.h - pad

            for (let i = 0; i < n; i++) {
                let t = ((d.benih * 97 + i * 31) % 10) / 10
                let tinggi = (14 + t * 34) * lapis.skala
                this.kotakBulat(pad + i * (wb + celah), dasar - tinggi, wb, tinggi, 1.5)
                ctx.fill()
            }
        } else {
            // Garis tren
            let n = 6
            let atas = pad + 18 * lapis.skala
            let dasar = d.h - pad
            ctx.strokeStyle = isi
            ctx.lineWidth = 1.6 * lapis.skala
            ctx.beginPath()

            for (let i = 0; i < n; i++) {
                let t = ((d.benih * 61 + i * 43) % 10) / 10
                let x = pad + (lebar / (n - 1)) * i
                let y = dasar - t * (dasar - atas)
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y)
            }

            ctx.stroke()
        }

        ctx.restore()
    },

    kotakBulat(x, y, w, h, r) {
        let ctx = this.ctx
        ctx.beginPath()

        if (ctx.roundRect) {
            ctx.roundRect(x, y, w, h, r)

            return
        }

        // Cadangan untuk mesin lama yang belum punya roundRect.
        let j = Math.min(r, w / 2, h / 2)
        ctx.moveTo(x + j, y)
        ctx.arcTo(x + w, y, x + w, y + h, j)
        ctx.arcTo(x + w, y + h, x, y + h, j)
        ctx.arcTo(x, y + h, x, y, j)
        ctx.arcTo(x, y, x + w, y, j)
        ctx.closePath()
    },
}));

/**
 * Layar transisi sesudah login. Menahan sebentar supaya animasinya sempat
 * terbaca sebagai sambutan, bukan berkelip — lalu berpindah ke halaman tujuan.
 * Bila pengguna meminta gerak minimal, tahan itu dilewati.
 */
Alpine.data('enteringScreen', (target) => ({
    tahapan: ['Menghubungkan sesi', 'Memuat data perangkat daerah', 'Menyusun ringkasan'],
    langkah: 0,
    berganti: false,

    init() {
        if (reducedMotion) {
            window.location.replace(target)

            return
        }

        // Mulai memuat halaman tujuan di latar belakang selagi animasi berjalan,
        // supaya jeda ini terpakai untuk sesuatu, bukan sekadar menunggu.
        let prefetch = document.createElement('link')
        prefetch.rel = 'prefetch'
        prefetch.href = target
        document.head.appendChild(prefetch)

        // Jeda pudar sengaja jauh lebih pendek dari selang gilirnya, supaya
        // teks terbaca mantap alih-alih berkedip.
        this.pergiliran = setInterval(() => {
            if (this.langkah >= this.tahapan.length - 1) return

            this.berganti = true
            setTimeout(() => {
                this.langkah++
                this.berganti = false
            }, 150)
        }, 700)

        this.pindah = setTimeout(() => window.location.replace(target), 2100)
    },

    destroy() {
        clearInterval(this.pergiliran)
        clearTimeout(this.pindah)
    },
}))

/**
 * Kerangka halaman admin: sidebar yang bisa dilipat jadi rel ikon, plus laci
 * untuk layar kecil.
 *
 * Status lipatan disimpan sebagai kelas di <html> — bukan di state Alpine saja —
 * karena setiap perpindahan halaman adalah muat-ulang penuh. Kelas itu sudah
 * dipasang oleh skrip di <head> sebelum halaman digambar, jadi komponen ini
 * cukup membacanya kembali agar keduanya sinkron.
 */
Alpine.data('sidebarShell', () => ({
    ciut: false,
    laci: false,
    gelap: false,

    init() {
        this.ciut = document.documentElement.classList.contains('sidebar-ciut')
        this.gelap = document.documentElement.classList.contains('dark')

        // Laci hanya relevan di layar kecil; menutup sendiri saat layar melebar
        // supaya tidak tertinggal dalam keadaan terbuka.
        this.lebar = window.matchMedia('(min-width: 1024px)')
        this.onLebar = (e) => { if (e.matches) this.laci = false }
        this.lebar.addEventListener('change', this.onLebar)

        this.$watch('laci', (buka) => {
            // Cegah halaman di belakang tirai ikut tergulir.
            document.body.style.overflow = buka ? 'hidden' : ''
        })
    },

    destroy() {
        this.lebar.removeEventListener('change', this.onLebar)
        document.body.style.overflow = ''
    },

    toggle() {
        this.ciut = !this.ciut
        document.documentElement.classList.toggle('sidebar-ciut', this.ciut)
        localStorage.setItem('sidebar', this.ciut ? 'ciut' : 'bentang')

        // Grafik ApexCharts mengukur lebarnya sekali saat digambar; beri tahu
        // untuk mengukur ulang setelah transisi lebar sidebar selesai.
        setTimeout(() => window.dispatchEvent(new Event('resize')), 320)
    },

    gantiTema() {
        this.gelap = !this.gelap
        document.documentElement.classList.toggle('dark', this.gelap)
        localStorage.setItem('tema', this.gelap ? 'gelap' : 'terang')

        // Warna sumbu dan garis grid ApexCharts dibaca sekali saat digambar,
        // jadi grafik perlu diberi tahu agar mengambil token yang baru.
        window.dispatchEvent(new Event('tema:ganti'))
    },
}))

/**
 * Notifikasi mengambang untuk aksi Livewire yang tidak berpindah halaman
 * (wire:click di dalam komponen, tanpa redirect).
 *
 * `<x-flash>` yang membaca session()->flash() hanya cocok untuk pemuatan
 * halaman penuh (login, callback OAuth) — Livewire hanya menambal ulang DOM
 * di dalam root komponennya sendiri, jadi flash session yang di-set di dalam
 * sebuah action tidak pernah terlihat pengguna kalau ditaruh di layout luar.
 * Container ini sengaja hidup di layout (bukan di dalam root komponen mana
 * pun), sehingga selamat dari penambalan itu dan tetap menangkap event
 * `toast` yang di-dispatch dari komponen mana saja di halaman.
 */
Alpine.data('toaster', () => ({
    toasts: [],

    tambah(tipe, pesan) {
        if (!pesan) return

        const id = `${Date.now()}-${Math.random()}`
        this.toasts.push({ id, tipe, pesan })
        setTimeout(() => this.hapus(id), 5000)
    },

    hapus(id) {
        this.toasts = this.toasts.filter((t) => t.id !== id)
    },
}))

Alpine.plugin(collapse);
Alpine.plugin(intersect);
// Menahan fokus papan tik di dalam modal selama modal terbuka.
Alpine.plugin(focus);

Livewire.start();
