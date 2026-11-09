<?php
/**
 * Koneksi database menggunakan MySQLi (gaya berorientasi objek).
 * Kredensial dibaca dari config.php yang TIDAK diunggah ke GitHub.
 */
function gagal_koneksi($pesan)
{
    http_response_code(500);
    if (defined('API_MODE')) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['status' => 'error', 'code' => 500, 'message' => $pesan], JSON_UNESCAPED_UNICODE));
    }
    exit($pesan);
}

$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    gagal_koneksi('File config.php belum ada. Salin config.example.php menjadi config.php, lalu isi kredensial database.');
}
require_once $configFile;

// Lempar exception setiap kali query gagal agar kesalahan tidak terlewat.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    // Samakan zona waktu database dengan aplikasi (WIB).
    $conn->query("SET time_zone = '+07:00'");
} catch (mysqli_sql_exception $e) {
    gagal_koneksi('Koneksi ke database gagal. Periksa pengaturan di config.php.');
}
