<?php
/**
 * Fungsi bantu untuk semua endpoint REST API di folder api/.
 * Setiap endpoint cukup: require_once __DIR__ . '/../includes/api.php';
 */
define('API_MODE', true); // fungsi.php tidak memulai session untuk API

require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/pesanan_fungsi.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');

// Kesalahan tak terduga tetap dibalas JSON, tanpa membocorkan detail server
set_exception_handler(function ($e) {
    error_log('API error: ' . $e->getMessage());
    api_error(500, 'Terjadi kesalahan pada server.');
});

/** Kirim response JSON lalu hentikan script. */
function api_json($kodeHttp, array $isi)
{
    http_response_code($kodeHttp);
    echo json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Response sukses: {"status": "success", ..., "data": ...} */
function api_sukses($data, $kodeHttp = 200, array $tambahan = [])
{
    api_json($kodeHttp, ['status' => 'success'] + $tambahan + ['data' => $data]);
}

/** Response gagal: {"status": "error", "code": 404, "message": "...", "errors": {...}} */
function api_error($kodeHttp, $pesan, $errors = null)
{
    $isi = ['status' => 'error', 'code' => $kodeHttp, 'message' => $pesan];
    if ($errors) {
        $isi['errors'] = $errors;
    }
    api_json($kodeHttp, $isi);
}

/** Batasi metode HTTP yang boleh dipakai sebuah endpoint. */
function api_metode(array $diizinkan)
{
    $metode = $_SERVER['REQUEST_METHOD'];
    if ($metode === 'OPTIONS') { // preflight CORS dari browser
        header('Access-Control-Allow-Methods: ' . implode(', ', $diizinkan) . ', OPTIONS');
        http_response_code(204);
        exit;
    }
    if (!in_array($metode, $diizinkan, true)) {
        header('Allow: ' . implode(', ', $diizinkan));
        api_error(405, 'Metode ' . $metode . ' tidak didukung oleh endpoint ini. Gunakan ' . implode(' atau ', $diizinkan) . '.');
    }
    return $metode;
}

/** Ambil parameter query berupa bilangan bulat positif. null jika tidak dikirim. */
function api_param_id($nama)
{
    if (!isset($_GET[$nama])) {
        return null;
    }
    $nilai = is_string($_GET[$nama]) ? $_GET[$nama] : '';
    if (!ctype_digit($nilai) || (int) $nilai < 1) {
        api_error(400, 'Parameter ' . $nama . ' harus berupa bilangan bulat positif.');
    }
    return (int) $nilai;
}

/** Baca body request JSON menjadi array. */
function api_body()
{
    $mentah = file_get_contents('php://input');
    if ($mentah === false || trim($mentah) === '') {
        if (!empty($_POST)) {
            return $_POST; // dukungan form-data / x-www-form-urlencoded
        }
        api_error(400, 'Body request kosong. Kirim data dalam format JSON.');
    }
    $data = json_decode($mentah, true);
    if (!is_array($data)) {
        api_error(400, 'Body request bukan JSON yang valid (' . json_last_error_msg() . ').');
    }
    return $data;
}

/** Ambil nilai teks dari body; nilai bukan teks/angka dianggap kosong. */
function api_teks(array $body, $kunci, $bawaan = '')
{
    if (!isset($body[$kunci]) || !is_scalar($body[$kunci])) {
        return $bawaan;
    }
    return trim((string) $body[$kunci]);
}

/** Cek bilangan bulat positif yang dikirim sebagai angka atau teks angka. */
function api_bulat_positif($nilai)
{
    if (is_int($nilai)) {
        return $nilai > 0;
    }
    return is_string($nilai) && ctype_digit($nilai) && (int) $nilai > 0;
}

/**
 * Autentikasi HTTP Basic (email + password akun pembeli).
 * Mengembalikan data pembeli, atau langsung membalas 401/403.
 */
function api_auth_pembeli(mysqli $conn)
{
    $email = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
    $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

    // Sebagian konfigurasi Apache hanya meneruskan header mentahnya
    if ($email === null) {
        $header = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (stripos($header, 'Basic ') === 0) {
            $dekode = base64_decode(substr($header, 6), true);
            if ($dekode !== false && strpos($dekode, ':') !== false) {
                list($email, $password) = explode(':', $dekode, 2);
            }
        }
    }

    if ($email === null || $email === '') {
        header('WWW-Authenticate: Basic realm="LapakKita API"');
        api_error(401, 'Autentikasi diperlukan. Gunakan Basic Auth dengan email dan password akun pembeli.');
    }

    $stmt = $conn->prepare('SELECT id_pengguna, nama, email, password, role FROM pengguna WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$akun || !password_verify($password, $akun['password'])) {
        header('WWW-Authenticate: Basic realm="LapakKita API"');
        api_error(401, 'Email atau password salah.');
    }
    if ($akun['role'] !== 'pembeli') {
        api_error(403, 'Endpoint ini hanya untuk akun pembeli.');
    }
    unset($akun['password']);
    return $akun;
}

/** Alamat dasar aplikasi, contoh: http://localhost/marketplace */
function api_url_dasar()
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $folder = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $folder;
}

function api_url_gambar($file)
{
    if (empty($file) || !is_file(__DIR__ . '/../uploads/' . $file)) {
        return null;
    }
    return api_url_dasar() . '/uploads/' . rawurlencode($file);
}

/** Ubah baris produk dari database menjadi struktur JSON. */
function api_produk(array $p, $lengkap = false)
{
    $data = [
        'id_produk'    => (int) $p['id_produk'],
        'nama_produk'  => $p['nama_produk'],
        'harga'        => (int) $p['harga'],
        'harga_format' => rupiah($p['harga']),
        'stok'         => (int) $p['stok'],
        'tersedia'     => (int) $p['stok'] > 0,
        'kategori'     => ['id_kategori' => (int) $p['id_kategori'], 'nama_kategori' => $p['nama_kategori']],
        'penjual'      => ['id_penjual' => (int) $p['id_penjual'], 'nama_toko' => $p['nama_toko']],
        'gambar_url'   => api_url_gambar($p['gambar']),
    ];
    if ($lengkap) {
        $data['penjual']['nama'] = $p['nama_penjual'];
        $data['deskripsi'] = $p['deskripsi'];
        $data['dibuat'] = $p['created_at'];
        $data['diperbarui'] = $p['updated_at'];
    } else {
        $data['url_detail'] = api_url_dasar() . '/api/produk.php?id=' . (int) $p['id_produk'];
    }
    return $data;
}

/** Ambil satu pesanan milik pembeli beserta itemnya. null jika tidak ada / bukan miliknya. */
function api_pesanan(mysqli $conn, $idPesanan, $idPembeli)
{
    $stmt = $conn->prepare(
        'SELECT ps.*, u.nama_toko FROM pesanan ps JOIN pengguna u ON u.id_pengguna = ps.id_penjual
         WHERE ps.id_pesanan = ? AND ps.id_pembeli = ?'
    );
    $stmt->bind_param('ii', $idPesanan, $idPembeli);
    $stmt->execute();
    $ps = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ps) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id_produk, nama_produk, harga, jumlah, subtotal FROM detail_pesanan WHERE id_pesanan = ? ORDER BY id_detail');
    $stmt->bind_param('i', $idPesanan);
    $stmt->execute();
    $items = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
        $items[] = [
            'id_produk'   => $d['id_produk'] === null ? null : (int) $d['id_produk'],
            'nama_produk' => $d['nama_produk'],
            'harga'       => (int) $d['harga'],
            'jumlah'      => (int) $d['jumlah'],
            'subtotal'    => (int) $d['subtotal'],
        ];
    }
    $stmt->close();

    return [
        'id_pesanan'    => (int) $ps['id_pesanan'],
        'kode_pesanan'  => $ps['kode_pesanan'],
        'status'        => $ps['status'],
        'status_label'  => nama_status($ps['status']),
        'toko'          => ['id_penjual' => (int) $ps['id_penjual'], 'nama_toko' => $ps['nama_toko']],
        'penerima'      => ['nama' => $ps['nama_penerima'], 'no_hp' => $ps['no_hp'], 'alamat' => $ps['alamat_kirim']],
        'metode_bayar'  => $ps['metode_bayar'],
        'catatan'       => $ps['catatan'],
        'items'         => $items,
        'total_harga'   => (int) $ps['total_harga'],
        'total_format'  => rupiah($ps['total_harga']),
        'tanggal_pesan' => $ps['tanggal_pesan'],
    ];
}
