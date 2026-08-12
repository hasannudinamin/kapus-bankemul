<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Memuat konfigurasi aplikasi
$config = require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $pasien_id = $_POST['pasien_id'] ?? null;
        
        // Memastikan ID User diambil dari sesi pengguna yang sedang masuk
        $user_id = $_SESSION['user_id'] ?? null; 

        if (!$pasien_id || !$user_id) {
            die("Gagal mendaftar: Data pasien atau identitas petugas tidak valid.");
        }

        try {
            $pdo->beginTransaction();

            // 1. Mendaftarkan kunjungan ke tabel rekam_medik dengan status antre
            $stmt_rm = $pdo->prepare("INSERT INTO rekam_medik (pasien_id, user_id, status_layanan) VALUES (?, ?, 'menunggu')");
            $stmt_rm->execute([$pasien_id, $user_id]);
            
            // Menangkap ID dari kunjungan yang baru saja dibuat
            $id_rm = $pdo->lastInsertId();

            // 2. Mendaftarkan tagihan awal ke tabel keuangan dengan status default[cite: 1]
            $stmt_keuangan = $pdo->prepare("INSERT INTO keuangan (rekam_medik_id, status_pembayaran) VALUES (?, 'belum_bayar')");
            $stmt_keuangan->execute([$id_rm]);

            $pdo->commit();

            // 3. Logika pengalihan (redirect) berdasarkan mode aplikasi
            $mode = $config['app']['mode'] ?? 'mandiri';
            
            if ($mode === 'klinik') {
                // Mode Klinik: Arahkan ke dashboard pelayanan dengan parameter notifikasi
                redirect("pelayanan.php?status=success&msg=terdaftar");
            } else {
                // Mode Mandiri: Arahkan langsung ke form pemeriksaan menggunakan parameter id_rm
                redirect("pemeriksaan.php?id_rm=" . $id_rm);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Terjadi kesalahan sistem saat mendaftarkan kunjungan: " . $e->getMessage());
        }
    }
}

// Fallback jika file diakses secara langsung melalui URL tanpa request POST
redirect("daftar-pasien.php");
