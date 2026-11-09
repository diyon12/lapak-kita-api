<?php
/**
 * Fungsi bantu yang dipakai di semua halaman.
 */
// API tidak memakai session/cookie (API_MODE didefinisikan di includes/api.php)
if (!defined('API_MODE') && session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/koneksi.php';

/** Escape teks sebelum ditampilkan ke HTML (mencegah XSS). */
function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

/** Format angka menjadi Rupiah, contoh: 150000 -> Rp150.000 */
function rupiah($angka)
{
    return 'Rp' . number_format((int) $angka, 0, ',', '.');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Pesan sekali tampil (flash message) ---------- */
function set_flash($tipe, $pesan)
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ambil_flash()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/* ---------- Proteksi CSRF untuk setiap form POST ---------- */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function cek_csrf()
{
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Sesi formulir sudah kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

/* ---------- Autentikasi ---------- */
function user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function sudah_login()
{
    return isset($_SESSION['user']);
}

function is_penjual()
{
    return sudah_login() && $_SESSION['user']['role'] === 'penjual';
}

function is_pembeli()
{
    return sudah_login() && $_SESSION['user']['role'] === 'pembeli';
}

/** Hanya izinkan alamat tujuan relatif agar tidak bisa dipakai untuk open redirect. */
function url_aman($url, $default = 'index.php')
{
    $url = (string) $url;
    if ($url === '' || preg_match('#^([a-z][a-z0-9+.\-]*:|//|\\\\)#i', $url) || preg_match('/[\r\n]/', $url)) {
        return $default;
    }
    return $url;
}

/**
 * Wajibkan login. Jika $role diisi, hanya role tersebut yang boleh masuk.
 * $kembali = halaman tujuan setelah login berhasil.
 */
function wajib_login($role = null, $kembali = null)
{
    if (!sudah_login()) {
        if ($kembali === null) {
            $kembali = 'index.php';
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $kembali = basename($_SERVER['SCRIPT_NAME']);
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $kembali .= '?' . $_SERVER['QUERY_STRING'];
                }
            }
        }
        set_flash('warning', 'Silakan masuk terlebih dahulu untuk melanjutkan.');
        redirect('login.php?next=' . urlencode($kembali));
    }
    if ($role !== null && $_SESSION['user']['role'] !== $role) {
        set_flash('danger', 'Halaman tersebut hanya untuk akun ' . $role . '.');
        redirect('index.php');
    }
}

/* ---------- Data bantu ---------- */
function jumlah_item_keranjang(mysqli $conn)
{
    if (!is_pembeli()) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM keranjang WHERE id_pembeli = ?');
    $stmt->bind_param('i', $_SESSION['user']['id']);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return (int) $total;
}

/** Ikon Bootstrap Icons sebagai pengganti gambar produk yang belum diunggah. */
function ikon_kategori($id_kategori)
{
    $ikon = [
        1 => 'bi-headphones',
        2 => 'bi-bag',
        3 => 'bi-cup-hot',
        4 => 'bi-house',
        5 => 'bi-journal-bookmark',
    ];
    return isset($ikon[(int) $id_kategori]) ? $ikon[(int) $id_kategori] : 'bi-box-seam';
}

/** Tampilkan gambar produk, atau ubin ikon jika belum ada gambar. */
function gambar_produk($gambar, $id_kategori, $alt, $kelas = '')
{
    if (!empty($gambar) && is_file(__DIR__ . '/../uploads/' . $gambar)) {
        return '<img src="uploads/' . e(rawurlencode($gambar)) . '" alt="' . e($alt) . '" class="produk-foto ' . e($kelas) . '" loading="lazy">';
    }
    $nada = ((int) $id_kategori % 5) + 1;
    return '<div class="produk-foto produk-foto-kosong nada-' . $nada . ' ' . e($kelas) . '" role="img" aria-label="' . e($alt) . '">'
        . '<i class="bi ' . ikon_kategori($id_kategori) . '"></i></div>';
}

function nama_status($status)
{
    $nama = [
        'menunggu'   => 'Menunggu konfirmasi',
        'diproses'   => 'Diproses',
        'dikirim'    => 'Dikirim',
        'selesai'    => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    return isset($nama[$status]) ? $nama[$status] : $status;
}

function label_status($status)
{
    $warna = [
        'menunggu'   => 'text-bg-warning',
        'diproses'   => 'text-bg-info',
        'dikirim'    => 'text-bg-primary',
        'selesai'    => 'text-bg-success',
        'dibatalkan' => 'text-bg-secondary',
    ];
    $kelas = isset($warna[$status]) ? $warna[$status] : 'text-bg-light';
    return '<span class="badge rounded-pill ' . $kelas . '">' . e(nama_status($status)) . '</span>';
}

function tanggal_indo($datetime)
{
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($datetime);
    return date('j', $t) . ' ' . $bulan[(int) date('n', $t) - 1] . ' ' . date('Y, H:i', $t);
}

/** Panjang teks yang aman untuk huruf non-ASCII (tetap jalan meski ekstensi mbstring tidak ada). */
function panjang($teks)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen((string) $teks, 'UTF-8');
    }
    $n = preg_match_all('/./us', (string) $teks);
    return $n === false ? strlen((string) $teks) : $n;
}

/**
 * Transisi status pesanan yang boleh dilakukan penjual.
 * Kunci = status sekarang, nilai = [status tujuan => teks tombol].
 */
function transisi_status()
{
    return [
        'menunggu' => ['diproses' => 'Proses pesanan', 'dibatalkan' => 'Batalkan'],
        'diproses' => ['dikirim' => 'Tandai dikirim', 'dibatalkan' => 'Batalkan'],
        'dikirim'  => ['selesai' => 'Tandai selesai'],
        'selesai'  => [],
        'dibatalkan' => [],
    ];
}

/** Penjelasan singkat setiap status untuk pembeli. */
function keterangan_status($status)
{
    $teks = [
        'menunggu'   => 'Pesanan sudah masuk dan menunggu dikonfirmasi penjual.',
        'diproses'   => 'Penjual sudah mengonfirmasi dan sedang menyiapkan pesanan.',
        'dikirim'    => 'Pesanan sedang dalam pengiriman.',
        'selesai'    => 'Pesanan sudah diterima. Terima kasih sudah berbelanja.',
        'dibatalkan' => 'Pesanan dibatalkan oleh penjual. Stok produk sudah dikembalikan.',
    ];
    return isset($teks[$status]) ? $teks[$status] : '';
}
