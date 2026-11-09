<?php
require_once 'includes/fungsi.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare(
    'SELECT p.*, k.nama_kategori, u.nama_toko, u.nama AS nama_penjual
     FROM produk p
     JOIN kategori k ON k.id_kategori = p.id_kategori
     JOIN pengguna u ON u.id_pengguna = p.id_penjual
     WHERE p.id_produk = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
    http_response_code(404);
    $judul = 'Produk tidak ditemukan';
    require 'includes/header.php';
    echo '<div class="panel keadaan-kosong"><i class="bi bi-question-circle"></i>'
        . '<h1 class="h4 mt-3">Produk tidak ditemukan</h1>'
        . '<p class="text-secondary">Produk mungkin sudah dihapus oleh penjual.</p>'
        . '<a href="index.php" class="btn btn-primary">Kembali ke daftar produk</a></div>';
    require 'includes/footer.php';
    exit;
}

$stok = (int) $produk['stok'];
$milikSendiri = is_penjual() && (int) user()['id'] === (int) $produk['id_penjual'];

$judul = $produk['nama_produk'];
$aktif = 'beranda';
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="index.php">Produk</a></li>
        <li class="breadcrumb-item"><a href="index.php?kategori=<?= (int) $produk['id_kategori'] ?>"><?= e($produk['nama_kategori']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($produk['nama_produk']) ?></li>
    </ol>
</nav>

<div class="row g-4 g-lg-5">
    <div class="col-md-6 col-lg-5">
        <div class="foto-detail position-relative">
            <?= gambar_produk($produk['gambar'], $produk['id_kategori'], $produk['nama_produk']) ?>
            <?php if ($stok === 0): ?><span class="pita-habis">Stok habis</span><?php endif; ?>
        </div>
    </div>

    <div class="col-md-6 col-lg-7">
        <p class="text-secondary mb-1"><i class="bi bi-shop me-1"></i><?= e($produk['nama_toko']) ?></p>
        <h1 class="h2 mb-3"><?= e($produk['nama_produk']) ?></h1>
        <p class="mb-3"><span class="label-harga besar"><?= rupiah($produk['harga']) ?></span></p>

        <dl class="row small mb-4">
            <dt class="col-4 col-sm-3 text-secondary fw-normal">Kategori</dt>
            <dd class="col-8 col-sm-9"><?= e($produk['nama_kategori']) ?></dd>
            <dt class="col-4 col-sm-3 text-secondary fw-normal">Stok</dt>
            <dd class="col-8 col-sm-9<?= $stok === 0 ? ' text-danger fw-semibold' : '' ?>">
                <?= $stok === 0 ? 'Habis' : $stok . ' tersedia' ?>
                <?= $stok > 0 && $stok <= 5 ? '<span class="badge text-bg-warning ms-1">Tinggal sedikit</span>' : '' ?>
            </dd>
            <dt class="col-4 col-sm-3 text-secondary fw-normal">Penjual</dt>
            <dd class="col-8 col-sm-9"><?= e($produk['nama_penjual']) ?></dd>
        </dl>

        <div class="panel mb-4">
            <?php if ($milikSendiri): ?>
                <p class="mb-3">Ini produk Anda. Ubah harga, stok, atau deskripsi melalui halaman kelola produk.</p>
                <a href="produk_form.php?id=<?= (int) $produk['id_produk'] ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Ubah produk</a>
            <?php elseif (is_penjual()): ?>
                <p class="mb-0 text-secondary">Anda masuk sebagai penjual. Masuk dengan akun pembeli untuk memesan produk ini.</p>
            <?php elseif ($stok === 0): ?>
                <p class="mb-0 text-secondary">Stok produk ini sedang habis. Silakan cek kembali nanti.</p>
            <?php else: ?>
                <form action="keranjang_aksi.php" method="post" class="d-flex flex-wrap align-items-end gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tambah">
                    <input type="hidden" name="id_produk" value="<?= (int) $produk['id_produk'] ?>">
                    <div>
                        <label for="jumlah" class="form-label small text-secondary mb-1">Jumlah</label>
                        <input type="number" id="jumlah" name="jumlah" value="1" min="1" max="<?= $stok ?>" class="form-control input-jumlah" required>
                    </div>
                    <button type="submit" name="lanjut" value="produk" class="btn btn-outline-primary">
                        <i class="bi bi-cart-plus me-1"></i>Tambah ke keranjang
                    </button>
                    <button type="submit" name="lanjut" value="keranjang" class="btn btn-kunyit">Beli sekarang</button>
                </form>
                <?php if (!sudah_login()): ?>
                    <p class="small text-secondary mt-3 mb-0">Anda akan diminta masuk sebagai pembeli terlebih dahulu.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <h2 class="h5">Deskripsi</h2>
        <p class="deskripsi text-body"><?= e($produk['deskripsi'] !== null && $produk['deskripsi'] !== '' ? $produk['deskripsi'] : 'Penjual belum menambahkan deskripsi.') ?></p>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
