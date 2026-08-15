<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

$id_rm = $_GET['id_rm'] ?? null;

if (!$id_rm) {
    redirect("pelayanan.php");
}

// Mengambil data rekam medis, pasien, dan data keuangan terkait
$stmt = $pdo->prepare("
    SELECT rm.*, p.nama_lengkap, p.no_rm, p.tanggal_lahir, p.jenis_kelamin,
           k.id AS keuangan_id, k.biaya_layanan, k.biaya_obat, k.total_tagihan, k.uang_dibayar, k.kembalian, k.status_pembayaran
    FROM rekam_medik rm 
    JOIN pasien p ON rm.pasien_id = p.id 
    LEFT JOIN keuangan k ON rm.id = k.rekam_medik_id
    WHERE rm.id = ?
");
$stmt->execute([$id_rm]);
$kunjungan = $stmt->fetch();

if (!$kunjungan) {
    die("Data kunjungan tidak ditemukan.");
}

// Cek apakah status sudah selesai / lunas
$is_selesai = strtolower($kunjungan['status_layanan']) === 'selesai';

// Mengambil daftar resep obat
$resep_stmt = $pdo->prepare("
    SELECT r.*, o.kode_obat, o.nama_obat, o.satuan, o.harga_satuan 
    FROM resep r
    JOIN obat o ON r.obat_id = o.id
    WHERE r.rekam_medik_id = ?
    ORDER BY r.id ASC
");
$resep_stmt->execute([$id_rm]);
$resep_list = $resep_stmt->fetchAll();

// Hitung total akumulasi biaya obat
$total_biaya_obat = 0;
foreach ($resep_list as $r) {
    $total_biaya_obat += ($r['jumlah'] * $r['harga_satuan']);
}

// Biaya jasa pemeriksaan dokter bawaan (default)
$biaya_jasa_default = $is_selesai ? (float)$kunjungan['biaya_layanan'] : 25000;

$umur = hitung_umur($kunjungan['tanggal_lahir'], 'Tahun');
$page_title = 'Konfirmasi Biaya & Kasir: ' . e($kunjungan['nama_lengkap']);
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 mb-1 is-size-5-mobile">
            <span class="icon has-text-primary mr-2">
                <i class="mdi mdi-cash-register"></i>
            </span>
            <span>Kasir & Pembayaran</span>
        </h1>
        <h2 class="subtitle is-size-6 has-text-grey mt-0 is-size-7-mobile">
            Pasien: <strong><?= e($kunjungan['nama_lengkap']) ?></strong> | No. RM: <strong><?= e($kunjungan['no_rm']) ?></strong>
        </h2>
    </div>
    <div class="column is-narrow">
        <a class="button is-light" href="pelayanan.php">
            <span class="icon"><i class="mdi mdi-arrow-left"></i></span>
            <span>Kembali ke Antrean</span>
        </a>
    </div>
</div>

<?php if ($is_selesai): ?>
<div class="notification is-success is-light mb-4">
    <span class="icon mr-1"><i class="mdi mdi-check-circle-outline"></i></span>
    <strong>Transaksi Lunas & Selesai!</strong> Pembayaran untuk kunjungan ini telah dikonfirmasi. Anda dapat mencetak struk pembayaran di bawah ini.
</div>
<?php endif; ?>

<div class="columns is-desktop is-variable is-3">
    <!-- KOLOM KIRI: Ringkasan Rincian Biaya -->
    <div class="column is-7-desktop">
        <div class="box box-desktop">
            <h4 class="title is-5 mb-4 has-text-grey-dark border-bottom pb-2">
                <span class="icon"><i class="mdi mdi-receipt"></i></span>
                Rincian Tagihan Layanan
            </h4>

            <div class="table-container mb-4">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Item / Deskripsi</th>
                            <th class="has-text-centered">Qty</th>
                            <th class="has-text-right">Harga Satuan</th>
                            <th class="has-text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Biaya Jasa / Konsultasi -->
                        <tr>
                            <td class="is-vcentered has-text-weight-medium">Jasa Pemeriksaan & Konsultasi Dokter</td>
                            <td class="is-vcentered has-text-centered">1</td>
                            <td class="is-vcentered has-text-right">Rp <span id="text-jasa-satuan"><?= number_format($biaya_jasa_default, 0, ',', '.') ?></span></td>
                            <td class="is-vcentered has-text-right has-text-weight-bold">Rp <span id="text-jasa-subtotal"><?= number_format($biaya_jasa_default, 0, ',', '.') ?></span></td>
                        </tr>

                        <!-- Item Resep Obat -->
                        <?php if (!empty($resep_list)): ?>
                            <?php foreach ($resep_list as $resep): 
                                $subtotal_item = $resep['jumlah'] * $resep['harga_satuan'];
                            ?>
                                <tr>
                                    <td class="is-vcentered">
                                        <?= e($resep['nama_obat']) ?>
                                        <span class="is-size-7 has-text-grey block">(<?= e($resep['aturan_pakai']) ?>)</span>
                                    </td>
                                    <td class="is-vcentered has-text-centered"><?= e($resep['jumlah']) ?> <?= e($resep['satuan']) ?></td>
                                    <td class="is-vcentered has-text-right">Rp <?= number_format($resep['harga_satuan'], 0, ',', '.') ?></td>
                                    <td class="is-vcentered has-text-right has-text-weight-bold">Rp <?= number_format($subtotal_item, 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="has-background-white-ter">
                            <th colspan="3" class="has-text-right is-size-6">Total Tagihan:</th>
                            <th class="has-text-right is-size-5 has-text-primary">
                                Rp <span id="display-total-tagihan"><?= number_format($is_selesai ? $kunjungan['total_tagihan'] : ($total_biaya_obat + $biaya_jasa_default), 0, ',', '.') ?></span>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN: Kalkulator & Form Kasir -->
    <div class="column is-5-desktop">
        <div class="box box-desktop border-primary-top">
            <h4 class="title is-5 mb-4 has-text-grey-dark border-bottom pb-2">
                <span class="icon"><i class="mdi mdi-calculator"></i></span>
                <?= $is_selesai ? 'Ringkasan Transaksi' : 'Proses Pembayaran' ?>
            </h4>

            <?php if (!$is_selesai): ?>
            <form action="aksi-pembayaran.php" method="POST" id="form-pembayaran">
                <input type="hidden" name="id_rm" value="<?= e($id_rm) ?>">
                <input type="hidden" name="biaya_obat" id="input-total-obat" value="<?= $total_biaya_obat ?>">
                
                <div class="field mb-3">
                    <label class="label is-small">Biaya Jasa Dokter (Rp)</label>
                    <div class="control has-icons-left">
                        <input class="input is-primary-focus" type="number" name="biaya_layanan" id="input-biaya-jasa" value="<?= $biaya_jasa_default ?>" min="0" required>
                        <span class="icon is-small is-left"><i class="mdi mdi-doctor"></i></span>
                    </div>
                </div>

                <div class="field mb-3">
                    <label class="label is-small">Total Tagihan (Rp)</label>
                    <div class="control has-icons-left">
                        <input class="input has-background-white-bis has-text-weight-bold has-text-primary is-medium" type="number" name="total_tagihan" id="input-total-tagihan" value="<?= $total_biaya_obat + $biaya_jasa_default ?>" readonly>
                        <span class="icon is-left"><i class="mdi mdi-cash-multiple"></i></span>
                    </div>
                </div>

                <div class="field mb-3">
                    <label class="label is-small">Uang Diterima / Cash (Rp) <span class="has-text-danger">*</span></label>
                    <div class="control has-icons-left">
                        <input class="input is-primary-focus is-medium has-text-weight-bold" type="number" name="uang_dibayar" id="input-uang-dibayar" min="0" required placeholder="0" autofocus>
                        <span class="icon is-left"><i class="mdi mdi-cash"></i></span>
                    </div>
                </div>

                <div class="field mb-5">
                    <label class="label is-small">Kembalian (Rp)</label>
                    <div class="control has-icons-left">
                        <input class="input has-background-white-bis has-text-weight-bold is-medium" type="text" id="input-kembalian" value="Rp 0" readonly>
                        <span class="icon is-left"><i class="mdi mdi-cash-refund"></i></span>
                    </div>
                </div>

                <div class="field">
                    <button type="submit" class="button is-primary is-fullwidth is-medium" id="btn-submit-pembayaran" onclick="return confirm('Konfirmasi penerimaan pembayaran ini?');">
                        <span class="icon"><i class="mdi mdi-check-circle"></i></span>
                        <span>Pelunasan & Simpan Transaksi</span>
                    </button>
                </div>
            </form>
            <?php else: ?>
            <!-- Mode Tampilan Nota Lunas -->
            <div class="content">
                <table class="table is-fullwidth is-borderless mb-4">
                    <tbody>
                        <tr>
                            <td class="has-text-grey">Total Tagihan</td>
                            <td class="has-text-right has-text-weight-bold">Rp <?= number_format($kunjungan['total_tagihan'], 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="has-text-grey">Uang Dibayar</td>
                            <td class="has-text-right has-text-weight-bold">Rp <?= number_format($kunjungan['uang_dibayar'], 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="has-text-grey">Kembalian</td>
                            <td class="has-text-right has-text-weight-bold has-text-success">Rp <?= number_format($kunjungan['kembalian'], 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="has-text-grey">Status</td>
                            <td class="has-text-right"><span class="tag is-success is-light font-weight-bold">LUNAS</span></td>
                        </tr>
                    </tbody>
                </table>

                <a href="cetak-struk.php?id_rm=<?= e($id_rm) ?>" target="_blank" class="button is-info is-fullwidth">
                    <span class="icon"><i class="mdi mdi-printer"></i></span>
                    <span>Cetak Struk Thermal</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$is_selesai): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const totalObat = parseFloat(document.getElementById('input-total-obat').value) || 0;
    const inputBiayaJasa = document.getElementById('input-biaya-jasa');
    const inputTotalTagihan = document.getElementById('input-total-tagihan');
    const displayTotalTagihan = document.getElementById('display-total-tagihan');
    const textJasaSatuan = document.getElementById('text-jasa-satuan');
    const textJasaSubtotal = document.getElementById('text-jasa-subtotal');
    
    const inputUangDibayar = document.getElementById('input-uang-dibayar');
    const inputKembalian = document.getElementById('input-kembalian');
    const btnSubmit = document.getElementById('btn-submit-pembayaran');

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }

    function hitungKalkulasi() {
        const biayaJasa = parseFloat(inputBiayaJasa.value) || 0;
        const totalTagihan = totalObat + biayaJasa;
        
        textJasaSatuan.innerText = formatRupiah(biayaJasa);
        textJasaSubtotal.innerText = formatRupiah(biayaJasa);
        
        inputTotalTagihan.value = totalTagihan;
        displayTotalTagihan.innerText = formatRupiah(totalTagihan);

        const uangDibayar = parseFloat(inputUangDibayar.value) || 0;
        const kembalian = uangDibayar - totalTagihan;

        if (uangDibayar >= totalTagihan) {
            inputKembalian.value = 'Rp ' + formatRupiah(kembalian);
            inputKembalian.classList.remove('has-text-danger');
            inputKembalian.classList.add('has-text-success');
            btnSubmit.disabled = false;
        } else {
            inputKembalian.value = 'Uang Kurang (Rp ' + formatRupiah(Math.abs(kembalian)) + ')';
            inputKembalian.classList.remove('has-text-success');
            inputKembalian.classList.add('has-text-danger');
            btnSubmit.disabled = true;
        }
    }

    inputBiayaJasa.addEventListener('input', hitungKalkulasi);
    inputUangDibayar.addEventListener('input', hitungKalkulasi);

    hitungKalkulasi();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
