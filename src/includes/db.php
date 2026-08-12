<?php

date_default_timezone_set('Asia/Jakarta');

// Load global helper functions
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/references.php';

// Define the SQLite database file path inside the Podman container
$db_file = '/var/www/db/ira.sqlite';

try {
    // Initialize PDO connection for SQLite
    $pdo = new PDO("sqlite:" . $db_file);
    // Set PDO to throw exceptions on errors
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("PRAGMA foreign_keys = ON;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        nama_lengkap TEXT,
        role TEXT DEFAULT 'pegawai',
        remember_token TEXT
    )");

    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hash, 'Administrator', 'admin']);
    }
} catch (PDOException $e) {
    // Terminate execution and display error if connection fails
    die("Database connection failed: " . $e->getMessage());
}
