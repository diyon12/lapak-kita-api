<?php
/** GET /api/ -> daftar endpoint yang tersedia (memudahkan pengujian lewat browser). */
require_once __DIR__ . '/../includes/api.php';
api_metode(['GET']);

$dasar = api_url_dasar() . '/api';
api_sukses([
    ['metode' => 'GET',  'url' => $dasar . '/produk.php',                'fungsi' => 'Menampilkan seluruh produk (filter opsional: ?q=kata&kategori=id)'],
    ['metode' => 'GET',  'url' => $dasar . '/produk.php?id={id}',        'fungsi' => 'Menampilkan detail satu produk'],
    ['metode' => 'GET',  'url' => $dasar . '/kategori.php',              'fungsi' => 'Menampilkan daftar kategori beserta jumlah produk'],
    ['metode' => 'POST', 'url' => $dasar . '/pesanan.php',               'fungsi' => 'Membuat pesanan baru (Basic Auth akun pembeli)'],
    ['metode' => 'GET',  'url' => $dasar . '/pesanan.php',               'fungsi' => 'Menampilkan pesanan milik pembeli (Basic Auth)'],
    ['metode' => 'GET',  'url' => $dasar . '/pesanan.php?id={id}',       'fungsi' => 'Menampilkan detail satu pesanan (Basic Auth)'],
], 200, ['nama' => APP_NAME . ' REST API', 'versi' => '1.0']);
