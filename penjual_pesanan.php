<?php
/** Pesanan masuk untuk toko penjual yang sedang login, beserta tombol ubah status. */
require_once 'includes/fungsi.php';
wajib_login('penjual');
$idPenjual = (int) user()['id'];

// Pesanan aktif tampil lebih dulu, yang selesai/dibatalkan di bawah
$stmt = $conn->prepare(
    "SELECT ps.*, u.nama AS nama_pembeli
     FROM pesanan ps
     JOIN pengguna u ON u.id_pengguna = ps.id_pembeli
     WHERE ps.id_penjual = ?
     ORDER BY FIELD(ps.status, 'menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'),
              ps.tanggal_pesan DESC, ps.id_pesanan DESC"
);
$stmt->bind_param('i', $idPenjual);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ambil semua detail sekaligus, lalu kelompokkan per pesanan
$stmt = $conn->prepare(
    'SELECT dp.id_pesanan, dp.nama_produk, dp.harga, dp.jumlah, dp.subtotal
     FROM detail_pesanan dp
     JOIN pesanan ps ON ps.id_pesanan = dp.id_pesanan
     WHERE ps.id_penjual = ?
     ORDER BY dp.id_detail'
);
$stmt->bind_param('i', $idPenjual);
$stmt->execute();
$detail = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
    $detail[(int) $d['id_pesanan']][] = $d;
}
$stmt->close();

$perluDiproses = 0;
foreach ($pesanan as $ps) {
    if ($ps['status'] === 'menunggu') {
        $perluDiproses++;
    }
}
$transisi = transisi_status();

$judul = 'Pesanan masuk';
$aktif = 'pesanan_masuk';
require 'includes/header.php';
?>

<h1 class="h3 mb-1">Pesanan masuk</h1>
<p class="text-secondary mb-4">
    Pesanan untuk <?= e(user()['nama_toko']) ?>.
    <?php if ($perluDiproses > 0): ?>
        <strong class="text-body"><?= $perluDiproses ?> pesanan menunggu konfirmasi Anda.</strong>
    <?php endif; ?>
</p>

<?php if (!$pesanan): ?>
    <div class="panel keadaan-kosong">
        <i class="bi bi-inbox"></i>
        <h2 class="h5 mt-3">Belum ada pesanan</h2>
        <p class="text-secondary mb-0">Pesanan dari pembeli akan muncul di sini setelah mereka checkout.</p>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
    <?php foreach ($pesanan as $ps): ?>
        <?php $idPs = (int) $ps['id_pesanan']; $aksi = $transisi[$ps['status']]; ?>
        <article class="panel" id="pesanan-<?= $idPs ?>">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-1"><?= e($ps['kode_pesanan']) ?></h2>
                    <span class="small text-secondary"><?= e(tanggal_indo($ps['tanggal_pesan'])) ?> WIB, dari <?= e($ps['nama_pembeli']) ?></span>
                </div>
                <div><?= label_status($ps['status']) ?></div>
            </div>
            <div class="row g-3">
                <div class="col-md-7">
                    <ul class="list-unstyled mb-0">
                    <?php foreach (isset($detail[$idPs]) ? $detail[$idPs] : [] as $it): ?>
                        <li class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span><?= e($it['nama_produk']) ?> <span class="text-secondary">× <?= (int) $it['jumlah'] ?></span></span>
                            <span class="text-nowrap"><?= rupiah($it['subtotal']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    <div class="d-flex justify-content-between pt-2 fw-semibold">
                        <span>Total pesanan</span><span><?= rupiah($ps['total_harga']) ?></span>
                    </div>
                </div>
                <div class="col-md-5 small">
                    <div class="text-secondary">Kirim ke</div>
                    <div class="fw-semibold"><?= e($ps['nama_penerima']) ?> (<?= e($ps['no_hp']) ?>)</div>
                    <div class="deskripsi mb-2"><?= e($ps['alamat_kirim']) ?></div>
                    <div><span class="text-secondary">Pembayaran:</span> <?= $ps['metode_bayar'] === 'cod' ? 'COD' : 'Transfer bank' ?></div>
                    <?php if ($ps['catatan'] !== null && $ps['catatan'] !== ''): ?>
                        <div><span class="text-secondary">Catatan:</span> <?= e($ps['catatan']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($aksi): ?>
                <div class="d-flex flex-wrap gap-2 border-top pt-3 mt-3">
                    <?php foreach ($aksi as $statusTujuan => $teksTombol): ?>
                        <form action="pesanan_status.php" method="post" class="m-0"
                            <?php if ($statusTujuan === 'dibatalkan'): ?>
                              onsubmit="return confirm('Batalkan pesanan <?= e($ps['kode_pesanan']) ?>? Stok produk akan dikembalikan.');"
                            <?php endif; ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_pesanan" value="<?= $idPs ?>">
                            <input type="hidden" name="status" value="<?= e($statusTujuan) ?>">
                            <button type="submit" class="btn btn-sm <?= $statusTujuan === 'dibatalkan' ? 'btn-outline-danger' : 'btn-primary' ?>">
                                <?= e($teksTombol) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="small text-secondary border-top pt-3 mt-3 mb-0">
                    <?= $ps['status'] === 'selesai' ? 'Pesanan selesai. Tidak ada tindakan lagi.' : 'Pesanan dibatalkan dan stok sudah dikembalikan.' ?>
                </p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
