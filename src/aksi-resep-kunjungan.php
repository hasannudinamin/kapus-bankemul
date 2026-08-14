<?php

/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // PROSES: Tambah Resep Baru via HTMX
    if ($action === 'create') {
        $rekam_medik_id = $_POST['rekam_medik_id'] ?? null;
        $obat_id = $_POST['obat_id'] ?? null;
        $aturan_pakai = trim($_POST['aturan_pakai'] ?? '');
        $jumlah = (int)($_POST['jumlah'] ?? 0);

        // Validasi ketersediaan data wajib dan jumlah tidak boleh kurang dari 1
        if (!$rekam_medik_id || !$obat_id || $jumlah <= 0) {
            http_response_code(400);
            exit("Data resep tidak valid.");
        }

        try {
            // Perhatian: Kita HANYA mencatat resep ke tabel. 
            // Pemotongan stok tabel `obat` tidak dilakukan di sini untuk mengizinkan dokter merevisi resep dengan aman.
            $stmt = $pdo->prepare("INSERT INTO resep (rekam_medik_id, obat_id, jumlah, aturan_pakai) VALUES (?, ?, ?, ?)");
            $stmt->execute([$rekam_medik_id, $obat_id, $jumlah, $aturan_pakai]);
            
            $new_id = $pdo->lastInsertId();

            // Mengambil kembali baris resep yang baru saja disimpan beserta nama dan satuan obat
            $stmt_fetch = $pdo->prepare("
                SELECT r.*, o.nama_obat, o.satuan 
                FROM resep r
                JOIN obat o ON r.obat_id = o.id
                WHERE r.id = ?
            ");
            $stmt_fetch->execute([$new_id]);
            $resep = $stmt_fetch->fetch();

            if ($resep) {
                // Memuntahkan sebaris elemen HTML <tr> yang akan langsung disisipkan ke dalam tabel oleh HTMX
                ?>
                <tr id="resep-<?= e($resep['id']) ?>">
                    <td class="is-vcentered has-text-weight-bold"><?= e($resep['nama_obat']) ?></td>
                    <td class="is-vcentered"><?= e($resep['aturan_pakai']) ?></td>
                    <td class="is-vcentered"><?= e($resep['jumlah']) ?> <?= e($resep['satuan']) ?></td>
                    <td class="is-vcentered has-text-right">
                        <button hx-post="aksi-resep-kunjungan.php" 
                                hx-vals='{"action": "delete", "id": "<?= e($resep['id']) ?>"}'
                                hx-target="closest tr" 
                                hx-swap="outerHTML" 
                                hx-confirm="Batalkan pemberian obat ini?"
                                class="button is-danger is-small is-responsive-icon is-light" title="Hapus">
                            <span class="icon"><i class="mdi mdi-trash-can-outline"></i></span>
                        </button>
                    </td>
                </tr>
                <?php
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            exit("Terjadi kesalahan sistem saat menyimpan resep.");
        }
    } 
    
    // PROSES: Hapus Resep (Revisi/Pembatalan Obat oleh Dokter)
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;

        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM resep WHERE id = ?");
                $stmt->execute([$id]);
                
                // Merespons dengan string kosong akan memicu hx-swap="outerHTML" untuk menghapus elemen <tr> secara instan
                echo ''; 
            } catch (Exception $e) {
                http_response_code(500);
                exit("Gagal menghapus resep.");
            }
        }
    }
}
?>
