<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;
$ref = $_GET['ref'] ?? null;
$obat = null;

if ($id) {
    // Mengambil data obat jika parameter ID tersedia untuk mode edit
    $stmt = $pdo->prepare("SELECT * FROM obat WHERE id = ?");
    $stmt->execute([$id]);
    $obat = $stmt->fetch();

    if (!$obat) {
        die("Data obat tidak ditemukan.");
    }
}

// Menetapkan judul halaman secara dinamis
$page_title = $id ? 'Edit Profil Obat' : 'Tambah Obat Baru';
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-5-mobile">
            <span class="icon has-text-primary">
                <i class="mdi <?= $id ? 'mdi-square-edit-outline' : 'mdi-plus-box' ?>"></i>
            </span>
            <span><?= $id ? 'Edit Profil Obat' : 'Tambah Obat Baru' ?></span>
        </h1>
    </div>
    <div class="column is-narrow is-narrow-mobile">
        <div class="is-hidden-desktop">
            <a class="button is-light is-responsive-icon" href="manajemen-obat.php">
                <span class="icon">
                    <i class="mdi mdi-arrow-left"></i>
                </span>
            </a>
        </div>
        <div class="is-hidden-touch">
  <a class="button is-light" href="<?= $ref === 'obat' ? 'obat.php?id=' . e($id) : 'manajemen-obat.php' ?>">
                <span class="icon">
                    <i class="mdi mdi-arrow-left"></i>
                </span>
                <span>Batal / Kembali</span>
            </a>
        </div>
    </div>
</div>

<div class="box box-desktop border-primary-top">
    <form action="aksi-obat.php" method="POST">
        <input type="hidden" name="action" value="<?= $id ? 'update' : 'create' ?>">
        <?php if ($id) : ?>
            <input type="hidden" name="id" value="<?= e($id) ?>">
        <?php endif; ?>
    
        <?php if ($ref) : ?>
            <input type="hidden" name="ref" value="<?= e($ref) ?>">
        <?php endif; ?>

        <div class="columns is-multiline">
            <div class="column is-6">
                <div class="field">
                    <label class="label" for="kode_obat">Kode Obat / Barcode</label>
                    <div class="control has-icons-left">
                        <input 
                          class="input is-primary-focus" 
                          type="text" 
                          id="kode_obat" 
                          name="kode_obat" 
                          value="<?= $obat ? e($obat['kode_obat']) : '' ?>" 
                          placeholder="Scan barcode atau kosongkan untuk otomatis"
                          autofocus>
                        <span class="icon is-small is-left"><i class="mdi mdi-barcode-scan"></i></span>
                    </div>
                    <?php if (!$id): ?>
                        <p class="help has-text-grey">Biarkan kosong jika ingin sistem membuat kode secara otomatis (misal: OBT-0001).</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="column is-6">
                <div class="field">
                    <label class="label" for="nama_obat">Nama Obat <span class="has-text-danger">*</span></label>
                    <div class="control has-icons-left">
                        <input 
                          class="input is-primary-focus" 
                          type="text" 
                          id="nama_obat" 
                          name="nama_obat" 
                          value="<?= $obat ? e($obat['nama_obat']) : '' ?>" 
                          required 
                          placeholder="Contoh: Paracetamol 500mg">
                        <span class="icon is-small is-left"><i class="mdi mdi-medical-bag"></i></span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="satuan">Satuan <span class="has-text-danger">*</span></label>
                    <div class="control has-icons-left">
                        <input 
                          class="input is-primary-focus" 
                          type="text" 
                          id="satuan" 
                          name="satuan" 
                          value="<?= $obat ? e($obat['satuan']) : '' ?>" 
                          required 
                          placeholder="Contoh: Strip, Botol, Ampul">
                        <span class="icon is-small is-left"><i class="mdi mdi-scale-balance"></i></span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="harga_satuan">Harga Satuan (Rp) <span class="has-text-danger">*</span></label>
                    <div class="control has-icons-left">
                        <input 
                          class="input is-primary-focus" 
                          type="number" 
                          id="harga_satuan" 
                          name="harga_satuan" 
                          value="<?= $obat ? (int)$obat['harga_satuan'] : '0' ?>" 
                          required 
                          min="0"
                          step="1">
                        <span class="icon is-small is-left"><i class="mdi mdi-cash-multiple"></i></span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="stok"><?= $id ? 'Stok Tersedia' : 'Stok Awal Gudang' ?></label>
                    <div class="control has-icons-left">
                        <input 
                          class="input <?= $id ? 'has-background-white-bis has-text-weight-bold has-text-grey' : 'is-primary-focus' ?>" 
                          type="number" 
                          id="stok" 
                          name="stok" 
                          value="<?= $obat ? (int)$obat['stok'] : '0' ?>" 
                          <?= $id ? 'readonly' : 'required min="0"' ?>>
                        <span class="icon is-small is-left"><i class="mdi mdi-package-variant-closed"></i></span>
                    </div>
                    <?php if ($id): ?>
                        <p class="help has-text-danger"><i class="mdi mdi-lock-outline"></i> Gunakan menu <strong>Restock</strong> pada tabel utama untuk menyesuaikan stok fisik.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="field is-grouped mt-5">
            <div class="control">
                <button type="submit" class="button is-primary">
                    <span class="icon">
                        <i class="mdi mdi-content-save"></i>
                    </span>
                    <span>Simpan Data Obat</span>
                </button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
