<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $kode_obat = trim($_POST['kode_obat'] ?? '');
        $nama_obat = trim($_POST['nama_obat']);
        $satuan = trim($_POST['satuan'] ?? '');
        // Memastikan input harga dan stok diformat sebagai angka untuk menghindari error tipe data
        $harga_satuan = (float)($_POST['harga_satuan'] ?? 0);
        $stok = (int)($_POST['stok'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Logika Pembuatan Kode Otomatis:
            // Jika kode obat dibiarkan kosong di form, sistem akan mencari nomor urut OBT- terbesar
            if ($kode_obat === '') {
                $stmt_kode = $pdo->query("SELECT MAX(CAST(SUBSTR(kode_obat, 5) AS INTEGER)) FROM obat WHERE kode_obat LIKE 'OBT-%'");
                $max_kode = $stmt_kode->fetchColumn();
                $next_kode = $max_kode ? (int)$max_kode + 1 : 1;
                $kode_obat = 'OBT-' . str_pad($next_kode, 4, '0', STR_PAD_LEFT);
            }

            // Menyisipkan seluruh nilai awal termasuk stok ke dalam database
            $stmt = $pdo->prepare("INSERT INTO obat (kode_obat, nama_obat, satuan, harga_satuan, stok) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$kode_obat, $nama_obat, $satuan, $harga_satuan, $stok]);

            $pdo->commit();
            redirect("manajemen-obat.php");

        } catch (PDOException $e) {
            $pdo->rollBack();
            // Menangkap pelanggaran atribut UNIQUE pada skema jika barcode sudah terdaftar
            if (strpos($e->getMessage(), 'UNIQUE constraint failed: obat.kode_obat') !== false) {
                die("Gagal menyimpan: Kode obat atau barcode '{$kode_obat}' sudah terdaftar di sistem.");
            }
            die("Terjadi kesalahan sistem: " . $e->getMessage());
        }
        
    } elseif ($action === 'update') {
        $id = $_POST['id'];
        $kode_obat = trim($_POST['kode_obat'] ?? '');
        $nama_obat = trim($_POST['nama_obat']);
        $satuan = trim($_POST['satuan'] ?? '');
        $harga_satuan = (float)($_POST['harga_satuan'] ?? 0);

        try {
            $pdo->beginTransaction();

            if ($kode_obat === '') {
                $stmt_kode = $pdo->query("SELECT MAX(CAST(SUBSTR(kode_obat, 5) AS INTEGER)) FROM obat WHERE kode_obat LIKE 'OBT-%'");
                $max_kode = $stmt_kode->fetchColumn();
                $next_kode = $max_kode ? (int)$max_kode + 1 : 1;
                $kode_obat = 'OBT-' . str_pad($next_kode, 4, '0', STR_PAD_LEFT);
            }

            // PERHATIAN: Kolom `stok` dihilangkan dari kueri UPDATE ini 
            // agar kuantitas fisik gudang hanya bisa diubah melalui mekanisme Restock
            $stmt = $pdo->prepare("UPDATE obat SET kode_obat = ?, nama_obat = ?, satuan = ?, harga_satuan = ? WHERE id = ?");
            $stmt->execute([$kode_obat, $nama_obat, $satuan, $harga_satuan, $id]);

            $pdo->commit();
            redirect("manajemen-obat.php");

        } catch (PDOException $e) {
            $pdo->rollBack();
            if (strpos($e->getMessage(), 'UNIQUE constraint failed: obat.kode_obat') !== false) {
                die("Gagal menyimpan: Kode obat atau barcode '{$kode_obat}' berbenturan dengan obat lain.");
            }
            die("Terjadi kesalahan sistem: " . $e->getMessage());
        }
        
    } elseif ($action === 'restock') {
        $id = $_POST['id'] ?? null;
        $jumlah_masuk = (int)($_POST['jumlah_masuk'] ?? 0);

        if ($id && $jumlah_masuk > 0) {
            try {
                $pdo->beginTransaction();

                // Menambahkan jumlah stok yang ada di database secara aman
                $stmt = $pdo->prepare("UPDATE obat SET stok = stok + ? WHERE id = ?");
                $stmt->execute([$jumlah_masuk, $id]);

                $pdo->commit();
                redirect("manajemen-obat.php");
            } catch (Exception $e) {
                $pdo->rollBack();
                die("Gagal memperbarui stok: " . $e->getMessage());
            }
        } else {
            die("Jumlah stok masuk tidak valid.");
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM obat WHERE id = ?");
            $stmt->execute([$id]);

            redirect("manajemen-obat.php");
        } catch (PDOException $e) {
            // Menangkap penolakan kueri dari atribut FOREIGN KEY ... ON DELETE RESTRICT 
            // jika obat sudah pernah digunakan dalam tabel resep
            if (strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
                die("Gagal menghapus: Obat ini tidak dapat dihapus secara permanen karena sudah tercatat dalam riwayat resep pasien. Silakan kosongkan stoknya atau nonaktifkan.");
            }
            die("Terjadi kesalahan sistem: " . $e->getMessage());
        }
    }
}

// Fallback jika file diakses secara langsung melalui URL tanpa request POST
redirect("manajemen-obat.php");
