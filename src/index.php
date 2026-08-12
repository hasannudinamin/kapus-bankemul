<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$page_title = 'Dashboard - Resep Bankemul';
include 'includes/header.php';
?>

<div class="mb-5">
  <h1 class="title is-size-3 is-size-4-mobile">
        <span class="icon has-text-primary mr-2"><i class="mdi mdi-view-dashboard"></i></span>
        Dashboard
    </h1>
    <h2 class="subtitle is-size-6 has-text-grey">Ringkasan Dashboard</h2>
</div>

<?php include 'includes/footer.php'; ?>
