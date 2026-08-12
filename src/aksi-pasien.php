<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/admin-only.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $tanggal_lahir = !empty($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : null;
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');

        try {
            // Memulai transaksi untuk mencegah kondisi balapan (race condition)
            $pdo->beginTransaction();

            // Mengambil angka terbesar dari porsi nilai numerik di format RM-XXXXX
            $stmt_rm = $pdo->query("SELECT MAX(CAST(SUBSTR(no_rm, 4) AS INTEGER)) FROM pasien");
            $max_rm = $stmt_rm->fetchColumn();
            
            $next_rm = $max_rm ? (int)$max_rm + 1 : 1;
            // Membentuk string secara utuh ke format RM-00001
            $no_rm = 'RM-' . str_pad($next_rm, 5, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO pasien (no_rm, nama_lengkap, tanggal_lahir, jenis_kelamin, alamat, no_telp) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$no_rm, $nama_lengkap, $tanggal_lahir, $jenis_kelamin, $alamat, $no_telp]);

            $new_id = $pdo->lastInsertId();
            
            // Konfirmasi (commit) kueri jika seluruh proses telah sukses dijalankan
            $pdo->commit();

            redirect("pasien.php?id=" . $new_id);
        } catch (Exception $e) {
            // Membatalkan (rollback) proses jika ada kesalahan pada eksekusi
            $pdo->rollBack();
            die("Gagal menyimpan pasien: " . $e->getMessage());
        }
        
    } elseif ($action === 'update') {
        $id = $_POST['id'];
        $no_rm = trim($_POST['no_rm']); // Nilai no_rm dipertahankan utuh dari form edit
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $tanggal_lahir = !empty($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : null;
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');

        $stmt = $pdo->prepare("UPDATE pasien SET no_rm = ?, nama_lengkap = ?, tanggal_lahir = ?, jenis_kelamin = ?, alamat = ?, no_telp = ? WHERE id = ?");
        $stmt->execute([$no_rm, $nama_lengkap, $tanggal_lahir, $jenis_kelamin, $alamat, $no_telp, $id]);

        redirect("pasien.php?id=" . $id);
        
    } elseif ($action === 'delete') {
        $id = $_POST['id'];

        // Fitur ON DELETE CASCADE pada db.php akan otomatis menghapus
        // data rekam_medik yang bersangkutan.
        $stmt = $pdo->prepare("DELETE FROM pasien WHERE id = ?");
        $stmt->execute([$id]);

        redirect("pasien.php");
    }
}

// Fallback jika file diakses langsung tanpa POST
redirect("pasien.php");
