<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Memastikan zona waktu sistem menggunakan standar GMT+7 sesuai instruksi
date_default_timezone_set('Asia/Jakarta');

// 1. Mendeteksi apakah ini adalah request dari HTMX
$is_htmx = isset($_SERVER['HTTP_HX_REQUEST']);

// Konfigurasi parameter pencarian dan filter
$limit = 30; 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Menetapkan default tanggal ke hari ini (GMT+7) jika tidak ada input pencarian
$filter_tanggal = isset($_GET['tanggal']) && $_GET['tanggal'] !== '' ? trim($_GET['tanggal']) : date('Y-m-d');
$filter_nama = isset($_GET['nama_pasien']) ? trim($_GET['nama_pasien']) : '';
$filter_status = isset($_GET['status_layanan']) ? trim($_GET['status_layanan']) : '';

// 2. Membangun kueri filter dinamis
$where = ["DATE(rm.tanggal_kunjungan) = ?"];
$params = [$filter_tanggal];

if ($filter_nama !== '') {
    $where[] = "p.nama_lengkap LIKE ?";
    $params[] = "%" . $filter_nama . "%";
}
if ($filter_status !== '') {
    $where[] = "rm.status_layanan = ?";
    $params[] = $filter_status;
}

// Mengambil data rekam medik yang digabung dengan data pasien
$sql = "
    SELECT rm.id AS id_rm, rm.tanggal_kunjungan, rm.status_layanan, 
           p.id AS pasien_id, p.nama_lengkap, p.no_rm, p.jenis_kelamin, p.tanggal_lahir, p.alamat, p.no_telp
    FROM rekam_medik rm
    JOIN pasien p ON rm.pasien_id = p.id
    WHERE " . implode(" AND ", $where) . " 
    ORDER BY rm.tanggal_kunjungan DESC 
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kunjungan_list = $stmt->fetchAll();

// 3. Menyimpan hasil render card ke dalam buffer
ob_start();
$total_rendered = count($kunjungan_list);

foreach ($kunjungan_list as $index => $row) :
    $is_last = ($index === $total_rendered - 1);
    $has_more = ($total_rendered === $limit);
    
    $attrs = '';
    if ($is_last && $has_more) {
        $next_page = $page + 1;
        $attrs = ' hx-get="pelayanan.php?page=' . $next_page . '" hx-trigger="revealed" hx-swap="afterend" hx-include="#filter-form" hx-indicator="#filter-indicator"';
    }

    // Menentukan warna tag dan tombol aksi berdasarkan status layanan dinamis
    $status = $row['status_layanan'];
    $tag_color = 'is-warning';
    if ($status === 'pemeriksaan') $tag_color = 'is-info';
    elseif ($status === 'farmasi') $tag_color = 'is-link';
    elseif ($status === 'pembayaran') $tag_color = 'is-primary';
    elseif ($status === 'selesai') $tag_color = 'is-success';

    $umur = hitung_umur($row['tanggal_lahir'], 'Tahun');
    $jk = $row['jenis_kelamin'] === 'P' ? 'Perempuan' : ($row['jenis_kelamin'] === 'L' ? 'Laki-laki' : '-');
?>
    <div class="column is-12-mobile is-6-tablet is-4-desktop"<?= $attrs ?>>
        <div class="card" style="display: flex; flex-direction: column; height: 100%;">
            <div class="card-content" style="flex-grow: 1; padding: 1.25rem;">
                <div class="media mb-3 is-align-items-center">
                    <div class="media-left">
                        <span class="icon is-large has-text-info" style="background-color: var(--bulma-info-light); border-radius: 8px; height: 3rem; width: 3rem;">
                            <i class="mdi mdi-account-clock mdi-24px"></i>
                        </span>
                    </div>
                    <div class="media-content" style="overflow: hidden;">
                        <p class="title is-6 mb-1 is-clipped">
                            <a href="pasien.php?id=<?= e($row['pasien_id']) ?>"><?= e($row['nama_lengkap']) ?></a>
                        </p>
                        <p class="subtitle is-7 has-text-grey mb-0">
                            No. RM: <strong><?= e($row['no_rm']) ?></strong>
                        </p>
                    </div>
                </div>

                <div class="content is-small mb-0">
                    <div class="tags mb-3">
                        <span class="tag <?= $tag_color ?> is-light font-weight-bold">Status: <?= e(ucfirst($status)) ?></span>
                    </div>

                    <!-- Menggunakan tata letak tabel selaras dengan daftar-pasien.php -->
                    <table class="table is-fullwidth is-narrow is-borderless mb-0">
                        <tbody>
                            <tr>
                                <td width="40%">
                                    <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-calendar-clock"></i></span> Waktu
                                </td>
                                <td>
                                    <?= e(date('H:i', strtotime($row['tanggal_kunjungan']))) ?> WIB
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-calendar-blank-outline"></i></span> Usia
                                </td>
                                <td>
                                    <?= $umur ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-gender-male-female"></i></span> Gender
                                </td>
                                <td>
                                    <?= e($jk) ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-map-marker-outline"></i></span> Alamat
                                </td>
                                <td>
                                    <span class="is-clipped"><?= !empty($row['alamat']) ? e($row['alamat']) : 'Alamat Kosong' ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="card-footer" style="background-color: #fafafa; padding: 0.75rem;">
                <div class="card-footer-item p-0">
                    <?php if ($status === 'menunggu' || $status === 'pemeriksaan'): ?>
                        <!-- Mengubah tautan target dari kunjungan.php menjadi pemeriksaan.php -->
                        <a href="pemeriksaan.php?id_rm=<?= e($row['id_rm']) ?>" class="button is-info is-small is-fullwidth">
                            <span class="icon"><i class="mdi mdi-stethoscope"></i></span>
                            <span>Proses Pemeriksaan</span>
                        </a>
                    <?php elseif ($status === 'farmasi'): ?>
                        <a href="farmasi.php?id_rm=<?= e($row['id_rm']) ?>" class="button is-link is-small is-fullwidth">
                            <span class="icon"><i class="mdi mdi-pill"></i></span>
                            <span>Validasi Farmasi</span>
                        </a>
                    <?php elseif ($status === 'pembayaran'): ?>
                        <a href="konfirmasi-biaya.php?id_rm=<?= e($row['id_rm']) ?>" class="button is-primary is-small is-fullwidth">
                            <span class="icon"><i class="mdi mdi-cash-register"></i></span>
                            <span>Proses Pembayaran</span>
                        </a>
                    <?php else: ?>
                        <a href="konfirmasi-biaya.php?id_rm=<?= e($row['id_rm']) ?>" class="button is-success is-light is-small is-fullwidth">
                            <span class="icon"><i class="mdi mdi-check-circle"></i></span>
                            <span>Lihat Rincian Selesai</span>
                        </a>
                    <?php endif; ?>
                </div>
            </footer>
        </div>
    </div>
<?php
endforeach;

if (empty($kunjungan_list) && $page === 1) :
?>
    <div class="column is-12">
        <div class="box has-text-centered py-6">
            <span class="icon is-large has-text-grey-light mb-3">
                <i class="mdi mdi-clipboard-search-outline mdi-48px"></i>
            </span>
            <p class="is-size-5 has-text-grey">Belum ada data kunjungan pasien pada tanggal yang dipilih.</p>
        </div>
    </div>
<?php
endif;

$grid_html = ob_get_clean();

// 4. Jika request HTMX, kirimkan hasil render buffer grid
if ($is_htmx) {
    echo $grid_html;
    exit; 
}

// 5. Render halaman penuh jika diakses langsung via browser
$page_title = 'Dashboard Pelayanan & Antrean';
include 'includes/header.php';

// Memproses pesan notifikasi dari parameter GET
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$notification_html = '';

if ($msg !== '') {
    $notif_title = '';
    $notif_text = '';
    
    switch ($msg) {
        case 'terdaftar':
            $notif_title = 'Pasien Terdaftar!';
            $notif_text = 'Kunjungan pasien berhasil didaftarkan dan masuk ke antrean.';
            break;
        case 'pemeriksaan_selesai':
            $notif_title = 'Pemeriksaan Selesai!';
            $notif_text = 'Data pemeriksaan medis telah disimpan. Pasien diarahkan ke tahap selanjutnya.';
            break;
        case 'farmasi_selesai':
            $notif_title = 'Farmasi Selesai!';
            $notif_text = 'Obat telah divalidasi dan diserahkan kepada pasien.';
            break;
        case 'pembayaran_selesai':
            $notif_title = 'Transaksi Lunas & Selesai!';
            $notif_text = 'Pembayaran untuk kunjungan ini telah dikonfirmasi. Anda dapat mencetak struk pembayaran di bawah ini.';
            break;
    }

    if ($notif_title !== '') {
        $notification_html = '
        <div class="notification is-success is-light mb-4">
            <button class="delete" onclick="this.parentElement.remove()"></button>
            <span class="icon mr-1"><i class="mdi mdi-check-circle-outline"></i></span>
            <strong>' . htmlspecialchars($notif_title) . '</strong> ' . htmlspecialchars($notif_text) . '
        </div>';
    }
}
?>

<?= $notification_html ?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-4-mobile is-flex is-align-items-center">
            <span class="icon has-text-info mr-2">
                <i class="mdi mdi-view-dashboard-outline"></i>
            </span>
            <span>Dashboard Pelayanan</span>
            <span class="htmx-indicator ml-3" id="filter-indicator">
                <i class="mdi mdi-loading mdi-spin has-text-grey-light"></i>
            </span>
        </h1>
    </div>
    <div class="column is-narrow">
        <a class="button is-info" href="daftar-pasien.php">
            <span class="icon">
                <i class="mdi mdi-account-search"></i>
            </span>
            <span>Cari Pasien / Daftar Kunjungan</span>
        </a>
    </div>
</div>

<div class="mb-5">
    <form id="filter-form" hx-get="pelayanan.php" hx-target="#container-grid" hx-indicator="#filter-indicator" hx-trigger="input changed delay:300ms from:input, change from:select">
        <div class="columns is-multiline is-variable is-2">
            <div class="column is-12-mobile is-4-tablet">
                <div class="control has-icons-left">
                    <input class="input" type="date" name="tanggal" value="<?= e($filter_tanggal) ?>" aria-label="Tanggal Kunjungan">
                    <span class="icon is-left"><i class="mdi mdi-calendar"></i></span>
                </div>
            </div>
            <div class="column is-12-mobile is-4-tablet">
                <div class="control has-icons-left">
                    <input class="input" type="text" name="nama_pasien" value="<?= e($filter_nama) ?>" placeholder="Cari nama pasien...">
                    <span class="icon is-left"><i class="mdi mdi-magnify"></i></span>
                </div>
            </div>
            <div class="column is-12-mobile is-4-tablet">
                <div class="control has-icons-left">
                    <div class="select is-fullwidth">
                        <select name="status_layanan">
                            <option value="">Semua Status Layanan</option>
                            <option value="menunggu" <?= $filter_status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                            <option value="pemeriksaan" <?= $filter_status === 'pemeriksaan' ? 'selected' : '' ?>>Pemeriksaan</option>
                            <option value="farmasi" <?= $filter_status === 'farmasi' ? 'selected' : '' ?>>Farmasi</option>
                            <option value="pembayaran" <?= $filter_status === 'pembayaran' ? 'selected' : '' ?>>Pembayaran</option>
                            <option value="selesai" <?= $filter_status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                    <span class="icon is-left"><i class="mdi mdi-filter-outline"></i></span>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="columns is-multiline" id="container-grid">
    <?= $grid_html ?>
</div>

<?php include 'includes/footer.php'; ?>
