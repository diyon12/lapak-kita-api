<?php
require_once 'includes/fungsi.php';
wajib_login('pembeli');
$idPembeli = (int) user()['id'];

$stmt = $conn->prepare(
    'SELECT ps.id_pesanan, ps.kode_pesanan, ps.total_harga, ps.status, ps.tanggal_pesan, u.nama_toko,
            COALESCE(SUM(dp.jumlah), 0) AS jumlah_barang
     FROM pesanan ps
     JOIN pengguna u ON u.id_pengguna = ps.id_penjual
     LEFT JOIN detail_pesanan dp ON dp.id_pesanan = ps.id_pesanan
     WHERE ps.id_pembeli = ?
     GROUP BY ps.id_pesanan, ps.kode_pesanan, ps.total_harga, ps.status, ps.tanggal_pesan, u.nama_toko
     ORDER BY ps.tanggal_pesan DESC, ps.id_pesanan DESC'
);
$stmt->bind_param('i', $idPembeli);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$judul = 'Pesanan saya';
$aktif = 'pesanan';
require 'includes/header.php';
?>

<h1 class="h3 mb-4">Pesanan saya</h1>

<?php if (!$pesanan): ?>
    <div class="panel keadaan-kosong">
        <i class="bi bi-receipt"></i>
        <h2 class="h5 mt-3">Belum ada pesanan</h2>
        <p class="text-secondary">Pesanan yang Anda buat dari keranjang akan muncul di sini.</p>
        <a href="index.php" class="btn btn-primary">Mulai belanja</a>
    </div>
<?php else: ?>
    <div class="panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Kode pesanan</th>
                        <th scope="col">Toko</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col" class="text-center">Barang</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pesanan as $ps): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= e($ps['kode_pesanan']) ?></td>
                        <td><?= e($ps['nama_toko']) ?></td>
                        <td class="text-nowrap"><?= e(tanggal_indo($ps['tanggal_pesan'])) ?></td>
                        <td class="text-center"><?= (int) $ps['jumlah_barang'] ?></td>
                        <td class="text-end text-nowrap"><?= rupiah($ps['total_harga']) ?></td>
                        <td><?= label_status($ps['status']) ?></td>
                        <td class="text-end"><a href="pesanan_detail.php?id=<?= (int) $ps['id_pesanan'] ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
