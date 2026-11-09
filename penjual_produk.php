<?php
/** READ: daftar produk milik penjual yang sedang login. */
require_once 'includes/fungsi.php';
wajib_login('penjual');
$idPenjual = (int) user()['id'];

$stmt = $conn->prepare(
    'SELECT p.id_produk, p.nama_produk, p.harga, p.stok, p.gambar, p.id_kategori, p.updated_at, k.nama_kategori
     FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori
     WHERE p.id_penjual = ?
     ORDER BY p.updated_at DESC, p.id_produk DESC'
);
$stmt->bind_param('i', $idPenjual);
$stmt->execute();
$produk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$judul = 'Produk saya';
$aktif = 'produk_saya';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Produk saya</h1>
        <p class="text-secondary mb-0"><?= e(user()['nama_toko']) ?> memiliki <?= count($produk) ?> produk.</p>
    </div>
    <a href="produk_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah produk</a>
</div>

<?php if (!$produk): ?>
    <div class="panel keadaan-kosong">
        <i class="bi bi-box-seam"></i>
        <h2 class="h5 mt-3">Belum ada produk</h2>
        <p class="text-secondary">Tambahkan produk pertama Anda agar tampil di halaman depan.</p>
        <a href="produk_form.php" class="btn btn-primary">Tambah produk</a>
    </div>
<?php else: ?>
    <div class="panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Kategori</th>
                        <th scope="col" class="text-end">Harga</th>
                        <th scope="col" class="text-center">Stok</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produk as $p): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="thumb-kecil"><?= gambar_produk($p['gambar'], $p['id_kategori'], $p['nama_produk']) ?></div>
                                <a href="produk.php?id=<?= (int) $p['id_produk'] ?>" class="fw-semibold text-decoration-none text-body"><?= e($p['nama_produk']) ?></a>
                            </div>
                        </td>
                        <td><?= e($p['nama_kategori']) ?></td>
                        <td class="text-end text-nowrap"><?= rupiah($p['harga']) ?></td>
                        <td class="text-center">
                            <?= (int) $p['stok'] === 0 ? '<span class="badge text-bg-secondary">Habis</span>' : (int) $p['stok'] ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="produk_form.php?id=<?= (int) $p['id_produk'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Ubah</a>
                            <form action="produk_hapus.php" method="post" class="d-inline"
                                  onsubmit="return confirm('Hapus <?= e(addslashes($p['nama_produk'])) ?>? Tindakan ini tidak bisa dibatalkan.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
