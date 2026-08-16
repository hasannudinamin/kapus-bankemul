<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect("daftar-pasien.php");
}

// Mengambil data utama pasien
$stmt = $pdo->prepare("SELECT * FROM pasien WHERE id = ?");
$stmt->execute([$id]);
$pasien = $stmt->fetch();

if (!$pasien) {
    die("Data pasien tidak ditemukan.");
}

// Mengambil daftar riwayat rekam medis / kunjungan
$rm_stmt = $pdo->prepare("
    SELECT rm.*, u.nama_lengkap AS nama_petugas 
    FROM rekam_medik rm
    JOIN users u ON rm.user_id = u.id
    WHERE rm.pasien_id = ?
    ORDER BY rm.tanggal_kunjungan DESC
");
$rm_stmt->execute([$id]);
$riwayat_kunjungan = $rm_stmt->fetchAll();

$page_title = 'Detail Pasien: ' . e($pasien['nama_lengkap']);
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 mb-1 is-size-5-mobile">
            <span class="icon has-text-primary mr-2">
                <i class="mdi mdi-account-circle"></i>
            </span>
            <span><?= e($pasien['nama_lengkap']) ?></span>
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey mt-0 is-size-7-mobile">
            No. RM: <strong><?= !empty($pasien['no_rm']) ? e($pasien['no_rm']) : '-' ?></strong>
        </h2>
    </div>
    <div class="column is-narrow buttons is-narrow-mobile">
        <div class="is-hidden-desktop">
            <a class="button is-light is-responsive-icon" href="daftar-pasien.php">
                <span class="icon"><i class="mdi mdi-arrow-left"></i></span>
            </a>

            <?php if ($_SESSION['role'] !== 'pegawai') : ?>
            <a href="form-pasien.php?id=<?= e($pasien['id']) ?>" class="button is-warning is-responsive-icon">
                <span class="icon"><i class="mdi mdi-pencil"></i></span>
            </a>
            <?php endif ?>
        </div>
        <div class="is-hidden-touch">
            <a class="button is-light" href="daftar-pasien.php">
                <span class="icon"><i class="mdi mdi-arrow-left"></i></span>
                <span>Kembali</span>
            </a>

            <?php if ($_SESSION['role'] !== 'pegawai') : ?>
            <a href="form-pasien.php?id=<?= e($pasien['id']) ?>" class="button is-warning">
                <span class="icon"><i class="mdi mdi-pencil"></i></span>
                <span>Edit Pasien</span>
            </a>
            <?php endif ?>
        </div>
    </div>
</div>

<div class="columns is-desktop">
    <div class="column is-4">
        <div class="box mb-5 box-desktop">
            <h4 class="title is-5 mb-4 has-text-grey-dark">
                <span class="icon"><i class="mdi mdi-information-outline"></i></span>
                Informasi Pasien
            </h4>
            
            <div class="mb-3">
                <p class="heading mb-1 has-text-grey">Nomor Telepon</p>
                <p class="subtitle is-6">
                    <span class="icon has-text-grey-light"><i class="mdi mdi-phone"></i></span>
                    <?= !empty($pasien['no_telp']) ? e($pasien['no_telp']) : '-' ?>
                 </p>
            </div>

            <div class="mb-3">
                <p class="heading mb-1 has-text-grey">Tanggal Lahir</p>
                <p class="subtitle is-6">
                    <span class="icon has-text-grey-light"><i class="mdi mdi-calendar-month"></i></span>
                    <?= !empty($pasien['tanggal_lahir']) ? e($pasien['tanggal_lahir']) : '-' ?>
                 </p>
            </div>

            <div class="mb-3">
                <p class="heading mb-1 has-text-grey">Usia</p>
                <p class="subtitle is-6">
                    <span class="icon has-text-grey-light"><i class="mdi mdi-timer-sand"></i></span>
                    <?= !empty($pasien['tanggal_lahir']) ? hitung_umur(e($pasien['tanggal_lahir']), 'Tahun') : '-' ?>
                 </p>
            </div>

            <div class="mb-3">
                <p class="heading mb-1 has-text-grey">Jenis Kelamin</p>
                <p class="subtitle is-6">
                    <span class="icon has-text-grey-light"><i class="mdi <?= $pasien['jenis_kelamin'] === 'P' ? 'mdi-gender-female' : 'mdi-gender-male' ?>"></i></span>
                    <?= isset(JENIS_KELAMIN[$pasien['jenis_kelamin']]) ? JENIS_KELAMIN[$pasien['jenis_kelamin']] : '-' ?>
                 </p>
            </div>

            <div class="mb-3">
                <p class="heading mb-1 has-text-grey">Alamat Lengkap</p>
                <p class="subtitle is-6">
                    <span class="icon has-text-grey-light"><i class="mdi mdi-map-marker"></i></span>
                    <?= !empty($pasien['alamat']) ? e($pasien['alamat']) : '-' ?>
                </p>
            </div>
        </div>
    </div>

    <div class="column is-8">
        <div class="box box-desktop mb-5">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-4">
                <h4 class="title is-5 mb-0 has-text-grey-dark">
                    <span class="icon"><i class="mdi mdi-history"></i></span>
                    Riwayat Rekam Medis
                </h4>
                
                <form action="aksi-kunjungan.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="pasien_id" value="<?= e($pasien['id']) ?>">
                    <button type="submit" class="button is-primary is-small">
                        <span class="icon"><i class="mdi mdi-plus-circle"></i></span>
                        <span>Daftarkan Kunjungan</span>
                    </button>
                </form>
            </div>

            <?php if (empty($riwayat_kunjungan)) : ?>
                <div class="notification is-light is-size-7 has-text-grey has-text-centered">
                    Belum ada riwayat kunjungan atau rekam medis untuk pasien ini.
                </div>
            <?php else : ?>
                <div class="content is-small pl-3" style="max-height: 600px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach ($riwayat_kunjungan as $rm) : 
                        $waktu = date('d/m/Y H:i', strtotime($rm['tanggal_kunjungan']));
                        
                        // Menentukan warna tag berdasarkan status layanan
                        $tag_color = 'is-warning';
                        if ($rm['status_layanan'] === 'selesai') $tag_color = 'is-success';
                        elseif ($rm['status_layanan'] === 'pemeriksaan') $tag_color = 'is-info';
                    ?>
                        <div class="pb-5" style="border-left: 2px solid #e0e0e0; padding-left: 20px; position: relative;">
                            <span class="icon is-small has-text-primary" style="position: absolute; left: -9px; top: 7px; background-color: #fff;">
                                <i class="mdi mdi-record-circle mdi-24px"></i>
                            </span>

                            <div class="is-flex is-justify-content-space-between is-align-items-center mb-2">
                                <div>
                                    <span class="has-text-grey-dark has-text-weight-bold is-size-6 mr-2"><?= $waktu ?></span>
                                    <span class="tag <?= $tag_color ?> is-light"><?= e(ucfirst($rm['status_layanan'])) ?></span>
                                </div>
                                
                                <div class="buttons is-right mb-0">
                                    <?php if ($rm['status_layanan'] !== 'selesai') : ?>
                                        <a href="pemeriksaan.php?id_rm=<?= e($rm['id']) ?>" class="button is-small is-info is-light">
                                            <span class="icon"><i class="mdi mdi-stethoscope"></i></span>
                                            <span>Proses</span>
                                        </a>
                                    <?php else : ?>
                                        <a href="konfirmasi-biaya.php?id_rm=<?= e($rm['id']) ?>" class="button is-small is-success is-light">
                                            <span class="icon"><i class="mdi mdi-cash"></i></span>
                                            <span>Biaya</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="box has-background-white-bis p-3 shadow-none border" style="border: 1px solid #f0f0f0;">
                                <p class="mb-1"><strong>Dokter/Petugas:</strong> <?= e($rm['nama_petugas']) ?></p>
                                <p class="mb-1"><strong>Keluhan:</strong> <?= !empty($rm['keluhan']) ? nl2br(e($rm['keluhan'])) : '<em class="has-text-grey-light">Belum diisi</em>' ?></p>
                                <p class="mb-1"><strong>Diagnosa:</strong> <?= !empty($rm['diagnosa']) ? e($rm['diagnosa']) : '<em class="has-text-grey-light">Belum diisi</em>' ?></p>
                                <p class="mb-0"><strong>Tindakan:</strong> <?= !empty($rm['tindakan']) ? nl2br(e($rm['tindakan'])) : '<em class="has-text-grey-light">Belum diisi</em>' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
