<?php
require 'includes/auth-check.php';

// Menangkap parameter 'diagnosa' yang dikirim otomatis oleh input HTMX
$search = trim($_GET['diagnosa'] ?? '');

// Minimal 2 karakter untuk mencegah permintaan API yang berlebihan/tidak spesifik
if (strlen($search) < 2) {
    exit;
}

// Membentuk URL API Clinical Tables NLM NIH
// sf dan df digunakan untuk secara spesifik meminta format kembalian [kode, nama_diagnosa]
$url = "https://clinicaltables.nlm.nih.gov/api/icd10cm/v3/search?terms=" . urlencode($search) . "&sf=code,name&df=code,name&maxList=5";

// Menggunakan cURL untuk mengambil data dari API eksternal
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout 5 detik agar aplikasi tidak hang jika API down
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo '<div class="panel is-info has-background-white mt-1"><div class="panel-block has-text-danger">Gagal terhubung ke server API ICD-10.</div></div>';
    exit;
}

$data = json_decode($response, true);

// Sesuai dokumentasi API NLM, data yang relevan berada di indeks ke-3 (array of arrays)
if (!isset($data[3]) || empty($data[3])) {
    echo '<div class="panel is-info has-background-white mt-1"><div class="panel-block has-text-grey">Diagnosa tidak ditemukan.</div></div>';
    exit;
}

echo '<div class="panel is-info has-background-white mt-1" style="box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden;">';

foreach ($data[3] as $item) {
    $code = htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8');
    
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
