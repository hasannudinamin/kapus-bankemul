<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id_rm = $_GET['id_rm'] ?? null;

if (!$id_rm) {
    redirect("pelayanan.php");
}

// Mengambil data rekam medis beserta informasi pasien
$stmt = $pdo->prepare("
    SELECT rm.*, p.nama_lengkap, p.no_rm, p.tanggal_lahir, p.jenis_kelamin 
    FROM rekam_medik rm 
    JOIN pasien p ON rm.pasien_id = p.id 
    WHERE rm.id = ?
");
$stmt->execute([$id_rm]);
$kunjungan = $stmt->fetch();

if (!$kunjungan) {
    die("Data kunjungan tidak ditemukan.");
}

// Mengambil daftar resep yang sudah ditambahkan pada kunjungan ini
$resep_stmt = $pdo->prepare("
    SELECT r.*, o.nama_obat, o.satuan 
    FROM resep r
    JOIN obat o ON r.obat_id = o.id
    WHERE r.rekam_medik_id = ?
    ORDER BY r.id ASC
");
$resep_stmt->execute([$id_rm]);
$resep_list = $resep_stmt->fetchAll();

$umur = hitung_umur($kunjungan['tanggal_lahir'], 'Tahun');
$page_title = 'Pemeriksaan: ' . e($kunjungan['nama_lengkap']);
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 mb-1 is-size-5-mobile">
            <span class="icon has-text-info mr-2">
                <i class="mdi mdi-stethoscope"></i>
            </span>
            <span>Pemeriksaan Medis</span>
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey mt-0 is-size-7-mobile">
            Pasien: <strong><?= e($kunjungan['nama_lengkap']) ?></strong> | No. RM: <strong><?= e($kunjungan['no_rm']) ?></strong>
        </h2>
    </div>
    <div class="column is-narrow">
        <a class="button is-light" href="pelayanan.php">
            <span class="icon"><i class="mdi mdi-arrow-left"></i></span>
            <span>Kembali ke Antrean</span>
        </a>
    </div>
</div>

<div class="columns is-desktop is-variable is-3">
    <!-- KOLOM KIRI: Form Rekam Medis Utama -->
    <div class="column is-6">
        <div class="box box-desktop" style="height: 100%;">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-4 pb-2 border-bottom">
                <h4 class="title is-5 mb-0 has-text-grey-dark">
                    <span class="icon"><i class="mdi mdi-clipboard-text-outline"></i></span>
                    Catatan Medis
                </h4>
                <span class="tag <?= $kunjungan['status_layanan'] === 'pemeriksaan' ? 'is-info' : 'is-warning' ?> is-light">
                    Status: <?= e(ucfirst($kunjungan['status_layanan'])) ?>
                </span>
            </div>

            <!-- Form terpisah murni untuk update data keluhan, diagnosa, tindakan -->
            <form action="aksi-pemeriksaan.php" method="POST">
                <input type="hidden" name="id_rm" value="<?= e($id_rm) ?>">

                <div class="field">
                    <label class="label has-text-grey">Keluhan Pasien (Anamnesa)</label>
                    <div class="control">
                        <textarea class="textarea is-info-focus" name="keluhan" rows="3" placeholder="Deskripsikan keluhan pasien..."><?= e($kunjungan['keluhan']) ?></textarea>
                    </div>
                </div>

                <div class="field">
    <label class="label has-text-grey">Diagnosa Utama (ICD-10)</label>
    <div class="control" id="diagnosa-widget" style="position: relative;">
        <div class="control has-icons-left">
            <input class="input is-info-focus" type="text" id="input-cari-diagnosa" name="diagnosa" value="<?= e($kunjungan['diagnosa']) ?>" placeholder="Ketik nama penyakit atau kode ICD-10..." autocomplete="off"
                hx-get="komponen-cari-diagnosa.php"
                hx-target="#hasil-pencarian-diagnosa"
                hx-trigger="input changed delay:500ms, search"
                hx-sync="this:replace">
            <span class="icon is-small is-left"><i class="mdi mdi-medical-bag"></i></span>
        </div>
        <!-- Hasil pencarian dari API akan muncul melayang di dalam div ini -->
        <div id="hasil-pencarian-diagnosa" style="position: absolute; width: 100%; z-index: 1000; top: 100%; left: 0;"></div>
    </div>
</div> 

                <div class="field">
                    <label class="label has-text-grey">Tindakan Medis</label>
                    <div class="control">
                        <textarea class="textarea is-info-focus" name="tindakan" rows="3" placeholder="Tindakan medis yang dilakukan di ruang periksa..."><?= e($kunjungan['tindakan']) ?></textarea>
                    </div>
                </div>

                <div class="field mt-5 is-grouped is-grouped-right">
                    <div class="control">
                        <button type="submit" name="action" value="simpan_draft" class="button is-light is-info">
                            <span class="icon"><i class="mdi mdi-content-save-outline"></i></span>
                            <span>Simpan Draft</span>
                        </button>
                    </div>
                    <div class="control">
                        <!-- Tombol ini akan menyimpan data sekaligus mengubah status ke 'farmasi' -->
                        <button type="submit" name="action" value="selesai_pemeriksaan" class="button is-info">
                            <span class="icon"><i class="mdi mdi-check-all"></i></span>
                            <span>Selesai & Lanjut Farmasi</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KOLOM KANAN: Form Resep Obat via HTMX -->
    <div class="column is-6">
        <div class="box box-desktop" style="height: 100%;">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-4 pb-2 border-bottom">
                <h4 class="title is-5 mb-0 has-text-grey-dark">
                    <span class="icon"><i class="mdi mdi-pill"></i></span>
                    Daftar Terapi (Resep)
                </h4>
            </div>

            <!-- Form Asinkron HTMX untuk Menambah Obat -->
            <div class="mb-5 p-4 has-background-white-bis" style="border-radius: 8px;">
                <form hx-post="aksi-resep-kunjungan.php" hx-target="#tabel-resep" hx-swap="beforeend" 
                      hx-on::after-request="if(event.detail.successful) { this.reset(); document.getElementById('hasil-pencarian').innerHTML = ''; }">
                    
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="rekam_medik_id" value="<?= e($id_rm) ?>">
                    
                    <div class="columns is-mobile is-multiline">
                        <div class="column is-12-desktop is-12-mobile">
                            <div class="field">
                                <label class="label is-small">Cari & Pilih Obat</label>
                                <!-- Widget Dropdown Custom HTMX -->
                                <div class="control" id="obat-widget" style="position: relative;">
                                    <div class="control has-icons-left">
                                        <!-- Input ini bertindak sebagai trigger pencarian -->
                                        <input class="input is-small is-link-focus" type="text" id="input-cari-obat" placeholder="Ketik nama obat..." autocomplete="off"
                                            hx-get="komponen-cari-obat.php"
                                            hx-target="#hasil-pencarian"
                                            hx-trigger="input changed delay:250ms, search"
                                            hx-sync="this:replace">
                                        <span class="icon is-small is-left"><i class="mdi mdi-magnify"></i></span>
                                    </div>
                                    <!-- Hasil pencarian akan muncul melayang di dalam div ini -->
                                    <div id="hasil-pencarian" style="position: absolute; width: 100%; z-index: 1000; top: 100%; left: 0;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="column is-6-desktop is-6-mobile">
                            <div class="field">
                                <label class="label is-small">Aturan Pakai (Signa)</label>
                                <div class="control">
                                    <input class="input is-small is-link-focus" type="text" name="aturan_pakai" placeholder="Contoh: 3 x 1 Sesudah Makan" required>
                                </div>
                            </div>
                        </div>
                        <div class="column is-3-desktop is-6-mobile">
                            <div class="field">
                                <label class="label is-small">Jumlah</label>
                                <div class="control">
                                    <input class="input is-small is-link-focus" type="number" name="jumlah" min="1" placeholder="Qty" required>
                                </div>
                            </div>
                        </div>
                        <div class="column is-3-desktop is-12-mobile">
                            <div class="field">
                                <label class="label is-small">&nbsp;</label>
                                <div class="control">
                                    <button type="submit" class="button is-link is-small is-fullwidth">
                                        <span class="icon"><i class="mdi mdi-plus"></i></span>
                                        <span>Tambah</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Resep yang Sudah Masuk -->
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable is-size-7-mobile">
                    <thead>
                        <tr>
                            <th width="45%">Nama Obat</th>
                            <th width="25%">Aturan Pakai</th>
                            <th width="15%">Jml</th>
                            <th width="15%" class="has-text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tabel-resep">
                        <?php if (empty($resep_list)): ?>
                            <tr id="baris-kosong">
                                <td colspan="4" class="has-text-centered has-text-grey is-italic">Belum ada obat yang diresepkan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resep_list as $resep) : ?>
                                <tr id="resep-<?= e($resep['id']) ?>">
                                    <td class="is-vcentered has-text-weight-bold"><?= e($resep['nama_obat']) ?></td>
                                    <td class="is-vcentered"><?= e($resep['aturan_pakai']) ?></td>
                                    <td class="is-vcentered"><?= e($resep['jumlah']) ?> <?= e($resep['satuan']) ?></td>
                                    <td class="is-vcentered has-text-right">
                                        <!-- Tombol Hapus Baris Resep via HTMX -->
                                        <button hx-post="aksi-resep-kunjungan.php" 
                                                hx-vals='{"action": "delete", "id": "<?= e($resep['id']) ?>"}'
                                                hx-target="closest tr" 
                                                hx-swap="outerHTML" 
                                                hx-confirm="Batalkan pemberian obat ini?"
                                                class="button is-danger is-small is-responsive-icon is-light" title="Hapus">
                                            <span class="icon"><i class="mdi mdi-trash-can-outline"></i></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Logika navigasi keyboard untuk widget pencarian obat HTMX
document.addEventListener('keydown', function(e) {
    const inputCari = document.getElementById('input-cari-obat');
    if (document.activeElement !== inputCari) return;

    const hasilContainer = document.getElementById('hasil-pencarian');
    if (!hasilContainer) return;

    const items = hasilContainer.querySelectorAll('.panel-block');
    if (items.length === 0) return;

    let currentIndex = Array.from(items).findIndex(item => item.classList.contains('has-background-link-light'));

    if (e.key === 'ArrowDown') {
        e.preventDefault(); 
        if (currentIndex >= 0) items[currentIndex].classList.remove('has-background-link-light');
        currentIndex = (currentIndex + 1) % items.length;
        items[currentIndex].classList.add('has-background-link-light');
        items[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (currentIndex >= 0) items[currentIndex].classList.remove('has-background-link-light');
        currentIndex = (currentIndex - 1 + items.length) % items.length;
        items[currentIndex].classList.add('has-background-link-light');
        items[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault(); 
        if (currentIndex >= 0) {
            items[currentIndex].click(); 
        } else if (items.length > 0) {
            items[0].click(); 
        }
    }
});

// Menghilangkan pesan "Belum ada obat" jika ada item baru masuk ke tabel via HTMX
document.body.addEventListener('htmx:afterSwap', function(evt) {
    if(evt.detail.target.id === 'tabel-resep') {
        const barisKosong = document.getElementById('baris-kosong');
        if(barisKosong) { barisKosong.remove(); }
    }
});


// Navigasi keyboard untuk widget diagnosa
document.addEventListener('keydown', function(e) {
    const inputDiagnosa = document.getElementById('input-cari-diagnosa');
    if (document.activeElement === inputDiagnosa) {
        const hasilDiagnosa = document.getElementById('hasil-pencarian-diagnosa');
        if (!hasilDiagnosa) return;
        
        const items = hasilDiagnosa.querySelectorAll('.panel-block');
        if (items.length === 0) return;

        let currentIndex = Array.from(items).findIndex(item => item.classList.contains('has-background-info-light'));

        if (e.key === 'ArrowDown') {
            e.preventDefault(); 
            if (currentIndex >= 0) items[currentIndex].classList.remove('has-background-info-light');
            currentIndex = (currentIndex + 1) % items.length;
            items[currentIndex].classList.add('has-background-info-light');
            items[currentIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentIndex >= 0) items[currentIndex].classList.remove('has-background-info-light');
            currentIndex = (currentIndex - 1 + items.length) % items.length;
            items[currentIndex].classList.add('has-background-info-light');
            items[currentIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault(); 
            if (currentIndex >= 0) {
                items[currentIndex].click(); 
            } else if (items.length > 0) {
                items[0].click(); 
            }
        }
    }
});

// Sembunyikan dropdown jika pengguna mengklik area luar
document.addEventListener('click', function(e) {
    if (!e.target.closest('#diagnosa-widget')) {
        const hasilDiagnosa = document.getElementById('hasil-pencarian-diagnosa');
        if (hasilDiagnosa) hasilDiagnosa.innerHTML = '';
    }
    if (!e.target.closest('#obat-widget')) {
        const hasilObat = document.getElementById('hasil-pencarian');
        if (hasilObat) hasilObat.innerHTML = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
