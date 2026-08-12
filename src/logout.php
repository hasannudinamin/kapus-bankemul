<?php

session_start();
require 'includes/db.php';

// Check if the Remember Me cookie exists in the user's browser
if (isset($_COOKIE['remember_token'])) {
    // Hash the token to identify the correct database record
    $hashed_token = hash('sha256', $_COOKIE['remember_token']);

    // Nullify the token in the database to prevent future use
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$hashed_token]);

    // Delete the browser cookie by setting its expiration time to the past
    setcookie('remember_token', '', time() - 3600, '/');
}

// Remove all session variables and destroy the session completely
session_unset();
session_destroy();

// Redirect the user back to the login page
redirect("login.php");
