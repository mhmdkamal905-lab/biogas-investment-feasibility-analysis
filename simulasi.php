<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$result = null;
$message = '';

$input = [
    'project_name' => 'Proyek Biogas TPA',
    'scenario' => 'Dasar',
    'waste_ton_day' => '50',
    'organic_percent' => '60',
    'methane_yield' => '80',
    'energy_efficiency' => '35',
    'electricity_price' => '1444.70',
    'operating_days' => '30',
    'capex' => '5000000000',
    'opex_month' => '100000000',
    'other_revenue_month' => '0',
    'discount_rate' => '10',
];

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah(float $value): string
{
    return 'Rp ' . number_format($value, 0, ',', '.');
}

function numberInput(array $input, string $key): float
{
    return (float) ($input[$key] ?? 0);
}

function numberId(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, ',', '.');
}

if (isset($_GET['saved'])) {
    $message = 'Simulasi berhasil disimpan ke database.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input as $key => $default) {
        if (isset($_POST[$key]) && !is_array($_POST[$key])) {
            $input[$key] = trim((string) $_POST[$key]);
        }
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    }

    if ($input['project_name'] === '') {
        $errors[] = 'Nama proyek wajib diisi.';
    }

    if (mb_strlen($input['project_name']) > 150) {
        $errors[] = 'Nama proyek maksimal 150 karakter.';
    }

    if (!in_array(
        $input['scenario'],
        ['Konservatif', 'Dasar', 'Optimistis'],
        true
    )) {
        $errors[] = 'Pilih skenario simulasi yang valid.';
    }

    $limits = [
        'waste_ton_day' => [0.001, 1000000, 'Volume sampah'],
        'organic_percent' => [0, 100, 'Persentase sampah organik'],
        'methane_yield' => [0, 500, 'Hasil metana'],
        'energy_efficiency' => [0, 100, 'Efisiensi konversi energi'],
        'electricity_price' => [0, 100000000, 'Harga listrik'],
        'operating_days' => [1, 31, 'Hari operasi per bulan'],
        'capex' => [0, 100000000000000, 'Investasi awal'],
        'opex_month' => [0, 100000000000000, 'Biaya operasional bulanan'],
        'other_revenue_month' => [0, 100000000000000, 'Pendapatan tambahan'],
        'discount_rate' => [0, 100, 'Tingkat diskonto'],
    ];

    foreach ($limits as $key => [$min, $max, $label]) {
        if (
            $input[$key] === '' ||
            !is_numeric($input[$key]) ||
            !is_finite((float) $input[$key]) ||
            (float) $input[$key] < $min ||
            (float) $input[$key] > $max
        ) {
            $errors[] = "$label harus bernilai antara $min dan $max.";
        }
    }

    if (!$errors) {
        $wasteTonDay = numberInput($input, 'waste_ton_day');
        $organicPercent = numberInput($input, 'organic_percent');
        $methaneYield = numberInput($input, 'methane_yield');
        $efficiency = numberInput($input, 'energy_efficiency') / 100;
        $electricityPrice = numberInput($input, 'electricity_price');
        $operatingDays = (int) numberInput($input, 'operating_days');
        $capex = numberInput($input, 'capex');
        $opex = numberInput($input, 'opex_month');
        $otherRevenue = numberInput($input, 'other_revenue_month');
        $discountRate = numberInput($input, 'discount_rate') / 100;

        /*
         * Asumsi model awal:
         * - Hasil metana dalam m3 CH4 per ton sampah organik.
         * - Nilai kalor metana: 9,97 kWh/m3.
         * - Energi listrik kotor = energi termal x efisiensi generator.
         * - Belum ada pengurangan energi internal/parasitik.
         */

        $organicWasteTonDay =
            $wasteTonDay * ($organicPercent / 100);

        $methaneDay = $organicWasteTonDay * $methaneYield;

        $energyKwhDay =
            $methaneDay * 9.97 * $efficiency;

        $energyKwhMonth = $energyKwhDay * $operatingDays;

        $electricityRevenue =
            $energyKwhMonth * $electricityPrice;

        $revenueMonth =
            $electricityRevenue + $otherRevenue;

        $profitMonth = $revenueMonth - $opex;

        $bepMonths = $profitMonth > 0
            ? $capex / $profitMonth
            : null;

        $annualCashFlow = $profitMonth * 12;

        // NPV indikatif dengan arus kas tahunan konstan selama 10 tahun.
        $npv = -$capex;

        for ($year = 1; $year <= 10; $year++) {
            $discountFactor = pow(1 + $discountRate, $year);
            $npv += $discountRate == 0.0
                ? $annualCashFlow
                : $annualCashFlow / $discountFactor;
        }

        $assumptions = [
            'total_waste_ton_day' => $wasteTonDay,
            'organic_percent' => $organicPercent,
            'organic_waste_ton_day' => $organicWasteTonDay,
            'methane_yield_m3_per_ton' => $methaneYield,
            'methane_energy_kwh_per_m3' => 9.97,
            'energy_efficiency_percent' => $efficiency * 100,
            'electricity_price' => $electricityPrice,
            'operating_days' => $operatingDays,
            'other_revenue_month' => $otherRevenue,
            'discount_rate_percent' => $discountRate * 100,
            'calculation_period_years' => 10,
        ];

        $result = [
            'organic_waste_ton_day' => $organicWasteTonDay,
            'methane_m3_day' => $methaneDay,
            'energy_kwh_day' => $energyKwhDay,
            'energy_kwh_month' => $energyKwhMonth,
            'electricity_revenue' => $electricityRevenue,
            'revenue_month' => $revenueMonth,
            'opex_month' => $opex,
            'profit_month' => $profitMonth,
            'capex' => $capex,
            'bep_months' => $bepMonths,
            'npv' => $npv,
            'assumptions' => $assumptions,
        ];

        if (isset($_POST['save_simulation'])) {
            try {
                $sql = "INSERT INTO simulation_history (
                    project_name,
                    scenario,
                    waste_ton_day,
                    methane_m3_day,
                    energy_kwh_day,
                    revenue_month,
                    opex_month,
                    profit_month,
                    capex,
                    bep_months,
                    npv,
                    irr_percent,
                    assumptions
                ) VALUES (
                    :project_name,
                    :scenario,
                    :waste_ton_day,
                    :methane_m3_day,
                    :energy_kwh_day,
                    :revenue_month,
                    :opex_month,
                    :profit_month,
                    :capex,
                    :bep_months,
                    :npv,
                    NULL,
                    :assumptions
                )";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':project_name' => $input['project_name'],
                    ':scenario' => $input['scenario'],
                    ':waste_ton_day' => $wasteTonDay,
                    ':methane_m3_day' => $methaneDay,
                    ':energy_kwh_day' => $energyKwhDay,
                    ':revenue_month' => $revenueMonth,
                    ':opex_month' => $opex,
                    ':profit_month' => $profitMonth,
                    ':capex' => $capex,
                    ':bep_months' => $bepMonths,
                    ':npv' => $npv,
                    ':assumptions' => json_encode(
                        $assumptions,
                        JSON_THROW_ON_ERROR
                    ),
                ]);

                header('Location: simulasi.php?saved=1');
                exit;
            } catch (Throwable $exception) {
                error_log(
                    'Save simulation failed: ' . $exception->getMessage()
                );

                $errors[] =
                    'Perhitungan berhasil, tetapi penyimpanan gagal. '
                    . 'Periksa struktur tabel simulation_history.';
            }
        }
    }
}

$chartData = null;

if ($result !== null) {
    $chartData = [
        'labels' => [
            'Pendapatan listrik',
            'Pendapatan tambahan',
            'Biaya operasional',
        ],
        'values' => [
            $result['electricity_revenue'],
            numberInput($input, 'other_revenue_month'),
            $result['opex_month'],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102d26">
    <title>Simulasi Biogas | WasteIQ</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary: #087f5b;
            --primary-dark: #065f46;
            --sidebar: #102d26;
            --background: #f4f7f6;
            --surface: #ffffff;
            --text: #1f2937;
            --muted: #64748b;
            --border: #e5ece8;
            --success-bg: #eaf8f0;
            --danger-bg: #fff0f0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        .app-shell { min-height: 100vh; }

        .sidebar {
            min-height: 100vh;
            background: var(--sidebar);
            color: #fff;
            padding: 25px 16px;
            display: flex;
            flex-direction: column;
        }

       .brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #ffffff;
    font-size: 24px;
    font-weight: 800;
    text-decoration: none;
}

.brand-mark {
    width: 46px;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    border-radius: 12px;
    background: #ffffff;
}

.brand-mark img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}

.brand-text {
    color: #ffffff;
    letter-spacing: -0.5px;
}
        .brand-mark svg { width: 23px; height: 23px; }

        .nav-caption {
            color: #87a79a;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            font-weight: 800;
            margin: 0 12px 10px;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #d1e7dd;
            text-decoration: none;
            padding: 12px 13px;
            margin-bottom: 6px;
            border-radius: 10px;
            font-size: 13px;
            transition: .2s ease;
        }

        .sidebar nav a svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            color: #fff;
            background: var(--primary);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 20px 9px 0;
            color: #9bb9ac;
            font-size: 11px;
            line-height: 1.7;
        }

        .sidebar-footer .status-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 5px;
            border-radius: 50%;
            background: #4ade80;
        }

        .main-content {
            padding: 30px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 27px;
        }

        .eyebrow {
            color: var(--primary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .page-title {
            font-size: clamp(24px, 3vw, 31px);
            letter-spacing: -1px;
            font-weight: 800;
            margin: 0 0 8px;
        }

        .page-description {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            margin: 0;
        }

        .surface,
        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 5px 22px rgba(15, 60, 45, .035);
        }

        .surface {
            padding: 24px;
            margin-bottom: 21px;
        }

        .surface-heading {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 23px;
        }

        .section-number {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            border-radius: 11px;
            background: #e8f6ef;
            color: var(--primary);
            font-size: 13px;
            font-weight: 800;
        }

        .surface-heading h2 {
            font-size: 16px;
            font-weight: 800;
            margin: 1px 0 5px;
        }

        .surface-heading p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
            line-height: 1.6;
        }

        .form-label {
            color: #34443c;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #dce5e0;
            border-radius: 9px;
            background-color: #fff;
            font-size: 13px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .2rem rgba(8, 127, 91, .12);
        }

        .form-text {
            font-size: 11px;
            color: var(--muted);
            line-height: 1.6;
        }

        .btn {
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            padding: 11px 16px;
        }

        .btn-primary,
        .btn-success {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }

        .btn-outline-success {
            color: var(--primary);
            border-color: #b9d8ca;
        }

        .btn-outline-success:hover {
            color: white;
            background: var(--primary);
            border-color: var(--primary);
        }

        .metric-card {
            padding: 19px;
            height: 100%;
            min-width: 0;
        }

        .metric-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 15px;
        }

        .metric-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: #e8f6ef;
            color: var(--primary);
        }

        .metric-icon svg { width: 19px; height: 19px; }

        .metric-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 8px;
        }

        .metric-value {
            color: var(--primary);
            font-size: clamp(21px, 2vw, 27px);
            font-weight: 800;
            letter-spacing: -.6px;
            overflow-wrap: anywhere;
        }

        .metric-unit {
            color: var(--muted);
            font-size: 11px;
            margin-top: 7px;
        }

        .assumption {
            background: #eef8f3;
            border: 1px solid #dcefe5;
            border-radius: 11px;
            padding: 14px 16px;
            color: #31594a;
            font-size: 12px;
            line-height: 1.8;
        }

        .result-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin: 31px 0 18px;
        }

        .result-header h2 {
            font-size: 21px;
            font-weight: 800;
            margin: 0 0 7px;
        }

        .result-header p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
        }

        .scenario-badge {
            background: #e8f6ef;
            color: var(--primary-dark);
            border-radius: 30px;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 800;
        }

        .table {
            --bs-table-bg: transparent;
            margin-bottom: 0;
        }

        .table td {
            padding: 15px 8px;
            border-color: #edf1ee;
            font-size: 12px;
        }

        .table tr:last-child td { border-bottom: 0; }

        .financial-total td {
            background: #f0f8f4;
            font-weight: 800;
        }

        .chart-container {
            height: 280px;
            position: relative;
        }

        .empty-state {
            border: 1px dashed #cbd9d1;
            border-radius: 14px;
            padding: 25px;
            color: var(--muted);
            text-align: center;
            font-size: 12px;
        }

        .empty-state svg {
            color: var(--primary);
            width: 27px;
            height: 27px;
            margin-bottom: 10px;
        }

        .footer {
            padding: 8px 0 22px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
                padding: 18px;
            }

            .brand { margin-bottom: 20px; }

            .sidebar nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 4px;
            }

            .sidebar nav a {
                padding: 10px;
                margin: 0;
                font-size: 11px;
            }

            .nav-caption,
            .sidebar-footer { display: none; }

            .main-content { padding: 20px 14px; }

            .surface { padding: 18px; }

            .topbar { align-items: flex-start; }

            .topbar .btn { white-space: nowrap; }

            .chart-container { height: 240px; }
        }
    </style>
</head>
<body>
<div class="container-fluid app-shell">
    <div class="row g-0">

        <aside class="col-md-3 col-lg-2 sidebar">
<div class="brand">
    <span class="brand-mark">
        <img src="img/logo branding website.png" alt="Logo WasteIQ">
    </span>
    <span class="brand-text">WasteIQ</span>
</div>

            
            <nav>
                <a href="index.php">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>

                <a href="simulasi.php" class="active">
                    <i data-lucide="calculator"></i>
                    <span>Simulasi Biogas</span>
                </a>

                <a href="history.php">
                    <i data-lucide="history"></i>
                    <span>Riwayat Simulasi</span>
                </a>

                <a href="report_monthly.php">
                    <i data-lucide="file-chart-column"></i>
                    <span>Laporan Bulanan</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <span class="status-dot"></span>
                Business Intelligence
                <br>
                Waste-to-Energy Analytics
            </div>
        </aside>

        <main class="col-md-9 col-lg-10 main-content">

            <header class="topbar">
                <div>
                    <div class="eyebrow">Analisis &amp; Proyeksi</div>
                    <h1 class="page-title">Simulasi Bisnis Biogas</h1>
                    <p class="page-description">
                        Analisis potensi sampah organik menjadi energi
                        dan estimasi kelayakan finansial proyek.
                    </p>
                </div>

                <a href="history.php" class="btn btn-outline-success">
                    <i data-lucide="history" class="me-1" style="width:15px;height:15px"></i>
                    Riwayat
                </a>
            </header>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success border-0 rounded-3 small">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger border-0 rounded-3 small">
                    <strong>Periksa kembali data Anda:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="simulasi.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <section class="surface">
                    <div class="surface-heading">
                        <div class="section-number">01</div>
                        <div>
                            <h2>Identitas Proyek</h2>
                            <p>Tentukan nama dan skenario analisis proyek.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="project_name">
                                Nama proyek
                            </label>
                            <input
                                class="form-control"
                                id="project_name"
                                name="project_name"
                                maxlength="150"
                                required
                                value="<?= e($input['project_name']) ?>"
                            >
                        </div>

                        <div class="col-md-5">
                            <label class="form-label" for="scenario">
                                Skenario simulasi
                            </label>
                            <select class="form-select" id="scenario" name="scenario">
                                <?php foreach (
                                    ['Konservatif', 'Dasar', 'Optimistis']
                                    as $scenario
                                ): ?>
                                    <option
                                        value="<?= e($scenario) ?>"
                                        <?= $input['scenario'] === $scenario
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e($scenario) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Label skenario belum mengubah parameter otomatis.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="surface">
                    <div class="surface-heading">
                        <div class="section-number">02</div>
                        <div>
                            <h2>Parameter Bahan Baku</h2>
                            <p>Masukkan volume sampah dan perkiraan potensi produksi metana.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="waste_ton_day">
                                Total sampah (ton/hari)
                            </label>
                            <input
                                type="number" min="0.001" max="1000000" step="0.001"
                                class="form-control" id="waste_ton_day"
                                name="waste_ton_day" required
                                value="<?= e($input['waste_ton_day']) ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="organic_percent">
                                Sampah organik (%)
                            </label>
                            <input
                                type="number" min="0" max="100" step="0.1"
                                class="form-control" id="organic_percent"
                                name="organic_percent" required
                                value="<?= e($input['organic_percent']) ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="methane_yield">
                                Hasil metana (m³/ton organik)
                            </label>
                            <input
                                type="number" min="0" max="500" step="0.1"
                                class="form-control" id="methane_yield"
                                name="methane_yield" required
                                value="<?= e($input['methane_yield']) ?>"
                            >
                        </div>
                    </div>

                    <div class="assumption mt-4">
                        <strong>Asumsi teknis:</strong>
                        produksi metana dihitung dari massa sampah organik
                        dikalikan hasil metana per ton sampah organik.
                        Hasil aktual dipengaruhi komposisi bahan baku,
                        kadar air, proses digesti, dan kualitas gas.
                    </div>
                </section>

                <section class="surface">
                    <div class="surface-heading">
                        <div class="section-number">03</div>
                        <div>
                            <h2>Konversi Energi &amp; Pendapatan</h2>
                            <p>Atur efisiensi pembangkit dan asumsi pendapatan bulanan.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="energy_efficiency">
                                Efisiensi konversi listrik (%)
                            </label>
                            <input
                                type="number" min="0" max="100" step="0.1"
                                class="form-control" id="energy_efficiency"
                                name="energy_efficiency" required
                                value="<?= e($input['energy_efficiency']) ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="electricity_price">
                                Harga listrik (Rp/kWh)
                            </label>
                            <input
                                type="number" min="0" max="100000000" step="0.01"
                                class="form-control" id="electricity_price"
                                name="electricity_price" required
                                value="<?= e($input['electricity_price']) ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="operating_days">
                                Hari operasi per bulan
                            </label>
                            <input
                                type="number" min="1" max="31" step="1"
                                class="form-control" id="operating_days"
                                name="operating_days" required
                                value="<?= e($input['operating_days']) ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="other_revenue_month">
                                Pendapatan tambahan per bulan (Rp)
                            </label>
                            <input
                                type="number" min="0" max="100000000000000" step="1000"
                                class="form-control" id="other_revenue_month"
                                name="other_revenue_month" required
                                value="<?= e($input['other_revenue_month']) ?>"
                            >
                            <div class="form-text">
                                Contoh: pendapatan digestate atau produk samping yang layak jual.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="surface">
                    <div class="surface-heading">
                        <div class="section-number">04</div>
                        <div>
                            <h2>Parameter Finansial</h2>
                            <p>Masukkan investasi awal, biaya operasional, dan tingkat diskonto.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="capex">
                                Investasi awal / CAPEX (Rp)
                            </label>
                            <input
                                type="number" min="0" max="100000000000000" step="1000000"
                                class="form-control" id="capex" name="capex" required
                                value="<?= e($input['capex']) ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="opex_month">
                                Biaya operasional / OPEX bulanan (Rp)
                            </label>
                            <input
                                type="number" min="0" max="100000000000000" step="1000000"
                                class="form-control" id="opex_month" name="opex_month" required
                                value="<?= e($input['opex_month']) ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="discount_rate">
                                Tingkat diskonto (%/tahun)
                            </label>
                            <input
                                type="number" min="0" max="100" step="0.1"
                                class="form-control" id="discount_rate" name="discount_rate" required
                                value="<?= e($input['discount_rate']) ?>"
                            >
                        </div>
                    </div>
                </section>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button type="submit" name="calculate" value="1"
                            class="btn btn-primary">
                        <i data-lucide="calculator" class="me-1"
                           style="width:15px;height:15px"></i>
                        Hitung Simulasi
                    </button>

                    <button type="submit" name="save_simulation" value="1"
                            class="btn btn-success">
                        <i data-lucide="save" class="me-1"
                           style="width:15px;height:15px"></i>
                        Hitung &amp; Simpan
                    </button>

                    <a href="simulasi.php" class="btn btn-outline-secondary">
                        Reset Formulir
                    </a>
                </div>
            </form>

            <?php if ($result !== null): ?>
                <section id="hasil">
                    <div class="result-header">
                        <div>
                            <div class="eyebrow">Hasil Perhitungan</div>
                            <h2>Ringkasan Simulasi</h2>
                            <p>
                                <?= e($input['project_name']) ?>
                                · <?= e($input['scenario']) ?>
                            </p>
                        </div>

                        <span class="scenario-badge">
                            Estimasi awal
                        </span>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-xl-3">
                            <div class="metric-card">
                                <div class="metric-top">
                                    <span class="metric-icon">
                                        <i data-lucide="recycle"></i>
                                    </span>
                                </div>
                                <div class="metric-label">Sampah organik</div>
                                <div class="metric-value">
                                    <?= numberId($result['organic_waste_ton_day']) ?>
                                </div>
                                <div class="metric-unit">ton/hari</div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="metric-card">
                                <div class="metric-top">
                                    <span class="metric-icon">
                                        <i data-lucide="cloud"></i>
                                    </span>
                                </div>
                                <div class="metric-label">Produksi metana</div>
                                <div class="metric-value">
                                    <?= numberId($result['methane_m3_day']) ?>
                                </div>
                                <div class="metric-unit">m³/hari</div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="metric-card">
                                <div class="metric-top">
                                    <span class="metric-icon">
                                        <i data-lucide="zap"></i>
                                    </span>
                                </div>
                                <div class="metric-label">Energi listrik harian</div>
                                <div class="metric-value">
                                    <?= numberId($result['energy_kwh_day']) ?>
                                </div>
                                <div class="metric-unit">kWh/hari</div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="metric-card">
                                <div class="metric-top">
                                    <span class="metric-icon">
                                        <i data-lucide="wallet"></i>
                                    </span>
                                </div>
                                <div class="metric-label">Laba operasional</div>
                                <div class="metric-value"
                                     style="color:<?= $result['profit_month'] >= 0 ? '#087f5b' : '#dc3545' ?>">
                                    <?= e(rupiah($result['profit_month'])) ?>
                                </div>
                                <div class="metric-unit">per bulan</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-xl-7">
                            <section class="surface h-100">
                                <div class="surface-heading">
                                    <div class="section-number">
                                        <i data-lucide="chart-column"
                                           style="width:18px;height:18px"></i>
                                    </div>
                                    <div>
                                        <h2>Analisis Finansial Bulanan</h2>
                                        <p>Perbandingan pendapatan dan biaya.</p>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <tbody>
                                            <tr>
                                                <td>Pendapatan listrik</td>
                                                <td class="text-end fw-semibold">
                                                    <?= e(rupiah($result['electricity_revenue'])) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Pendapatan tambahan</td>
                                                <td class="text-end fw-semibold">
                                                    <?= e(rupiah(numberInput($input, 'other_revenue_month'))) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Total pendapatan</td>
                                                <td class="text-end fw-semibold">
                                                    <?= e(rupiah($result['revenue_month'])) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Biaya operasional</td>
                                                <td class="text-end">
                                                    <?= e(rupiah($result['opex_month'])) ?>
                                                </td>
                                            </tr>
                                            <tr class="financial-total">
                                                <td>Laba / (rugi) operasional</td>
                                                <td class="text-end <?= $result['profit_month'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= e(rupiah($result['profit_month'])) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Investasi awal</td>
                                                <td class="text-end">
                                                    <?= e(rupiah($result['capex'])) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Estimasi BEP sederhana</td>
                                                <td class="text-end fw-semibold">
                                                    <?= $result['bep_months'] !== null
                                                        ? e(numberId($result['bep_months'], 1)) . ' bulan'
                                                        : 'Belum tercapai dengan laba saat ini' ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>NPV indikatif (10 tahun)</td>
                                                <td class="text-end fw-semibold <?= $result['npv'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= e(rupiah($result['npv'])) ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="assumption mt-3">
                                    <strong>Interpretasi:</strong>
                                    BEP dihitung dengan membagi investasi awal
                                    dengan laba operasional bulanan. NPV memakai
                                    arus kas tahunan konstan selama 10 tahun.
                                    Pajak, modal kerja, pembiayaan, penggantian
                                    peralatan, dan perubahan produksi belum dihitung.
                                </div>
                            </section>
                        </div>

                        <div class="col-xl-5">
                            <section class="surface h-100">
                                <div class="surface-heading">
                                    <div class="section-number">
                                        <i data-lucide="chart-pie"
                                           style="width:18px;height:18px"></i>
                                    </div>
                                    <div>
                                        <h2>Komponen Finansial</h2>
                                        <p>Nilai pendapatan dan biaya bulanan.</p>
                                    </div>
                                </div>

                                <div class="chart-container">
                                    <canvas id="financialChart"></canvas>
                                </div>

                                <p class="form-text mt-3">
                                    Grafik memperlihatkan nilai nominal,
                                    bukan laba bersih kumulatif.
                                </p>
                            </section>
                        </div>
                    </div>

                    <section class="surface mt-3">
                        <div class="surface-heading">
                            <div class="section-number">
                                <i data-lucide="clipboard-list"
                                   style="width:18px;height:18px"></i>
                            </div>
                            <div>
                                <h2>Asumsi Model</h2>
                                <p>Parameter yang digunakan untuk menghasilkan estimasi.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <tbody>
                                    <tr>
                                        <td>Total sampah masuk</td>
                                        <td class="text-end">
                                            <?= numberId($result['assumptions']['total_waste_ton_day']) ?>
                                            ton/hari
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Persentase sampah organik</td>
                                        <td class="text-end">
                                            <?= numberId($result['assumptions']['organic_percent']) ?>%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Hasil metana per ton organik</td>
                                        <td class="text-end">
                                            <?= numberId($result['assumptions']['methane_yield_m3_per_ton']) ?>
                                            m³/ton
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Nilai kalor metana</td>
                                        <td class="text-end">9,97 kWh/m³</td>
                                    </tr>
                                    <tr>
                                        <td>Efisiensi konversi listrik</td>
                                        <td class="text-end">
                                            <?= numberId($result['assumptions']['energy_efficiency_percent']) ?>%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Hari operasi per bulan</td>
                                        <td class="text-end">
                                            <?= (int) $result['assumptions']['operating_days'] ?> hari
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Tingkat diskonto</td>
                                        <td class="text-end">
                                            <?= numberId($result['assumptions']['discount_rate_percent']) ?>%/tahun
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>
            <?php else: ?>
                <section class="surface">
                    <div class="empty-state">
                        <i data-lucide="chart-no-axes-combined"></i>
                        <div class="fw-bold mb-2">Hasil simulasi akan ditampilkan di sini</div>
                        <div>
                            Lengkapi parameter proyek, lalu pilih
                            <strong>Hitung Simulasi</strong> untuk melihat
                            estimasi produksi metana, energi, pendapatan, BEP, dan NPV.
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <footer class="footer">
                WasteIQ · Waste-to-Energy Business Intelligence
                <br>
                Hasil merupakan estimasi awal untuk perencanaan dan bukan
                pengganti studi kelayakan teknis maupun finansial.
            </footer>
        </main>
    </div>
</div>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }

    <?php if ($chartData !== null): ?>
    const chartElement = document.getElementById('financialChart');

    if (chartElement && window.Chart) {
        new Chart(chartElement, {
            type: 'bar',
            data: {
                labels: <?= json_encode(
                    $chartData['labels'],
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                ) ?>,
                datasets: [{
                    label: 'Rupiah per bulan',
                    data: <?= json_encode($chartData['values']) ?>,
                    backgroundColor: [
                        '#087f5b',
                        '#6bbf96',
                        '#e5a65b'
                    ],
                    borderRadius: 7,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' +
                                    new Intl.NumberFormat('id-ID', {
                                        maximumFractionDigits: 0
                                    }).format(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('id-ID', {
                                    notation: 'compact',
                                    maximumFractionDigits: 1
                                }).format(value);
                            }
                        },
                        grid: { color: '#edf1ee' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    <?php if ($result !== null): ?>
    const resultSection = document.getElementById('hasil');
    if (resultSection) {
        resultSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
    <?php endif; ?>
</script>
</body>
</html>
