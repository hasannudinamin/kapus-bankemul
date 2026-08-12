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

// Menyimpan hasil render grid ke dalam buffer
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
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-cash-multiple"></i></span> Harga
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
                <footer class="card-footer" style="background-color: #fafafa; padding: 0.75rem;">
                    <div class="card-footer-item p-0 mr-2">
                        <!-- Menggunakan HTMX untuk memanggil modal restock -->
                        <button 
                            hx-get="form-stok-modal.php?id=<?= e($obat['id']) ?>" 
                            hx-target="#modal-container"
                            class="button is-success is-small is-fullwidth"
                            title="Tambah Stok">
                            <span class="icon"><i class="mdi mdi-package-down"></i></span>
                            <span>Restock</span>
                        </button>
                    </div>
                    <div class="card-footer-item p-0">
                        <a href="form-obat.php?id=<?= e($obat['id']) ?>" class="button is-warning is-light is-small is-fullwidth" title="Edit Profil Obat">
                            <span class="icon"><i class="mdi mdi-square-edit-outline"></i></span>
                            <span>Edit Info</span>
                        </a>
                    </div>
                </footer>
            </div>
        </div>
        <?php
    }
}
$grid_html = ob_get_clean();

// Jika request berasal dari HTMX (Live Search), muntahkan grid dan hentikan eksekusi
if ($is_htmx) {
    echo $grid_html;
    exit;
}

// Memuat header halaman untuk request normal browser
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
                hx-target="#obat-grid"
                hx-trigger="keyup changed delay:500ms, search"
                aria-label="Pencarian Obat"
                autofocus>
            <span class="icon is-left">
                <i class="mdi mdi-magnify"></i>
            </span>
        </div>
    </div>
</form>

<div class="columns is-multiline" id="obat-grid">
    <?= $grid_html ?>
</div>

<!-- Penampung DOM (Container) untuk Modal HTMX -->
<div id="modal-container"></div>

<?php include 'includes/footer.php'; ?>
