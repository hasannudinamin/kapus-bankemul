<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id_rm = $_GET['id'] ?? null;

if (!$id_rm) {
    die("Kesalahan: ID Rekam Medis tidak valid.");
}

// 1. Ambil Data Rekam Medis, Pasien, Petugas, dan Keuangan
$stmt_rm = $pdo->prepare("
    SELECT rm.*, 
           p.no_rm, p.nama_lengkap, p.tanggal_lahir, p.jenis_kelamin, p.alamat, p.no_telp,
           u.nama_lengkap AS nama_petugas,
           k.id AS keuangan_id, k.biaya_layanan, k.biaya_obat, k.total_tagihan, k.uang_dibayar, k.kembalian, k.status_pembayaran
    FROM rekam_medik rm
    JOIN pasien p ON rm.pasien_id = p.id
    JOIN users u ON rm.user_id = u.id
    LEFT JOIN keuangan k ON rm.id = k.rekam_medik_id
    WHERE rm.id = ?
");
$stmt_rm->execute([$id_rm]);
$data = $stmt_rm->fetch();

if (!$data) {
    die("Kesalahan: Data rekam medis tidak ditemukan.");
}

// Hitung Umur Pasien
$umur = 'Tidak diketahui';
if (!empty($data['tanggal_lahir'])) {
    $birthDate = new DateTime($data['tanggal_lahir']);
    $today = new DateTime('now');
    $umur = $today->diff($birthDate)->y . ' Tahun';
}

// 2. Ambil Data Resep
$stmt_resep = $pdo->prepare("
    SELECT r.*, o.nama_obat, o.satuan 
    FROM resep r 
    JOIN obat o ON r.obat_id = o.id 
    WHERE r.rekam_medik_id = ?
");
$stmt_resep->execute([$id_rm]);
$resep_list = $stmt_resep->fetchAll();

$is_lunas = strtolower($data['status_pembayaran']) === 'lunas';

$page_title = 'Detail Rekam Medis - ' . e($data['no_rm']);
include 'includes/header.php';
?>

<div class="is-flex is-justify-content-space-between is-align-items-center mb-5">
    <h1 class="title is-size-3 mb-0 is-size-5-mobile">
        <span class="icon has-text-info mr-2"><i class="mdi mdi-clipboard-text"></i></span>
        Detail Rekam Medis
    </h1>
    <a href="index.php" class="button is-light">
        <span class="icon"><i class="mdi mdi-arrow-left"></i></span>
        <span>Kembali</span>
    </a>
</div>

<div class="columns is-desktop is-variable is-4">
    <!-- Kolom Kiri: Informasi Klinis & Pasien -->
    <div class="column is-8">
        
        <!-- Identitas Pasien -->
        <div class="box border-top border-info mb-4">
            <h4 class="title is-5 has-text-grey-dark border-bottom pb-2 mb-3">
                <span class="icon"><i class="mdi mdi-account-details"></i></span> Identitas Pasien
            </h4>
            <div class="columns is-mobile is-multiline">
                <div class="column is-6-mobile is-3-desktop">
                    <p class="heading mb-1">No. Rekam Medis</p>
                    <p class="title is-6 has-text-weight-bold"><?= e($data['no_rm']) ?></p>
                </div>
                <div class="column is-6-mobile is-5-desktop">
                    <p class="heading mb-1">Nama Lengkap</p>
                    <p class="title is-6"><?= e($data['nama_lengkap']) ?></p>
                </div>
                <div class="column is-6-mobile is-2-desktop">
                    <p class="heading mb-1">Usia</p>
                    <p class="title is-6"><?= $umur ?></p>
                </div>
                <div class="column is-6-mobile is-2-desktop">
                    <p class="heading mb-1">Kelamin</p>
                    <p class="title is-6"><?= e($data['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></p>
                </div>
            </div>
        </div>

        <!-- Data Klinis -->
        <div class="box border-top border-primary mb-4">
            <div class="is-flex is-justify-content-space-between is-align-items-center border-bottom pb-2 mb-3">
                <h4 class="title is-5 has-text-grey-dark mb-0">
                    <span class="icon"><i class="mdi mdi-stethoscope"></i></span> Data Klinis
                </h4>
                <div class="has-text-right is-size-7 has-text-grey">
                    <p><strong>Tgl Kunjungan:</strong> <?= date('d/m/Y H:i', strtotime($data['tanggal_kunjungan'])) ?></p>
                    <p><strong>Petugas/Dokter:</strong> <?= e($data['nama_petugas']) ?></p>
                </div>
            </div>
            
            <div class="content is-small">
                <h6>Keluhan Utama</h6>
                <p class="has-background-white-bis p-3 rounded" style="border-left: 3px solid #00d1b2;">
                    <?= nl2br(e($data['keluhan'] ?? 'Tidak ada catatan keluhan.')) ?>
                </p>

                <h6>Diagnosa</h6>
                <p class="has-background-white-bis p-3 rounded" style="border-left: 3px solid #3e8ed0;">
                    <?= nl2br(e($data['diagnosa'] ?? 'Belum ada diagnosa.')) ?>
                </p>

                <h6>Tindakan / Anjuran</h6>
                <p class="has-background-white-bis p-3 rounded" style="border-left: 3px solid #485fc7;">
                    <?= nl2br(e($data['tindakan'] ?? 'Belum ada tindakan.')) ?>
                </p>
            </div>
        </div>

        <!-- Tabel Resep Obat -->
        <div class="box border-top border-success">
            <h4 class="title is-5 has-text-grey-dark border-bottom pb-2 mb-3">
                <span class="icon"><i class="mdi mdi-pill"></i></span> Resep Obat
            </h4>
            
            <?php if (empty($resep_list)): ?>
                <p class="has-text-grey is-italic is-size-7">Tidak ada resep obat yang diberikan pada kunjungan ini.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="table is-fullwidth is-narrow is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Nama Obat</th>
                                <th class="has-text-centered">Jumlah</th>
                                <th>Aturan Pakai</th>
                                <th class="has-text-right">Harga Satuan</th>
                                <th class="has-text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resep_list as $resep): 
                                $subtotal = $resep['jumlah'] * $resep['harga_jual'];
                            ?>
                                <tr>
                                    <td class="is-vcentered"><?= e($resep['nama_obat']) ?></td>
                                    <td class="is-vcentered has-text-centered"><?= (int)$resep['jumlah'] ?> <?= e($resep['satuan']) ?></td>
                                    <td class="is-vcentered"><?= e($resep['aturan_pakai']) ?></td>
                                    <td class="is-vcentered has-text-right">Rp <?= number_format($resep['harga_jual'], 0, ',', '.') ?></td>
                                    <td class="is-vcentered has-text-right font-weight-bold">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Kolom Kanan: Keuangan & Aksi -->
    <div class="column is-4">
        <div class="box h-100 is-flex is-flex-direction-column">
            <h4 class="title is-5 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-cash-register"></i></span> Rincian Keuangan
            </h4>
            
            <div class="has-text-centered mb-4">
                <?php if ($is_lunas): ?>
                    <span class="tag is-success is-large is-light w-100 py-4 has-text-weight-bold is-size-5">
                        <span class="icon mr-2"><i class="mdi mdi-check-circle"></i></span> LUNAS
                    </span>
                <?php else: ?>
                    <span class="tag is-warning is-large is-light w-100 py-4 has-text-weight-bold is-size-5">
                        <span class="icon mr-2"><i class="mdi mdi-clock-outline"></i></span> BELUM BAYAR
                    </span>
                <?php endif; ?>
            </div>

            <div class="is-flex-grow-1">
                <div class="is-flex is-justify-content-space-between mb-2 is-size-6">
                    <span class="has-text-grey">Biaya Layanan/Tindakan</span>
                    <strong>Rp <?= number_format($data['biaya_layanan'] ?? 0, 0, ',', '.') ?></strong>
                </div>
                <div class="is-flex is-justify-content-space-between mb-4 is-size-6">
                    <span class="has-text-grey">Total Biaya Obat</span>
                    <strong>Rp <?= number_format($data['biaya_obat'] ?? 0, 0, ',', '.') ?></strong>
                </div>
                
                <hr class="my-2">
                
                <div class="is-flex is-justify-content-space-between mb-4 is-size-5 has-text-weight-bold">
                    <span>Total Tagihan</span>
                    <span class="has-text-primary-dark">Rp <?= number_format($data['total_tagihan'] ?? 0, 0, ',', '.') ?></span>
                </div>

                <?php if ($is_lunas): ?>
                    <div class="is-flex is-justify-content-space-between mb-2 is-size-7">
                        <span class="has-text-grey">Uang Dibayar</span>
                        <strong class="has-text-success">Rp <?= number_format($data['uang_dibayar'] ?? 0, 0, ',', '.') ?></strong>
                    </div>
                    <div class="is-flex is-justify-content-space-between mb-2 is-size-7">
                        <span class="has-text-grey">Kembalian</span>
                        <strong>Rp <?= number_format($data['kembalian'] ?? 0, 0, ',', '.') ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-5 border-top pt-4">
                <?php if ($is_lunas): ?>
                    <a href="cetak-struk.php?id_rm=<?= e($data['id']) ?>" target="_blank" class="button is-info is-fullwidth mb-2">
                        <span class="icon"><i class="mdi mdi-printer"></i></span>
                        <span>Cetak Struk Transaksi</span>
                    </a>
                <?php else: ?>
                    <a href="konfirmasi-biaya.php?id_rm=<?= e($data['id']) ?>" class="button is-primary is-fullwidth is-medium mb-2">
                        <span class="icon"><i class="mdi mdi-cash"></i></span>
                        <span>Proses ke Kasir</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
