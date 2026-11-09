<?php
require_once 'includes/fungsi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
cek_csrf();

// Kosongkan semua data sesi lalu ganti ID sesi agar sesi lama tidak bisa dipakai lagi
$_SESSION = [];
session_regenerate_id(true);

set_flash('success', 'Anda sudah keluar.');
redirect('index.php');
