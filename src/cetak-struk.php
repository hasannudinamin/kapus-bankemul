<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Memuat konfigurasi untuk mengambil variabel nama klinik dan ukuran printer
$config = require_once __DIR__ . '/includes/config.php';

$id_rm = $_GET['id_rm'] ?? null;
if (!$id_rm) {
    die("ID Kunjungan tidak valid.");
}

// Mengambil data transaksi, rekam medis, dan informasi pasien
$stmt = $pdo->prepare("
    SELECT rm.tanggal_kunjungan, p.nama_lengkap, p.no_rm, 
           k.id AS no_transaksi, k.biaya_layanan, k.total_tagihan, k.uang_dibayar, k.kembalian, k.status_pembayaran
    FROM rekam_medik rm 
    JOIN pasien p ON rm.pasien_id = p.id 
    JOIN keuangan k ON rm.id = k.rekam_medik_id
    WHERE rm.id = ?
");
$stmt->execute([$id_rm]);
$data = $stmt->fetch();

if (!$data || strtolower($data['status_pembayaran']) !== 'lunas') {
    die("Transaksi belum lunas atau data tidak ditemukan.");
}

// Mengambil rincian obat yang diberikan
$resep_stmt = $pdo->prepare("
    SELECT r.jumlah, o.nama_obat, r.harga_jual AS harga_satuan
    FROM resep r 
    JOIN obat o ON r.obat_id = o.id 
    WHERE r.rekam_medik_id = ?
");
$resep_stmt->execute([$id_rm]);
$resep_list = $resep_stmt->fetchAll();

// Konfigurasi dinamis
$printer_size = $config['app']['printer_size'] ?? '58mm';
$clinic_name = $config['app']['name'] ?? 'Klinik';

// Penetapan lebar cetak yang aman (printable area) dan ukuran font
$width_css = $printer_size === '80mm' ? '72mm' : '48mm';
$font_size = $printer_size === '80mm' ? '14px' : '12px';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - <?= e($data['nama_lengkap']) ?></title>
    <style>
        /* Menghilangkan margin bawaan halaman untuk peramban berbasis Chromium */
        @page { 
            margin: 0; 
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
        }
        .ticket {
            width: <?= $width_css ?>;
            max-width: 100%;
            margin: 0 auto;
            padding: 2mm;
            font-size: <?= $font_size ?>;
            line-height: 1.2;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        td { 
            vertical-align: top; 
            padding: 2px 0; 
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        .mt-1 { margin-top: 5px; }
        .mb-1 { margin-bottom: 5px; }
    </style>
</head>
<body onload="window.print();">
    <div class="ticket">
        <div class="text-center bold mb-1" style="font-size: 1.2em;">
            <?= e($clinic_name) ?>
        </div>
        <div class="text-center mb-1">
            Struk Pembayaran
        </div>
        
        <div class="divider"></div>
        
        <table>
            <tr>
                <td width="30%">Tgl</td>
                <td width="5%">:</td>
                <td><?= date('d/m/Y H:i', strtotime($data['tanggal_kunjungan'])) ?></td>
            </tr>
            <tr>
                <td>No</td>
                <td>:</td>
                <td>TRX-<?= str_pad($data['no_transaksi'], 5, '0', STR_PAD_LEFT) ?></td>
            </tr>
            <tr>
                <td>RM</td>
                <td>:</td>
                <td><?= e($data['no_rm']) ?></td>
            </tr>
            <tr>
                <td>Nama</td>
                <td>:</td>
                <td><?= e($data['nama_lengkap']) ?></td>
            </tr>
        </table>
        
        <div class="divider"></div>
        
        <table>
            <!-- Jasa Medis -->
            <tr>
                <td colspan="3">Jasa Pemeriksaan Dokter</td>
            </tr>
            <tr>
                <td width="20%">1x</td>
                <td width="40%" class="text-right"><?= number_format($data['biaya_layanan'], 0, ',', '.') ?></td>
                <td width="40%" class="text-right"><?= number_format($data['biaya_layanan'], 0, ',', '.') ?></td>
            </tr>

            <!-- Item Obat -->
            <?php foreach ($resep_list as $resep): 
                $subtotal = $resep['jumlah'] * $resep['harga_satuan'];
            ?>
            <tr>
                <td colspan="3"><?= e($resep['nama_obat']) ?></td>
            </tr>
            <tr>
                <td><?= $resep['jumlah'] ?>x</td>
                <td class="text-right"><?= number_format($resep['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right"><?= number_format($subtotal, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="divider"></div>
        
        <table>
            <tr>
                <td class="bold">Total</td>
                <td class="text-right bold"><?= number_format($data['total_tagihan'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Tunai</td>
                <td class="text-right"><?= number_format($data['uang_dibayar'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Kembali</td>
                <td class="text-right"><?= number_format($data['kembalian'], 0, ',', '.') ?></td>
            </tr>
        </table>
        
        <div class="divider mt-1"></div>
        
        <div class="text-center mt-1">
            Terima kasih atas kunjungan Anda.<br>Semoga lekas sembuh.
        </div>
    </div>
</body>
</html>
