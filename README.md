# LapakKita: Aplikasi Web Marketplace Sederhana

Aplikasi marketplace tempat **penjual** menawarkan produk dan **pembeli** memesan produk secara online.
Dibangun dengan **PHP Native**, **MySQLi** (prepared statement), dan **Bootstrap 5**.

- Demo online: `https://lapak-kito.infinityfreeapp.com/`


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

