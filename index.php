<?php
$data_loaded = false;
$summary = [
    'total_biaya' => 0,
    'total_omzet' => 0,
    'total_klik' => 0,
    'total_dilihat' => 0,
    'total_konversi' => 0,
    'total_terjual' => 0
];
$products = [];
$top_omzet = [];
$top_biaya = [];
$categories = [];
$periode_iklan = "Tidak Diketahui";

function determineCategory($name) {
    $nameLower = strtolower($name);
    if (strpos($nameLower, 'polo shirt') !== false || strpos($nameLower, 'kaos polo') !== false || strpos($nameLower, 'polo') !== false) {
        return 'Polo Shirt';
    } elseif (strpos($nameLower, 'celana chino') !== false || strpos($nameLower, 'chinos') !== false) {
        return 'Celana Chino';
    } elseif (strpos($nameLower, 'celana pendek') !== false || strpos($nameLower, 'shorts') !== false || strpos($nameLower, 'cargo pendek') !== false) {
        return 'Celana Pendek';
    } elseif (strpos($nameLower, 'celana bahan') !== false || strpos($nameLower, 'celana panjang') !== false || strpos($nameLower, 'ankle pants') !== false || strpos($nameLower, 'easy pants') !== false || strpos($nameLower, 'trouser') !== false) {
        return 'Celana Panjang / Formal';
    } elseif (strpos($nameLower, 'kemeja') !== false || strpos($nameLower, 'oxford') !== false || strpos($nameLower, 'shirt') !== false) { 
        return 'Kemeja';
    } elseif (strpos($nameLower, 'rompi') !== false || strpos($nameLower, 'vest') !== false) {
        return 'Rompi / Vest';
    } elseif (strpos($nameLower, 'parfum') !== false || strpos($nameLower, 'edp') !== false) {
        return 'Parfum';
    } elseif (strpos($nameLower, 'sweater') !== false || strpos($nameLower, 'crewneck') !== false) {
        return 'Sweater';
    } elseif (strpos($nameLower, 'jaket') !== false || strpos($nameLower, 'jacket') !== false) {
        return 'Jaket';
    } elseif (strpos($nameLower, 'kaos kaki') !== false || strpos($nameLower, 'sock') !== false || strpos($nameLower, 'belt') !== false || strpos($nameLower, 'sabuk') !== false || strpos($nameLower, 'gesper') !== false) {
        return 'Aksesoris';
    } elseif (strpos($nameLower, 'tanktop') !== false || strpos($nameLower, 'wanita') !== false) {
        return 'Pakaian Wanita';
    } else {
        return 'Lainnya';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExtension == 'csv') {
        if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
            $header_found = false;
            $header = [];
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if (!$header_found) {
                    if (isset($row[0]) && strtolower(trim($row[0])) == 'periode' && isset($row[1])) {
                        $periode_iklan = trim($row[1]);
                    }
                    if (isset($row[0]) && $row[0] == 'Urutan' && isset($row[1]) && $row[1] == 'Nama Iklan') {
                        $header_found = true;
                        $header = $row;
                    }
                    continue;
                }
                
                if (count($row) == count($header)) {
                    $item = array_combine($header, $row);
                    
                    $biaya = (float)str_replace(',', '', $item['Biaya'] ?? 0);
                    $omzet = (float)str_replace(',', '', $item['Omzet Penjualan'] ?? 0);
                    $klik = (int)str_replace(',', '', $item['Jumlah Klik'] ?? 0);
                    $dilihat = (int)str_replace(',', '', $item['Dilihat'] ?? 0);
                    $konversi = (int)str_replace(',', '', $item['Konversi'] ?? 0);
                    $terjual = (int)str_replace(',', '', $item['Produk Terjual'] ?? 0);
                    
                    $summary['total_biaya'] += $biaya;
                    $summary['total_omzet'] += $omzet;
                    $summary['total_klik'] += $klik;
                    $summary['total_dilihat'] += $dilihat;
                    $summary['total_konversi'] += $konversi;
                    $summary['total_terjual'] += $terjual;
                    
                    $item['_biaya'] = $biaya;
                    $item['_omzet'] = $omzet;
                    $item['_nama'] = $item['Nama Iklan'];
                    $item['_kategori'] = determineCategory($item['_nama']);
                    
                    if (!isset($categories[$item['_kategori']])) {
                        $categories[$item['_kategori']] = [
                            'biaya' => 0, 'omzet' => 0, 'klik' => 0, 'dilihat' => 0, 'konversi' => 0
                        ];
                    }
                    $categories[$item['_kategori']]['biaya'] += $biaya;
                    $categories[$item['_kategori']]['omzet'] += $omzet;
                    $categories[$item['_kategori']]['klik'] += $klik;
                    $categories[$item['_kategori']]['dilihat'] += $dilihat;
                    $categories[$item['_kategori']]['konversi'] += $konversi;
                    
                    $roas_item = $biaya > 0 ? ($omzet / $biaya) : 0;
                    $item['_roas'] = $roas_item;
                    
                    if ($roas_item >= 10) {
                        $item['_insight'] = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-rocket-takeoff-fill"></i> Sangat Menguntungkan (Scale Up)</span>';
                    } elseif ($roas_item >= 5) {
                        $item['_insight'] = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-check-circle-fill"></i> Menguntungkan (Pertahankan)</span>';
                    } elseif ($roas_item > 0 && $roas_item < 5) {
                        $item['_insight'] = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-exclamation-triangle-fill"></i> Evaluasi (Optimasi)</span>';
                    } elseif ($roas_item == 0 && $biaya > 15000) {
                        $item['_insight'] = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle-fill"></i> Boncos (Hentikan)</span>';
                    } else {
                        $item['_insight'] = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-hourglass-split"></i> Data Belum Cukup</span>';
                    }

                    $products[] = $item;
                }
            }
            fclose($handle);
            $data_loaded = true;
            
            uasort($categories, function($a, $b) {
                return $b['omzet'] <=> $a['omzet'];
            });
            
            $temp_omzet = $products;
            usort($temp_omzet, function($a, $b) {
                return $b['_omzet'] <=> $a['_omzet'];
            });
            $top_omzet = array_slice($temp_omzet, 0, 5);
            
            $temp_biaya = $products;
            usort($temp_biaya, function($a, $b) {
                return $b['_biaya'] <=> $a['_biaya'];
            });
            $top_biaya = array_slice($temp_biaya, 0, 5);
        }
    }
}

$roas = $summary['total_biaya'] > 0 ? ($summary['total_omzet'] / $summary['total_biaya']) : 0;
$acos = $summary['total_omzet'] > 0 ? ($summary['total_biaya'] / $summary['total_omzet']) * 100 : 0;
$cpc = $summary['total_klik'] > 0 ? ($summary['total_biaya'] / $summary['total_klik']) : 0;
$ctr = $summary['total_dilihat'] > 0 ? ($summary['total_klik'] / $summary['total_dilihat']) * 100 : 0;
$cr = $summary['total_klik'] > 0 ? ($summary['total_konversi'] / $summary['total_klik']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Ads Dashboard</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #4f46e5;      /* Indigo */
            --primary-hover: #4338ca;
            --success: #10b981;      /* Emerald */
            --danger: #ef4444;       /* Red */
            --warning: #f59e0b;      /* Amber */
            --info: #0ea5e9;         /* Sky */
            --dark: #0f172a;         /* Slate */
            --gray: #64748b;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
        }
        body { 
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--dark);
        }
        .navbar {
            background-color: var(--card-bg);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            padding: 1rem 2rem;
        }
        .navbar-brand {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }
        .container { max-width: 1400px; }
        
        .card { 
            border: none; 
            border-radius: 16px; 
            background-color: var(--card-bg);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05); 
            margin-bottom: 24px; 
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
        }
        
        .kpi-card {
            position: relative;
            overflow: hidden;
            padding: 24px;
            z-index: 1;
        }
        .kpi-icon {
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 7rem;
            opacity: 0.04;
            transform: rotate(-15deg);
            z-index: -1;
        }
        .kpi-title { 
            font-size: 0.85rem; 
            color: var(--gray); 
            text-transform: uppercase; 
            font-weight: 700; 
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .kpi-value { 
            font-size: 2.2rem; 
            font-weight: 800; 
            color: var(--dark);
            line-height: 1.1;
            letter-spacing: -1px;
        }
        .kpi-value.text-primary { color: var(--primary) !important; }
        .kpi-value.text-success { color: var(--success) !important; }
        .kpi-value.text-danger { color: var(--danger) !important; }
        .kpi-value.text-warning { color: var(--warning) !important; }
        .kpi-value.text-info { color: var(--info) !important; }
        
        .upload-section {
            background: linear-gradient(135deg, var(--dark) 0%, #1e293b 100%);
            color: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .upload-section h3 {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .btn-upload {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            padding: 10px 28px;
            transition: all 0.2s;
        }
        .btn-upload:hover {
            background-color: var(--primary-hover);
            transform: scale(1.02);
            color: white;
        }
        .form-control-file {
            background: rgba(255,255,255,0.05);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 10px;
        }
        .form-control-file:focus {
            background: rgba(255,255,255,0.1);
            color: white;
            border-color: var(--primary);
            box-shadow: none;
        }
        
        .badge-periode {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 12px;
        }
        
        .section-title {
            font-weight: 800;
            color: var(--dark);
            font-size: 1.3rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.5px;
        }
        .section-title i {
            color: var(--primary);
            background: #e0e7ff;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 1.2rem;
        }
        
        .table { color: var(--dark); }
        .table thead th {
            background-color: #f8fafc;
            color: var(--gray);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            padding: 16px;
        }
        .table tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .badge {
            font-weight: 600;
            padding: 0.5em 0.8em;
            border-radius: 8px;
            letter-spacing: 0.2px;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="#"><i class="bi bi-activity"></i> AdsIntelligence.</a>
        <a href="monitoring_polo.php" class="btn btn-primary fw-semibold rounded-pill px-4 me-2 shadow-sm">
            <i class="bi bi-bar-chart-line-fill me-1"></i> Monitoring Ads (Enterprise View)
        </a>
        <button class="btn btn-outline-dark fw-semibold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#kamusModal">
            <i class="bi bi-info-circle me-1"></i> Panduan Metrik
        </button>
    </div>
</nav>

<div class="container">
    <!-- Upload Section -->
    <div class="upload-section">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h3 class="mb-2">Marketing Performance Dashboard</h3>
                <p class="text-white-50 mb-0">Upload laporan CSV dari Shopee Ads untuk melihat visualisasi performa dan rekomendasi AI (Artificial Intelligence).</p>
            </div>
            <div class="col-lg-6">
                <form method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-3 justify-content-lg-end align-items-center">
                    <input class="form-control form-control-file w-100" style="max-width: 300px;" type="file" name="file" accept=".csv" required>
                    <button class="btn btn-upload text-nowrap" type="submit"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Generate Report</button>
                </form>
            </div>
        </div>
        
        <?php if ($data_loaded && $periode_iklan != ""): ?>
        <div class="mt-4 pt-4 border-top border-secondary border-opacity-25 d-flex align-items-center">
            <div class="badge-periode px-4 py-2 d-inline-flex align-items-center gap-3">
                <i class="bi bi-calendar3 fs-4 text-info"></i> 
                <div>
                    <span class="d-block" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8;">Periode Laporan</span>
                    <span class="fw-bold fs-6"><?= htmlspecialchars($periode_iklan) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($data_loaded): ?>
    
    <!-- Executive Summary KPIs -->
    <div class="section-title"><i class="bi bi-wallet2"></i> Ringkasan Finansial</div>
    <div class="row mb-3">
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-cash-stack kpi-icon text-danger"></i>
                <div class="kpi-title">Total Biaya Iklan (Spend)</div>
                <div class="kpi-value text-danger">Rp <?= number_format($summary['total_biaya'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-bag-check-fill kpi-icon text-success"></i>
                <div class="kpi-title">Omzet Penjualan (GMV)</div>
                <div class="kpi-value text-success">Rp <?= number_format($summary['total_omzet'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-graph-up-arrow kpi-icon text-primary"></i>
                <div class="kpi-title">ROAS (Efektivitas)</div>
                <div class="kpi-value text-primary"><?= number_format($roas, 2) ?>x</div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-pie-chart-fill kpi-icon text-warning"></i>
                <div class="kpi-title">ACOS (Biaya / Omzet)</div>
                <div class="kpi-value text-warning"><?= number_format($acos, 2) ?>%</div>
            </div>
        </div>
    </div>

    <div class="section-title"><i class="bi bi-funnel"></i> Funnel & Traffic</div>
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-eye-fill kpi-icon text-secondary"></i>
                <div class="kpi-title">Impresi (Dilihat)</div>
                <div class="kpi-value"><?= number_format($summary['total_dilihat'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-hand-index-thumb-fill kpi-icon text-secondary"></i>
                <div class="kpi-title">Total Klik</div>
                <div class="kpi-value"><?= number_format($summary['total_klik'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-tags-fill kpi-icon text-secondary"></i>
                <div class="kpi-title">Biaya per Klik (CPC)</div>
                <div class="kpi-value">Rp <?= number_format($cpc, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card kpi-card card-hover h-100">
                <i class="bi bi-cart-check-fill kpi-icon text-info"></i>
                <div class="kpi-title">Conversion Rate (CR)</div>
                <div class="kpi-value text-info"><?= number_format($cr, 2) ?>%</div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="section-title"><i class="bi bi-bar-chart-fill"></i> Analisa Top Produk</div>
    <div class="row mb-5">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="card p-4 h-100">
                <h6 class="fw-bold mb-4 text-success"><i class="bi bi-trophy-fill me-2"></i>Top 5 Penghasil Omzet</h6>
                <div style="position: relative; height:300px; width:100%">
                    <canvas id="chartOmzet"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 h-100">
                <h6 class="fw-bold mb-4 text-danger"><i class="bi bi-fire me-2"></i>Top 5 Penghabis Biaya</h6>
                <div style="position: relative; height:300px; width:100%">
                    <canvas id="chartBiaya"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Kategori Table -->
    <div class="section-title"><i class="bi bi-tags-fill"></i> Performa Berdasarkan Kategori</div>
    <div class="card p-4 mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 25%">Kategori Produk</th>
                        <th class="text-end">Biaya (Rp)</th>
                        <th class="text-end">Omzet (Rp)</th>
                        <th class="text-center">ROAS</th>
                        <th class="text-center">ACOS</th>
                        <th class="text-center">CR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $catName => $catData): 
                        $cat_roas = $catData['biaya'] > 0 ? ($catData['omzet'] / $catData['biaya']) : 0;
                        $cat_acos = $catData['omzet'] > 0 ? ($catData['biaya'] / $catData['omzet'] * 100) : 0;
                        $cat_cr = $catData['klik'] > 0 ? ($catData['konversi'] / $catData['klik'] * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded"><i class="bi bi-box-seam"></i></div>
                                <span class="fw-bold text-dark"><?= $catName ?></span>
                            </div>
                        </td>
                        <td class="text-end fw-semibold text-danger"><?= number_format($catData['biaya'], 0, ',', '.') ?></td>
                        <td class="text-end fw-semibold text-success"><?= number_format($catData['omzet'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6"><?= number_format($cat_roas, 2) ?>x</span>
                        </td>
                        <td class="text-center fw-medium"><?= number_format($cat_acos, 2) ?>%</td>
                        <td class="text-center fw-medium"><?= number_format($cat_cr, 2) ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DataTables -->
    <div class="section-title"><i class="bi bi-table"></i> Detail Kampanye & Rekomendasi</div>
    <div class="card p-4 mb-5">
        <div class="table-responsive">
            <table id="adsTable" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th style="min-width: 250px;">Nama Iklan</th>
                        <th class="text-end">Dilihat</th>
                        <th class="text-end">Klik</th>
                        <th class="text-end">Biaya (Rp)</th>
                        <th class="text-end">Omzet (Rp)</th>
                        <th class="text-end">ROAS</th>
                        <th class="text-center" style="min-width: 200px;">Sistem Insight</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><span class="badge bg-light text-secondary border"><?= $p['_kategori'] ?></span></td>
                        <td class="fw-medium text-dark"><?= htmlspecialchars($p['_nama']) ?></td>
                        <td class="text-end text-muted" data-sort="<?= $p['Dilihat'] ?>"><?= number_format($p['Dilihat'], 0, ',', '.') ?></td>
                        <td class="text-end text-muted" data-sort="<?= $p['Jumlah Klik'] ?>"><?= number_format($p['Jumlah Klik'], 0, ',', '.') ?></td>
                        <td class="text-end fw-semibold text-danger" data-sort="<?= $p['_biaya'] ?>"><?= number_format($p['_biaya'], 0, ',', '.') ?></td>
                        <td class="text-end fw-semibold text-success" data-sort="<?= $p['_omzet'] ?>"><?= number_format($p['_omzet'], 0, ',', '.') ?></td>
                        <td class="text-end" data-sort="<?= $p['_roas'] ?>"><span class="badge bg-dark fs-6"><?= number_format($p['_roas'], 2) ?>x</span></td>
                        <td class="text-center"><?= $p['_insight'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const topOmzetLabels = <?= json_encode(array_column($top_omzet, '_nama')) ?>;
        const topOmzetData = <?= json_encode(array_column($top_omzet, '_omzet')) ?>;
        
        const topBiayaLabels = <?= json_encode(array_column($top_biaya, '_nama')) ?>;
        const topBiayaData = <?= json_encode(array_column($top_biaya, '_biaya')) ?>;

        const simplifyLabel = (label) => label.length > 25 ? label.substring(0, 25) + '...' : label;
        
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#64748b";
        
        const commonOptions = {
            indexAxis: 'y', 
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { display: false }
                },
                y: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { weight: '500' }, color: '#0f172a' }
                }
            }
        };

        new Chart(document.getElementById('chartOmzet'), {
            type: 'bar',
            data: {
                labels: topOmzetLabels.map(simplifyLabel),
                datasets: [{
                    data: topOmzetData,
                    backgroundColor: '#10b981',
                    borderRadius: 6,
                    barThickness: 24
                }]
            },
            options: commonOptions
        });

        new Chart(document.getElementById('chartBiaya'), {
            type: 'bar',
            data: {
                labels: topBiayaLabels.map(simplifyLabel),
                datasets: [{
                    data: topBiayaData,
                    backgroundColor: '#ef4444',
                    borderRadius: 6,
                    barThickness: 24
                }]
            },
            options: commonOptions
        });
    </script>
    <?php endif; ?>
</div>

<!-- Modal Kamus Istilah Ads -->
<div class="modal fade" id="kamusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-0 pt-4 px-4">
        <h4 class="modal-title fw-bold text-dark"><i class="bi bi-book-half text-primary me-2"></i> Panduan Metrik Ads</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 h-100">
                    <h6 class="fw-bold text-primary mb-2">Total Biaya Iklan (Spend)</h6>
                    <p class="text-muted small mb-0">Total uang yang dibayarkan ke Shopee untuk menayangkan iklan Anda.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 h-100">
                    <h6 class="fw-bold text-success mb-2">Total Omzet Penjualan (GMV)</h6>
                    <p class="text-muted small mb-0">Pendapatan kotor (GMV) yang didapatkan langsung dari pembeli yang mengklik iklan.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 h-100">
                    <h6 class="fw-bold text-dark mb-2">ROAS (Return on Ad Spend)</h6>
                    <p class="text-muted small mb-0">Rasio keuntungan dari iklan. Jika ROAS 10x artinya modal Rp 1.000 menghasilkan Rp 10.000.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 h-100">
                    <h6 class="fw-bold text-warning mb-2">ACOS (Biaya / Omzet)</h6>
                    <p class="text-muted small mb-0">Persentase budget iklan dari omzet. ACOS 5% artinya dari omzet Rp 100rb, biaya iklannya Rp 5rb.</p>
                </div>
            </div>
        </div>
        
        <hr class="my-4 text-muted">
        
        <h6 class="fw-bold text-dark mb-3">Cara Membaca Sistem Insight (AI)</h6>
        <div class="d-flex flex-column gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2" style="min-width: 140px;">Sangat Menguntungkan</span>
                <span class="text-muted small">ROAS &ge; 10x. Rekomendasi: Naikkan batas budget harian agar omzet lebih maksimal.</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" style="min-width: 140px;">Menguntungkan</span>
                <span class="text-muted small">ROAS 5x - 10x. Iklan sehat, biarkan dan pertahankan strategi yang ada.</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2" style="min-width: 140px;">Evaluasi (Optimasi)</span>
                <span class="text-muted small">ROAS < 5x. Profit tipis. Rekomendasi: Evaluasi foto, judul, atau turunkan bid.</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2" style="min-width: 140px;">Boncos (Hentikan)</span>
                <span class="text-muted small">Biaya > Rp 15rb namun ROAS 0. Rekomendasi: Hentikan iklan segera.</span>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#adsTable').DataTable({
            "pageLength": 100,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            "order": [[ 6, "desc" ]], 
            "language": {
                "search": "Cari (Kategori/Iklan):",
                "lengthMenu": "Tampilkan _MENU_ data",
                "zeroRecords": "Tidak ada iklan yang ditemukan",
                "info": "Menampilkan _START_ - _END_ dari _TOTAL_ iklan",
                "infoEmpty": "Tidak ada data tersedia",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "Lanjut",
                    "previous": "Kembali"
                }
            }
        });
    });
</script>
</body>
</html>
