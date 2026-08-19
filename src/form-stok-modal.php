<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;
$ref = $_GET['ref'] ?? null;

if (!$id) {
    echo '<div class="notification is-danger">Kesalahan: Parameter ID Obat tidak valid.</div>';
    exit;
}

// Mengambil data obat berdasarkan ID
$stmt = $pdo->prepare("SELECT id, nama_obat, satuan, stok FROM obat WHERE id = ?");
$stmt->execute([$id]);
$obat = $stmt->fetch();

if (!$obat) {
    echo '<div class="notification is-danger">Kesalahan: Data obat tidak ditemukan di database.</div>';
    exit;
}
?>

<div class="modal is-active" id="restock-modal">
    <!-- Latar belakang modal, klik untuk menutup -->
    <div class="modal-background" onclick="document.getElementById('modal-container').innerHTML = ''"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title is-size-5 has-text-weight-bold">
                <span class="icon has-text-success mr-2"><i class="mdi mdi-package-down"></i></span>
                Restock Obat (Batch Baru)
            </p>
            <button class="delete" aria-label="close" type="button" onclick="document.getElementById('modal-container').innerHTML = ''"></button>
        </header>
        <section class="modal-card-body">
            <!-- Formulir akan mengarah ke aksi-obat.php untuk pemrosesan database[cite: 3] -->
            <form id="form-restock" action="aksi-obat.php" method="POST">
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="id" value="<?= e($obat['id']) ?>">
        
                <?php if ($ref) : ?>
                    <input type="hidden" name="ref" value="<?= e($ref) ?>">
                <?php endif; ?>
                
                <div class="box has-background-white-bis shadow-none border mb-5" style="border: 1px solid #f0f0f0;">
                    <p class="mb-1 is-size-6"><strong>Nama Obat:</strong> <?= e($obat['nama_obat']) ?></p>
                    <p class="mb-0 is-size-6">
                        <strong>Stok Saat Ini:</strong> 
                        <span class="tag is-info is-light has-text-weight-bold ml-1">
                            <?= e($obat['stok']) ?> <?= e($obat['satuan']) ?>
                        </span>
                    </p>
                </div>

                <div class="columns is-mobile is-multiline mb-0">
                    <!-- Input Jumlah Masuk -->
                    <div class="column is-6-desktop is-12-mobile pb-1">
                        <div class="field">
                            <label class="label is-small">Jumlah Stok Masuk <span class="has-text-danger">*</span></label>
                            <div class="control has-icons-left has-icons-right">
                                <input id="input-jumlah" class="input is-success" type="number" name="jumlah_masuk" required min="1" placeholder="Contoh: 50" autofocus>
                                <span class="icon is-small is-left"><i class="mdi mdi-plus-box"></i></span>
                                <span class="icon is-small is-right has-text-grey" style="width: auto; padding-right: 15px;"><?= e($obat['satuan']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Input Harga Beli Total (Bantuan Kalkulasi) -->
                    <div class="column is-6-desktop is-12-mobile pb-1">
                        <div class="field">
                            <label class="label is-small">Harga Beli Total (Opsional)</label>
                            <div class="control has-icons-left">
                                <input id="input-total" class="input is-info" type="number" step="any" min="0" placeholder="Total harga faktur...">
                                <span class="icon is-small is-left"><i class="mdi mdi-calculator"></i></span>
                            </div>
                            <p class="help has-text-grey is-size-7 mt-1">Isi untuk hitung otomatis harga per satuan.</p>
                        </div>
                    </div>

                    <!-- Input Harga Beli Satuan -->
                    <div class="column is-6-desktop is-12-mobile pb-1">
                        <div class="field">
                            <label class="label is-small">Harga Beli (Per <?= e($obat['satuan']) ?>) <span class="has-text-danger">*</span></label>
                            <div class="control has-icons-left">
                                <input id="input-satuan" class="input" type="number" step="any" name="harga_beli" required min="0" placeholder="Contoh: 1500">
                                <span class="icon is-small is-left"><i class="mdi mdi-cash"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Input Tanggal Kedaluwarsa -->
                    <div class="column is-6-desktop is-12-mobile pb-1">
                        <div class="field">
                            <label class="label is-small">Tanggal Kedaluwarsa (ED) <span class="has-text-danger">*</span></label>
                            <div class="control has-icons-left">
                                <input class="input" type="date" name="tanggal_kedaluwarsa" required min="<?= date('Y-m-d') ?>">
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar-clock"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Input Nomor Batch -->
                    <div class="column is-12-desktop is-12-mobile pb-1">
                        <div class="field">
                            <label class="label is-small">No. Batch Pabrik</label>
                            <div class="control has-icons-left">
                                <input class="input" type="text" name="no_batch" placeholder="Opsional (kosongkan jika tidak ada)">
                                <span class="icon is-small is-left"><i class="mdi mdi-barcode"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="notification is-warning is-light is-size-7 mt-3 py-2 px-3">
                    <span class="icon"><i class="mdi mdi-information-outline"></i></span>
                    Sistem akan mencatat kedatangan ini sebagai <strong>Batch Baru</strong> dan akan dikeluarkan ke pasien menggunakan metode <strong>FEFO</strong> (First Expired, First Out).
                </div>
            </form>
        </section>
        <footer class="modal-card-foot is-justify-content-flex-end" style="background-color: #fafafa;">
            <button class="button" type="button" onclick="document.getElementById('modal-container').innerHTML = ''">Batal</button>
            <button class="button is-success" type="submit" form="form-restock">
                <span class="icon"><i class="mdi mdi-content-save"></i></span>
                <span>Simpan Stok Masuk</span>
            </button>
        </footer>
    </div>
</div>

<script>
    // Logika Kalkulasi Otomatis Harga Satuan
    const inputJumlah = document.getElementById('input-jumlah');
    const inputTotal = document.getElementById('input-total');
    const inputSatuan = document.getElementById('input-satuan');

    function hitungHargaSatuan() {
        const jumlah = parseFloat(inputJumlah.value) || 0;
        const total = parseFloat(inputTotal.value) || 0;

        // Melakukan kalkulasi hanya jika pengguna memasukkan angka valid
        if (jumlah > 0 && total > 0) {
            const hargaPerSatuan = total / jumlah;
            // Menghilangkan desimal berlebih jika angkanya bulat
            inputSatuan.value = Number.isInteger(hargaPerSatuan) ? hargaPerSatuan : hargaPerSatuan.toFixed(2);
        }
    }

    // Memicu kalkulasi saat input "Jumlah Masuk" atau "Harga Beli Total" berubah
    inputJumlah.addEventListener('input', hitungHargaSatuan);
    inputTotal.addEventListener('input', hitungHargaSatuan);
</script>
