<?php
/**
 * Semua request di endpoint ini memakai HTTP Basic Auth (email + password akun pembeli).
 *
 * POST /api/pesanan.php          -> membuat pesanan baru (body JSON)
 * GET  /api/pesanan.php          -> daftar pesanan milik pembeli
 * GET  /api/pesanan.php?id={id}  -> detail satu pesanan milik pembeli
 *
 * Contoh body POST:
 * {
 *   "nama_penerima": "Budi Santoso",
 *   "no_hp": "081234567803",
 *   "alamat_kirim": "Jl. Melati No. 21, Kota Bengkulu",
 *   "metode_bayar": "cod",
 *   "catatan": "opsional",
 *   "items": [ {"id_produk": 7, "jumlah": 2}, {"id_produk": 1, "jumlah": 1} ]
 * }
 */
require_once __DIR__ . '/../includes/api.php';
$metode = api_metode(['GET', 'POST']);
$pembeli = api_auth_pembeli($conn);
$idPembeli = (int) $pembeli['id_pengguna'];

/* ---------------- GET: baca pesanan ---------------- */
if ($metode === 'GET') {
    $id = api_param_id('id');
    if ($id !== null) {
        $pesanan = api_pesanan($conn, $id, $idPembeli);
        if (!$pesanan) {
            api_error(404, 'Pesanan dengan id ' . $id . ' tidak ditemukan.');
        }
        api_sukses($pesanan);
    }

    $stmt = $conn->prepare('SELECT id_pesanan FROM pesanan WHERE id_pembeli = ? ORDER BY tanggal_pesan DESC, id_pesanan DESC');
    $stmt->bind_param('i', $idPembeli);
    $stmt->execute();
    $daftarId = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_pesanan');
    $stmt->close();

    $data = [];
    foreach ($daftarId as $idPs) {
        $data[] = api_pesanan($conn, (int) $idPs, $idPembeli);
    }
    api_sukses($data, 200, ['jumlah' => count($data)]);
}

/* ---------------- POST: buat pesanan baru ---------------- */
$body = api_body();
$kirim = [
    'nama_penerima' => api_teks($body, 'nama_penerima'),
    'no_hp'         => api_teks($body, 'no_hp'),
    'alamat_kirim'  => api_teks($body, 'alamat_kirim'),
    'catatan'       => api_teks($body, 'catatan'),
    'metode_bayar'  => api_teks($body, 'metode_bayar', 'transfer'),
];
$errors = validasi_pengiriman($kirim);

$items = [];
if (!isset($body['items']) || !is_array($body['items']) || !$body['items']) {
    $errors['items'] = 'items wajib berupa array berisi minimal 1 produk, contoh: [{"id_produk": 1, "jumlah": 2}].';
} elseif (count($body['items']) > 50) {
    $errors['items'] = 'Maksimal 50 item dalam satu request.';
} else {
    foreach (array_values($body['items']) as $i => $it) {
        if (!is_array($it) || !isset($it['id_produk'], $it['jumlah'])
            || !api_bulat_positif($it['id_produk']) || !api_bulat_positif($it['jumlah']) || (int) $it['jumlah'] > 1000) {
            $errors['items[' . $i . ']'] = 'id_produk dan jumlah wajib bilangan bulat positif (jumlah maksimal 1000).';
            continue;
        }
        $items[] = ['id_produk' => (int) $it['id_produk'], 'jumlah' => (int) $it['jumlah']];
    }
}

if ($errors) {
    api_error(422, 'Data pesanan tidak valid.', $errors);
}

$conn->begin_transaction();
try {
    $pesananBaru = buat_pesanan($conn, $idPembeli, $items, $kirim);
    $conn->commit();
} catch (CheckoutException $e) {
    $conn->rollback();
    api_error($e->getCode() ?: 422, $e->getMessage());
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('API pesanan gagal: ' . $e->getMessage());
    api_error(500, 'Pesanan gagal disimpan karena gangguan database.');
}

// Baca ulang dari database sebagai bukti data benar-benar tersimpan
$data = [];
foreach (array_keys($pesananBaru) as $idPs) {
    $data[] = api_pesanan($conn, $idPs, $idPembeli);
}
header('Location: ' . api_url_dasar() . '/api/pesanan.php?id=' . $data[0]['id_pesanan']);
api_sukses($data, 201, [
    'message' => count($data) . ' pesanan berhasil dibuat' . (count($data) > 1 ? ', dipecah satu pesanan per toko' : '') . '.',
    'jumlah'  => count($data),
]);
