<?php
/**
 * Penjual mengubah status pesanan tokonya (hanya menerima POST).
 * Alur yang diizinkan diatur oleh transisi_status() di includes/fungsi.php:
 *   menunggu -> diproses -> dikirim -> selesai
 *   menunggu / diproses -> dibatalkan (stok produk dikembalikan)
 */
require_once 'includes/fungsi.php';
wajib_login('penjual');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('penjual_pesanan.php');
}
cek_csrf();

$idPenjual = (int) user()['id'];
$idPesanan = isset($_POST['id_pesanan']) ? (int) $_POST['id_pesanan'] : 0;
$tujuan = isset($_POST['status']) ? (string) $_POST['status'] : '';
$kembali = 'penjual_pesanan.php#pesanan-' . $idPesanan;

$conn->begin_transaction();
try {
    // Kunci baris pesanan agar tidak diubah dua kali secara bersamaan
    $stmt = $conn->prepare('SELECT kode_pesanan, status FROM pesanan WHERE id_pesanan = ? AND id_penjual = ? FOR UPDATE');
    $stmt->bind_param('ii', $idPesanan, $idPenjual);
    $stmt->execute();
    $pesanan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pesanan) {
        $conn->rollback();
        set_flash('danger', 'Pesanan tidak ditemukan atau bukan milik toko Anda.');
        redirect('penjual_pesanan.php');
    }

    $boleh = transisi_status();
    if (!isset($boleh[$pesanan['status']][$tujuan])) {
        $conn->rollback();
        set_flash('warning', 'Status ' . $pesanan['kode_pesanan'] . ' tidak bisa diubah dari "'
            . nama_status($pesanan['status']) . '" menjadi "' . nama_status($tujuan) . '".');
        redirect($kembali);
    }

    if ($tujuan === 'dibatalkan') {
        // Kembalikan stok untuk produk yang masih ada (produk yang sudah dihapus dilewati)
        $stmt = $conn->prepare(
            'UPDATE produk p JOIN detail_pesanan dp ON dp.id_produk = p.id_produk
             SET p.stok = p.stok + dp.jumlah
             WHERE dp.id_pesanan = ?'
        );
        $stmt->bind_param('i', $idPesanan);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare('UPDATE pesanan SET status = ? WHERE id_pesanan = ? AND id_penjual = ?');
    $stmt->bind_param('sii', $tujuan, $idPesanan, $idPenjual);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $pesan = 'Status ' . $pesanan['kode_pesanan'] . ' diubah menjadi "' . nama_status($tujuan) . '".';
    if ($tujuan === 'dibatalkan') {
        $pesan .= ' Stok produk sudah dikembalikan.';
    }
    set_flash('success', $pesan);
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('Ubah status gagal: ' . $e->getMessage());
    set_flash('danger', 'Status gagal diubah karena gangguan database. Silakan coba lagi.');
}
redirect($kembali);
