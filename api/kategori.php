<?php
/**
 * GET /api/kategori.php        -> daftar kategori beserta jumlah produknya
 * GET /api/kategori.php?id=3   -> satu kategori
 */
require_once __DIR__ . '/../includes/api.php';
api_metode(['GET']);

$sql = 'SELECT k.id_kategori, k.nama_kategori, COUNT(p.id_produk) AS jumlah_produk
        FROM kategori k
        LEFT JOIN produk p ON p.id_kategori = k.id_kategori';

$id = api_param_id('id');
if ($id !== null) {
    $stmt = $conn->prepare($sql . ' WHERE k.id_kategori = ? GROUP BY k.id_kategori, k.nama_kategori');
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare($sql . ' GROUP BY k.id_kategori, k.nama_kategori ORDER BY k.id_kategori');
}
$stmt->execute();
$baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$data = [];
foreach ($baris as $k) {
    $data[] = [
        'id_kategori'   => (int) $k['id_kategori'],
        'nama_kategori' => $k['nama_kategori'],
        'jumlah_produk' => (int) $k['jumlah_produk'],
        'url_produk'    => api_url_dasar() . '/api/produk.php?kategori=' . (int) $k['id_kategori'],
    ];
}

if ($id !== null) {
    if (!$data) {
        api_error(404, 'Kategori dengan id ' . $id . ' tidak ditemukan.');
    }
    api_sukses($data[0]);
}
api_sukses($data, 200, ['jumlah' => count($data)]);
