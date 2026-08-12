<?php
// Mencegah error jika session_start() sudah dipanggil di file utama
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Set a default title if the specific page doesn't provide one
$page_title = isset($page_title) ? $page_title : 'Resep Bankemul';

/** @var $hide_nav */
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Completely private, no-tracking drop-in replacement -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=roboto:300,400,500,700,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="/custom.css">
    <link rel="stylesheet" href="/main.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <script 
      src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.10/dist/htmx.min.js" 
      integrity="sha384-H5SrcfygHmAuTDZphMHqBJLc3FhssKjG7w/CeCpFReSfwBWDTKpkzPP8c+cLsK+V" 
      crossorigin="anonymous"></script>
    <link rel="icon" href="/img/puskesmas.svg" type="image/svg+xml">
    <script src="/main.js" defer></script>
    <title><?php echo e($page_title); ?></title>
</head>

<body>
    <?php if (isset($_SESSION['user_id'])) : // Only show navigation if logged in
        ?>
        <?php if (!isset($hide_nav) || $hide_nav !== true) : ?>
            <nav class="navbar has-shadow has-text-weight-bold" role="navigation" aria-label="main navigation">
                <div class="navbar-brand">
                    <a href="/" class="navbar-item"><img src="/img/logo.svg" alt="Aset Bankemul"></a>
                    <a 
                      class="navbar-burger" 
                      role="button" 
                      data-target="navMenu" 
                      aria-label="menu" 
                      aria-expanded="false">
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                    </a>
                </div>
                <div class="navbar-menu" id="navMenu">
                    <div class="navbar-start">
                        <a href="daftar-resep.php" class="navbar-item">
                            <span class="icon-text">
                                <span class="icon has-text-primary">
                                    <i class="mdi mdi-prescription"></i>
                                </span>
                                <span>Resep</span>
                            </span>
                        </a>
                        <a href="daftar-pasien.php" class="navbar-item">
                            <span class="icon-text">
                                <span class="icon has-text-info">
                                    <i class="mdi mdi-account"></i>
                                </span>
                                <span>Pasien</span>
                            </span>
                        </a>
                        <a href="daftar-obat.php" class="navbar-item">
                            <span class="icon-text">
                                <span class="icon has-text-warning">
                                    <i class="mdi mdi-pill"></i>
                                </span>
                                <span>Obat</span>
                            </span>
                        </a>
                        <div class="navbar-item has-dropdown is-hoverable">
                          <a class="navbar-link">
                            Lainnya
                          </a>
                          <div class="navbar-dropdown">
                              <a href="log-resep.php" class="navbar-item">
                                      <span class="icon has-text-grey">
                                          <i class="mdi mdi-history"></i>
                                      </span>
                                      <span>Log Riwayat Resep</span>
                              </a>
                              <a href="grafik-data.php" class="navbar-item">
                                      <span class="icon has-text-primary">
                                          <i class="mdi mdi-chart-bar"></i>
                                      </span>
                                      <span>Grafik Analitik Data</span>
                              </a>
                              <a href="olah-data.php" class="navbar-item">
                                      <span class="icon has-text-link">
                                          <i class="mdi mdi-database-sync"></i>
                                      </span>
                                      <span>Olah Data</span>
                              </a>
                              <?php if ($_SESSION['role'] === 'admin'): ?>
                              <a href="manajemen-user.php" class="navbar-item">
                                      <span class="icon has-text-warning">
                                          <i class="mdi mdi-account-box"></i>
                                      </span>
                                      <span>Manajemen User</span>
                              <?php endif ?>
                              <a href="pengaturan.php" class="navbar-item">
                                      <span class="icon has-text-danger">
                                          <i class="mdi mdi-cog"></i>
                                      </span>
                                      <span>Pengaturan Akun</span>
                              </a>
                          </div>
                        </div>
                        <a href="logout.php" class="navbar-item is-hidden-desktop">
                            <span class="icon-text">
                                <span class="icon">
                                    <i class="mdi mdi-logout"></i>
                                </span>
                                <span>Keluar</span>
                            </span>
                        </a>
                    </div>
                </div>
                <div class="navbar-end is-hidden-touch">
                    <div class="navbar-item">
                        <a href="logout.php" class="button is-light">
                            <span class="icon-text">
                                <span class="icon">
                                    <i class="mdi mdi-logout"></i>
                                </span>
                                <span>Keluar</span>
                            </span>

                        </a>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <section class="section is-small">
        <div class="container">
