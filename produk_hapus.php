<?php
/** DELETE: hapus produk milik penjual (hanya menerima POST). */
require_once 'includes/fungsi.php';
wajib_login('penjual');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('penjual_produk.php');
}
cek_csrf();

$idPenjual = (int) user()['id'];
$idProduk = isset($_POST['id_produk']) ? (int) $_POST['id_produk'] : 0;

$stmt = $conn->prepare('SELECT nama_produk, gambar FROM produk WHERE id_produk = ? AND id_penjual = ?');
$stmt->bind_param('ii', $idProduk, $idPenjual);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
    set_flash('danger', 'Produk tidak ditemukan atau bukan milik Anda.');
    redirect('penjual_produk.php');
}

// Riwayat pesanan tetap aman: detail_pesanan.id_produk menjadi NULL (ON DELETE SET NULL)
// dan item di keranjang pembeli ikut terhapus (ON DELETE CASCADE).
$stmt = $conn->prepare('DELETE FROM produk WHERE id_produk = ? AND id_penjual = ?');
$stmt->bind_param('ii', $idProduk, $idPenjual);
$stmt->execute();
$stmt->close();

if (!empty($produk['gambar'])) {
    $file = __DIR__ . '/uploads/' . basename($produk['gambar']);
    if (is_file($file)) {
        unlink($file);
    }
}

set_flash('success', $produk['nama_produk'] . ' berhasil dihapus.');
redirect('penjual_produk.php');
