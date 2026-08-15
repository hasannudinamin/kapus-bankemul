<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id_rm = $_GET['id_rm'] ?? null;

if (!$id_rm) {
    redirect("pelayanan.php");
}

// Mengambil data kunjungan dan identitas pasien
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

// Mengambil daftar resep beserta informasi stok fisik dari tabel obat
$resep_stmt = $pdo->prepare("
    SELECT r.*, o.kode_obat, o.nama_obat, o.satuan, o.stok, o.harga_satuan 
    FROM resep r
    JOIN obat o ON r.obat_id = o.id
    WHERE r.rekam_medik_id = ?
    ORDER BY r.id ASC
");
$resep_stmt->execute([$id_rm]);
$resep_list = $resep_stmt->fetchAll();

$umur = hitung_umur($kunjungan['tanggal_lahir'], 'Tahun');
$page_title = 'Validasi Farmasi: ' . e($kunjungan['nama_lengkap']);
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 mb-1 is-size-5-mobile">
            <span class="icon has-text-link mr-2">
                <i class="mdi mdi-pill"></i>
            </span>
            <span>Validasi Farmasi</span>
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
    <!-- KOLOM KIRI: Informasi Pasien & Catatan Klinis -->
    <div class="column is-4-desktop">
        <div class="box box-desktop border-link-top">
            <h4 class="title is-6 mb-4 has-text-grey-dark border-bottom pb-2">
                <span class="icon"><i class="mdi mdi-account-details-outline"></i></span>
                Ringkasan Kunjungan
            </h4>
            
            <table class="table is-fullwidth is-narrow is-borderless mb-4">
                <tbody>
                    <tr>
                        <td width="35%" class="has-text-grey">Waktu</td>
                        <td class="has-text-weight-medium"><?= e(date('d/m/Y H:i', strtotime($kunjungan['tanggal_kunjungan']))) ?></td>
                    </tr>
                    <tr>
                        <td class="has-text-grey">Usia</td>
                        <td class="has-text-weight-medium"><?= $umur ?></td>
                    </tr>
                    <tr>
                        <td class="has-text-grey">Gender</td>
                        <td class="has-text-weight-medium"><?= $kunjungan['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="content is-small">
                <p class="mb-1 has-text-grey"><strong>Diagnosa (ICD-10):</strong></p>
                <div class="notification is-light is-info py-2 px-3 mb-3">
                    <?= !empty($kunjungan['diagnosa']) ? e($kunjungan['diagnosa']) : '<span class="is-italic">Tidak ada catatan diagnosa.</span>' ?>
                </div>

                <p class="mb-1 has-text-grey"><strong>Tindakan Medis:</strong></p>
                <div class="notification is-light is-info py-2 px-3 mb-0">
                    <?= !empty($kunjungan['tindakan']) ? nl2br(e($kunjungan['tindakan'])) : '<span class="is-italic">Tidak ada tindakan.</span>' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN: Daftar Resep & Validasi Stok -->
    <div class="column is-8-desktop">
        <div class="box box-desktop">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-4 pb-2 border-bottom">
                <h4 class="title is-5 mb-0 has-text-grey-dark">
                    <span class="icon"><i class="mdi mdi-format-list-checks"></i></span>
                    Daftar Persiapan Obat
                </h4>
                <span class="tag is-link is-light">
                    Status: <?= e(ucfirst($kunjungan['status_layanan'])) ?>
                </span>
            </div>

            <div class="table-container mb-5">
                <table class="table is-fullwidth is-striped is-hoverable is-bordered">
                    <thead class="has-background-white-ter">
                        <tr>
                            <th width="35%">Nama Obat</th>
                            <th width="25%">Aturan Pakai</th>
                            <th width="15%" class="has-text-centered">Permintaan</th>
                            <th width="10%" class="has-text-centered">Stok Fisik</th>
                            <th width="15%" class="has-text-centered">Status Validasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $can_process = true;
                        // $total_biaya_obat = 0;

                        if (empty($resep_list)): 
                        ?>
                            <tr>
                                <td colspan="5" class="has-text-centered py-4 has-text-grey is-italic">
                                    Dokter tidak meresepkan obat untuk kunjungan ini.
                                </td>
                            </tr>
                        <?php 
                        else:
                            foreach ($resep_list as $resep) : 
                                $jml_diminta = (int)$resep['jumlah'];
                                $stok_tersedia = (int)$resep['stok'];
                                $subtotal = $jml_diminta * (float)$resep['harga_satuan'];
                                // $total_biaya_obat += $subtotal;

                                // Logika validasi stok
                                $is_sufficient = $stok_tersedia >= $jml_diminta;
                                if (!$is_sufficient) {
                                    $can_process = false;
                                }

                                $status_class = $is_sufficient ? 'has-text-success' : 'has-text-danger has-text-weight-bold';
                                $status_icon = $is_sufficient ? 'mdi-check-circle' : 'mdi-alert-circle';
                                $status_text = $is_sufficient ? 'Siap' : 'Stok Kurang';
                        ?>
                            <tr>
                                <td class="is-vcentered">
                                    <p class="has-text-weight-bold mb-0"><?= e($resep['nama_obat']) ?></p>
                                    <p class="is-size-7 has-text-grey"><?= e($resep['kode_obat']) ?></p>
                                </td>
                                <td class="is-vcentered"><?= e($resep['aturan_pakai']) ?></td>
                                <td class="is-vcentered has-text-centered has-text-weight-bold">
                                    <?= $jml_diminta ?> <?= e($resep['satuan']) ?>
                                </td>
                                <td class="is-vcentered has-text-centered <?= !$is_sufficient ? 'has-text-danger has-text-weight-bold' : '' ?>">
                                    <?= $stok_tersedia ?>
                                </td>
                                <td class="is-vcentered has-text-centered <?= $status_class ?>">
                                    <span class="icon is-small mr-1"><i class="mdi <?= $status_icon ?>"></i></span>
                                    <span><?= $status_text ?></span>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Tampilan Peringatan Jika Stok Kurang -->
            <?php if (!$can_process): ?>
                <div class="notification is-danger is-light">
                    <span class="icon mr-2"><i class="mdi mdi-alert"></i></span>
                    <strong>Validasi Gagal:</strong> Terdapat obat yang jumlah permintaannya melebihi stok fisik di gudang. Silakan hubungi dokter yang bertugas untuk merevisi resep melalui halaman pemeriksaan pasien, atau sesuaikan stok di manajemen obat.
                </div>
            <?php endif; ?>

            <!-- Form Eksekusi Farmasi -->
            <form action="aksi-farmasi.php" method="POST" class="mt-4">
                <input type="hidden" name="id_rm" value="<?= e($id_rm) ?>">
                
                <div class="is-flex is-justify-content-flex-end">
                    <button type="submit" class="button is-link is-medium" <?= !$can_process ? 'disabled' : '' ?> onclick="return confirm('Apakah Anda yakin semua obat telah disiapkan dengan benar? Tindakan ini akan memotong stok gudang secara permanen.');">
                        <span class="icon"><i class="mdi mdi-package-check"></i></span>
                        <span>Konfirmasi & Serahkan ke Kasir</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
