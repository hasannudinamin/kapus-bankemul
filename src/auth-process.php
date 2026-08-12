<?php

session_start();
require 'includes/db.php';

// Retrieve submitted credentials, defaulting to empty strings if not set
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
// Check if the Remember Me checkbox was submitted
$remember = isset($_POST['remember']);

// Fetch user data from the database using a prepared statement to prevent SQL injection
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

// Verify the user exists and the submitted password matches the stored hash
if ($user && password_verify($password, $user['password'])) {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Set standard session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // Process the Remember Me token logic
    if ($remember) {
        // Generate a 64-character cryptographically secure random string
        $token = bin2hex(random_bytes(32));

        // Hash the token before storing it in the database for extra security
        $hashed_token = hash('sha256', $token);

        // Update the user record with the hashed token
        $update_stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $update_stmt->execute([$hashed_token, $user['id']]);

        // Store the plain token in a browser cookie valid for 30 days
        // Parameters: name, value, expiration time, path, domain, secure, httponly
        setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
    }

    // Redirect to the main application page
    redirect("index.php");
} else {
    // Redirect back to the login page with an error flag
    redirect("login.php?error=1");
}
exit;
