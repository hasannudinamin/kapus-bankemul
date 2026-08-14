<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$config = require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rm = $_POST['id_rm'] ?? null;
    $keluhan = trim($_POST['keluhan'] ?? '');
    $diagnosa = trim($_POST['diagnosa'] ?? '');
    $tindakan = trim($_POST['tindakan'] ?? '');
    $action = $_POST['action'] ?? '';

    if (!$id_rm) {
        die("Gagal: ID Rekam Medis tidak ditemukan.");
    }

    // VALIDASI KEAMANAN: Pastikan status pasien belum melampaui pemeriksaan
    $stmt_cek = $pdo->prepare("SELECT status_layanan FROM rekam_medik WHERE id = ?");
    $stmt_cek->execute([$id_rm]);
    $status_sekarang = $stmt_cek->fetchColumn();

    if (in_array(strtolower($status_sekarang), ['farmasi', 'pembayaran', 'selesai'])) {
        die("Pelanggaran Akses: Anda tidak diizinkan mengubah catatan medis karena resep pasien sudah mulai diproses atau selesai.");
    }

    try {
        if ($action === 'simpan_draft') {
            // Menyimpan data tanpa mengubah status layanan
            $stmt = $pdo->prepare("UPDATE rekam_medik SET keluhan = ?, diagnosa = ?, tindakan = ? WHERE id = ?");
            $stmt->execute([$keluhan, $diagnosa, $tindakan, $id_rm]);
            
            // Tetap di halaman pemeriksaan agar dokter bisa melanjutkan pengisian
            redirect("pemeriksaan.php?id_rm=" . $id_rm . "&status=draft");
            
        } elseif ($action === 'selesai_pemeriksaan') {
            // Menyimpan data dan memajukan status ke 'farmasi'
            $stmt = $pdo->prepare("UPDATE rekam_medik SET keluhan = ?, diagnosa = ?, tindakan = ?, status_layanan = 'farmasi' WHERE id = ?");
            $stmt->execute([$keluhan, $diagnosa, $tindakan, $id_rm]);

            // Logika pengalihan (redirect) dinamis berdasarkan mode aplikasi
            $mode = $config['app']['mode'] ?? 'mandiri';
            
            if ($mode === 'klinik') {
                // Mode Klinik: Arahkan kembali ke dashboard pelayanan
                redirect("pelayanan.php?status=success&msg=pemeriksaan_selesai");
            } else {
                // Mode Mandiri: Arahkan langsung ke form farmasi
                redirect("farmasi.php?id_rm=" . $id_rm);
            }
        }
    } catch (Exception $e) {
        die("Terjadi kesalahan sistem: " . $e->getMessage());
    }
}

// Fallback jika file diakses secara langsung melalui URL
redirect("pelayanan.php");
