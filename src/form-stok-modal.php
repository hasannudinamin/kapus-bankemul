<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;

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
    <div class="modal-background" onclick="closeModal()"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title is-size-5 has-text-weight-bold">
                <span class="icon has-text-success mr-2"><i class="mdi mdi-package-down"></i></span>
                Restock Obat
            </p>
            <button class="delete" aria-label="close" type="button" onclick="closeModal()"></button>
        </header>
        <section class="modal-card-body">
            <!-- Formulir akan mengarah ke aksi-stok.php untuk pemrosesan database -->
            <form id="form-restock" action="aksi-obat.php" method="POST">
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="id" value="<?= e($obat['id']) ?>">
                
                <div class="box has-background-white-bis shadow-none border mb-5" style="border: 1px solid #f0f0f0;">
                    <p class="mb-1 is-size-6"><strong>Nama Obat:</strong> <?= e($obat['nama_obat']) ?></p>
                    <p class="mb-0 is-size-6">
                        <strong>Stok Saat Ini:</strong> 
                        <span class="tag is-info is-light has-text-weight-bold ml-1">
                            <?= e($obat['stok']) ?> <?= e($obat['satuan']) ?>
                        </span>
                    </p>
                </div>

                <div class="field">
                    <label class="label">Jumlah Stok Masuk (Tambahan) <span class="has-text-danger">*</span></label>
                    <div class="control has-icons-left">
                        <input 
                            class="input is-success is-medium" 
                            type="number" 
                            name="jumlah_masuk" 
                            required 
                            min="1" 
                            placeholder="Contoh: 50" 
                            autofocus>
                        <span class="icon is-left"><i class="mdi mdi-plus-box"></i></span>
                    </div>
                    <p class="help has-text-grey">
                        <i class="mdi mdi-information-outline"></i> Masukkan jumlah barang baru yang masuk ke gudang. Angka ini akan otomatis ditambahkan ke stok saat ini.
                    </p>
                </div>
            </form>
        </section>
        <footer class="modal-card-foot is-justify-content-flex-end" style="background-color: #fafafa;">
            <button class="button" type="button" onclick="closeModal()">Batal</button>
            <button class="button is-success" type="submit" form="form-restock">
                <span class="icon"><i class="mdi mdi-content-save"></i></span>
                <span>Simpan Stok Masuk</span>
            </button>
        </footer>
    </div>
</div>

<script>
    // Fungsi sederhana untuk menghapus modal dari DOM
    function closeModal() {
        const modalContainer = document.getElementById('modal-container');
        if (modalContainer) {
            modalContainer.innerHTML = '';
        }
    }

    // Menutup modal jika tombol Escape ditekan
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
</script>
