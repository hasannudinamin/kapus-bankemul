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
        <p class="subtitle is-size-6 has-text-grey mt-1">Pusat unduh laporan dan master data (CSV).</p>
    </div>
</div>

<!-- Navigasi Tabs Bulma -->
<div class="tabs is-boxed is-medium mb-4">
    <ul>
        <li class="tab-link is-active" data-target="tab-obat">
            <a>
                <span class="icon is-small"><i class="mdi mdi-pill"></i></span>
                <span>Master Obat</span>
            </a>
        </li>
        <li class="tab-link" data-target="tab-keuangan">
            <a>
                <span class="icon is-small"><i class="mdi mdi-finance"></i></span>
                <span>Keuangan</span>
            </a>
        </li>
        <li class="tab-link" data-target="tab-klinis">
            <a>
                <span class="icon is-small"><i class="mdi mdi-stethoscope"></i></span>
                <span>Klinis & RM</span>
            </a>
        </li>
    </ul>
</div>

<!-- Kontainer Konten Tabs -->
<div class="box has-background-white-ter" style="min-height: 400px;">
    
    <!-- Tab 1: Master Obat (Aktif) -->
    <div id="tab-obat" class="tab-content">
        <h3 class="title is-5 mb-2">Ekspor Master Data Obat</h3>
        <p class="is-size-7 has-text-grey mb-5">Unduh daftar obat beserta informasi harga dan akumulasi stok saat ini.</p>

        <div class="columns">
            <div class="column is-6-desktop">
                <form action="export.php" method="GET">
                    <input type="hidden" name="jenis" value="master_obat">
                    
                    <div class="field">
                        <label class="label is-small">Filter Kondisi Stok</label>
                        <div class="control has-icons-left">
                            <div class="select is-fullwidth is-success">
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

                    <div class="field mt-5">
                        <button type="submit" class="button is-success">
                            <span class="icon"><i class="mdi mdi-download"></i></span>
                            <span>Download CSV Master Obat</span>
                        </button>
                    </div>
                </form>

            <hr class="my-5" style="background-color: #dbdbdb; height: 1px;">
            
            <h3 class="title is-5 mb-2">Ekspor Kartu Stok (Log)</h3>
            <p class="is-size-7 has-text-grey mb-4">Unduh riwayat pergerakan stok berdasarkan rentang waktu.</p>

            <form action="export.php" method="GET">
                <input type="hidden" name="jenis" value="kartu_stok">
                
                <div class="columns is-mobile mb-0">
                    <div class="column is-6">
                        <div class="field">
                            <label class="label is-small">Dari Tanggal</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="start" value="<?= date('Y-m-01') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="field">
                            <label class="label is-small">Sampai Tanggal</label>
                            <div class="control has-icons-left">
                                <input class="input is-small" type="date" name="end" value="<?= date('Y-m-t') ?>" required>
                                <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small">Jenis Transaksi</label>
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

                <div class="field mt-4">
                    <button type="submit" class="button is-info is-small">
                        <span class="icon"><i class="mdi mdi-download"></i></span>
                        <span>Download CSV Kartu Stok</span>
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

<!-- Tab 2: Keuangan -->
    <div id="tab-keuangan" class="tab-content is-hidden">
        <h3 class="title is-5 mb-2">Ekspor Buku Besar Keuangan</h3>
        <p class="is-size-7 has-text-grey mb-5">Unduh riwayat transaksi, rincian biaya, dan margin laba ke format CSV.</p>

        <div class="columns">
            <div class="column is-6-desktop">
                <form action="export.php" method="GET">
                    <input type="hidden" name="jenis" value="buku_besar">
                    
                    <div class="columns is-mobile mb-0">
                        <div class="column is-6">
                            <div class="field">
                                <label class="label is-small">Dari Tanggal</label>
                                <div class="control has-icons-left">
                                    <input class="input is-small" type="date" name="start" value="<?= date('Y-m-01') ?>" required>
                                    <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="column is-6">
                            <div class="field">
                                <label class="label is-small">Sampai Tanggal</label>
                                <div class="control has-icons-left">
                                    <input class="input is-small" type="date" name="end" value="<?= date('Y-m-t') ?>" required>
                                    <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label is-small">Status Pembayaran</label>
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

                    <div class="field mt-5">
                        <button type="submit" class="button is-primary">
                            <span class="icon"><i class="mdi mdi-download"></i></span>
                            <span>Download CSV Buku Besar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  
<!-- Tab 3: Klinis & RM -->
    <div id="tab-klinis" class="tab-content is-hidden">
        <h3 class="title is-5 mb-2">Ekspor Riwayat Kunjungan</h3>
        <p class="is-size-7 has-text-grey mb-5">Unduh data rekam medis pasien, keluhan, diagnosa, dan status layanan.</p>

        <div class="columns">
            <div class="column is-6-desktop">
                <form action="export.php" method="GET">
                    <input type="hidden" name="jenis" value="riwayat_kunjungan">
                    
                    <div class="columns is-mobile mb-0">
                        <div class="column is-6">
                            <div class="field">
                                <label class="label is-small">Dari Tanggal</label>
                                <div class="control has-icons-left">
                                    <input class="input is-small" type="date" name="start" value="<?= date('Y-m-01') ?>" required>
                                    <span class="icon is-small is-left"><i class="mdi mdi-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="column is-6">
                            <div class="field">
                                <label class="label is-small">Sampai Tanggal</label>
                                <div class="control has-icons-left">
                                    <input class="input is-small" type="date" name="end" value="<?= date('Y-m-t') ?>" required>
                                    <span class="icon is-small is-left"><i class="mdi mdi-calendar-check"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label is-small">Status Pelayanan</label>
                        <div class="control has-icons-left">
                            <div class="select is-fullwidth is-small is-link">
                                <select name="status_layanan">
                                    <option value="semua">Semua Status</option>
                                    <option value="selesai">Selesai</option>
                                    <option value="menunggu">Menunggu</option>
                                </select>
                            </div>
                            <span class="icon is-small is-left"><i class="mdi mdi-clipboard-pulse-outline"></i></span>
                        </div>
                    </div>

                    <div class="field mt-5">
                        <button type="submit" class="button is-link">
                            <span class="icon"><i class="mdi mdi-download"></i></span>
                            <span>Download CSV Riwayat Kunjungan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Vanilla JS untuk mengatur perpindahan Tabs Bulma
    document.addEventListener('DOMContentLoaded', () => {
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');

        tabLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Hapus class is-active dari semua tab
                tabLinks.forEach(item => item.classList.remove('is-active'));
                // Sembunyikan semua konten tab
                tabContents.forEach(content => content.classList.add('is-hidden'));

                // Tambahkan class is-active ke tab yang diklik
                link.classList.add('is-active');
                // Tampilkan konten tab yang sesuai dengan data-target
                const targetId = link.getAttribute('data-target');
                document.getElementById(targetId).classList.remove('is-hidden');
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
