<?php
require_once __DIR__ . '/includes/helpers.php';
session_start();

// Jika pengguna sudah memiliki session atau token remember me, arahkan ke index
// Biarkan auth-check.php di index yang menangani regenerasi session dari token
if (isset($_SESSION['user_id']) || isset($_COOKIE['remember_token'])) {
    redirect("index.php");
}

$page_title = "Masuk - Resep Bankemul";
include 'includes/header.php';
?>

<div class="columns is-centered">
    <div class="column is-6-desktop is-8-tablet">
    <div class="is-flex is-justify-content-center mb-5">
        <img width="240" src="/img/logo.svg" alt="Resep Bankemul" />
    </div>

    <div class="box">


        <h1 class="title is-4 mb-5">
            <span class="icon has-text-primary"><i class="mdi mdi-login-variant"></i></span>
            Masuk
        </h1>

        <?php if (isset($_GET['error'])) : ?>
            <div class='notification is-danger is-light'><span class='icon'><i class='mdi mdi-alert'></i></span> <strong>Gagal!</strong> Username atau password yang Anda masukkan salah.</div>
        <?php endif; ?>
      
        <form action="auth-process.php" method="POST">
            <?php $redirect_target = $_GET['redirect'] ?? ''; ?>
            <input type="hidden" name="redirect" value="<?= e($redirect_target) ?>">
            <div class="field">
                <label class="label" for="username">Nama Pengguna</label>
                <div class="control has-icons-left">
                    <input class="input" type="text" id="username" name="username" placeholder="Masukkan nama pengguna" required>
                    <span class="icon is-left">
                        <i class="mdi mdi-account"></i>
                    </span>
                </div>
            </div>

            <div class="field">
                <label class="label" for="password">Kata Sandi</label>
                <div class="control has-icons-left">
                    <input class="input" type="password" id="password" name="password" placeholder="Masukkan kata sandi" required>
                    <span class="icon is-left">
                        <i class="mdi mdi-lock"></i>
                    </span>
                </div>
            </div>

            <div class="field">
                <div class="control">
                    <label class="checkbox" for="remember">
                        <input type="checkbox" role="switch" id="remember" name="remember">
                        Ingat Saya
                    </label>
                </div>
            </div>

            <div class="field">
                <div class="control has-text-right">
                    <button class="button is-success" type="submit">
                        <span class="icon">
                            <i class="mdi mdi-login"></i>
                        </span>
                        <span>Masuk</span>
                    </button>
                </div>
            </div>
        </form>

    </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
