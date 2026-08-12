<?php
require_once __DIR__ . '/helpers.php';
session_start();

// Check if the user lacks an active session but possesses a Remember Me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    require_once 'db.php';

    // Retrieve the token from the cookie and hash it to match the database record
    $token_from_cookie = $_COOKIE['remember_token'];
    $hashed_token = hash('sha256', $token_from_cookie);

    // Query the database for a user associated with this hashed token
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$hashed_token]);
    $user = $stmt->fetch();

    if ($user) {
        // Token is valid; reconstruct the session automatically
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
    }
}

// If no valid session exists after checking the cookie, force redirect to login
if (!isset($_SESSION['user_id'])) {
    // Clear the invalid or expired cookie so login.php doesn't try to reuse it
    setcookie('remember_token', '', time() - 3600, '/');

    // Now it is safe to redirect
    redirect('login.php');
    header("Location: " . "login.php");
    exit;
}
