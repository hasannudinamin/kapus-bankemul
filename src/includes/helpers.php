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



function arabicToRoman(int $num): string
{
    // Fail-safe for numbers outside the standard Roman numeral range
    if ($num <= 0 || $num > 3999) {
        return '';
    }

    // Define the lookup map of Roman characters to Arabic values
    $map = [
        'M'  => 1000, 'CM' => 900,  'D'  => 500, 'CD' => 400,
        'C'  => 100,  'XC' => 90,   'L'  => 50,  'XL' => 40,
        'X'  => 10,   'IX' => 9,    'V'  => 5,   'IV' => 4,
        'I'  => 1
    ];

    $result = '';

    foreach ($map as $roman => $value) {
        // Determine how many times the current value fits into the number
        $matches = intval($num / $value);
        
        if ($matches > 0) {
            // Append the Roman character string multiplied by the matches
            $result .= str_repeat($roman, $matches);
            // Reduce the number by the total subtracted value
            $num %= $value;
        }
    }

    return $result;
}
