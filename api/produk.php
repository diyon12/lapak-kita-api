<?php
/**
 * GET /api/produk.php                  -> daftar seluruh produk
 * GET /api/produk.php?q=kopi           -> cari produk berdasarkan nama/deskripsi
 * GET /api/produk.php?kategori=3       -> produk dalam satu kategori
 * GET /api/produk.php?id=1             -> detail satu produk
 */
require_once __DIR__ . '/../includes/api.php';
api_metode(['GET']);

$sqlDasar = 'SELECT p.*, k.nama_kategori, u.nama_toko, u.nama AS nama_penjual
             FROM produk p
             JOIN kategori k ON k.id_kategori = p.id_kategori
             JOIN pengguna u ON u.id_pengguna = p.id_penjual';

/* ---------- Detail produk ---------- */
$id = api_param_id('id');
if ($id !== null) {
    $stmt = $conn->prepare($sqlDasar . ' WHERE p.id_produk = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $produk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$produk) {
        api_error(404, 'Produk dengan id ' . $id . ' tidak ditemukan.');
    }
    api_sukses(api_produk($produk, true));
}

/* ---------- Daftar produk (dengan filter opsional) ---------- */
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
if (panjang($q) > 100) {
    api_error(400, 'Parameter q maksimal 100 karakter.');
}
$idKategori = api_param_id('kategori');
if ($idKategori !== null) {
    $stmt = $conn->prepare('SELECT 1 FROM kategori WHERE id_kategori = ?');
    $stmt->bind_param('i', $idKategori);
    $stmt->execute();
    $ada = $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$ada) {
        api_error(404, 'Kategori dengan id ' . $idKategori . ' tidak ditemukan.');
    }
}

$syarat = [];
$tipe = '';
$nilai = [];
if ($q !== '') {
    $syarat[] = '(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
    $kata = '%' . $q . '%';
    $tipe .= 'ss';
    $nilai[] = $kata;
    $nilai[] = $kata;
}
if ($idKategori !== null) {
    $syarat[] = 'p.id_kategori = ?';
    $tipe .= 'i';
    $nilai[] = $idKategori;
}
$sql = $sqlDasar . ($syarat ? ' WHERE ' . implode(' AND ', $syarat) : '') . ' ORDER BY p.id_produk';

$stmt = $conn->prepare($sql);
if ($tipe !== '') {
    $stmt->bind_param($tipe, ...$nilai);
}
$stmt->execute();
$baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$data = [];
foreach ($baris as $p) {
    $data[] = api_produk($p);
}
api_sukses($data, 200, [
    'jumlah' => count($data),
    'filter' => ['q' => $q !== '' ? $q : null, 'kategori' => $idKategori],
]);
