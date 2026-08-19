<?php
/**
 * Script Generator Data Dummy untuk Aplikasi Klinik/Puskesmas
 * Menyesuaikan dengan skema database baru (Multi-batch & FEFO).
 */

// Memastikan skrip HANYA bisa dijalankan melalui Command Line (CLI)
if (php_sapi_name() !== 'cli') {
    die("Akses Ditolak: Script ini hanya dapat dijalankan melalui Command Line (CLI).\n");
}

require 'includes/db.php'; // Memuat koneksi $pdo[cite: 13]

// Pastikan skrip berjalan cukup lama tanpa timeout[cite: 13]
set_time_limit(300);

echo "Memulai proses pembuatan data dummy...\n";

try {
    $pdo->beginTransaction();

    // 1. Pastikan User Admin (ID 1) Tersedia[cite: 13]
    $stmt_user =$pdo->query("SELECT id FROM users LIMIT 1");
    $user_id =$stmt_user->fetchColumn() ?: 1;

    // 2. Generate Data Obat, Batch, dan Log Stok Masuk
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

    // Kolom stok di tabel obat di-set 0 di awal, akan di-update dari batch[cite: 12]
    $stmt_obat =$pdo->prepare("INSERT INTO obat (kode_obat, nama_obat, satuan, stok, harga_satuan) VALUES (?, ?, ?, 0, ?)");
    $stmt_batch =$pdo->prepare("INSERT INTO obat_batch (obat_id, no_batch, tanggal_kedaluwarsa, stok_batch, harga_beli) VALUES (?, ?, ?, ?, ?)");
    $stmt_log_masuk =$pdo->prepare("INSERT INTO log_stok (obat_id, obat_batch_id, user_id, jenis_transaksi, jumlah, keterangan) VALUES (?, ?, ?, 'masuk', ?, 'Stok Awal Dummy')");
    $stmt_update_stok_utama =$pdo->prepare("UPDATE obat SET stok = stok + ? WHERE id = ?");
    
    $obat_ids = [];
    foreach ($obat_list as$obat) {
        // Insert Master Obat
        $stmt_obat->execute([$obat[0], $obat[1],$obat[2], $obat[4]]);$new_obat_id = $pdo->lastInsertId();$obat_ids[] = [
            'id' => $new_obat_id,
            'harga_satuan' => $obat[4]
        ];

        // Insert Batch Obat (Simulasi Restock Awal)
        $qty_awal = $obat[3];$harga_beli = $obat[4] * 0.7; // Harga beli diasumsikan 70\% dari harga jual
        $ed = date('Y-m-d', strtotime('+' . rand(12, 36) . ' months'));
        $no_batch = 'BCH-' . date('Y') . '-' . rand(100, 999);
        
        $stmt_batch->execute([$new_obat_id, $no_batch,$ed, $qty_awal,$harga_beli]);
        $new_batch_id =$pdo->lastInsertId();

        // Insert Log Stok
        $stmt_log_masuk->execute([$new_obat_id,$new_batch_id, $user_id,$qty_awal]);

        // Update Stok Master
        $stmt_update_stok_utama->execute([$qty_awal,$new_obat_id]);
    }
    echo "- Berhasil membuat " . count($obat_list) . " data master obat beserta batch awalnya.\n";

    // 3. Generate 30 Data Pasien[cite: 13]
    $nama_depan = ['Budi', 'Siti', 'Agus', 'Ayu', 'Eko', 'Dewi', 'Joko', 'Ratna', 'Rizky', 'Putri', 'Hendra', 'Nita'];
    $nama_belakang = ['Santoso', 'Wijaya', 'Setiawan', 'Lestari', 'Saputra', 'Indah', 'Kurniawan', 'Sari'];$alamat_list = ['Jl. Merdeka No. 1', 'Jl. Sudirman Blok B', 'Perumahan Indah 2', 'Ds. Suka Maju RT 3', 'Jl. Diponegoro No 45'];
    
    $stmt_pasien =$pdo->prepare("INSERT INTO pasien (no_rm, nama_lengkap, tanggal_lahir, jenis_kelamin, alamat, no_telp) VALUES (?, ?, ?, ?, ?, ?)");
    $pasien_ids = [];
    
    for ($i = 1; $i <= 30; $i++) {
        $no_rm = 'RM-' . date('Ym') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $nama =$nama_depan[array_rand($nama_depan)] . ' ' .$nama_belakang[array_rand($nama_belakang)];$tgl_lahir = date('Y-m-d', strtotime('-' . rand(10, 65) . ' years -' . rand(1, 12) . ' months'));
        $jk = rand(0, 1) ? 'L' : 'P';$alamat = $alamat_list[array_rand($alamat_list)];
        $no_telp = '0812' . rand(10000000, 99999999);$stmt_pasien->execute([$no_rm,$nama, $tgl_lahir,$jk, $alamat,$no_telp]);
        $pasien_ids[] =$pdo->lastInsertId();
    }
    echo "- Berhasil membuat 30 data pasien.\n";

    // 4. Generate 150 Data Rekam Medik (05 Agustus 2026 - 17 Agustus 2026)
    $keluhan_list = ['Demam dan pusing sejak 2 hari yang lalu', 'Batuk berdahak dan pilek', 'Nyeri perut bagian bawah', 'Gatal-gatal pada lipatan kulit', 'Sakit kepala sebelah (migrain)', 'Badan lemas dan mual'];
    $diagnosa_list = ['R50 - Fever of other and unknown origin', 'J00 - Acute nasopharyngitis', 'K30 - Functional dyspepsia', 'L20 - Atopic dermatitis', 'G43 - Migraine'];$tindakan_list = ['Pemeriksaan fisik umum', 'Pemberian resep obat', 'Observasi TTV', 'Konseling diet dan gizi'];
    
    $stmt_rm =$pdo->prepare("INSERT INTO rekam_medik (pasien_id, user_id, tanggal_kunjungan, status_layanan, keluhan, diagnosa, tindakan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_resep =$pdo->prepare("INSERT INTO resep (rekam_medik_id, obat_id, jumlah, aturan_pakai, harga_jual) VALUES (?, ?, ?, ?, ?)");
    $stmt_keuangan =$pdo->prepare("INSERT INTO keuangan (rekam_medik_id, biaya_layanan, biaya_obat, total_tagihan, uang_dibayar, kembalian, status_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Prepared Statements untuk Algoritma FEFO[cite: 12]
    $stmt_get_batches =$pdo->prepare("SELECT id, stok_batch, harga_beli FROM obat_batch WHERE obat_id = ? AND stok_batch > 0 ORDER BY tanggal_kedaluwarsa ASC");
    $stmt_update_batch =$pdo->prepare("UPDATE obat_batch SET stok_batch = stok_batch - ? WHERE id = ?");
    $stmt_insert_detail =$pdo->prepare("INSERT INTO resep_detail_batch (resep_id, obat_batch_id, jumlah, harga_beli) VALUES (?, ?, ?, ?)");
    $stmt_insert_log_keluar =$pdo->prepare("INSERT INTO log_stok (obat_id, obat_batch_id, user_id, jenis_transaksi, jumlah, keterangan) VALUES (?, ?, ?, 'keluar', ?, 'Penjualan via resep (Dummy)')");
    $stmt_update_stok =$pdo->prepare("UPDATE obat SET stok = stok - ? WHERE id = ?");

    // Menentukan rentang batas waktu
    $start_date = strtotime('2026-08-05 07:00:00');$end_date = strtotime('2026-08-17 21:00:00');

    for ($i = 1; $i <= 150; $i++) {$p_id = $pasien_ids[array_rand($pasien_ids)];
        // Generate tanggal acak di antara rentang waktu yang ditentukan
        $tgl_kunjungan = date('Y-m-d H:i:s', rand($start_date,$end_date));
        
        // Memilih status dengan probabilitas (mayoritas selesai)[cite: 13]
        $rand_status = rand(1, 100);
        if ($rand_status <= 5)$status = 'menunggu';
        elseif ($rand_status <= 10)$status = 'pemeriksaan';
        elseif ($rand_status <= 20)$status = 'farmasi';
        elseif ($rand_status <= 30)$status = 'pembayaran';
        else $status = 'selesai';

        $keluhan = ($status !== 'menunggu') ? $keluhan_list[array_rand($keluhan_list)] : '';
        $diagnosa = ($status !== 'menunggu') ? $diagnosa_list[array_rand($diagnosa_list)] : '';
        $tindakan = ($status !== 'menunggu') ? $tindakan_list[array_rand($tindakan_list)] : '';

        $stmt_rm->execute([$p_id, $user_id,$tgl_kunjungan, $status,$keluhan, $diagnosa,$tindakan]);
        $rm_id =$pdo->lastInsertId();

        // Jika status farmasi, pembayaran, atau selesai, buat data resep[cite: 13]
        $biaya_obat = 0;
        if (in_array($status, ['farmasi', 'pembayaran', 'selesai'])) {
            $jml_obat_diresepkan = rand(1, 3);$obat_dipilih = (array)array_rand($obat_ids,$jml_obat_diresepkan);
            
            foreach ($obat_dipilih as$idx) {
                $obat =$obat_ids[$idx];$jumlah = rand(2, 10);
                $harga_jual =$obat['harga_satuan'];
                $aturan = rand(2, 3) . ' x 1 Sesudah Makan';$stmt_resep->execute([$rm_id,$obat['id'], $jumlah,$aturan, $harga_jual]);$resep_id = $pdo->lastInsertId();$biaya_obat += ($jumlah * $harga_jual);

                // Jika status sudah melewati farmasi, eksekusi pemotongan stok FEFO
                if (in_array($status, ['pembayaran', 'selesai'])) {
                    $jumlah_sisa =$jumlah;
                    
                    $stmt_get_batches->execute([$obat['id']]);
                    $batches =$stmt_get_batches->fetchAll();

                    foreach ($batches as$batch) {
                        if ($jumlah_sisa <= 0) break;

                        $potong = min($jumlah_sisa, (int)$batch['stok_batch']);$stmt_update_batch->execute([$potong,$batch['id']]);
                        $stmt_insert_detail->execute([$resep_id, $batch['id'],$potong, $batch['harga_beli']]);$stmt_insert_log_keluar->execute([$obat['id'],$batch['id'], $user_id,$potong]);
                        
                        $jumlah_sisa -=$potong;
                    }

                    $stmt_update_stok->execute([$jumlah,$obat['id']]);
                }
            }
        }

        // Jika status pembayaran atau selesai, buat data keuangan[cite: 13]
        if (in_array($status, ['pembayaran', 'selesai'])) {$biaya_layanan = 25000;
            $total_tagihan = $biaya_layanan +$biaya_obat;
            
            if ($status === 'selesai') {
                $uang_dibayar =$total_tagihan < 50000 ? 50000 : 100000; 
                while ($uang_dibayar <$total_tagihan) {
                    $uang_dibayar += 50000;                 }$kembalian = $uang_dibayar -$total_tagihan;
                $status_bayar = 'lunas';
            } else {
                $uang_dibayar = 0;
                $kembalian = 0;
                $status_bayar = 'belum_bayar';
            }

            $stmt_keuangan->execute([$rm_id, $biaya_layanan,$biaya_obat, $total_tagihan,$uang_dibayar, $kembalian,$status_bayar
            ]);
        }
    }
    echo "- Berhasil membuat 150 riwayat kunjungan (05-17 Agustus 2026) beserta implementasi FEFO.\n";

    $pdo->commit();
    echo "\nPROSES SELESAI. Semua data dummy berhasil dimasukkan ke database.\n";

} catch (Exception $e) {$pdo->rollBack();
    echo "\nGAGAL: " . $e->getMessage() . "\n";
}
