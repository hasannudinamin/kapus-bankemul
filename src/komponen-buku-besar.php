<?php
/** @var \PDO $pdo */
require 'includes/auth-check.php';
require 'includes/db.php';

// Menangkap parameter filter dan halaman
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

// Konfigurasi Lazy Loading
$limit = 20;
$offset = ($page - 1) * $limit;

// Kueri Transaksi dengan kolom total_hpp_obat
$sql_trans = "
    SELECT k.id AS keuangan_id, rm.id AS id_rm, rm.tanggal_kunjungan, p.nama_lengkap, p.no_rm, 
           k.biaya_layanan, k.biaya_obat, k.total_tagihan, k.total_hpp_obat, k.status_pembayaran 
    FROM keuangan k 
    JOIN rekam_medik rm ON k.rekam_medik_id = rm.id 
    JOIN pasien p ON rm.pasien_id = p.id 
    WHERE date(rm.tanggal_kunjungan) BETWEEN ? AND ?
";
$params = [$start_date, $end_date];

if (!empty($search)) {
    $sql_trans .= " AND (p.nama_lengkap LIKE ? OR p.no_rm LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql_trans .= " ORDER BY rm.tanggal_kunjungan DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt_trans = $pdo->prepare($sql_trans);
$stmt_trans->execute($params);
$transactions = $stmt_trans->fetchAll();

// Jika data kosong
if (empty($transactions)) {
    // Hanya tampilkan pesan kosong jika ini adalah permintaan halaman pertama
    if ($page === 1) {
        echo '<tr><td colspan="9" class="has-text-centered has-text-grey is-italic py-4">Tidak ada transaksi ditemukan pada rentang waktu atau pencarian ini.</td></tr>';
    }
    // Jika halaman > 1 kosong, biarkan respon kosong untuk menghentikan lazy loading
    exit;
}

$count = count($transactions);

foreach ($transactions as $index => $trx) {
    $is_lunas = strtolower($trx['status_pembayaran']) === 'lunas';
    
    // Perhitungan Margin Laba
    $margin = ($trx['biaya_layanan'] + $trx['biaya_obat']) - (float)$trx['total_hpp_obat'];
    $margin_color = $margin >= 0 ? 'has-text-success' : 'has-text-danger';
    
    // Logika Pemicu Lazy Loading HTMX
    // Jika iterasi saat ini adalah baris terakhir dari hasil query DAN jumlah hasil sama dengan limit,
    // itu menandakan kemungkinan masih ada data di halaman selanjutnya.
    $is_last_row = ($index === $count - 1) && ($count === $limit);
    
    $tr_attributes = '';
    if ($is_last_row) {
        $next_page = $page + 1;
        // Membangun URL query string dengan parameter yang sama ditambah halaman berikutnya
        $query_string = http_build_query([
            'start' => $start_date,
            'end' => $end_date,
            'q' => $search,
            'page' => $next_page
        ]);
        
        // Pemicu 'revealed' aktif saat elemen muncul di layar (di-scroll)
        // 'afterend' akan menyisipkan hasil halaman berikutnya tepat di bawah baris ini
        $tr_attributes = ' hx-get="komponen-buku-besar.php?' . $query_string . '" hx-trigger="revealed" hx-swap="afterend"';
    }

    echo '<tr' . $tr_attributes . '>';
    echo '<td class="is-vcentered">' . date('d/m/Y H:i', strtotime($trx['tanggal_kunjungan'])) . '</td>';
    echo '<td class="is-vcentered">TRX-' . str_pad($trx['keuangan_id'], 5, '0', STR_PAD_LEFT) . '</td>';
    echo '<td class="is-vcentered"><strong>' . e($trx['nama_lengkap']) . '</strong><br><span class="is-size-7 has-text-grey">' . e($trx['no_rm']) . '</span></td>';
    echo '<td class="is-vcentered has-text-right">Rp ' . number_format($trx['biaya_layanan'], 0, ',', '.') . '</td>';
    echo '<td class="is-vcentered has-text-right">Rp ' . number_format($trx['biaya_obat'], 0, ',', '.') . '</td>';
    echo '<td class="is-vcentered has-text-right has-text-weight-bold">Rp ' . number_format($trx['total_tagihan'], 0, ',', '.') . '</td>';
    
    // Kolom Margin Laba
    echo '<td class="is-vcentered has-text-right has-text-weight-bold ' . $margin_color . '">Rp ' . number_format($margin, 0, ',', '.') . '</td>';
    
    echo '<td class="is-vcentered has-text-centered">';
    echo $is_lunas ? '<span class="tag is-success is-light">Lunas</span>' : '<span class="tag is-warning is-light">Belum Bayar</span>';
    echo '</td>';
    echo '<td class="is-vcentered has-text-right">';
    if ($is_lunas) {
        echo '<a href="cetak-struk.php?id_rm=' . e($trx['id_rm']) . '" target="_blank" class="button is-small is-info is-light" title="Cetak Ulang Struk"><span class="icon"><i class="mdi mdi-printer"></i></span></a>';
    } else {
        echo '<a href="konfirmasi-biaya.php?id_rm=' . e($trx['id_rm']) . '" class="button is-small is-primary" title="Proses Kasir">Bayar</a>';
    }
    echo '</td>';
    echo '</tr>';
}
?>
