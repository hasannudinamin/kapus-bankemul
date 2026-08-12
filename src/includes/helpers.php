<?php

// The "Escape" Helper (XSS Protection)
// Writing htmlspecialchars() over and over is exhausting and easy to misspell.
// This shortens it to just e($string) and handles null values gracefully.
function e($string)
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// The "Redirect" Helper
// Forgetting to write 'exit;' after a header redirect is the #1 cause of ghost bugs in PHP.
// This function guarantees the script stops executing immediately.
function redirect($url)
{
    header("Location: " . $url);
    exit;
}

/**
 * Mencatat riwayat perubahan pada data terapi / resep pasien.
 *
 * @param PDO $pdo Objek koneksi database
 * @param int $id_pasien ID Pasien yang bersangkutan
 * @param int|null $id_resep ID Resep yang ditambah/diubah/dihapus
 * @param string $aksi Tipe aksi ('CREATE', 'UPDATE', 'DELETE')
 * @param string $detail Deskripsi riwayat dalam bentuk kalimat
 */
function catat_log($pdo, $id_pasien, $id_resep, $aksi, $detail) {
    // Menangkap identitas pengguna dari session yang sedang login
    $username = $_SESSION['username'] ?? 'Sistem';
    
    // Menangkap waktu saat ini menggunakan fungsi PHP yang sudah berada di zona Asia/Jakarta
    $waktu_sekarang = date('Y-m-d H:i:s');
    
    try {
        // Menambahkan kolom 'waktu' secara eksplisit ke dalam kueri
        $stmt = $pdo->prepare("INSERT INTO log_resep (id_pasien, id_resep, username, aksi, detail, waktu) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_pasien, $id_resep, $username, $aksi, $detail, $waktu_sekarang]);
    } catch (PDOException $e) {
        // Silent fail: Jika proses pencatatan log gagal, jangan hentikan aplikasi utama
        // namun catat error tersebut ke dalam log error internal server (Apache/PHP).
        error_log("Gagal mencatat log_resep: " . $e->getMessage());
    }
}

if (!function_exists('hitung_umur')) {
    function hitung_umur(?string $tanggalLahir, string $suffix = 'Tahun'): string
    {
        if (empty($tanggalLahir)) {
            return '-';
        }

        try {
            $birthDate = new DateTime($tanggalLahir);
            $today = new DateTime('today');

            return $birthDate->diff($today)->y . ' ' . $suffix;
        } catch (Exception $e) {
            return e($tanggalLahir);
        }
    }
}
