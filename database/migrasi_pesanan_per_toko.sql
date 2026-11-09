-- =============================================================
-- Migrasi: satu pesanan = satu toko (versi 2)
-- Jalankan SEKALI di phpMyAdmin (tab SQL) jika database Anda
-- dibuat dari marketplace.sql versi pertama dan datanya ingin
-- dipertahankan. Jika tidak perlu mempertahankan data, cukup
-- impor ulang marketplace.sql yang baru.
--
-- Perubahan:
--   1. pesanan mendapat kolom id_penjual (FK ke pengguna)
--   2. detail_pesanan.id_penjual dihapus (sudah ada di pesanan)
-- Catatan: pesanan lama yang berisi produk dari beberapa toko
-- akan dimiliki oleh toko dengan id terkecil di pesanan itu.
-- =============================================================

ALTER TABLE pesanan
  ADD COLUMN id_penjual INT UNSIGNED NULL AFTER id_pembeli;

UPDATE pesanan ps
SET ps.id_penjual = (
  SELECT MIN(dp.id_penjual) FROM detail_pesanan dp WHERE dp.id_pesanan = ps.id_pesanan
);

-- Pesanan tanpa detail (seharusnya tidak ada) dihapus agar kolom bisa NOT NULL
DELETE FROM pesanan WHERE id_penjual IS NULL;

ALTER TABLE pesanan
  MODIFY id_penjual INT UNSIGNED NOT NULL,
  ADD KEY idx_pesanan_penjual (id_penjual),
  ADD CONSTRAINT fk_pesanan_penjual FOREIGN KEY (id_penjual)
    REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE detail_pesanan
  DROP FOREIGN KEY fk_detail_penjual,
  DROP KEY idx_detail_penjual,
  DROP COLUMN id_penjual;
