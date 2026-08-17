<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Memuat konfigurasi aplikasi untuk membaca mode operasional
$config = require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rm = $_POST['id_rm'] ?? null;
    
    // Mengambil ID User dari sesi untuk dicatat di log_stok
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$id_rm || !$user_id) {
        die("Gagal: Data tidak valid atau sesi pengguna tidak ditemukan.");
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Mengambil seluruh item resep dan harga satuan saat ini dari tabel obat
        // Tambahkan nama_obat untuk keperluan pesan error jika stok tidak cukup
        $stmt_resep = $pdo->prepare("
            SELECT r.id AS resep_id, r.obat_id, r.jumlah, o.harga_satuan, o.nama_obat 
            FROM resep r
            JOIN obat o ON r.obat_id = o.id
            WHERE r.rekam_medik_id = ?
        ");
        $stmt_resep->execute([$id_rm]);
        $resep_items = $stmt_resep->fetchAll();

        // Persiapan kueri-kueri di luar loop agar lebih efisien (Prepared Statements)
        
        // Kueri FEFO: Ambil batch yang masih ada stoknya, urut berdasarkan ED terdekat
        $stmt_get_batches = $pdo->prepare("
            SELECT id, stok_batch, harga_beli 
            FROM obat_batch 
            WHERE obat_id = ? AND stok_batch > 0 
            ORDER BY tanggal_kedaluwarsa ASC, id ASC
        ");
        
        // Kueri pembaruan stok pada tabel obat_batch
        $stmt_update_batch = $pdo->prepare("UPDATE obat_batch SET stok_batch = stok_batch - ? WHERE id = ?");
        
        // Kueri pencatatan detail potongan multi-batch (Jembatan HPP)
        $stmt_insert_detail = $pdo->prepare("INSERT INTO resep_detail_batch (resep_id, obat_batch_id, jumlah, harga_beli) VALUES (?, ?, ?, ?)");
        
        // Kueri pencatatan riwayat (Kartu Stok)
        $stmt_insert_log = $pdo->prepare("INSERT INTO log_stok (obat_id, obat_batch_id, user_id, jenis_transaksi, jumlah, keterangan) VALUES (?, ?, ?, 'keluar', ?, 'Penjualan via resep')");

        // Kueri pembaruan stok akumulasi di tabel master obat
        $stmt_update_stok = $pdo->prepare("UPDATE obat SET stok = stok - ? WHERE id = ?");
        
        // Kueri penguncian harga jual di tabel resep
        $stmt_update_harga = $pdo->prepare("UPDATE resep SET harga_jual = ? WHERE id = ?");

        foreach ($resep_items as $item) {
            $resep_id = $item['resep_id'];
            $obat_id = $item['obat_id'];
            $jumlah_dibutuhkan = (int)$item['jumlah'];
            $harga_saat_ini = (float)$item['harga_satuan'];
            $nama_obat = $item['nama_obat'];

            $jumlah_sisa = $jumlah_dibutuhkan;

            // Eksekusi pengambilan daftar batch untuk obat ini
            $stmt_get_batches->execute([$obat_id]);
            $batches = $stmt_get_batches->fetchAll();

            // 2. Loop Algoritma FEFO (Air Terjun)
            foreach ($batches as $batch) {
                // Jika kebutuhan sudah terpenuhi, hentikan loop batch
                if ($jumlah_sisa <= 0) {
                    break;
                }

                $batch_id = $batch['id'];
                $stok_batch = (int)$batch['stok_batch'];
                $harga_beli_batch = (float)$batch['harga_beli'];

                // Tentukan jumlah yang bisa diambil dari batch ini
                $potong = min($jumlah_sisa, $stok_batch);

                // Potong stok di tabel obat_batch
                $stmt_update_batch->execute([$potong, $batch_id]);

                // Catat transaksi pemotongan batch untuk laporan Laba (HPP)
                $stmt_insert_detail->execute([$resep_id, $batch_id, $potong, $harga_beli_batch]);

                // Catat log pergerakan stok (Stok Keluar)
                $stmt_insert_log->execute([$obat_id, $batch_id, $user_id, $potong]);

                // Kurangi target sisa yang masih harus dicari dari batch berikutnya
                $jumlah_sisa -= $potong;
            }

            // Proteksi: Jika setelah mengecek semua batch ternyata kebutuhan masih kurang (inkonsistensi data)
            if ($jumlah_sisa > 0) {
                throw new Exception("Stok fisik tidak mencukupi untuk obat: " . $nama_obat . ". Terdapat ketidaksesuaian antara stok agregat dan rincian batch.");
            }

            // 3. Eksekusi pemotongan stok akumulasi di tabel obat master
            $stmt_update_stok->execute([$jumlah_dibutuhkan, $obat_id]);

            // 4. Eksekusi penguncian harga pada tabel resep
            $stmt_update_harga->execute([$harga_saat_ini, $resep_id]);
        }

        // 5. Memperbarui status layanan rekam medis dari 'farmasi' menjadi 'pembayaran'
        $stmt_rm = $pdo->prepare("UPDATE rekam_medik SET status_layanan = 'pembayaran' WHERE id = ?");
        $stmt_rm->execute([$id_rm]);

        $pdo->commit();

        // 6. Logika pengalihan (redirect) dinamis berdasarkan mode aplikasi
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
