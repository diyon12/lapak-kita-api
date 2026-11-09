<?php
/**
 * Logika pembuatan pesanan yang dipakai bersama oleh:
 *   - checkout.php      (aplikasi web, item diambil dari keranjang)
 *   - api/pesanan.php   (REST API, item dikirim dalam body JSON)
 * Dengan begitu aturan stok, harga, dan pemecahan per toko selalu sama.
 */

/**
 * Pesan kesalahan yang aman ditampilkan ke pengguna.
 * Kode exception dipakai API sebagai kode status HTTP (404, 409, 422).
 */
class CheckoutException extends Exception
{
}

/** Validasi data pengiriman. Mengembalikan array error (kosong jika valid). */
function validasi_pengiriman(array $d)
{
    $errors = [];
    if ($d['nama_penerima'] === '' || panjang($d['nama_penerima']) > 100) {
        $errors['nama_penerima'] = 'Nama penerima wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $d['no_hp'])) {
        $errors['no_hp'] = 'Nomor HP 8 sampai 20 digit, contoh 081234567890.';
    }
    if (panjang($d['alamat_kirim']) < 10) {
        $errors['alamat_kirim'] = 'Tulis alamat lengkap minimal 10 karakter.';
    }
    if (panjang($d['catatan']) > 255) {
        $errors['catatan'] = 'Catatan maksimal 255 karakter.';
    }
    if (!in_array($d['metode_bayar'], ['transfer', 'cod'], true)) {
        $errors['metode_bayar'] = 'Metode pembayaran harus transfer atau cod.';
    }
    return $errors;
}

/**
 * Membuat pesanan dari daftar item, dipecah menjadi satu pesanan per toko.
 * WAJIB dipanggil di dalam transaksi ($conn->begin_transaction()); commit/rollback oleh pemanggil.
 *
 * @param array $items  [['id_produk' => 1, 'jumlah' => 2], ...]
 * @param array $kirim  nama_penerima, no_hp, alamat_kirim, catatan, metode_bayar
 * @return array        [id_pesanan => kode_pesanan, ...]
 * @throws CheckoutException jika produk tidak ada atau stok tidak cukup
 */
function buat_pesanan(mysqli $conn, $idPembeli, array $items, array $kirim)
{
    // Gabungkan jumlah jika produk yang sama muncul lebih dari sekali
    $jumlahPerProduk = [];
    foreach ($items as $it) {
        $id = (int) $it['id_produk'];
        $jumlahPerProduk[$id] = (isset($jumlahPerProduk[$id]) ? $jumlahPerProduk[$id] : 0) + (int) $it['jumlah'];
    }
    if (!$jumlahPerProduk) {
        throw new CheckoutException('Tidak ada produk yang dipesan.', 422);
    }

    // Ambil data produk terbaru dan kunci barisnya selama transaksi
    $ids = array_keys($jumlahPerProduk);
    $tanda = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT id_produk, id_penjual, nama_produk, harga, stok FROM produk
         WHERE id_produk IN ($tanda) ORDER BY id_penjual, id_produk FOR UPDATE"
    );
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $produk = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $produk[(int) $p['id_produk']] = $p;
    }
    $stmt->close();

    // Cek keberadaan dan stok, lalu kelompokkan per penjual
    $perToko = [];
    foreach ($jumlahPerProduk as $id => $jumlah) {
        if (!isset($produk[$id])) {
            throw new CheckoutException('Produk dengan id ' . $id . ' tidak ditemukan.', 404);
        }
        $p = $produk[$id];
        if ($jumlah > (int) $p['stok']) {
            throw new CheckoutException('Stok ' . $p['nama_produk'] . ' tidak mencukupi (tersisa ' . (int) $p['stok'] . ').', 409);
        }
        $p['jumlah'] = $jumlah;
        $perToko[(int) $p['id_penjual']][] = $p;
    }
    ksort($perToko);

    $catatan = isset($kirim['catatan']) && $kirim['catatan'] !== '' ? $kirim['catatan'] : null;
    $stmtPesanan = $conn->prepare(
        'INSERT INTO pesanan (kode_pesanan, id_pembeli, id_penjual, nama_penerima, no_hp, alamat_kirim, catatan, metode_bayar, total_harga)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtDetail = $conn->prepare(
        'INSERT INTO detail_pesanan (id_pesanan, id_produk, nama_produk, harga, jumlah, subtotal) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmtStok = $conn->prepare('UPDATE produk SET stok = stok - ? WHERE id_produk = ? AND stok >= ?');

    $pesananBaru = [];
    foreach ($perToko as $idPenjual => $itemToko) {
        $total = 0;
        foreach ($itemToko as $it) {
            $total += (int) $it['harga'] * (int) $it['jumlah'];
        }

        // 1. Satu pesanan untuk toko ini
        $kode = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmtPesanan->bind_param(
            'siisssssi',
            $kode,
            $idPembeli,
            $idPenjual,
            $kirim['nama_penerima'],
            $kirim['no_hp'],
            $kirim['alamat_kirim'],
            $catatan,
            $kirim['metode_bayar'],
            $total
        );
        $stmtPesanan->execute();
        $idPesanan = $conn->insert_id;
        $pesananBaru[$idPesanan] = $kode;

        // 2. Detail pesanan dan 3. pengurangan stok
        foreach ($itemToko as $it) {
            $idProduk = (int) $it['id_produk'];
            $harga = (int) $it['harga'];
            $jumlah = (int) $it['jumlah'];
            $subtotal = $harga * $jumlah;

            $stmtDetail->bind_param('iisiii', $idPesanan, $idProduk, $it['nama_produk'], $harga, $jumlah, $subtotal);
            $stmtDetail->execute();

            $stmtStok->bind_param('iii', $jumlah, $idProduk, $jumlah);
            $stmtStok->execute();
            if ($stmtStok->affected_rows !== 1) {
                throw new CheckoutException('Stok ' . $it['nama_produk'] . ' berubah saat checkout. Silakan coba lagi.', 409);
            }
        }
    }
    $stmtPesanan->close();
    $stmtDetail->close();
    $stmtStok->close();

    return $pesananBaru;
}
