<?php
require 'includes/auth-check.php';
require 'includes/admin-only.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;
$pasien = null;
$display_rm = '';

/** @var \PDO $pdo */
if ($id) {
    // Mengambil data pasien jika parameter ID tersedia
    $stmt = $pdo->prepare("SELECT * FROM pasien WHERE id = ?");
    $stmt->execute([$id]);
    $pasien = $stmt->fetch();

    if (!$pasien) {
        die("Data pasien tidak ditemukan.");
    }
    
    // Format untuk mode edit mengambil nilai utuh dari database
    $display_rm = $pasien['no_rm'];
} else {
    // Mode tambah hanya menampilkan penanda teks
    $display_rm = '[Otomatis oleh Sistem]';
}

// Menetapkan judul halaman secara dinamis
$page_title = $id ? 'Edit Pasien' : 'Tambah Pasien';
include 'includes/header.php';

// Mengambil daftar jenis kelamin dari references.php
$daftar_jenis_kelamin = JENIS_KELAMIN;
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-5-mobile">
            <span class="icon has-text-primary">
                <i class="mdi <?= $id ? 'mdi-account-edit' : 'mdi-account-plus' ?>"></i>
            </span>
            <span>
                <?= $id ? 'Edit Data Pasien' : 'Tambah Pasien Baru' ?>
            </span>
        </h1>
    </div>
    <div class="column is-narrow is-narrow-mobile">
        <div class="is-hidden-desktop">
            <a class="button is-light is-responsive-icon" href="pasien.php">
                <span class="icon">
                    <i class="mdi mdi-arrow-left"></i>
                </span>
            </a>
        </div>
        <div class="is-hidden-touch">
            <a class="button is-light" href="pasien.php">
                <span class="icon">
                    <i class="mdi mdi-arrow-left"></i>
                </span>
                <span>Batal</span>
            </a>
        </div>
    </div>
</div>

<div class="box box-desktop border-primary-top">
    <form action="aksi-pasien.php" method="POST">
        <input type="hidden" name="action" value="<?= $id ? 'update' : 'create' ?>">
        <?php if ($id) : ?>
            <input type="hidden" name="id" value="<?= e($id) ?>">
        <?php endif; ?>

        <div class="columns">
            <div class="column is-4">
                <div class="field">
                    <label class="label" for="no_rm_display">Nomor Rekam Medis (RM)</label>
                    <div class="control has-icons-left">
                        <?php if ($id) : ?>
                            <!-- Input tersembunyi yang menjaga data RM saat update -->
                            <input type="hidden" name="no_rm" value="<?= e($pasien['no_rm']) ?>">
                        <?php endif; ?>
                        <!-- Input read-only untuk tampilan UI -->
                        <input 
                          class="input has-background-white-bis has-text-weight-bold <?= !$id ? 'has-text-grey' : '' ?>" 
                          type="text" 
                          id="no_rm_display" 
                          value="<?= e($display_rm) ?>" 
                          readonly>
                        <span class="icon is-small is-left"><i class="mdi mdi-clipboard-account"></i></span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="nama_lengkap">Nama Lengkap <span class="has-text-danger">*</span></label>
                    <div class="control has-icons-left">
                    <input 
                      class="input is-primary-focus" 
                      type="text" 
                      id="nama_lengkap" 
                      name="nama_lengkap" 
                      value="<?= $pasien ? e($pasien['nama_lengkap']) : '' ?>" 
                      required 
                      placeholder="Contoh: Budi Santoso">
                        <span class="icon is-small is-left"><i class="mdi mdi-account"></i></span>
                    </div>
                </div>
            </div>
            
            <div class="column is-4">
                <div class="field">
                    <label class="label" for="tanggal_lahir">Tanggal Lahir</label>
                    <div class="control has-icons-left">
                    <input 
                      class="input is-primary-focus" 
                      type="date" 
                      id="tanggal_lahir" 
                      name="tanggal_lahir" 
                      value="<?= $pasien ? e($pasien['tanggal_lahir']) : '' ?>">
                        <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="columns">
            <div class="column is-4">
                <div class="field">
                    <label class="label" for="jenis_kelamin">Jenis Kelamin</label>
                    <div class="control has-icons-left">
                        <div class="select is-fullwidth is-primary-focus">
                            <select id="jenis_kelamin" name="jenis_kelamin">
                                <option value="" disabled <?= !$pasien ? 'selected' : '' ?>>Pilih Jenis Kelamin...</option>
                                <?php foreach ($daftar_jenis_kelamin as $kode => $label) : ?>
                                    <option value="<?= e($kode) ?>" <?= ($pasien && isset($pasien['jenis_kelamin']) && $pasien['jenis_kelamin'] === (string)$kode) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="icon is-small is-left"><i class="mdi mdi-gender-male-female"></i></span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="alamat">Alamat</label>
                    <div class="control has-icons-left">
                    <input 
                      class="input is-primary-focus" 
                      type="text" 
                      id="alamat" 
                      name="alamat" 
                      value="<?= $pasien ? e($pasien['alamat']) : '' ?>" 
                      placeholder="Contoh: Jl. Sudirman No. 10">
                        <span class="icon is-small is-left"><i class="mdi mdi-map-marker"></i></span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="no_telp">Nomor Telepon</label>
                    <div class="control has-icons-left">
                    <input 
                      class="input is-primary-focus" 
                      type="text" 
                      id="no_telp" 
                      name="no_telp" 
                      value="<?= $pasien ? e($pasien['no_telp']) : '' ?>" 
                      placeholder="Contoh: 08123456789">
                        <span class="icon is-small is-left"><i class="mdi mdi-phone"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="field is-grouped mt-5">
            <div class="control">
                <button type="submit" class="button is-primary">
                    <span class="icon">
                        <i class="mdi mdi-content-save"></i>
                    </span>
                    <span>Simpan Data Pasien</span>
                </button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
