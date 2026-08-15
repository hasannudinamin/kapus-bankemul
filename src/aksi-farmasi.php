<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Memuat konfigurasi aplikasi untuk membaca mode operasional
$config = require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rm = $_POST['id_rm'] ?? null;

    if (!$id_rm) {
        die("Gagal: ID Rekam Medis tidak valid.");
    }

    try {
        $pdo->beginTransaction();
        // 1. Mengambil seluruh item resep dan harga satuan saat ini dari tabel obat
        $stmt_resep = $pdo->prepare("
            SELECT r.id AS resep_id, r.obat_id, r.jumlah, o.harga_satuan 
            FROM resep r
            JOIN obat o ON r.obat_id = o.id
            WHERE r.rekam_medik_id = ?
        ");
        $stmt_resep->execute([$id_rm]);
        $resep_items = $stmt_resep->fetchAll();

        // 2. Melakukan perulangan untuk memotong stok fisik DAN mengunci harga jual
        $stmt_update_stok = $pdo->prepare("UPDATE obat SET stok = stok - ? WHERE id = ?");

        // Siapkan kueri untuk menyalin harga ke tabel resep
        $stmt_update_harga = $pdo->prepare("UPDATE resep SET harga_jual = ? WHERE id = ?");

        foreach ($resep_items as $item) {
            $resep_id = $item['resep_id'];
            $obat_id = $item['obat_id'];
            $jumlah = (int)$item['jumlah'];
            $harga_saat_ini = (float)$item['harga_satuan']; // Harga yang berlaku di hari farmasi mengonfirmasi

            // Eksekusi pemotongan stok di tabel obat
            $stmt_update_stok->execute([$jumlah, $obat_id]);

            // Eksekusi penguncian harga pada tabel resep
            $stmt_update_harga->execute([$harga_saat_ini, $resep_id]);
        }

        // 3. Memperbarui status layanan rekam medis dari 'farmasi' menjadi 'pembayaran'
        $stmt_rm = $pdo->prepare("UPDATE rekam_medik SET status_layanan = 'pembayaran' WHERE id = ?");
        $stmt_rm->execute([$id_rm]);

        $pdo->commit();

        // 4. Logika pengalihan (redirect) dinamis berdasarkan mode aplikasi
        $mode = $config['app']['mode'] ?? 'mandiri';

        if ($mode === 'klinik') {
            // Mode Klinik: Kembali ke dashboard pelayanan dengan parameter status sukses
            redirect("pelayanan.php?status=success&msg=farmasi_selesai");
        } else {
            // Mode Mandiri: Langsung diarahkan ke halaman konfirmasi pembayaran/kasir
            redirect("konfirmasi-biaya.php?id_rm=" . $id_rm);
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Terjadi kesalahan sistem saat memproses validasi farmasi: " . $e->getMessage());
    }
}

// Fallback jika file diakses langsung tanpa melalui method POST
redirect("pelayanan.php");
