<?php
// Define the current user's role
$user_role = $_SESSION['role'] ?? 'pegawai';

// --- ADMIN ONLY ACTIONS BELOW ---
// If the user is not an admin, dokter, or perawat, immediately redirect them away
if ($user_role === 'pegawai') {
    redirect("index.php");
}
