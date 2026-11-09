<?php
require_once 'includes/fungsi.php';
wajib_login('pembeli');
$idPembeli = (int) user()['id'];

$stmt = $conn->prepare(
    'SELECT k.id_keranjang, k.jumlah, p.id_produk, p.nama_produk, p.harga, p.stok,
            p.gambar, p.id_kategori, u.nama_toko
     FROM keranjang k
     JOIN produk p ON p.id_produk = k.id_produk
     JOIN pengguna u ON u.id_pengguna = p.id_penjual
     WHERE k.id_pembeli = ?
     ORDER BY k.created_at DESC, k.id_keranjang DESC'
);
$stmt->bind_param('i', $idPembeli);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0;
$totalBarang = 0;
$adaMasalahStok = false;
foreach ($items as $it) {
    $total += (int) $it['harga'] * (int) $it['jumlah'];
    $totalBarang += (int) $it['jumlah'];
    if ((int) $it['jumlah'] > (int) $it['stok']) {
        $adaMasalahStok = true;
    }
}

$judul = 'Keranjang belanja';
$aktif = 'keranjang';
require 'includes/header.php';
?>

<h1 class="h3 mb-4">Keranjang belanja</h1>

<?php if (!$items): ?>
    <div class="panel keadaan-kosong">
        <i class="bi bi-cart3"></i>
        <h2 class="h5 mt-3">Keranjang Anda masih kosong</h2>
        <p class="text-secondary">Buka daftar produk dan tekan “Tambah ke keranjang” pada produk yang Anda inginkan.</p>
        <a href="index.php" class="btn btn-primary">Mulai belanja</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <?php if ($adaMasalahStok): ?>
                <div class="alert alert-warning">
                    Stok beberapa produk sudah berubah. Kurangi jumlah yang ditandai merah sebelum checkout.
                </div>
            <?php endif; ?>
            <div class="panel p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col" class="text-end">Harga</th>
                                <th scope="col" class="text-center">Jumlah</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col"><span class="visually-hidden">Hapus</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <?php $lebih = (int) $it['jumlah'] > (int) $it['stok']; ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <a href="produk.php?id=<?= (int) $it['id_produk'] ?>" class="thumb-kecil">
                                            <?= gambar_produk($it['gambar'], $it['id_kategori'], $it['nama_produk']) ?>
                                        </a>
                                        <div>
                                            <a href="produk.php?id=<?= (int) $it['id_produk'] ?>" class="fw-semibold text-decoration-none text-body"><?= e($it['nama_produk']) ?></a>
                                            <div class="small text-secondary"><?= e($it['nama_toko']) ?></div>
                                            <?php if ($lebih): ?>
                                                <div class="small text-danger fw-semibold">Stok tersisa <?= (int) $it['stok'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap"><?= rupiah($it['harga']) ?></td>
                                <td class="text-center">
                                    <form action="keranjang_aksi.php" method="post" class="d-inline-flex gap-1 justify-content-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="ubah">
                                        <input type="hidden" name="id_keranjang" value="<?= (int) $it['id_keranjang'] ?>">
                                        <label class="visually-hidden" for="jml<?= (int) $it['id_keranjang'] ?>">Jumlah <?= e($it['nama_produk']) ?></label>
                                        <input type="number" id="jml<?= (int) $it['id_keranjang'] ?>" name="jumlah"
                                               value="<?= (int) $it['jumlah'] ?>" min="1" max="<?= max(1, (int) $it['stok']) ?>"
                                               class="form-control form-control-sm input-jumlah<?= $lebih ? ' is-invalid' : '' ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Simpan jumlah">
                                            <i class="bi bi-arrow-repeat"></i><span class="visually-hidden">Simpan jumlah</span>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end fw-semibold text-nowrap"><?= rupiah((int) $it['harga'] * (int) $it['jumlah']) ?></td>
                                <td class="text-end">
                                    <form action="keranjang_aksi.php" method="post" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_keranjang" value="<?= (int) $it['id_keranjang'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger" title="Hapus dari keranjang">
                                            <i class="bi bi-trash3"></i><span class="visually-hidden">Hapus <?= e($it['nama_produk']) ?></span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <a href="index.php" class="btn btn-link px-0 mt-3"><i class="bi bi-arrow-left me-1"></i>Lanjut belanja</a>
        </div>

        <div class="col-lg-4">
            <aside class="panel ringkasan">
                <h2 class="h5 mb-3">Ringkasan belanja</h2>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Total barang</span>
                    <span><?= $totalBarang ?> barang</span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3 mb-4">
                    <span class="fw-semibold">Total harga</span>
                    <span class="label-harga"><?= rupiah($total) ?></span>
                </div>
                <?php if ($adaMasalahStok): ?>
                    <button class="btn btn-primary w-100" disabled>Checkout</button>
                    <p class="small text-secondary mt-2 mb-0">Sesuaikan jumlah dengan stok terlebih dahulu.</p>
                <?php else: ?>
                    <a href="checkout.php" class="btn btn-primary w-100">Checkout</a>
                <?php endif; ?>
            </aside>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
