<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function angka($value, int $decimal = 2): string
{
    return number_format((float)$value, $decimal, ',', '.');
}

// Bulan laporan: default bulan saat ini
$selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$startDate = $selectedMonth . '-01';
$endDate = date('Y-m-d', strtotime($startDate . ' +1 month'));

$monthTimestamp = strtotime($startDate);
$monthTitle = date('F Y', $monthTimestamp);

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
    4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September',
    10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$monthTitle = $monthNames[(int)date('n', $monthTimestamp)]
    . ' ' . date('Y', $monthTimestamp);

// Ringkasan periode
$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_simulasi,
        COALESCE(SUM(waste_ton_day), 0) AS total_sampah,
        COALESCE(SUM(methane_m3_day), 0) AS total_metana,
        COALESCE(SUM(energy_kwh_day), 0) AS total_energi,
        COALESCE(SUM(revenue_month), 0) AS total_pendapatan,
        COALESCE(SUM(opex_month), 0) AS total_opex,
        COALESCE(SUM(profit_month), 0) AS total_laba,
        COALESCE(SUM(capex), 0) AS total_capex,
        AVG(bep_months) AS rata_bep,
        AVG(irr_percent) AS rata_irr
    FROM simulation_history
    WHERE created_at >= :start_date
      AND created_at < :end_date
");

$summaryStmt->execute([
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Data detail untuk bulan yang dipilih
$detailStmt = $pdo->prepare("
    SELECT
        id, created_at, project_name, scenario,
        waste_ton_day, methane_m3_day, energy_kwh_day,
        revenue_month, opex_month, profit_month,
        capex, bep_months, npv, irr_percent
    FROM simulation_history
    WHERE created_at >= :start_date
      AND created_at < :end_date
    ORDER BY created_at DESC, id DESC
");

$detailStmt->execute([
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);

$details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

// Tren bulanan: 12 bulan terakhir termasuk bulan laporan
$trendStart = date('Y-m-01', strtotime($startDate . ' -11 months'));
$trendEnd = date('Y-m-d', strtotime($startDate . ' +1 month'));

$trendStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS periode,
        COUNT(*) AS jumlah,
        COALESCE(SUM(revenue_month), 0) AS pendapatan,
        COALESCE(SUM(opex_month), 0) AS opex,
        COALESCE(SUM(profit_month), 0) AS laba
    FROM simulation_history
    WHERE created_at >= :start_date
      AND created_at < :end_date
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY periode ASC
");

$trendStmt->execute([
    ':start_date' => $trendStart,
    ':end_date' => $trendEnd
]);

$trendRows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

$trendMap = [];
foreach ($trendRows as $trendRow) {
    $trendMap[$trendRow['periode']] = $trendRow;
}

$chartLabels = [];
$chartRevenue = [];
$chartOpex = [];
$chartProfit = [];

for ($i = 11; $i >= 0; $i--) {
    $period = date('Y-m', strtotime($startDate . " -$i months"));
    $labelTimestamp = strtotime($period . '-01');

    $chartLabels[] = $monthNames[(int)date('n', $labelTimestamp)]
        . ' ' . date('Y', $labelTimestamp);

    $chartRevenue[] = (float)($trendMap[$period]['pendapatan'] ?? 0);
    $chartOpex[] = (float)($trendMap[$period]['opex'] ?? 0);
    $chartProfit[] = (float)($trendMap[$period]['laba'] ?? 0);
}

$totalSimulasi = (int)($summary['total_simulasi'] ?? 0);
$totalPendapatan = (float)($summary['total_pendapatan'] ?? 0);
$totalOpex = (float)($summary['total_opex'] ?? 0);
$totalLaba = (float)($summary['total_laba'] ?? 0);

$margin = $totalPendapatan != 0
    ? ($totalLaba / $totalPendapatan) * 100
    : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Bulanan <?= h($monthTitle) ?> | WasteIQ</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">

    <style>
        .report-page { max-width: 1500px; margin: 0 auto; }
        .report-heading {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:20px; margin-bottom:25px;
        }
        .report-heading h1 {
            font-family:'Plus Jakarta Sans',sans-serif;
            font-size:clamp(25px,3vw,32px); font-weight:800;
            letter-spacing:-1px; margin:6px 0;
        }
        .report-heading p { color:#7b8981; font-size:13px; margin:0; }
        .report-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .report-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
            padding:11px 16px; min-height:43px; border-radius:11px;
            border:1px solid #e3e9e4; background:#fff; color:#273b30;
            font-size:12px; font-weight:700; text-decoration:none; cursor:pointer;
        }
        .report-btn-primary { background:#16865c; border-color:#16865c; color:white; }
        .report-btn-primary:hover { background:#116d49; color:#fff; }
        .report-period {
            display:flex; align-items:end; gap:10px; flex-wrap:wrap;
            background:#fff; border:1px solid #e7ece8; border-radius:15px;
            padding:15px 18px; margin-bottom:22px;
        }
        .report-period label { display:block; font-size:11px; font-weight:700; color:#69786e; margin-bottom:6px; }
        .report-period .form-control { width:190px; min-height:41px; font-size:13px; border-radius:10px; }
        .report-grid {
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr));
            gap:15px; margin-bottom:20px;
        }
        .report-metric {
            background:#fff; border:1px solid #e7ece8; border-radius:16px;
            padding:19px; min-width:0; box-shadow:0 4px 16px #173e2b05;
        }
        .report-metric-label { color:#7b8981; font-size:11px; font-weight:700; }
        .report-metric-value {
            font-family:'Plus Jakarta Sans',sans-serif; font-size:clamp(17px,1.8vw,25px);
            font-weight:800; letter-spacing:-.7px; margin-top:13px;
            overflow-wrap:anywhere; color:#20392d;
        }
        .report-metric-note { color:#8b9690; font-size:10px; margin-top:6px; }
        .report-section {
            background:#fff; border:1px solid #e7ece8; border-radius:17px;
            margin-bottom:20px; overflow:hidden;
            box-shadow:0 4px 16px #173e2b05;
        }
        .report-section-head {
            padding:21px 23px; display:flex; justify-content:space-between;
            align-items:flex-start; gap:15px; flex-wrap:wrap;
        }
        .report-section-head h2 { font-size:16px; font-weight:800; margin:0 0 5px; }
        .report-section-head p { color:#859189; font-size:11px; margin:0; }
        .report-chip {
            background:#eaf7f0; color:#16865c; border-radius:30px;
            padding:6px 10px; font-size:10px; font-weight:800;
        }
        .report-chart-wrap { padding:0 20px 22px; height:330px; position:relative; }
        .report-table-wrap { overflow-x:auto; }
        .report-table { width:100%; min-width:1050px; border-collapse:collapse; }
        .report-table th {
            background:#f8faf9; color:#79867e; text-transform:uppercase;
            font-size:10px; letter-spacing:.4px; padding:13px 15px;
            white-space:nowrap; border-top:1px solid #edf0ed;
            border-bottom:1px solid #edf0ed;
        }
        .report-table td {
            padding:14px 15px; font-size:11px; border-bottom:1px solid #f0f3f0;
            color:#46564c; vertical-align:middle;
        }
        .report-table tr:last-child td { border-bottom:0; }
        .report-project { font-weight:800; color:#23392d; }
        .report-muted { color:#8d9991; font-size:10px; margin-top:4px; }
        .report-scenario {
            display:inline-block; padding:5px 9px; border-radius:20px;
            background:#eaf7f0; color:#16865c; font-size:10px; font-weight:800;
        }
        .report-profit { color:#16865c !important; font-weight:800; white-space:nowrap; }
        .report-loss { color:#cf4b4b !important; font-weight:800; white-space:nowrap; }
        .report-empty { text-align:center; padding:45px 20px; color:#87938b; font-size:13px; }
        .report-finance {
            display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:15px;
            padding:0 22px 22px;
        }
        .report-finance-item { border:1px solid #edf0ed; background:#fbfcfb; border-radius:13px; padding:16px; }
        .report-finance-label { font-size:11px; color:#7d8982; font-weight:600; }
        .report-finance-value { font-size:19px; font-weight:800; margin-top:8px; overflow-wrap:anywhere; }
        .report-notice {
            background:#f3f8f4; border:1px solid #e0ece3; border-radius:13px;
            padding:15px 18px; color:#637469; font-size:11px; line-height:1.8;
            margin-bottom:20px;
        }
        .report-footer { color:#8c9790; font-size:10px; padding:5px 0 22px; }
        @media(max-width:1050px) {
            .report-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media(max-width:600px) {
            .report-grid { gap:10px; }
            .report-metric { padding:14px; }
            .report-period .form-control { width:100%; }
            .report-period > div { width:100%; }
            .report-actions { width:100%; }
            .report-actions .report-btn { flex:1; }
            .report-finance { grid-template-columns:1fr; }
            .report-section-head { padding:18px 16px; }
            .report-chart-wrap { height:270px; padding:0 10px 18px; }
        }
        @media print {
            @page { size:A4 landscape; margin:12mm; }
            body { background:#fff !important; font-size:10px; }
            .sidebar,.topbar,.report-actions,.report-period,.no-print { display:none !important; }
            .main-content { margin:0 !important; padding:0 !important; }
            .report-page { max-width:none; }
            .report-heading { margin-bottom:15px; }
            .report-heading h1 { font-size:23px; }
            .report-grid { grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; }
            .report-metric { padding:11px; box-shadow:none; }
            .report-metric-value { font-size:16px; }
            .report-section { box-shadow:none; break-inside:avoid; }
            .report-section-head { padding:12px 14px; }
            .report-chart-wrap { height:240px; }
            .report-table { min-width:0; }
            .report-table th,.report-table td { padding:7px 6px; font-size:8px; }
            .report-finance { gap:8px; }
            .report-finance-item { padding:10px; }
            .report-footer { padding-bottom:0; }
            .report-notice { padding:10px; }
            a { text-decoration:none !important; }
        }
    </style>
</head>
<body>
<div class="app-shell">

    <aside class="sidebar">
        <a class="brand" href="index.php">
            <img src="img/logo branding website.png" alt="Logo WasteIQ" class="brand-logo">
            <span class="brand-text">
                WASTE<span class="brand-accent">IQ</span>
                <small>BUSINESS INTELLIGENCE</small>
            </span>
        </a>

        <div class="menu-label">MENU UTAMA</div>
        <nav class="sidebar-nav">
            <a href="index.php" class="menu-link">
                <span class="menu-icon">▦</span> Dashboard
            </a>
            <a href="simulasi.php" class="menu-link">
                <span class="menu-icon">＋</span> Simulasi Biogas
            </a>
            <a href="history.php" class="menu-link">
                <span class="menu-icon">◷</span> Riwayat Simulasi
            </a>
            <a href="report_monthly.php" class="menu-link active">
                <span class="menu-icon">▤</span> Laporan Bulanan
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-status">
                <span class="status-dot"></span>
                Sistem analisis aktif
            </div>
            <small>WasteIQ · Waste to Energy</small>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <div class="eyebrow">WASTE-TO-ENERGY / REPORT CENTER</div>
                <div class="topbar-title">Laporan Analisis Bulanan</div>
            </div>
            <div class="top-actions">
                <div class="date-chip"><?= h(date('d M Y')) ?></div>
            </div>
        </header>

        <div class="report-page">
            <section class="report-heading">
                <div>
                    <div class="eyebrow">BUSINESS INTELLIGENCE REPORT</div>
                    <h1>Laporan Bulanan</h1>
                    <p>Ringkasan operasional dan finansial simulasi biogas untuk <?= h($monthTitle) ?>.</p>
                </div>
                <div class="report-actions">
                    <button type="button" class="report-btn" onclick="window.print()">
                        ⎙ Cetak / Simpan PDF
                    </button>
                    <a href="history.php?month=<?= h($selectedMonth) ?>" class="report-btn">
                        ◷ Riwayat Data
                    </a>
                </div>
            </section>

            <form action="report_monthly.php" method="GET" class="report-period no-print">
                <div>
                    <label for="month">Pilih periode laporan</label>
                    <input class="form-control" type="month" id="month" name="month"
                           value="<?= h($selectedMonth) ?>" required>
                </div>
                <button type="submit" class="report-btn report-btn-primary">Tampilkan Laporan</button>
                <a href="report_monthly.php" class="report-btn">Bulan Ini</a>
            </form>

            <section class="report-grid">
                <article class="report-metric">
                    <div class="report-metric-label">Total Simulasi</div>
                    <div class="report-metric-value">
                        <?= number_format($totalSimulasi, 0, ',', '.') ?>
                    </div>
                    <div class="report-metric-note">Simulasi tersimpan pada periode ini</div>
                </article>

                <article class="report-metric">
                    <div class="report-metric-label">Estimasi Pendapatan</div>
                    <div class="report-metric-value">
                        <?= rupiah($totalPendapatan) ?>
                    </div>
                    <div class="report-metric-note">Penjumlahan nilai pendapatan catatan</div>
                </article>

                <article class="report-metric">
                    <div class="report-metric-label">Biaya Operasional</div>
                    <div class="report-metric-value">
                        <?= rupiah($totalOpex) ?>
                    </div>
                    <div class="report-metric-note">Penjumlahan OPEX catatan</div>
                </article>

                <article class="report-metric">
                    <div class="report-metric-label">Estimasi Laba</div>
                    <div class="report-metric-value" style="color:<?= $totalLaba >= 0 ? '#16865c' : '#cf4b4b' ?>">
                        <?= rupiah($totalLaba) ?>
                    </div>
                    <div class="report-metric-note">Pendapatan dikurangi biaya pada hasil tersimpan</div>
                </article>
            </section>

            <section class="report-section">
                <div class="report-section-head">
                    <div>
                        <h2>Tren Kinerja Finansial</h2>
                        <p>Perbandingan pendapatan, biaya operasional, dan laba dari 12 bulan terakhir.</p>
                    </div>
                    <span class="report-chip">12 Bulan</span>
                </div>
                <div class="report-chart-wrap">
                    <canvas id="monthlyChart" aria-label="Grafik tren finansial bulanan"></canvas>
                </div>
            </section>

            <section class="report-section">
                <div class="report-section-head">
                    <div>
                        <h2>Rangkuman Finansial Periode</h2>
                        <p>Indikator finansial berdasarkan seluruh catatan pada bulan terpilih.</p>
                    </div>
                    <span class="report-chip"><?= h($monthTitle) ?></span>
                </div>
                <div class="report-finance">
                    <div class="report-finance-item">
                        <div class="report-finance-label">Margin Laba Sederhana</div>
                        <div class="report-finance-value">
                            <?= $margin === null ? '—' : angka($margin) . '%' ?>
                        </div>
                    </div>
                    <div class="report-finance-item">
                        <div class="report-finance-label">Rata-rata BEP Tersimpan</div>
                        <div class="report-finance-value">
                            <?= $summary['rata_bep'] !== null
                                ? angka($summary['rata_bep']) . ' bulan'
                                : '—' ?>
                        </div>
                    </div>
                    <div class="report-finance-item">
                        <div class="report-finance-label">Rata-rata IRR Tersimpan</div>
                        <div class="report-finance-value">
                            <?= $summary['rata_irr'] !== null
                                ? angka($summary['rata_irr']) . '%'
                                : '—' ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="report-section">
                <div class="report-section-head">
                    <div>
                        <h2>Detail Simulasi Bulanan</h2>
                        <p>Daftar simulasi yang dibuat selama <?= h($monthTitle) ?>.</p>
                    </div>
                    <span class="report-chip"><?= $totalSimulasi ?> catatan</span>
                </div>

                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal</th>
                            <th>Nama Proyek</th>
                            <th>Skenario</th>
                            <th>Sampah (ton/hari)</th>
                            <th>Metana (m³/hari)</th>
                            <th>Energi (kWh/hari)</th>
                            <th>Pendapatan/bulan</th>
                            <th>OPEX/bulan</th>
                            <th>Laba/bulan</th>
                            <th>BEP</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$details): ?>
                            <tr>
                                <td colspan="11">
                                    <div class="report-empty">
                                        Belum ada data simulasi pada <?= h($monthTitle) ?>.
                                        Silakan pilih periode lain atau simpan simulasi terlebih dahulu.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($details as $index => $row): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= h(date('d/m/Y', strtotime($row['created_at']))) ?></td>
                                    <td>
                                        <div class="report-project"><?= h($row['project_name']) ?></div>
                                        <div class="report-muted">ID #<?= (int)$row['id'] ?></div>
                                    </td>
                                    <td><span class="report-scenario"><?= h($row['scenario']) ?></span></td>
                                    <td><?= angka($row['waste_ton_day'], 3) ?></td>
                                    <td><?= angka($row['methane_m3_day']) ?></td>
                                    <td><?= angka($row['energy_kwh_day']) ?></td>
                                    <td><?= rupiah($row['revenue_month']) ?></td>
                                    <td><?= rupiah($row['opex_month']) ?></td>
                                    <td class="<?= (float)$row['profit_month'] >= 0 ? 'report-profit' : 'report-loss' ?>">
                                        <?= rupiah($row['profit_month']) ?>
                                    </td>
                                    <td>
                                        <?= $row['bep_months'] !== null && (float)$row['bep_months'] > 0
                                            ? angka($row['bep_months']) . ' bln'
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="report-notice">
                <strong>Catatan metodologi:</strong>
                angka pada laporan ini merupakan agregasi catatan simulasi yang dibuat pada periode
                terpilih. Apabila beberapa catatan menggambarkan fasilitas atau proyek yang sama,
                penjumlahan tersebut bukan nilai kapasitas fasilitas aktual. Nilai BEP dan IRR
                merupakan rata-rata dari nilai yang tersimpan, bukan hasil perhitungan ulang
                berdasarkan gabungan seluruh proyek. Laporan ini adalah estimasi awal dan bukan
                pengganti studi kelayakan teknis maupun finansial.
            </div>

            <footer class="report-footer">
                WasteIQ · Waste-to-Energy Business Intelligence
                <span style="float:right">Dicetak: <?= h(date('d/m/Y H:i')) ?></span>
            </footer>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const chartLabels = <?= json_encode(
    $chartLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const chartRevenue = <?= json_encode($chartRevenue) ?>;
const chartOpex = <?= json_encode($chartOpex) ?>;
const chartProfit = <?= json_encode($chartProfit) ?>;

const canvas = document.getElementById('monthlyChart');

if (canvas) {
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: chartRevenue,
                    borderColor: '#16865c',
                    backgroundColor: 'rgba(22, 134, 92, 0.09)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    borderWidth: 2
                },
                {
                    label: 'Biaya Operasional',
                    data: chartOpex,
                    borderColor: '#e9a23b',
                    backgroundColor: 'rgba(233, 162, 59, 0.05)',
                    fill: false,
                    tension: 0.35,
                    pointRadius: 3,
                    borderWidth: 2
                },
                {
                    label: 'Laba',
                    data: chartProfit,
                    borderColor: '#5978d8',
                    backgroundColor: 'rgba(89, 120, 216, 0.05)',
                    fill: false,
                    tension: 0.35,
                    pointRadius: 3,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 18,
                        font: { family: 'DM Sans', size: 11 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' +
                                new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0
                                }).format(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 10 }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('id-ID', {
                                notation: 'compact',
                                maximumFractionDigits: 1
                            }).format(value);
                        },
                        font: { size: 10 }
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>
