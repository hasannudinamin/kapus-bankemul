<?php
/** @var \PDO $pdo */
require_once 'includes/auth-check.php';
require_once 'includes/db.php'; // Menyertakan koneksi database SQLite Anda

// Menangkap parameter 'diagnosa' yang dikirim otomatis oleh input HTMX
$search = trim($_GET['diagnosa'] ?? '');

// Minimal 2 karakter untuk mencegah query database yang terlalu membebani sistem
if (strlen($search) < 2) {
    exit;
}

try {
    // Menyiapkan query untuk mencari kecocokan pada kolom code atau name
    // Dibatasi LIMIT 3 sesuai dengan limit alur kerja bawaan API sebelumnya
    $stmt = $pdo->prepare("
        SELECT code, name 
        FROM icd10cm 
        WHERE code LIKE :search OR name LIKE :search 
        LIMIT 5
    ");
    
    // Menambahkan wildcard % agar pencarian bersifat fleksibel (partial match)
    $searchTerm = '%' . $search . '%';
    $stmt->execute([':search' => $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Penanganan error jika terjadi kegagalan query pada database SQLite
    echo '<div class="panel is-info has-background-white mt-1"><div class="panel-block has-text-danger">Gagal memproses pencarian database ICD-10.</div></div>';
    exit;
}

// Jika data tidak ditemukan di database local
if (empty($results)) {
    echo '<div class="panel is-info has-background-white mt-1"><div class="panel-block has-text-grey">Diagnosa tidak ditemukan.</div></div>';
    exit;
}

echo '<div class="panel is-info has-background-white mt-1" style="box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden;">';

foreach ($results as $item) {
    $code = htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
    
    // Format penggabungan yang akan dimasukkan ke input
    $full_text = $code . ' - ' . $name;
    
    // Ketika item diklik, isi input diagnosa dan hilangkan dropdown ini
    echo '<a class="panel-block item-diagnosa" style="cursor: pointer;" onclick="document.getElementById(\'input-cari-diagnosa\').value = \'' . addslashes($full_text) . '\'; document.getElementById(\'hasil-pencarian-diagnosa\').innerHTML = \'\';">';
    echo '<span class="panel-icon"><i class="mdi mdi-medical-bag has-text-info"></i></span>';
    echo '<strong>' . $code . '</strong>&nbsp;- ' . $name;
    echo '</a>';
}

echo '</div>';
?>
