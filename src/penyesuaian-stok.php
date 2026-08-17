<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo '<div class="notification is-danger">Kesalahan: Parameter ID Obat tidak valid.</div>';
    exit;
}

// Mengambil data obat utama
$stmt = $pdo->prepare("SELECT id, nama_obat, satuan, stok FROM obat WHERE id = ?");
$stmt->execute([$id]);
$obat = $stmt->fetch();

if (!$obat) {
    echo '<div class="notification is-danger">Kesalahan: Data obat tidak ditemukan.</div>';
    exit;
}

// Mengambil daftar batch untuk pilihan penyesuaian
// Kita menampilkan semua batch (termasuk stok 0) karena bisa jadi penyesuaian berupa penambahan barang yang terselip
$stmt_batch = $pdo->prepare("
    SELECT id, no_batch, tanggal_kedaluwarsa, stok_batch 
    FROM obat_batch 
    WHERE obat_id = ? 
    ORDER BY stok_batch DESC, tanggal_kedaluwarsa ASC
");
$stmt_batch->execute([$id]);
$batches = $stmt_batch->fetchAll();
?>

<div class="modal is-active" id="opname-modal">
    <div class="modal-background" onclick="document.getElementById('modal-container').innerHTML = ''"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title is-size-5 has-text-weight-bold">
                <span class="icon has-text-warning-dark mr-2"><i class="mdi mdi-scale-balance"></i></span>
                Penyesuaian Stok (Opname)
            </p>
            <button class="delete" aria-label="close" type="button" onclick="document.getElementById('modal-container').innerHTML = ''"></button>
        </header>
        <section class="modal-card-body">
            
            <?php if (empty($batches)): ?>
                <div class="notification is-warning is-light">
                    <span class="icon"><i class="mdi mdi-alert"></i></span>
                    Belum ada data batch untuk obat ini. Silakan lakukan <strong>Restock</strong> terlebih dahulu sebelum melakukan penyesuaian.
                </div>
            <?php else: ?>
                <form id="form-opname" action="aksi-obat.php" method="POST">
                    <input type="hidden" name="action" value="opname">
                    <input type="hidden" name="id" value="<?= e($obat['id']) ?>">
                    
                    <div class="box has-background-white-bis shadow-none border mb-4" style="border: 1px solid #f0f0f0;">
                        <p class="mb-1 is-size-6"><strong>Nama Obat:</strong> <?= e($obat['nama_obat']) ?></p>
                        <p class="mb-0 is-size-6">
                            <strong>Total Stok Sistem:</strong> 
                            <span class="tag is-info is-light has-text-weight-bold ml-1">
                                <?= number_format($obat['stok'], 0, ',', '.') ?> <?= e($obat['satuan']) ?>
                            </span>
                        </p>
                    </div>

                    <div class="field">
                        <label class="label is-small">Pilih Batch yang Disesuaikan <span class="has-text-danger">*</span></label>
                        <div class="control has-icons-left">
                            <div class="select is-fullwidth">
                                <select name="obat_batch_id" required>
                                    <option value="" disabled selected>-- Pilih Batch --</option>
                                    <?php foreach ($batches as $batch): 
                                        $label_batch = !empty($batch['no_batch']) ? e($batch['no_batch']) : 'Tanpa No. Batch';
                                        $label_ed = date('d/m/Y', strtotime($batch['tanggal_kedaluwarsa']));
                                        $label_stok = number_format($batch['stok_batch'], 0, ',', '.');
                                    ?>
                                        <option value="<?= e($batch['id']) ?>" data-stok="<?= e($batch['stok_batch']) ?>">
                                            Batch: <?= $label_batch ?> | ED: <?= $label_ed ?> | Sisa Sistem: <?= $label_stok ?> <?= e($obat['satuan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <span class="icon is-small is-left"><i class="mdi mdi-layers"></i></span>
                        </div>
                    </div>

                    <div class="columns is-mobile is-multiline mb-0">
                        <div class="column is-6-desktop is-12-mobile pb-1">
                            <div class="field">
                                <label class="label is-small">Jenis Penyesuaian <span class="has-text-danger">*</span></label>
                                <div class="control has-icons-left">
                                    <div class="select is-fullwidth">
                                        <select name="jenis_penyesuaian" required>
                                            <option value="pengurangan">Pengurangan (-)</option>
                                            <option value="penambahan">Penambahan (+)</option>
                                        </select>
                                    </div>
                                    <span class="icon is-small is-left"><i class="mdi mdi-swap-vertical"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="column is-6-desktop is-12-mobile pb-1">
                            <div class="field">
                                <label class="label is-small">Jumlah Selisih <span class="has-text-danger">*</span></label>
                                <div class="control has-icons-left has-icons-right">
                                    <input class="input is-warning" type="number" name="jumlah" required min="1" placeholder="Cth: 2">
                                    <span class="icon is-small is-left"><i class="mdi mdi-numeric"></i></span>
                                    <span class="icon is-small is-right has-text-grey" style="width: auto; padding-right: 15px;"><?= e($obat['satuan']) ?></span>
                                </div>
                                <p class="help has-text-grey is-size-7 mt-1">Masukkan angka selisihnya saja.</p>
                            </div>
                        </div>
                    </div>

                    <div class="field mt-2">
                        <label class="label is-small">Keterangan / Alasan <span class="has-text-danger">*</span></label>
                        <div class="control has-icons-left">
                            <input class="input" type="text" name="keterangan" required placeholder="Contoh: Barang rusak, botol pecah, dsb." autocomplete="off">
                            <span class="icon is-small is-left"><i class="mdi mdi-text-box-edit-outline"></i></span>
                        </div>
                    </div>

                </form>
            <?php endif; ?>
        </section>
        <footer class="modal-card-foot is-justify-content-flex-end" style="background-color: #fafafa;">
            <?php if (!empty($batches)): ?>
                <button class="button is-warning is-dark" type="submit" form="form-opname" onclick="return confirm('Apakah Anda yakin ingin melakukan penyesuaian stok ini? Aksi ini akan dicatat di Kartu Stok.');">
                    <span class="icon"><i class="mdi mdi-content-save-alert"></i></span>
                    <span>Simpan Penyesuaian</span>
                </button>
            <?php endif; ?>
        </footer>
    </div>
</div>
