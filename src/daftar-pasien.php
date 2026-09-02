<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Mendeteksi apakah permintaan datang dari HTMX untuk Live Search
$is_htmx = isset($_SERVER['HTTP_HX_REQUEST']);

// Konfigurasi parameter pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search . '%';

// Membangun kueri dinamis untuk entitas pasien tanpa batasan paginasi
$data_sql = "SELECT * FROM pasien";
$params = [];

if ($search !== '') {
    // Pencarian mencakup Nama Lengkap atau Nomor RM sesuai skema tabel pasien
    $where_clause = " WHERE nama_lengkap LIKE ? OR no_rm LIKE ?";
    $data_sql .= $where_clause;
    $params[] = $search_param;
    $params[] = $search_param;
}

$data_sql .= " ORDER BY nama_lengkap ASC";

// Eksekusi kueri
$stmt = $pdo->prepare($data_sql);
$stmt->execute($params);
$pasien_list = $stmt->fetchAll();

// Memuat header halaman hanya jika bukan merupakan pemanggilan HTMX
if (!$is_htmx) {
    $page_title = 'Daftar Pasien';
    include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-5-mobile">
            <span class="icon has-text-primary">
                <i class="mdi mdi-account-group"></i>
            </span>
            <span>Daftar Pasien</span>
        </h1>
        <h2 class="subtitle is-size-5 has-text-grey is-size-6-mobile">
            Manajemen Data Rekam Medis
        </h2>
    </div>
    <div class="column is-narrow is-narrow-mobile">
        <div class="is-hidden-desktop">
        <a class="button is-responsive-icon is-primary" href="form-pasien.php">
            <span class="icon-text">
                <span class="icon">
                    <i class="mdi mdi-account-plus"></i>
                </span>
            </span>
        </a>
        </div>
        <div class="is-hidden-touch">
        <a class="button is-primary" href="form-pasien.php">
            <span class="icon-text">
                <span class="icon">
                    <i class="mdi mdi-account-plus"></i>
                </span>
                <span>Pasien Baru</span>
            </span>
        </a>
        </div>
    </div>
</div>

<form method="GET" action="daftar-pasien.php" class="mb-5" id="search-form" onsubmit="event.preventDefault();">
    <div class="field has-addons">
        <div class="control is-expanded has-icons-left">
            <input
                class="input is-size-7-mobile"
                type="search"
                name="search"
                placeholder="Cari berdasarkan nama pasien atau nomor RM (cth: RM-00001)..."
                value="<?= e($search) ?>"
                hx-get="daftar-pasien.php"
                hx-target="#pasien-grid"
                hx-select="#pasien-grid"
                hx-trigger="keyup changed delay:500ms, search"
                aria-label="Pencarian Pasien">
            <span class="icon is-left is-size-7-mobile">
                <i class="mdi mdi-magnify"></i>
            </span>
        </div>
    </div>
</form>

<?php 
} // Penutup blok header HTMX
?>

<!-- Kontainer utama untuk target pembaruan dom HTMX -->
<div id="pasien-grid">
    <?php if (empty($pasien_list)): ?>
        <div class="box has-text-centered py-6">
            <span class="icon is-large has-text-grey-light mb-3">
                <i class="mdi mdi-account-search-outline mdi-48px"></i>
            </span>
            <p class="is-size-5 has-text-grey">
                <?= $search !== '' ? 'Pencarian tidak menemukan hasil.' : 'Belum ada data pasien di sistem.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="columns is-multiline">
            <?php foreach ($pasien_list as $pasien): ?>
                <div class="column is-12-mobile is-6-tablet is-4-desktop is-4-widescreen">
                    <div class="card" style="display: flex; flex-direction: column; height: 100%;">
                        <div class="card-content" style="flex-grow: 1; padding: 1.25rem;">

                            <div class="media mb-3 is-align-items-center">
                                <div class="media-left">
                                    <span class="icon is-large has-text-primary" style="background-color: var(--bulma-primary-light); border-radius: 8px; height: 3rem; width: 3rem;">
                                        <i class="mdi mdi-account-circle-outline mdi-24px"></i>
                                    </span>
                                </div>
                                <div class="media-content" style="overflow: hidden;">
                                    <p class="title is-6 mb-1 is-clipped" title="<?= e($pasien['nama_lengkap']) ?>">
                                        <?= e($pasien['nama_lengkap']) ?>
                                    </p>
                                    <p class="subtitle is-7 has-text-grey mb-0">
                                        No. RM: <strong><?= !empty($pasien['no_rm']) ? e($pasien['no_rm']) : '-' ?></strong>
                                    </p>
                                </div>
                            </div>

                            <div class="content is-small mb-0">
                                <?php
                                // Menghitung umur pasien secara dinamis[cite: 2]
                                $umur = hitung_umur($pasien['tanggal_lahir'], 'Tahun');

                                // Memetakan ikon dan penamaan label untuk nilai jenis kelamin[cite: 1]
                                $jk = $pasien['jenis_kelamin'] ?? '';
                                $icon_jk = $jk === 'P' ? 'mdi-gender-female' : ($jk === 'L' ? 'mdi-gender-male' : 'mdi-account-question-outline');
                                $label_jk = $jk === 'P' ? 'Perempuan' : ($jk === 'L' ? 'Laki-laki' : '-');
                                ?>

                                <p class="mb-3 is-flex is-align-items-center is-flex-wrap-wrap">
                                    <table class="table is-fullwidth is-narrow is-borderless">
                <tbody>
                  <tr>
                  <td>
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi <?= $icon_jk ?>"></i></span> Jenis Kelamin
                  </td>
                    <td>
                                        <span><?= e($label_jk) ?></span>
                    </td>
                  </tr>
                  <tr>
                  <td>
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-calendar-blank-outline"></i></span> Usia
                  </td>
                    <td>
                      <?= $umur ?>
                    </td>
                  </tr>
                  <tr>
                  <td>
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-map-marker-outline"></i></span> Alamat
                  </td>
                    <td>
                                        <span class="is-clipped"><?= !empty($pasien['alamat']) ? e($pasien['alamat']) : 'Alamat Kosong' ?></span>
                    </td>
                  </tr>
                  <tr>
                  <td>
                                        <span class="icon is-small has-text-grey mr-1"><i class="mdi mdi-phone-outline"></i></span> No. Telepon
                  </td>
                    <td>
                                        <span><?= !empty($pasien['no_telp']) ? e($pasien['no_telp']) : '-' ?></span>
                    </td>
                  </tr>
                </tbody>
                                    </table>
                                </p>
                            </div>

                        </div>
                        <footer class="card-footer" style="background-color: #fafafa;">
                            <a href="pasien.php?id=<?= e($pasien['id']) ?>" class="card-footer-item has-text-primary has-text-weight-bold">
                                <span class="icon mr-2"><i class="mdi mdi-clipboard-text-outline"></i></span>
                                Buka Riwayat Pasien
                            </a>
                        </footer>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
// Memuat footer halaman hanya jika bukan merupakan pemanggilan HTMX
if (!$is_htmx) {
    include 'includes/footer.php'; 
} 
?>
