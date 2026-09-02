<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Menangkap parameter filter (Default: Bulan ini)
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');
$search = $_GET['q'] ?? '';

// Kueri untuk Panel Metrik Utama (KPIs)
$stmt_today = $pdo->query("SELECT SUM(total_tagihan) as omzet, SUM(total_tagihan - total_hpp_obat) as laba FROM keuangan k JOIN rekam_medik rm ON k.rekam_medik_id = rm.id WHERE k.status_pembayaran = 'lunas' AND date(rm.tanggal_kunjungan) = date('now', 'localtime')");
$data_today = $stmt_today->fetch();
$income_today = $data_today['omzet'] ?: 0;
$laba_today = $data_today['laba'] ?: 0;

$stmt_month = $pdo->query("SELECT SUM(total_tagihan) as omzet, SUM(total_tagihan - total_hpp_obat) as laba FROM keuangan k JOIN rekam_medik rm ON k.rekam_medik_id = rm.id WHERE k.status_pembayaran = 'lunas' AND strftime('%Y-%m', rm.tanggal_kunjungan) = strftime('%Y-%m', 'now', 'localtime')");
$data_month = $stmt_month->fetch();
$income_month = $data_month['omzet'] ?: 0;
$laba_month = $data_month['laba'] ?: 0;

$stmt_pending = $pdo->query("SELECT SUM(total_tagihan) FROM keuangan WHERE status_pembayaran = 'belum_bayar'");
$income_pending = $stmt_pending->fetchColumn() ?: 0;

$stmt_patients = $pdo->query("SELECT COUNT(*) FROM rekam_medik WHERE status_layanan = 'selesai' AND date(tanggal_kunjungan) = date('now', 'localtime')");
$patients_today = $stmt_patients->fetchColumn() ?: 0;

// Kueri Proporsi Pendapatan (Bulan Ini) untuk Doughnut Chart
$stmt_breakdown = $pdo->query("SELECT SUM(biaya_layanan) as total_jasa, SUM(biaya_obat) as total_obat, SUM(total_hpp_obat) as total_hpp FROM keuangan k JOIN rekam_medik rm ON k.rekam_medik_id = rm.id WHERE k.status_pembayaran = 'lunas' AND strftime('%Y-%m', rm.tanggal_kunjungan) = strftime('%Y-%m', 'now', 'localtime')");
$breakdown = $stmt_breakdown->fetch();
$total_jasa = (float)($breakdown['total_jasa'] ?? 0);
$total_hpp = (float)($breakdown['total_hpp'] ?? 0);
$laba_obat = (float)(($breakdown['total_obat'] ?? 0) - $total_hpp);

// Kueri Tren Pendapatan & Laba 7 Hari Terakhir untuk Line Chart
$chart_labels = [];
$chart_omzet = [];
$chart_laba = [];
$raw_chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date_str));
    $raw_chart_data[$date_str] = ['omzet' => 0, 'laba' => 0];
}

$stmt_trend = $pdo->query("
    SELECT date(rm.tanggal_kunjungan) as tgl, SUM(k.total_tagihan) as total_omzet, SUM(k.total_tagihan - k.total_hpp_obat) as total_laba
    FROM keuangan k
    JOIN rekam_medik rm ON k.rekam_medik_id = rm.id
    WHERE k.status_pembayaran = 'lunas' AND date(rm.tanggal_kunjungan) >= date('now', '-6 days', 'localtime')
    GROUP BY date(rm.tanggal_kunjungan)
");
while ($row = $stmt_trend->fetch()) {
    if (isset($raw_chart_data[$row['tgl']])) {
        $raw_chart_data[$row['tgl']]['omzet'] = (float)$row['total_omzet'];
        $raw_chart_data[$row['tgl']]['laba'] = (float)$row['total_laba'];
    }
}
foreach ($raw_chart_data as $data) {
    $chart_omzet[] = $data['omzet'];
    $chart_laba[] = $data['laba'];
}

$page_title = 'Dashboard Analisis Keuangan & Laba';
include 'includes/header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="is-flex is-justify-content-space-between is-align-items-center mb-5">
    <div>
        <h1 class="title is-size-3 mb-0 is-size-5-mobile">
            <span class="icon has-text-primary mr-2"><i class="mdi mdi-finance"></i></span>
            Dashboard Keuangan
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey mt-1">
            Analisis profitabilitas dan arus kas klinik
        </h2>
    </div>
</div>

<!-- Panel Metrik (KPIs) Laba & Omzet -->
<div class="columns is-multiline is-mobile mb-4">
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-success-light border-success-top h-100">
            <p class="heading has-text-success-dark has-text-weight-bold">Laba Bersih (Bulan Ini)</p>
            <p class="title is-4 has-text-success-dark">Rp <?= number_format($laba_month, 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="column is-6-mobile is-3-desktop">
        <div class="box has-background-info-light border-info-top h-100">
            <p class="heading has-text-info-dark has-text-weight-bold">Omzet Kotor (Bulan Ini)</p>
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
        <div class="box has-background-primary-light border-primary-top h-100">
            <p class="heading has-text-primary has-text-weight-bold">Laba Bersih (Hari Ini)</p>
            <p class="title is-4 has-text-primary-dark">Rp <?= number_format($laba_today, 0, ',', '.') ?></p>
        </div>
    </div>
</div>

<!-- Grafik Visualisasi -->
<div class="columns is-desktop is-variable is-4 mb-5">
    <!-- Tren 7 Hari Terakhir -->
    <div class="column is-8">
        <div class="box box-desktop h-100">
            <h4 class="title is-6 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-chart-areaspline"></i></span> Tren Omzet & Laba (7 Hari Terakhir)
            </h4>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Breakdown Laba & Modal Bulan Ini -->
    <div class="column is-4">
        <div class="box box-desktop h-100">
            <h4 class="title is-6 has-text-grey-dark border-bottom pb-2 mb-4">
                <span class="icon"><i class="mdi mdi-chart-donut"></i></span> Struktur Pendapatan Bulan Ini
            </h4>
            <div style="position: relative; height: 220px; width: 100%; display: flex; justify-content: center;">
                <canvas id="breakdownChart"></canvas>
            </div>
            <div class="mt-4">
                <div class="is-flex is-justify-content-space-between is-size-7 mb-2">
                    <span><span class="icon has-text-primary"><i class="mdi mdi-circle"></i></span> Jasa Medis</span>
                    <strong class="has-text-grey-dark">Rp <?= number_format($total_jasa, 0, ',', '.') ?></strong>
                </div>
                <div class="is-flex is-justify-content-space-between is-size-7 mb-2">
                    <span><span class="icon has-text-success"><i class="mdi mdi-circle"></i></span> Laba Jual Obat</span>
                    <strong class="has-text-grey-dark">Rp <?= number_format($laba_obat, 0, ',', '.') ?></strong>
                </div>
                <div class="is-flex is-justify-content-space-between is-size-7">
                    <span><span class="icon has-text-warning"><i class="mdi mdi-circle"></i></span> Modal Obat (HPP)</span>
                    <strong class="has-text-grey-dark">Rp <?= number_format($total_hpp, 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Buku Besar / Filter & Tabel Transaksi -->
<div class="box box-desktop">
    <div class="is-flex is-flex-wrap-wrap is-justify-content-space-between is-align-items-flex-end mb-4 border-bottom pb-3">
        <h4 class="title is-5 mb-2 has-text-grey-dark">
            <span class="icon"><i class="mdi mdi-book-open-page-variant"></i></span> Buku Besar Transaksi
        </h4>
        
        <!-- Form Filter & Pencarian dengan HTMX yang diarahkan ke komponen tabel -->
        <form class="is-flex is-flex-wrap-wrap gap-2" style="gap: 10px;" id="form-filter-keuangan" hx-get="komponen-buku-besar.php" hx-target="#tbody-transaksi" hx-trigger="submit, change from:input[type='date']">
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
                    <input class="input is-small" type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Pasien / RM..." autocomplete="off" hx-get="komponen-buku-besar.php" hx-include="#form-filter-keuangan" hx-target="#tbody-transaksi" hx-trigger="input changed delay:500ms, search">
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
                    <th class="has-text-right">Total Omzet</th>
                    <th class="has-text-right">Margin Laba</th>
                    <th class="has-text-centered">Status</th>
                    <th class="has-text-right">Aksi</th>
                </tr>
            </thead>
            <!-- Area Target HTMX dimuat secara otomatis saat halaman pertama kali dibuka -->
            <tbody id="tbody-transaksi" hx-get="komponen-buku-besar.php?start=<?= e($start_date) ?>&end=<?= e($end_date) ?>&q=<?= e($search) ?>" hx-trigger="load">
                <tr>
                    <td colspan="9" class="has-text-centered has-text-grey py-5">
                        <span class="icon is-large"><i class="mdi mdi-loading mdi-spin mdi-24px"></i></span>
                        <p>Memuat data buku besar...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const colorPrimary = '#00d1b2';       
    const colorPrimaryLight = 'rgba(0, 209, 178, 0.2)';
    const colorSuccess = '#48c774';
    const colorSuccessLight = 'rgba(72, 199, 116, 0.2)';
    const colorWarning = '#ffdd57';
    const colorGrid = '#ededed';

    // 1. Line Chart (Tren Omzet & Laba)
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Omzet (Kotor)',
                    data: <?= json_encode($chart_omzet) ?>,
                    backgroundColor: colorPrimaryLight,
                    borderColor: colorPrimary,
                    borderWidth: 2,
                    pointBackgroundColor: colorPrimary,
                    fill: false,
                    tension: 0.3
                },
                {
                    label: 'Laba (Bersih)',
                    data: <?= json_encode($chart_laba) ?>,
                    backgroundColor: colorSuccessLight,
                    borderColor: colorSuccess,
                    borderWidth: 2,
                    pointBackgroundColor: colorSuccess,
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { position: 'top' },
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
                            return 'Rp ' + (value / 1000) + 'k';
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false }
                }
            }
        }
    });

    // 2. Doughnut Chart (Struktur Pendapatan & Modal)
    const ctxBreakdown = document.getElementById('breakdownChart').getContext('2d');
    new Chart(ctxBreakdown, {
        type: 'doughnut',
        data: {
            labels: ['Jasa Medis', 'Laba Obat', 'Modal Obat (HPP)'],
            datasets: [{
                data: [<?= $total_jasa ?>, <?= $laba_obat ?>, <?= $total_hpp ?>],
                backgroundColor: [colorPrimary, colorSuccess, colorWarning],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
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
