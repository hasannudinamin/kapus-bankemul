<?php
require 'includes/auth-check.php';
require 'includes/db.php';

// Menentukan aksi komponen, default ke tampilan awal (reset)[cite: 10]
$action = $_GET['action'] ?? 'reset';

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');

    // Jangan tampilkan dropdown jika input kosong
    if (strlen($q) < 1) {
        // echo ' ';
        exit;
    }

    // Mengambil data obat beserta stok
    $stmt = $pdo->prepare("SELECT id, kode_obat, nama_obat, satuan, stok FROM obat WHERE nama_obat LIKE ? OR kode_obat LIKE ? ORDER BY nama_obat ASC LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    $obat_list = $stmt->fetchAll();

    if ($obat_list) {
        echo '<div class="dropdown-menu" style="display: block; width: 100%; top: 100%; left: 0; padding-top: 4px; position: absolute; z-index: 1000;">';
        echo '<div class="dropdown-content has-background-white" style="max-height: 250px; overflow-y: auto; border: 1px solid #dbdbdb; padding: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
        
        foreach ($obat_list as $obat) {
            $stok = (int)$obat['stok'];
            $is_empty = $stok <= 0;
            $is_low = $stok > 0 && $stok <= 10;
            
            // Konfigurasi warna peringatan stok
            $stock_color = 'has-text-grey';
            if ($is_empty) {
                $stock_color = 'has-text-danger has-text-weight-bold';
            } elseif ($is_low) {
                $stock_color = 'has-text-warning-dark has-text-weight-bold';
            }

            if ($is_empty) {
                // Tampilan untuk obat dengan stok kosong (tidak bisa diklik)
                echo '<div class="dropdown-item has-background-white-ter" style="display:flex; flex-direction:column; cursor: not-allowed; opacity: 0.7;">';
                echo '<div><span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-pill"></i></span>';
                echo '<strong>' . e($obat['nama_obat']) . '</strong> <span class="is-size-7 has-text-grey">(' . e($obat['kode_obat']) . ')</span></div>';
                echo '<div class="is-size-7 ' . $stock_color . ' mt-1">Stok Habis</div>';
                echo '</div>';
            } else {
                // Tampilan untuk obat tersedia (mengganti widget ke mode select via HTMX)[cite: 10]
                echo '<a class="dropdown-item is-clickable obat-item" style="display:flex; flex-direction:column; white-space:normal;" ' .
                     'onmouseenter="document.querySelectorAll(\'.obat-item\').forEach(el => el.classList.remove(\'has-background-link-light\')); this.classList.add(\'has-background-link-light\');" ' .
                     'hx-get="komponen-cari-obat.php?action=select&id=' . e($obat['id']) . '" hx-target="#obat-widget" hx-swap="outerHTML">';
                echo '<div><span class="icon is-small has-text-link mr-1 mt-1"><i class="mdi mdi-pill"></i></span>';
                echo '<strong>' . e($obat['nama_obat']) . '</strong> <span class="is-size-7 has-text-grey">(' . e($obat['kode_obat']) . ')</span></div>';
                echo '<div class="is-size-7 ' . $stock_color . ' mt-1">Stok: ' . $stok . ' ' . e($obat['satuan']) . '</div>';
                echo '</a>';
            }
        }
        echo '</div></div>';
    } else {
        echo '<div class="dropdown-menu" style="display: block; width: 100%; top: 100%; left: 0; padding-top: 4px; position: absolute; z-index: 1000;">';
        echo '<div class="dropdown-content has-background-white" style="border: 1px solid #dbdbdb; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
        echo '<div class="dropdown-item has-text-grey is-italic">Obat tidak ditemukan</div>';
        echo '</div></div>';
    }
    exit;
}

if ($action === 'select') {
    $id = $_GET['id'] ?? '';
    $stmt = $pdo->prepare("SELECT id, nama_obat FROM obat WHERE id = ?");
    $stmt->execute([$id]);
    $obat = $stmt->fetch();

    if ($obat) {
        // Mengembalikan widget dalam mode terkunci beserta input tersembunyi[cite: 10]
        ?>
        <div class="control" id="obat-widget" style="position: relative;">
            <input type="hidden" name="obat_id" value="<?= e($obat['id']) ?>">
            <div class="field has-addons mb-0">
                <div class="control is-expanded">
                    <input class="input is-small has-background-link-light has-text-link has-text-weight-bold" type="text" value="<?= e($obat['nama_obat']) ?>" readonly>
                </div>
                <div class="control">
                    <button type="button" id="btn-reset-obat" class="button is-small is-danger" hx-get="komponen-cari-obat.php?action=reset" hx-target="#obat-widget" hx-swap="outerHTML" title="Ganti Obat">
                        <span class="icon"><i class="mdi mdi-close"></i></span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    exit;
}

if ($action === 'reset') {
    // Mengembalikan widget ke wujud input teks awal[cite: 10]
    ?>
    <div class="control" id="obat-widget" style="position: relative;">
        <div class="control has-icons-left">
            <input class="input is-small is-link-focus" type="text" id="input-cari-obat" name="q" placeholder="Ketik nama obat..." autocomplete="off"
                   hx-get="komponen-cari-obat.php?action=search"
                   hx-target="#hasil-pencarian"
                   hx-trigger="input changed delay:250ms, search"
                   hx-sync="this:replace"
                   hx-swap="innerHTML"> <span class="icon is-small is-left"><i class="mdi mdi-magnify"></i></span>
        </div>
        <div id="hasil-pencarian" style="position: absolute; width: 100%; z-index: 1000; top: 100%; left: 0;"></div>
    </div>
    <?php
    exit;
}
?>
