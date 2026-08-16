<?php
/**
 * Script Generator Data Dummy untuk Aplikasi Klinik/Puskesmas
 * Tempatkan file ini di root direktori proyek Anda (sejajar dengan folder includes).
 * Jalankan sekali saja via browser atau CLI.
 */

require 'includes/db.php'; // Memuat koneksi $pdo

// Pastikan skrip berjalan cukup lama tanpa timeout
set_time_limit(300);

echo "Memulai proses pembuatan data dummy...\n<br>";

try {
    $pdo->beginTransaction();

    // 1. Pastikan User Admin (ID 1) Tersedia (Bawaan db.php)
    $stmt_user = $pdo->query("SELECT id FROM users LIMIT 1");
    $user_id = $stmt_user->fetchColumn() ?: 1;

    // 2. Generate Data Obat
    $obat_list = [
        ['PAR-001', 'Paracetamol 500mg', 'Tablet', 1000, 500],
        ['AMX-001', 'Amoxicillin 500mg', 'Kapsul', 1000, 1000],
        ['CTM-001', 'Chlorpheniramine 4mg', 'Tablet', 1000, 200],
        ['IBU-001', 'Ibuprofen 400mg', 'Tablet', 1000, 800],
        ['ANT-001', 'Antasida Doen', 'Tablet Kunyah', 1000, 300],
        ['VIT-001', 'Vitamin C 50mg', 'Tablet', 1000, 150],
        ['DXM-001', 'Dexamethasone 0.5mg', 'Tablet', 1000, 250],
        ['OMP-001', 'Omeprazole 20mg', 'Kapsul', 1000, 1200]
    ];

    $stmt_obat = $pdo->prepare("INSERT INTO obat (kode_obat, nama_obat, satuan, stok, harga_satuan) VALUES (?, ?, ?, ?, ?)");
    $obat_ids = [];
    foreach ($obat_list as $obat) {
        $stmt_obat->execute($obat);
        $obat_ids[] = [
            'id' => $pdo->lastInsertId(),
            'harga_satuan' => $obat[4]
        ];
    }
    echo "- Berhasil membuat " . count($obat_list) . " data obat.<n<br>";

    // 3. Generate 30 Data Pasien
    $nama_depan = ['Budi', 'Siti', 'Agus', 'Ayu', 'Eko', 'Dewi', 'Joko', 'Ratna', 'Rizky', 'Putri', 'Hendra', 'Nita'];
    $nama_belakang = ['Santoso', 'Wijaya', 'Setiawan', 'Lestari', 'Saputra', 'Indah', 'Kurniawan', 'Sari'];
    $alamat_list = ['Jl. Merdeka No. 1', 'Jl. Sudirman Blok B', 'Perumahan Indah 2', 'Ds. Suka Maju RT 3', 'Jl. Diponegoro No 45'];
    
    $stmt_pasien = $pdo->prepare("INSERT INTO pasien (no_rm, nama_lengkap, tanggal_lahir, jenis_kelamin, alamat, no_telp) VALUES (?, ?, ?, ?, ?, ?)");
    $pasien_ids = [];
    
    for ($i = 1; $i <= 30; $i++) {
        $no_rm = 'RM-' . date('Ym') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $nama = $nama_depan[array_rand($nama_depan)] . ' ' . $nama_belakang[array_rand($nama_belakang)];
        $tgl_lahir = date('Y-m-d', strtotime('-' . rand(10, 65) . ' years -' . rand(1, 12) . ' months'));
        $jk = rand(0, 1) ? 'L' : 'P';
        $alamat = $alamat_list[array_rand($alamat_list)];
        $no_telp = '0812' . rand(10000000, 99999999);
        
        $stmt_pasien->execute([$no_rm, $nama, $tgl_lahir, $jk, $alamat, $no_telp]);
        $pasien_ids[] = $pdo->lastInsertId();
    }
    echo "- Berhasil membuat 30 data pasien.<n<br>";

    // 4. Generate 100 Data Rekam Medik
    $keluhan_list = ['Demam dan pusing sejak 2 hari yang lalu', 'Batuk berdahak dan pilek', 'Nyeri perut bagian bawah', 'Gatal-gatal pada lipatan kulit', 'Sakit kepala sebelah (migrain)', 'Badan lemas dan mual'];
    $diagnosa_list = ['R50 - Fever of other and unknown origin', 'J00 - Acute nasopharyngitis', 'K30 - Functional dyspepsia', 'L20 - Atopic dermatitis', 'G43 - Migraine'];
    $tindakan_list = ['Pemeriksaan fisik umum', 'Pemberian resep obat', 'Observasi TTV', 'Konseling diet dan gizi'];
    $status_list = ['menunggu', 'pemeriksaan', 'farmasi', 'pembayaran', 'selesai'];
    
    $stmt_rm = $pdo->prepare("INSERT INTO rekam_medik (pasien_id, user_id, tanggal_kunjungan, status_layanan, keluhan, diagnosa, tindakan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_resep = $pdo->prepare("INSERT INTO resep (rekam_medik_id, obat_id, jumlah, aturan_pakai, harga_jual) VALUES (?, ?, ?, ?, ?)");
    $stmt_keuangan = $pdo->prepare("INSERT INTO keuangan (rekam_medik_id, biaya_layanan, biaya_obat, total_tagihan, uang_dibayar, kembalian, status_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Siapkan update stok
    $stmt_update_stok = $pdo->prepare("UPDATE obat SET stok = stok - ? WHERE id = ?");

    for ($i = 1; $i <= 200; $i++) {
        $p_id = $pasien_ids[array_rand($pasien_ids)];
        // Generate tanggal acak dalam 6 bulan terakhir
        $timestamp = time() - rand(0, 180 * 24 * 60 * 60);
        $tgl_kunjungan = date('Y-m-d H:i:s', $timestamp);
        
        // Memilih status dengan probabilitas (mayoritas selesai)
        $rand_status = rand(1, 100);
        if ($rand_status <= 5) $status = 'menunggu';
        elseif ($rand_status <= 10) $status = 'pemeriksaan';
        elseif ($rand_status <= 20) $status = 'farmasi';
        elseif ($rand_status <= 30) $status = 'pembayaran';
        else $status = 'selesai';

        $keluhan = ($status !== 'menunggu') ? $keluhan_list[array_rand($keluhan_list)] : '';
        $diagnosa = ($status !== 'menunggu') ? $diagnosa_list[array_rand($diagnosa_list)] : '';
        $tindakan = ($status !== 'menunggu') ? $tindakan_list[array_rand($tindakan_list)] : '';

        $stmt_rm->execute([$p_id, $user_id, $tgl_kunjungan, $status, $keluhan, $diagnosa, $tindakan]);
        $rm_id = $pdo->lastInsertId();

        // Jika status farmasi, pembayaran, atau selesai, buat data resep
        $biaya_obat = 0;
        if (in_array($status, ['farmasi', 'pembayaran', 'selesai'])) {
            $jml_obat_diresepkan = rand(1, 3);
            $obat_dipilih = (array)array_rand($obat_ids, $jml_obat_diresepkan);
            
            foreach ($obat_dipilih as $idx) {
                $obat = $obat_ids[$idx];
                $jumlah = rand(5, 15);
                $harga_jual = $obat['harga_satuan'];
                $aturan = rand(2, 3) . ' x 1 Sesudah Makan';
                
                $stmt_resep->execute([$rm_id, $obat['id'], $jumlah, $aturan, $harga_jual]);
                $biaya_obat += ($jumlah * $harga_jual);

                // Jika status sudah melewati farmasi, potong stok
                if (in_array($status, ['pembayaran', 'selesai'])) {
                    $stmt_update_stok->execute([$jumlah, $obat['id']]);
                }
            }
        }

        // Jika status pembayaran atau selesai, buat data keuangan
        if (in_array($status, ['pembayaran', 'selesai'])) {
            $biaya_layanan = 25000;
            $total_tagihan = $biaya_layanan + $biaya_obat;
            
            if ($status === 'selesai') {
                // Skenario Lunas
                $pecahan = [50000, 100000];
                $uang_dibayar = $total_tagihan < 50000 ? 50000 : 100000; 
                while ($uang_dibayar < $total_tagihan) {
                    $uang_dibayar += 50000;
                }
                $kembalian = $uang_dibayar - $total_tagihan;
                $status_bayar = 'lunas';
            } else {
                // Skenario Belum Bayar
                $uang_dibayar = 0;
                $kembalian = 0;
                $status_bayar = 'belum_bayar';
            }

            $stmt_keuangan->execute([
                $rm_id, $biaya_layanan, $biaya_obat, $total_tagihan, $uang_dibayar, $kembalian, $status_bayar
            ]);
        }
    }
    echo "- Berhasil membuat 100 riwayat rekam medis (Kunjungan) beserta data resep dan keuangannya.<n<br>";

    $pdo->commit();
    echo "<br><strong>PROSES SELESAI.</strong> Semua data dummy berhasil dimasukkan ke database.";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<br><strong>GAGAL:</strong> " . $e->getMessage();
}
