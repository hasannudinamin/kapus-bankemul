<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$jenis = $_GET['jenis'] ?? '';

if (empty($jenis)) {
    die("Jenis ekspor tidak valid.");
}

// Inisialisasi variabel untuk nama file dan data
$nama_file = "Export_" . date('Y-m-d_His') . ".csv";
$header_csv = [];
$data_csv = [];

try {
    switch ($jenis) {
        
        // 1. EKSPOR MASTER OBAT
        case 'master_obat':
            $filter_stok = $_GET['filter_stok'] ?? 'semua';
            $nama_file = "Master_Obat_{$filter_stok}_" . date('Y-m-d') . ".csv";
            $header_csv = ['Kode Obat', 'Nama Obat', 'Satuan', 'Harga Jual (Rp)', 'Total Stok', 'ED Terdekat'];
            
            $sql = "
                SELECT o.kode_obat, o.nama_obat, o.satuan, o.harga_satuan, o.stok,
                       (SELECT MIN(tanggal_kedaluwarsa) FROM obat_batch ob WHERE ob.obat_id = o.id AND ob.stok_batch > 0) AS ed_terdekat
                FROM obat o
                WHERE 1=1
            ";
            
            if ($filter_stok === 'tersedia') {
                $sql .= " AND o.stok > 0";
            } elseif ($filter_stok === 'kritis') {
                $sql .= " AND o.stok > 0 AND o.stok <= 10";
            } elseif ($filter_stok === 'kosong') {
                $sql .= " AND o.stok = 0";
            }
            
            $sql .= " ORDER BY o.nama_obat ASC";
            $stmt = $pdo->query($sql);
            
            while ($row = $stmt->fetch()) {
                $data_csv[] = [
                    $row['kode_obat'],
                    $row['nama_obat'],
                    $row['satuan'],
                    $row['harga_satuan'],
                    $row['stok'],
                    $row['ed_terdekat'] ? date('d/m/Y', strtotime($row['ed_terdekat'])) : '-'
                ];
            }
            break;

        // 2. EKSPOR KARTU STOK (LOG PERGERAKAN)
        case 'kartu_stok':
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $jenis_transaksi = $_GET['jenis_transaksi'] ?? 'semua';
            $nama_file = "Kartu_Stok_{$start}_sd_{$end}.csv";
            
            $header_csv = ['Waktu', 'Kode Obat', 'Nama Obat', 'No Batch', 'Jenis Transaksi', 'Jumlah', 'Sisa Sistem', 'Petugas', 'Keterangan'];
            
            $sql = "
                SELECT ls.tanggal, o.kode_obat, o.nama_obat, ob.no_batch, ls.jenis_transaksi, ls.jumlah, 
                       u.nama_lengkap AS nama_petugas, ls.keterangan, o.stok
                FROM log_stok ls
                JOIN obat o ON ls.obat_id = o.id
                LEFT JOIN obat_batch ob ON ls.obat_batch_id = ob.id
                LEFT JOIN users u ON ls.user_id = u.id
                WHERE date(ls.tanggal) BETWEEN ? AND ?
            ";
            $params = [$start, $end];
            
            if ($jenis_transaksi !== 'semua') {
                $sql .= " AND ls.jenis_transaksi = ?";
                $params[] = $jenis_transaksi;
            }
            
            $sql .= " ORDER BY ls.tanggal DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            while ($row = $stmt->fetch()) {
                $data_csv[] = [
                    date('d/m/Y H:i', strtotime($row['tanggal'])),
                    $row['kode_obat'],
                    $row['nama_obat'],
                    $row['no_batch'] ?: '-',
                    strtoupper($row['jenis_transaksi']),
                    $row['jumlah'],
                    $row['stok'], // Sisa stok master terakhir, bukan per baris historis
                    $row['nama_petugas'] ?? 'Sistem',
                    $row['keterangan']
                ];
            }
            break;

        // 3. EKSPOR BUKU BESAR KEUANGAN
        case 'buku_besar':
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $status_pembayaran = $_GET['status_pembayaran'] ?? 'semua';
            $nama_file = "Buku_Besar_{$start}_sd_{$end}.csv";
            
            $header_csv = ['Waktu', 'No Transaksi', 'No RM', 'Nama Pasien', 'Jasa Medis (Rp)', 'Biaya Obat (Rp)', 'Total Tagihan (Rp)', 'Total HPP (Rp)', 'Laba (Rp)', 'Status'];
            
            $sql = "
                SELECT rm.tanggal_kunjungan, k.id AS keuangan_id, p.no_rm, p.nama_lengkap, 
                       k.biaya_layanan, k.biaya_obat, k.total_tagihan, k.total_hpp_obat, k.status_pembayaran
                FROM keuangan k
                JOIN rekam_medik rm ON k.rekam_medik_id = rm.id
                JOIN pasien p ON rm.pasien_id = p.id
                WHERE date(rm.tanggal_kunjungan) BETWEEN ? AND ?
            ";
            $params = [$start, $end];
            
            if ($status_pembayaran !== 'semua') {
                $sql .= " AND k.status_pembayaran = ?";
                $params[] = $status_pembayaran;
            }
            
            $sql .= " ORDER BY rm.tanggal_kunjungan ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            while ($row = $stmt->fetch()) {
                $laba = ($row['biaya_layanan'] + $row['biaya_obat']) - $row['total_hpp_obat'];
                $data_csv[] = [
                    date('d/m/Y H:i', strtotime($row['tanggal_kunjungan'])),
                    'TRX-' . str_pad($row['keuangan_id'], 5, '0', STR_PAD_LEFT),
                    $row['no_rm'],
                    $row['nama_lengkap'],
                    $row['biaya_layanan'],
                    $row['biaya_obat'],
                    $row['total_tagihan'],
                    $row['total_hpp_obat'],
                    $laba,
                    strtoupper($row['status_pembayaran'])
                ];
            }
            break;

        // 4. EKSPOR RIWAYAT KUNJUNGAN KLINIS
        case 'riwayat_kunjungan':
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $status_layanan = $_GET['status_layanan'] ?? 'semua';
            $nama_file = "Riwayat_Kunjungan_{$start}_sd_{$end}.csv";
            
            $header_csv = ['Waktu', 'No RM', 'Nama Pasien', 'L/P', 'Usia', 'Keluhan', 'Diagnosa', 'Tindakan', 'Status', 'Petugas (Dokter)'];
            
            $sql = "
                SELECT rm.tanggal_kunjungan, p.no_rm, p.nama_lengkap, p.jenis_kelamin, p.tanggal_lahir, 
                       rm.keluhan, rm.diagnosa, rm.tindakan, rm.status_layanan, u.nama_lengkap AS nama_petugas
                FROM rekam_medik rm
                JOIN pasien p ON rm.pasien_id = p.id
                LEFT JOIN users u ON rm.user_id = u.id
                WHERE date(rm.tanggal_kunjungan) BETWEEN ? AND ?
            ";
            $params = [$start, $end];
            
            if ($status_layanan !== 'semua') {
                $sql .= " AND rm.status_layanan = ?";
                $params[] = $status_layanan;
            }
            
            $sql .= " ORDER BY rm.tanggal_kunjungan DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            while ($row = $stmt->fetch()) {
                // Kalkulasi usia
                $usia = '-';
                if ($row['tanggal_lahir']) {
                    $usia = date_diff(date_create($row['tanggal_lahir']), date_create($row['tanggal_kunjungan']))->y . ' thn';
                }

                $data_csv[] = [
                    date('d/m/Y H:i', strtotime($row['tanggal_kunjungan'])),
                    $row['no_rm'],
                    $row['nama_lengkap'],
                    $row['jenis_kelamin'],
                    $usia,
                    $row['keluhan'] ?: '-',
                    $row['diagnosa'] ?: '-',
                    $row['tindakan'] ?: '-',
                    strtoupper($row['status_layanan']),
                    $row['nama_petugas'] ?? '-'
                ];
            }
            break;

        default:
            die("Jenis ekspor tidak dikenali.");
    }

    if (empty($data_csv)) {
        die("Tidak ada data yang sesuai dengan filter rentang waktu atau parameter yang dipilih.");
    }

    // Menyiapkan HTTP Header untuk memicu unduhan file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama_file . '"');

    $output = fopen('php://output', 'w');

    // Menambahkan BOM (Byte Order Mark) agar karakter UTF-8 terbaca sempurna di Microsoft Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Menulis baris header
    fputcsv($output, $header_csv);

    // Menulis data baris demi baris
    foreach ($data_csv as $baris) {
        fputcsv($output, $baris);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}
?>
