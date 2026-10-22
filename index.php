<?php
require __DIR__ . '/config/db.php';

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Navigasi halaman
|--------------------------------------------------------------------------
*/
$page = $_GET['page'] ?? 'dashboard';

$allowedPages = ['dashboard', 'python'];

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

/*
|--------------------------------------------------------------------------
| Data dashboard
|--------------------------------------------------------------------------
| Query dashboard hanya dijalankan ketika halaman dashboard dibuka.
| Ini mencegah halaman Python bergantung pada query dashboard.
|--------------------------------------------------------------------------
*/
$stats = [
    'total_simulasi' => 0,
    'sampah' => 0,
    'metana' => 0,
    'energi' => 0,
];

$finance = [
    'jumlah' => 0,
    'pendapatan' => 0,
    'opex' => 0,
    'laba' => 0,
];

$recent = [];
$labels = [];
$revenues = [];
$opexValues = [];
$profits = [];

if ($page === 'dashboard') {
    try {
        $stats = $pdo->query("
            SELECT
                COUNT(*) AS total_simulasi,
                COALESCE(SUM(waste_ton_day), 0) AS sampah,
                COALESCE(SUM(methane_m3_day), 0) AS metana,
                COALESCE(SUM(energy_kwh_day), 0) AS energi
            FROM simulation_history
        ")->fetch();

        $bulan = date('Y-m');

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS jumlah,
                COALESCE(SUM(revenue_month), 0) AS pendapatan,
                COALESCE(SUM(opex_month), 0) AS opex,
                COALESCE(SUM(profit_month), 0) AS laba
            FROM simulation_history
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ");

        $stmt->execute([$bulan]);
        $finance = $stmt->fetch();

        $recent = $pdo->query("
            SELECT
                id,
                created_at,
                project_name,
                scenario,
                waste_ton_day,
                methane_m3_day,
                energy_kwh_day,
                profit_month
            FROM simulation_history
            ORDER BY created_at DESC
            LIMIT 6
        ")->fetchAll();

        $monthlyRows = $pdo->query("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS periode,
                SUM(revenue_month) AS pendapatan,
                SUM(opex_month) AS opex,
                SUM(profit_month) AS laba
            FROM simulation_history
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY periode ASC
        ")->fetchAll();

        foreach ($monthlyRows as $row) {
            $labels[] = date(
                'M Y',
                strtotime($row['periode'] . '-01')
            );

            $revenues[] = (float) $row['pendapatan'];
            $opexValues[] = (float) $row['opex'];
            $profits[] = (float) $row['laba'];
        }
    } catch (PDOException $exception) {
        error_log('WasteIQ dashboard: ' . $exception->getMessage());

        http_response_code(500);
        exit(
            'Data dashboard gagal dimuat. ' .
            'Periksa koneksi database dan struktur tabel simulation_history.'
        );
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= $page === 'python'
            ? 'Analisis Python | WasteIQ'
            : 'Dashboard | WasteIQ' ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link href="assets/style.css" rel="stylesheet">

    <?php if ($page === 'dashboard'): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>

    <style>
        .menu-link.active {
            background: rgba(54, 120, 245, .12);
            color: #3678f5;
            font-weight: 600;
        }

        .python-frame-wrap {
            width: 100%;
            min-height: 850px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e8edf4;
            border-radius: 16px;
        }

        .python-frame {
            display: block;
            width: 100%;
            height: 1000px;
            border: 0;
            background: #fff;
        }

        .python-notice {
            padding: 16px 18px;
            margin-bottom: 18px;
            border-radius: 12px;
            background: #eef6ff;
            color: #234d7d;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .python-frame-wrap {
                min-height: 700px;
            }

            .python-frame {
                height: 850px;
            }
        }
    </style>
</head>

<body>
<div class="app-shell">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <img
                src="img/logo branding website.png"
                alt="Logo WasteIQ"
                class="brand-logo"
            >

            <span class="brand-text">
                WASTE<span class="brand-accent">IQ</span>
                <small>BUSINESS INTELLIGENCE</small>
            </span>
        </a>

        <div class="menu-label">MENU UTAMA</div>

        <a
            class="menu-link <?= $page === 'dashboard' ? 'active' : '' ?>"
            href="index.php?page=dashboard"
        >
            <span class="menu-icon">▦</span>
            Dashboard
        </a>

        <a class="menu-link" href="simulasi.php">
            <span class="menu-icon">＋</span>
            Simulasi Bisnis
        </a>

        <a class="menu-link" href="history.php">
            <span class="menu-icon">◷</span>
            Riwayat Simulasi
        </a>

        <a
            class="menu-link <?= $page === 'python' ? 'active' : '' ?>"
            href="index.php?page=python"
        >
            <span class="menu-icon">⚙</span>
            Analisis Python
        </a>

        <div class="menu-label">PELAPORAN</div>

        <a class="menu-link" href="report_monthly.php">
            <span class="menu-icon">▤</span>
            Laporan Bulanan
        </a>

        <div class="sidebar-bottom">
            <div class="status-dot"></div>

            <div>
                <strong>Waste-to-Energy</strong>
                <small>Project workspace</small>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <?php if ($page === 'python'): ?>

            <!-- HALAMAN ANALISIS PYTHON -->
            <header class="topbar">
                <div>
                    <div class="eyebrow">
                        WASTE-TO-ENERGY / PYTHON ANALYTICS
                    </div>

                    <h1>Analisis Python</h1>

                    <p>
                        Analisis teknis dan finansial pengolahan sampah
                        menjadi energi menggunakan Python.
                    </p>
                </div>

                <div class="top-actions">
                    <div class="date-chip">
                        ◷ <?= date('d M Y') ?>
                    </div>

                    <a
                        href="index.php?page=dashboard"
                        class="btn btn-primary"
                    >
                        ← Dashboard
                    </a>
                </div>
            </header>

            <section class="content-card mt-4">
                <div class="card-heading">
                    <div>
                        <h2>Waste-to-Energy Business Intelligence</h2>

                        <p>
                            Model perhitungan produksi metana, energi,
                            pendapatan, biaya operasional, dan kelayakan
                            investasi.
                        </p>
                    </div>
                </div>

                <div class="python-notice">
                    <strong>Integrasi Python aktif melalui Streamlit.</strong>
                    Pastikan server Streamlit berjalan pada port 8501.
                    Jika halaman tidak tampil, jalankan perintah pada
                    petunjuk instalasi di bawah.
                </div>

                <div class="python-frame-wrap">
                    <iframe
                        class="python-frame"
                        src="http://localhost:8501"
                        title="WasteIQ Python Business Intelligence"
                        loading="lazy"
                        allow="clipboard-read; clipboard-write"
                    ></iframe>
                </div>
            </section>

        <?php else: ?>

            <!-- DASHBOARD PHP -->
            <header class="topbar">
                <div>
                    <div class="eyebrow">
                        OVERVIEW / BUSINESS ANALYTICS
                    </div>

                    <h1>Dashboard</h1>

                    <p>
                        Pantau performa dan potensi bisnis pengolahan
                        sampah menjadi energi.
                    </p>
                </div>

                <div class="top-actions">
                    <div class="date-chip">
                        <span>◷</span>
                        <?= date('d M Y') ?>
                    </div>

                    <a href="simulasi.php" class="btn btn-primary">
                        + Simulasi Baru
                    </a>
                </div>
            </header>

            <!-- WELCOME BANNER -->
            <section class="welcome-banner">
                <div class="welcome-copy">
                    <span class="banner-tag">
                        WASTE-TO-ENERGY PROJECT
                    </span>

                    <h2>Ubah Sampah Menjadi<br>Energi Bernilai.</h2>

                    <p>
                        Analisis potensi produksi biogas, estimasi energi,
                        dan proyeksi kelayakan finansial dalam satu dashboard.
                    </p>

                    <a href="simulasi.php" class="btn btn-light">
                        Mulai Analisis <span>→</span>
                    </a>
                </div>

                <div class="banner-art" aria-hidden="true">
                    <div class="orbit orbit-one"></div>
                    <div class="orbit orbit-two"></div>
                    <div class="eco-symbol">♻</div>

                    <div class="floating-card card-gas">
                        <span>CH₄</span>
                        <strong>Biogas</strong>
                    </div>

                    <div class="floating-card card-energy">
                        <span>⚡</span>
                        <strong>Clean Energy</strong>
                    </div>
                </div>
            </section>

            <!-- INDIKATOR UTAMA -->
            <div class="section-heading">
                <div>
                    <h2>Indikator Utama</h2>
                    <p>Ringkasan seluruh catatan simulasi tersimpan.</p>
                </div>

                <span class="period-label">SEMUA PERIODE</span>
            </div>

            <section class="row g-3 metric-grid">

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div class="metric-top">
                            <span>Total Simulasi</span>
                            <span class="metric-icon icon-blue">▦</span>
                        </div>

                        <div class="metric-number">
                            <?= number_format((int) $stats['total_simulasi']) ?>
                        </div>

                        <div class="metric-foot">
                            Data tersimpan di database
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div class="metric-top">
                            <span>Estimasi Sampah</span>
                            <span class="metric-icon icon-green">♻</span>
                        </div>

                        <div class="metric-number">
                            <?= number_format((float) $stats['sampah'], 1, ',', '.') ?>
                            <small>ton/hari*</small>
                        </div>

                        <div class="metric-foot">
                            Akumulasi nilai simulasi
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div class="metric-top">
                            <span>Potensi Metana</span>
                            <span class="metric-icon icon-purple">CH₄</span>
                        </div>

                        <div class="metric-number">
                            <?= number_format((float) $stats['metana'], 1, ',', '.') ?>
                            <small>m³/hari*</small>
                        </div>

                        <div class="metric-foot">
                            Estimasi produksi gas
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div class="metric-top">
                            <span>Potensi Energi</span>
                            <span class="metric-icon icon-orange">⚡</span>
                        </div>

                        <div class="metric-number">
                            <?= number_format((float) $stats['energi'], 1, ',', '.') ?>
                            <small>kWh/hari*</small>
                        </div>

                        <div class="metric-foot">
                            Estimasi energi per simulasi
                        </div>
                    </div>
                </div>

            </section>

            <!-- ANALISIS FINANSIAL -->
            <div class="section-heading mt-4">
                <div>
                    <h2>Analisis Finansial</h2>
                    <p>Proyeksi dari simulasi yang disimpan bulan ini.</p>
                </div>

                <a href="report_monthly.php" class="text-link">
                    Lihat laporan →
                </a>
            </div>

            <section class="row g-3">

                <div class="col-12 col-md-4">
                    <div class="finance-card">
                        <div class="finance-label">
                            Pendapatan Proyeksi
                        </div>

                        <div class="finance-value">
                            <?= rupiah($finance['pendapatan']) ?>
                        </div>

                        <div class="finance-description">
                            Total skenario bulan berjalan
                        </div>

                        <div class="finance-line line-blue"></div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="finance-card">
                        <div class="finance-label">
                            Biaya Operasional (OPEX)
                        </div>

                        <div class="finance-value">
                            <?= rupiah($finance['opex']) ?>
                        </div>

                        <div class="finance-description">
                            Total biaya yang diproyeksikan
                        </div>

                        <div class="finance-line line-orange"></div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="finance-card">
                        <div class="finance-label">
                            Laba / (Rugi) Proyeksi
                        </div>

                        <div class="finance-value <?= (float) $finance['laba'] < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= rupiah($finance['laba']) ?>
                        </div>

                        <div class="finance-description">
                            <?= (int) $finance['jumlah'] ?>
                            simulasi bulan ini
                        </div>

                        <div class="finance-line line-green"></div>
                    </div>
                </div>

            </section>

            <!-- GRAFIK FINANSIAL -->
            <section class="content-card mt-4">
                <div class="card-heading">
                    <div>
                        <h2>Tren Kinerja Finansial</h2>
                        <p>
                            Perbandingan pendapatan, OPEX, dan laba proyeksi.
                        </p>
                    </div>

                    <span class="chart-badge">6 BULAN TERAKHIR</span>
                </div>

                <div class="chart-container">
                    <canvas id="financialChart"></canvas>
                </div>
            </section>

            <!-- RIWAYAT TERBARU -->
            <section class="content-card mt-4">
                <div class="card-heading">
                    <div>
                        <h2>Riwayat Simulasi Terbaru</h2>
                        <p>Hasil analisis yang terakhir disimpan.</p>
                    </div>

                    <a href="history.php" class="text-link">
                        Lihat semua →
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table dashboard-table">
                        <thead>
                            <tr>
                                <th>TANGGAL</th>
                                <th>NAMA PROYEK</th>
                                <th>SKENARIO</th>
                                <th>SAMPAH / HARI</th>
                                <th>METANA / HARI</th>
                                <th>LABA / BULAN</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (!$recent): ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    <div class="empty-icon">▤</div>

                                    <strong>Belum ada simulasi</strong>

                                    <p>
                                        Buat simulasi pertama untuk mengisi dashboard.
                                    </p>

                                    <a
                                        href="simulasi.php"
                                        class="btn btn-primary btn-sm"
                                    >
                                        Buat Simulasi
                                    </a>
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td>
                                        <?= date('d M Y', strtotime($r['created_at'])) ?>
                                    </td>

                                    <td>
                                        <strong><?= e($r['project_name']) ?></strong>

                                        <small class="d-block text-muted">
                                            #<?= (int) $r['id'] ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="scenario-pill">
                                            <?= e($r['scenario']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= number_format((float) $r['waste_ton_day'], 2, ',', '.') ?>
                                        ton
                                    </td>

                                    <td>
                                        <?= number_format((float) $r['methane_m3_day'], 2, ',', '.') ?>
                                        m³
                                    </td>

                                    <td>
                                        <strong>
                                            <?= rupiah($r['profit_month']) ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php endif; ?>

        <!-- FOOTER -->
        <footer class="page-footer">
            <span>
                © <?= date('Y') ?> WasteIQ Business Intelligence
            </span>

            <span>
                Waste-to-Energy · Biogas Project By Muhamad Kamal
            </span>
        </footer>

    </main>
</div>

<?php if ($page === 'dashboard'): ?>
<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const revenueData = <?= json_encode($revenues) ?>;
const opexData = <?= json_encode($opexValues) ?>;
const profitData = <?= json_encode($profits) ?>;

const chartElement = document.getElementById('financialChart');

if (chartElement) {
    new Chart(chartElement, {
        type: 'line',

        data: {
            labels: labels,

            datasets: [
                {
                    label: 'Pendapatan',
                    data: revenueData,
                    borderColor: '#3678f5',
                    backgroundColor: 'rgba(54,120,245,.08)',
                    fill: true,
                    tension: .35,
                    borderWidth: 2.5
                },
                {
                    label: 'OPEX',
                    data: opexData,
                    borderColor: '#f0a13b',
                    backgroundColor: 'transparent',
                    tension: .35,
                    borderWidth: 2.5
                },
                {
                    label: 'Laba',
                    data: profitData,
                    borderColor: '#10a78c',
                    backgroundColor: 'transparent',
                    tension: .35,
                    borderWidth: 2.5
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                intersect: false,
                mode: 'index'
            },

            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 22
                    }
                },

                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': Rp ' +
                                new Intl.NumberFormat('id-ID')
                                    .format(ctx.raw);
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    grid: {
                        color: '#edf1f6'
                    },

                    ticks: {
                        callback: function(value) {
                            return 'Rp ' +
                                new Intl.NumberFormat('id-ID', {
                                    notation: 'compact'
                                }).format(value);
                        }
                    }
                },

                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
</script>
<?php endif; ?>

</body>
</html>