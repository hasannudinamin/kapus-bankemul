<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect("manajemen-obat.php");
}

// 1. Ambil Data Master Obat
$stmt_obat = $pdo->prepare("SELECT * FROM obat WHERE id = ?");
$stmt_obat->execute([$id]);
$obat = $stmt_obat->fetch();

if (!$obat) {
    die("Kesalahan: Data obat tidak ditemukan di database.");
}

// 2. Ambil Data Rincian Batch Aktif (Stok > 0)
$stmt_batch = $pdo->prepare("
    SELECT * FROM obat_batch 
    WHERE obat_id = ? AND stok_batch > 0 
    ORDER BY tanggal_kedaluwarsa ASC
");
$stmt_batch->execute([$id]);
$batch_list = $stmt_batch->fetchAll();

// 3. Ambil Data Log Stok (Riwayat Pergerakan)
$stmt_log = $pdo->prepare("
    SELECT ls.*, u.nama_lengkap AS nama_petugas, ob.no_batch, ob.tanggal_kedaluwarsa 
    FROM log_stok ls
    LEFT JOIN users u ON ls.user_id = u.id
    LEFT JOIN obat_batch ob ON ls.obat_batch_id = ob.id
    WHERE ls.obat_id = ?
    ORDER BY ls.tanggal DESC
    LIMIT 100
");
$stmt_log->execute([$id]);
$log_list = $stmt_log->fetchAll();

$page_title = 'Detail Obat - ' . e($obat['nama_obat']);
include 'includes/header.php';
?>

<div class="is-flex is-justify-content-space-between is-align-items-center mb-5">
    <div>
        <h1 class="title is-size-3 mb-1 is-size-5-mobile">
            <span class="icon has-text-primary mr-2"><i class="mdi mdi-pill"></i></span>
            <?= e($obat['nama_obat']) ?>
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey mt-0">
            Kode: <strong><?= e($obat['kode_obat']) ?></strong>
        </h2>
    </div>
    <div class="buttons">
        <a href="manajemen-obat.php" class="button is-light">
            <span class="icon"><i class="mdi mdi-arrow-left"></i></span>
            <span class="is-hidden-mobile">Kembali</span>
        </a>
    </div>
</div>

<!-- Panel Metrik Utama Obat -->
<div class="columns is-multiline mb-4">
    <div class="column is-4">
        <div class="box has-background-primary-light border-primary-top h-100">
            <p class="heading has-text-primary has-text-weight-bold">Total Stok Akumulasi</p>
            <p class="title is-3 has-text-primary-dark">
                <?= number_format($obat['stok'], 0, ',', '.') ?> <span class="is-size-5 has-text-weight-normal"><?= e($obat['satuan']) ?></span>
            </p>
        </div>
    </div>
    <div class="column is-4">
        <div class="box has-background-info-light border-info-top h-100">
            <p class="heading has-text-info has-text-weight-bold">Harga Jual Satuan</p>
            <p class="title is-3 has-text-info-dark">Rp <?= number_format($obat['harga_satuan'], 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="column is-4">
        <div class="box has-background-white h-100 is-flex is-flex-direction-column is-justify-content-center">
            <div class="buttons is-centered mb-0 w-100">
                <button hx-get="form-stok-modal.php?id=<?= e($obat['id']) ?>&ref=obat" hx-target="#modal-container" class="button is-success is-fullwidth mb-2">
                    <span class="icon"><i class="mdi mdi-package-down"></i></span>
                    <span>Restock (Masuk)</span>
                </button>
                <button hx-get="penyesuaian-stok.php?id=<?= e($obat['id']) ?>&ref=obat" hx-target="#modal-container" class="button is-warning is-light is-fullwidth">
                    <span class="icon"><i class="mdi mdi-scale-balance"></i></span>
                    <span>Stock Opname</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="columns is-desktop is-variable is-4">
    <!-- Kolom Kiri: Rincian Batch Aktif -->
    <div class="column is-5">
        <div class="box h-100 border-top border-success">
            <h4 class="title is-5 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-layers"></i></span> Rincian Batch Aktif
            </h4>
            
            <?php if (empty($batch_list)): ?>
                <div class="notification is-danger is-light has-text-centered">
                    <span class="icon is-large"><i class="mdi mdi-package-variant-closed mdi-36px"></i></span>
                    <p class="mt-2">Stok fisik kosong. Belum ada batch aktif.</p>
                </div>
            <?php else: ?>
                <?php foreach ($batch_list as $batch): 
                    // Menentukan status kedaluwarsa
                    $ed = new DateTime($batch['tanggal_kedaluwarsa']);
                    $today = new DateTime();
                    $interval = $today->diff($ed);
                    $days_left = (int)$interval->format('%r%a');
                    
                    $ed_class = 'has-text-success';
                    $ed_icon = 'mdi-check-circle';
                    if ($days_left < 0) {
                        $ed_class = 'has-text-danger has-text-weight-bold';
                        $ed_icon = 'mdi-alert-octagon';
                    } elseif ($days_left <= 90) {
                        $ed_class = 'has-text-warning-dark has-text-weight-bold';
                        $ed_icon = 'mdi-alert';
                    }
                ?>
                    <div class="card mb-3 shadow-sm">
                        <div class="card-content p-3">
                            <div class="is-flex is-justify-content-space-between is-align-items-center mb-2">
                                <span class="tag is-info is-light has-text-weight-bold">
                                    <span class="icon is-small mr-1"><i class="mdi mdi-barcode"></i></span>
                                    <?= !empty($batch['no_batch']) ? e($batch['no_batch']) : 'Tanpa No. Batch' ?>
                                </span>
                                <span class="tag is-white is-size-6 has-text-weight-bold">
                                    <?= number_format($batch['stok_batch'], 0, ',', '.') ?> <?= e($obat['satuan']) ?>
                                </span>
                            </div>
                            <div class="content is-small mb-0">
                                <p class="mb-1 is-flex is-justify-content-space-between">
                                    <span class="has-text-grey">Kedaluwarsa (ED):</span>
                                    <span class="<?= $ed_class ?>">
                                        <span class="icon is-small mr-1"><i class="mdi <?= $ed_icon ?>"></i></span>
                                        <?= date('d/m/Y', strtotime($batch['tanggal_kedaluwarsa'])) ?>
                                    </span>
                                </p>
                                <p class="mb-0 is-flex is-justify-content-space-between">
                                    <span class="has-text-grey">Harga Beli Satuan:</span>
                                    <span class="has-text-weight-medium">Rp <?= number_format($batch['harga_beli'], 0, ',', '.') ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Kanan: Kartu Stok (Riwayat Pergerakan) -->
    <div class="column is-7">
        <div class="box h-100 border-top border-info">
            <h4 class="title is-5 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-history"></i></span> Kartu Stok (Riwayat)
            </h4>
            
            <div class="table-container" style="max-height: 500px; overflow-y: auto;">
                <table class="table is-fullwidth is-striped is-hoverable is-narrow is-size-7">
                    <thead style="position: sticky; top: 0; background-color: white; z-index: 1;">
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis</th>
                            <th class="has-text-centered">Jumlah</th>
                            <th>No. Batch / ED</th>
                            <th>Keterangan</th>
                            <th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log_list)): ?>
                            <tr>
                                <td colspan="6" class="has-text-centered has-text-grey is-italic py-4">Belum ada riwayat pergerakan stok.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($log_list as $log): 
                                $jenis = strtolower($log['jenis_transaksi']);
                                $qty_class = '';
                                $qty_sign = '';
                                $jenis_tag = '';
                                
                                if ($jenis === 'masuk') {
                                    $qty_class = 'has-text-success has-text-weight-bold';
                                    $qty_sign = '+';
                                    $jenis_tag = '<span class="tag is-success is-light is-small">Masuk</span>';
                                } elseif ($jenis === 'keluar') {
                                    $qty_class = 'has-text-danger has-text-weight-bold';
                                    $qty_sign = '-';
                                    $jenis_tag = '<span class="tag is-danger is-light is-small">Keluar</span>';
                                } elseif ($jenis === 'penyesuaian') {
                                    $qty_class = 'has-text-warning-dark has-text-weight-bold';
                                    $qty_sign = '';
                                    $jenis_tag = '<span class="tag is-warning is-light is-small">Opname</span>';
                                }
                            ?>
                                <tr>
                                    <td class="is-vcentered whitespace-nowrap"><?= date('d/m/y H:i', strtotime($log['tanggal'])) ?></td>
                                    <td class="is-vcentered"><?= $jenis_tag ?></td>
                                    <td class="is-vcentered has-text-centered <?= $qty_class ?>">
                                        <?= $qty_sign ?><?= number_format($log['jumlah'], 0, ',', '.') ?>
                                    </td>
                                    <td class="is-vcentered">
                                        <?= !empty($log['no_batch']) ? e($log['no_batch']) : '-' ?><br>
                                        <span class="has-text-grey">ED: <?= !empty($log['tanggal_kedaluwarsa']) ? date('d/m/y', strtotime($log['tanggal_kedaluwarsa'])) : '-' ?></span>
                                    </td>
                                    <td class="is-vcentered has-text-grey"><?= e($log['keterangan']) ?></td>
                                    <td class="is-vcentered"><?= e($log['nama_petugas'] ?? 'Sistem') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Penampung Modal HTMX -->
<div id="modal-container"></div>

<?php include 'includes/footer.php'; ?>
