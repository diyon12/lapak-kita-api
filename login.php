<?php
require_once 'includes/fungsi.php';

$next = url_aman(isset($_GET['next']) ? $_GET['next'] : (isset($_POST['next']) ? $_POST['next'] : ''), '');
if (sudah_login()) {
    redirect($next !== '' ? $next : 'index.php');
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $email = trim(isset($_POST['email']) ? (string) $_POST['email'] : '');
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    $stmt = $conn->prepare('SELECT id_pengguna, nama, email, password, role, nama_toko FROM pengguna WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($akun && password_verify($password, $akun['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int) $akun['id_pengguna'],
            'nama'      => $akun['nama'],
            'email'     => $akun['email'],
            'role'      => $akun['role'],
            'nama_toko' => $akun['nama_toko'],
        ];
        set_flash('success', 'Selamat datang, ' . $akun['nama'] . '.');
        if ($next !== '') {
            redirect($next);
        }
        redirect($akun['role'] === 'penjual' ? 'penjual_produk.php' : 'index.php');
    }
    $error = 'Email atau password salah.';
}

$judul = 'Masuk';
$aktif = 'login';
require 'includes/header.php';
?>

<div class="kartu-auth">
    <div class="panel">
        <h1 class="h3 mb-1">Masuk</h1>
        <p class="text-secondary mb-4">Gunakan akun pembeli untuk berbelanja atau akun penjual untuk mengelola produk.</p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus autocomplete="email">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Masuk</button>
        </form>
        <p class="text-center small mt-3 mb-0">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
    </div>
    <div class="panel mt-3 small">
        <p class="fw-semibold mb-2">Akun demo untuk pengujian</p>
        <p class="mb-1">Pembeli: <code>pembeli@demo.com</code> / <code>pembeli123</code></p>
        <p class="mb-0">Penjual: <code>penjual@demo.com</code> / <code>penjual123</code></p>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
