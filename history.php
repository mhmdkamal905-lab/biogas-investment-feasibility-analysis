<?php
require __DIR__ . '/config/db.php';

function rupiah(float $value): string
{
    return 'Rp ' . number_format($value, 0, ',', '.');
}

$sql = "
    SELECT *
    FROM simulation_history
    ORDER BY created_at DESC, id DESC
";

$stmt = $pdo->query($sql);
$history = $stmt->fetchAll();

$total = count($history);

$totalRevenue = 0;
$totalProfit = 0;

foreach ($history as $item) {
    $totalRevenue += (float) $item['revenue_year_rp'];
    $totalProfit += (float) $item['operating_profit_year_rp'];
}

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Simulasi | Waste-to-Energy BI</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f7f5;
            color: #18352b;
            font-family: Arial, sans-serif;
        }

        .header-panel {
            background: #123d2c;
            color: white;
            padding: 28px;
            border-radius: 16px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e0e9e3;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #e0e9e3;
        }

        .table th {
            background: #eaf2ed;
            white-space: nowrap;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                font-size: 10px;
            }

            .header-panel,
            .stat-card,
            .table-container {
                border: 1px solid #bbb;
                color: #000;
                background: white;
                box-shadow: none;
            }

            .table-responsive {
                overflow: visible;
            }

            table {
                width: 100% !important;
                font-size: 9px;
            }

            th, td {
                padding: 5px !important;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid p-3 p-lg-4">

    <div class="header-panel mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h2>♻ Waste-to-Energy BI</h2>
                <p class="mb-0">
                    Riwayat analisis teknis dan kelayakan finansial.
                </p>
            </div>

            <div class="no-print d-flex align-items-center gap-2">
                <button
                    class="btn btn-light"
                    onclick="window.print()"
                >
                    Cetak / Simpan PDF
                </button>

                <a
                    href="history.php"
                    class="btn btn-outline-light"
                >
                    Refresh
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="text-secondary">Total simulasi</div>
                <div class="stat-value">
                    <?= number_format($total, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="text-secondary">
                    Akumulasi pendapatan proyeksi
                </div>
                <div class="stat-value">
                    <?= e(rupiah($totalRevenue)) ?>
                </div>
                <small class="text-secondary">
                    Penjumlahan nilai tahunan dari setiap simulasi,
                    bukan pendapatan aktual.
                </small>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="text-secondary">
                    Akumulasi laba operasi proyeksi
                </div>
                <div class="stat-value">
                    <?= e(rupiah($totalProfit)) ?>
                </div>
                <small class="text-secondary">
                    Bukan laba aktual atau laba bulanan.
                </small>
            </div>
        </div>
    </div>

    <div class="table-container">
        <h5 class="mb-3">Data riwayat simulasi</h5>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Waktu simpan</th>
                        <th>Sampah (ton/hari)</th>
                        <th>Bahan layak (ton/hari)</th>
                        <th>Metana (Nm³/hari)</th>
                        <th>Listrik bersih (kWh/hari)</th>
                        <th>Pendapatan/tahun</th>
                        <th>OPEX/tahun</th>
                        <th>Laba operasi/tahun</th>
                        <th>NPV</th>
                        <th>IRR</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4">
                            Belum ada simulasi yang tersimpan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= e($row['id']) ?></td>
                            <td>
                                <?= e($row['created_at']) ?>
                            </td>
                            <td>
                                <?= number_format(
                                    (float) $row['waste_tpd'],
                                    2, ',', '.'
                                ) ?>
                            </td>
                            <td>
                                <?= number_format(
                                    (float) $row['feedstock_tpd'],
                                    2, ',', '.'
                                ) ?>
                            </td>
                            <td>
                                <?= number_format(
                                    (float) $row['methane_nm3_day'],
                                    0, ',', '.'
                                ) ?>
                            </td>
                            <td>
                                <?= number_format(
                                    (float) $row['net_electricity_kwh_day'],
                                    0, ',', '.'
                                ) ?>
                            </td>
                            <td>
                                <?= e(rupiah(
                                    (float) $row['revenue_year_rp']
                                )) ?>
                            </td>
                            <td>
                                <?= e(rupiah(
                                    (float) $row['opex_year_rp']
                                )) ?>
                            </td>
                            <td>
                                <?= e(rupiah(
                                    (float) $row['operating_profit_year_rp']
                                )) ?>
                            </td>
                            <td>
                                <?= e(rupiah(
                                    (float) $row['npv_rp']
                                )) ?>
                            </td>
                            <td>
                                <?= $row['irr'] === null
                                    ? 'N/A'
                                    : number_format(
                                        (float) $row['irr'] * 100,
                                        2, ',', '.'
                                    ) . '%' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="text-secondary small mb-0">
            Data bersumber dari database simulasi.
            Proyeksi finansial bukan realisasi transaksi.
        </p>
    </div>

</div>
</body>
</html>
