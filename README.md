# LapakKita: Aplikasi Web Marketplace Sederhana

Aplikasi marketplace tempat **penjual** menawarkan produk dan **pembeli** memesan produk secara online.
Dibangun dengan **PHP Native**, **MySQLi** (prepared statement), dan **Bootstrap 5**.

- Demo online: `https://NAMA-SUBDOMAIN.infinityfreeapp.com` *(ganti dengan URL Anda)*
- Repository: `https://github.com/USERNAME/marketplace` *(ganti dengan URL Anda)*

## Fitur

| Fitur | File |
| --- | --- |
| Daftar produk + pencarian + filter kategori | `index.php` |
| Detail produk | `produk.php` |
| Kelola produk / CRUD (khusus penjual) | `penjual_produk.php` (Read), `produk_form.php` (Create & Update), `produk_hapus.php` (Delete) |
| Keranjang belanja (tambah, ubah jumlah, hapus) | `keranjang.php`, `keranjang_aksi.php` |
| Checkout dan simpan transaksi pesanan (otomatis dipecah satu pesanan per toko) | `checkout.php` |
| Riwayat dan detail pesanan pembeli | `pesanan.php`, `pesanan_detail.php` |
| Pesanan masuk + ubah status pesanan (khusus penjual) | `penjual_pesanan.php`, `pesanan_status.php` |
| Registrasi, login, logout | `register.php`, `login.php`, `logout.php` |
| REST API (produk, kategori, pesanan) | `api/produk.php`, `api/kategori.php`, `api/pesanan.php` |

Keamanan yang diterapkan: prepared statement di semua query, `password_hash()` untuk password,
token CSRF di semua form POST, `htmlspecialchars()` untuk semua output, validasi tipe file gambar,
dan transaksi database (`begin_transaction` / `commit` / `rollback`) saat checkout.

## Akun demo

| Peran | Email | Password |
| --- | --- | --- |
| Pembeli | `pembeli@demo.com` | `pembeli123` |
| Penjual (Toko Sinar Jaya) | `penjual@demo.com` | `penjual123` |
| Penjual (Dapur Bu Sari) | `penjual2@demo.com` | `penjual123` |

## Struktur folder

```
marketplace/
├── assets/css/style.css      # Tema tambahan di atas Bootstrap
├── database/marketplace.sql  # Skema tabel + data contoh
├── includes/
│   ├── koneksi.php           # Koneksi MySQLi (membaca config.php)
│   ├── fungsi.php            # Fungsi bantu: escape, rupiah, CSRF, login
│   ├── pesanan_fungsi.php    # Logika buat pesanan (dipakai web dan API)
│   ├── api.php               # Fungsi bantu REST API (response JSON, Basic Auth)
│   ├── header.php            # Navbar + pesan flash
│   └── footer.php
├── api/                      # REST API + koleksi Postman
├── uploads/                  # Gambar produk yang diunggah penjual
├── config.example.php        # Contoh konfigurasi (salin menjadi config.php)
├── index.php, produk.php, keranjang.php, checkout.php, ...
└── README.md
```

## ERD

```mermaid
erDiagram
    PENGGUNA ||--o{ PRODUK : "menjual"
    KATEGORI ||--o{ PRODUK : "mengelompokkan"
    PENGGUNA ||--o{ KERANJANG : "memiliki"
    PRODUK ||--o{ KERANJANG : "dimasukkan ke"
    PENGGUNA ||--o{ PESANAN : "membuat (pembeli)"
    PENGGUNA ||--o{ PESANAN : "menerima (penjual)"
    PESANAN ||--|{ DETAIL_PESANAN : "berisi"
    PRODUK |o--o{ DETAIL_PESANAN : "tercatat di"

    PENGGUNA {
        int id_pengguna PK
        varchar nama
        varchar email UK
        varchar password
        enum role
        varchar no_hp
        text alamat
        varchar nama_toko
    }
    KATEGORI {
        int id_kategori PK
        varchar nama_kategori UK
    }
    PRODUK {
        int id_produk PK
        int id_penjual FK
        int id_kategori FK
        varchar nama_produk
        text deskripsi
        int harga
        int stok
        varchar gambar
    }
    KERANJANG {
        int id_keranjang PK
        int id_pembeli FK
        int id_produk FK
        int jumlah
    }
    PESANAN {
        int id_pesanan PK
        varchar kode_pesanan UK
        int id_pembeli FK
        int id_penjual FK
        varchar nama_penerima
        varchar no_hp
        text alamat_kirim
        enum metode_bayar
        int total_harga
        enum status
        datetime tanggal_pesan
    }
    DETAIL_PESANAN {
        int id_detail PK
        int id_pesanan FK
        int id_produk FK
        varchar nama_produk
        int harga
        int jumlah
        int subtotal
    }
```

## REST API

API ada di folder `api/` dan memakai database serta file koneksi yang sama dengan aplikasi web.
URL dasar di XAMPP: `http://localhost/marketplace/api` (buka di browser untuk melihat daftar endpoint).

| Metode | Endpoint | Fungsi | Autentikasi |
| --- | --- | --- | --- |
| GET | `/api/produk.php` | Seluruh produk (filter opsional `?q=kata&kategori=id`) | - |
| GET | `/api/produk.php?id=1` | Detail satu produk | - |
| GET | `/api/kategori.php` | Daftar kategori + jumlah produk | - |
| POST | `/api/pesanan.php` | Membuat pesanan (body JSON) | Basic Auth pembeli |
| GET | `/api/pesanan.php` | Daftar pesanan milik pembeli | Basic Auth pembeli |
| GET | `/api/pesanan.php?id=2` | Detail satu pesanan | Basic Auth pembeli |

Contoh body `POST /api/pesanan.php` (Basic Auth `pembeli@demo.com` / `pembeli123`):

```json
{
  "nama_penerima": "Budi Santoso",
  "no_hp": "081234567803",
  "alamat_kirim": "Jl. Melati No. 21, Kota Bengkulu",
  "metode_bayar": "cod",
  "items": [ { "id_produk": 7, "jumlah": 2 }, { "id_produk": 1, "jumlah": 1 } ]
}
```

Format response: sukses `{"status": "success", "data": ...}`, gagal `{"status": "error", "code": 404, "message": "..."}`.
Kode status yang dipakai: 200, 201, 400, 401, 403, 404, 405, 409, 422, 500.

**Pengujian dengan Postman:** impor `api/LapakKita_API.postman_collection.json`, lalu jalankan
seluruh koleksi lewat **Run collection**. Koleksi berisi 15 request beserta skrip tes otomatis.
Ubah variabel `base_url` jika nama folder proyek Anda bukan `marketplace`.

## Pesanan per toko dan status pesanan

Satu pesanan selalu milik satu toko. Jika keranjang berisi produk dari dua toko, checkout membuat
dua pesanan dengan kode INV berbeda, dan masing-masing penjual hanya melihat pesanan tokonya.

Penjual mengubah status dari halaman **Pesanan masuk**:

```
menunggu  ──▶  diproses  ──▶  dikirim  ──▶  selesai
    │              │
    └──────┬───────┘
           ▼
      dibatalkan  (stok produk dikembalikan otomatis)
```

Status tidak bisa melompat (misalnya langsung dari menunggu ke selesai), dan pesanan yang sudah
selesai atau dibatalkan tidak bisa diubah lagi. Pembeli melihat status terbaru di **Pesanan saya**.

## Memperbarui database dari versi pertama

Jika database Anda dibuat dari `marketplace.sql` versi pertama (sebelum ada fitur status per toko):

- **Data boleh hilang:** impor ulang `database/marketplace.sql`.
- **Data ingin dipertahankan:** jalankan `database/migrasi_pesanan_per_toko.sql` sekali di tab SQL phpMyAdmin.

## Menjalankan di komputer (XAMPP / Laragon)

1. Salin folder `marketplace` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Buka phpMyAdmin, buat database bernama `marketplace` (collation `utf8mb4_unicode_ci`).
3. Pilih database tersebut, buka tab **Import**, lalu impor `database/marketplace.sql`.
4. Salin `config.example.php` menjadi `config.php`, lalu sesuaikan isinya.
5. Buka `http://localhost/marketplace`.

Kebutuhan: PHP 7.4 atau lebih baru dengan ekstensi `mysqli`, serta MySQL 5.7+ atau MariaDB 10.3+.

## Deployment ke InfinityFree

1. Daftar di [infinityfree.com](https://infinityfree.com), lalu buat akun hosting dengan subdomain gratis.
2. Buka **Control Panel → MySQL Databases**, buat database (misalnya `marketplace`).
   Catat **MySQL Hostname**, **Database Name**, **Username**, dan **Password** yang ditampilkan.
   Hostname-nya bukan `localhost`, melainkan seperti `sqlXXX.infinityfree.com`.
3. Klik **Admin** (phpMyAdmin) di samping database tersebut, buka tab **Import**, lalu impor `database/marketplace.sql`.
4. Buka **File Manager** (atau FTP dengan FileZilla), masuk ke folder `htdocs`, hapus file bawaan,
   lalu unggah seluruh isi folder proyek (bukan foldernya).
5. Di dalam `htdocs`, buat file `config.php` berisi kredensial dari langkah 2:

   ```php
   <?php
   define('DB_HOST', 'sqlXXX.infinityfree.com');
   define('DB_USER', 'if0_XXXXXXXX');
   define('DB_PASS', 'password_hosting_anda');
   define('DB_NAME', 'if0_XXXXXXXX_marketplace');
   define('APP_NAME', 'LapakKita');
   ```

6. Buka URL subdomain Anda. Subdomain baru kadang butuh beberapa menit sampai beberapa jam sebelum bisa diakses.

## Mengunggah ke GitHub

```bash
cd marketplace
git init
git add .
git status            # pastikan config.php TIDAK ada di daftar
git commit -m "Aplikasi Web Marketplace dengan PHP Native, MySQLi, dan Bootstrap"
git branch -M main
git remote add origin https://github.com/USERNAME/marketplace.git
git push -u origin main
```

`config.php` dan isi folder `uploads/` sudah tercantum di `.gitignore`, sehingga password database
tidak ikut terunggah. Jangan pernah menulis password hosting langsung di file lain yang di-commit.
