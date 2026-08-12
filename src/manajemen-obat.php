<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Mendeteksi apakah permintaan datang dari HTMX untuk Live Search
$is_htmx = isset($_SERVER['HTTP_HX_REQUEST']);

// Konfigurasi parameter pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search . '%';

// Membangun kueri dinamis untuk entitas obat berdasarkan skema tabel[cite: 1]
$data_sql = "SELECT * FROM obat";
$params = [];

if ($search !== '') {
    $where_clause = " WHERE nama_obat LIKE ? OR kode_obat LIKE ?";
    $data_sql .= $where_clause;
    $params[] = $search_param;
    $params[] = $search_param;
}

$data_sql .= " ORDER BY nama_obat ASC";

// Eksekusi kueri
$stmt = $pdo->prepare($data_sql);
$stmt->execute($params);
$obat_list = $stmt->fetchAll();

// Jika ini adalah request HTMX, kita hanya memproses dan merender isi tabel (tbody)
if ($is_htmx) {
    if (empty($obat_list)) {
        echo '<tr><td colspan="7" class="has-text-centered py-5 has-text-grey">Pencarian tidak menemukan hasil.</td></tr>';
        exit;
    }
    
    foreach ($obat_list as $index => $obat) {
        $is_low_stock = $obat['stok'] <= 10;
        $stock_class = $is_low_stock ? 'has-text-danger has-text-weight-bold' : 'has-text-weight-medium';
        $stock_icon = $is_low_stock ? '<span class="icon is-small ml-1 has-text-danger" title="Stok Menipis"><i class="mdi mdi-alert-circle"></i></span>' : '';
        
        echo '<tr>';
        echo '<td class="is-vcentered">' . ($index + 1) . '</td>';
        echo '<td class="is-vcentered"><span class="tag is-light is-info has-text-weight-bold">' . e($obat['kode_obat']) . '</span></td>';
        echo '<td class="is-vcentered has-text-weight-bold">' . e($obat['nama_obat']) . '</td>';
        echo '<td class="is-vcentered is-hidden-mobile">' . e($obat['satuan']) . '</td>';
        echo '<td class="is-vcentered">Rp ' . number_format($obat['harga_satuan'], 0, ',', '.') . '</td>';
        echo '<td class="is-vcentered ' . $stock_class . '">' . e($obat['stok']) . $stock_icon . '</td>';
        echo '<td class="is-vcentered has-text-right">';
        echo '<div class="buttons is-right mb-0">';
        echo '<a href="form-stok.php?id=' . e($obat['id']) . '" class="button is-success is-small is-responsive-icon" title="Tambah Stok (Restock)">';
        echo '<span class="icon"><i class="mdi mdi-package-down"></i></span><span class="is-hidden-mobile">Restock</span></a>';
        echo '<a href="form-obat.php?id=' . e($obat['id']) . '" class="button is-warning is-small is-responsive-icon" title="Edit Profil Obat">';
        echo '<span class="icon"><i class="mdi mdi-square-edit-outline"></i></span></a>';
        echo '</div></td>';
        echo '</tr>';
    }
    exit;
}

// Memuat header halaman untuk request normal
$page_title = 'Manajemen Obat & Inventaris - Klinik';
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-4-mobile">
            <span class="icon has-text-primary">
                <i class="mdi mdi-pill"></i>
            </span>
            <span>Manajemen Obat</span>
        </h1>
        <h2 class="subtitle is-size-5 has-text-grey is-size-6-mobile">
            Inventaris dan Harga Obat
        </h2>
    </div>
    <div class="column is-narrow is-narrow-mobile">
        <a class="button is-primary" href="form-obat.php">
            <span class="icon-text">
                <span class="icon">
                    <i class="mdi mdi-plus-box"></i>
                </span>
                <span class="is-hidden-mobile">Tambah Obat Baru</span>
            </span>
        </a>
    </div>
</div>

<form method="GET" action="manajemen-obat.php" class="mb-5" id="search-form" onsubmit="event.preventDefault();">
    <div class="field has-addons">
        <div class="control is-expanded has-icons-left">
            <input
                class="input"
                type="search"
                name="search"
                placeholder="Cari berdasarkan nama obat atau scan barcode kode obat..."
                value="<?= e($search) ?>"
                hx-get="manajemen-obat.php"
                hx-target="#obat-tbody"
                hx-trigger="keyup changed delay:500ms, search"
                aria-label="Pencarian Obat"
                autofocus>
            <span class="icon is-left">
                <i class="mdi mdi-magnify"></i>
            </span>
        </div>
    </div>
</form>

<div class="box box-desktop table-container">
    <table class="table is-fullwidth is-striped is-hoverable">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Obat</th>
                <th>Nama Obat</th>
                <th class="is-hidden-mobile" width="10%">Satuan</th>
                <th width="15%">Harga Satuan</th>
                <th width="10%">Stok</th>
                <th width="20%" class="has-text-right">Aksi</th>
            </tr>
        </thead>
        <tbody id="obat-tbody">
            <?php if (empty($obat_list)): ?>
                <tr>
                    <td colspan="7" class="has-text-centered py-5 has-text-grey">
                        Belum ada data obat di sistem.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($obat_list as $index => $obat): 
                    $is_low_stock = $obat['stok'] <= 10;
                    $stock_class = $is_low_stock ? 'has-text-danger has-text-weight-bold' : 'has-text-weight-medium';
                ?>
                    <tr>
                        <td class="is-vcentered"><?= $index + 1 ?></td>
                        <td class="is-vcentered">
                            <span class="tag is-light is-info has-text-weight-bold"><?= e($obat['kode_obat']) ?></span>
                        </td>
                        <td class="is-vcentered has-text-weight-bold"><?= e($obat['nama_obat']) ?></td>
                        <td class="is-vcentered is-hidden-mobile"><?= e($obat['satuan']) ?></td>
                        <td class="is-vcentered">Rp <?= number_format($obat['harga_satuan'], 0, ',', '.') ?></td>
                        <td class="is-vcentered <?= $stock_class ?>">
                            <?= e($obat['stok']) ?>
                            <?php if ($is_low_stock): ?>
                                <span class="icon is-small ml-1 has-text-danger" title="Stok Menipis"><i class="mdi mdi-alert-circle"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="is-vcentered has-text-right">
                            <div class="buttons is-right mb-0">
                                <!-- Tombol aksi dipisahkan sesuai strategi validasi gudang -->
                                <a href="form-stok.php?id=<?= e($obat['id']) ?>" class="button is-success is-small is-responsive-icon" title="Tambah Stok (Restock)">
                                    <span class="icon"><i class="mdi mdi-package-down"></i></span>
                                    <span class="is-hidden-mobile">Restock</span>
                                </a>
                                <a href="form-obat.php?id=<?= e($obat['id']) ?>" class="button is-warning is-small is-responsive-icon" title="Edit Profil Obat">
                                    <span class="icon"><i class="mdi mdi-square-edit-outline"></i></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
