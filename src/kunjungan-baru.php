<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Jika HTMX meminta hasil pencarian
if (isset($_GET['q'])) {
    $search = '%' . $_GET['q'] . '%';
    $stmt = $pdo->prepare("SELECT id, no_rm, nama_lengkap FROM pasien WHERE nama_lengkap LIKE ? OR no_rm LIKE ? LIMIT 5");
    $stmt->execute([$search, $search]);
    $pasien_list = $stmt->fetchAll();

    if (empty($pasien_list)) {
        echo '<div class="notification is-warning is-light is-size-7">Pasien tidak ditemukan. <a href="form-pasien.php">Daftar Pasien Baru</a></div>';
    } else {
        foreach ($pasien_list as $p) {
            echo '<div class="box is-flex is-justify-content-space-between is-align-items-center py-2 mb-2">
                    <div>
                        <strong>' . e($p['nama_lengkap']) . '</strong><br>
                        <span class="is-size-7 has-text-grey">' . e($p['no_rm']) . '</span>
                    </div>
                    <form action="aksi-kunjungan.php" method="POST">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="pasien_id" value="' . $p['id'] . '">
                        <button type="submit" class="button is-small is-primary">Pilih</button>
                    </form>
                  </div>';
        }
    }
    exit;
}
?>

<div class="modal is-active">
    <div class="modal-background" onclick="document.getElementById('modal-container').innerHTML = ''"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title is-size-5">Daftarkan Kunjungan Baru</p>
            <button class="delete" aria-label="close" onclick="document.getElementById('modal-container').innerHTML = ''"></button>
        </header>
        <section class="modal-card-body">
            <div class="field">
                <label class="label">Cari Pasien (Nama/No. RM)</label>
                <div class="control has-icons-left">
                    <input class="input" type="text" placeholder="Ketik nama atau No. RM..." 
                           hx-get="kunjungan-baru.php" hx-trigger="input changed delay:300ms" 
                           hx-target="#search-results" name="q" autofocus autocomplete="off">
                    <span class="icon is-left"><i class="mdi mdi-magnify"></i></span>
                </div>
            </div>
            <div id="search-results" class="mt-4">
                <p class="has-text-grey is-size-7">Silakan ketik nama pasien untuk memulai pencarian.</p>
            </div>
        </section>
    </div>
</div>

