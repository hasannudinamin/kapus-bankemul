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

    // Enforce foreign key constraints
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        nama_lengkap TEXT,
        role TEXT DEFAULT 'pegawai',
        remember_token TEXT
    )");

    // 2. Pasien Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS pasien (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        no_rm TEXT UNIQUE NOT NULL,
        nama_lengkap TEXT NOT NULL,
        tanggal_lahir DATE,
        jenis_kelamin TEXT,
        alamat TEXT,
        no_telp TEXT
    )");

    // 3. Obat Table (Includes constraint to prevent negative stock)
    $pdo->exec("CREATE TABLE IF NOT EXISTS obat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kode_obat TEXT UNIQUE NOT NULL,
        nama_obat TEXT NOT NULL,
        satuan TEXT,
        stok INTEGER DEFAULT 0 CHECK(stok >= 0),
        harga_satuan REAL DEFAULT 0
    )");

    // 4. Rekam Medik Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS rekam_medik (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pasien_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        tanggal_kunjungan DATETIME DEFAULT CURRENT_TIMESTAMP,
        status_layanan TEXT DEFAULT 'menunggu',
        keluhan TEXT,
        diagnosa TEXT,
        tindakan TEXT,
        FOREIGN KEY (pasien_id) REFERENCES pasien(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    )");

    // 5. Resep Table (Ties medicines to a specific medical record)
    $pdo->exec("CREATE TABLE IF NOT EXISTS resep (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rekam_medik_id INTEGER NOT NULL,
        obat_id INTEGER NOT NULL,
        jumlah INTEGER NOT NULL CHECK(jumlah > 0),
        aturan_pakai TEXT,
        FOREIGN KEY (rekam_medik_id) REFERENCES rekam_medik(id) ON DELETE CASCADE,
        FOREIGN KEY (obat_id) REFERENCES obat(id) ON DELETE RESTRICT
    )");

    // 6. Keuangan Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS keuangan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rekam_medik_id INTEGER NOT NULL UNIQUE,
        biaya_layanan REAL DEFAULT 0,
        biaya_obat REAL DEFAULT 0,
        total_biaya REAL DEFAULT 0,
        status_pembayaran TEXT DEFAULT 'belum_bayar',
        FOREIGN KEY (rekam_medik_id) REFERENCES rekam_medik(id) ON DELETE CASCADE
    )");

    // Seed default admin user if the users table is empty
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
