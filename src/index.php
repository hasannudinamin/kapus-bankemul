<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// 1. Kueri Panel Metrik Utama (KPIs)
// Total Kunjungan Hari Ini
$stmt_kunjungan = $pdo->query("SELECT COUNT(*) FROM rekam_medik WHERE date(tanggal_kunjungan) = date('now', 'localtime')");
$kunjungan_hari_ini = $stmt_kunjungan->fetchColumn() ?: 0;

// Pasien Selesai Hari Ini
$stmt_selesai = $pdo->query("SELECT COUNT(*) FROM rekam_medik WHERE status_layanan = 'selesai' AND date(tanggal_kunjungan) = date('now', 'localtime')");
$pasien_selesai = $stmt_selesai->fetchColumn() ?: 0;

// Peringatan Stok Obat (Stok di bawah 10)
$stmt_stok = $pdo->query("SELECT COUNT(*) FROM obat WHERE stok < 10");
$stok_menipis = $stmt_stok->fetchColumn() ?: 0;

// Pendapatan Hari Ini
$stmt_income = $pdo->query("
    SELECT SUM(k.total_tagihan) 
    FROM keuangan k 
    JOIN rekam_medik rm ON k.rekam_medik_id = rm.id 
    WHERE k.status_pembayaran = 'lunas' AND date(rm.tanggal_kunjungan) = date('now', 'localtime')
");
$pendapatan_hari_ini = $stmt_income->fetchColumn() ?: 0;


// 2. Kueri Grafik (Chart.js)
// Tren Kunjungan 7 Hari Terakhir
$chart_labels = [];
$chart_values = [];
$raw_chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date_str));
    $raw_chart_data[$date_str] = 0;
}

$stmt_trend = $pdo->query("
    SELECT date(tanggal_kunjungan) as tgl, COUNT(*) as total
    FROM rekam_medik
    WHERE date(tanggal_kunjungan) >= date('now', '-6 days', 'localtime')
    GROUP BY date(tanggal_kunjungan)
");
while ($row = $stmt_trend->fetch()) {
    if (isset($raw_chart_data[$row['tgl']])) {
        $raw_chart_data[$row['tgl']] = (int)$row['total'];
    }
}
$chart_values = array_values($raw_chart_data);

// Proporsi Status Layanan Hari Ini
$status_counts = [
    'menunggu' => 0,
    'pemeriksaan' => 0,
    'farmasi' => 0,
    'pembayaran' => 0,
    'selesai' => 0
];
$stmt_status = $pdo->query("SELECT status_layanan, COUNT(*) as total FROM rekam_medik WHERE date(tanggal_kunjungan) = date('now', 'localtime') GROUP BY status_layanan");
while ($row = $stmt_status->fetch()) {
    $stat = strtolower($row['status_layanan']);
    if (isset($status_counts[$stat])) {
        $status_counts[$stat] = (int)$row['total'];
    }
}


// 3. Kueri Antrean Pasien Hari Ini
// Diurutkan berdasarkan alur layanan agar yang masih awal antrean berada di atas
$stmt_antrean = $pdo->query("
    SELECT rm.id AS rekam_medik_id, rm.tanggal_kunjungan, rm.status_layanan, rm.keluhan, 
           p.no_rm, p.nama_lengkap 
    FROM rekam_medik rm 
    JOIN pasien p ON rm.pasien_id = p.id 
    WHERE date(rm.tanggal_kunjungan) = date('now', 'localtime') 
    ORDER BY 
        CASE rm.status_layanan
            WHEN 'menunggu' THEN 1
            WHEN 'pemeriksaan' THEN 2
            WHEN 'farmasi' THEN 3
            WHEN 'pembayaran' THEN 4
            WHEN 'selesai' THEN 5
            ELSE 6
        END ASC,
        rm.tanggal_kunjungan ASC
");
$antrean = $stmt_antrean->fetchAll();


// 4. Kueri Obat Kritis
$stmt_obat_kritis = $pdo->query("SELECT nama_obat, stok, satuan FROM obat WHERE stok < 10 ORDER BY stok ASC LIMIT 5");
$obat_kritis = $stmt_obat_kritis->fetchAll();


$page_title = 'Dashboard Operasional';
include 'includes/header.php';

// Helper fungsi warna tag Bulma untuk status layanan
function getStatusTag($status) {
    switch (strtolower($status)) {
        case 'menunggu': return '<span class="tag is-warning is-light">Menunggu</span>';
        case 'pemeriksaan': return '<span class="tag is-info is-light">Pemeriksaan</span>';
        case 'farmasi': return '<span class="tag is-primary is-light">Farmasi</span>';
        case 'pembayaran': return '<span class="tag is-link is-light">Pembayaran</span>';
        case 'selesai': return '<span class="tag is-success is-light">Selesai</span>';
        default: return '<span class="tag is-light">' . e($status) . '</span>';
    }
}

// Helper fungsi tombol aksi berdasarkan status
function getActionButton($status, $id_rm) {
    switch (strtolower($status)) {
        case 'menunggu': 
            return '<a href="pemeriksaan.php?id=' . $id_rm . '" class="button is-small is-info" title="Mulai Pemeriksaan"><span class="icon"><i class="mdi mdi-stethoscope"></i></span> <span>Periksa</span></a>';
        case 'pemeriksaan': 
            return '<a href="resep.php?id=' . $id_rm . '" class="button is-small is-primary" title="Input Resep & Tindakan"><span class="icon"><i class="mdi mdi-pill"></i></span> <span>Resep</span></a>';
        case 'farmasi': 
            return '<a href="farmasi-proses.php?id=' . $id_rm . '" class="button is-small is-primary is-light" title="Proses Obat"><span class="icon"><i class="mdi mdi-medical-bag"></i></span> <span>Siapkan Obat</span></a>';
        case 'pembayaran': 
            return '<a href="konfirmasi-biaya.php?id_rm=' . $id_rm . '" class="button is-small is-link" title="Ke Kasir"><span class="icon"><i class="mdi mdi-cash-register"></i></span> <span>Kasir</span></a>';
        case 'selesai': 
            return '<a href="detail-rm.php?id=' . $id_rm . '" class="button is-small is-light" title="Lihat Rekam Medik"><span class="icon"><i class="mdi mdi-eye"></i></span></a>';
        default: return '';
    }
}
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="is-flex is-justify-content-space-between is-align-items-center mb-5">
    <h1 class="title is-size-3 mb-0 is-size-5-mobile">
        <span class="icon has-text-primary mr-2"><i class="mdi mdi-monitor-dashboard"></i></span>
        Dashboard Operasional
    </h1>
    <div class="buttons">
        <a href="pasien-tambah.php" class="button is-info is-light">
            <span class="icon"><i class="mdi mdi-account-plus"></i></span>
            <span>Pasien Baru</span>
        </a>
        <a href="kunjungan-baru.php" class="button is-primary">
            <span class="icon"><i class="mdi mdi-plus-circle"></i></span>
            <span>Buat Antrean</span>
        </a>
    </div>
</div>

<!-- Panel Metrik (KPIs) -->
<div class="columns is-multiline is-mobile mb-4">
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-info-light border-info-top h-100">
            <p class="heading has-text-info has-text-weight-bold">Total Kunjungan Hari Ini</p>
            <p class="title is-4 has-text-info-dark"><?= number_format($kunjungan_hari_ini) ?> <span class="is-size-6 has-text-weight-normal">Pasien</span></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-success-light border-success-top h-100">
            <p class="heading has-text-success has-text-weight-bold">Pasien Selesai</p>
            <p class="title is-4 has-text-success-dark"><?= number_format($pasien_selesai) ?> <span class="is-size-6 has-text-weight-normal">Pasien</span></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box <?= $stok_menipis > 0 ? 'has-background-danger-light border-danger-top' : 'has-background-grey-light' ?> h-100">
            <p class="heading <?= $stok_menipis > 0 ? 'has-text-danger-dark' : 'has-text-grey-dark' ?> has-text-weight-bold">Peringatan Stok Obat</p>
            <p class="title is-4 <?= $stok_menipis > 0 ? 'has-text-danger-dark' : 'has-text-grey-dark' ?>"><?= number_format($stok_menipis) ?> <span class="is-size-6 has-text-weight-normal">Item Menipis</span></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-primary-light border-primary-top h-100">
            <p class="heading has-text-primary has-text-weight-bold">Pendapatan Hari Ini</p>
            <p class="title is-4 has-text-primary-dark">Rp <?= number_format($pendapatan_hari_ini, 0, ',', '.') ?></p>
        </div>
    </div>
</div>

<div class="columns is-desktop is-variable is-4 mb-5">
    <!-- Tabel Antrean Kiri -->
    <div class="column is-8">
        <div class="box h-100">
            <h4 class="title is-5 mb-4 has-text-grey-dark border-bottom pb-2">
                <span class="icon"><i class="mdi mdi-clipboard-text-clock"></i></span> Antrean Pasien Hari Ini
            </h4>
            
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pasien (No. RM)</th>
                            <th>Keluhan</th>
                            <th class="has-text-centered">Status</th>
                            <th class="has-text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($antrean)): ?>
                            <tr>
                                <td colspan="5" class="has-text-centered has-text-grey is-italic py-4">Belum ada pasien terdaftar hari ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($antrean as $antre): ?>
                                <tr>
                                    <td class="is-vcentered is-size-7"><?= date('H:i', strtotime($antre['tanggal_kunjungan'])) ?></td>
                                    <td class="is-vcentered">
                                        <strong><?= e($antre['nama_lengkap']) ?></strong><br>
                                        <span class="is-size-7 has-text-grey"><?= e($antre['no_rm']) ?></span>
                                    </td>
                                    <td class="is-vcentered is-size-7" style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($antre['keluhan']) ?>">
                                        <?= e($antre['keluhan']) ?>
                                    </td>
                                    <td class="is-vcentered has-text-centered">
                                        <?= getStatusTag($antre['status_layanan']) ?>
                                    </td>
                                    <td class="is-vcentered has-text-right">
                                        <?= getActionButton($antre['status_layanan'], $antre['rekam_medik_id']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Peringatan Stok & Grafik Donat -->
    <div class="column is-4">
        <!-- Panel Stok Obat -->
        <div class="box mb-4">
            <h4 class="title is-6 mb-3 has-text-danger border-bottom pb-2">
                <span class="icon"><i class="mdi mdi-alert-circle-outline"></i></span> Stok Menipis (< 10)
            </h4>
            <?php if (empty($obat_kritis)): ?>
                <p class="has-text-grey is-size-7 is-italic">Ketersediaan stok obat aman.</p>
            <?php else: ?>
                <table class="table is-fullwidth is-narrow is-size-7">
                    <tbody>
                        <?php foreach ($obat_kritis as $obat): ?>
                            <tr>
                                <td><?= e($obat['nama_obat']) ?></td>
                                <td class="has-text-right">
                                    <strong class="<?= $obat['stok'] == 0 ? 'has-text-danger' : 'has-text-warning-dark' ?>">
                                        <?= (int)$obat['stok'] ?> <?= e($obat['satuan']) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Grafik Proporsi Status -->
        <div class="box">
            <h4 class="title is-6 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-chart-donut"></i></span> Status Pelayanan Hari Ini
            </h4>
            <div style="position: relative; height: 180px; width: 100%; display: flex; justify-content: center;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Garis (Tren Kunjungan Bawah) -->
<div class="box">
    <h4 class="title is-6 has-text-grey-dark border-bottom pb-2 mb-4">
        <span class="icon"><i class="mdi mdi-chart-areaspline"></i></span> Tren Kunjungan 7 Hari Terakhir
    </h4>
    <div style="position: relative; height: 250px; width: 100%;">
        <canvas id="trendChart"></canvas>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Warna khusus untuk status chart
    const colors = {
        menunggu: '#ffe08a',    // Bulma Warning
        pemeriksaan: '#3e8ed0', // Bulma Info
        farmasi: '#00d1b2',     // Bulma Primary
        pembayaran: '#485fc7',  // Bulma Link
        selesai: '#48c774'      // Bulma Success
    };

    // 1. Doughnut Chart (Status Hari Ini)
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Menunggu', 'Pemeriksaan', 'Farmasi', 'Pembayaran', 'Selesai'],
            datasets: [{
                data: [
                    <?= $status_counts['menunggu'] ?>, 
                    <?= $status_counts['pemeriksaan'] ?>, 
                    <?= $status_counts['farmasi'] ?>, 
                    <?= $status_counts['pembayaran'] ?>, 
                    <?= $status_counts['selesai'] ?>
                ],
                backgroundColor: [
                    colors.menunggu, colors.pemeriksaan, colors.farmasi, colors.pembayaran, colors.selesai
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12 } }
            }
        }
    });

    // 2. Line Chart (Tren Kunjungan 7 Hari)
    const colorInfo = '#3e8ed0';
    const colorInfoLight = 'rgba(62, 142, 208, 0.2)';
    
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Jumlah Kunjungan',
                data: <?= json_encode($chart_values) ?>,
                backgroundColor: colorInfoLight,
                borderColor: colorInfo,
                borderWidth: 2,
                pointBackgroundColor: colorInfo,
                pointBorderColor: '#fff',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
