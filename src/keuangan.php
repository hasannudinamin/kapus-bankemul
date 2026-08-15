<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// 1. Menangkap parameter filter (Default: Bulan ini)
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');
$search = $_GET['q'] ?? '';

// 2. Kueri untuk Panel Metrik Utama (KPIs)
// Pendapatan Hari Ini
$stmt_today = $pdo->query("SELECT SUM(total_tagihan) FROM keuangan k JOIN rekam_medik rm ON k.rekam_medik_id = rm.id WHERE k.status_pembayaran = 'lunas' AND date(rm.tanggal_kunjungan) = date('now', 'localtime')");
$income_today = $stmt_today->fetchColumn() ?: 0;

// Pendapatan Bulan Ini
$stmt_month = $pdo->query("SELECT SUM(total_tagihan) FROM keuangan k JOIN rekam_medik rm ON k.rekam_medik_id = rm.id WHERE k.status_pembayaran = 'lunas' AND strftime('%Y-%m', rm.tanggal_kunjungan) = strftime('%Y-%m', 'now', 'localtime')");
$income_month = $stmt_month->fetchColumn() ?: 0;

// Tagihan Tertunda (Piutang)
$stmt_pending = $pdo->query("SELECT SUM(total_tagihan) FROM keuangan WHERE status_pembayaran = 'belum_bayar'");
$income_pending = $stmt_pending->fetchColumn() ?: 0;

// Pasien Selesai Hari Ini
$stmt_patients = $pdo->query("SELECT COUNT(*) FROM rekam_medik WHERE status_layanan = 'selesai' AND date(tanggal_kunjungan) = date('now', 'localtime')");
$patients_today = $stmt_patients->fetchColumn() ?: 0;

// 3. Kueri Proporsi Pendapatan (Bulan Ini) untuk Doughnut Chart
$stmt_breakdown = $pdo->query("SELECT SUM(biaya_layanan) as total_jasa, SUM(biaya_obat) as total_obat FROM keuangan k JOIN rekam_medik rm ON k.rekam_medik_id = rm.id WHERE k.status_pembayaran = 'lunas' AND strftime('%Y-%m', rm.tanggal_kunjungan) = strftime('%Y-%m', 'now', 'localtime')");
$breakdown = $stmt_breakdown->fetch();
$total_jasa = (float)($breakdown['total_jasa'] ?? 0);
$total_obat = (float)($breakdown['total_obat'] ?? 0);

// 4. Kueri Tren Pendapatan 7 Hari Terakhir untuk Line Chart
$chart_labels = [];
$chart_values = [];
$raw_chart_data = [];

// Inisialisasi array 7 hari ke belakang (memastikan hari dengan 0 pendapatan tetap tampil)
for ($i = 6; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date_str));
    $raw_chart_data[$date_str] = 0;
}

$stmt_trend = $pdo->query("
    SELECT date(rm.tanggal_kunjungan) as tgl, SUM(k.total_tagihan) as total
    FROM keuangan k
    JOIN rekam_medik rm ON k.rekam_medik_id = rm.id
    WHERE k.status_pembayaran = 'lunas' AND date(rm.tanggal_kunjungan) >= date('now', '-6 days', 'localtime')
    GROUP BY date(rm.tanggal_kunjungan)
");
while ($row = $stmt_trend->fetch()) {
    if (isset($raw_chart_data[$row['tgl']])) {
        $raw_chart_data[$row['tgl']] = (float)$row['total'];
    }
}
$chart_values = array_values($raw_chart_data);

// 5. Kueri Tabel Buku Besar / Transaksi
$sql_trans = "
    SELECT k.id AS keuangan_id, rm.id AS id_rm, rm.tanggal_kunjungan, p.nama_lengkap, p.no_rm, 
           k.biaya_layanan, k.biaya_obat, k.total_tagihan, k.status_pembayaran 
    FROM keuangan k 
    JOIN rekam_medik rm ON k.rekam_medik_id = rm.id 
    JOIN pasien p ON rm.pasien_id = p.id 
    WHERE date(rm.tanggal_kunjungan) BETWEEN ? AND ?
";
$params = [$start_date, $end_date];

if (!empty($search)) {
    $sql_trans .= " AND (p.nama_lengkap LIKE ? OR p.no_rm LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql_trans .= " ORDER BY rm.tanggal_kunjungan DESC LIMIT 100";

$stmt_trans = $pdo->prepare($sql_trans);
$stmt_trans->execute($params);
$transactions = $stmt_trans->fetchAll();

// --- BLOK HTMX: Mengembalikan HANYA isi tabel jika request berasal dari HTMX ---
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    if (empty($transactions)) {
        echo '<tr><td colspan="8" class="has-text-centered has-text-grey is-italic py-4">Tidak ada transaksi ditemukan pada rentang waktu/pencarian ini.</td></tr>';
        exit;
    }
    foreach ($transactions as $trx) {
        $is_lunas = strtolower($trx['status_pembayaran']) === 'lunas';
        echo '<tr>';
        echo '<td class="is-vcentered">' . date('d/m/Y H:i', strtotime($trx['tanggal_kunjungan'])) . '</td>';
        echo '<td class="is-vcentered">TRX-' . str_pad($trx['keuangan_id'], 5, '0', STR_PAD_LEFT) . '</td>';
        echo '<td class="is-vcentered"><strong>' . e($trx['nama_lengkap']) . '</strong><br><span class="is-size-7 has-text-grey">' . e($trx['no_rm']) . '</span></td>';
        echo '<td class="is-vcentered has-text-right">Rp ' . number_format($trx['biaya_layanan'], 0, ',', '.') . '</td>';
        echo '<td class="is-vcentered has-text-right">Rp ' . number_format($trx['biaya_obat'], 0, ',', '.') . '</td>';
        echo '<td class="is-vcentered has-text-right has-text-weight-bold">Rp ' . number_format($trx['total_tagihan'], 0, ',', '.') . '</td>';
        echo '<td class="is-vcentered has-text-centered">';
        echo $is_lunas ? '<span class="tag is-success is-light">Lunas</span>' : '<span class="tag is-warning is-light">Belum Bayar</span>';
        echo '</td>';
        echo '<td class="is-vcentered has-text-right">';
        if ($is_lunas) {
            echo '<a href="cetak-struk.php?id_rm=' . e($trx['id_rm']) . '" target="_blank" class="button is-small is-info is-light" title="Cetak Ulang Struk"><span class="icon"><i class="mdi mdi-printer"></i></span></a>';
        } else {
            echo '<a href="konfirmasi-biaya.php?id_rm=' . e($trx['id_rm']) . '" class="button is-small is-primary" title="Proses Kasir">Bayar</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    exit;
}
// --- AKHIR BLOK HTMX ---

$page_title = 'Dashboard Keuangan';
include 'includes/header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="is-flex is-justify-content-space-between is-align-items-center mb-5">
    <h1 class="title is-size-3 mb-0 is-size-5-mobile">
        <span class="icon has-text-primary mr-2"><i class="mdi mdi-finance"></i></span>
        Dashboard Keuangan
    </h1>
</div>

<!-- Panel Metrik (KPIs) -->
<div class="columns is-multiline is-mobile mb-4">
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-primary-light border-primary-top h-100">
            <p class="heading has-text-primary has-text-weight-bold">Pendapatan Hari Ini</p>
            <p class="title is-4 has-text-primary-dark">Rp <?= number_format($income_today, 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-info-light border-info-top h-100">
            <p class="heading has-text-info has-text-weight-bold">Pendapatan Bulan Ini</p>
            <p class="title is-4 has-text-info-dark">Rp <?= number_format($income_month, 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-warning-light border-warning-top h-100">
            <p class="heading has-text-warning-dark has-text-weight-bold">Piutang (Tertunda)</p>
            <p class="title is-4 has-text-warning-dark">Rp <?= number_format($income_pending, 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-success-light border-success-top h-100">
            <p class="heading has-text-success has-text-weight-bold">Pasien Selesai (Hari Ini)</p>
            <p class="title is-4 has-text-success-dark"><?= number_format($patients_today) ?> <span class="is-size-6 has-text-weight-normal">Orang</span></p>
        </div>
    </div>
</div>

<!-- Grafik Visualisasi -->
<div class="columns is-desktop is-variable is-4 mb-5">
    <!-- Tren 7 Hari Terakhir -->
    <div class="column is-8">
        <div class="box h-100">
            <h4 class="title is-6 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-chart-areaspline"></i></span> Tren Pendapatan 7 Hari Terakhir
            </h4>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Breakdown Bulan Ini -->
    <div class="column is-4">
        <div class="box h-100">
            <h4 class="title is-6 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-chart-donut"></i></span> Proporsi Sumber Pendapatan
            </h4>
            <div style="position: relative; height: 220px; width: 100%; display: flex; justify-content: center;">
                <canvas id="breakdownChart"></canvas>
            </div>
            <div class="mt-4">
                <div class="is-flex is-justify-content-space-between is-size-7 mb-2">
                    <span><span class="icon has-text-primary"><i class="mdi mdi-circle"></i></span> Jasa Medis</span>
                    <strong class="has-text-grey-dark">Rp <?= number_format($total_jasa, 0, ',', '.') ?></strong>
                </div>
                <div class="is-flex is-justify-content-space-between is-size-7">
                    <span><span class="icon has-text-info"><i class="mdi mdi-circle"></i></span> Penjualan Obat</span>
                    <strong class="has-text-grey-dark">Rp <?= number_format($total_obat, 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Buku Besar / Filter & Tabel Transaksi -->
<div class="box">
    <div class="is-flex is-flex-wrap-wrap is-justify-content-space-between is-align-items-flex-end mb-4 border-bottom pb-3">
        <h4 class="title is-5 mb-2 has-text-grey-dark">
            <span class="icon"><i class="mdi mdi-book-open-page-variant"></i></span> Riwayat Transaksi
        </h4>
        
        <!-- Form Filter & Pencarian dengan HTMX -->
        <form class="is-flex is-flex-wrap-wrap gap-2" style="gap: 10px;" id="form-filter-keuangan" hx-get="keuangan.php" hx-target="#tbody-transaksi" hx-trigger="submit, change from:input[type='date']">
            <div class="field mb-0">
                <div class="control has-icons-left">
                    <input class="input is-small" type="date" name="start" value="<?= e($start_date) ?>" required title="Tanggal Mulai">
                    <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                </div>
            </div>
            <div class="field mb-0 is-flex is-align-items-center">
                <span class="mx-2 has-text-grey is-size-7">s/d</span>
            </div>
            <div class="field mb-0">
                <div class="control has-icons-left">
                    <input class="input is-small" type="date" name="end" value="<?= e($end_date) ?>" required title="Tanggal Akhir">
                    <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                </div>
            </div>
            <div class="field mb-0">
                <div class="control has-icons-left">
                    <!-- Trigger pencarian live delay 500ms -->
                    <input class="input is-small" type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Pasien / RM..." autocomplete="off" hx-get="keuangan.php" hx-include="#form-filter-keuangan" hx-target="#tbody-transaksi" hx-trigger="input changed delay:500ms, search">
                    <span class="icon is-small is-left"><i class="mdi mdi-magnify"></i></span>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="table is-fullwidth is-striped is-hoverable is-narrow">
            <thead>
                <tr>
                    <th>Tanggal Waktu</th>
                    <th>No. Transaksi</th>
                    <th>Pasien</th>
                    <th class="has-text-right">Jasa Medis</th>
                    <th class="has-text-right">Biaya Obat</th>
                    <th class="has-text-right">Total Tagihan</th>
                    <th class="has-text-centered">Status</th>
                    <th class="has-text-right">Aksi</th>
                </tr>
            </thead>
            <!-- Area Target HTMX -->
            <tbody id="tbody-transaksi">
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="has-text-centered has-text-grey is-italic py-4">Tidak ada transaksi ditemukan pada rentang waktu ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $trx): 
                        $is_lunas = strtolower($trx['status_pembayaran']) === 'lunas';
                    ?>
                        <tr>
                            <td class="is-vcentered"><?= date('d/m/Y H:i', strtotime($trx['tanggal_kunjungan'])) ?></td>
                            <td class="is-vcentered">TRX-<?= str_pad($trx['keuangan_id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="is-vcentered">
                                <strong><?= e($trx['nama_lengkap']) ?></strong><br>
                                <span class="is-size-7 has-text-grey"><?= e($trx['no_rm']) ?></span>
                            </td>
                            <td class="is-vcentered has-text-right">Rp <?= number_format($trx['biaya_layanan'], 0, ',', '.') ?></td>
                            <td class="is-vcentered has-text-right">Rp <?= number_format($trx['biaya_obat'], 0, ',', '.') ?></td>
                            <td class="is-vcentered has-text-right has-text-weight-bold">Rp <?= number_format($trx['total_tagihan'], 0, ',', '.') ?></td>
                            <td class="is-vcentered has-text-centered">
                                <?= $is_lunas ? '<span class="tag is-success is-light">Lunas</span>' : '<span class="tag is-warning is-light">Belum Bayar</span>' ?>
                            </td>
                            <td class="is-vcentered has-text-right">
                                <?php if ($is_lunas): ?>
                                    <a href="cetak-struk.php?id_rm=<?= e($trx['id_rm']) ?>" target="_blank" class="button is-small is-info is-light" title="Cetak Ulang Struk">
                                        <span class="icon"><i class="mdi mdi-printer"></i></span>
                                    </a>
                                <?php else: ?>
                                    <a href="konfirmasi-biaya.php?id_rm=<?= e($trx['id_rm']) ?>" class="button is-small is-primary" title="Proses Kasir">Bayar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pengaturan warna Bulma CSS untuk Chart.js
    const colorPrimary = '#00d1b2';       // Bulma Primary
    const colorPrimaryLight = 'rgba(0, 209, 178, 0.2)';
    const colorInfo = '#3e8ed0';          // Bulma Info
    const colorGrid = '#ededed';

    // 1. Inisialisasi Line Chart (Tren Pendapatan)
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Total Pendapatan (Rp)',
                data: <?= json_encode($chart_values) ?>,
                backgroundColor: colorPrimaryLight,
                borderColor: colorPrimary,
                borderWidth: 2,
                pointBackgroundColor: colorPrimary,
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: colorPrimary,
                fill: true,
                tension: 0.3 // Smooth curves
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: colorGrid, drawBorder: false },
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000) + 'k'; // Format ke ribuan untuk efisiensi ruang
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false }
                }
            }
        }
    });

    // 2. Inisialisasi Doughnut Chart (Breakdown Bulan Ini)
    const ctxBreakdown = document.getElementById('breakdownChart').getContext('2d');
    new Chart(ctxBreakdown, {
        type: 'doughnut',
        data: {
            labels: ['Jasa Medis', 'Penjualan Obat'],
            datasets: [{
                data: [<?= $total_jasa ?>, <?= $total_obat ?>],
                backgroundColor: [colorPrimary, colorInfo],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed.raw);
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
