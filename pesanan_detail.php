<?php
require_once 'includes/fungsi.php';
wajib_login('pembeli');
$idPembeli = (int) user()['id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Pembeli hanya boleh melihat pesanannya sendiri
$stmt = $conn->prepare(
    'SELECT ps.*, u.nama_toko
     FROM pesanan ps JOIN pengguna u ON u.id_pengguna = ps.id_penjual
     WHERE ps.id_pesanan = ? AND ps.id_pembeli = ?'
);
$stmt->bind_param('ii', $id, $idPembeli);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

$stmt = $conn->prepare(
    'SELECT dp.*, p.gambar, p.id_kategori
     FROM detail_pesanan dp
     LEFT JOIN produk p ON p.id_produk = dp.id_produk
     WHERE dp.id_pesanan = ?
     ORDER BY dp.id_detail'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$baru = isset($_GET['baru']);
$judul = 'Pesanan ' . $pesanan['kode_pesanan'];
$aktif = 'pesanan';
require 'includes/header.php';
?>

<?php if ($baru): ?>
    <div class="alert alert-success d-flex gap-3 align-items-start">
        <i class="bi bi-check-circle-fill fs-4"></i>
        <div>
            <strong>Pesanan berhasil dibuat.</strong><br>
            Simpan kode <strong><?= e($pesanan['kode_pesanan']) ?></strong>. Statusnya akan berubah di halaman ini setelah <?= e($pesanan['nama_toko']) ?> memproses pesanan Anda.
        </div>
    </div>
<?php endif; ?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="pesanan.php">Pesanan saya</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($pesanan['kode_pesanan']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0"><?= e($pesanan['kode_pesanan']) ?></h1>
    <?= label_status($pesanan['status']) ?>
</div>

<?php
$tahapan = ['menunggu', 'diproses', 'dikirim', 'selesai'];
$posisi = array_search($pesanan['status'], $tahapan, true);
?>
<section class="panel mb-4">
    <p class="mb-3"><i class="bi bi-shop me-1"></i><?= e($pesanan['nama_toko']) ?>: <?= e(keterangan_status($pesanan['status'])) ?></p>
    <?php if ($posisi !== false): ?>
        <ol class="tahapan-status list-unstyled d-flex flex-wrap gap-2 mb-0">
            <?php foreach ($tahapan as $i => $t): ?>
                <li class="<?= $i < $posisi ? 'lewat' : ($i === $posisi ? 'kini' : '') ?>">
                    <i class="bi <?= $i <= $posisi ? 'bi-check-circle-fill' : 'bi-circle' ?> me-1"></i><?= e(nama_status($t)) ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Produk</th>
                            <th scope="col" class="text-end">Harga</th>
                            <th scope="col" class="text-center">Jumlah</th>
                            <th scope="col" class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detail as $d): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="thumb-kecil"><?= gambar_produk($d['gambar'], $d['id_kategori'], $d['nama_produk']) ?></div>
                                    <div>
                                        <?php if ($d['id_produk'] !== null): ?>
                                            <a href="produk.php?id=<?= (int) $d['id_produk'] ?>" class="fw-semibold text-decoration-none text-body"><?= e($d['nama_produk']) ?></a>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?= e($d['nama_produk']) ?></span>
                                            <span class="small text-secondary">(produk sudah dihapus)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap"><?= rupiah($d['harga']) ?></td>
                            <td class="text-center"><?= (int) $d['jumlah'] ?></td>
                            <td class="text-end fw-semibold text-nowrap"><?= rupiah($d['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end text-nowrap"><?= rupiah($pesanan['total_harga']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <aside class="panel">
            <h2 class="h6 text-secondary">Dikirim ke</h2>
            <p class="mb-1 fw-semibold"><?= e($pesanan['nama_penerima']) ?></p>
            <p class="mb-1"><?= e($pesanan['no_hp']) ?></p>
            <p class="deskripsi mb-3"><?= e($pesanan['alamat_kirim']) ?></p>
            <h2 class="h6 text-secondary">Pembayaran</h2>
            <p class="mb-3"><?= $pesanan['metode_bayar'] === 'cod' ? 'Bayar di tempat (COD)' : 'Transfer bank' ?></p>
            <?php if ($pesanan['catatan'] !== null && $pesanan['catatan'] !== ''): ?>
                <h2 class="h6 text-secondary">Catatan</h2>
                <p class="mb-3"><?= e($pesanan['catatan']) ?></p>
            <?php endif; ?>
            <h2 class="h6 text-secondary">Tanggal pesan</h2>
            <p class="mb-0"><?= e(tanggal_indo($pesanan['tanggal_pesan'])) ?> WIB</p>
        </aside>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
