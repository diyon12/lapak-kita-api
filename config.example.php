<?php
/**
 * Contoh konfigurasi aplikasi.
 *
 * 1. Salin file ini menjadi "config.php" (di folder yang sama).
 * 2. Isi kredensial database sesuai server Anda.
 *
 * PENTING: config.php sudah dimasukkan ke .gitignore sehingga
 * password database TIDAK ikut terunggah ke GitHub.
 */

// --- Contoh untuk XAMPP / Laragon (lokal) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'marketplace');

// --- Contoh untuk InfinityFree (lihat menu MySQL Databases di panel) ---
// define('DB_HOST', 'sqlXXX.infinityfree.com');
// define('DB_USER', 'if0_XXXXXXXX');
// define('DB_PASS', 'password_vpanel_anda');
// define('DB_NAME', 'if0_XXXXXXXX_marketplace');

define('APP_NAME', 'LapakKita');
