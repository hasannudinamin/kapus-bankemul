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
