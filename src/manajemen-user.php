<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php'; //[cite: 2]
require 'includes/admin-only.php'; //[cite: 3]

$pesan = '';

// Memproses formulir jika ada request POST[cite: 2]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role']; //[cite: 1]

        // Validasi Username (Pastikan tidak bentrok dengan user lain)[cite: 2]
        $stmt_cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_cek->execute([$username]);
        if ($stmt_cek->fetch()) {
            $pesan = "<div class='notification is-danger is-light'><span class='icon'><i class='mdi mdi-alert'></i></span> <strong>Gagal!</strong> Username '$username' sudah terdaftar.</div>";
        } else {
            // Hash password dan eksekusi insert[cite: 1, 2]
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $nama_lengkap, $role]);
            $pesan = "<div class='notification is-success is-light'><span class='icon'><i class='mdi mdi-check-circle'></i></span> <strong>Berhasil!</strong> Pengguna baru ditambahkan.</div>";
        }
    } elseif ($action === 'reset') {
        $user_id_reset = $_POST['user_id'];
        $password_baru = $_POST['password_baru'];
        
        $hash = password_hash($password_baru, PASSWORD_DEFAULT); //[cite: 1]
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id_reset]);
        
        $pesan = "<div class='notification is-success is-light'><span class='icon'><i class='mdi mdi-check-circle'></i></span> <strong>Berhasil!</strong> Password pengguna berhasil direset.</div>";
    } elseif ($action === 'delete') {
        $user_id_del = $_POST['user_id'];
        
        // Mencegah admin menghapus akunnya sendiri yang sedang login
        if ($user_id_del != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id_del]);
            $pesan = "<div class='notification is-success is-light'><span class='icon'><i class='mdi mdi-check-circle'></i></span> <strong>Berhasil!</strong> Pengguna dihapus.</div>";
        } else {
            $pesan = "<div class='notification is-danger is-light'><span class='icon'><i class='mdi mdi-alert'></i></span> <strong>Gagal!</strong> Anda tidak dapat menghapus akun Anda sendiri.</div>";
        }
    }
}

// Mengambil daftar seluruh pengguna dari database
$stmt = $pdo->query("SELECT id, username, nama_lengkap, role FROM users ORDER BY id ASC");
$users_list = $stmt->fetchAll();

$page_title = 'Manajemen Pengguna';
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 mb-1 is-size-5-mobile">
            <span class="icon has-text-warning mr-2">
                <i class="mdi mdi-account-box-multiple"></i>
            </span>
            <span>Manajemen Pengguna</span>
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey mt-0 is-size-7-mobile">
            Pengaturan Hak Akses & Pendaftaran Staff
        </h2>
    </div>
</div>

<?= $pesan ?>

<div class="box box-desktop mb-5">
    <div class="is-flex is-justify-content-space-between is-align-items-center mb-4">
        <h4 class="title is-5 mb-0 has-text-grey-dark">
            <span class="icon"><i class="mdi mdi-account-plus"></i></span>
            Registrasi Pengguna Baru
        </h4>
    </div>

    <!-- Container form meniru style form input resep pasien[cite: 4] -->
    <div class="mb-5 p-4 has-background-white-bis" style="border-radius: 8px;">
        <form action="manajemen-user.php" method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="columns is-mobile is-multiline">
                <div class="column is-3-desktop is-12-mobile is-6-tablet">
                    <div class="field">
                        <label class="label is-small">Nama Lengkap</label>
                        <div class="control has-icons-left">
                            <input class="input is-small" type="text" name="nama_lengkap" placeholder="Nama Lengkap" required>
                            <span class="icon is-small is-left"><i class="mdi mdi-card-account-details-outline"></i></span>
                        </div>
                    </div>
                </div>
                <div class="column is-3-desktop is-12-mobile is-6-tablet">
                    <div class="field">
                        <label class="label is-small">Username</label>
                        <div class="control has-icons-left">
                            <input class="input is-small" type="text" name="username" placeholder="Username login" required>
                            <span class="icon is-small is-left"><i class="mdi mdi-account"></i></span>
                        </div>
                    </div>
                </div>
                <div class="column is-2-desktop is-6-mobile is-4-tablet">
                    <div class="field">
                        <label class="label is-small">Password</label>
                        <div class="control has-icons-left">
                            <input class="input is-small" type="password" name="password" placeholder="***" required>
                            <span class="icon is-small is-left"><i class="mdi mdi-lock"></i></span>
                        </div>
                    </div>
                </div>
                <div class="column is-2-desktop is-6-mobile is-4-tablet">
                    <div class="field">
                        <label class="label is-small">Role / Akses</label>
                        <div class="control has-icons-left">
                            <div class="select is-small is-fullwidth">
                                <select name="role" required>
                                    <option value="pegawai">Pegawai</option>
                                    <option value="perawat">Perawat</option>
                                    <option value="dokter">Dokter</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <span class="icon is-small is-left"><i class="mdi mdi-shield-account"></i></span>
                        </div>
                    </div>
                </div>
                <div class="column is-12-mobile is-2-tablet">
                    <div class="field">
                        <label class="label is-small">&nbsp;</label>
                        <div class="control">
                            <button type="submit" class="button is-primary is-small is-fullwidth">
                                <span class="icon"><i class="mdi mdi-plus"></i></span>
                                <span>Daftarkan</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel daftar pengguna meniru struktur tabel resep pasien[cite: 4] -->
    <div class="table-container">
        <table class="table is-fullwidth is-striped is-hoverable is-size-7-mobile">
            <thead>
                <tr>
                    <th width="30%">Nama Lengkap</th>
                    <th width="25%">Username</th>
                    <th width="20%">Role</th>
                    <th width="25%" class="has-text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $u) : ?>
                    <tr>
                        <td class="is-vcentered has-text-weight-bold"><?= e($u['nama_lengkap']) ?></td>
                        <td class="is-vcentered"><?= e($u['username']) ?></td>
                        <td class="is-vcentered">
                            <?php 
                                $role_class = 'is-light';
                                if ($u['role'] === 'admin') $role_class = 'is-danger is-light';
                                if ($u['role'] === 'dokter') $role_class = 'is-info is-light';
                                if ($u['role'] === 'perawat') $role_class = 'is-success is-light';
                            ?>
                            <span class="tag <?= $role_class ?>"><?= ucfirst(e($u['role'])) ?></span>
                        </td>
                        <td class="is-vcentered has-text-right">
                            <div class="buttons is-right mb-0">
                                
                                <!-- Form inline dengan JavaScript prompt murni untuk Reset Password -->
                                <form action="manajemen-user.php" method="POST" style="display: inline-block;" onsubmit="return handleResetPassword(this, '<?= e($u['username']) ?>');">
                                    <input type="hidden" name="action" value="reset">
                                    <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                    <input type="hidden" name="password_baru" class="input-new-password">
                                    <button type="submit" class="button is-warning is-responsive-icon" title="Reset Password">
                                        <span class="icon"><i class="mdi mdi-lock-reset"></i></span>
                                    </button>
                                </form>
                                
                                <!-- Form Hapus User meniru cara hapus obat[cite: 4] -->
                                <form action="manajemen-user.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini secara permanen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                    <button type="submit" class="button is-danger is-responsive-icon" title="Hapus Pengguna" <?= ($u['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                        <span class="icon"><i class="mdi mdi-trash-can-outline"></i></span>
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Fungsi interaktif ringan untuk meminta password reset baru tanpa reload navigasi rumit
function handleResetPassword(formElement, username) {
    const newPassword = prompt(`Masukkan password baru untuk pengguna: ${username}`);
    
    // Jika admin menekan cancel atau mengosongkan input, batalkan eksekusi
    if (newPassword === null || newPassword.trim() === '') {
        return false; 
    }
    
    formElement.querySelector('.input-new-password').value = newPassword;
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>
