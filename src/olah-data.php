<?php
require 'includes/auth-check.php';
require 'includes/db.php';

$page_title = 'Olah Data (Export CSV)';
include 'includes/header.php';
?>

<div class="columns is-vcentered mb-5 is-mobile">
    <div class="column">
        <h1 class="title is-size-3 is-size-4-mobile">
            <span class="icon has-text-link mr-2">
                <i class="mdi mdi-database-export"></i>
            </span>
            <span>Ekspor Data Sistem</span>
        </h1>
        <p class="subtitle is-size-6 has-text-grey mt-1">Pusat unduh laporan klinis, finansial, dan inventaris (CSV).</p>
    </div>
</div>

<div class="columns is-multiline is-desktop">
    
    <!-- Box 1: Master Obat -->
    <div class="column is-6-desktop">
        <div class="box has-background-white-ter is-flex is-flex-direction-column" style="height: 100%;">
            <h3 class="title is-5 is-size-6-mobile mb-4">
                <span class="icon has-text-success mr-1"><i class="mdi mdi-pill"></i></span>
                Ekspor Master Data Obat
            </h3>
            <p class="is-size-7 has-text-grey mb-4">Unduh daftar obat beserta informasi harga, satuan, ED terdekat, dan akumulasi stok saat ini.</p>

            <form action="export.php" method="GET" class="is-flex is-flex-direction-column" style="height: 100%;">
                <input type="hidden" name="jenis" value="master_obat">
                
                <div class="field">
                    <label class="label is-small">Filter Kondisi Stok:</label>
                    <div class="control has-icons-left">
                        <div class="select is-fullwidth is-small is-success">
                            <select name="filter_stok">
                                <option value="semua">Semua Obat (Termasuk Kosong)</option>
                                <option value="tersedia">Hanya Stok Tersedia (> 0)</option>
                                <option value="kritis">Stok Kritis (<= 10)</option>
                                <option value="kosong">Stok Kosong (= 0)</option>
                            </select>
                        </div>
                        <span class="icon is-small is-left"><i class="mdi mdi-filter"></i></span>
                    </div>
                </div>

                <div class="field mt-auto pt-4">
                    <button type="submit" class="button is-success is-fullwidth">
                        <span class="icon"><i class="mdi mdi-download"></i></span>
                        <span>Download CSV Master Obat</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Box 2: Kartu Stok -->
    <div class="column is-6-desktop">
        <div class="box has-background-white-ter is-flex is-flex-direction-column" style="height: 100%;">
            <h3 class="title is-5 is-size-6-mobile mb-4">
                <span class="icon has-text-info mr-1"><i class="mdi mdi-clipboard-text-clock"></i></span>
                Ekspor Kartu Stok (Log)
            </h3>
            <p class="is-size-7 has-text-grey mb-4">Unduh riwayat pergerakan fisik inventaris gudang (barang masuk, keluar, dan opname) untuk keperluan audit.</p>

            <form action="export.php" method="GET" class="is-flex is-flex-direction-column" style="height: 100%;">
                <input type="hidden" name="jenis" value="kartu_stok">
                
                <div class="columns is-mobile mb-0">
                    <div class="column is-6 pb-0">
                        <div class="field">
                            <label class="label is-small">Dari Tanggal:</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="start" value="<?= date('Y-m-01') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6 pb-0">
                        <div class="field">
                            <label class="label is-small">Sampai Tanggal:</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="end" value="<?= date('Y-m-t') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small">Jenis Transaksi:</label>
                    <div class="control has-icons-left">
                        <div class="select is-fullwidth is-small is-info">
                            <select name="jenis_transaksi">
                                <option value="semua">Semua Transaksi</option>
                                <option value="masuk">Barang Masuk (Restock)</option>
                                <option value="keluar">Barang Keluar (Resep)</option>
                                <option value="penyesuaian">Penyesuaian (Opname)</option>
                            </select>
                        </div>
                        <span class="icon is-small is-left"><i class="mdi mdi-swap-vertical"></i></span>
                    </div>
                </div>

                <div class="field mt-auto pt-4">
                    <button type="submit" class="button is-info is-fullwidth">
                        <span class="icon"><i class="mdi mdi-download"></i></span>
                        <span>Download CSV Kartu Stok</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Box 3: Buku Besar Keuangan -->
    <div class="column is-6-desktop">
        <div class="box has-background-white-ter is-flex is-flex-direction-column" style="height: 100%;">
            <h3 class="title is-5 is-size-6-mobile mb-4">
                <span class="icon has-text-primary mr-1"><i class="mdi mdi-finance"></i></span>
                Ekspor Buku Besar Keuangan
            </h3>
            <p class="is-size-7 has-text-grey mb-4">Unduh rekapitulasi transaksi kasir, rincian biaya layanan & obat, serta perhitungan margin laba kotor klinik.</p>

            <form action="export.php" method="GET" class="is-flex is-flex-direction-column" style="height: 100%;">
                <input type="hidden" name="jenis" value="buku_besar">
                
                <div class="columns is-mobile mb-0">
                    <div class="column is-6 pb-0">
                        <div class="field">
                            <label class="label is-small">Dari Tanggal:</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="start" value="<?= date('Y-m-01') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6 pb-0">
                        <div class="field">
                            <label class="label is-small">Sampai Tanggal:</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="end" value="<?= date('Y-m-t') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small">Status Pembayaran:</label>
                    <div class="control has-icons-left">
                        <div class="select is-fullwidth is-small is-primary">
                            <select name="status_pembayaran">
                                <option value="semua">Semua Transaksi</option>
                                <option value="lunas">Hanya Lunas</option>
                                <option value="belum_bayar">Hanya Belum Bayar (Piutang)</option>
                            </select>
                        </div>
                        <span class="icon is-small is-left"><i class="mdi mdi-cash-register"></i></span>
                    </div>
                </div>

                <div class="field mt-auto pt-4">
                    <button type="submit" class="button is-primary is-fullwidth">
                        <span class="icon"><i class="mdi mdi-download"></i></span>
                        <span>Download CSV Buku Besar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Box 4: Riwayat Kunjungan -->
    <div class="column is-6-desktop">
        <div class="box has-background-white-ter is-flex is-flex-direction-column" style="height: 100%;">
            <h3 class="title is-5 is-size-6-mobile mb-4">
                <span class="icon has-text-link mr-1"><i class="mdi mdi-stethoscope"></i></span>
                Ekspor Riwayat Kunjungan
            </h3>
            <p class="is-size-7 has-text-grey mb-4">Unduh data rekam medis pasien secara massal, mencakup keluhan, diagnosa klinis, dan detail tindakan.</p>

            <form action="export.php" method="GET" class="is-flex is-flex-direction-column" style="height: 100%;">
                <input type="hidden" name="jenis" value="riwayat_kunjungan">
                
                <div class="columns is-mobile mb-0">
                    <div class="column is-6 pb-0">
                        <div class="field">
                            <label class="label is-small">Dari Tanggal:</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="start" value="<?= date('Y-m-01') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6 pb-0">
                        <div class="field">
                            <label class="label is-small">Sampai Tanggal:</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="end" value="<?= date('Y-m-t') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small">Status Pelayanan:</label>
                    <div class="control has-icons-left">
                        <div class="select is-fullwidth is-small is-link">
                            <select name="status_layanan">
                                <option value="semua">Semua Status</option>
                                <option value="selesai">Selesai (Pulang)</option>
                                <option value="menunggu">Menunggu / Sedang Diproses</option>
                            </select>
                        </div>
                        <span class="icon is-small is-left"><i class="mdi mdi-clipboard-pulse-outline"></i></span>
                    </div>
                </div>

                <div class="field mt-auto pt-4">
                    <button type="submit" class="button is-link is-fullwidth">
                        <span class="icon"><i class="mdi mdi-download"></i></span>
                        <span>Download CSV Kunjungan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
