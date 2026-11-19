<?php
/**
 * Centralized Administrative API Router
 * Handles all AJAX operations with strict session authentication and CSRF protection.
 */

require_once __DIR__ . '/auth.php';

// Strict CSRF Validation on all administrative state-changing POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['admin_csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $submittedCsrf)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Security token (CSRF) validation failed. Please refresh the page and try again.']);
        exit;
    }
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

// ─── 1. Excluded Days Management ──────────────────────────────
if ($action === 'get_excluded_days') {
    header('Content-Type: application/json');
    $config = getExcludedDays();
    echo json_encode(['success' => true, 'excludedDays' => $config]);
    exit;
}

if ($action === 'add_excluded_day' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $center = trim((string)($_POST['center'] ?? '474'));
    $date = str_replace('/', '-', trim((string)($_POST['date'] ?? '')));

    if (!in_array($center, ['474', '476', 'all'], true)) {
        echo json_encode(['error' => 'Invalid exam center code. Must be 474, 476, or all.']);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['error' => 'Invalid date format. Expected YYYY-MM-DD.']);
        exit;
    }

    $config = getExcludedDays();
    if (!isset($config[$center]) || !is_array($config[$center])) {
        $config[$center] = [];
    }

    if (!in_array($date, $config[$center], true)) {
        $config[$center][] = $date;
        sort($config[$center]);
    }

    if (!saveExcludedDays($config)) {
        echo json_encode(['error' => 'Failed to save to excluded_days.json. Check file permissions.']);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'message'      => "Date {$date} successfully added to exclusions for center {$center}.",
        'excludedDays' => $config
    ]);
    exit;
}

if ($action === 'remove_excluded_day' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $center = trim((string)($_POST['center'] ?? '474'));
    $date = str_replace('/', '-', trim((string)($_POST['date'] ?? '')));

    if (!in_array($center, ['474', '476', 'all'], true)) {
        echo json_encode(['error' => 'Invalid exam center code.']);
        exit;
    }

    $config = getExcludedDays();
    if (isset($config[$center]) && is_array($config[$center])) {
        $config[$center] = array_values(array_filter($config[$center], fn($d) => $d !== $date));
    }

    if (!saveExcludedDays($config)) {
        echo json_encode(['error' => 'Failed to update excluded_days.json. Check file permissions.']);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'message'      => "Date {$date} removed from exclusions.",
        'excludedDays' => $config
    ]);
    exit;
}

// ─── System Settings Management ──────────────────────────────
if ($action === 'get_system_settings') {
    header('Content-Type: application/json');
    $settings = getSystemSettings();
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

if ($action === 'toggle_personal_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $current = getSystemSettings();
    $newVal = !empty($current['show_personal_details']) ? false : true;

    if (isset($_POST['enabled'])) {
        $val = $_POST['enabled'];
        $newVal = ($val === '1' || $val === 'true' || $val === true || $val === 1);
    }

    $saved = saveSystemSettings(['show_personal_details' => $newVal]);
    if (!$saved) {
        echo json_encode(['error' => 'Failed to save settings. Please check file permissions on system_settings.json.']);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'enabled'  => $newVal,
        'settings' => getSystemSettings(),
        'message'  => $newVal 
            ? 'Examinee Personal Details section is now VISIBLE on the result page.' 
            : 'Examinee Personal Details section is now HIDDEN on the result page.'
    ]);
    exit;
}

// ─── 2. Tables & Examinee Data Management ─────────────────────
if ($action === 'stats') {
    header('Content-Type: application/json');
    echo json_encode(['tables' => getMonthTables($pdo)]);
    exit;
}

if ($action === 'check_table') {
    header('Content-Type: application/json');
    $tbl = $_GET['table'] ?? '';
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tbl)) {
        echo json_encode(['error' => 'Invalid table name']);
        exit;
    }
    $chk = $pdo->query("SHOW TABLES LIKE '$tbl'");
    $exists = $chk->rowCount() > 0;
    $count = 0;
    if ($exists) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
    }
    echo json_encode(['exists' => $exists, 'count' => $count]);
    exit;
}

if ($action === 'init_table' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $tableName = $_POST['table_name'] ?? '';
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tableName)) {
        echo json_encode(['error' => 'Invalid table name: ' . $tableName]);
        exit;
    }

    $chk = $pdo->query("SHOW TABLES LIKE '$tableName'");
    $created = false;
    if ($chk->rowCount() === 0) {
        createMonthTable($pdo, $tableName);
        $created = true;
    }
    echo json_encode(['success' => true, 'table' => $tableName, 'created' => $created]);
    exit;
}

if ($action === 'import_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $tableName = $_POST['table_name'] ?? '';
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tableName)) {
        echo json_encode(['error' => 'Invalid table name']);
        exit;
    }

    $rowsJson = $_POST['rows'] ?? '[]';
    $rows = json_decode($rowsJson, true);
    if (!is_array($rows)) {
        echo json_encode(['error' => 'Invalid batch data']);
        exit;
    }

    $successCount = 0;
    $errorCount = 0;

    $stmt = $pdo->prepare("
        INSERT INTO `$tableName` (examinee_number, name, dob, email, phone, status)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        name = VALUES(name), dob = VALUES(dob), email = VALUES(email),
        phone = VALUES(phone), status = VALUES(status)
    ");

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            if (is_array($row) && count($row) >= 6) {
                try {
                    $examineeNum = trim((string)$row[0]);
                    $name        = trim((string)$row[1]);
                    $dob         = !empty(trim((string)$row[2])) ? trim((string)$row[2]) : null;
                    if ($dob !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                        $parsedTime = strtotime($dob);
                        $dob = $parsedTime ? date('Y-m-d', $parsedTime) : null;
                    }
                    $email       = !empty(trim((string)$row[3])) ? trim((string)$row[3]) : null;
                    $phone       = !empty(trim((string)$row[4])) ? trim((string)$row[4]) : null;
                    $statusRaw   = trim((string)($row[5] ?? ''));
                    $statusLower = strtolower($statusRaw);
                    if (stripos($statusRaw, 'pass') === 0 || $statusLower === 'p') {
                        $status = 'Pass';
                    } elseif (stripos($statusRaw, 'fail') === 0 || $statusLower === 'f') {
                        $status = 'Fail';
                    } else {
                        $status = 'Pending';
                    }

                    $stmt->execute([
                        $examineeNum, $name, $dob,
                        $email, $phone, $status
                    ]);
                    $successCount++;
                } catch (PDOException $e) {
                    $errorCount++;
                    error_log("[" . date('Y-m-d H:i:s') . "] Batch row error (" . ($row[0] ?? '') . "): " . $e->getMessage());
                }
            } else {
                $errorCount++;
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Batch transaction failed: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'successCount' => $successCount,
        'errorCount'   => $errorCount
    ]);
    exit;
}

if ($action === 'delete_table' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $tbl = $_POST['table_name'] ?? '';
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tbl)) {
        echo json_encode(['error' => 'Invalid table name']);
        exit;
    }
    $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
    echo json_encode(['success' => true, 'message' => "Table $tbl deleted."]);
    exit;
}

if ($action === 'get_table_examinees') {
    header('Content-Type: application/json');
    $tbl = trim((string)($_GET['table_name'] ?? $_POST['table_name'] ?? ''));
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tbl)) {
        echo json_encode(['error' => 'Invalid table name format']);
        exit;
    }

    $chk = $pdo->query("SHOW TABLES LIKE '$tbl'");
    if ($chk->rowCount() === 0) {
        echo json_encode(['error' => "Table $tbl does not exist."]);
        exit;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? 'all'));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(500, max(10, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    // Build query conditions
    $where = [];
    $params = [];

    if ($status !== '' && $status !== 'all') {
        $where[] = "status = ?";
        $params[] = $status;
    }

    if ($search !== '') {
        $where[] = "(examinee_number LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

    // Count filtered records
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` $whereSql");
    $countStmt->execute($params);
    $filteredCount = (int)$countStmt->fetchColumn();

    // Fetch batch statistics (total, pass, fail, pending)
    $statsStmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN LOWER(status) LIKE 'pass%' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN LOWER(status) LIKE 'fail%' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN LOWER(status) NOT LIKE 'pass%' AND LOWER(status) NOT LIKE 'fail%' THEN 1 ELSE 0 END) as pending
        FROM `$tbl`");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'passed' => 0, 'failed' => 0, 'pending' => 0];

    // Fetch records
    $querySql = "SELECT id, examinee_number, name, dob, email, phone, status, created_at 
                 FROM `$tbl` $whereSql 
                 ORDER BY id ASC 
                 LIMIT $limit OFFSET $offset";
    $queryStmt = $pdo->prepare($querySql);
    $queryStmt->execute($params);
    $examinees = $queryStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'       => true,
        'table'         => $tbl,
        'examinees'     => $examinees,
        'filteredCount' => $filteredCount,
        'total'         => (int)($stats['total'] ?? 0),
        'passed'        => (int)($stats['passed'] ?? 0),
        'failed'        => (int)($stats['failed'] ?? 0),
        'pending'       => (int)($stats['pending'] ?? 0),
        'page'          => $page,
        'limit'         => $limit,
        'totalPages'    => ceil($filteredCount / $limit)
    ]);
    exit;
}

if ($action === 'update_examinee' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $tbl = trim((string)($_POST['table_name'] ?? ''));
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tbl)) {
        echo json_encode(['error' => 'Invalid table name format']);
        exit;
    }

    $id             = (int)($_POST['id'] ?? 0);
    $examineeNumber = trim((string)($_POST['examinee_number'] ?? ''));
    $name           = trim((string)($_POST['name'] ?? ''));
    $email          = trim((string)($_POST['email'] ?? ''));
    $phone          = trim((string)($_POST['phone'] ?? ''));
    $dob            = trim((string)($_POST['dob'] ?? ''));
    $statusRaw      = trim((string)($_POST['status'] ?? 'Pending'));

    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid examinee ID.']);
        exit;
    }

    if (empty($examineeNumber)) {
        echo json_encode(['error' => 'Examinee number is required.']);
        exit;
    }

    if (empty($name)) {
        echo json_encode(['error' => 'Candidate name is required.']);
        exit;
    }

    // Normalize status strictly to Pass, Fail, or Pending
    $statusLower = strtolower($statusRaw);
    if (strpos($statusLower, 'pass') === 0 || $statusLower === 'p') {
        $status = 'Pass';
    } elseif (strpos($statusLower, 'fail') === 0 || $statusLower === 'f') {
        $status = 'Fail';
    } else {
        $status = 'Pending';
    }

    // Format DOB if provided
    $formattedDob = null;
    if (!empty($dob)) {
        $time = strtotime($dob);
        if ($time) {
            $formattedDob = date('Y-m-d', $time);
        }
    }

    // Check duplicate examinee_number in the same table
    $dupStmt = $pdo->prepare("SELECT id FROM `$tbl` WHERE examinee_number = ? AND id != ? LIMIT 1");
    $dupStmt->execute([$examineeNumber, $id]);
    if ($dupStmt->fetch()) {
        echo json_encode(['error' => "Examinee number \"$examineeNumber\" is already assigned to another candidate in this table."]);
        exit;
    }

    try {
        $updateStmt = $pdo->prepare("UPDATE `$tbl` SET 
            examinee_number = ?, 
            name = ?, 
            email = ?, 
            phone = ?, 
            dob = ?, 
            status = ? 
            WHERE id = ?");
        $updateStmt->execute([
            $examineeNumber,
            $name,
            !empty($email) ? $email : null,
            !empty($phone) ? $phone : null,
            $formattedDob,
            $status,
            $id
        ]);

        // Fetch updated record
        $getStmt = $pdo->prepare("SELECT id, examinee_number, name, dob, email, phone, status, created_at FROM `$tbl` WHERE id = ?");
        $getStmt->execute([$id]);
        $updatedRecord = $getStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => "Examinee #{$examineeNumber} updated successfully.",
            'data'    => $updatedRecord
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to update examinee: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_examinee' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $tbl = trim((string)($_POST['table_name'] ?? ''));
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tbl)) {
        echo json_encode(['error' => 'Invalid table name format']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid examinee ID.']);
        exit;
    }

    try {
        $delStmt = $pdo->prepare("DELETE FROM `$tbl` WHERE id = ?");
        $delStmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Examinee record deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to delete examinee: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'export_table_csv') {
    $tbl = trim((string)($_GET['table_name'] ?? ''));
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tbl)) {
        http_response_code(400);
        die('Invalid table name format');
    }

    $chk = $pdo->query("SHOW TABLES LIKE '$tbl'");
    if ($chk->rowCount() === 0) {
        http_response_code(404);
        die("Table $tbl does not exist.");
    }

    $filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
    $whereSql = "";
    $label = "All";

    if ($filter === 'pass') {
        $whereSql = "WHERE LOWER(status) LIKE 'pass%'";
        $label = "Pass";
    } elseif ($filter === 'fail') {
        $whereSql = "WHERE LOWER(status) LIKE 'fail%'";
        $label = "Fail";
    } else {
        $label = "All";
    }

    // Clean filename
    $filename = "{$tbl}_{$label}_Examinees_" . date('Ymd_His') . ".csv";

    // Set download headers
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Microsoft Excel / Google Sheets compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Column Headers
    fputcsv($output, [
        'SL',
        'Examinee Number',
        'Candidate Name',
        'Email Address',
        'Phone Number',
        'Date of Birth',
        'Result Status',
        'Batch Table'
    ], ',', '"', '\\');

    $stmt = $pdo->query("SELECT examinee_number, name, email, phone, dob, status FROM `$tbl` $whereSql ORDER BY id ASC");

    $sl = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rawStatus = trim($row['status'] ?? 'Pending');
        $statusFormatted = 'Pending';
        if (stripos($rawStatus, 'pass') === 0) {
            $statusFormatted = 'Pass';
        } elseif (stripos($rawStatus, 'fail') === 0) {
            $statusFormatted = 'Fail';
        }

        fputcsv($output, [
            $sl++,
            $row['examinee_number'] ?? '',
            $row['name'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['dob'] ?? '',
            $statusFormatted,
            $tbl
        ], ',', '"', '\\');
    }

    fclose($output);
    exit;
}

// ─── 3. PBT Scraper Cron Job Helpers & Handlers ───────────────
function getAdminCronConfigFile() {
    return dirname(__DIR__) . '/result/cron-config.json';
}
function getAdminCronStatusFile() {
    return dirname(__DIR__) . '/result/cron-status.json';
}
function loadAdminCronConfig() {
    $file = getAdminCronConfigFile();
    $default = [
        'enabled'                  => false,
        'target_month'             => '10',
        'target_year'              => date('y'),
        'center_code'              => '474',
        'auto_disable_on_complete' => true,
        'last_run'                 => null,
        'last_status'              => 'DISABLED',
        'updated_at'               => date('Y-m-d H:i:s')
    ];
    if (file_exists($file)) {
        $data = json_decode(@file_get_contents($file), true);
        if (is_array($data)) return array_merge($default, $data);
    }
    return $default;
}
function saveAdminCronConfigFile($data) {
    $file = getAdminCronConfigFile();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

if ($action === 'get_cron_config') {
    header('Content-Type: application/json');
    $config = loadAdminCronConfig();
    $statusData = [];
    $statusFile = getAdminCronStatusFile();
    if (file_exists($statusFile)) {
        $statusData = json_decode(@file_get_contents($statusFile), true) ?: [];
    }
    echo json_encode(['success' => true, 'config' => $config, 'statuses' => $statusData]);
    exit;
}

if ($action === 'toggle_cron' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $config = loadAdminCronConfig();
    
    if (isset($_POST['enabled'])) {
        $raw = strtolower(trim((string)$_POST['enabled']));
        $newState = ($raw === '1' || $raw === 'true');
    } else {
        $newState = empty($config['enabled']);
    }

    $config['enabled'] = $newState;
    $config['last_status'] = $config['enabled'] ? 'ACTIVE' : 'DISABLED';
    $config['updated_at'] = date('Y-m-d H:i:s');

    $res = saveAdminCronConfigFile($config);
    if ($res === false) {
        echo json_encode([
            'success' => false,
            'error'   => 'Permission error: Server cannot write to result/cron-config.json. Please ensure result directory has write permissions (chmod 755 or 777).'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'enabled' => $config['enabled'], 'config' => $config]);
    exit;
}

if ($action === 'save_cron_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $config = loadAdminCronConfig();

    $month = preg_replace('/[^0-9]/', '', $_POST['target_month'] ?? '10');
    if (strlen($month) === 1) $month = '0' . $month;
    if ((int)$month < 1 || (int)$month > 12) $month = '10';

    $year = preg_replace('/[^0-9]/', '', $_POST['target_year'] ?? date('y'));
    if (strlen($year) === 4) $year = substr($year, 2, 2);
    if (empty($year)) $year = date('y');

    $center = trim($_POST['center_code'] ?? '474');
    if (!in_array($center, ['474', '476', 'all'])) $center = '474';

    $config['target_month'] = $month;
    $config['target_year'] = $year;
    $config['center_code'] = $center;
    $config['auto_disable_on_complete'] = true;
    $config['updated_at'] = date('Y-m-d H:i:s');

    $res = saveAdminCronConfigFile($config);
    if ($res === false) {
        echo json_encode([
            'success' => false,
            'error'   => 'Permission error: Server cannot write to result/cron-config.json.'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Scraping configuration saved.', 'config' => $config]);
    exit;
}

if ($action === 'reset_cron_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $config = loadAdminCronConfig();
    $statusFile = getAdminCronStatusFile();
    $allStatuses = [];
    if (file_exists($statusFile)) {
        $allStatuses = json_decode(@file_get_contents($statusFile), true) ?: [];
    }
    $jobKey = "{$config['target_year']}_{$config['target_month']}_{$config['center_code']}";
    unset($allStatuses[$jobKey]);
    @file_put_contents($statusFile, json_encode($allStatuses, JSON_PRETTY_PRINT));

    $config['last_status'] = $config['enabled'] ? 'ACTIVE' : 'DISABLED';
    $config['updated_at'] = date('Y-m-d H:i:s');
    saveAdminCronConfigFile($config);

    echo json_encode(['success' => true, 'message' => "Job lock for {$jobKey} reset successfully."]);
    exit;
}

if ($action === 'run_cron_now' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    @set_time_limit(180);

    $config = loadAdminCronConfig();
    $targetMonth  = $_POST['target_month'] ?? $config['target_month'] ?? '10';
    $targetYear   = $_POST['target_year']  ?? $config['target_year']  ?? date('y');
    $targetCenter = $_POST['center_code']  ?? $config['center_code']  ?? '474';

    $cronScript = dirname(__DIR__) . '/result/cron-job.php';
    $outputText = '';
    $ranViaCli = false;

    // 1. Try CLI if exec is enabled and binary is accessible
    if (function_exists('exec')) {
        $phpBin = PHP_BINARY;
        if (!preg_match('/fpm/i', $phpBin) && is_executable($phpBin)) {
            $cmd = escapeshellarg($phpBin) . " " . escapeshellarg($cronScript) . " force=1 month=" . escapeshellarg($targetMonth) . " center=" . escapeshellarg($targetCenter) . " year=" . escapeshellarg($targetYear);
            $output = [];
            $ret = 0;
            exec($cmd . " 2>&1", $output, $ret);
            if (!empty($output)) {
                $outputText = implode("\n", $output);
                $ranViaCli = true;
            }
        }
    }

    // 2. Fallback to direct in-process execution if CLI is not accessible
    if (!$ranViaCli && file_exists($cronScript)) {
        $_GET['force']  = '1';
        $_GET['format'] = 'json';
        $_GET['month']  = $targetMonth;
        $_GET['center'] = $targetCenter;
        $_GET['year']   = $targetYear;

        if (!defined('ADMIN_INTERNAL_EXECUTION')) define('ADMIN_INTERNAL_EXECUTION', true);

        ob_start();
        include $cronScript;
        $raw = ob_get_clean();
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            echo json_encode($parsed);
            exit;
        }
        $outputText = $raw;
    }

    $updatedConfig = loadAdminCronConfig();
    $statusFile = getAdminCronStatusFile();
    $statusData = [];
    if (file_exists($statusFile)) {
        $statusData = json_decode(@file_get_contents($statusFile), true) ?: [];
    }

    echo json_encode([
        'success'  => true,
        'output'   => $outputText ?: 'Scraping executed.',
        'config'   => $updatedConfig,
        'statuses' => $statusData
    ]);
    exit;
}

// ─── 4. CBT Scraper Cron Job Helpers & Handlers ───────────────
function getAdminCbtCronConfigFile() {
    return dirname(__DIR__) . '/result/cbt-cron-config.json';
}
function getAdminCbtCronStatusFile() {
    return dirname(__DIR__) . '/result/cbt-cron-status.json';
}
function loadAdminCbtCronConfig() {
    $file = getAdminCbtCronConfigFile();
    $default = [
        'enabled'                  => false,
        'known_old_data'           => 'For tests held through November 9, 2026 (Mon)',
        'center_code'              => 'all',
        'auto_disable_on_complete' => false,
        'email_notifications'      => true,
        'admin_emails'             => 'hiriazul@gmail.com,ulsaioffice@gmail.com',
        'mod_emails'               => 'sherbazahmed787@gmail.com,salekeenmanob01@gmail.com',
        'last_published_cutoff'    => '',
        'last_published_date'      => '',
        'last_run'                 => null,
        'last_status'              => 'DISABLED',
        'last_message'             => 'CBT Scraper initialized.',
        'updated_at'               => date('Y-m-d H:i:s')
    ];
    if (file_exists($file)) {
        $data = json_decode(@file_get_contents($file), true);
        if (is_array($data)) return array_merge($default, $data);
    }
    return $default;
}
function saveAdminCbtCronConfigFile($data) {
    $file = getAdminCbtCronConfigFile();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

if ($action === 'get_cbt_cron_config') {
    header('Content-Type: application/json');
    $config = loadAdminCbtCronConfig();
    $statusData = [];
    $statusFile = getAdminCbtCronStatusFile();
    if (file_exists($statusFile)) {
        $statusData = json_decode(@file_get_contents($statusFile), true) ?: [];
    }
    echo json_encode(['success' => true, 'config' => $config, 'metrics' => $statusData]);
    exit;
}

if ($action === 'toggle_cbt_cron' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $config = loadAdminCbtCronConfig();

    if (isset($_POST['enabled'])) {
        $raw = strtolower(trim((string)$_POST['enabled']));
        $newState = ($raw === '1' || $raw === 'true');
    } else {
        $newState = empty($config['enabled']);
    }

    $config['enabled'] = $newState;
    $config['last_status'] = $config['enabled'] ? 'ACTIVE' : 'DISABLED';
    $config['updated_at'] = date('Y-m-d H:i:s');

    $res = saveAdminCbtCronConfigFile($config);
    if ($res === false) {
        echo json_encode([
            'success' => false,
            'error'   => 'Permission error: Server cannot write to result/cbt-cron-config.json.'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'enabled' => $config['enabled'], 'config' => $config]);
    exit;
}

if ($action === 'save_cbt_cron_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $config = loadAdminCbtCronConfig();

    $center = trim($_POST['center_code'] ?? 'all');
    if (!in_array($center, ['474', '476', 'all'], true)) $center = 'all';

    $knownOldData = trim((string)($_POST['known_old_data'] ?? ''));

    $config['center_code'] = $center;
    if ($knownOldData !== '') {
        $config['known_old_data'] = $knownOldData;
        $config['last_published_cutoff'] = $knownOldData;
    }
    $config['updated_at'] = date('Y-m-d H:i:s');

    $res = saveAdminCbtCronConfigFile($config);
    if ($res === false) {
        echo json_encode([
            'success' => false,
            'error'   => 'Permission error: Server cannot write to result/cbt-cron-config.json.'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'CBT Scraping settings saved.', 'config' => $config]);
    exit;
}

if ($action === 'reset_cbt_cron_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $config = loadAdminCbtCronConfig();
    $config['known_old_data']        = '';
    $config['last_published_cutoff'] = '';
    $config['last_published_date']   = '';
    $config['last_status']           = $config['enabled'] ? 'ACTIVE' : 'DISABLED';
    $config['last_message']          = 'KNOWN_OLD_DATA reset by admin. Next run will re-process latest CBT results.';
    $config['updated_at']            = date('Y-m-d H:i:s');
    saveAdminCbtCronConfigFile($config);

    echo json_encode(['success' => true, 'message' => 'CBT KNOWN_OLD_DATA reset successfully.', 'config' => $config]);
    exit;
}

if ($action === 'run_cbt_cron_now' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(180);

    // Disable all buffering and compression so log chunks flush to browser immediately
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @ob_implicit_flush(1);

    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
    header('Pragma: no-cache');
    header('X-Accel-Buffering: no');

    echo ":" . str_repeat(" ", 1024) . "\n";
    if (ob_get_level() > 0) @ob_flush();
    @flush();

    $config = loadAdminCbtCronConfig();
    $targetCenter = $_POST['center_code'] ?? $config['center_code'] ?? 'all';

    $cronScript = dirname(__DIR__) . '/result/cbt-cron-job.php';
    if (!file_exists($cronScript)) {
        echo "❌ [ERROR] Scraper script file missing: result/cbt-cron-job.php\nPlease ensure this file is deployed to the server.\n";
        exit;
    }

    if (!defined('ADMIN_INTERNAL_EXECUTION')) define('ADMIN_INTERNAL_EXECUTION', true);

    $streamLogger = function($msg) {
        echo $msg . "\n";
        if (ob_get_level() > 0) @ob_flush();
        @flush();
    };

    try {
        require_once $cronScript;

        $result = executeCbtResultScraper([
            'force'  => true,
            'center' => $targetCenter,
            'on_log' => $streamLogger
        ], $pdo);
    } catch (Throwable $e) {
        $errDetails = "Fatal Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
        echo "\n❌ [CRITICAL SCRAPER ERROR]\n" . $errDetails . "\n\nTrace:\n" . $e->getTraceAsString() . "\n";
        exit;
    }

    exit;
}

// ─── 5. User Portal Accounts Management Endpoints ─────────────
if ($action === 'get_users') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    createUsersTableIfNotExists($pdo);
    try {
        $stmt = $pdo->query("SELECT id, name, email, phone, institute, status, created_at FROM `users` ORDER BY id DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    createUsersTableIfNotExists($pdo);

    $name      = trim((string)($_POST['name'] ?? ''));
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $institute = trim((string)($_POST['institute'] ?? ''));
    $password  = trim((string)($_POST['password'] ?? ''));

    if (empty($name)) {
        echo json_encode(['error' => 'User Name is required.']);
        exit;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'A valid Email Address is required.']);
        exit;
    }
    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['error' => 'Password must be at least 6 characters.']);
        exit;
    }

    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(email) = ?");
        $chk->execute([$email]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['error' => 'A user account with this email address already exists.']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO `users` (name, email, phone, institute, password, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $email, $phone, $institute, $hashedPassword]);

        echo json_encode([
            'success' => true,
            'message' => "User \"{$name}\" created successfully.",
            'user_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    createUsersTableIfNotExists($pdo);

    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim((string)($_POST['name'] ?? ''));
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $institute = trim((string)($_POST['institute'] ?? ''));
    $password  = trim((string)($_POST['password'] ?? ''));

    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid user ID.']);
        exit;
    }
    if (empty($name)) {
        echo json_encode(['error' => 'User Name is required.']);
        exit;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'A valid Email Address is required.']);
        exit;
    }

    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(email) = ? AND id != ?");
        $chk->execute([$email, $id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['error' => 'Another user already exists with this email address.']);
            exit;
        }

        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo json_encode(['error' => 'New password must be at least 6 characters.']);
                exit;
            }
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE `users` SET name = ?, email = ?, phone = ?, institute = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $institute, $hashedPassword, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE `users` SET name = ?, email = ?, phone = ?, institute = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $institute, $id]);
        }

        echo json_encode(['success' => true, 'message' => "User account \"{$name}\" updated successfully."]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid user ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM `users` WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'toggle_user_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid user ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT status FROM `users` WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();
        if ($currentStatus === false) {
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        $upd = $pdo->prepare("UPDATE `users` SET status = ? WHERE id = ?");
        $upd->execute([$newStatus, $id]);

        echo json_encode([
            'success' => true,
            'status'  => $newStatus,
            'message' => "User status updated to " . ucfirst($newStatus) . "."
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to toggle status: ' . $e->getMessage()]);
    }
    exit;
}

// Default fallback for unknown action
header('Content-Type: application/json', true, 404);
echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
exit;