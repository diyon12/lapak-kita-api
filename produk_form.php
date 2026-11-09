<?php
/**
 * CREATE dan UPDATE produk.
 *   produk_form.php        -> tambah produk baru
 *   produk_form.php?id=5   -> ubah produk dengan id 5 (harus milik penjual yang login)
 */
require_once 'includes/fungsi.php';
wajib_login('penjual');
$idPenjual = (int) user()['id'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$modeUbah = $id > 0;

$form = [
    'nama_produk' => '',
    'id_kategori' => '',
    'harga'       => '',
    'stok'        => '',
    'deskripsi'   => '',
    'gambar'      => null,
];

if ($modeUbah) {
    $stmt = $conn->prepare('SELECT * FROM produk WHERE id_produk = ? AND id_penjual = ?');
    $stmt->bind_param('ii', $id, $idPenjual);
    $stmt->execute();
    $lama = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lama) {
        set_flash('danger', 'Produk tidak ditemukan atau bukan milik Anda.');
        redirect('penjual_produk.php');
    }
    foreach ($form as $kunci => $v) {
        $form[$kunci] = $lama[$kunci];
    }
}

$kategori = $conn->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetch_all(MYSQLI_ASSOC);
$idKategoriValid = array_map('intval', array_column($kategori, 'id_kategori'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $form['nama_produk'] = trim(isset($_POST['nama_produk']) ? (string) $_POST['nama_produk'] : '');
    $form['id_kategori'] = isset($_POST['id_kategori']) ? (int) $_POST['id_kategori'] : 0;
    $form['harga'] = trim(isset($_POST['harga']) ? (string) $_POST['harga'] : '');
    $form['stok'] = trim(isset($_POST['stok']) ? (string) $_POST['stok'] : '');
    $form['deskripsi'] = trim(isset($_POST['deskripsi']) ? (string) $_POST['deskripsi'] : '');

    // ---------- Validasi ----------
    if ($form['nama_produk'] === '' || panjang($form['nama_produk']) > 150) {
        $errors['nama_produk'] = 'Nama produk wajib diisi (maksimal 150 karakter).';
    }
    if (!in_array($form['id_kategori'], $idKategoriValid, true)) {
        $errors['id_kategori'] = 'Pilih kategori produk.';
    }
    if (!ctype_digit($form['harga']) || (int) $form['harga'] < 100 || (int) $form['harga'] > 1000000000) {
        $errors['harga'] = 'Harga berupa angka tanpa titik, minimal 100.';
    }
    if (!ctype_digit($form['stok']) || (int) $form['stok'] > 100000) {
        $errors['stok'] = 'Stok berupa angka 0 sampai 100.000.';
    }
    if (panjang($form['deskripsi']) > 5000) {
        $errors['deskripsi'] = 'Deskripsi maksimal 5.000 karakter.';
    }

    // ---------- Validasi gambar (opsional) ----------
    $gambarBaru = null;
    $adaUnggahan = isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($adaUnggahan) {
        $f = $_FILES['gambar'];
        $ekstensiDiizinkan = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors['gambar'] = 'Gambar gagal diunggah. Pastikan ukurannya di bawah 2 MB.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $errors['gambar'] = 'Ukuran gambar maksimal 2 MB.';
        } else {
            $info = @getimagesize($f['tmp_name']);
            if ($info === false || !isset($ekstensiDiizinkan[$info[2]])) {
                $errors['gambar'] = 'Format gambar harus JPG, PNG, atau WEBP.';
            } else {
                $gambarBaru = 'produk_' . bin2hex(random_bytes(8)) . '.' . $ekstensiDiizinkan[$info[2]];
            }
        }
    }

    if (!$errors) {
        if ($gambarBaru !== null && !move_uploaded_file($_FILES['gambar']['tmp_name'], __DIR__ . '/uploads/' . $gambarBaru)) {
            $errors['gambar'] = 'Gambar tidak dapat disimpan ke folder uploads.';
        }
    }

    if (!$errors) {
        $harga = (int) $form['harga'];
        $stok = (int) $form['stok'];
        $idKat = (int) $form['id_kategori'];
        $gambarSimpan = $gambarBaru !== null ? $gambarBaru : $form['gambar'];

        if ($modeUbah) {
            $stmt = $conn->prepare(
                'UPDATE produk SET id_kategori = ?, nama_produk = ?, deskripsi = ?, harga = ?, stok = ?, gambar = ?
                 WHERE id_produk = ? AND id_penjual = ?'
            );
            $stmt->bind_param('issiisii', $idKat, $form['nama_produk'], $form['deskripsi'], $harga, $stok, $gambarSimpan, $id, $idPenjual);
            $stmt->execute();
            $stmt->close();

            // Hapus file gambar lama jika diganti
            if ($gambarBaru !== null && !empty($form['gambar']) && is_file(__DIR__ . '/uploads/' . basename($form['gambar']))) {
                unlink(__DIR__ . '/uploads/' . basename($form['gambar']));
            }
            set_flash('success', $form['nama_produk'] . ' berhasil diperbarui.');
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok, gambar)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iissiis', $idPenjual, $idKat, $form['nama_produk'], $form['deskripsi'], $harga, $stok, $gambarSimpan);
            $stmt->execute();
            $stmt->close();
            set_flash('success', $form['nama_produk'] . ' berhasil ditambahkan.');
        }
        redirect('penjual_produk.php');
    }
}

function err($errors, $field)
{
    return isset($errors[$field]) ? $errors[$field] : '';
}

$judul = $modeUbah ? 'Ubah produk' : 'Tambah produk';
$aktif = 'produk_saya';
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="penjual_produk.php">Produk saya</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $modeUbah ? 'Ubah produk' : 'Tambah produk' ?></li>
    </ol>
</nav>
<h1 class="h3 mb-4"><?= $modeUbah ? 'Ubah produk' : 'Tambah produk' ?></h1>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="panel">
                <div class="mb-3">
                    <label for="nama_produk" class="form-label">Nama produk</label>
                    <input type="text" id="nama_produk" name="nama_produk" maxlength="150" required
                           class="form-control<?= err($errors, 'nama_produk') ? ' is-invalid' : '' ?>" value="<?= e($form['nama_produk']) ?>">
                    <div class="invalid-feedback"><?= e(err($errors, 'nama_produk')) ?></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="id_kategori" class="form-label">Kategori</label>
                        <select id="id_kategori" name="id_kategori" required class="form-select<?= err($errors, 'id_kategori') ? ' is-invalid' : '' ?>">
                            <option value="">Pilih kategori</option>
                            <?php foreach ($kategori as $k): ?>
                                <option value="<?= (int) $k['id_kategori'] ?>" <?= (int) $form['id_kategori'] === (int) $k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e(err($errors, 'id_kategori')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label for="harga" class="form-label">Harga (Rp)</label>
                        <input type="number" id="harga" name="harga" min="100" step="1" required inputmode="numeric"
                               class="form-control<?= err($errors, 'harga') ? ' is-invalid' : '' ?>" value="<?= e($form['harga']) ?>" placeholder="150000">
                        <div class="invalid-feedback"><?= e(err($errors, 'harga')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label for="stok" class="form-label">Stok</label>
                        <input type="number" id="stok" name="stok" min="0" step="1" required inputmode="numeric"
                               class="form-control<?= err($errors, 'stok') ? ' is-invalid' : '' ?>" value="<?= e($form['stok']) ?>">
                        <div class="invalid-feedback"><?= e(err($errors, 'stok')) ?></div>
                    </div>
                </div>
                <div>
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" rows="6"
                              class="form-control<?= err($errors, 'deskripsi') ? ' is-invalid' : '' ?>"
                              placeholder="Bahan, ukuran, kondisi, dan informasi lain yang membantu pembeli"><?= e($form['deskripsi']) ?></textarea>
                    <div class="invalid-feedback"><?= e(err($errors, 'deskripsi')) ?></div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <aside class="panel">
                <label for="gambar" class="form-label">Gambar produk</label>
                <div class="foto-detail mb-3">
                    <?= gambar_produk($form['gambar'], $form['id_kategori'] !== '' ? $form['id_kategori'] : 0, 'Pratinjau gambar produk') ?>
                </div>
                <input type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp"
                       class="form-control<?= err($errors, 'gambar') ? ' is-invalid' : '' ?>">
                <div class="invalid-feedback"><?= e(err($errors, 'gambar')) ?></div>
                <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB.<?= $modeUbah ? ' Kosongkan jika tidak ingin mengganti gambar.' : ' Boleh dikosongkan.' ?></div>
                <hr class="my-4">
                <button type="submit" class="btn btn-primary w-100"><?= $modeUbah ? 'Simpan perubahan' : 'Simpan produk' ?></button>
                <a href="penjual_produk.php" class="btn btn-link w-100 mt-2">Batal</a>
            </aside>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
