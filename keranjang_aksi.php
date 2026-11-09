<?php
/**
 * Memproses aksi keranjang (hanya menerima POST):
 *   aksi=tambah  -> id_produk, jumlah, lanjut (produk|keranjang)
 *   aksi=ubah    -> id_keranjang, jumlah
 *   aksi=hapus   -> id_keranjang
 */
require_once 'includes/fungsi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('keranjang.php');
}
cek_csrf();

$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

/* ---------------- Tambah produk ke keranjang ---------------- */
if ($aksi === 'tambah') {
    $idProduk = isset($_POST['id_produk']) ? (int) $_POST['id_produk'] : 0;
    $jumlah = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 1;
    $halamanProduk = 'produk.php?id=' . $idProduk;

    wajib_login('pembeli', $halamanProduk);
    $idPembeli = (int) user()['id'];

    if ($jumlah < 1) {
        set_flash('warning', 'Jumlah minimal 1.');
        redirect($halamanProduk);
    }

    $stmt = $conn->prepare('SELECT nama_produk, stok FROM produk WHERE id_produk = ?');
    $stmt->bind_param('i', $idProduk);
    $stmt->execute();
    $produk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$produk) {
        set_flash('danger', 'Produk tidak ditemukan.');
        redirect('index.php');
    }

    // Jumlah yang sudah ada di keranjang ikut dihitung agar tidak melebihi stok
    $stmt = $conn->prepare('SELECT jumlah FROM keranjang WHERE id_pembeli = ? AND id_produk = ?');
    $stmt->bind_param('ii', $idPembeli, $idProduk);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sudahAda = $baris ? (int) $baris['jumlah'] : 0;
    $jumlahBaru = $sudahAda + $jumlah;

    if ($jumlahBaru > (int) $produk['stok']) {
        $sisa = max(0, (int) $produk['stok'] - $sudahAda);
        set_flash('warning', 'Stok tidak cukup. Anda masih bisa menambah ' . $sisa . ' lagi untuk ' . $produk['nama_produk'] . '.');
        redirect($halamanProduk);
    }

    $stmt = $conn->prepare(
        'INSERT INTO keranjang (id_pembeli, id_produk, jumlah) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah)'
    );
    $stmt->bind_param('iii', $idPembeli, $idProduk, $jumlahBaru);
    $stmt->execute();
    $stmt->close();

    if (isset($_POST['lanjut']) && $_POST['lanjut'] === 'keranjang') {
        redirect('keranjang.php');
    }
    set_flash('success', $produk['nama_produk'] . ' ditambahkan ke keranjang.');
    redirect($halamanProduk);
}

/* ---------------- Ubah jumlah atau hapus item ---------------- */
wajib_login('pembeli', 'keranjang.php');
$idPembeli = (int) user()['id'];
$idKeranjang = isset($_POST['id_keranjang']) ? (int) $_POST['id_keranjang'] : 0;

// Pastikan item benar-benar milik pembeli yang sedang login
$stmt = $conn->prepare(
    'SELECT k.id_keranjang, p.nama_produk, p.stok
     FROM keranjang k JOIN produk p ON p.id_produk = k.id_produk
     WHERE k.id_keranjang = ? AND k.id_pembeli = ?'
);
$stmt->bind_param('ii', $idKeranjang, $idPembeli);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    set_flash('danger', 'Item keranjang tidak ditemukan.');
    redirect('keranjang.php');
}

if ($aksi === 'ubah') {
    $jumlah = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 1;
    if ($jumlah < 1) {
        $aksi = 'hapus';
    } elseif ($jumlah > (int) $item['stok']) {
        set_flash('warning', 'Stok ' . $item['nama_produk'] . ' hanya tersisa ' . (int) $item['stok'] . '.');
        redirect('keranjang.php');
    } else {
        $stmt = $conn->prepare('UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ? AND id_pembeli = ?');
        $stmt->bind_param('iii', $jumlah, $idKeranjang, $idPembeli);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Jumlah ' . $item['nama_produk'] . ' diperbarui.');
        redirect('keranjang.php');
    }
}

if ($aksi === 'hapus') {
    $stmt = $conn->prepare('DELETE FROM keranjang WHERE id_keranjang = ? AND id_pembeli = ?');
    $stmt->bind_param('ii', $idKeranjang, $idPembeli);
    $stmt->execute();
    $stmt->close();
    set_flash('success', $item['nama_produk'] . ' dihapus dari keranjang.');
    redirect('keranjang.php');
}

redirect('keranjang.php');
