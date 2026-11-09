<?php
/**
 * Bagian atas setiap halaman. Sebelum include, halaman boleh mengisi:
 *   $judul  = judul halaman (tag <title>)
 *   $aktif  = nama menu yang sedang aktif
 */
$judul = isset($judul) ? $judul : 'Belanja';
$aktif = isset($aktif) ? $aktif : '';
$u = user();
$isiKeranjang = jumlah_item_keranjang($conn);
$flash = ambil_flash();
$qNav = isset($_GET['q']) ? (string) $_GET['q'] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="tenda" aria-hidden="true"></div>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark" aria-hidden="true"><i class="bi bi-shop-window"></i></span>
            <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navUtama" aria-controls="navUtama" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navUtama">
            <form class="d-flex flex-grow-1 mx-lg-4 my-3 my-lg-0" action="index.php" method="get" role="search">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input class="form-control border-start-0" type="search" name="q" value="<?= e($qNav) ?>" placeholder="Cari produk, misalnya kopi atau kaos" aria-label="Cari produk">
                </div>
            </form>
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link<?= $aktif === 'beranda' ? ' active' : '' ?>" href="index.php">Produk</a></li>
                <?php if ($u === null): ?>
                    <li class="nav-item"><a class="nav-link<?= $aktif === 'login' ? ' active' : '' ?>" href="login.php">Masuk</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-primary btn-sm px-3" href="register.php">Daftar</a></li>
                <?php elseif ($u['role'] === 'pembeli'): ?>
                    <li class="nav-item"><a class="nav-link<?= $aktif === 'pesanan' ? ' active' : '' ?>" href="pesanan.php">Pesanan saya</a></li>
                    <li class="nav-item">
                        <a class="nav-link position-relative<?= $aktif === 'keranjang' ? ' active' : '' ?>" href="keranjang.php" aria-label="Keranjang belanja, <?= $isiKeranjang ?> barang">
                            <i class="bi bi-cart3 fs-5"></i>
                            <?php if ($isiKeranjang > 0): ?>
                                <span class="badge rounded-pill badge-keranjang"><?= $isiKeranjang ?></span>
                            <?php endif; ?>
                            <span class="d-lg-none ms-1">Keranjang</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link<?= $aktif === 'produk_saya' ? ' active' : '' ?>" href="penjual_produk.php">Produk saya</a></li>
                    <li class="nav-item"><a class="nav-link<?= $aktif === 'pesanan_masuk' ? ' active' : '' ?>" href="penjual_pesanan.php">Pesanan masuk</a></li>
                <?php endif; ?>
                <?php if ($u !== null): ?>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?= e(strtok($u['nama'], ' ')) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-secondary">
                                <?= $u['role'] === 'penjual' ? 'Akun penjual: ' . e($u['nama_toko']) : 'Akun pembeli' ?>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="logout.php" method="post" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['tipe']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['pesan']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>
