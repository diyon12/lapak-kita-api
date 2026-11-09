<?php
require_once 'includes/fungsi.php';

if (sudah_login()) {
    redirect('index.php');
}

$form = ['nama' => '', 'email' => '', 'role' => 'pembeli', 'nama_toko' => '', 'no_hp' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    foreach ($form as $kunci => $v) {
        $form[$kunci] = trim(isset($_POST[$kunci]) ? (string) $_POST[$kunci] : '');
    }
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $konfirmasi = isset($_POST['konfirmasi']) ? (string) $_POST['konfirmasi'] : '';

    if ($form['nama'] === '' || panjang($form['nama']) > 100) {
        $errors['nama'] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 100) {
        $errors['email'] = 'Masukkan alamat email yang valid.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $errors['konfirmasi'] = 'Konfirmasi password tidak sama.';
    }
    if (!in_array($form['role'], ['pembeli', 'penjual'], true)) {
        $errors['role'] = 'Pilih jenis akun.';
    }
    if ($form['role'] === 'penjual' && ($form['nama_toko'] === '' || panjang($form['nama_toko']) > 100)) {
        $errors['nama_toko'] = 'Nama toko wajib diisi untuk akun penjual.';
    }
    if ($form['no_hp'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $form['no_hp'])) {
        $errors['no_hp'] = 'Nomor HP 8 sampai 20 digit.';
    }

    if (!isset($errors['email'])) {
        $stmt = $conn->prepare('SELECT 1 FROM pengguna WHERE email = ?');
        $stmt->bind_param('s', $form['email']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) {
            $errors['email'] = 'Email sudah terdaftar. Silakan masuk.';
        }
        $stmt->close();
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $namaToko = $form['role'] === 'penjual' ? $form['nama_toko'] : null;
        $noHp = $form['no_hp'] !== '' ? $form['no_hp'] : null;

        $stmt = $conn->prepare('INSERT INTO pengguna (nama, email, password, role, no_hp, nama_toko) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $form['nama'], $form['email'], $hash, $form['role'], $noHp, $namaToko);
        $stmt->execute();
        $idBaru = $conn->insert_id;
        $stmt->close();

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int) $idBaru,
            'nama'      => $form['nama'],
            'email'     => $form['email'],
            'role'      => $form['role'],
            'nama_toko' => $namaToko,
        ];
        set_flash('success', 'Akun berhasil dibuat. Selamat datang, ' . $form['nama'] . '.');
        redirect($form['role'] === 'penjual' ? 'penjual_produk.php' : 'index.php');
    }
}

function salah($errors, $field)
{
    return isset($errors[$field]) ? $errors[$field] : '';
}

$judul = 'Daftar akun';
require 'includes/header.php';
?>

<div class="kartu-auth">
    <div class="panel">
        <h1 class="h3 mb-1">Daftar akun</h1>
        <p class="text-secondary mb-4">Pilih pembeli untuk berbelanja atau penjual untuk membuka lapak.</p>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Jenis akun</legend>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="role" id="rolePembeli" value="pembeli" <?= $form['role'] !== 'penjual' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="rolePembeli"><i class="bi bi-bag me-1"></i>Pembeli</label>
                    <input type="radio" class="btn-check" name="role" id="rolePenjual" value="penjual" <?= $form['role'] === 'penjual' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="rolePenjual"><i class="bi bi-shop me-1"></i>Penjual</label>
                </div>
            </fieldset>
            <div class="mb-3">
                <label for="nama" class="form-label">Nama lengkap</label>
                <input type="text" id="nama" name="nama" maxlength="100" class="form-control<?= salah($errors, 'nama') ? ' is-invalid' : '' ?>" value="<?= e($form['nama']) ?>" required>
                <div class="invalid-feedback"><?= e(salah($errors, 'nama')) ?></div>
            </div>
            <div class="mb-3" id="grupToko">
                <label for="nama_toko" class="form-label">Nama toko</label>
                <input type="text" id="nama_toko" name="nama_toko" maxlength="100" class="form-control<?= salah($errors, 'nama_toko') ? ' is-invalid' : '' ?>" value="<?= e($form['nama_toko']) ?>">
                <div class="invalid-feedback"><?= e(salah($errors, 'nama_toko')) ?></div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" maxlength="100" class="form-control<?= salah($errors, 'email') ? ' is-invalid' : '' ?>" value="<?= e($form['email']) ?>" required autocomplete="email">
                <div class="invalid-feedback"><?= e(salah($errors, 'email')) ?></div>
            </div>
            <div class="mb-3">
                <label for="no_hp" class="form-label">Nomor HP <span class="text-secondary">(opsional)</span></label>
                <input type="tel" id="no_hp" name="no_hp" maxlength="20" class="form-control<?= salah($errors, 'no_hp') ? ' is-invalid' : '' ?>" value="<?= e($form['no_hp']) ?>">
                <div class="invalid-feedback"><?= e(salah($errors, 'no_hp')) ?></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control<?= salah($errors, 'password') ? ' is-invalid' : '' ?>" required autocomplete="new-password">
                    <div class="invalid-feedback"><?= e(salah($errors, 'password')) ?></div>
                </div>
                <div class="col-sm-6">
                    <label for="konfirmasi" class="form-label">Ulangi password</label>
                    <input type="password" id="konfirmasi" name="konfirmasi" class="form-control<?= salah($errors, 'konfirmasi') ? ' is-invalid' : '' ?>" required autocomplete="new-password">
                    <div class="invalid-feedback"><?= e(salah($errors, 'konfirmasi')) ?></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Buat akun</button>
        </form>
        <p class="text-center small mt-3 mb-0">Sudah punya akun? <a href="login.php">Masuk</a></p>
    </div>
</div>

<script>
// Tampilkan isian nama toko hanya untuk akun penjual
(function () {
    var grup = document.getElementById('grupToko');
    function atur() {
        grup.style.display = document.getElementById('rolePenjual').checked ? '' : 'none';
    }
    document.querySelectorAll('input[name="role"]').forEach(function (r) { r.addEventListener('change', atur); });
    atur();
})();
</script>

<?php require 'includes/footer.php'; ?>
