<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$user_id = $_SESSION['user_id'];
$pesan = '';

// Ambil data user saat ini dari database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Data pengguna tidak ditemukan.");
}

// Proses formulir jika ada request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    // 1. Keamanan Utama: Wajib memverifikasi identitas dengan password lama
    if (!password_verify($password_lama, $user['password'])) {
        $pesan = "<div class='notification is-danger is-light'><span class='icon'><i class='mdi mdi-alert'></i></span> <strong>Gagal!</strong> Password lama yang Anda masukkan salah.</div>";
    } else {
        $hash_password = $user['password']; // Default menggunakan password lama
        $validasi_lolos = true;

        // 2. Validasi perubahan password jika kolom password baru diisi
        if (!empty($password_baru)) {
            if ($password_baru !== $konfirmasi_password) {
                $pesan = "<div class='notification is-danger is-light'><span class='icon'><i class='mdi mdi-alert'></i></span> <strong>Gagal!</strong> Konfirmasi password baru tidak cocok.</div>";
                $validasi_lolos = false;
            } else {
                $hash_password = password_hash($password_baru, PASSWORD_DEFAULT);
            }
        }

        // 3. Validasi Username (Pastikan tidak bentrok dengan user lain)
        if ($validasi_lolos && $username !== $user['username']) {
            $stmt_cek = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_cek->execute([$username, $user_id]);
            if ($stmt_cek->fetch()) {
                $pesan = "<div class='notification is-danger is-light'><span class='icon'><i class='mdi mdi-alert'></i></span> <strong>Gagal!</strong> Username '$username' sudah digunakan oleh pengguna lain.</div>";
                $validasi_lolos = false;
            }
        }

        // 4. Eksekusi Update Database jika semua validasi lolos
        if ($validasi_lolos) {
            try {
                $stmt_update = $pdo->prepare("UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id = ?");
                $stmt_update->execute([$nama_lengkap, $username, $hash_password, $user_id]);

                // Perbarui data session
                $_SESSION['username'] = $username;

                // Perbarui variabel $user agar HTML di bawah langsung menampilkan data terbaru
                $user['nama_lengkap'] = $nama_lengkap;
                $user['username'] = $username;
                $user['password'] = $hash_password;

                $pesan = "<div class='notification is-success is-light'><span class='icon'><i class='mdi mdi-check-circle'></i></span> <strong>Berhasil!</strong> Pengaturan akun telah diperbarui.</div>";
            } catch (PDOException $e) {
                $pesan = "<div class='notification is-danger is-light'><strong>Error Database:</strong> " . e($e->getMessage()) . "</div>";
            }
        }
    }
}

$page_title = 'Pengaturan Akun';
include 'includes/header.php';
?>

<div class="columns is-centered">
    <div class="column is-6-desktop is-8-tablet">
        <div class="box box-desktop">
            <h1 class="title is-4 mb-5">
                <span class="icon has-text-danger"><i class="mdi mdi-account-cog"></i></span>
                Pengaturan Akun
            </h1>

            <?= $pesan ?>

            <form action="pengaturan.php" method="POST">
                
                <h2 class="subtitle is-6 has-text-grey-dark mb-3 mt-4 border-bottom">Informasi Dasar</h2>
                
                <div class="field">
                    <label class="label is-small">Nama Lengkap</label>
                    <div class="control has-icons-left">
                        <input class="input" type="text" name="nama_lengkap" value="<?= e($user['nama_lengkap'] ?? '') ?>" required>
                        <span class="icon is-small is-left"><i class="mdi mdi-card-account-details-outline"></i></span>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small">Username</label>
                    <div class="control has-icons-left">
                        <input class="input" type="text" name="username" value="<?= e($user['username']) ?>" required>
                        <span class="icon is-small is-left"><i class="mdi mdi-account"></i></span>
                    </div>
                </div>

                <h2 class="subtitle is-6 has-text-grey-dark mb-3 mt-5 border-bottom">Ubah Password (Opsional)</h2>
                <p class="is-size-7 has-text-grey mb-3">Biarkan kosong jika Anda tidak ingin mengubah password.</p>

                <div class="field">
                    <label class="label is-small">Password Baru</label>
                    <div class="control has-icons-left">
                        <input class="input" type="password" name="password_baru" placeholder="Masukkan password baru...">
                        <span class="icon is-small is-left"><i class="mdi mdi-lock-reset"></i></span>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small">Konfirmasi Password Baru</label>
                    <div class="control has-icons-left">
                        <input class="input" type="password" name="konfirmasi_password" placeholder="Ulangi password baru...">
                        <span class="icon is-small is-left"><i class="mdi mdi-lock-check"></i></span>
                    </div>
                </div>

                <hr>
                
                <div class="notification is-warning is-light is-size-7">
                    <span class="icon"><i class="mdi mdi-shield-key"></i></span>
                    Untuk menyimpan perubahan pada nama, username, maupun password, Anda <strong>wajib</strong> memasukkan password saat ini.
                </div>

                <div class="field mb-5">
                    <label class="label is-small">Password Saat Ini (Wajib)</label>
                    <div class="control has-icons-left">
                        <input class="input is-warning" type="password" name="password_lama" required placeholder="Masukkan password Anda saat ini...">
                        <span class="icon is-small is-left"><i class="mdi mdi-lock"></i></span>
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <button type="submit" class="button is-primary is-fullwidth">
                            <span class="icon"><i class="mdi mdi-content-save"></i></span>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
