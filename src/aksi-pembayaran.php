<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Memuat konfigurasi aplikasi untuk membaca mode operasional
$config = require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rm = $_POST['id_rm'] ?? null;
    $pasien_id = $_POST['pasien_id'] ?? null;
    $biaya_layanan = (float)($_POST['biaya_layanan'] ?? 0); // Tambahkan baris ini
    $biaya_obat = (float)($_POST['biaya_obat'] ?? 0);
    $total_tagihan = (float)($_POST['total_tagihan'] ?? 0);
    $uang_dibayar = (float)($_POST['uang_dibayar'] ?? 0);

    if (!$id_rm) {
        die("Gagal: ID Rekam Medis tidak valid.");
    }

    // Validasi kecukupan pembayaran di sisi server
    if ($uang_dibayar < $total_tagihan) {
        die("Gagal memproses pembayaran: Nominal uang yang dibayarkan kurang dari total tagihan.");
    }

    $kembalian = $uang_dibayar - $total_tagihan;

    try {
        $pdo->beginTransaction();

        // 1. Validasi keamanan status kunjungan saat ini
        $stmt_cek = $pdo->prepare("SELECT status_layanan FROM rekam_medik WHERE id = ?");
        $stmt_cek->execute([$id_rm]);
        $status_sekarang = $stmt_cek->fetchColumn();

        if (strtolower($status_sekarang) === 'selesai') {
            $pdo->rollBack();
            die("Transaksi ini sudah diselesaikan sebelumnya.");
        }

        // 2. Memeriksa ketersediaan catatan keuangan dan memperbarui datanya
        $stmt_keuangan_cek = $pdo->prepare("SELECT id FROM keuangan WHERE rekam_medik_id = ?");
        $stmt_keuangan_cek->execute([$id_rm]);
        $has_keuangan = $stmt_keuangan_cek->fetchColumn();

        if ($has_keuangan) {
            $stmt_keuangan = $pdo->prepare("
                UPDATE keuangan 
                SET biaya_layanan = ?, biaya_obat = ?, total_tagihan = ?, uang_dibayar = ?, kembalian = ?, status_pembayaran = 'lunas' 
                WHERE rekam_medik_id = ?
            ");
            $stmt_keuangan->execute([$biaya_layanan, $biaya_obat, $total_tagihan, $uang_dibayar, $kembalian, $id_rm]);
        } else {
            $stmt_keuangan = $pdo->prepare("
                INSERT INTO keuangan (rekam_medik_id, biaya_layanan, biaya_obat, total_tagihan, uang_dibayar, kembalian, status_pembayaran) 
                VALUES (?, ?, ?, ?, ?, ?, 'lunas')
            ");
            $stmt_keuangan->execute([$id_rm, $biaya_layanan, $biaya_obat, $total_tagihan, $uang_dibayar, $kembalian]);
        }

        // 3. Memperbarui status layanan rekam medis menjadi 'selesai'
        $stmt_rm = $pdo->prepare("UPDATE rekam_medik SET status_layanan = 'selesai' WHERE id = ?");
        $stmt_rm->execute([$id_rm]);

        $pdo->commit();

        // 4. Logika pengalihan (redirect) dinamis berdasarkan mode aplikasi
        $mode = $config['app']['mode'] ?? 'mandiri';

        if ($mode === 'klinik') {
            $_SESSION['notification'] = [
               'status' => 'farmasi_selesai',
               'id_pasien' => $pasien_id,
            ];
            // Mode Klinik: Kembali ke dashboard pelayanan dengan parameter notifikasi
            redirect("pelayanan.php");
        } else {
            // Mode Mandiri: Tetap di halaman konfirmasi biaya untuk menampilkan nota lunas & cetak struk
            redirect("konfirmasi-biaya.php?id_rm=" . $id_rm);
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Terjadi kesalahan sistem saat memproses pembayaran: " . $e->getMessage());
    }
}

// Fallback jika file diakses langsung tanpa method POST
redirect("pelayanan.php");
