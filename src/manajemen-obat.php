<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Mendeteksi apakah permintaan datang dari HTMX untuk Live Search
$is_htmx = isset($_SERVER['HTTP_HX_REQUEST']);

// 1. Parameter Filter & Sorting
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'nama_asc';

// 2. Kueri Panel Metrik (KPIs)
$stmt_kpi1 = $pdo->query("SELECT COUNT(*) FROM obat");
$total_master = $stmt_kpi1->fetchColumn() ?: 0;

$stmt_kpi2 = $pdo->query("SELECT COUNT(*) FROM obat WHERE stok <= 10");
$total_kritis = $stmt_kpi2->fetchColumn() ?: 0;

// Menghitung obat yang memiliki batch kedaluwarsa dalam 90 hari ke depan atau sudah lewat
$stmt_kpi3 = $pdo->query("SELECT COUNT(DISTINCT obat_id) FROM obat_batch WHERE stok_batch > 0 AND tanggal_kedaluwarsa <= date('now', '+90 days', 'localtime')");
$total_ed_kritis = $stmt_kpi3->fetchColumn() ?: 0;

$stmt_kpi4 = $pdo->query("SELECT SUM(ob.stok_batch * ob.harga_beli) FROM obat_batch ob WHERE ob.stok_batch > 0");
$total_aset = $stmt_kpi4->fetchColumn() ?: 0;


// 3. Membangun Kueri Dinamis Berdasarkan Filter & Sorting
$data_sql = "
    SELECT o.*, 
           (SELECT MIN(ob.tanggal_kedaluwarsa) FROM obat_batch ob WHERE ob.obat_id = o.id AND ob.stok_batch > 0) as ed_terdekat 
    FROM obat o
";
$params = [];
$where_clauses = [];

if ($search !== '') {
    $where_clauses[] = "(o.nama_obat LIKE ? OR o.kode_obat LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($where_clauses)) {
    $data_sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Logika Sorting
switch ($sort) {
    case 'stok_asc':
        $data_sql .= " ORDER BY o.stok ASC, o.nama_obat ASC";
        break;
    case 'stok_desc':
        $data_sql .= " ORDER BY o.stok DESC, o.nama_obat ASC";
        break;
    case 'ed_terdekat':
        // Obat yang tidak punya stok/batch aktif akan ditaruh di bawah
        $data_sql .= " ORDER BY CASE WHEN ed_terdekat IS NULL THEN 1 ELSE 0 END ASC, ed_terdekat ASC, o.nama_obat ASC";
        break;
    case 'terbaru':
        $data_sql .= " ORDER BY o.id DESC";
        break;
    case 'nama_desc':
        $data_sql .= " ORDER BY o.nama_obat DESC";
        break;
    case 'nama_asc':
    default:
        $data_sql .= " ORDER BY o.nama_obat ASC";
        break;
}

// Eksekusi kueri
$stmt = $pdo->prepare($data_sql);
$stmt->execute($params);
$obat_list = $stmt->fetchAll();


// 4. Menyimpan hasil render grid ke dalam buffer
ob_start();
if (empty($obat_list)) {
    ?>
    <div class="column is-12">
        <div class="box has-text-centered py-6">
            <span class="icon is-large has-text-grey-light mb-3">
                <i class="mdi mdi-pill mdi-48px"></i>
            </span>
            <p class="is-size-5 has-text-grey">
                <?= $search !== '' ? 'Pencarian tidak menemukan hasil.' : 'Belum ada data obat di sistem.' ?>
            </p>
        </div>
    </div>
    <?php
} else {
    foreach ($obat_list as $obat) {
        $is_low_stock = $obat['stok'] <= 10;
        $stock_class = $is_low_stock ? 'has-text-danger has-text-weight-bold' : 'has-text-weight-medium';
        $stock_icon = $is_low_stock ? '<span class="icon is-small has-text-danger mr-1" title="Stok Menipis"><i class="mdi mdi-alert-circle"></i></span>' : '<span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-package-variant-closed"></i></span>';
        ?>
        <div class="column is-12-mobile is-6-tablet is-4-desktop is-3-widescreen">
            <div class="card" style="display: flex; flex-direction: column; height: 100%;">
                <div class="card-content" style="flex-grow: 1; padding: 1.25rem;">
                    
                    <div class="media mb-3 is-align-items-center">
                        <div class="media-left">
                            <span class="icon is-large has-text-primary" style="background-color: var(--bulma-primary-light); border-radius: 8px; height: 3rem; width: 3rem;">
                                <i class="mdi mdi-pill mdi-24px"></i>
                            </span>
                        </div>
                        <div class="media-content" style="overflow: hidden;">
                            <p class="title is-6 mb-1 is-clipped" title="<?= e($obat['nama_obat']) ?>">
                                <?= e($obat['nama_obat']) ?>
                            </p>
                            <p class="subtitle is-7 has-text-grey mb-0">
                                <span class="tag is-light is-info has-text-weight-bold"><?= e($obat['kode_obat']) ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="content is-small mb-0">
                        <table class="table is-fullwidth is-narrow is-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td width="40%">
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-scale-balance"></i></span> Satuan
                                    </td>
                                    <td>
                                        <?= e($obat['satuan']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-cash-multiple"></i></span> Harga Jual
                                    </td>
                                    <td>
                                        Rp <?= number_format($obat['harga_satuan'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?= $stock_icon ?> Stok
                                    </td>
                                    <td class="<?= $stock_class ?>">
                                        <?= e($obat['stok']) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
                <footer class="card-footer" style="background-color: #fafafa; padding: 0.5rem; display: flex; justify-content: space-around;">
                    <!-- Tombol Restock -->
                    <button 
                        hx-get="form-stok-modal.php?id=<?= e($obat['id']) ?>&ref=manajemen-obat" 
                        hx-target="#modal-container"
                        class="button is-success is-small is-light"
                        title="Restock (Tambah Stok)">
                        <span class="icon"><i class="mdi mdi-package-down"></i></span>
                    </button>
                    <!-- Tombol Stock Opname -->
                    <button 
                        hx-get="penyesuaian-stok.php?id=<?= e($obat['id']) ?>&ref=manajemen-obat" 
                        hx-target="#modal-container"
                        class="button is-warning is-small is-light"
                        title="Penyesuaian Stok (Opname)">
                        <span class="icon"><i class="mdi mdi-scale-balance"></i></span>
                    </button>
                    <!-- Tombol Edit Profil Obat -->
                    <a href="form-obat.php?id=<?= e($obat['id']) ?>&ref=manajemen-obat" class="button is-info is-small is-light" title="Edit Profil Obat">
                        <span class="icon"><i class="mdi mdi-square-edit-outline"></i></span>
                    </a>
                    <!-- Tombol Detail / Kartu Stok (Opsi A) -->
                    <a href="obat.php?id=<?= e($obat['id']) ?>" class="button is-primary is-small is-light" title="Lihat Kartu Stok & Batch">
                        <span class="icon"><i class="mdi mdi-eye"></i></span>
                    </a>
                </footer>
            </div>
        </div>
        <?php
    }
}
$grid_html = ob_get_clean();

// Jika request berasal dari HTMX, kirimkan hanya grid obat
if ($is_htmx) {
    echo $grid_html;
    exit;
}

// Memuat header halaman untuk request normal browser
$page_title = 'Manajemen Obat & Inventaris - Klinik';
include 'includes/header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="columns is-vcentered mb-4 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-5-mobile">
            <span class="icon has-text-primary">
                <i class="mdi mdi-pill"></i>
            </span>
            <span>Manajemen Obat</span>
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey">
            Pusat kendali inventaris, batch, dan kartu stok farmasi
        </h2>
    </div>
    <div class="column is-narrow">
        <div class="is-hidden-touch">
        <a class="button is-primary" href="form-obat.php">
            <span class="icon-text">
                <span class="icon">
                    <i class="mdi mdi-plus-box"></i>
                </span>
                <span>Tambah Obat Baru</span>
            </span>
        </a>
        </div>
        <div class="is-hidden-desktop">
        <a class="button is-size-7-mobile is-primary" href="form-obat.php">
            <span class="icon-text">
                <span class="icon is-responsive-icon">
                    <i class="mdi mdi-plus-box"></i>
                </span>
            </span>
        </a>
        </div>
    </div>
</div>

<!-- Panel Metrik Utama (KPIs) -->
<div class="columns is-multiline is-mobile mb-4">
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-primary-light border-primary-top h-100">
            <p class="heading has-text-primary has-text-weight-bold">Total Master Obat</p>
            <p class="title is-4 has-text-primary-dark"><?= number_format($total_master) ?> <span class="is-size-6 has-text-weight-normal">Item</span></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box <?= $total_kritis > 0 ? 'has-background-danger-light border-danger-top' : 'has-background-success-light' ?> h-100">
            <p class="heading <?= $total_kritis > 0 ? 'has-text-danger-dark' : 'has-text-success-dark' ?> has-text-weight-bold">Stok Kritis (<= 10)</p>
            <p class="title is-4 <?= $total_kritis > 0 ? 'has-text-danger-dark' : 'has-text-success-dark' ?>"><?= number_format($total_kritis) ?> <span class="is-size-6 has-text-weight-normal">Obat</span></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box <?= $total_ed_kritis > 0 ? 'has-background-warning-light' : 'has-background-white' ?> h-100">
            <p class="heading has-text-warning-dark has-text-weight-bold">ED Kritis / Lewat (< 90 Hari)</p>
            <p class="title is-4 has-text-warning-dark"><?= number_format($total_ed_kritis) ?> <span class="is-size-6 has-text-weight-normal">Obat</span></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-info-light border-info-top h-100">
            <p class="heading has-text-info has-text-weight-bold">Total Estimasi Aset</p>
            <p class="title is-5 has-text-info-dark">Rp <?= number_format($total_aset, 0, ',', '.') ?></p>
        </div>
    </div>
</div>

<!-- Form Filter & Live Sort Terpadu dengan HTMX -->
<div class="box box-desktop mb-5">
    <form method="GET" action="manajemen-obat.php" id="form-filter-obat" class="columns is-multiline is-variable is-2 mb-0" hx-get="manajemen-obat.php" hx-target="#obat-grid" hx-trigger="submit, change from:select, keyup changed delay:500ms from:input[name='search']">
        <div class="column is-8-desktop is-12-mobile">
            <div class="field mb-0">
                <div class="control has-icons-left is-size-7-mobile">
                    <input
                        class="input is-size-7-mobile"
                        type="search"
                        name="search"
                        placeholder="Cari berdasarkan nama obat atau scan barcode kode obat..."
                        value="<?= e($search) ?>"
                        autocomplete="off">
                    <span class="icon is-left is-size-7-mobile">
                        <i class="mdi mdi-magnify"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="column is-4-desktop is-12-mobile">
            <div class="field mb-0">
                <div class="control has-icons-left is-size-7-mobile">
                    <div class="select is-fullwidth">
                        <select name="sort">
                            <option value="nama_asc" <?= $sort === 'nama_asc' ? 'selected' : '' ?>>Urutkan: Nama (A - Z)</option>
                            <option value="nama_desc" <?= $sort === 'nama_desc' ? 'selected' : '' ?>>Urutkan: Nama (Z - A)</option>
                            <option value="stok_asc" <?= $sort === 'stok_asc' ? 'selected' : '' ?>>Urutkan: Stok Terendah</option>
                            <option value="stok_desc" <?= $sort === 'stok_desc' ? 'selected' : '' ?>>Urutkan: Stok Tertinggi</option>
                            <option value="ed_terdekat" <?= $sort === 'ed_terdekat' ? 'selected' : '' ?>>Urutkan: ED Terdekat</option>
                            <option value="terbaru" <?= $sort === 'terbaru' ? 'selected' : '' ?>>Urutkan: Obat Terbaru</option>
                        </select>
                    </div>
                    <span class="icon is-left"><i class="mdi mdi-sort"></i></span>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Grid Daftar Obat -->
<div class="columns is-multiline" id="obat-grid">
    <?= $grid_html ?>
</div>

<!-- Penampung DOM (Container) untuk Modal HTMX -->
<div id="modal-container"></div>

<?php include 'includes/footer.php'; ?>
