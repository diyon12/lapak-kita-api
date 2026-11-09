<?php
require_once 'includes/fungsi.php';
require_once 'includes/pesanan_fungsi.php';
wajib_login('pembeli');
$idPembeli = (int) user()['id'];

/** Ambil isi keranjang + data produk terbaru. */
function ambil_keranjang(mysqli $conn, $idPembeli, $kunci = false)
{
    $sql = 'SELECT k.id_produk, k.jumlah, p.nama_produk, p.harga, p.stok, p.id_penjual, u.nama_toko
            FROM keranjang k
            JOIN produk p ON p.id_produk = k.id_produk
            JOIN pengguna u ON u.id_pengguna = p.id_penjual
            WHERE k.id_pembeli = ?
            ORDER BY p.id_penjual, k.id_keranjang';
    if ($kunci) {
        // Kunci baris produk selama transaksi agar stok tidak berubah oleh pembeli lain
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $idPembeli);
    $stmt->execute();
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $hasil;
}

// Data awal formulir diambil dari profil pembeli
$stmt = $conn->prepare('SELECT nama, no_hp, alamat FROM pengguna WHERE id_pengguna = ?');
$stmt->bind_param('i', $idPembeli);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc();
$stmt->close();

$form = [
    'nama_penerima' => $profil['nama'],
    'no_hp'         => (string) $profil['no_hp'],
    'alamat_kirim'  => (string) $profil['alamat'],
    'catatan'       => '',
    'metode_bayar'  => 'transfer',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    foreach ($form as $kunci => $nilaiAwal) {
        $form[$kunci] = isset($_POST[$kunci]) ? trim((string) $_POST[$kunci]) : '';
    }
    $errors = validasi_pengiriman($form);

    if (!$errors) {
        // Simpan pesanan dalam satu transaksi database
        $conn->begin_transaction();
        try {
            // Kunci isi keranjang, lalu buat pesanan (dipecah per toko) dari isinya
            $items = ambil_keranjang($conn, $idPembeli, true);
            if (!$items) {
                throw new CheckoutException('Keranjang Anda kosong.');
            }
            $pesananBaru = buat_pesanan($conn, $idPembeli, $items, $form);

            // Kosongkan keranjang
            $stmt = $conn->prepare('DELETE FROM keranjang WHERE id_pembeli = ?');
            $stmt->bind_param('i', $idPembeli);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            if (count($pesananBaru) === 1) {
                redirect('pesanan_detail.php?id=' . key($pesananBaru) . '&baru=1');
            }
            set_flash('success', 'Berhasil membuat ' . count($pesananBaru) . ' pesanan karena produk berasal dari '
                . count($pesananBaru) . ' toko: ' . implode(', ', $pesananBaru) . '. Setiap toko memproses pesanannya sendiri.');
            redirect('pesanan.php');
        } catch (CheckoutException $e) {
            $conn->rollback();
            set_flash('warning', $e->getMessage());
            redirect('keranjang.php');
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('Checkout gagal: ' . $e->getMessage());
            $errors['umum'] = 'Pesanan gagal disimpan karena gangguan database. Silakan coba lagi.';
        }
    }
}

// Tampilan ringkasan (tanpa penguncian)
$items = ambil_keranjang($conn, $idPembeli);
if (!$items) {
    set_flash('info', 'Keranjang Anda kosong. Tambahkan produk terlebih dahulu.');
    redirect('keranjang.php');
}
$total = 0;
foreach ($items as $it) {
    $total += (int) $it['harga'] * (int) $it['jumlah'];
}

function kelas_error($errors, $field)
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

$judul = 'Checkout';
$aktif = 'keranjang';
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="keranjang.php">Keranjang</a></li>
        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
    </ol>
</nav>
<h1 class="h3 mb-4">Checkout</h1>

<?php if (isset($errors['umum'])): ?>
    <div class="alert alert-danger"><?= e($errors['umum']) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="panel">
                <h2 class="h5 mb-3">Alamat pengiriman</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nama_penerima" class="form-label">Nama penerima</label>
                        <input type="text" id="nama_penerima" name="nama_penerima" maxlength="100" required
                               class="form-control<?= kelas_error($errors, 'nama_penerima') ?>" value="<?= e($form['nama_penerima']) ?>">
                        <div class="invalid-feedback"><?= e(isset($errors['nama_penerima']) ? $errors['nama_penerima'] : '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="no_hp" class="form-label">Nomor HP</label>
                        <input type="tel" id="no_hp" name="no_hp" maxlength="20" required
                               class="form-control<?= kelas_error($errors, 'no_hp') ?>" value="<?= e($form['no_hp']) ?>">
                        <div class="invalid-feedback"><?= e(isset($errors['no_hp']) ? $errors['no_hp'] : '') ?></div>
                    </div>
                    <div class="col-12">
                        <label for="alamat_kirim" class="form-label">Alamat lengkap</label>
                        <textarea id="alamat_kirim" name="alamat_kirim" rows="3" required
                                  class="form-control<?= kelas_error($errors, 'alamat_kirim') ?>"
                                  placeholder="Nama jalan, nomor rumah, kelurahan, kecamatan, kota, kode pos"><?= e($form['alamat_kirim']) ?></textarea>
                        <div class="invalid-feedback"><?= e(isset($errors['alamat_kirim']) ? $errors['alamat_kirim'] : '') ?></div>
                    </div>
                    <div class="col-12">
                        <label for="catatan" class="form-label">Catatan untuk penjual <span class="text-secondary">(opsional)</span></label>
                        <input type="text" id="catatan" name="catatan" maxlength="255"
                               class="form-control<?= kelas_error($errors, 'catatan') ?>" value="<?= e($form['catatan']) ?>"
                               placeholder="Contoh: warna hitam, ukuran L">
                        <div class="invalid-feedback"><?= e(isset($errors['catatan']) ? $errors['catatan'] : '') ?></div>
                    </div>
                </div>

                <h2 class="h5 mt-4 mb-3">Metode pembayaran</h2>
                <div class="d-flex flex-column gap-2">
                    <label class="form-check border rounded-3 p-3 ps-5 mb-0">
                        <input class="form-check-input" type="radio" name="metode_bayar" value="transfer" <?= $form['metode_bayar'] === 'transfer' ? 'checked' : '' ?>>
                        <span class="fw-semibold d-block">Transfer bank</span>
                        <span class="small text-secondary">Penjual mengirimkan nomor rekening setelah pesanan dikonfirmasi.</span>
                    </label>
                    <label class="form-check border rounded-3 p-3 ps-5 mb-0">
                        <input class="form-check-input" type="radio" name="metode_bayar" value="cod" <?= $form['metode_bayar'] === 'cod' ? 'checked' : '' ?>>
                        <span class="fw-semibold d-block">Bayar di tempat (COD)</span>
                        <span class="small text-secondary">Bayar tunai kepada kurir saat barang diterima.</span>
                    </label>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <aside class="panel ringkasan">
                <h2 class="h5 mb-3">Pesanan Anda</h2>
                <?php
                $grup = [];
                foreach ($items as $it) {
                    $grup[(int) $it['id_penjual']]['toko'] = $it['nama_toko'];
                    $grup[(int) $it['id_penjual']]['items'][] = $it;
                }
                ?>
                <?php if (count($grup) > 1): ?>
                    <p class="small text-secondary">Produk berasal dari <?= count($grup) ?> toko, sehingga akan dibuat <?= count($grup) ?> pesanan terpisah.</p>
                <?php endif; ?>
                <?php foreach ($grup as $g): ?>
                    <div class="small fw-semibold text-secondary mt-3"><i class="bi bi-shop me-1"></i><?= e($g['toko']) ?></div>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($g['items'] as $it): ?>
                            <li class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                <span><?= e($it['nama_produk']) ?> <span class="text-secondary">× <?= (int) $it['jumlah'] ?></span></span>
                                <span class="text-nowrap"><?= rupiah((int) $it['harga'] * (int) $it['jumlah']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
                <div class="d-flex justify-content-between align-items-center pt-3 mb-4">
                    <span class="fw-semibold">Total bayar</span>
                    <span class="label-harga"><?= rupiah($total) ?></span>
                </div>
                <button type="submit" class="btn btn-kunyit w-100 py-2">Buat pesanan</button>
                <p class="small text-secondary mt-2 mb-0">Stok diperiksa ulang saat pesanan dibuat.</p>
            </aside>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
