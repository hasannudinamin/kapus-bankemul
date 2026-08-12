<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/admin-only.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $no_rm = trim($_POST['no_rm']);
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $tanggal_lahir = !empty($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : null;
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');

        $stmt = $pdo->prepare("INSERT INTO pasien (no_rm, nama_lengkap, tanggal_lahir, jenis_kelamin, alamat, no_telp) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$no_rm, $nama_lengkap, $tanggal_lahir, $jenis_kelamin, $alamat, $no_telp]);

        $new_id = $pdo->lastInsertId();
        redirect("pasien.php?id=" . $new_id);
        
    } elseif ($action === 'update') {
        $id = $_POST['id'];
        $no_rm = trim($_POST['no_rm']);
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
