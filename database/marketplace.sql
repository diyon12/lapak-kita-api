-- =============================================================
-- Database  : marketplace (LapakKita)
-- Keterangan: Skema + data contoh aplikasi Web Marketplace
-- Cara pakai: pilih/buat database terlebih dahulu di phpMyAdmin,
--             lalu Import file ini. File ini sengaja TIDAK berisi
--             CREATE DATABASE agar bisa diimpor ke database
--             InfinityFree (nama database ditentukan hosting).
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS detail_pesanan;
DROP TABLE IF EXISTS pesanan;
DROP TABLE IF EXISTS keranjang;
DROP TABLE IF EXISTS produk;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS pengguna;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- Tabel pengguna (penjual dan pembeli)
-- -------------------------------------------------------------
CREATE TABLE pengguna (
  id_pengguna INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('penjual','pembeli') NOT NULL DEFAULT 'pembeli',
  no_hp       VARCHAR(20)  NULL,
  alamat      TEXT         NULL,
  nama_toko   VARCHAR(100) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uk_pengguna_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel kategori
-- -------------------------------------------------------------
CREATE TABLE kategori (
  id_kategori   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_kategori VARCHAR(50)  NOT NULL,
  PRIMARY KEY (id_kategori),
  UNIQUE KEY uk_kategori_nama (nama_kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel produk
-- -------------------------------------------------------------
CREATE TABLE produk (
  id_produk   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_penjual  INT UNSIGNED NOT NULL,
  id_kategori INT UNSIGNED NOT NULL,
  nama_produk VARCHAR(150) NOT NULL,
  deskripsi   TEXT         NULL,
  harga       INT UNSIGNED NOT NULL,
  stok        INT UNSIGNED NOT NULL DEFAULT 0,
  gambar      VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_produk),
  KEY idx_produk_penjual (id_penjual),
  KEY idx_produk_kategori (id_kategori),
  CONSTRAINT fk_produk_penjual FOREIGN KEY (id_penjual)
    REFERENCES pengguna (id_pengguna) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_produk_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel keranjang
-- -------------------------------------------------------------
CREATE TABLE keranjang (
  id_keranjang INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pembeli   INT UNSIGNED NOT NULL,
  id_produk    INT UNSIGNED NOT NULL,
  jumlah       INT UNSIGNED NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_keranjang),
  UNIQUE KEY uk_keranjang_item (id_pembeli, id_produk),
  KEY idx_keranjang_produk (id_produk),
  CONSTRAINT fk_keranjang_pembeli FOREIGN KEY (id_pembeli)
    REFERENCES pengguna (id_pengguna) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_keranjang_produk FOREIGN KEY (id_produk)
    REFERENCES produk (id_produk) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel pesanan
-- Satu pesanan = satu toko. Checkout dari beberapa toko otomatis
-- dipecah menjadi beberapa pesanan (satu kode INV per toko).
-- Alur status: menunggu -> diproses -> dikirim -> selesai,
-- atau dibatalkan (hanya dari menunggu/diproses, stok dikembalikan).
-- -------------------------------------------------------------
CREATE TABLE pesanan (
  id_pesanan    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_pesanan  VARCHAR(30)  NOT NULL,
  id_pembeli    INT UNSIGNED NOT NULL,
  id_penjual    INT UNSIGNED NOT NULL,
  nama_penerima VARCHAR(100) NOT NULL,
  no_hp         VARCHAR(20)  NOT NULL,
  alamat_kirim  TEXT         NOT NULL,
  catatan       VARCHAR(255) NULL,
  metode_bayar  ENUM('transfer','cod') NOT NULL DEFAULT 'transfer',
  total_harga   INT UNSIGNED NOT NULL,
  status        ENUM('menunggu','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu',
  tanggal_pesan DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pesanan),
  UNIQUE KEY uk_pesanan_kode (kode_pesanan),
  KEY idx_pesanan_pembeli (id_pembeli),
  KEY idx_pesanan_penjual (id_penjual),
  CONSTRAINT fk_pesanan_pembeli FOREIGN KEY (id_pembeli)
    REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pesanan_penjual FOREIGN KEY (id_penjual)
    REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel detail_pesanan
-- -------------------------------------------------------------
CREATE TABLE detail_pesanan (
  id_detail   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pesanan  INT UNSIGNED NOT NULL,
  id_produk   INT UNSIGNED NULL,
  nama_produk VARCHAR(150) NOT NULL,
  harga       INT UNSIGNED NOT NULL,
  jumlah      INT UNSIGNED NOT NULL,
  subtotal    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_detail),
  KEY idx_detail_pesanan (id_pesanan),
  KEY idx_detail_produk (id_produk),
  CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan)
    REFERENCES pesanan (id_pesanan) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detail_produk FOREIGN KEY (id_produk)
    REFERENCES produk (id_produk) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DATA CONTOH
-- Akun demo (password di-hash dengan bcrypt):
--   penjual@demo.com  / penjual123  (Toko Sinar Jaya)
--   penjual2@demo.com / penjual123  (Dapur Bu Sari)
--   pembeli@demo.com  / pembeli123
-- =============================================================

INSERT INTO pengguna (id_pengguna, nama, email, password, role, no_hp, alamat, nama_toko) VALUES
(1, 'Rudi Hartono', 'penjual@demo.com',  '$2y$10$4GB50ta9gREqZbR1SUeZOO9CZ28Ga0KLFPII0VpSX7dp2M.ulJiTW', 'penjual', '081234567801', 'Jl. Merdeka No. 10', 'Toko Sinar Jaya'),
(2, 'Sari Wulandari', 'penjual2@demo.com', '$2y$10$4GB50ta9gREqZbR1SUeZOO9CZ28Ga0KLFPII0VpSX7dp2M.ulJiTW', 'penjual', '081234567802', 'Jl. Kenanga No. 5', 'Dapur Bu Sari'),
(3, 'Budi Santoso', 'pembeli@demo.com',  '$2y$10$gOlSy8rf0N1w38.2a5n1Jeedb7OUEBqtbo9IhBXT.JyEf69SyA5by', 'pembeli', '081234567803', 'Jl. Melati No. 21, Kota Bengkulu', NULL);

INSERT INTO kategori (id_kategori, nama_kategori) VALUES
(1, 'Elektronik'),
(2, 'Fashion'),
(3, 'Makanan & Minuman'),
(4, 'Rumah Tangga'),
(5, 'Buku & Alat Tulis');

INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok) VALUES
(1, 1, 'Earphone Bluetooth TWS', 'Earphone nirkabel dengan Bluetooth 5.3, baterai tahan hingga 5 jam, dan kotak pengisi daya. Cocok untuk kuliah daring dan olahraga.', 189000, 25),
(1, 1, 'Power Bank 10.000 mAh', 'Power bank ringkas dengan dua port USB dan satu port USB-C. Mendukung pengisian cepat 18 W.', 159000, 15),
(1, 2, 'Kaos Polos Katun Combed 30s', 'Kaos polos bahan katun combed 30s yang adem dan menyerap keringat. Tersedia ukuran S sampai XL.', 65000, 40),
(1, 2, 'Kemeja Batik Lengan Pendek', 'Kemeja batik motif kontemporer dengan bahan katun halus. Pas untuk acara kampus maupun kantor.', 145000, 12),
(1, 4, 'Botol Minum Stainless 600 ml', 'Botol minum stainless steel dua lapis yang menjaga minuman tetap dingin hingga 12 jam.', 79000, 20),
(1, 4, 'Lampu Meja LED Lipat', 'Lampu belajar LED dengan tiga tingkat kecerahan dan leher yang bisa ditekuk.', 99000, 0),
(2, 3, 'Kopi Bubuk Robusta 250 g', 'Kopi robusta giling sedang dari dataran tinggi Sumatra. Aroma kuat dengan rasa cokelat yang pekat.', 45000, 30),
(2, 3, 'Keripik Pisang Cokelat 200 g', 'Keripik pisang renyah berlapis cokelat, dibuat dalam jumlah kecil setiap minggu.', 25000, 50),
(2, 5, 'Buku Catatan A5 Hardcover', 'Buku catatan 120 halaman kertas 80 gsm bergaris, sampul keras dengan pita pembatas.', 35000, 3);

-- Contoh satu pesanan agar tabel transaksi tidak kosong saat pertama kali dibuka
INSERT INTO pesanan (id_pesanan, kode_pesanan, id_pembeli, id_penjual, nama_penerima, no_hp, alamat_kirim, catatan, metode_bayar, total_harga, status) VALUES
(1, 'INV-CONTOH-000001', 3, 2, 'Budi Santoso', '081234567803', 'Jl. Melati No. 21, Kota Bengkulu', 'Contoh pesanan dari data awal', 'transfer', 115000, 'selesai');

INSERT INTO detail_pesanan (id_pesanan, id_produk, nama_produk, harga, jumlah, subtotal) VALUES
(1, 7, 'Kopi Bubuk Robusta 250 g', 45000, 2, 90000),
(1, 8, 'Keripik Pisang Cokelat 200 g', 25000, 1, 25000);
