<?php
// Prevent caching on proxies, LiteSpeed, and browsers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ==========================================
// SAFE DATA STORAGE & PERSISTENCE
// ==========================================
function resolveDataFilePath() {
    $candidates = [
        __DIR__ . '/data_polo.json',
        __DIR__ . '/../ads_data/data_polo.json',
        __DIR__ . '/../../ads_data/data_polo.json',
        __DIR__ . '/data_storage/data_polo.json',
        __DIR__ . '/../data_storage/data_polo.json'
    ];

    // Prioritaskan file yang memiliki ukuran data terbesar (berisi produk nyata)
    $bestFile = null;
    $maxSize = -1;

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            $sz = filesize($file);
            if ($sz > $maxSize) {
                $maxSize = $sz;
                $bestFile = $file;
            }
        }
    }

    if ($bestFile && $maxSize > 500) {
        // Sinkronisasi ke folder ../ads_data jika folder tersebut ada
        $securePath = __DIR__ . '/../ads_data/data_polo.json';
        if ($bestFile !== $securePath && is_dir(dirname($securePath))) {
            @copy($bestFile, $securePath);
        }
        return $bestFile;
    }

    return __DIR__ . '/data_polo.json';
}

function saveDataWithBackup($filePath, $data) {
    $dir = dirname($filePath);
    $backupDir = $dir . '/backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    // Buat backup harian & backup terakhir jika file saat ini ada isinya
    if (file_exists($filePath) && filesize($filePath) > 0) {
        $dailyBackup = $backupDir . '/data_polo_backup_' . date('Ymd') . '.json';
        if (!file_exists($dailyBackup)) {
            @copy($filePath, $dailyBackup);
        }
        @copy($filePath, $backupDir . '/data_polo_latest_backup.json');

        // Rotasi backup (simpan maksimal 7 backup harian terakhir)
        $backups = glob($backupDir . '/data_polo_backup_*.json');
        if ($backups && count($backups) > 7) {
            sort($backups);
            $toDelete = array_slice($backups, 0, count($backups) - 7);
            foreach ($toDelete as $oldFile) {
                @unlink($oldFile);
            }
        }
    }

    // Simpan data secara atomic (tulis ke temp lalu rename) agar tidak korup
    $tempFile = $filePath . '.tmp.' . uniqid();
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($tempFile, $encoded) !== false) {
        if (@rename($tempFile, $filePath)) {
            return true;
        }
    }
    return @file_put_contents($filePath, $encoded) !== false;
}

$dataFile = resolveDataFilePath();

if (!file_exists($dataFile)) {
    saveDataWithBackup($dataFile, ['products' => [], 'meta_period_dates' => [], 'meta_period_days' => []]);
}
$data = json_decode(file_get_contents($dataFile), true);

if (!isset($data['meta_period_dates'])) $data['meta_period_dates'] = [];
if (!isset($data['meta_period_days'])) $data['meta_period_days'] = [];

// ==========================================
// 1. DATA MIGRATION LOGIC
// ==========================================
$is_migrated = false;
$current_system_year = date('Y');
$current_system_month = date('m');

foreach ($data['products'] as $kode => &$p) {
    if (isset($p['periods']) && !isset($p['history'])) {
        $p['history'] = [
            $current_system_year => [
                $current_system_month => $p['periods']
            ]
        ];
        unset($p['periods']);
        $is_migrated = true;
    }
}
unset($p);
if ($is_migrated) {
    saveDataWithBackup($dataFile, $data);
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================
function determineCategory($name) {
    $nameLower = strtolower($name);
    if (strpos($nameLower, 'polo shirt') !== false || strpos($nameLower, 'kaos polo') !== false || strpos($nameLower, 'polo') !== false) return 'Polo Shirt';
    if (strpos($nameLower, 'celana chino') !== false || strpos($nameLower, 'chinos') !== false) return 'Celana Chino';
    if (strpos($nameLower, 'celana pendek') !== false || strpos($nameLower, 'shorts') !== false || strpos($nameLower, 'cargo pendek') !== false) return 'Celana Pendek';
    if (strpos($nameLower, 'celana bahan') !== false || strpos($nameLower, 'celana panjang') !== false || strpos($nameLower, 'ankle pants') !== false || strpos($nameLower, 'trouser') !== false) return 'Celana Panjang / Formal';
    if (strpos($nameLower, 'kemeja') !== false || strpos($nameLower, 'oxford') !== false || strpos($nameLower, 'shirt') !== false) return 'Kemeja';
    if (strpos($nameLower, 'rompi') !== false || strpos($nameLower, 'vest') !== false) return 'Rompi / Vest';
    if (strpos($nameLower, 'parfum') !== false || strpos($nameLower, 'edp') !== false) return 'Parfum';
    if (strpos($nameLower, 'sweater') !== false || strpos($nameLower, 'crewneck') !== false) return 'Sweater';
    if (strpos($nameLower, 'jaket') !== false || strpos($nameLower, 'jacket') !== false) return 'Jaket';
    if (strpos($nameLower, 'kaos kaki') !== false || strpos($nameLower, 'sock') !== false || strpos($nameLower, 'sabuk') !== false) return 'Aksesoris';
    return 'Lainnya';
}

function determineBrand($name) {
    $nameClean = strtolower(str_replace([' ', '-', '_'], '', $name ?? ''));
    if (strpos($nameClean, 'bluebutton') !== false) return 'BLUEBUTTON';
    if (strpos($nameClean, 'privateproject') !== false) return 'Private Project';
    if (strpos($nameClean, 'littlepampered') !== false) return 'Little Pampered';
    if (strpos($nameClean, 'olere') !== false) return 'Olere';
    return 'Lainnya';
}

function formatShopeeDateRange($date_str) {
    if (preg_match_all('/(\d{2})[\/\_](\d{2})[\/\_](\d{4})/', $date_str, $matches)) {
        if (count($matches[0]) >= 2) {
            $months_short = [
                '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
                '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agt',
                '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
            ];
            
            $d1_d = $matches[1][0];
            $d1_m = $matches[2][0];
            $d1_y = $matches[3][0];
            
            $d2_d = $matches[1][1];
            $d2_m = $matches[2][1];
            $d2_y = $matches[3][1];
            
            if ($d1_y === $d2_y && $d1_m === $d2_m) {
                return ltrim($d1_d, '0') . "-" . ltrim($d2_d, '0') . " " . $months_short[$d1_m] . " " . $d1_y;
            } elseif ($d1_y === $d2_y) {
                return ltrim($d1_d, '0') . " " . $months_short[$d1_m] . " - " . ltrim($d2_d, '0') . " " . $months_short[$d2_m] . " " . $d1_y;
            } else {
                return ltrim($d1_d, '0') . " " . $months_short[$d1_m] . " " . $d1_y . " - " . ltrim($d2_d, '0') . " " . $months_short[$d2_m] . " " . $d2_y;
            }
        }
    }
    return $date_str;
}

$months_id = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$message = "";

// Initialize Database Connection Early
$db_conn = null;
try {
    $db_conn = @mysqli_connect('localhost', 'root', '', 'wms_gmk');
    if ($db_conn) {
        @mysqli_query($db_conn, "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }
} catch (Throwable $e) {
    $db_conn = null;
}

// Determine default and active period filter
$latest_year_with_data = null;
$latest_month_with_data = null;

if ($db_conn) {
    $q_max = @mysqli_query($db_conn, "SELECT MONTH(perf_date) as m, YEAR(perf_date) as y, COUNT(*) as cnt FROM shopee_ads_daily_item_perf WHERE perf_date IS NOT NULL GROUP BY YEAR(perf_date), MONTH(perf_date) ORDER BY perf_date DESC LIMIT 1");
    if ($q_max && $r_max = mysqli_fetch_assoc($q_max)) {
        $latest_month_with_data = str_pad($r_max['m'], 2, '0', STR_PAD_LEFT);
        $latest_year_with_data = (string)$r_max['y'];
    }
}

if (!$latest_month_with_data && isset($data['products'])) {
    foreach ($data['products'] as $p) {
        if (!empty($p['history'])) {
            foreach ($p['history'] as $y => $months) {
                foreach ($months as $m => $periods) {
                    foreach ($periods as $per) {
                        if (($per['biaya'] ?? 0) > 0 || ($per['qty'] ?? 0) > 0) {
                            $latest_year_with_data = (string)$y;
                            $latest_month_with_data = str_pad($m, 2, '0', STR_PAD_LEFT);
                            break 3;
                        }
                    }
                }
            }
        }
    }
}

$default_year = $latest_year_with_data ?? $current_system_year;
$default_month = $latest_month_with_data ?? $current_system_month;

$filter_year = isset($_GET['year']) && !empty($_GET['year']) ? (string)$_GET['year'] : (isset($_POST['up_year']) && !empty($_POST['up_year']) ? (string)$_POST['up_year'] : $default_year);
$filter_month = isset($_GET['month']) && !empty($_GET['month']) ? str_pad($_GET['month'], 2, '0', STR_PAD_LEFT) : (isset($_POST['up_month']) && !empty($_POST['up_month']) ? str_pad($_POST['up_month'], 2, '0', STR_PAD_LEFT) : $default_month);
$all_brands = ['BLUEBUTTON', 'Private Project', 'Little Pampered', 'Olere', 'Lainnya'];
$filter_category = $_GET['cat'] ?? ($_POST['filter_category'] ?? 'Polo Shirt');
$filter_brand = trim((string)($_GET['brand'] ?? ($_POST['filter_brand'] ?? 'Semua')));
if ($filter_brand !== '' && strcasecmp($filter_brand, 'Semua') !== 0) {
    $norm_filter = strtolower(str_replace([' ', '-', '_'], '', $filter_brand));
    foreach ($all_brands as $b) {
        if (strtolower(str_replace([' ', '-', '_'], '', $b)) === $norm_filter) {
            $filter_brand = $b;
            break;
        }
    }
} else {
    $filter_brand = 'Semua';
}

// ==========================================
// 2. FORM ACTIONS & AJAX
// ==========================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_field') {
        $kode = $_POST['kode'] ?? '';
        $field = $_POST['field'] ?? '';
        $value = trim((string)($_POST['value'] ?? ''));

        // Target HARUS angka bulat (integer, bukan desimal)
        if ($field === 'target_roas' || $field === 'target_qty') {
            $int_val = (int)round((float)$value);
            if ($int_val <= 0) {
                $int_val = ($field === 'target_roas' ? 10 : 25);
            }
            $value = (string)$int_val;
        } elseif ($field === 'budget_plan_pct') {
            $int_val = (int)round((float)$value);
            $value = (string)$int_val;
        }

        if (isset($data['products'][$kode])) {
            $data['products'][$kode][$field] = $value;
            saveDataWithBackup($dataFile, $data);

            // Sinkronisasi ke database MySQL shopee_ads_targets
            if ($db_conn && ($field === 'target_roas' || $field === 'target_qty')) {
                $c_id = $data['products'][$kode]['kode'] ?? $kode;
                $ad_name = $data['products'][$kode]['nama'] ?? '';
                $cat = $data['products'][$kode]['kategori'] ?? 'POLO SHIRT';

                $c_id_esc = mysqli_real_escape_string($db_conn, $c_id);
                $ad_name_esc = mysqli_real_escape_string($db_conn, $ad_name);
                $cat_esc = mysqli_real_escape_string($db_conn, strtoupper($cat));
                $val_num = (float)$value;
                $my_esc = mysqli_real_escape_string($db_conn, "$filter_year-$filter_month");

                mysqli_query($db_conn, "UPDATE shopee_ads_targets 
                                       SET $field = $val_num, updated_at = NOW() 
                                       WHERE campaign_id = '$c_id_esc' OR ad_name = '$ad_name_esc'");

                mysqli_query($db_conn, "INSERT INTO shopee_ads_targets (campaign_id, ad_name, category, month_year, $field, updated_at) 
                                       VALUES ('$c_id_esc', '$ad_name_esc', '$cat_esc', '$my_esc', $val_num, NOW()) 
                                       ON DUPLICATE KEY UPDATE $field = $val_num, updated_at = NOW()");
            }

            echo json_encode(['status' => 'success', 'value' => $value]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        }
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_update_field') {
        $field = $_POST['field'] ?? '';
        $value = trim((string)($_POST['value'] ?? ''));
        $brand_filter = $_POST['filter_brand'] ?? 'Semua';
        $cat_filter = $_POST['filter_category'] ?? 'Semua';

        // Target HARUS angka bulat (integer, bukan desimal)
        if ($field === 'target_roas' || $field === 'target_qty') {
            $int_val = (int)round((float)$value);
            if ($int_val <= 0) {
                $int_val = ($field === 'target_roas' ? 10 : 25);
            }
            $value = (string)$int_val;
        }

        $matching_kodes = [];
        foreach ($data['products'] as $kode => &$p) {
            $brand = determineBrand($p['nama'] ?? '');
            $cat_match = ($cat_filter === 'Semua' || strcasecmp($p['kategori'] ?? '', $cat_filter) === 0);
            $brand_match = ($brand_filter === 'Semua' || strcasecmp(str_replace(' ', '', $brand), str_replace(' ', '', $brand_filter)) === 0);
            
            if ($cat_match && $brand_match) {
                $p[$field] = $value;
                $matching_kodes[] = $kode;
            }
        }
        unset($p);
        
        saveDataWithBackup($dataFile, $data);

        // Sinkronisasi ke MySQL shopee_ads_targets dan shopee_ads_category_targets
        if ($db_conn && ($field === 'target_roas' || $field === 'target_qty')) {
            $val_num = (float)$value;
            $my_esc = mysqli_real_escape_string($db_conn, "$filter_year-$filter_month");

            // Update default target per category jika ada
            if ($cat_filter !== 'Semua') {
                $cat_esc = mysqli_real_escape_string($db_conn, strtoupper($cat_filter));
                mysqli_query($db_conn, "UPDATE shopee_ads_category_targets 
                                       SET $field = $val_num, updated_at = NOW() 
                                       WHERE UPPER(category_name) = '$cat_esc'");
            }

            // Update setiap produk yang cocok di tabel target
            foreach ($matching_kodes as $mkode) {
                $m_p = $data['products'][$mkode];
                $c_id_esc = mysqli_real_escape_string($db_conn, $m_p['kode'] ?? $mkode);
                $ad_name_esc = mysqli_real_escape_string($db_conn, $m_p['nama'] ?? '');
                $cat_esc = mysqli_real_escape_string($db_conn, strtoupper($m_p['kategori'] ?? 'POLO SHIRT'));

                mysqli_query($db_conn, "UPDATE shopee_ads_targets 
                                       SET $field = $val_num, updated_at = NOW() 
                                       WHERE campaign_id = '$c_id_esc' OR ad_name = '$ad_name_esc'");

                mysqli_query($db_conn, "INSERT INTO shopee_ads_targets (campaign_id, ad_name, category, month_year, $field, updated_at) 
                                       VALUES ('$c_id_esc', '$ad_name_esc', '$cat_esc', '$my_esc', $val_num, NOW()) 
                                       ON DUPLICATE KEY UPDATE $field = $val_num, updated_at = NOW()");
            }
        }

        echo json_encode(['status' => 'success', 'count' => count($matching_kodes), 'value' => $value]);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'get_history') {
        $kode = $_POST['kode'];
        if (isset($data['products'][$kode])) {
            echo json_encode([
                'status' => 'success',
                'nama' => $data['products'][$kode]['nama'],
                'history' => $data['products'][$kode]['history'] ?? []
            ]);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'upload' && isset($_FILES['file'])) {
        $up_year = $_POST['up_year'];
        $up_month = $_POST['up_month'];
        $period = $_POST['period'];
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension == 'csv') {
            if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                $header_found = false;
                $header = [];
                $periode_string = "";
                
                while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    if (!$header_found) {
                        if (isset($row[0]) && $row[0] === 'Periode' && isset($row[1])) {
                            $periode_string = trim($row[1]);
                        }
                        if (isset($row[0]) && $row[0] == 'Urutan' && isset($row[1]) && $row[1] == 'Nama Iklan') {
                            $header_found = true;
                            $header = $row;
                        }
                        continue;
                    }
                    
                    if (count($row) == count($header)) {
                        $item = array_combine($header, $row);
                        $nama = $item['Nama Iklan'];
                        $kategori = determineCategory($nama);
                        
                        $kode = $item['Kode Produk'];
                        $biaya = (float)str_replace(',', '', $item['Biaya'] ?? 0);
                        $omzet = (float)str_replace(',', '', $item['Omzet Penjualan'] ?? 0);
                        $qty = (int)str_replace(',', '', $item['Produk Terjual'] ?? 0);
                        
                        if (!isset($data['products'][$kode])) {
                            $data['products'][$kode] = [
                                'kode' => $kode,
                                'nama' => $nama,
                                'kategori' => $kategori,
                                'target_roas' => '18-20',
                                'target_qty' => 25,
                                'budget_plan_pct' => 30,
                                'history' => []
                            ];
                        }
                        
                        $data['products'][$kode]['kategori'] = $kategori;
                        $data['products'][$kode]['nama'] = $nama;
                        
                        if (!isset($data['products'][$kode]['history'][$up_year][$up_month])) {
                            $data['products'][$kode]['history'][$up_year][$up_month] = [
                                'w1' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
                                'w2' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
                                'w3' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
                                'w4' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
                                'twin' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
                                'payday' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0]
                            ];
                        }
                        
                        $data['products'][$kode]['history'][$up_year][$up_month][$period]['biaya'] = $biaya;
                        $data['products'][$kode]['history'][$up_year][$up_month][$period]['omzet'] = $omzet;
                        $data['products'][$kode]['history'][$up_year][$up_month][$period]['qty'] = $qty;
                    }
                }
                fclose($handle);
                
                $raw_filename = pathinfo($fileName, PATHINFO_FILENAME);
                
                // 1. Calculate Days (Must use raw unformatted string)
                $days_count = 1; 
                $date_to_parse = $periode_string !== "" ? $periode_string : $raw_filename;
                if (preg_match_all('/(\d{2})[\/\_](\d{2})[\/\_](\d{4})/', $date_to_parse, $matches)) {
                    if (count($matches[0]) >= 2) {
                        $d1_str = $matches[3][0] . "-" . $matches[2][0] . "-" . $matches[1][0];
                        $d2_str = $matches[3][1] . "-" . $matches[2][1] . "-" . $matches[1][1];
                        try {
                            $dt1 = new DateTime($d1_str);
                            $dt2 = new DateTime($d2_str);
                            $days_count = $dt1->diff($dt2)->days + 1;
                        } catch (Exception $e) {}
                    }
                }
                
                // 2. Set Label Date Range
                $formatted_date = "";
                if ($periode_string !== "") {
                    $formatted_date = formatShopeeDateRange($periode_string);
                }
                if ($formatted_date === "" || $formatted_date === $periode_string) {
                    $formatted_date = formatShopeeDateRange($raw_filename);
                }
                
                if (strpos(strtolower($formatted_date), 'shopee') !== false || strpos(strtolower($formatted_date), 'laporan') !== false) {
                    $fileLabel = "Cek File";
                } else {
                    $fileLabel = $formatted_date;
                }
                
                if (!isset($data['meta_period_dates'][$up_year])) $data['meta_period_dates'][$up_year] = [];
                if (!isset($data['meta_period_dates'][$up_year][$up_month])) $data['meta_period_dates'][$up_year][$up_month] = [];
                $data['meta_period_dates'][$up_year][$up_month][$period] = $fileLabel;
                
                if (!isset($data['meta_period_days'][$up_year])) $data['meta_period_days'][$up_year] = [];
                if (!isset($data['meta_period_days'][$up_year][$up_month])) $data['meta_period_days'][$up_year][$up_month] = [];
                $data['meta_period_days'][$up_year][$up_month][$period] = $days_count > 0 ? $days_count : 1;
                
                saveDataWithBackup($dataFile, $data);
                
                $_GET['year'] = $up_year;
                $_GET['month'] = $up_month;
                $message = "<div class='alert alert-success border-0 shadow-sm fw-bold px-3 py-2 d-inline-flex align-items-center mb-0'><i class='bi bi-check-circle-fill me-2'></i> Data " . strtoupper($period) . " ({$fileLabel}) berhasil diupload.</div>";
            }
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'clear') {
        $clr_year = $_POST['up_year'];
        $clr_month = $_POST['up_month'];
        $period = $_POST['period'];
        
        foreach ($data['products'] as $kode => &$p) {
            if (isset($p['history'][$clr_year][$clr_month][$period])) {
                $p['history'][$clr_year][$clr_month][$period] = ['biaya' => 0, 'omzet' => 0, 'qty' => 0];
            }
        }
        unset($p);
        
        if (isset($data['meta_period_dates'][$clr_year][$clr_month][$period])) unset($data['meta_period_dates'][$clr_year][$clr_month][$period]);
        if (isset($data['meta_period_days'][$clr_year][$clr_month][$period])) unset($data['meta_period_days'][$clr_year][$clr_month][$period]);
        
        saveDataWithBackup($dataFile, $data);
        
        $_GET['year'] = $clr_year;
        $_GET['month'] = $clr_month;
        $message = "<div class='alert alert-warning border-0 shadow-sm fw-bold px-3 py-2 d-inline-flex align-items-center mb-0'><i class='bi bi-trash-fill me-2'></i> Data " . strtoupper($period) . " {$months_id[$clr_month]} {$clr_year} dikosongkan.</div>";
    }
}

// ==========================================
// 3. DATABASE INTEGRATION & FILTERING
// ==========================================
// Sinkronisasi data dari Database MySQL jika ada untuk periode ini
if ($db_conn) {
    $twin_day = (int)$filter_month;
    $m_days = cal_days_in_month(CAL_GREGORIAN, (int)$filter_month, (int)$filter_year);
    if ($twin_day > $m_days) $twin_day = $m_days;

    $sql_db_load = "SELECT 
        p.ad_name,
        p.category,
        p.campaign_id,
        MAX(t.target_roas) as target_roas,
        MAX(t.target_qty) as target_qty,
        30 as budget_plan_pct,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 1 AND 7 THEN p.expense ELSE 0 END) as w1_biaya,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 1 AND 7 THEN p.gmv ELSE 0 END) as w1_omzet,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 1 AND 7 THEN p.qty_sold ELSE 0 END) as w1_qty,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 8 AND 14 THEN p.expense ELSE 0 END) as w2_biaya,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 8 AND 14 THEN p.gmv ELSE 0 END) as w2_omzet,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 8 AND 14 THEN p.qty_sold ELSE 0 END) as w2_qty,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 15 AND 21 THEN p.expense ELSE 0 END) as w3_biaya,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 15 AND 21 THEN p.gmv ELSE 0 END) as w3_omzet,
        SUM(CASE WHEN DAY(p.perf_date) BETWEEN 15 AND 21 THEN p.qty_sold ELSE 0 END) as w3_qty,
        SUM(CASE WHEN DAY(p.perf_date) >= 22 THEN p.expense ELSE 0 END) as w4_biaya,
        SUM(CASE WHEN DAY(p.perf_date) >= 22 THEN p.gmv ELSE 0 END) as w4_omzet,
        SUM(CASE WHEN DAY(p.perf_date) >= 22 THEN p.qty_sold ELSE 0 END) as w4_qty,
        SUM(CASE WHEN DAY(p.perf_date) = $twin_day THEN p.expense ELSE 0 END) as twin_biaya,
        SUM(CASE WHEN DAY(p.perf_date) = $twin_day THEN p.gmv ELSE 0 END) as twin_omzet,
        SUM(CASE WHEN DAY(p.perf_date) = $twin_day THEN p.qty_sold ELSE 0 END) as twin_qty,
        SUM(CASE WHEN DAY(p.perf_date) >= 25 THEN p.expense ELSE 0 END) as payday_biaya,
        SUM(CASE WHEN DAY(p.perf_date) >= 25 THEN p.gmv ELSE 0 END) as payday_omzet,
        SUM(CASE WHEN DAY(p.perf_date) >= 25 THEN p.qty_sold ELSE 0 END) as payday_qty
    FROM shopee_ads_daily_item_perf p
    LEFT JOIN shopee_ads_targets t ON (t.campaign_id = p.campaign_id OR t.ad_name = p.ad_name)
    WHERE YEAR(p.perf_date) = " . (int)$filter_year . " AND MONTH(p.perf_date) = " . (int)$filter_month . "
    GROUP BY p.ad_name";

    $q_db_res = @mysqli_query($db_conn, $sql_db_load);
    if ($q_db_res && mysqli_num_rows($q_db_res) > 0) {
        while ($rdb = mysqli_fetch_assoc($q_db_res)) {
            $ad_name = $rdb['ad_name'];
            $c_id = $rdb['campaign_id'];
            $cat_name = !empty($rdb['category']) && $rdb['category'] !== 'Uncategorized' ? $rdb['category'] : determineCategory($ad_name);

            // Cari matching product di JSON atau buat baru
            $matched_kode = null;
            foreach ($data['products'] as $k => $prod) {
                if (($prod['nama'] ?? '') === $ad_name || ($prod['kode'] ?? '') === $c_id) {
                    $matched_kode = $k;
                    break;
                }
            }
            if (!$matched_kode) {
                $matched_kode = $c_id ?: md5($ad_name);
                $data['products'][$matched_kode] = [
                    'kode' => $matched_kode,
                    'nama' => $ad_name,
                    'kategori' => $cat_name,
                    'target_roas' => !empty($rdb['target_roas']) ? (string)(int)round((float)$rdb['target_roas']) : '10',
                    'target_qty' => !empty($rdb['target_qty']) ? (string)(int)round((float)$rdb['target_qty']) : '25',
                    'budget_plan_pct' => 30,
                    'history' => []
                ];
            }

            $data['products'][$matched_kode]['history'][$filter_year][$filter_month] = [
                'w1' => ['biaya' => (float)$rdb['w1_biaya'], 'omzet' => (float)$rdb['w1_omzet'], 'qty' => (int)$rdb['w1_qty']],
                'w2' => ['biaya' => (float)$rdb['w2_biaya'], 'omzet' => (float)$rdb['w2_omzet'], 'qty' => (int)$rdb['w2_qty']],
                'w3' => ['biaya' => (float)$rdb['w3_biaya'], 'omzet' => (float)$rdb['w3_omzet'], 'qty' => (int)$rdb['w3_qty']],
                'w4' => ['biaya' => (float)$rdb['w4_biaya'], 'omzet' => (float)$rdb['w4_omzet'], 'qty' => (int)$rdb['w4_qty']],
                'twin' => ['biaya' => (float)$rdb['twin_biaya'], 'omzet' => (float)$rdb['twin_omzet'], 'qty' => (int)$rdb['twin_qty']],
                'payday' => ['biaya' => (float)$rdb['payday_biaya'], 'omzet' => (float)$rdb['payday_omzet'], 'qty' => (int)$rdb['payday_qty']]
            ];
            
            // Format target selalu angka bulat (integer)
            if (!empty($rdb['target_roas'])) {
                $data['products'][$matched_kode]['target_roas'] = (string)(int)round((float)$rdb['target_roas']);
            } elseif (isset($data['products'][$matched_kode]['target_roas'])) {
                $data['products'][$matched_kode]['target_roas'] = (string)(int)round((float)$data['products'][$matched_kode]['target_roas']);
            } else {
                $data['products'][$matched_kode]['target_roas'] = '10';
            }

            if (!empty($rdb['target_qty'])) {
                $data['products'][$matched_kode]['target_qty'] = (string)(int)round((float)$rdb['target_qty']);
            } elseif (isset($data['products'][$matched_kode]['target_qty'])) {
                $data['products'][$matched_kode]['target_qty'] = (string)(int)round((float)$data['products'][$matched_kode]['target_qty']);
            } else {
                $data['products'][$matched_kode]['target_qty'] = '25';
            }
        }
    }
}

// Pastikan semua produk memiliki target berupa angka bulat (integer, bukan desimal)
foreach ($data['products'] as &$p) {
    if (isset($p['target_roas'])) {
        $p['target_roas'] = (string)(int)round((float)$p['target_roas']);
    } else {
        $p['target_roas'] = '10';
    }
    if (isset($p['target_qty'])) {
        $p['target_qty'] = (string)(int)round((float)$p['target_qty']);
    } else {
        $p['target_qty'] = '25';
    }
}
unset($p);

$all_categories = [];
$available_years = [];

foreach ($data['products'] as $p) {
    if (isset($p['kategori'])) $all_categories[$p['kategori']] = true;
    if (isset($p['history'])) {
        foreach (array_keys($p['history']) as $y) {
            $available_years[$y] = true;
        }
    }
}
$available_years[$current_system_year] = true; 
$all_categories = array_keys($all_categories);
sort($all_categories);
$years_list = array_keys($available_years);
rsort($years_list); 

// Kategori yang benar-benar ada pada brand yang sedang aktif
$brand_categories = [];
foreach ($data['products'] as $p) {
    $b = determineBrand($p['nama'] ?? '');
    if ($filter_brand === 'Semua' || strcasecmp(str_replace(' ', '', $b), str_replace(' ', '', $filter_brand)) === 0) {
        if (!empty($p['kategori'])) {
            $brand_categories[$p['kategori']] = true;
        }
    }
}
$brand_categories = array_keys($brand_categories);
sort($brand_categories);

// Validasi kategori terpilih
if ($filter_brand !== 'Semua') {
    // Jika user memilih brand spesifik dan kategori saat ini tidak ada pada brand tersebut, fallback ke 'Semua'
    if ($filter_category !== 'Semua' && !in_array($filter_category, $brand_categories)) {
        $filter_category = 'Semua';
    }
} else {
    if ($filter_category !== 'Semua' && !in_array($filter_category, $all_categories) && count($all_categories) > 0) {
        $filter_category = $all_categories[0];
    }
}

$display_categories = ($filter_brand === 'Semua' ? $all_categories : $brand_categories);

$days_meta = $data['meta_period_days'][$filter_year][$filter_month] ?? [];

function getQtyStatus($qty_per_day, $target_qty) {
    $qty = (float)$qty_per_day;
    if ($qty <= 0) return ['label' => '-', 'class' => ''];
    
    $target_str = trim((string)$target_qty);
    if ($target_str === '') return ['label' => '-', 'class' => ''];
    if (strpos($target_str, '-') !== false) {
        $parts = explode('-', $target_str);
        $target = (float)trim($parts[0]);
    } else {
        $target = (float)$target_str;
    }
    if ($target <= 0) return ['label' => '-', 'class' => ''];
    
    if ($qty > $target * 1.5) {
        return ['label' => 'Excellent', 'class' => 'status-excellent'];
    } elseif ($qty >= $target) {
        return ['label' => 'Good', 'class' => 'status-good'];
    } else {
        return ['label' => 'Bad', 'class' => 'status-bad'];
    }
}

function getRoasStatus($roas, $target_roas) {
    $roas = (float)$roas;
    if ($roas <= 0) return ['label' => '-', 'class' => ''];
    
    $target_str = trim((string)$target_roas);
    if ($target_str === '') return ['label' => '-', 'class' => ''];
    if (strpos($target_str, '-') !== false) {
        $parts = explode('-', $target_str);
        $target = (float)trim($parts[0]);
    } else {
        $target = (float)$target_str;
    }
    if ($target <= 0) return ['label' => '-', 'class' => ''];
    
    if ($roas > $target * 1.5) {
        return ['label' => 'Excellent', 'class' => 'status-excellent'];
    } elseif ($roas >= $target) {
        return ['label' => 'Good', 'class' => 'status-good'];
    } else {
        return ['label' => 'Bad', 'class' => 'status-bad'];
    }
}

$products = [];
$kpi_total_spend = 0;
$kpi_total_gmv = 0;
$kpi_total_qty = 0;
$kpi_active_count = 0;
$kpi_excellent_count = 0;
$kpi_good_count = 0;
$kpi_bad_count = 0;

foreach ($data['products'] as $kode => $p) {
    $brand = determineBrand($p['nama'] ?? '');
    
    $cat_match = ($filter_category === 'Semua' || (isset($p['kategori']) && $p['kategori'] === $filter_category));
    $brand_match = ($filter_brand === 'Semua' || strcasecmp(str_replace(' ', '', $brand), str_replace(' ', '', $filter_brand)) === 0);
    
    if ($cat_match && $brand_match) {
        
        $h = $p['history'][$filter_year][$filter_month] ?? [
            'w1' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
            'w2' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
            'w3' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
            'w4' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
            'twin' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0],
            'payday' => ['biaya'=>0, 'omzet'=>0, 'qty'=>0]
        ];
        
        $p['_display_history'] = $h;
        
        $sum_biaya_daily = 0;
        $sum_omzet = 0;
        $sum_qty_daily = 0;
        $sum_biaya_raw = 0;
        $sum_qty_raw = 0;
        $weeks_active = 0;
        
        foreach (['w1', 'w2', 'w3', 'w4'] as $w) {
            if ($h[$w]['biaya'] > 0 || $h[$w]['qty'] > 0) {
                $d = $days_meta[$w] ?? 7; // default 7 hari per week
                if ($d < 1) $d = 1;
                
                $sum_biaya_daily += $h[$w]['biaya'] / $d;
                $sum_qty_daily += $h[$w]['qty'] / $d;
                $sum_biaya_raw += $h[$w]['biaya'];
                $sum_qty_raw += $h[$w]['qty'];
                $sum_omzet += $h[$w]['omzet'];
                $weeks_active++;
            }
        }
        
        $p['_calc_rata2_roas'] = $sum_biaya_raw > 0 ? ($sum_omzet / $sum_biaya_raw) : 0;
        $p['_calc_rata2_qty'] = $weeks_active > 0 ? ($sum_qty_daily / $weeks_active) : 0;
        $p['_calc_rata2_budget'] = $weeks_active > 0 ? ($sum_biaya_daily / $weeks_active) : 0;
        $p['_calc_budget_plan'] = $p['_calc_rata2_budget'] * (1 + (($p['budget_plan_pct'] ?? 30) / 100));
        $p['_total_month_exp'] = $sum_biaya_raw;
        $p['_total_month_qty'] = $sum_qty_raw;
        $p['_total_month_gmv'] = $sum_omzet;
        
        // Periksa status
        $sts_check = getQtyStatus($p['_calc_rata2_qty'], $p['target_qty'] ?? 0);
        $p['_status_label'] = $sts_check['label'];
        
        if ($sts_check['label'] === 'Excellent') $kpi_excellent_count++;
        elseif ($sts_check['label'] === 'Good') $kpi_good_count++;
        elseif ($sts_check['label'] === 'Bad') $kpi_bad_count++;

        if ($sum_biaya_raw > 0 || $sum_qty_raw > 0) {
            $kpi_active_count++;
        }
        $kpi_total_spend += $sum_biaya_raw;
        $kpi_total_gmv += $sum_omzet;
        $kpi_total_qty += $sum_qty_raw;

        $products[$kode] = $p;
    }
}

uasort($products, function($a, $b) {
    return $b['_calc_rata2_roas'] <=> $a['_calc_rata2_roas'];
});

$kpi_total_products = count($products);
$kpi_overall_roas = $kpi_total_spend > 0 ? ($kpi_total_gmv / $kpi_total_spend) : 0;

$title_text = "PERFORMA HISTORY ROAS - " . strtoupper($months_id[$filter_month] ?? '') . " " . $filter_year;

$labels = $data['meta_period_dates'][$filter_year][$filter_month] ?? [];
$lbl_w1 = $labels['w1'] ?? '-';
$lbl_w2 = $labels['w2'] ?? '-';
$lbl_w3 = $labels['w3'] ?? '-';
$lbl_w4 = $labels['w4'] ?? '-';
$lbl_twin = $labels['twin'] ?? '-';
$lbl_payday = $labels['payday'] ?? '-';

function rCell($period_data, $css_class, $days_count = 1, $target_qty = 0, $target_roas = '') {
    if ($days_count < 1) $days_count = 1;
    
    $roas = $period_data['biaya'] > 0 ? ($period_data['omzet'] / $period_data['biaya']) : 0;
    $biaya_per_day = $period_data['biaya'] / $days_count;
    $qty_per_day = $period_data['qty'] / $days_count;
    
    $r_text = $roas > 0 ? number_format($roas, 0) : '-';
    $b_text = $period_data['biaya'] > 0 ? number_format($biaya_per_day, 0, ',', '.') : '-';
    $q_text = $period_data['qty'] > 0 ? number_format($qty_per_day, 1, ',', '.') : '-';
    
    $tot_b_text = $period_data['biaya'] > 0 ? 'Tot: Rp ' . number_format($period_data['biaya'], 0, ',', '.') : '';
    $tot_q_text = $period_data['qty'] > 0 ? 'Tot: ' . number_format($period_data['qty'], 0, ',', '.') . ' pcs' : '';
    $tooltip = ($period_data['biaya'] > 0 || $period_data['omzet'] > 0) ? 'title="Total Biaya: Rp ' . number_format($period_data['biaya'], 0, ',', '.') . ' | Total GMV: Rp ' . number_format($period_data['omzet'], 0, ',', '.') . '"' : '';

    $roas_sts = getRoasStatus($roas, $target_roas);
    $roas_sts_html = $roas_sts['class'] ? "<span class='status-badge {$roas_sts['class']}'>{$roas_sts['label']}</span>" : '<span class="text-muted opacity-40">-</span>';

    $qty_sts = getQtyStatus($qty_per_day, $target_qty);
    $qty_sts_html = $qty_sts['class'] ? "<span class='status-badge {$qty_sts['class']}'>{$qty_sts['label']}</span>" : '<span class="text-muted opacity-40">-</span>';
    
    $roas_highlight = '';
    if ($roas >= 15) {
        $roas_highlight = 'roas-high';
    } elseif ($roas >= 10) {
        $roas_highlight = 'roas-med';
    }

    echo "<td class='{$css_class} {$css_class}-detail col-period-detail text-center val-roas {$roas_highlight}' {$tooltip} data-val='{$roas}'>{$r_text}</td>";
    echo "<td class='{$css_class} {$css_class}-summary col-period-summary text-center' data-val='{$roas_sts['label']}'>{$roas_sts_html}</td>";
    echo "<td class='{$css_class} {$css_class}-detail col-period-detail text-end val-bgt' data-val='{$biaya_per_day}'><div>{$b_text}</div>" . ($tot_b_text ? "<div class='cell-sub'>{$tot_b_text}</div>" : "") . "</td>";
    echo "<td class='{$css_class} {$css_class}-detail col-period-detail text-center val-qty' data-val='{$qty_per_day}'><div>{$q_text}</div>" . ($tot_q_text ? "<div class='cell-sub'>{$tot_q_text}</div>" : "") . "</td>";
    echo "<td class='{$css_class} {$css_class}-detail col-period-detail text-center col-sep' data-val='{$qty_sts['label']}'>{$qty_sts_html}</td>";
}

$days_w1 = $days_meta['w1'] ?? 1;
$days_w2 = $days_meta['w2'] ?? 1;
$days_w3 = $days_meta['w3'] ?? 1;
$days_w4 = $days_meta['w4'] ?? 1;
$days_twin = $days_meta['twin'] ?? 1;
$days_payday = $days_meta['payday'] ?? 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Tracking & Monitoring Ads</title>
    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            
            /* Slate & Dark Themes */
            --slate-950: #020617;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-400: #94a3b8;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50:  #f8fafc;
            
            /* Primary Brand Accent */
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --primary-hover: #4338ca;
            
            /* Clean Group Tints */
            --grp-roas-head: #e0f2fe;
            --grp-roas-text: #0369a1;
            --grp-roas-border: #7dd3fc;
            --grp-roas-bg: #f8fbff;
            
            --grp-qty-head: #dcfce7;
            --grp-qty-text: #15803d;
            --grp-qty-border: #86efac;
            --grp-qty-bg: #f7fdf9;
            
            --grp-plan-head: #f3e8ff;
            --grp-plan-text: #7e22ce;
            --grp-plan-border: #d8b4fe;
            --grp-plan-bg: #fbf8ff;
            
            --grp-twin-head: #fef3c7;
            --grp-twin-text: #b45309;
            --grp-twin-border: #fcd34d;
            --grp-twin-bg: #fffef9;
            
            --grp-payday-head: #d1fae5;
            --grp-payday-text: #047857;
            --grp-payday-border: #6ee7b7;
            --grp-payday-bg: #f6fdf9;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-main);
            background-color: #f1f5f9;
            color: var(--slate-900);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Number column styling */
        .val-roas, .val-bgt, .val-qty, .tabular-nums {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
        }

        /* === TOP NAVIGATION BAR === */
        .top-navbar {
            background: #0f172a;
            color: #ffffff;
            padding: 9px 24px;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .brand-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo-icon {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.35);
        }

        .brand-title {
            font-size: 0.92rem;
            font-weight: 800;
            letter-spacing: 0.3px;
            color: #ffffff;
            line-height: 1.1;
        }

        .brand-subtitle {
            font-size: 0.68rem;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.2px;
        }

        /* Filter Pills */
        .filter-container {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 3px 6px;
            gap: 4px;
        }

        .filter-item {
            display: flex;
            align-items: center;
            position: relative;
        }

        .filter-item select {
            background: transparent;
            color: #f8fafc;
            border: none;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 5px 22px 5px 8px;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            outline: none;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .filter-item select:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }

        .filter-item select option {
            background: #1e293b;
            color: #f8fafc;
            font-size: 0.8rem;
            padding: 8px;
        }

        .filter-arrow {
            position: absolute;
            right: 6px;
            pointer-events: none;
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .filter-sep {
            width: 1px;
            height: 18px;
            background: rgba(255, 255, 255, 0.15);
            margin: 0 2px;
        }

        /* === EXECUTIVE SUMMARY STRIP === */
        .summary-strip {
            background: #ffffff;
            border-bottom: 1px solid var(--slate-200);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            flex-wrap: wrap;
        }

        .kpi-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .kpi-chip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 6px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .kpi-chip:hover {
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
            transform: translateY(-1px);
        }

        .kpi-icon-badge {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }

        .kpi-meta {
            display: flex;
            flex-direction: column;
        }

        .kpi-lbl {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            line-height: 1.1;
        }

        .kpi-val {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: -0.2px;
            color: #0f172a;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        /* === TOOLBAR & SEARCH CONTROLS === */
        .controls-toolbar {
            background: #ffffff;
            border-bottom: 1px solid var(--slate-200);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .search-box-wrap {
            position: relative;
            min-width: 280px;
            max-width: 360px;
            flex: 1;
        }

        .search-box-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .search-input {
            width: 100%;
            padding: 6px 12px 6px 34px;
            font-size: 0.78rem;
            font-weight: 500;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
        }

        .search-input:focus {
            background: #ffffff;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .status-chips-filter {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn-chip {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .filter-btn-chip:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .filter-btn-chip.active {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
        }

        /* Action Buttons */
        .action-btns-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-modern {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-modern-primary {
            background: #4f46e5;
            color: #ffffff;
        }
        .btn-modern-primary:hover {
            background: #4338ca;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        }

        .btn-modern-outline {
            background: #ffffff;
            color: #475569;
            border-color: #cbd5e1;
        }
        .btn-modern-outline:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-modern-danger {
            background: #ffffff;
            color: #e11d48;
            border-color: #fecdd3;
        }
        .btn-modern-danger:hover {
            background: #fff1f2;
            color: #be123c;
            border-color: #fda4af;
        }

        /* === TABLE AREA === */
        .main-content {
            flex: 1;
            padding: 10px 16px 12px 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .table-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--slate-200);
            flex: 1;
            overflow: auto;
            position: relative;
        }

        /* Scrollbars */
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        table {
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1250px;
            width: 100%;
            background: #ffffff;
            font-variant-numeric: tabular-nums;
        }

        /* === PERIOD EXPAND / COLLAPSE ACCORDION === */
        .btn-toggle-period {
            background: transparent;
            border: none;
            padding: 0 2px;
            font-size: 0.78rem;
            cursor: pointer;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
            pointer-events: none; /* Let parent th capture clicks cleanly */
            transition: transform 0.15s ease;
        }
        .th-period-header {
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease, filter 0.15s ease;
        }
        .th-period-header:hover {
            filter: brightness(0.93);
        }
        .th-period-header:hover .btn-toggle-period {
            transform: scale(1.25);
        }
        .th-period-header.is-collapsed .period-date-badge {
            display: none !important;
        }

        /* Collapsed states: hide detail columns, keep summary (ROAS STS) visible */
        .period-w1-collapsed .grp-w1-detail { display: none !important; }
        .period-w1-collapsed .grp-w1-summary { border-right: 2px solid #cbd5e1 !important; }

        .period-w2-collapsed .grp-w2-detail { display: none !important; }
        .period-w2-collapsed .grp-w2-summary { border-right: 2px solid #cbd5e1 !important; }

        .period-w3-collapsed .grp-w3-detail { display: none !important; }
        .period-w3-collapsed .grp-w3-summary { border-right: 2px solid #cbd5e1 !important; }

        .period-w4-collapsed .grp-w4-detail { display: none !important; }
        .period-w4-collapsed .grp-w4-summary { border-right: 2px solid #cbd5e1 !important; }

        .period-twin-collapsed .grp-twin-detail { display: none !important; }
        .period-twin-collapsed .grp-twin-summary { border-right: 2px solid #cbd5e1 !important; }

        .period-payday-collapsed .grp-payday-detail { display: none !important; }
        .period-payday-collapsed .grp-payday-summary { border-right: 2px solid #cbd5e1 !important; }

        th, td {
            padding: 5px 8px;
            vertical-align: middle;
            font-size: 0.72rem;
            line-height: 1.25;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
        }

        tbody tr {
            transition: background 0.12s ease;
        }

        tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        tbody tr:hover td.sticky-col-1,
        tbody tr:hover td.sticky-col-2 {
            background-color: #f1f5f9 !important;
        }

        /* === STICKY HEADERS === */
        thead th {
            position: sticky;
            z-index: 10;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            font-size: 0.68rem;
            letter-spacing: 0.4px;
        }

        /* Row 1: Title Bar */
        .th-title {
            top: 0;
            z-index: 20 !important;
            background: #0f172a !important;
            color: #f8fafc !important;
            font-size: 0.82rem;
            letter-spacing: 1.5px;
            padding: 9px 16px;
            border-bottom: 1px solid #1e293b;
        }

        /* Row 2: Group Headers */
        .th-group-main {
            top: 36px;
            z-index: 12 !important;
            padding: 7px 6px;
            font-size: 0.67rem;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        /* Row 3: Sub Headers */
        .th-sub {
            top: 68px;
            z-index: 11 !important;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 6px 4px;
            background: #f8fafc;
            color: #475569;
            border-bottom: 2px solid #cbd5e1;
        }

        /* === COLUMN GROUP BACKGROUNDS & HEADERS === */
        .grp-metrik-roas { background: var(--grp-roas-bg) !important; }
        .grp-metrik-qty  { background: var(--grp-qty-bg) !important; }
        .grp-plan        { background: var(--grp-plan-bg) !important; }
        
        /* Week 1: Soft Slate Grey (Abu-abu) */
        .grp-w1          { background: #f1f5f9 !important; }
        /* Week 2: Pure Crisp White (Putih Bersih) */
        .grp-w2          { background: #ffffff !important; }
        /* Week 3: Soft Slate Grey (Abu-abu) */
        .grp-w3          { background: #f1f5f9 !important; }
        /* Week 4: Pure Crisp White (Putih Bersih) */
        .grp-w4          { background: #ffffff !important; }
        
        .grp-twin        { background: var(--grp-twin-bg) !important; }
        .grp-payday      { background: var(--grp-payday-bg) !important; }

        /* Group Level 2 Headers */
        .th-grp-roas   { background: var(--grp-roas-head) !important; color: var(--grp-roas-text) !important; border-bottom: 2px solid var(--grp-roas-border) !important; }
        .th-grp-qty    { background: var(--grp-qty-head) !important; color: var(--grp-qty-text) !important; border-bottom: 2px solid var(--grp-qty-border) !important; }
        .th-grp-plan   { background: var(--grp-plan-head) !important; color: var(--grp-plan-text) !important; border-bottom: 2px solid var(--grp-plan-border) !important; }
        
        /* Week 1 & Week 3: GREY Headers */
        .th-grp-w1, .th-grp-w3 {
            background: #e2e8f0 !important;
            color: #1e293b !important;
            border-bottom: 3px solid #94a3b8 !important;
        }
        /* Week 2 & Week 4: PUTIH / WHITE Headers */
        .th-grp-w2, .th-grp-w4 {
            background: #ffffff !important;
            color: #1e293b !important;
            border-bottom: 3px solid #cbd5e1 !important;
        }
        
        .th-grp-twin   { background: var(--grp-twin-head) !important; color: var(--grp-twin-text) !important; border-bottom: 3px solid var(--grp-twin-border) !important; }
        .th-grp-payday { background: var(--grp-payday-head) !important; color: var(--grp-payday-text) !important; border-bottom: 3px solid var(--grp-payday-border) !important; }

        /* Group Level 3 Sub-Headers */
        .th-sub.grp-w1, .th-sub.grp-w3 {
            background: #edf2f7 !important;
            color: #475569 !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        .th-sub.grp-w2, .th-sub.grp-w4 {
            background: #ffffff !important;
            color: #475569 !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        .th-sub.grp-twin {
            background: #fef9c3 !important;
            color: #854d0e !important;
            border-bottom: 2px solid #fde047 !important;
        }
        .th-sub.grp-payday {
            background: #e6fcf5 !important;
            color: #065f46 !important;
            border-bottom: 2px solid #6ee7b7 !important;
        }

        /* Distinct Group Boundary Divider Line (Vertical Separator) */
        .col-sep {
            border-right: 2px solid #cbd5e1 !important;
        }

        /* Row hover preservation: maintaining week contrast when hovering */
        tbody tr:hover .grp-w1, tbody tr:hover .grp-w3 {
            background-color: #e2e8f0 !important;
        }
        tbody tr:hover .grp-w2, tbody tr:hover .grp-w4 {
            background-color: #f8fafc !important;
        }
        tbody tr:hover .grp-twin {
            background-color: #fefce8 !important;
        }
        tbody tr:hover .grp-payday {
            background-color: #ecfdf5 !important;
        }

        /* Date Badges inside Headers */
        .badge-w1, .badge-w3 {
            background: rgba(0, 0, 0, 0.08) !important;
            color: #334155 !important;
        }
        .badge-w2, .badge-w4 {
            background: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }
        .badge-twin {
            background: rgba(180, 83, 9, 0.12) !important;
            color: #b45309 !important;
        }
        .badge-payday {
            background: rgba(4, 120, 87, 0.12) !important;
            color: #047857 !important;
        }

        /* === SORTABLE TABLE HEADERS === */
        th.sortable-th {
            cursor: pointer !important;
            user-select: none !important;
            transition: background 0.15s ease, filter 0.15s ease;
            white-space: nowrap;
        }
        th.sortable-th:hover {
            filter: brightness(0.92);
        }
        th.sortable-th .sort-icon {
            display: inline-block;
            margin-left: 3px;
            font-size: 0.65rem;
            opacity: 0.45;
            vertical-align: middle;
            transition: transform 0.15s ease, opacity 0.15s ease, color 0.15s ease;
        }
        th.sortable-th:hover .sort-icon {
            opacity: 0.9;
        }
        th.sortable-th.sorted-asc .sort-icon,
        th.sortable-th.sorted-desc .sort-icon {
            opacity: 1 !important;
            color: #0284c7 !important;
            font-weight: 700;
        }

        /* === STICKY COLUMNS === */
        .sticky-col-1 {
            position: sticky;
            left: 0;
            z-index: 6;
            width: 44px;
            min-width: 44px;
            max-width: 44px;
            text-align: center;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
        }

        .sticky-col-2 {
            position: sticky;
            left: 44px;
            z-index: 6;
            width: 290px;
            min-width: 290px;
            max-width: 320px;
            background: #ffffff;
            border-right: 2px solid #cbd5e1;
            box-shadow: 4px 0 10px -2px rgba(15, 23, 42, 0.08);
        }

        thead th.sticky-col-1,
        thead th.sticky-col-2 {
            z-index: 25 !important;
            background: #e2e8f0 !important;
            color: #0f172a !important;
        }

        /* Product Title */
        .product-name {
            font-weight: 600;
            font-size: 0.72rem;
            white-space: normal;
            line-height: 1.35;
            color: #1e293b;
            transition: color 0.15s;
        }

        .history-link {
            text-decoration: none;
            display: block;
            padding: 3px 0;
            border-radius: 4px;
        }

        .history-link:hover .product-name {
            color: #4f46e5 !important;
        }

        /* === VALUE STYLING === */
        .val-roas {
            color: #0284c7;
            font-weight: 700;
            font-size: 0.73rem;
            letter-spacing: -0.1px;
        }
        .val-roas.roas-high {
            color: #059669;
            font-weight: 800;
        }
        .val-roas.roas-med {
            color: #0284c7;
            font-weight: 700;
        }

        .val-bgt {
            color: #be123c;
            font-weight: 600;
            font-size: 0.72rem;
        }

        .val-qty {
            color: #059669;
            font-weight: 600;
            font-size: 0.72rem;
        }

        .cell-sub {
            font-size: 0.58rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 1px;
            white-space: nowrap;
            line-height: 1.1;
        }

        /* === EDITABLE CELLS === */
        .editable {
            cursor: pointer;
            color: #4f46e5;
            font-weight: 700;
            font-size: 0.72rem;
            position: relative;
            transition: all 0.15s;
        }
        .editable::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 20%;
            right: 20%;
            height: 1px;
            background: #c7d2fe;
            transition: all 0.15s;
        }
        .editable:hover {
            background-color: #eef2ff !important;
        }
        .editable:hover::after {
            left: 10%;
            right: 10%;
            background: #4f46e5;
        }
        .edit-input {
            width: 100%;
            border: 2px solid #4f46e5;
            border-radius: 6px;
            padding: 2px 4px;
            outline: none;
            font-weight: 700;
            font-size: 0.72rem;
            color: #4f46e5;
            text-align: center;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
        }

        /* Bulk Edit Icon */
        .bulk-edit {
            opacity: 0.45;
            transition: all 0.2s;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .bulk-edit:hover {
            opacity: 1;
            transform: scale(1.2);
            color: #4f46e5 !important;
        }

        /* === MODERN STATUS BADGES === */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.59rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 9999px;
            letter-spacing: 0.3px;
            white-space: nowrap;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .status-badge::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
        }

        .status-excellent {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status-excellent::before {
            background: #10b981;
        }

        .status-good {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .status-good::before {
            background: #3b82f6;
        }

        .status-bad {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .status-bad::before {
            background: #ef4444;
        }

        .date-badge {
            display: inline-block;
            font-size: 0.58rem;
            font-weight: 600;
            text-transform: none;
            margin-left: 4px;
            padding: 1px 5px;
            background: rgba(0,0,0,0.06);
            border-radius: 4px;
            color: inherit;
        }

        /* Fullscreen View Mode */
        .is-fullscreen {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 99999 !important;
            background: #ffffff !important;
            padding: 8px !important;
        }
    </style>
</head>
<body>

<!-- 1. TOP NAVBAR -->
<div class="top-navbar">
    <div class="brand-box">
        <a href="index.php" class="text-decoration-none text-white-50 me-1" title="Kembali ke Dashboard Ringkasan">
            <i class="bi bi-arrow-left-circle fs-5"></i>
        </a>
        <div class="brand-logo-icon">
            <i class="bi bi-bar-chart-line-fill"></i>
        </div>
        <div>
            <div class="brand-title">ADS INTELLIGENCE MONITORING</div>
            <div class="brand-subtitle">Weekly & Promo Tracking • Enterprise System</div>
        </div>
    </div>
    
    <form method="GET" action="monitoring_polo.php" class="filter-container" id="filterForm">
        <div class="filter-item">
            <select name="year" onchange="document.getElementById('filterForm').submit()">
                <?php foreach ($years_list as $y): ?>
                <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <i class="bi bi-chevron-down filter-arrow"></i>
        </div>
        <div class="filter-sep"></div>
        <div class="filter-item">
            <select name="month" onchange="document.getElementById('filterForm').submit()">
                <?php foreach ($months_id as $m_val => $m_name): ?>
                <option value="<?= $m_val ?>" <?= $filter_month == $m_val ? 'selected' : '' ?>><?= strtoupper($m_name) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="bi bi-chevron-down filter-arrow"></i>
        </div>
        <div class="filter-sep"></div>
        <div class="filter-item">
            <select name="brand" onchange="document.getElementById('filterForm').submit()">
                <option value="Semua">SEMUA BRAND</option>
                <?php foreach ($all_brands as $brand): ?>
                <option value="<?= $brand ?>" <?= $filter_brand === $brand ? 'selected' : '' ?>><?= strtoupper($brand) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="bi bi-chevron-down filter-arrow"></i>
        </div>
        <div class="filter-sep"></div>
        <div class="filter-item">
            <select name="cat" onchange="document.getElementById('filterForm').submit()">
                <option value="Semua">SEMUA KATEGORI</option>
                <?php foreach ($display_categories as $cat): ?>
                <option value="<?= $cat ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= strtoupper($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="bi bi-chevron-down filter-arrow"></i>
        </div>
    </form>
</div>

<!-- 2. EXECUTIVE SUMMARY STATS STRIP -->
<div class="summary-strip">
    <div class="kpi-group">
        <!-- Total Spend -->
        <div class="kpi-chip">
            <div class="kpi-icon-badge" style="background: #fee2e2; color: #dc2626;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="kpi-meta">
                <span class="kpi-lbl">Total Biaya Bulan Ini</span>
                <span class="kpi-val text-danger">Rp <?= number_format($kpi_total_spend, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Total GMV -->
        <div class="kpi-chip">
            <div class="kpi-icon-badge" style="background: #dcfce7; color: #16a34a;">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="kpi-meta">
                <span class="kpi-lbl">Total Omzet (GMV)</span>
                <span class="kpi-val text-success">Rp <?= number_format($kpi_total_gmv, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Overall ROAS -->
        <div class="kpi-chip">
            <div class="kpi-icon-badge" style="background: #e0e7ff; color: #4f46e5;">
                <i class="bi bi-rocket-takeoff-fill"></i>
            </div>
            <div class="kpi-meta">
                <span class="kpi-lbl">ROAS Keseluruhan</span>
                <span class="kpi-val" style="color: #4338ca;"><?= number_format($kpi_overall_roas, 2) ?>x</span>
            </div>
        </div>

        <!-- Total Terjual -->
        <div class="kpi-chip">
            <div class="kpi-icon-badge" style="background: #ccfbf1; color: #0d9488;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div class="kpi-meta">
                <span class="kpi-lbl">Total Qty Terjual</span>
                <span class="kpi-val"><?= number_format($kpi_total_qty, 0, ',', '.') ?> <span style="font-size:0.7rem; font-weight:600; color:#64748b;">pcs</span></span>
            </div>
        </div>

        <!-- Total Iklan -->
        <div class="kpi-chip">
            <div class="kpi-icon-badge" style="background: #f1f5f9; color: #475569;">
                <i class="bi bi-collection-fill"></i>
            </div>
            <div class="kpi-meta">
                <span class="kpi-lbl">Iklan Dimonitor</span>
                <span class="kpi-val"><?= $kpi_total_products ?> <span style="font-size:0.7rem; font-weight:600; color:#64748b;">(<?= $kpi_active_count ?> aktif)</span></span>
            </div>
        </div>
    </div>

    <!-- Right Side: Status Distribution & Alert Message -->
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($message)) echo $message; ?>
        <div class="d-none d-xl-flex align-items-center gap-1 bg-light px-2 py-1 rounded-3 border">
            <span class="status-badge status-excellent" title="Growth > 50% dari Target (Actual > 150%)"><?= $kpi_excellent_count ?> Exc</span>
            <span class="status-badge status-good" title="Mencapai Target s/d Growth 50% (100% - 150%)"><?= $kpi_good_count ?> Good</span>
            <span class="status-badge status-bad" title="Di bawah Target (Actual < 100%)"><?= $kpi_bad_count ?> Bad</span>
        </div>
    </div>
</div>

<!-- 3. CONTROLS TOOLBAR (SEARCH, STATUS FILTERS, ACTIONS) -->
<div class="controls-toolbar">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <!-- Live Search -->
        <div class="search-box-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="quickSearch" class="search-input" placeholder="Cari nama iklan atau kode produk...">
        </div>

        <!-- Status Filter Chips -->
        <div class="status-chips-filter">
            <button class="filter-btn-chip active" data-filter="all">Semua (<?= $kpi_total_products ?>)</button>
            <button class="filter-btn-chip" data-filter="active">Hanya Aktif (<?= $kpi_active_count ?>)</button>
            <button class="filter-btn-chip" data-filter="Excellent"><span class="status-badge status-excellent p-0" style="background:transparent; border:none;"></span> Excellent (<?= $kpi_excellent_count ?>)</button>
            <button class="filter-btn-chip" data-filter="Good"><span class="status-badge status-good p-0" style="background:transparent; border:none;"></span> Good (<?= $kpi_good_count ?>)</button>
            <button class="filter-btn-chip" data-filter="Bad"><span class="status-badge status-bad p-0" style="background:transparent; border:none;"></span> Bad (<?= $kpi_bad_count ?>)</button>
        </div>
        <span id="filteredCount" class="text-muted" style="font-size: 0.72rem; font-weight: 600;"></span>
    </div>

    <!-- Action Buttons -->
    <div class="action-btns-group">
        <button class="btn-modern btn-modern-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-arrow-up-fill"></i> Upload Slot
        </button>
        
        <button class="btn-modern btn-modern-danger" data-bs-toggle="modal" data-bs-target="#clearModal">
            <i class="bi bi-trash3-fill"></i> Kosongkan Slot
        </button>

        <button class="btn-modern btn-modern-outline" id="btnExportCsv" title="Unduh data tabel dalam format CSV">
            <i class="bi bi-file-earmark-spreadsheet-fill text-success"></i> Ekspor CSV
        </button>

        <button class="btn-modern btn-modern-outline" id="btnToggleAllPeriods" title="Buka / Tutup Semua Detail Periode">
            <i class="bi bi-layout-three-columns text-primary"></i> <span id="lblToggleAll">Buka Semua Week</span>
        </button>

        <button class="btn-modern btn-modern-outline" id="btnFullscreen" title="Perbesar Tampilan">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>
    </div>
</div>

<!-- 4. MASTER DATA TABLE -->
<div class="main-content" id="mainContentArea">
    <div class="table-container">
        <table id="dataTable" class="period-w1-collapsed period-w2-collapsed period-w3-collapsed period-w4-collapsed period-twin-collapsed period-payday-collapsed">
            <thead>
                <!-- Level 1: Main Title -->
                <tr>
                    <th colspan="40" class="th-title">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <i class="bi bi-table me-2 text-info"></i>
                                <?= $title_text ?>
                                <?php if($filter_brand !== 'Semua') echo " &bull; BRAND: " . strtoupper($filter_brand); ?>
                                <?php if($filter_category !== 'Semua') echo " &bull; KATEGORI: " . strtoupper($filter_category); ?>
                            </div>
                            <div class="fw-normal text-white-50" style="font-size: 0.72rem;">
                                Menampilkan <?= count($products) ?> Iklan Terdaftar
                            </div>
                        </div>
                    </th>
                </tr>

                <!-- Level 2: Group Headers -->
                <tr>
                    <th rowspan="2" class="sticky-col-1 th-group-main sortable-th" data-col="0" title="Klik untuk mengurutkan No">NO <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th rowspan="2" class="sticky-col-2 th-group-main text-start ps-3 sortable-th" data-col="1" title="Klik untuk mengurutkan Nama Iklan">NAMA IKLAN <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    
                    <th colspan="3" class="th-group-main th-grp-roas col-sep"><i class="bi bi-graph-up me-1"></i>METRIK ROAS</th>
                    <th colspan="3" class="th-group-main th-grp-qty col-sep"><i class="bi bi-box-seam me-1"></i>METRIK QTY</th>
                    <th colspan="2" class="th-group-main th-grp-plan col-sep"><i class="bi bi-wallet2 me-1"></i>PLAN BUDGETING</th>
                    
                    <th colspan="1" class="th-group-main th-grp-w1 col-sep th-period-header is-collapsed" data-period="w1" title="Klik untuk buka detail Week 1">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <span>WEEK 1</span>
                            <button type="button" class="btn-toggle-period" tabindex="-1"><i class="bi bi-plus-circle text-primary"></i></button>
                        </div>
                        <span class="date-badge badge-w1 period-date-badge"><?= htmlspecialchars($lbl_w1) ?></span>
                    </th>
                    <th colspan="1" class="th-group-main th-grp-w2 col-sep th-period-header is-collapsed" data-period="w2" title="Klik untuk buka detail Week 2">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <span>WEEK 2</span>
                            <button type="button" class="btn-toggle-period" tabindex="-1"><i class="bi bi-plus-circle text-primary"></i></button>
                        </div>
                        <span class="date-badge badge-w2 period-date-badge"><?= htmlspecialchars($lbl_w2) ?></span>
                    </th>
                    <th colspan="1" class="th-group-main th-grp-w3 col-sep th-period-header is-collapsed" data-period="w3" title="Klik untuk buka detail Week 3">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <span>WEEK 3</span>
                            <button type="button" class="btn-toggle-period" tabindex="-1"><i class="bi bi-plus-circle text-primary"></i></button>
                        </div>
                        <span class="date-badge badge-w3 period-date-badge"><?= htmlspecialchars($lbl_w3) ?></span>
                    </th>
                    <th colspan="1" class="th-group-main th-grp-w4 col-sep th-period-header is-collapsed" data-period="w4" title="Klik untuk buka detail Week 4">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <span>WEEK 4</span>
                            <button type="button" class="btn-toggle-period" tabindex="-1"><i class="bi bi-plus-circle text-primary"></i></button>
                        </div>
                        <span class="date-badge badge-w4 period-date-badge"><?= htmlspecialchars($lbl_w4) ?></span>
                    </th>
                    <th colspan="1" class="th-group-main th-grp-twin col-sep th-period-header is-collapsed" data-period="twin" title="Klik untuk buka detail Twin Date">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-calendar2-heart-fill me-1"></i><span>TWIN</span>
                            <button type="button" class="btn-toggle-period" tabindex="-1"><i class="bi bi-plus-circle text-primary"></i></button>
                        </div>
                        <span class="date-badge badge-twin period-date-badge"><?= htmlspecialchars($lbl_twin) ?></span>
                    </th>
                    <th colspan="1" class="th-group-main th-grp-payday col-sep th-period-header is-collapsed" data-period="payday" title="Klik untuk buka detail Payday">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-cash-coin me-1"></i><span>PAYDAY</span>
                            <button type="button" class="btn-toggle-period" tabindex="-1"><i class="bi bi-plus-circle text-primary"></i></button>
                        </div>
                        <span class="date-badge badge-payday period-date-badge"><?= htmlspecialchars($lbl_payday) ?></span>
                    </th>
                </tr>

                <!-- Level 3: Sub Headers -->
                <tr>
                    <th class="th-sub grp-metrik-roas sortable-th" data-col="2" title="Klik untuk mengurutkan Target ROAS">
                        TARGET <i class="bi bi-pencil-square ms-1 bulk-edit" data-field="target_roas" title="Edit Massal Target ROAS" style="color: #0369a1;"></i> <i class="bi bi-arrow-down-up sort-icon"></i>
                    </th>
                    <th class="th-sub grp-metrik-roas sortable-th" data-col="3" title="Klik untuk mengurutkan Rata-rata ROAS">RATA2 <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-metrik-roas col-sep sortable-th" data-col="4" title="Klik untuk mengurutkan Status ROAS">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-metrik-qty sortable-th" data-col="5" title="Klik untuk mengurutkan Target QTY">
                        TARGET <i class="bi bi-pencil-square ms-1 bulk-edit" data-field="target_qty" title="Edit Massal Target QTY" style="color: #15803d;"></i> <i class="bi bi-arrow-down-up sort-icon"></i>
                    </th>
                    <th class="th-sub grp-metrik-qty sortable-th" data-col="6" title="Klik untuk mengurutkan Rata-rata Actual QTY">RATA2 ACT <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-metrik-qty col-sep sortable-th" data-col="7" title="Klik untuk mengurutkan Status QTY">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-plan sortable-th" data-col="8" title="Klik untuk mengurutkan Plan Budgeting %">PLAN % <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-plan col-sep sortable-th" data-col="9" title="Klik untuk mengurutkan Rata-rata Budget">RATA2 BGT <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    
                    <th class="th-sub grp-w1 grp-w1-detail col-period-detail sortable-th" data-col="10" title="Sort W1 ROAS">ROAS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w1 grp-w1-summary col-period-summary sortable-th" data-col="11" title="Sort Status ROAS Week 1">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w1 grp-w1-detail col-period-detail sortable-th" data-col="12" title="Sort W1 Budget/Hari">BGT/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w1 grp-w1-detail col-period-detail sortable-th" data-col="13" title="Sort W1 Qty/Hari">QTY/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w1 grp-w1-detail col-period-detail col-sep sortable-th" data-col="14" title="Sort Status QTY Week 1">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>

                    <th class="th-sub grp-w2 grp-w2-detail col-period-detail sortable-th" data-col="15" title="Sort W2 ROAS">ROAS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w2 grp-w2-summary col-period-summary sortable-th" data-col="16" title="Sort Status ROAS Week 2">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w2 grp-w2-detail col-period-detail sortable-th" data-col="17" title="Sort W2 Budget/Hari">BGT/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w2 grp-w2-detail col-period-detail sortable-th" data-col="18" title="Sort W2 Qty/Hari">QTY/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w2 grp-w2-detail col-period-detail col-sep sortable-th" data-col="19" title="Sort Status QTY Week 2">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>

                    <th class="th-sub grp-w3 grp-w3-detail col-period-detail sortable-th" data-col="20" title="Sort W3 ROAS">ROAS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w3 grp-w3-summary col-period-summary sortable-th" data-col="21" title="Sort Status ROAS Week 3">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w3 grp-w3-detail col-period-detail sortable-th" data-col="22" title="Sort W3 Budget/Hari">BGT/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w3 grp-w3-detail col-period-detail sortable-th" data-col="23" title="Sort W3 Qty/Hari">QTY/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w3 grp-w3-detail col-period-detail col-sep sortable-th" data-col="24" title="Sort Status QTY Week 3">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>

                    <th class="th-sub grp-w4 grp-w4-detail col-period-detail sortable-th" data-col="25" title="Sort W4 ROAS">ROAS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w4 grp-w4-summary col-period-summary sortable-th" data-col="26" title="Sort Status ROAS Week 4">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w4 grp-w4-detail col-period-detail sortable-th" data-col="27" title="Sort W4 Budget/Hari">BGT/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w4 grp-w4-detail col-period-detail sortable-th" data-col="28" title="Sort W4 Qty/Hari">QTY/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-w4 grp-w4-detail col-period-detail col-sep sortable-th" data-col="29" title="Sort Status QTY Week 4">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>

                    <th class="th-sub grp-twin grp-twin-detail col-period-detail sortable-th" data-col="30" title="Sort Twin Date ROAS">ROAS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-twin grp-twin-summary col-period-summary sortable-th" data-col="31" title="Sort Status ROAS Twin Date">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-twin grp-twin-detail col-period-detail sortable-th" data-col="32" title="Sort Twin Date Budget/Hari">BGT/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-twin grp-twin-detail col-period-detail sortable-th" data-col="33" title="Sort Twin Date Qty/Hari">QTY/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-twin grp-twin-detail col-period-detail col-sep sortable-th" data-col="34" title="Sort Status QTY Twin Date">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>

                    <th class="th-sub grp-payday grp-payday-detail col-period-detail sortable-th" data-col="35" title="Sort Payday ROAS">ROAS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-payday grp-payday-summary col-period-summary sortable-th" data-col="36" title="Sort Status ROAS Payday">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-payday grp-payday-detail col-period-detail sortable-th" data-col="37" title="Sort Payday Budget/Hari">BGT/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-payday grp-payday-detail col-period-detail sortable-th" data-col="38" title="Sort Payday Qty/Hari">QTY/HR <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="th-sub grp-payday grp-payday-detail col-period-detail col-sep sortable-th" data-col="39" title="Sort Status QTY Payday">STS <i class="bi bi-arrow-down-up sort-icon"></i></th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php 
                $no = 1;
                foreach ($products as $kode => $p): 
                    $has_spend = ($p['_total_month_exp'] > 0 || $p['_total_month_qty'] > 0);
                    $avg_sts = getQtyStatus($p['_calc_rata2_qty'], $p['target_qty']);
                    $avg_sts_html = $avg_sts['class'] ? "<span class='status-badge {$avg_sts['class']}'>{$avg_sts['label']}</span>" : '<span class="text-muted opacity-40">-</span>';
                    $roas_sts = getRoasStatus($p['_calc_rata2_roas'], $p['target_roas'] ?? '');
                    $roas_sts_html = $roas_sts['class'] ? "<span class='status-badge {$roas_sts['class']}'>{$roas_sts['label']}</span>" : '<span class="text-muted opacity-40">-</span>';
                ?>
                <tr data-status="<?= htmlspecialchars($avg_sts['label']) ?>" data-roas-status="<?= htmlspecialchars($roas_sts['label']) ?>" data-active="<?= $has_spend ? '1' : '0' ?>">
                    <!-- Col 1: Index -->
                    <td class="sticky-col-1 text-center fw-bold" style="color: #94a3b8; font-size: 0.72rem;" data-val="<?= $no ?>"><?= $no++ ?></td>
                    
                    <!-- Col 2: Product Name -->
                    <td class="sticky-col-2" data-val="<?= htmlspecialchars($p['nama']) ?>">
                        <a href="#" class="history-link" data-kode="<?= $kode ?>" title="Klik untuk lihat analisa perbandingan antar-bulan">
                            <div class="product-name">
                                <?= htmlspecialchars($p['nama']) ?>
                            </div>
                        </a>
                    </td>
                    
                    <!-- Col 3: Target ROAS (Editable) -->
                    <td class="grp-metrik-roas text-center editable" data-kode="<?= $kode ?>" data-field="target_roas" data-val="<?= (float)$p['target_roas'] ?>" title="Klik untuk mengedit Target ROAS (angka bulat)">
                        <?= (int)round((float)$p['target_roas']) ?>
                    </td>
                    
                    <!-- Col 4: Rata-rata ROAS -->
                    <td class="grp-metrik-roas text-center val-roas" data-val="<?= (float)$p['_calc_rata2_roas'] ?>" <?= (!empty($p['_total_month_exp']) && !empty($p['_total_month_gmv'])) ? 'title="Total Biaya: Rp ' . number_format($p['_total_month_exp'], 0, ',', '.') . ' | Total GMV: Rp ' . number_format($p['_total_month_gmv'], 0, ',', '.') . '"' : '' ?>>
                        <?= number_format($p['_calc_rata2_roas'], 0) ?>
                    </td>
                    
                    <!-- Col 5: Status ROAS -->
                    <td class="grp-metrik-roas text-center col-sep" data-val="<?= htmlspecialchars($roas_sts['label']) ?>" data-roas-status="<?= htmlspecialchars($roas_sts['label']) ?>"><?= $roas_sts_html ?></td>
                    
                    <!-- Col 6: Target Qty (Editable) -->
                    <td class="grp-metrik-qty text-center editable" data-kode="<?= $kode ?>" data-field="target_qty" data-val="<?= (float)$p['target_qty'] ?>" title="Klik untuk mengedit Target QTY (angka bulat)">
                        <?= (int)round((float)$p['target_qty']) ?>
                    </td>
                    
                    <!-- Col 7: Rata-rata Actual Qty -->
                    <td class="grp-metrik-qty text-center val-qty" data-val="<?= (float)$p['_calc_rata2_qty'] ?>">
                        <div><?= number_format($p['_calc_rata2_qty'], 1) ?></div>
                        <?php if (!empty($p['_total_month_qty'])): ?>
                            <div class="cell-sub" title="Total Penjualan Sebulan">Tot: <?= number_format($p['_total_month_qty'], 0, ',', '.') ?> pcs</div>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Col 8: Status Qty -->
                    <td class="grp-metrik-qty text-center col-sep" data-val="<?= htmlspecialchars($avg_sts['label']) ?>"><?= $avg_sts_html ?></td>
                    
                    <!-- Col 9: Budget Plan % (Editable) -->
                    <td class="grp-plan text-end editable" data-kode="<?= $kode ?>" data-field="budget_plan_pct" data-val="<?= (float)$p['_calc_budget_plan'] ?>" style="color: #7e22ce;" title="Klik untuk mengedit persentase Plan Budgeting">
                        <span class="text-muted fw-semibold" style="font-size: 0.62rem;">(+<?= $p['budget_plan_pct'] ?>%)</span><br>
                        <?= number_format($p['_calc_budget_plan'], 0, ',', '.') ?>
                    </td>
                    
                    <!-- Col 10: Rata-rata Budget Harian -->
                    <td class="grp-plan text-end col-sep" data-val="<?= (float)$p['_calc_rata2_budget'] ?>" style="color: #7e22ce; font-weight: 600;">
                        <div><?= number_format($p['_calc_rata2_budget'], 0, ',', '.') ?></div>
                        <?php if (!empty($p['_total_month_exp'])): ?>
                            <div class="cell-sub" title="Total Pengeluaran Sebulan">Tot: Rp <?= number_format($p['_total_month_exp'], 0, ',', '.') ?></div>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Weekly & Promo Columns -->
                    <?php 
                    $tq = (float)$p['target_qty'];
                    $tr = $p['target_roas'] ?? '';
                    rCell($p['_display_history']['w1'], 'grp-w1', $days_w1, $tq, $tr);
                    rCell($p['_display_history']['w2'], 'grp-w2', $days_w2, $tq, $tr);
                    rCell($p['_display_history']['w3'], 'grp-w3', $days_w3, $tq, $tr);
                    rCell($p['_display_history']['w4'], 'grp-w4', $days_w4, $tq, $tr);
                    rCell($p['_display_history']['twin'], 'grp-twin', $days_twin, $tq, $tr);
                    rCell($p['_display_history']['payday'], 'grp-payday', $days_payday, $tq, $tr);
                    ?>
                </tr>
                <?php endforeach; ?>
                
                <?php if (count($products) === 0): ?>
                <tr>
                    <td colspan="40" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        Tidak ada data iklan yang cocok dengan filter yang dipilih.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- 5. MODALS                                  -->
<!-- ========================================== -->

<!-- Modal: History Perbandingan Bulanan -->
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-slate-900 text-white border-0 py-3 px-4" style="background: #0f172a;">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary text-white p-2 rounded-3">
                <i class="bi bi-graph-up-arrow fs-5"></i>
            </div>
            <div>
                <h5 class="modal-title fw-bold fs-6 mb-0">PERBANDINGAN PERFORMA BULANAN</h5>
                <small class="text-white-50">Riwayat performa iklan lintas periode dari database & histori slot</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 bg-light">
        <!-- Product Header Card -->
        <div class="card border-0 shadow-sm p-3 mb-3 rounded-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span class="text-muted" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">NAMA PRODUK IKLAN:</span>
                    <h6 class="text-primary fw-bold mb-0 fs-5 mt-1" id="hm-nama-iklan">Memuat data...</h6>
                </div>
                <div class="d-flex align-items-center gap-2" id="hm-stat-badges"></div>
            </div>
        </div>
        
        <div class="table-responsive bg-white rounded-3 border shadow-sm">
            <table class="table table-hover table-bordered mb-0 text-center" style="font-size: 0.8rem; min-width: 1000px;">
                <thead style="background: #f8fafc;">
                    <tr style="font-size: 0.75rem; color: #475569;">
                        <th class="py-3 text-start ps-3">PERIODE BULAN</th>
                        <th class="py-3 text-end pe-3">TOTAL BIAYA</th>
                        <th class="py-3 text-end pe-3">TOTAL OMZET</th>
                        <th class="py-3 text-primary">ROAS TOTAL</th>
                        <th class="py-3">TOTAL QTY</th>
                        <th class="py-3 text-muted">ROAS W1</th>
                        <th class="py-3 text-muted">ROAS W2</th>
                        <th class="py-3 text-muted">ROAS W3</th>
                        <th class="py-3 text-muted">ROAS W4</th>
                        <th class="py-3 text-warning-emphasis">ROAS TWIN</th>
                        <th class="py-3 text-success-emphasis">ROAS PAYDAY</th>
                    </tr>
                </thead>
                <tbody id="hm-tbody">
                </tbody>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Upload Data Slot -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-slate-900 text-white border-0 py-3 px-4" style="background: #0f172a;">
        <h5 class="modal-title fw-bold fs-6"><i class="bi bi-cloud-arrow-up-fill me-2 text-primary"></i>Upload Data Laporan Slot</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <div class="modal-body p-4">
            <p class="text-muted small mb-3">Pilih periode dan slot waktu yang ingin Anda isi dengan file CSV laporan Shopee Ads.</p>
            
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label fw-bold text-dark small">Tahun</label>
                    <select name="up_year" class="form-select" required>
                        <?php foreach ($years_list as $y): ?>
                        <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold text-dark small">Bulan</label>
                    <select name="up_month" class="form-select" required>
                        <?php foreach ($months_id as $m_val => $m_name): ?>
                        <option value="<?= $m_val ?>" <?= $filter_month == $m_val ? 'selected' : '' ?>><?= $m_name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-dark small">Pilih Slot Periode</label>
                <select name="period" class="form-select" required>
                    <option value="" disabled selected>-- Pilih Slot Waktu --</option>
                    <option value="w1">Week 1 (Hari 1 - 7)</option>
                    <option value="w2">Week 2 (Hari 8 - 14)</option>
                    <option value="w3">Week 3 (Hari 15 - 21)</option>
                    <option value="w4">Week 4 (Hari 22 - Akhir Bulan)</option>
                    <option value="twin">Twin Date Promo</option>
                    <option value="payday">Payday Promo</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-dark small">File Laporan CSV Shopee Ads</label>
                <input class="form-control" type="file" name="file" accept=".csv" required>
                <div class="form-text">Pastikan file bertipe CSV unduhan resmi Shopee Ads.</div>
            </div>
        </div>
        <div class="modal-footer bg-light border-0 py-3 px-4">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold"><i class="bi bi-upload me-1"></i> Mulai Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Kosongkan Slot (Confirmation) -->
<div class="modal fade" id="clearModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-danger text-white border-0 py-3 px-4">
        <h5 class="modal-title fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>Kosongkan Data Slot</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="clear">
        <input type="hidden" name="up_year" value="<?= $filter_year ?>">
        <input type="hidden" name="up_month" value="<?= $filter_month ?>">
        <div class="modal-body p-4">
            <div class="alert alert-warning border-0 d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div class="small">
                    Tindakan ini akan mengosongkan data biaya, omzet, dan qty pada slot yang dipilih untuk periode <b><?= $months_id[$filter_month] ?> <?= $filter_year ?></b>.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-dark small">Pilih Slot Yang Ingin Dikosongkan</label>
                <select name="period" class="form-select text-danger fw-bold" required>
                    <option value="" disabled selected>-- Pilih Slot --</option>
                    <option value="w1">Week 1</option>
                    <option value="w2">Week 2</option>
                    <option value="w3">Week 3</option>
                    <option value="w4">Week 4</option>
                    <option value="twin">Twin Date</option>
                    <option value="payday">Payday</option>
                </select>
            </div>
        </div>
        <div class="modal-footer bg-light border-0 py-3 px-4">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold"><i class="bi bi-trash3-fill me-1"></i> Ya, Kosongkan Data</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Bulk Edit Target -->
<div class="modal fade" id="bulkEditModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-slate-900 text-white border-0 py-3 px-4" style="background: #0f172a;">
        <h5 class="modal-title fw-bold fs-6" id="bulkModalTitle"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Massal</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="card bg-light border-0 p-3 mb-3 rounded-3">
            <div class="text-muted" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">Lingkup Target Edit Massal:</div>
            <div class="d-flex align-items-center gap-2 mt-1">
                <span class="badge bg-dark">Brand: <?= $filter_brand === 'Semua' ? 'SEMUA BRAND' : htmlspecialchars($filter_brand) ?></span>
                <span class="badge bg-secondary">Kategori: <?= $filter_category === 'Semua' ? 'SEMUA KATEGORI' : htmlspecialchars($filter_category) ?></span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold text-dark small" id="bulkInputLabel">Nilai Baru (Angka Bulat)</label>
            <input type="number" step="1" id="bulkInputValue" class="form-control form-control-lg text-center fw-bold text-primary" placeholder="Masukkan angka bulat (contoh: 15, 20, 25)">
            <div class="form-text" id="bulkInputHelp">Target harus berupa angka bulat (bukan desimal) yang akan diterapkan ke seluruh produk pada filter saat ini.</div>
        </div>

        <div class="mb-2">
            <div class="text-muted small fw-bold mb-2">Preset Cepat:</div>
            <div class="d-flex gap-2 flex-wrap" id="bulkPresets">
            </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 py-3 px-4">
        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btnSubmitBulk"><i class="bi bi-check2-circle me-1"></i> Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- 6. SCRIPTS                                 -->
<!-- ========================================== -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {

    const monthNames = {
        '01': 'Januari', '02': 'Februari', '03': 'Maret', '04': 'April',
        '05': 'Mei', '06': 'Juni', '07': 'Juli', '08': 'Agustus',
        '09': 'September', '10': 'Oktober', '11': 'November', '12': 'Desember'
    };

    // 1. LIVE SEARCH & FILTER
    let currentStatusFilter = 'all';
    
    function applyTableFilters() {
        let keyword = $('#quickSearch').val().toLowerCase().trim();
        let visibleCount = 0;
        let totalCount = 0;

        $('#tableBody tr').each(function() {
            let row = $(this);
            // Skip "no data" placeholder row if present
            if (row.find('td').length <= 1) return;

            totalCount++;
            let productName = row.find('.product-name').text().toLowerCase();
            let rowStatus = row.data('status') || '';
            let isActive = row.data('active') == 1;

            let matchesKeyword = keyword === '' || productName.indexOf(keyword) !== -1;
            let matchesStatus = true;

            if (currentStatusFilter === 'active') {
                matchesStatus = isActive;
            } else if (currentStatusFilter !== 'all') {
                matchesStatus = (rowStatus.toLowerCase() === currentStatusFilter.toLowerCase());
            }

            if (matchesKeyword && matchesStatus) {
                row.show();
                visibleCount++;
            } else {
                row.hide();
            }
        });

        if (keyword !== '' || currentStatusFilter !== 'all') {
            $('#filteredCount').text(`(Tersaring ${visibleCount} dari ${totalCount} iklan)`);
        } else {
            $('#filteredCount').text('');
        }
        updateRowNumbers();
    }

    // 1.1 TABLE COLUMN SORTING
    let currentSortCol = null;
    let currentSortDir = 'desc';

    function updateRowNumbers() {
        let no = 1;
        $('#tableBody tr').each(function() {
            let row = $(this);
            if (row.find('td').length <= 1) return;
            if (row.is(':visible')) {
                row.find('td.sticky-col-1').text(no++);
            }
        });
    }

    $(document).on('click', '.sortable-th', function(e) {
        if ($(e.target).closest('.bulk-edit').length) return;

        let colIndex = parseInt($(this).data('col'), 10);
        if (isNaN(colIndex)) return;

        // Toggle direction or switch column
        if (currentSortCol === colIndex) {
            currentSortDir = (currentSortDir === 'asc') ? 'desc' : 'asc';
        } else {
            currentSortCol = colIndex;
            // Column 1 (Nama Iklan) & Column 0 (No) default to 'asc'
            // Metric columns (ROAS, QTY, Budget, etc.) default to 'desc' (highest first)
            currentSortDir = (colIndex === 1 || colIndex === 0) ? 'asc' : 'desc';
        }

        // Update UI icons
        $('.sortable-th').removeClass('sorted-asc sorted-desc');
        $('.sortable-th .sort-icon')
            .removeClass('bi-arrow-up-short bi-arrow-down-short text-primary')
            .addClass('bi-arrow-down-up');

        let activeTh = $(this);
        activeTh.addClass(currentSortDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
        activeTh.find('.sort-icon')
            .removeClass('bi-arrow-down-up')
            .addClass(currentSortDir === 'asc' ? 'bi-arrow-up-short text-primary' : 'bi-arrow-down-short text-primary');

        // Sort rows
        let rows = $('#tableBody tr').filter(function() {
            return $(this).find('td').length > 1;
        }).get();

        rows.sort(function(a, b) {
            let tdA = $(a).children('td').eq(colIndex);
            let tdB = $(b).children('td').eq(colIndex);

            let valA = tdA.attr('data-val');
            let valB = tdB.attr('data-val');

            if (valA === undefined) valA = tdA.text().trim();
            if (valB === undefined) valB = tdB.text().trim();

            let isDashA = (valA === '-' || valA === '');
            let isDashB = (valB === '-' || valB === '');

            let numA = parseFloat(valA);
            let numB = parseFloat(valB);

            let isNumA = (!isNaN(numA) && isFinite(valA)) || isDashA;
            let isNumB = (!isNaN(numB) && isFinite(valB)) || isDashB;

            if (isDashA) numA = 0;
            if (isDashB) numB = 0;

            if (isNumA && isNumB) {
                return currentSortDir === 'asc' ? (numA - numB) : (numB - numA);
            } else if (isNumA && !isNumB) {
                return currentSortDir === 'asc' ? -1 : 1;
            } else if (!isNumA && isNumB) {
                return currentSortDir === 'asc' ? 1 : -1;
            } else {
                let strA = String(valA).toLowerCase();
                let strB = String(valB).toLowerCase();
                let comp = strA.localeCompare(strB, 'id', { numeric: true, sensitivity: 'base' });
                return currentSortDir === 'asc' ? comp : -comp;
            }
        });

        let tbody = $('#tableBody');
        $.each(rows, function(i, row) {
            tbody.append(row);
        });

        updateRowNumbers();
    });

    $('#quickSearch').on('input', applyTableFilters);

    $('.filter-btn-chip').on('click', function() {
        $('.filter-btn-chip').removeClass('active');
        $(this).addClass('active');
        currentStatusFilter = $(this).data('filter');
        applyTableFilters();
    });

    // === 2.1 PERIOD ACCORDION (EXPAND / COLLAPSE) ===
    const allPeriods = ['w1', 'w2', 'w3', 'w4', 'twin', 'payday'];

    function togglePeriod(period, forceState = null) {
        let table = $('#dataTable');
        let th = $(`.th-period-header[data-period="${period}"]`);
        let collapsedClass = `period-${period}-collapsed`;
        let isCurrentlyCollapsed = table.hasClass(collapsedClass);
        
        let shouldCollapse = (forceState !== null) ? forceState : !isCurrentlyCollapsed;
        
        if (shouldCollapse) {
            table.addClass(collapsedClass);
            th.addClass('is-collapsed').attr('colspan', '1');
            th.find('.btn-toggle-period i').removeClass('bi-dash-circle text-danger').addClass('bi-plus-circle text-primary');
            th.attr('title', `Klik untuk buka detail ${period.toUpperCase()}`);
        } else {
            table.removeClass(collapsedClass);
            th.removeClass('is-collapsed').attr('colspan', '5');
            th.find('.btn-toggle-period i').removeClass('bi-plus-circle text-primary').addClass('bi-dash-circle text-danger');
            th.attr('title', `Klik untuk tutup detail ${period.toUpperCase()}`);
        }
        updateToggleAllButtonState();
    }

    function updateToggleAllButtonState() {
        let anyCollapsed = allPeriods.some(p => $('#dataTable').hasClass(`period-${p}-collapsed`));
        if (anyCollapsed) {
            $('#btnToggleAllPeriods').html('<i class="bi bi-layout-three-columns text-primary"></i> <span id="lblToggleAll">Buka Semua Week</span>');
        } else {
            $('#btnToggleAllPeriods').html('<i class="bi bi-layout-sidebar-inset text-danger"></i> <span id="lblToggleAll">Tutup Semua Week</span>');
        }
    }

    $(document).on('click', '.th-period-header', function(e) {
        let period = $(this).data('period');
        if (period) {
            togglePeriod(period);
        }
    });

    $('#btnToggleAllPeriods').on('click', function() {
        let anyCollapsed = allPeriods.some(p => $('#dataTable').hasClass(`period-${p}-collapsed`));
        let targetCollapseState = !anyCollapsed;
        allPeriods.forEach(p => {
            togglePeriod(p, targetCollapseState);
        });
    });

    // 2. FULLSCREEN TOGGLE
    $('#btnFullscreen').on('click', function() {
        let elem = document.getElementById('mainContentArea');
        if (!document.fullscreenElement) {
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            }
            $(this).html('<i class="bi bi-fullscreen-exit"></i>');
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
            $(this).html('<i class="bi bi-arrows-fullscreen"></i>');
        }
    });

    // 3. EXPORT TABLE TO CSV
    $('#btnExportCsv').on('click', function() {
        let csv = [];
        let rows = document.querySelectorAll('#dataTable tr');
        
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll('td, th');
            
            // Skip title row
            if (cols.length === 1) continue;
            
            // If row is hidden by search, don't export
            if (rows[i].style.display === 'none') continue;

            for (let j = 0; j < cols.length; j++) {
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
                row.push('"' + text + '"');
            }
            csv.push(row.join(','));
        }

        let csvFile = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        let downloadLink = document.createElement('a');
        downloadLink.download = 'Shopee_Ads_Monitoring_<?= $filter_year ?>_<?= $filter_month ?>.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    });

    // 4. HISTORY PERBANDINGAN BULANAN MODAL
    $('.history-link').on('click', function(e) {
        e.preventDefault();
        let kode = $(this).data('kode');
        
        $('#hm-nama-iklan').text("Memuat data...");
        $('#hm-stat-badges').html('');
        $('#hm-tbody').html('<tr><td colspan="11" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="text-muted small mt-2">Mengambil riwayat data...</div></td></tr>');
        
        let modal = new bootstrap.Modal(document.getElementById('historyModal'));
        modal.show();
        
        $.post('monitoring_polo.php', {
            action: 'get_history',
            kode: kode
        }, function(res) {
            let data = JSON.parse(res);
            if (data.status === 'success') {
                $('#hm-nama-iklan').text(data.nama);
                
                let tbody = '';
                let history = data.history;
                let years = Object.keys(history).sort().reverse(); 
                
                let hasData = false;
                let grandBgt = 0, grandOmz = 0, grandQty = 0;
                
                years.forEach(year => {
                    let months = Object.keys(history[year]).sort().reverse(); 
                    months.forEach(month => {
                        let h = history[year][month];
                        
                        let sumBgt = 0, sumOmz = 0, sumQty = 0;
                        ['w1','w2','w3','w4','twin','payday'].forEach(w => {
                            sumBgt += h[w].biaya || 0;
                            sumOmz += h[w].omzet || 0;
                            sumQty += h[w].qty || 0;
                        });
                        
                        if (sumBgt > 0 || sumQty > 0) {
                            hasData = true;
                            grandBgt += sumBgt;
                            grandOmz += sumOmz;
                            grandQty += sumQty;

                            let monthlyRoas = sumBgt > 0 ? Math.round(sumOmz / sumBgt) : 0;
                            let formatRp = (num) => 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
                            
                            let getRoas = (wData) => {
                                if (!wData || wData.biaya == 0) return '<span class="text-muted opacity-40">-</span>';
                                let r = Math.round(wData.omzet / wData.biaya);
                                return r > 0 ? `<span class="fw-bold">${r}</span>` : '<span class="text-muted opacity-40">-</span>';
                            };
                            
                            tbody += `<tr>
                                <td class="fw-bold text-start ps-3">${monthNames[month] || month} ${year}</td>
                                <td class="text-danger fw-semibold text-end pe-3">${formatRp(sumBgt)}</td>
                                <td class="text-success fw-semibold text-end pe-3">${formatRp(sumOmz)}</td>
                                <td class="text-primary fw-bold">${monthlyRoas}</td>
                                <td class="fw-bold">${new Intl.NumberFormat('id-ID').format(sumQty)}</td>
                                <td>${getRoas(h.w1)}</td>
                                <td>${getRoas(h.w2)}</td>
                                <td>${getRoas(h.w3)}</td>
                                <td>${getRoas(h.w4)}</td>
                                <td class="text-warning-emphasis">${getRoas(h.twin)}</td>
                                <td class="text-success-emphasis">${getRoas(h.payday)}</td>
                            </tr>`;
                        }
                    });
                });
                
                if (hasData) {
                    let avgRoasAllTime = grandBgt > 0 ? Math.round(grandOmz / grandBgt) : 0;
                    $('#hm-stat-badges').html(`
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">Total Biaya: Rp ${new Intl.NumberFormat('id-ID').format(grandBgt)}</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">Total GMV: Rp ${new Intl.NumberFormat('id-ID').format(grandOmz)}</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">ROAS All-Time: ${avgRoasAllTime}x</span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2">Total Terjual: ${new Intl.NumberFormat('id-ID').format(grandQty)} pcs</span>
                    `);
                } else {
                    tbody = '<tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada record data bulanan yang tercatat.</td></tr>';
                }
                
                $('#hm-tbody').html(tbody);
            }
        });
    });

    // 5. INLINE EDITING UX
    $('.editable').on('click', function() {
        if ($(this).find('input').length > 0) return; 
        
        let rawHtml = $(this).html();
        let val = $(this).text().trim();
        
        if ($(this).data('field') === 'budget_plan_pct') {
            let matches = val.match(/\(\+([0-9]+)%\)/);
            if (matches && matches[1]) val = matches[1];
        }
        
        let input = $('<input type="text" class="edit-input">').val(val);
        $(this).html(input);
        input.focus();
        
        input.on('blur', function() { triggerSave($(this), val, rawHtml); });
        input.on('keydown', function(e) { 
            if (e.which === 13) { 
                $(this).blur(); 
            } else if (e.which === 27) { 
                // ESC cancels
                $(this).parent().html(rawHtml);
            }
        });
    });
    
    function triggerSave(inputEl, oldVal, oldHtml) {
        let newVal = inputEl.val().trim();
        let td = inputEl.parent();
        let kode = td.data('kode');
        let field = td.data('field');
        
        if (field === 'target_roas' || field === 'target_qty') {
            let parsed = parseInt(newVal, 10);
            if (!isNaN(parsed) && parsed > 0) {
                newVal = parsed.toString();
            } else {
                alert('Target harus berupa angka bulat positif (bukan desimal).');
                td.html(oldHtml);
                return;
            }
        }
        
        if (newVal === oldVal || newVal === "") { td.html(oldHtml); return; }
        
        td.html('<span class="spinner-border spinner-border-sm text-primary" role="status"></span>');
        
        $.post('monitoring_polo.php', {
            action: 'update_field',
            kode: kode,
            field: field,
            value: newVal,
            up_year: '<?= $filter_year ?>',
            up_month: '<?= $filter_month ?>'
        }, function(response) {
            window.location.reload();
        }).fail(function() {
            td.html(oldHtml);
            alert("Gagal menyimpan data.");
        });
    }

    // 6. BULK EDIT MODAL
    let currentBulkField = '';
    const currentBrand = <?= json_encode($filter_brand) ?>;
    const currentCat = <?= json_encode($filter_category) ?>;
    
    $('.bulk-edit').on('click', function(e) {
        e.stopPropagation();
        currentBulkField = $(this).data('field');
        let label = currentBulkField === 'target_roas' ? 'Target ROAS' : 'Target QTY';
        
        $('#bulkModalTitle').html(`<i class="bi bi-pencil-square me-2 text-primary"></i> Edit Massal: ${label}`);
        $('#bulkInputLabel').text(`Nilai ${label} Baru (Angka Bulat)`);
        $('#bulkInputValue').val('').focus();
        
        let presetsHtml = '';
        if (currentBulkField === 'target_roas') {
            presetsHtml = `
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-val="10">10</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-val="15">15</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-val="20">20</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-val="25">25</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-val="30">30</button>
            `;
        } else {
            presetsHtml = `
                <button type="button" class="btn btn-outline-success btn-sm preset-btn" data-val="15">15</button>
                <button type="button" class="btn btn-outline-success btn-sm preset-btn" data-val="20">20</button>
                <button type="button" class="btn btn-outline-success btn-sm preset-btn" data-val="25">25</button>
                <button type="button" class="btn btn-outline-success btn-sm preset-btn" data-val="30">30</button>
                <button type="button" class="btn btn-outline-success btn-sm preset-btn" data-val="50">50</button>
            `;
        }
        $('#bulkPresets').html(presetsHtml);

        let modal = new bootstrap.Modal(document.getElementById('bulkEditModal'));
        modal.show();
    });

    $(document).on('click', '.preset-btn', function() {
        let val = $(this).data('val');
        $('#bulkInputValue').val(val);
    });

    $('#btnSubmitBulk').on('click', function() {
        let newVal = $('#bulkInputValue').val().trim();
        if (newVal === '') {
            alert('Silakan masukkan nilai target baru.');
            return;
        }

        if (currentBulkField === 'target_roas' || currentBulkField === 'target_qty') {
            let parsed = parseInt(newVal, 10);
            if (isNaN(parsed) || parsed <= 0) {
                alert('Target harus berupa angka bulat positif (bukan desimal).');
                return;
            }
            newVal = parsed.toString();
        }

        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...');

        $.post('monitoring_polo.php', {
            action: 'bulk_update_field',
            field: currentBulkField,
            value: newVal,
            filter_brand: currentBrand,
            filter_category: currentCat,
            up_year: '<?= $filter_year ?>',
            up_month: '<?= $filter_month ?>'
        }, function(res) {
            window.location.reload();
        }).fail(function() {
            alert('Gagal menyimpan data.');
            $('#btnSubmitBulk').prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Simpan Perubahan');
        });
    });

});
</script>
</body>
</html>
