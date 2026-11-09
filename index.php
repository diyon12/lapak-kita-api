<?php
require_once 'includes/fungsi.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$idKategori = isset($_GET['kategori']) ? (int) $_GET['kategori'] : 0;

// Daftar kategori untuk tombol filter
$kategori = $conn->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetch_all(MYSQLI_ASSOC);

// Bangun query produk dengan filter opsional (tetap memakai prepared statement)
$sql = 'SELECT p.id_produk, p.nama_produk, p.harga, p.stok, p.gambar, p.id_kategori,
               k.nama_kategori, u.nama_toko
        FROM produk p
        JOIN kategori k ON k.id_kategori = p.id_kategori
        JOIN pengguna u ON u.id_pengguna = p.id_penjual';
$syarat = [];
$tipe = '';
$nilai = [];

if ($q !== '') {
    $syarat[] = '(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
    $kata = '%' . $q . '%';
    $tipe .= 'ss';
    $nilai[] = $kata;
    $nilai[] = $kata;
}
if ($idKategori > 0) {
    $syarat[] = 'p.id_kategori = ?';
    $tipe .= 'i';
    $nilai[] = $idKategori;
}
if ($syarat) {
    $sql .= ' WHERE ' . implode(' AND ', $syarat);
}
// Produk yang masih ada stok tampil lebih dulu
$sql .= ' ORDER BY (p.stok = 0), p.created_at DESC, p.id_produk DESC';

$stmt = $conn->prepare($sql);
if ($tipe !== '') {
    $stmt->bind_param($tipe, ...$nilai);
}
$stmt->execute();
$produk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$namaKategoriAktif = '';
foreach ($kategori as $k) {
    if ((int) $k['id_kategori'] === $idKategori) {
        $namaKategoriAktif = $k['nama_kategori'];
    }
}

$judul = 'Daftar produk';
$aktif = 'beranda';
require 'includes/header.php';
?>

<section class="kepala-beranda mb-4">
    <?php if ($q !== ''): ?>
        <h1 class="mb-2">Hasil pencarian “<?= e($q) ?>”</h1>
        <p class="text-secondary mb-0"><?= count($produk) ?> produk ditemukan. <a href="index.php">Lihat semua produk</a></p>
    <?php else: ?>
        <h1 class="mb-2">Belanja langsung dari lapak penjual</h1>
        <p class="text-secondary mb-0">Pilih produk, masukkan ke keranjang, dan pesan dalam beberapa langkah.</p>
    <?php endif; ?>
</section>

<nav class="filter-kategori mb-4" aria-label="Filter kategori">
    <a href="index.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>"
       class="btn btn-sm <?= $idKategori === 0 ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">Semua</a>
    <?php foreach ($kategori as $k): ?>
        <?php
        $param = ['kategori' => $k['id_kategori']];
        if ($q !== '') {
            $param['q'] = $q;
        }
        $aktifKat = (int) $k['id_kategori'] === $idKategori;
        ?>
        <a href="index.php?<?= e(http_build_query($param)) ?>"
           class="btn btn-sm <?= $aktifKat ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>"
           <?= $aktifKat ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= ikon_kategori($k['id_kategori']) ?> me-1"></i><?= e($k['nama_kategori']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$produk): ?>
    <div class="panel keadaan-kosong">
        <i class="bi bi-search"></i>
        <h2 class="h5 mt-3">Produk tidak ditemukan</h2>
        <p class="text-secondary">
            <?= $namaKategoriAktif !== '' ? 'Belum ada produk di kategori ' . e($namaKategoriAktif) . '.' : 'Coba kata kunci lain atau hapus filter.' ?>
        </p>
        <a href="index.php" class="btn btn-primary">Lihat semua produk</a>
    </div>
<?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-lg-4">
        <?php foreach ($produk as $p): ?>
            <div class="col">
                <article class="kartu-produk">
                    <?= gambar_produk($p['gambar'], $p['id_kategori'], $p['nama_produk']) ?>
                    <?php if ((int) $p['stok'] === 0): ?>
                        <span class="pita-habis">Stok habis</span>
                    <?php endif; ?>
                    <div class="isi">
                        <span class="toko"><i class="bi bi-shop me-1"></i><?= e($p['nama_toko']) ?></span>
                        <a class="nama" href="produk.php?id=<?= (int) $p['id_produk'] ?>"><?= e($p['nama_produk']) ?></a>
                        <div class="mt-auto pt-2 d-flex flex-wrap align-items-center justify-content-between gap-1">
                            <span class="label-harga"><?= rupiah($p['harga']) ?></span>
                            <span class="tanda-stok<?= (int) $p['stok'] === 0 ? ' habis' : '' ?>">
                                <?= (int) $p['stok'] === 0 ? 'Habis' : 'Stok ' . (int) $p['stok'] ?>
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
