<?php
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// Load admin config (DB connection + admin credentials)
require_once __DIR__ . '/config.php';

$message = '';
$messageType = '';

// Handle Login with Brute-Force Rate Limiting & Timing-Safe Check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // 1. Verify CSRF Token
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['admin_csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $submittedCsrf)) {
        $message = "Security token validation failed. Please refresh the page.";
        $messageType = "error";
    } else {
        // 2. Check Lockout (Max 5 attempts, 15-minute cooldown)
        $lockoutUntil = (int)($_SESSION['admin_login_lockout'] ?? 0);
        $attempts     = (int)($_SESSION['admin_login_attempts'] ?? 0);
        $currentTime  = time();

        if ($lockoutUntil > $currentTime) {
            $remainingMins = max(1, (int)ceil(($lockoutUntil - $currentTime) / 60));
            $message = "Too many failed login attempts. Admin login is temporarily locked for {$remainingMins} minute(s).";
            $messageType = "error";
        } else {
            if ($lockoutUntil > 0 && $lockoutUntil <= $currentTime) {
                unset($_SESSION['admin_login_lockout']);
                $_SESSION['admin_login_attempts'] = 0;
                $attempts = 0;
            }

            $userMatch = hash_equals((string)$admin_user, (string)($_POST['username'] ?? ''));
            $inputPass = (string)($_POST['password'] ?? '');
            $passMatch = password_verify($inputPass, $admin_pass) || hash_equals((string)$admin_pass, $inputPass);

            if ($userMatch && $passMatch) {
                // Success: Clear failed attempts and regenerate session ID
                unset($_SESSION['admin_login_attempts']);
                unset($_SESSION['admin_login_lockout']);
                session_regenerate_id(true);

                $_SESSION[$admin_session_name] = true;
                header("Location: ./");
                exit;
            } else {
                $attempts++;
                $_SESSION['admin_login_attempts'] = $attempts;
                if ($attempts >= 5) {
                    $_SESSION['admin_login_lockout'] = $currentTime + 900; // 15 minutes
                    $message = "Too many failed login attempts. Access temporarily locked for 15 minutes.";
                } else {
                    $rem = 5 - $attempts;
                    $message = "Invalid username or password. ({$rem} attempt" . ($rem === 1 ? '' : 's') . " remaining)";
                }
                $messageType = "error";
            }
        }
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ./");
    exit;
}

// Check if logged in
$isLoggedIn = isset($_SESSION[$admin_session_name]) && $_SESSION[$admin_session_name] === true;

// ─── AJAX Endpoints ─────────────────────────────────────────
$isAjax = (isset($_GET['action']) && in_array($_GET['action'], ['stats', 'check_table', 'get_cron_config', 'get_users', 'get_excluded_days'])) 
       || (isset($_POST['action']) && in_array($_POST['action'], ['init_table', 'import_batch', 'delete_table', 'toggle_cron', 'save_cron_config', 'reset_cron_status', 'run_cron_now', 'create_user', 'update_user', 'delete_user', 'toggle_user_status', 'add_excluded_day', 'remove_excluded_day']));

if ($isAjax && !$isLoggedIn) {
    header('Content-Type: application/json', true, 401);
    echo json_encode(['error' => 'Session expired. Please log in again.']);
    exit;
}

// Strict CSRF Validation on all administrative POST actions
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['admin_csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $submittedCsrf)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Security token (CSRF) validation failed. Please refresh the page and try again.']);
        exit;
    }
}

// AJAX: Get Excluded Days
if ($isLoggedIn && isset($_GET['action']) && $_GET['action'] === 'get_excluded_days') {
    header('Content-Type: application/json');
    $config = getExcludedDays();
    echo json_encode(['success' => true, 'excludedDays' => $config]);
    exit;
}

// AJAX: Add Excluded Day
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_excluded_day') {
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

// AJAX: Remove Excluded Day
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_excluded_day') {
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

// AJAX: Dashboard stats
if ($isLoggedIn && isset($_GET['action']) && $_GET['action'] === 'stats') {
    header('Content-Type: application/json');
    echo json_encode(['tables' => getMonthTables($pdo)]);
    exit;
}

// AJAX: Check if table exists
if ($isLoggedIn && isset($_GET['action']) && $_GET['action'] === 'check_table') {
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

// AJAX: Initialize or prepare month table
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'init_table') {
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

// AJAX: Import a batch of rows in real-time
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_batch') {
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
                        // Blank, N/A, pending, -, or unreleased results default to Pending
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

// AJAX: Delete a month table
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_table') {
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

// ─── Cron Job Config Helpers & AJAX Handlers ─────────────────
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

// AJAX: Get Cron Job Config & Status
if ($isLoggedIn && isset($_GET['action']) && $_GET['action'] === 'get_cron_config') {
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

// AJAX: Toggle Cron Job Enable / Disable
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_cron') {
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

// AJAX: Save Cron Job Settings
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_cron_config') {
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

    $autoDisable = isset($_POST['auto_disable_on_complete']) ? (bool)$_POST['auto_disable_on_complete'] : true;

    $config['target_month'] = $month;
    $config['target_year'] = $year;
    $config['center_code'] = $center;
    $config['auto_disable_on_complete'] = $autoDisable;
    $config['updated_at'] = date('Y-m-d H:i:s');

    $res = saveAdminCronConfigFile($config);
    if ($res === false) {
        echo json_encode([
            'success' => false,
            'error'   => 'Permission error: Server cannot write to result/cron-config.json. Please ensure result directory has write permissions (chmod 755 or 777).'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Scraping configuration saved.', 'config' => $config]);
    exit;
}

// AJAX: Reset Cron Lock Status
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_cron_status') {
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

// AJAX: Trigger Test Run Now
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_cron_now') {
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

// ─── User Portal Accounts Management Endpoints ───────────────

// AJAX: Get list of all portal users
if ($isLoggedIn && isset($_GET['action']) && $_GET['action'] === 'get_users') {
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

// AJAX: Create a new portal user
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
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

// AJAX: Update an existing portal user
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
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

// AJAX: Delete a portal user
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
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

// AJAX: Toggle user status (Active / Inactive)
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_user_status') {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Admin Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="../favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png?v=2">
    <link rel="icon" type="image/png" sizes="128x128" href="../favicon-icon.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png?v=2">
    <!-- Production Compiled Tailwind CSS -->
    <link rel="stylesheet" href="../assets/css/style.min.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.min.css') ? filemtime(__DIR__ . '/../assets/css/style.min.css') : '1'; ?>">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800">

<?php if (!$isLoggedIn): ?>
    <!-- ═══ Login Screen ═══ -->
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-slate-100 animate-fade-up">
            <div class="flex items-center justify-center mb-5">
                <img src="../logo.png" alt="NAT-TEST BD Logo" class="w-16 h-16 object-contain drop-shadow">
            </div>
            <h2 class="text-2xl font-bold text-center text-slate-900 mb-1">Admin Access</h2>
            <p class="text-center text-slate-400 text-sm mb-6">Sign in to manage examinee data</p>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg text-sm bg-red-50 text-red-600 border border-red-200">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'] ?? ''); ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white font-medium p-3 rounded-xl hover:bg-slate-800 transition">Log In</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ═══ Admin Dashboard ═══ -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-3">
                    <img src="../logo.png" alt="NAT-TEST BD Logo" class="w-8 h-8 object-contain">
                    <span class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars($admin_title); ?></span>
                </div>
                <a href="?logout=true" class="text-sm font-medium text-red-600 hover:text-red-800 bg-red-50 px-4 py-2 rounded-lg transition">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- ── Stats Cards ── -->
        <div id="stats-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Exam Tables (CBT/PBT)</p>
                <p class="text-3xl font-black text-slate-900" id="stat-months">—</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Records</p>
                <p class="text-3xl font-black text-slate-900" id="stat-records">—</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Overall Pass Rate</p>
                <p class="text-3xl font-black text-emerald-600" id="stat-pass-rate">—</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">CBT Portal Users</p>
                <p class="text-3xl font-black text-cyan-600" id="stat-portal-users">—</p>
            </div>
        </div>

        <!-- ── Result Publish Scraping Cron Job Card ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <!-- Card Header -->
            <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-white">Result Publish Scraping Cron Job</h2>
                            <span id="cron-badge-status" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-slate-700 text-slate-300 border border-slate-600 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                <span>Loading...</span>
                            </span>
                        </div>
                        <p class="text-slate-400 text-xs mt-0.5">Automates scraping official pass examinee IDs from nat-test.jp and updates database status (N5 ➔ N1) once published.</p>
                    </div>
                </div>

                <!-- Toggle Switch in Header -->
                <div class="flex items-center gap-3 bg-white/10 px-4 py-2.5 rounded-xl backdrop-blur-sm border border-white/10 shrink-0">
                    <span class="text-xs font-semibold text-slate-200" id="cron-toggle-label">Cron Disabled</span>
                    <button type="button" id="cron-toggle-btn" onclick="toggleCronJob()" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600" role="switch" aria-checked="false" title="Click to Enable/Disable Cron Job">
                        <span id="cron-toggle-knob" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0" style="transform: translateX(0px); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);"></span>
                    </button>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-6">
                <form id="cron-config-form" onsubmit="saveCronConfig(event)" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        
                        <!-- Scraping Exam Month -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Scraping Exam Month <span class="text-red-500">*</span>
                            </label>
                            <select id="cron-target-month" name="target_month" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition">
                                <option value="01">01 - January</option>
                                <option value="02">02 - February</option>
                                <option value="03">03 - March</option>
                                <option value="04">04 - April</option>
                                <option value="05">05 - May</option>
                                <option value="06">06 - June</option>
                                <option value="07">07 - July</option>
                                <option value="08">08 - August</option>
                                <option value="09">09 - September</option>
                                <option value="10">10 - October</option>
                                <option value="11">11 - November</option>
                                <option value="12">12 - December</option>
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1.5">Exam session month to scrape</p>
                        </div>

                        <!-- Target Exam Year -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Exam Year <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center">
                                <span class="bg-slate-200 text-slate-600 px-3 py-2.5 rounded-l-lg border border-r-0 border-slate-300 text-sm font-bold">20</span>
                                <input type="text" id="cron-target-year" name="target_year" maxlength="2" class="w-full bg-white border border-slate-300 rounded-r-lg p-2.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition font-mono" placeholder="26">
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1.5">2-digit year (e.g. 26 = 2026)</p>
                        </div>

                        <!-- Target Center Code -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Center Code
                            </label>
                            <select id="cron-center-code" name="center_code" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition">
                                <option value="474">474 - Dhaka Center</option>
                                <option value="476">476 - Khulna Center</option>
                                <option value="all">All Centers (474, 476)</option>
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1.5">Dhaka (474), Khulna (476)</p>
                        </div>

                        <!-- Auto-Stop Option -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Auto-Stop Execution
                                </label>
                                <label class="flex items-start gap-2 cursor-pointer mt-1">
                                    <input type="checkbox" id="cron-auto-disable" name="auto_disable_on_complete" value="1" checked class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-xs text-slate-600 font-medium">Auto-disable cron after all levels (N5–N1) are updated</span>
                                </label>
                            </div>
                            <p class="text-[11px] text-emerald-600 font-medium mt-1">✓ Automatically halts on completion</p>
                        </div>

                    </div>

                    <!-- Live Cron Status Banner -->
                    <div id="cron-status-banner" class="p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 border-slate-200 text-slate-700">
                        <div class="flex items-start gap-3">
                            <div id="cron-status-icon" class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm text-slate-800" id="cron-status-title">Cron Job Status</span>
                                    <span id="cron-last-run" class="text-xs text-slate-400 font-mono">Last run: Never</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5" id="cron-status-desc">Cron is currently disabled. Toggle the switch above to activate automated scraping.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" onclick="runScraperNow()" id="btn-cron-run-now" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span id="btn-cron-run-text">Run Now (Test Scrape)</span>
                            </button>
                            <button type="button" onclick="resetCronLock()" id="btn-cron-reset" class="px-3 py-2 bg-slate-200 hover:bg-red-50 hover:text-red-600 text-slate-700 text-xs font-semibold rounded-lg transition hidden">
                                Reset Completed Lock
                            </button>
                        </div>
                    </div>

                    <!-- Footer: Save & Crontab hint -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                        <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-slate-700">cPanel Cron Command:</span>
                            <code class="bg-slate-100 text-slate-800 px-2 py-1 rounded text-[11px] font-mono border border-slate-200 select-all" id="cron-crontab-snippet">
                                /usr/local/bin/php <?php echo dirname(__DIR__); ?>/result/cron-job.php >/dev/null 2>&1
                            </code>
                        </div>
                        <div class="flex items-center gap-3">
                            <span id="cron-save-feedback" class="text-xs font-semibold text-emerald-600 opacity-0 transition-opacity duration-300">✓ Settings saved successfully!</span>
                            <button type="submit" id="btn-save-cron" class="bg-slate-900 hover:bg-slate-800 text-white font-medium px-5 py-2.5 rounded-xl text-sm transition shadow-sm flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span>Save Scraping Settings</span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Live Scraper Output Console (Collapsible/Hidden by default) -->
                <div id="cron-run-output-box" class="hidden mt-5 p-4 rounded-xl bg-slate-950 border border-slate-800 text-cyan-400 font-mono text-xs">
                    <div class="flex justify-between items-center mb-2 pb-2 border-b border-slate-800">
                        <span class="font-bold text-slate-200 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                            Scraper Execution Log Output:
                        </span>
                        <button type="button" onclick="document.getElementById('cron-run-output-box').classList.add('hidden')" class="text-slate-400 hover:text-white text-xs">Close [✕]</button>
                    </div>
                    <pre id="cron-run-output-text" class="overflow-x-auto whitespace-pre-wrap max-h-60 leading-relaxed text-[11px] text-cyan-300 font-mono"></pre>
                </div>
            </div>
        </div>

        <!-- ── Upload Section ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="p-6 border-b border-slate-200">
                <h1 class="text-xl font-bold text-slate-900">Import Examinees</h1>
                <p class="text-slate-400 text-sm mt-1">Upload a CSV or Excel file. The system auto-detects the exam month and test type (CBT / PBT) from examinee numbers.</p>
            </div>

            <div class="p-6 grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- Left: File Upload -->
                <div class="lg:col-span-3 space-y-4">
                    <div id="dropzone" class="border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center transition-colors duration-200 cursor-pointer hover:bg-slate-50 relative">
                        <svg class="mx-auto h-10 w-10 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <span id="file-name-display" class="font-medium text-blue-600">Click or drag CSV / Excel here</span>
                        <p class="text-xs text-slate-400 mt-2">.csv or .xlsx — Max 10 MB</p>
                        <input id="file-upload" type="file" accept=".csv,.xlsx,.xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    </div>
                </div>

                <!-- Right: Examinee Number Decoder -->
                <div class="lg:col-span-2 bg-slate-50 rounded-2xl p-5 border border-slate-100">
                    <h3 class="font-bold text-slate-700 text-sm mb-3">Examinee Number Format</h3>
                    <div class="font-mono text-center mb-4">
                        <div class="flex justify-center gap-1 text-lg font-bold">
                            <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded">26</span>
                            <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded">08</span>
                            <span class="bg-slate-200 text-slate-600 px-2 py-1 rounded">00</span>
                            <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded">474</span>
                            <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded">5</span>
                            <span class="bg-rose-100 text-rose-700 px-2 py-1 rounded">0001</span>
                        </div>
                        <div class="flex justify-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 mt-2">
                            <span class="w-[36px]">Year</span>
                            <span class="w-[36px]">Mon</span>
                            <span class="w-[36px]">Type</span>
                            <span class="w-[44px]">Site</span>
                            <span class="w-[28px]">Lvl</span>
                            <span class="w-[52px]">Roll</span>
                        </div>
                    </div>
                    <div class="text-xs text-slate-500 space-y-1">
                        <p><strong>Type:</strong> <span class="text-blue-600 font-semibold">00 = PBT</span> (Paper), <span class="text-purple-600 font-semibold">01–31 = CBT</span> (Computer)</p>
                        <p><strong>Level:</strong> 5 = N5, 4 = N4, 3 = N3…</p>
                        <p><strong>Table:</strong> Created as <code class="bg-slate-200 px-1 rounded">Aug_26_PBT</code> or <code class="bg-slate-200 px-1 rounded">Aug_26_CBT</code></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Column Mapping Modal (hidden by default) ── -->
        <div id="mapping-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-3xl w-full my-8 overflow-hidden animate-fade-up">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white p-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500/20 border border-blue-400/30 rounded-xl flex items-center justify-center text-blue-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">Select & Match File Columns</h2>
                            <p class="text-xs text-slate-300" id="mapping-file-title">File loaded — please match the required columns below</p>
                        </div>
                    </div>
                    <button type="button" onclick="cancelMapping()" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition" title="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 max-h-[75vh] overflow-y-auto space-y-6">
                    <!-- Instruction notice -->
                    <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-sm text-blue-900 flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div>
                            <p class="font-semibold text-slate-800">Columns can be in any order.</p>
                            <p class="text-xs text-slate-600 mt-0.5">Please map each required database field to the corresponding column from your file. Matching columns have been automatically pre-selected where detected.</p>
                        </div>
                    </div>

                    <!-- Error notification box -->
                    <div id="mapping-error-box" class="hidden p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700 flex items-start gap-3">
                        <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div id="mapping-error-text" class="font-medium text-xs"></div>
                    </div>

                    <!-- Column Mapping Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Examinee Number -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Examinee Number <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-blue-700 font-semibold bg-blue-100 px-2 py-0.5 rounded">14-digit No</span>
                            </label>
                            <select id="map-examinee-number" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-mono truncate" id="sample-examinee-number">Sample: —</p>
                        </div>

                        <!-- Examinee Name -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Examinee Name <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">Full Name</span>
                            </label>
                            <select id="map-name" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 truncate" id="sample-name">Sample: —</p>
                        </div>

                        <!-- Date of Birth -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Date of Birth (DOB) <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">YYYY-MM-DD</span>
                            </label>
                            <select id="map-dob" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-mono truncate" id="sample-dob">Sample: —</p>
                        </div>

                        <!-- Phone Number -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Phone Number <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">Contact / Mobile</span>
                            </label>
                            <select id="map-phone" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-mono truncate" id="sample-phone">Sample: —</p>
                        </div>

                        <!-- Email Address -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Email Address <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">Email</span>
                            </label>
                            <select id="map-email" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 truncate" id="sample-email">Sample: —</p>
                        </div>

                        <!-- Result Status -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Result Status <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-2 py-0.5 rounded">Pass / Fail</span>
                            </label>
                            <select id="map-status" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-semibold truncate" id="sample-status">Sample: —</p>
                        </div>
                    </div>

                    <!-- File Data Preview Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-inner">
                        <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">File Preview (First 3 Rows)</span>
                            <span class="text-[11px] text-slate-500" id="preview-col-count">0 columns</span>
                        </div>
                        <div class="overflow-x-auto max-h-48">
                            <table class="w-full text-xs text-left text-slate-600">
                                <thead class="bg-slate-50 text-slate-700 uppercase font-semibold border-b border-slate-200 sticky top-0" id="mapping-preview-thead">
                                </thead>
                                <tbody id="mapping-preview-tbody" class="divide-y divide-slate-100 bg-white font-mono text-[11px]">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button type="button" onclick="cancelMapping()" class="px-5 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="button" onclick="applyColumnMapping()" class="px-6 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-md shadow-blue-500/20 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Confirm Mapping & Preview
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Preview Section (hidden) ── -->
        <div id="preview-section" class="hidden mb-8 animate-fade-up">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100 flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-slate-800">File Analysis Complete</h2>
                            <span id="preview-type-badge" class="hidden"></span>
                        </div>
                        <p class="text-xs text-slate-500" id="preview-file-info">—</p>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Preview Stats -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
                            <p class="text-2xl font-black text-slate-900" id="preview-total">0</p>
                            <p class="text-xs font-bold text-slate-400 uppercase">Total Rows</p>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-100">
                            <p class="text-2xl font-black text-emerald-600" id="preview-pass">0</p>
                            <p class="text-xs font-bold text-emerald-400 uppercase">Passed</p>
                        </div>
                        <div class="bg-red-50 rounded-xl p-4 text-center border border-red-100">
                            <p class="text-2xl font-black text-red-600" id="preview-fail">0</p>
                            <p class="text-xs font-bold text-red-400 uppercase">Failed</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-100">
                            <p class="text-2xl font-black text-amber-600" id="preview-pending">0</p>
                            <p class="text-xs font-bold text-amber-500 uppercase">Pending</p>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                            <p class="text-2xl font-black text-blue-600" id="preview-rate">0%</p>
                            <p class="text-xs font-bold text-blue-400 uppercase">Pass Rate</p>
                        </div>
                    </div>

                    <!-- Table info -->
                    <div id="preview-table-info" class="mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800 hidden"></div>

                    <!-- Preview Mapped Records Sample -->
                    <div class="mb-5 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-200 flex justify-between items-center text-xs text-slate-700 font-bold uppercase tracking-wider">
                            <span>Mapped Data Preview (First 3 Records)</span>
                            <span class="text-[11px] text-emerald-600 font-semibold font-mono">Ready to import</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-200 text-[11px]">
                                    <tr>
                                        <th class="px-3 py-2 border-r border-slate-200">Examinee No</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Name</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Date of Birth</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Email</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Phone</th>
                                        <th class="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-sample-tbody" class="divide-y divide-slate-100 bg-white font-mono text-[11px]">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex flex-wrap gap-3">
                        <button id="btn-confirm" onclick="confirmImport()" class="flex-1 min-w-[200px] bg-blue-600 text-white font-medium py-3 rounded-xl hover:bg-blue-700 transition shadow-md shadow-blue-500/20 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Confirm Import
                        </button>
                        <button onclick="reopenMappingModal()" class="px-5 py-3 bg-slate-100 text-slate-700 font-medium rounded-xl hover:bg-slate-200 transition flex items-center gap-2 text-sm">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Change Column Mapping
                        </button>
                        <button onclick="cancelImport()" class="px-6 py-3 bg-slate-100 text-slate-600 font-medium rounded-xl hover:bg-slate-200 transition">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Real-time Progress Bar & Stats (hidden) ── -->
        <div id="progress-container" class="hidden mb-8 animate-fade-up">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-md">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div id="progress-spinner" class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                        <span class="font-bold text-slate-800 text-base" id="progress-text">Preparing import…</span>
                    </div>
                    <span class="font-extrabold text-blue-600 text-lg" id="progress-percent">0%</span>
                </div>

                <!-- Animated Progress Bar -->
                <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden mb-4 border border-slate-200/60 p-0.5">
                    <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-200 ease-out" style="width: 0%"></div>
                </div>

                <!-- Live Metrics Footer -->
                <div class="flex flex-wrap items-center justify-between text-xs text-slate-500 gap-2 pt-2 border-t border-slate-100">
                    <div class="flex items-center gap-4">
                        <span>Imported: <strong id="progress-count" class="text-slate-800 font-bold">0</strong> of <strong id="progress-total" class="text-slate-800 font-bold">0</strong></span>
                        <span class="text-slate-300">|</span>
                        <span class="text-emerald-600 font-medium">✓ <strong id="progress-ok-count">0</strong> OK</span>
                        <span class="text-red-500 font-medium">✗ <strong id="progress-err-count">0</strong> Errors</span>
                    </div>
                    <div id="progress-eta" class="text-slate-400 font-medium">Starting…</div>
                </div>
            </div>
        </div>

        <!-- ── Existing Tables ── -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Exam Month Tables</h2>
            <button onclick="loadDashboard()" class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Refresh
            </button>
        </div>
        <div id="tables-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div class="col-span-full text-center py-12 text-slate-400 text-sm">Loading tables…</div>
        </div>

        <!-- ── User Accounts Management Section ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <!-- Card Header -->
            <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-white">CBT Portal User Accounts</h2>
                            <span id="user-count-badge" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-cyan-950 text-cyan-300 border border-cyan-700">0 Users</span>
                        </div>
                        <p class="text-slate-400 text-xs mt-0.5">Manage authorized user accounts for the CBT Seat Availability Checker portal. Users log in with their email and password.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <button type="button" onclick="openAddUserModal()" class="px-3.5 py-2 bg-gradient-to-r from-cyan-500 to-teal-400 hover:from-cyan-400 hover:to-teal-300 text-slate-950 font-bold text-xs rounded-xl shadow transition flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        <span>Add New User</span>
                    </button>
                    <a href="../user/dashboard" target="_blank" class="px-3 py-2 bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-semibold rounded-xl border border-white/15 transition flex items-center gap-1.5" title="Open User Portal Dashboard">
                        <span>Open Portal</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>
            </div>

            <!-- Users Table Body -->
            <div class="p-6">
                <!-- Search & Filters -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 mb-4">
                    <div class="relative w-full sm:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="user-search-input" oninput="filterUsersTable()" placeholder="Search name, email, institute..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <button type="button" onclick="loadUsersList()" class="text-xs text-slate-500 hover:text-slate-800 font-medium flex items-center gap-1 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Refresh Users</span>
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left text-xs border-collapse divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">User</th>
                                <th class="py-3 px-4">Phone Number</th>
                                <th class="py-3 px-4">Institute Name</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Created Date</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body" class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">Loading user accounts...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── CBT Exam Schedule & Excluded Days Section ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-slate-800">CBT Exam Schedule & Excluded Days</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-slate-100 text-slate-600 border border-slate-200">
                                excluded_days.json
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Manage non-exam days and center holidays. Excluded dates are immediately hidden from student registration.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="loadExcludedDays()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl transition cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Center Tabs -->
            <div class="px-6 pt-4 border-b border-slate-100 bg-slate-50/50 flex flex-wrap gap-2">
                <button type="button" id="tab-center-474" onclick="setExcludedCenterTab('474')" class="px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-rose-600 text-rose-700 bg-white shadow-sm">
                    <span>ULSAI (Dhaka - 474)</span>
                    <span id="badge-count-474" class="px-2 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700">0</span>
                </button>
                <button type="button" id="tab-center-476" onclick="setExcludedCenterTab('476')" class="px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-600 hover:text-slate-900">
                    <span>KHULNA (476)</span>
                    <span id="badge-count-476" class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-600">0</span>
                </button>
                <button type="button" id="tab-center-all" onclick="setExcludedCenterTab('all')" class="px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-600 hover:text-slate-900">
                    <span>All Centers / Nationwide (all)</span>
                    <span id="badge-count-all" class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-600">0</span>
                </button>
            </div>

            <!-- Content Area: Calendar & List -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Left: Interactive Visual Calendar Picker -->
                    <div class="lg:col-span-7 space-y-4">
                        <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="changeExcludedMonth(-1)" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-600 transition cursor-pointer" title="Previous Month">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                </button>
                                <span id="calendar-month-year" class="font-bold text-slate-800 text-sm">November 2026</span>
                                <button type="button" onclick="changeExcludedMonth(1)" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-600 transition cursor-pointer" title="Next Month">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>
                            <button type="button" onclick="resetExcludedMonthToToday()" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg border border-rose-200/60 transition cursor-pointer">
                                Today
                            </button>
                        </div>

                        <!-- Days Header -->
                        <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-slate-400 uppercase tracking-wider py-1">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>

                        <!-- Calendar Grid -->
                        <div id="calendar-days-grid" class="grid grid-cols-7 gap-1.5 min-h-[260px]">
                            <!-- Dynamically populated -->
                        </div>

                        <!-- Legend & Quick Guide -->
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between text-xs text-slate-500 gap-2">
                            <div class="flex items-center gap-3 text-[11px]">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded bg-rose-500 border border-rose-600 inline-block"></span>
                                    <span>Center Excluded</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded bg-amber-500 border border-amber-600 inline-block"></span>
                                    <span>Nationwide (all)</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded bg-white border border-slate-300 inline-block"></span>
                                    <span>Available</span>
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400">💡 Click any upcoming day to toggle exclusion</span>
                        </div>

                        <!-- Quick Manual Date Input -->
                        <div class="pt-2 bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/80">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Quick Date Exclude (or click date in calendar above):
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="date" id="input-quick-exclude-date" class="flex-1 p-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none bg-white">
                                <button type="button" onclick="quickAddExcludedDate()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Exclude Date
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Excluded Dates List -->
                    <div class="lg:col-span-5 flex flex-col h-full space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <span>Excluded Dates for:</span>
                                <span id="current-center-label" class="text-rose-600 font-extrabold">ULSAI (Dhaka - 474)</span>
                            </h3>
                            <span id="active-center-total" class="text-[11px] font-semibold text-slate-400">0 dates</span>
                        </div>

                        <div id="excluded-dates-list" class="space-y-2 max-h-[380px] overflow-y-auto pr-1 flex-1">
                            <div class="py-10 text-center text-slate-400 text-xs">Loading excluded dates...</div>
                        </div>

                        <div class="text-[11px] text-slate-400 bg-amber-50/70 border border-amber-200/60 text-amber-800 p-2.5 rounded-xl">
                            <strong>Note:</strong> Past dates and today are automatically blocked from seat reservation. Dates configured here will be hidden from the reservation calendar.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Add / Edit User Modal ── -->
        <div id="user-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-lg w-full my-8 overflow-hidden animate-fade-up">
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white p-5 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </div>
                        <h3 class="font-bold text-base" id="user-modal-title">Add New Portal User</h3>
                    </div>
                    <button type="button" onclick="closeUserModal()" class="text-slate-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form id="user-form" onsubmit="saveUserForm(event)" class="p-6 space-y-4">
                    <input type="hidden" id="user-form-id" value="">

                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            User Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="user-form-name" required placeholder="e.g. Md. Tanvir Rahman"
                            class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Email Address (Used for Login) <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="user-form-email" required placeholder="user@institute.com"
                            class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                    </div>

                    <!-- Phone & Institute in 2 columns -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Phone Number
                            </label>
                            <input type="text" id="user-form-phone" placeholder="+880 1712-345678"
                                class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Institute Name
                            </label>
                            <input type="text" id="user-form-institute" placeholder="e.g. Dhaka Japanese Academy"
                                class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Password <span id="user-form-pw-req" class="text-red-500">*</span>
                            </label>
                            <button type="button" onclick="generateRandomUserPassword()" class="text-[11px] font-semibold text-blue-600 hover:text-blue-800">
                                🎲 Generate Password
                            </button>
                        </div>
                        <div class="relative">
                            <input type="text" id="user-form-password" placeholder="Enter password (min 6 chars)"
                                class="w-full p-2.5 pr-20 text-xs font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                            <button type="button" onclick="copyUserPassword()" class="absolute right-2 top-2 px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-medium transition" title="Copy password">
                                Copy
                            </button>
                        </div>
                        <p id="user-form-pw-hint" class="text-[11px] text-slate-400 mt-1">Min 6 characters. Passwords are securely hashed with bcrypt in the database.</p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <button type="button" onclick="closeUserModal()" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="btn-save-user" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow transition cursor-pointer">
                            Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
    const ADMIN_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_csrf_token'] ?? ''); ?>;

    // Intercept native fetch to automatically attach CSRF token to all admin POST requests
    const _originalAdminFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        if (options && options.method && options.method.toUpperCase() === 'POST') {
            options.headers = options.headers || {};
            if (options.headers instanceof Headers) {
                options.headers.set('X-CSRF-Token', ADMIN_CSRF_TOKEN);
            } else {
                options.headers['X-CSRF-Token'] = ADMIN_CSRF_TOKEN;
            }
        }
        return _originalAdminFetch(url, options);
    };

    // ─── Constants ──────────────────────────────────────────
    const MONTHS_SHORT = {
        '01':'Jan','02':'Feb','03':'Mar','04':'Apr','05':'May','06':'Jun',
        '07':'Jul','08':'Aug','09':'Sep','10':'Oct','11':'Nov','12':'Dec'
    };
    const MONTHS_FULL = {
        '01':'January','02':'February','03':'March','04':'April','05':'May','06':'June',
        '07':'July','08':'August','09':'September','10':'October','11':'November','12':'December'
    };

    // ─── State ──────────────────────────────────────────────
    let currentUploadedFile = null; // { fileName, headers: [], dataRows: [], savedMapping: {} }
    let analysisResult      = null; // { tableName, month, year, total, pass, fail, fileName, rows }

    // ─── DOM Refs ───────────────────────────────────────────
    const dropzone        = document.getElementById('dropzone');
    const fileInput       = document.getElementById('file-upload');
    const fileNameDisplay = document.getElementById('file-name-display');

    // ─── Drag & Drop ────────────────────────────────────────
    ['dragenter','dragover','dragleave','drop'].forEach(e =>
        dropzone.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); }, false));
    ['dragenter','dragover'].forEach(e =>
        dropzone.addEventListener(e, () => dropzone.classList.add('drag-active'), false));
    ['dragleave','drop'].forEach(e =>
        dropzone.addEventListener(e, () => dropzone.classList.remove('drag-active'), false));

    dropzone.addEventListener('drop', e => {
        fileInput.files = e.dataTransfer.files;
        handleFileSelect();
    }, false);

    fileInput.addEventListener('change', handleFileSelect);

    // ─── File Selection Handler ─────────────────────────────
    function handleFileSelect() {
        const file = fileInput.files[0];
        if (!file) return;

        const ext = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'xlsx', 'xls'].includes(ext)) {
            alert('Unsupported file type. Please upload a .csv, .xlsx, or .xls file.');
            fileInput.value = '';
            return;
        }

        fileNameDisplay.innerHTML = `<span class="text-emerald-600 font-bold">${file.name}</span>`;

        const reader = new FileReader();
        reader.onload = e => {
            try {
                let rows = [];
                if (ext === 'csv') {
                    try {
                        const wb = XLSX.read(e.target.result, { type: 'array', cellDates: true, raw: false });
                        const ws = wb.Sheets[wb.SheetNames[0]];
                        rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', blankrows: false });
                    } catch (sheetErr) {
                        const decoder = new TextDecoder('utf-8');
                        const text = decoder.decode(e.target.result);
                        rows = parseCSVToRows(text);
                    }
                } else {
                    const wb = XLSX.read(e.target.result, { type: 'array', cellDates: true, raw: false });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', blankrows: false });
                }

                // Filter out empty rows
                rows = (rows || []).filter(r => Array.isArray(r) && r.some(c => c !== null && c !== undefined && String(c).trim() !== ''));

                if (!rows || rows.length < 2) {
                    alert('The uploaded file is empty or does not contain data rows.');
                    cancelMapping();
                    return;
                }

                // Extract column headers (row 0)
                const headers = rows[0].map((h, i) => {
                    const str = String(h !== null && h !== undefined ? h : '').trim();
                    return str || `Column ${i + 1}`;
                });

                const dataRows = rows.slice(1);

                currentUploadedFile = {
                    fileName: file.name,
                    headers: headers,
                    dataRows: dataRows,
                    savedMapping: {}
                };

                openMappingModal(currentUploadedFile);

            } catch (err) {
                console.error(err);
                alert('Error reading file: ' + (err.message || 'Unknown error'));
                cancelMapping();
            }
        };

        reader.readAsArrayBuffer(file);
    }

    // ─── Simple CSV text parser (handles quotes and commas) ──
    function parseCSVToRows(text) {
        const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
        return lines.map(line => parseCSVLine(line));
    }

    function parseCSVLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (inQuotes) {
                if (ch === '"' && line[i+1] === '"') { current += '"'; i++; }
                else if (ch === '"') { inQuotes = false; }
                else { current += ch; }
            } else {
                if (ch === '"') { inQuotes = true; }
                else if (ch === ',') { result.push(current); current = ''; }
                else { current += ch; }
            }
        }
        result.push(current);
        return result;
    }

    // ─── Escape HTML Helper ─────────────────────────────────
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ─── Excel Date Serial Checker ──────────────────────────
    function isExcelDateSerial(val) {
        if (val === null || val === undefined || val === '') return false;
        const num = Math.floor(Number(val));
        return !isNaN(num) && num >= 1000 && num <= 60000 && String(num).length <= 5;
    }

    // ─── Date Normalization Helper ──────────────────────────
    function normalizeDate(val) {
        if (val === null || val === undefined || val === '') return '';
        if (val instanceof Date) {
            if (!isNaN(val.getTime())) {
                const yr = val.getFullYear();
                const mo = String(val.getMonth() + 1).padStart(2, '0');
                const dy = String(val.getDate()).padStart(2, '0');
                return `${yr}-${mo}-${dy}`;
            }
        }
        // Handle Excel serial date as number OR numeric string (e.g. 38764 or "38764" or "38764.0")
        if (isExcelDateSerial(val)) {
            const num = Number(val);
            const d = new Date(Math.round((num - 25569) * 86400 * 1000));
            if (!isNaN(d.getTime())) {
                const yr = d.getUTCFullYear();
                const mo = String(d.getUTCMonth() + 1).padStart(2, '0');
                const dy = String(d.getUTCDate()).padStart(2, '0');
                return `${yr}-${mo}-${dy}`;
            }
        }
        const str = String(val).trim();
        if (!str) return '';

        // Match YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD
        let m = str.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
        if (m) {
            return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`;
        }

        // Match DD/MM/YYYY or DD-MM-YYYY
        m = str.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
        if (m) {
            return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
        }

        // Try standard Date parse
        const dt = new Date(str);
        if (!isNaN(dt.getTime()) && dt.getFullYear() > 1900 && dt.getFullYear() < 2100) {
            const yr = dt.getFullYear();
            const mo = String(dt.getMonth() + 1).padStart(2, '0');
            const dy = String(dt.getDate()).padStart(2, '0');
            return `${yr}-${mo}-${dy}`;
        }

        return str;
    }

    // ─── Status Normalization Helper ────────────────────────
    function normalizeStatus(val) {
        if (val === undefined || val === null) return 'Pending';
        const s = String(val).trim().toLowerCase();
        if (!s || s === 'n/a' || s === 'na' || s === '-' || s === 'pending' || s === 'waiting' || s === 'null' || s === 'none') {
            return 'Pending';
        }
        if (s.startsWith('pass') || s === 'p') return 'Pass';
        if (s.startsWith('fail') || s === 'f') return 'Fail';
        return 'Pending';
    }

    // ─── Mapping Fields Definition ──────────────────────────
    const MAPPING_FIELDS = [
        {
            key: 'examinee_number',
            name: 'Examinee Number',
            selectId: 'map-examinee-number',
            sampleId: 'sample-examinee-number',
            test: h => (/examinee.*(no|num|id)/i.test(h) || /candidate.*(no|num|id)/i.test(h) || /roll/i.test(h) || /reg(istration)?.*(no|num|id)?/i.test(h) || /^id$/i.test(h) || (/examinee/i.test(h) && !/name/i.test(h)) || /exam.*no/i.test(h))
        },
        {
            key: 'name',
            name: 'Examinee Name',
            selectId: 'map-name',
            sampleId: 'sample-name',
            test: h => ((/name/i.test(h) && !/father|mother|guard|sur/i.test(h)) || (/student|candidate|examinee/i.test(h) && !/no|num|id|roll|reg|date|phone|mail/i.test(h)))
        },
        {
            key: 'dob',
            name: 'Date of Birth (DOB)',
            selectId: 'map-dob',
            sampleId: 'sample-dob',
            test: h => (/dob|birth|bday/i.test(h) || /date.*of.*birth/i.test(h))
        },
        {
            key: 'phone',
            name: 'Phone Number',
            selectId: 'map-phone',
            sampleId: 'sample-phone',
            test: h => (/phone|mobile|contact|cell|telephone|^tel|whats?app|ph[._\s-]?no|mob[._\s-]?no|電話|連絡先|携帯|ফোন|মোবাইল/i.test(h))
        },
        {
            key: 'email',
            name: 'Email Address',
            selectId: 'map-email',
            sampleId: 'sample-email',
            test: h => (/email|mail|e-mail/i.test(h))
        },
        {
            key: 'status',
            name: 'Result Status',
            selectId: 'map-status',
            sampleId: 'sample-status',
            test: h => (/status|result|verdict|grade|pass.*fail|remarks?/i.test(h))
        }
    ];

    function getSampleValueForCol(dataRows, colIdx) {
        if (!dataRows || colIdx === '' || colIdx === null || colIdx === undefined) return null;
        for (let r = 0; r < Math.min(dataRows.length, 10); r++) {
            const val = dataRows[r][colIdx];
            if (val !== undefined && val !== null && String(val).trim() !== '') {
                return String(val).trim();
            }
        }
        return '';
    }

    // ─── Open Column Mapping Modal ──────────────────────────
    function openMappingModal(fileData) {
        document.getElementById('mapping-file-title').textContent =
            `${fileData.fileName}  •  ${fileData.headers.length} columns detected  •  ${fileData.dataRows.length.toLocaleString()} rows`;

        document.getElementById('preview-col-count').textContent = `${fileData.headers.length} columns`;

        // Clear any previous error
        const errBox = document.getElementById('mapping-error-box');
        errBox.classList.add('hidden');
        document.getElementById('mapping-error-text').textContent = '';

        // Track auto-assigned columns so we don't assign the same column to multiple fields
        const assignedCols = new Set();
        const autoSelections = {};

        // If saved mapping exists from previous confirmation, restore it
        if (fileData.savedMapping && Object.keys(fileData.savedMapping).length > 0) {
            Object.assign(autoSelections, fileData.savedMapping);
        } else {
            // Auto-detect matches
            MAPPING_FIELDS.forEach(field => {
                let matchedIdx = null;
                for (let i = 0; i < fileData.headers.length; i++) {
                    if (assignedCols.has(i)) continue;
                    const header = fileData.headers[i];
                    if (field.test(header)) {
                        matchedIdx = i;
                        assignedCols.add(i);
                        break;
                    }
                }
                if (matchedIdx !== null) {
                    autoSelections[field.key] = String(matchedIdx);
                }
            });
        }

        // Populate selects and sample labels
        MAPPING_FIELDS.forEach(field => {
            const selectEl = document.getElementById(field.selectId);
            const sampleEl = document.getElementById(field.sampleId);

            // Populate options
            selectEl.innerHTML = '<option value="">-- Select Column --</option>' +
                fileData.headers.map((h, i) => {
                    let sample = getSampleValueForCol(fileData.dataRows, i);
                    if (field.key === 'dob' || isExcelDateSerial(sample) || /dob|birth|date/i.test(h)) {
                        const d = normalizeDate(sample);
                        if (d) sample = d;
                    }
                    const sampleStr = sample ? ` (${sample.length > 20 ? sample.substring(0, 18) + '…' : sample})` : '';
                    return `<option value="${i}">${escapeHtml(h)}${escapeHtml(sampleStr)}</option>`;
                }).join('');

            // Apply auto selection if found
            if (autoSelections[field.key] !== undefined) {
                selectEl.value = autoSelections[field.key];
            }

            // Update sample text
            const updateSample = () => {
                const val = selectEl.value;
                if (val === '') {
                    sampleEl.textContent = 'Sample: —';
                    sampleEl.className = 'text-[11px] text-slate-400 mt-1.5 truncate';
                } else {
                    let sample = getSampleValueForCol(fileData.dataRows, parseInt(val, 10));
                    if (field.key === 'dob' || isExcelDateSerial(sample)) {
                        const d = normalizeDate(sample);
                        if (d) sample = d;
                    }
                    sampleEl.textContent = 'Sample: ' + (sample || '(empty)');
                    sampleEl.className = 'text-[11px] text-blue-600 font-semibold mt-1.5 truncate';
                }
            };

            selectEl.onchange = updateSample;
            updateSample();
        });

        // Populate Mini Preview Table (First 3 rows)
        const thead = document.getElementById('mapping-preview-thead');
        const tbody = document.getElementById('mapping-preview-tbody');

        thead.innerHTML = '<tr>' +
            fileData.headers.map((h, i) =>
                `<th class="px-3 py-2 border-r border-slate-200 whitespace-nowrap bg-slate-100">#${i + 1}: ${escapeHtml(h)}</th>`
            ).join('') + '</tr>';

        const previewRows = fileData.dataRows.slice(0, 3);
        tbody.innerHTML = previewRows.map((row) => {
            return `<tr class="hover:bg-slate-50 transition-colors">` +
                fileData.headers.map((h, cIdx) => {
                    let cellVal = row[cIdx] !== undefined && row[cIdx] !== null ? row[cIdx] : '';
                    if (/dob|birth|date/i.test(h) || isExcelDateSerial(cellVal)) {
                        const d = normalizeDate(cellVal);
                        if (d) cellVal = d;
                    }
                    const cellStr = String(cellVal).trim();
                    return `<td class="px-3 py-2 border-r border-slate-100 whitespace-nowrap text-slate-700">${escapeHtml(cellStr) || '<span class="text-slate-300 italic">null</span>'}</td>`;
                }).join('') +
            `</tr>`;
        }).join('');

        document.getElementById('mapping-modal').classList.remove('hidden');
    }

    // ─── Cancel Column Mapping ──────────────────────────────
    function cancelMapping() {
        document.getElementById('mapping-modal').classList.add('hidden');
        if (document.getElementById('preview-section').classList.contains('hidden')) {
            currentUploadedFile = null;
            fileInput.value = '';
            fileNameDisplay.innerHTML = '<span class="text-blue-600">Click or drag CSV / Excel here</span>';
        }
    }

    // ─── Re-open Column Mapping Modal ───────────────────────
    function reopenMappingModal() {
        if (!currentUploadedFile) {
            alert('No uploaded file in memory. Please select a file again.');
            return;
        }
        openMappingModal(currentUploadedFile);
    }

    // ─── Apply Column Mapping & Validate ────────────────────
    function applyColumnMapping() {
        const errBox  = document.getElementById('mapping-error-box');
        const errText = document.getElementById('mapping-error-text');
        errBox.classList.add('hidden');

        const currentMapping = {};
        const missingFields = [];
        const usedCols = {};

        MAPPING_FIELDS.forEach(field => {
            const selectEl = document.getElementById(field.selectId);
            const val = selectEl.value;
            if (val === '') {
                missingFields.push(field.name);
            } else {
                currentMapping[field.key] = parseInt(val, 10);
                if (usedCols[val]) {
                    usedCols[val].push(field.name);
                } else {
                    usedCols[val] = [field.name];
                }
            }
        });

        // Validation 1: All 6 required
        if (missingFields.length > 0) {
            errText.textContent = `Please select a matching column for all required fields: ${missingFields.join(', ')}.`;
            errBox.classList.remove('hidden');
            return;
        }

        // Validation 2: No duplicates
        const duplicates = Object.entries(usedCols).filter(([col, fields]) => fields.length > 1);
        if (duplicates.length > 0) {
            const conflictDesc = duplicates.map(([col, fields]) => `Column ${parseInt(col, 10) + 1} is assigned to multiple fields (${fields.join(' & ')})`).join('; ');
            errText.textContent = `Conflict detected: ${conflictDesc}. Each field must map to a distinct column.`;
            errBox.classList.remove('hidden');
            return;
        }

        // Save mapping
        currentUploadedFile.savedMapping = currentMapping;

        // Process data rows
        let total = 0, pass = 0, fail = 0, pending = 0;
        const validRows = [];
        const tableGroups = {}; // Keyed by tableName (e.g. 'Oct_26_CBT', 'Nov_26_CBT', 'Oct_26_PBT')

        const idxNum    = currentMapping['examinee_number'];
        const idxName   = currentMapping['name'];
        const idxDob    = currentMapping['dob'];
        const idxPhone  = currentMapping['phone'];
        const idxEmail  = currentMapping['email'];
        const idxStatus = currentMapping['status'];

        for (const row of currentUploadedFile.dataRows) {
            const rawNum = row[idxNum];
            if (rawNum === undefined || rawNum === null || String(rawNum).trim() === '') continue;

            const cleanNum = String(rawNum).trim().replace(/[^0-9]/g, '');
            if (!cleanNum || cleanNum.length < 14) continue;

            const detectedYear  = cleanNum.substring(0, 2);
            const detectedMonth = cleanNum.substring(2, 4);
            const monthShort    = MONTHS_SHORT[detectedMonth];
            const monthFull     = MONTHS_FULL[detectedMonth];
            if (!monthShort) continue; // skip unrecognized month codes

            // Determine test type: 00 = PBT, any non-00 = CBT
            const typeCode = cleanNum.substring(4, 6);
            const testType = (typeCode === '00') ? 'PBT' : 'CBT';
            const tableName = `${monthShort}_${detectedYear}_${testType}`;

            const cleanName = String(row[idxName] || '').trim();
            const normDob   = normalizeDate(row[idxDob]);
            const cleanMail = String(row[idxEmail] || '').trim();
            const cleanTel  = String(row[idxPhone] || '').trim();
            const normStat  = normalizeStatus(row[idxStatus]);

            total++;
            if (normStat === 'Pass') pass++;
            else if (normStat === 'Fail') fail++;
            else pending++;

            const mappedRow = [cleanNum, cleanName, normDob, cleanMail, cleanTel, normStat];
            validRows.push(mappedRow);

            if (!tableGroups[tableName]) {
                tableGroups[tableName] = {
                    tableName,
                    month: detectedMonth,
                    year: detectedYear,
                    monthShort,
                    monthFull,
                    testType,
                    rows: [],
                    pass: 0,
                    fail: 0,
                    pending: 0
                };
            }
            tableGroups[tableName].rows.push(mappedRow);
            if (normStat === 'Pass') {
                tableGroups[tableName].pass++;
            } else if (normStat === 'Fail') {
                tableGroups[tableName].fail++;
            } else {
                tableGroups[tableName].pending++;
            }
        }

        const groupKeys = Object.keys(tableGroups);
        if (validRows.length === 0 || groupKeys.length === 0) {
            errText.textContent = 'No valid data rows found with 14-digit examinee numbers. Please check your mapping.';
            errBox.classList.remove('hidden');
            return;
        }

        analysisResult = {
            fileName: currentUploadedFile.fileName,
            total,
            pass,
            fail,
            pending,
            tableGroups,
            groupKeys,
            rows: validRows
        };

        // Close modal and show preview
        document.getElementById('mapping-modal').classList.add('hidden');
        showPreview(analysisResult);
    }

    // ─── Show Preview ───────────────────────────────────────
    async function showPreview(analysis) {
        document.getElementById('preview-total').textContent = analysis.total.toLocaleString();
        document.getElementById('preview-pass').textContent  = analysis.pass.toLocaleString();
        document.getElementById('preview-fail').textContent  = analysis.fail.toLocaleString();
        const pendEl = document.getElementById('preview-pending');
        if (pendEl) pendEl.textContent = (analysis.pending || 0).toLocaleString();

        const evaluated = analysis.pass + analysis.fail;
        const rate = evaluated > 0 ? ((analysis.pass / evaluated) * 100).toFixed(1) + '%' : (analysis.pending > 0 ? 'Pending' : '0%');
        document.getElementById('preview-rate').textContent  = rate;

        const badge = document.getElementById('preview-type-badge');
        const infoBox = document.getElementById('preview-table-info');

        if (analysis.groupKeys.length === 1) {
            const grp = analysis.tableGroups[analysis.groupKeys[0]];
            document.getElementById('preview-file-info').textContent =
                `${analysis.fileName}  •  ${grp.monthFull} 20${grp.year}  •  Table: ${grp.tableName}`;

            if (badge) {
                badge.classList.remove('hidden');
                if (grp.testType === 'CBT') {
                    badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-purple-100 text-purple-700 border border-purple-200 flex items-center gap-1.5';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> CBT (Computer Based)';
                } else {
                    badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-100 text-blue-700 border border-blue-200 flex items-center gap-1.5';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> PBT (Paper Based)';
                }
            }

            try {
                const resp = await fetch(`?action=check_table&table=${grp.tableName}`);
                const data = await resp.json();
                if (data.exists) {
                    infoBox.innerHTML = `⚠ Table <strong>${grp.tableName}</strong> (${grp.testType}) already exists with <strong>${data.count.toLocaleString()}</strong> records. Duplicates will be updated.`;
                    infoBox.className = 'mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800';
                } else {
                    infoBox.innerHTML = `✓ Table <strong>${grp.tableName}</strong> (${grp.testType}) will be created automatically.`;
                    infoBox.className = 'mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-800';
                }
                infoBox.classList.remove('hidden');
            } catch (e) { infoBox.classList.add('hidden'); }

        } else {
            // Multi-table auto-split!
            document.getElementById('preview-file-info').textContent =
                `${analysis.fileName}  •  Smart Auto-Split (${analysis.groupKeys.length} Tables Detected)`;

            if (badge) {
                badge.classList.remove('hidden');
                badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-indigo-100 text-indigo-700 border border-indigo-200 flex items-center gap-1.5';
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Multi-Session (${analysis.groupKeys.length} Tables)`;
            }

            try {
                const tableChecks = await Promise.all(analysis.groupKeys.map(async (tblName) => {
                    try {
                        const resp = await fetch(`?action=check_table&table=${tblName}`);
                        const data = await resp.json();
                        return { tableName: tblName, exists: data.exists, count: data.count || 0 };
                    } catch (e) {
                        return { tableName: tblName, exists: false, count: 0 };
                    }
                }));

                const statusMap = {};
                tableChecks.forEach(tc => { statusMap[tc.tableName] = tc; });

                const cardsHtml = analysis.groupKeys.map(tblName => {
                    const grp = analysis.tableGroups[tblName];
                    const stat = statusMap[tblName] || { exists: false, count: 0 };
                    const typeColor = grp.testType === 'CBT'
                        ? 'bg-purple-100 text-purple-700 border-purple-200'
                        : 'bg-blue-100 text-blue-700 border-blue-200';
                    const existPill = stat.exists
                        ? `<span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">Exists (${stat.count.toLocaleString()})</span>`
                        : `<span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">New Table</span>`;

                    const pendingBadge = grp.pending > 0 ? `, <span class="text-amber-600 font-medium">${grp.pending} Pending</span>` : '';

                    return `
                        <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-slate-900">${escapeHtml(grp.tableName)}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border ${typeColor}">${grp.testType}</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">${grp.rows.length.toLocaleString()} examinees &bull; <span class="text-emerald-600 font-medium">${grp.pass} Pass</span>, <span class="text-red-500 font-medium">${grp.fail} Fail</span>${pendingBadge}</p>
                            </div>
                            <div>${existPill}</div>
                        </div>`;
                }).join('');

                infoBox.className = 'mb-5 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-800';
                infoBox.innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 font-bold text-slate-800">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <span>Smart Multi-Table Routing (${analysis.groupKeys.length} Tables Detected)</span>
                        </div>
                        <p class="text-xs text-slate-600">This file contains examinees across multiple exam sessions/months. Each examinee will be imported into their matching database table automatically:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                            ${cardsHtml}
                        </div>
                    </div>`;
                infoBox.classList.remove('hidden');
            } catch (e) { infoBox.classList.add('hidden'); }
        }

        // Render mapped data preview sample (first 3 records)
        const sampleTbody = document.getElementById('preview-sample-tbody');
        if (sampleTbody && analysis.rows) {
            sampleTbody.innerHTML = analysis.rows.slice(0, 3).map(r => {
                let statClass = 'text-amber-600 font-semibold';
                if (r[5] === 'Pass') statClass = 'text-emerald-600 font-bold';
                else if (r[5] === 'Fail') statClass = 'text-red-500 font-bold';

                return `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-3 py-2 border-r border-slate-100 font-bold text-blue-600">${escapeHtml(r[0])}</td>
                    <td class="px-3 py-2 border-r border-slate-100 text-slate-800 font-sans">${escapeHtml(r[1])}</td>
                    <td class="px-3 py-2 border-r border-slate-100 text-slate-700 font-semibold">${escapeHtml(r[2])}</td>
                    <td class="px-3 py-2 border-r border-slate-100 text-slate-600 font-sans">${escapeHtml(r[3]) || '<span class="text-slate-300 italic">—</span>'}</td>
                    <td class="px-3 py-2 border-r border-slate-100 text-slate-700">${escapeHtml(r[4]) || '<span class="text-slate-300 italic">—</span>'}</td>
                    <td class="px-3 py-2 ${statClass}">${escapeHtml(r[5])}</td>
                </tr>
            `}).join('');
        }

        document.getElementById('preview-section').classList.remove('hidden');
    }

    // ─── Confirm & Real-time Batch Import ──────────────────
    async function confirmImport() {
        if (!analysisResult || !analysisResult.groupKeys || analysisResult.groupKeys.length === 0) {
            alert('No valid records to import.');
            return;
        }

        const btn = document.getElementById('btn-confirm');
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');

        // UI Elements
        const progContainer = document.getElementById('progress-container');
        const progBar       = document.getElementById('progress-bar');
        const progText      = document.getElementById('progress-text');
        const progPercent   = document.getElementById('progress-percent');
        const progCount     = document.getElementById('progress-count');
        const progTotal     = document.getElementById('progress-total');
        const progOkCount   = document.getElementById('progress-ok-count');
        const progErrCount  = document.getElementById('progress-err-count');
        const progEta       = document.getElementById('progress-eta');
        const progSpinner   = document.getElementById('progress-spinner');

        // Reset progress UI
        progContainer.classList.remove('hidden');
        progSpinner.classList.remove('hidden');
        progBar.style.width = '0%';
        progBar.className = 'bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-200 ease-out';
        progText.textContent = 'Initializing tables…';
        progPercent.textContent = '0%';
        progTotal.textContent = analysisResult.total.toLocaleString();
        progCount.textContent = '0';
        progOkCount.textContent = '0';
        progErrCount.textContent = '0';
        progEta.textContent = 'Starting…';

        const endpoint = window.location.href;
        const BATCH_SIZE = 150;
        const totalRows = analysisResult.total;
        let processedRows = 0;
        let totalSuccess = 0;
        let totalErrors = 0;
        const startTime = Date.now();

        // Process each table group
        for (const tblName of analysisResult.groupKeys) {
            const group = analysisResult.tableGroups[tblName];

            // 1. Initialize this group's table
            progText.textContent = `Preparing table ${group.tableName}…`;
            try {
                const initFormData = new FormData();
                initFormData.append('action', 'init_table');
                initFormData.append('table_name', group.tableName);

                const initResp = await fetch(endpoint, {
                    method: 'POST',
                    body: initFormData
                });

                if (initResp.status === 401) {
                    alert('Session expired. Please log in again.');
                    window.location.reload();
                    return;
                }

                const initData = await initResp.json();
                if (initData.error) {
                    alert(`Error creating table ${group.tableName}: ` + initData.error);
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    return;
                }
            } catch (err) {
                alert(`Failed to initialize database table ${group.tableName}.`);
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                return;
            }

            // 2. Batch upload rows for this table
            for (let i = 0; i < group.rows.length; i += BATCH_SIZE) {
                const chunk = group.rows.slice(i, i + BATCH_SIZE);
                const batchFormData = new FormData();
                batchFormData.append('action', 'import_batch');
                batchFormData.append('table_name', group.tableName);
                batchFormData.append('rows', JSON.stringify(chunk));

                try {
                    const resp = await fetch(endpoint, {
                        method: 'POST',
                        body: batchFormData
                    });

                    if (resp.status === 401) {
                        alert('Session expired. Please log in again.');
                        window.location.reload();
                        return;
                    }

                    const data = await resp.json();
                    if (data.error) {
                        progText.textContent = `Error importing into ${group.tableName}`;
                        alert(data.error);
                        break;
                    }

                    totalSuccess += (data.successCount || 0);
                    totalErrors  += (data.errorCount || 0);
                    processedRows = Math.min(totalRows, processedRows + chunk.length);

                    // Update UI metrics
                    const pct = Math.round((processedRows / totalRows) * 100);
                    progBar.style.width = pct + '%';
                    progPercent.textContent = pct + '%';
                    progCount.textContent = processedRows.toLocaleString();
                    progOkCount.textContent = totalSuccess.toLocaleString();
                    progErrCount.textContent = totalErrors.toLocaleString();

                    // Calculate ETA
                    const elapsedSec = (Date.now() - startTime) / 1000;
                    const rowsPerSec = processedRows / elapsedSec;
                    const remainingRows = totalRows - processedRows;
                    const etaSec = rowsPerSec > 0 ? Math.ceil(remainingRows / rowsPerSec) : 0;
                    progEta.textContent = `${Math.round(rowsPerSec)} records/sec • ETA: ${etaSec}s`;
                    progText.textContent = `Importing into ${group.tableName}… (${processedRows}/${totalRows})`;

                } catch (batchErr) {
                    console.error('Batch error:', batchErr);
                    totalErrors += chunk.length;
                    processedRows = Math.min(totalRows, processedRows + chunk.length);
                    progErrCount.textContent = totalErrors.toLocaleString();
                }
            }
        }

        // Completion
        progBar.style.width = '100%';
        progPercent.textContent = '100%';
        progBar.className = 'bg-emerald-500 h-2 rounded-full transition-all duration-300';
        progSpinner.classList.add('hidden');

        const tblCount = analysisResult.groupKeys.length;
        progText.innerHTML = `<span class="text-emerald-600 font-bold">Import Completed!</span> <span class="text-slate-500 font-normal">(${totalSuccess.toLocaleString()} records across ${tblCount} table${tblCount > 1 ? 's' : ''})</span>`;
        progEta.textContent = `Completed in ${((Date.now() - startTime) / 1000).toFixed(1)}s`;

        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');

        // Hide preview section and reload stats
        setTimeout(() => {
            document.getElementById('preview-section').classList.add('hidden');
            loadDashboard();
        }, 1200);
    }

    // ─── Cancel Import ──────────────────────────────────────
    function cancelImport() {
        analysisResult = null;
        selectedFile = null;
        csvBlob = null;
        fileInput.value = '';
        fileNameDisplay.innerHTML = '<span class="text-blue-600">Click or drag CSV / Excel here</span>';
        document.getElementById('preview-section').classList.add('hidden');
    }

    // ─── Load Dashboard Stats ───────────────────────────────
    async function loadDashboard() {
        try {
            const resp = await fetch('?action=stats');
            const data = await resp.json();
            const tables = data.tables || [];

            // Aggregate stats
            let totalRecords = 0, totalPass = 0, totalFail = 0, totalPending = 0;
            tables.forEach(t => {
                totalRecords += t.total;
                totalPass    += t.passed;
                totalFail    += t.failed;
                totalPending += (t.pending || 0);
            });

            document.getElementById('stat-months').textContent  = tables.length;
            document.getElementById('stat-records').textContent = totalRecords.toLocaleString();
            const evaluatedTotal = totalPass + totalFail;
            const overallRate = evaluatedTotal > 0 ? ((totalPass / evaluatedTotal) * 100).toFixed(1) + '%' : (totalPending > 0 ? 'Pending' : '—');
            document.getElementById('stat-pass-rate').textContent = overallRate;

            // Render table cards
            renderTableCards(tables);
        } catch (e) {
            console.error('Failed to load dashboard:', e);
        }
    }

    // ─── Render Table Cards ─────────────────────────────────
    function renderTableCards(tables) {
        const grid = document.getElementById('tables-grid');

        if (tables.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full text-center py-16">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <p class="text-slate-400 text-sm">No exam tables yet. Import a CSV to get started.</p>
                </div>`;
            return;
        }

        grid.innerHTML = tables.map(t => {
            const pendingCount = t.pending || 0;
            const evaluated = t.passed + t.failed;
            const rate = evaluated > 0 ? ((t.passed / evaluated) * 100).toFixed(1) : (pendingCount > 0 ? 'Pending' : '0');
            const barWidth = evaluated > 0 ? Math.round((t.passed / evaluated) * 100) : 0;

            let badgeHtml = '';
            if (t.type === 'CBT' || t.name.endsWith('_CBT')) {
                badgeHtml = '<span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase bg-purple-100 text-purple-700 border border-purple-200">CBT</span>';
            } else if (t.type === 'PBT' || t.name.endsWith('_PBT')) {
                badgeHtml = '<span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase bg-blue-100 text-blue-700 border border-blue-200">PBT</span>';
            } else {
                badgeHtml = '<span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">Legacy</span>';
            }

            return `
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow group">
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-lg font-bold text-slate-900">${escapeHtml(t.name)}</h3>
                                ${badgeHtml}
                            </div>
                            <button onclick="deleteTable('${escapeHtml(t.name)}')" class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-red-500 p-1" title="Delete table">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                        <p class="text-2xl font-black text-slate-800">${t.total.toLocaleString()}</p>
                        <p class="text-xs text-slate-400 mb-4">examinees</p>

                        <!-- Pass/Fail bar -->
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden mb-2">
                            <div class="bg-emerald-500 h-2 rounded-full" style="width:${barWidth}%"></div>
                        </div>
                        <div class="flex justify-between items-center text-xs font-medium gap-1 flex-wrap">
                            <span class="text-emerald-600 font-bold">${t.passed.toLocaleString()} Pass</span>
                            ${pendingCount > 0 ? `<span class="text-amber-600 font-semibold">${pendingCount.toLocaleString()} Pending</span>` : ''}
                            <span class="text-red-500 font-medium">${t.failed.toLocaleString()} Fail</span>
                        </div>
                    </div>
                    <div class="px-5 py-2.5 bg-slate-50 border-t border-slate-100 text-xs text-slate-400 text-center">
                        Pass rate: <strong class="text-slate-600">${rate}${rate !== 'Pending' ? '%' : ''}</strong>
                    </div>
                </div>`;
        }).join('');
    }

    // ─── Delete Table ───────────────────────────────────────
    async function deleteTable(name) {
        if (!confirm(`Delete table "${name}" and all its records? This cannot be undone.`)) return;

        try {
            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_table&table_name=${encodeURIComponent(name)}`
            });
            const data = await resp.json();
            if (data.success) {
                loadDashboard();
            } else {
                alert(data.error || 'Failed to delete table.');
            }
        } catch (e) { alert('Error deleting table.'); }
    }

    // ─── Parse Examinee Number (JS mirror) ──────────────────
    function parseExamineeNumber(num) {
        num = num.replace(/[^0-9]/g, '');
        if (num.length < 14) return null;
        return {
            year:     num.substring(0, 2),
            month:    num.substring(2, 4),
            examType: num.substring(4, 6),
            site:     num.substring(6, 9),
            level:    num.substring(9, 10),
            roll:     num.substring(10, 14)
        };
    }

    // ─── Cron Job Management ────────────────────────────────
    const ADMIN_API_URL = window.location.pathname;
    let currentCronConfig = null;

    async function loadCronConfig() {
        try {
            const resp = await fetch(ADMIN_API_URL + '?action=get_cron_config');
            const data = await resp.json();
            if (!data.success) return;

            currentCronConfig = data.config;
            const statuses = data.statuses || {};

            // Populate form values
            const mSelect = document.getElementById('cron-target-month');
            const yInput  = document.getElementById('cron-target-year');
            const cSelect = document.getElementById('cron-center-code');
            const autoChk = document.getElementById('cron-auto-disable');

            if (mSelect && currentCronConfig.target_month) mSelect.value = currentCronConfig.target_month;
            if (yInput && currentCronConfig.target_year)   yInput.value = currentCronConfig.target_year;
            if (cSelect && currentCronConfig.center_code) cSelect.value = currentCronConfig.center_code;
            if (autoChk) autoChk.checked = (currentCronConfig.auto_disable_on_complete !== false);

            updateCronUI(currentCronConfig, statuses);
        } catch (e) {
            console.error('Failed to load cron config:', e);
        }
    }

    function updateCronUI(cfg, statuses = {}) {
        const toggleBtn   = document.getElementById('cron-toggle-btn');
        const toggleKnob  = document.getElementById('cron-toggle-knob');
        const toggleLabel = document.getElementById('cron-toggle-label');
        const badgeStatus = document.getElementById('cron-badge-status');
        const lastRunSpan = document.getElementById('cron-last-run');
        const statusTitle = document.getElementById('cron-status-title');
        const statusDesc  = document.getElementById('cron-status-desc');
        const resetBtn    = document.getElementById('btn-cron-reset');
        const statusBanner= document.getElementById('cron-status-banner');
        const statusIcon  = document.getElementById('cron-status-icon');

        const isEnabled = !!cfg.enabled;

        // Toggle Switch visual state
        if (toggleBtn && toggleKnob) {
            toggleBtn.setAttribute('aria-checked', isEnabled ? 'true' : 'false');
            toggleKnob.style.transition = 'transform 0.2s cubic-bezier(0.4, 0, 0.2, 1)';
            if (isEnabled) {
                toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-emerald-500 shadow-sm shadow-emerald-500/30';
                toggleKnob.style.transform = 'translateX(20px)';
                if (toggleLabel) toggleLabel.textContent = 'Cron Enabled';
            } else {
                toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
                toggleKnob.style.transform = 'translateX(0px)';
                if (toggleLabel) toggleLabel.textContent = 'Cron Disabled';
            }
        }

        // Job Key for active status lookup: e.g. 26_10_474
        const y2 = (cfg.target_year || '26').slice(-2);
        const m2 = (cfg.target_month || '10');
        const cCode = cfg.center_code || '474';
        const jobKey = `${y2}_${m2}_${cCode}`;
        const activeJob = statuses[jobKey] || null;

        // Last Run Info
        if (lastRunSpan) {
            lastRunSpan.textContent = cfg.last_run ? `Last run: ${cfg.last_run}` : 'Last run: Never';
        }

        const monthName = MONTHS_FULL[m2] || 'October';

        if (!isEnabled) {
            // Disabled
            if (badgeStatus) {
                badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-slate-800 text-slate-300 border border-slate-700 flex items-center gap-1.5';
                badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> DISABLED';
            }
            if (statusTitle) statusTitle.textContent = 'Cron Job is Disabled (OFF)';
            if (statusDesc) statusDesc.textContent = `Automated result scraping is currently turned off. To monitor ${monthName} 20${y2} results, toggle the switch above to Enable.`;
            if (resetBtn) resetBtn.classList.add('hidden');
            if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 border-slate-200 text-slate-700';
            if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5';

        } else if (activeJob && activeJob.status === 'COMPLETED') {
            // Completed
            if (badgeStatus) {
                badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1.5';
                badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> COMPLETED (HALTED)';
            }
            if (statusTitle) statusTitle.textContent = `Completed & Statuses Updated for ${monthName} 20${y2}`;
            const totalPass = activeJob.total_passed || 0;
            const passUp = activeJob.total_passed_updated !== undefined ? activeJob.total_passed_updated : (activeJob.total_updated || 0);
            const failUp = activeJob.total_failed_updated || 0;
            if (statusDesc) statusDesc.textContent = `Official results processed (${totalPass} passed examinees found). Database updated: ${passUp} set to Pass, ${failUp} set to Fail. Cron has auto-halted to save resources.`;
            if (resetBtn) resetBtn.classList.remove('hidden');
            if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-emerald-50 border-emerald-200 text-emerald-900';
            if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-emerald-200 text-emerald-800 flex items-center justify-center shrink-0 mt-0.5';

        } else if (activeJob && activeJob.status === 'WAITING') {
            // Waiting
            if (badgeStatus) {
                badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1.5';
                badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> WAITING FOR PUBLICATION';
            }
            if (statusTitle) statusTitle.textContent = `Active: Monitoring ${monthName} 20${y2} Results`;
            if (statusDesc) statusDesc.textContent = `Results have not yet been posted on nat-test.jp. The cron job is running on schedule and will immediately update the database as soon as results appear.`;
            if (resetBtn) resetBtn.classList.add('hidden');
            if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-amber-50 border-amber-200 text-amber-900';
            if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-amber-200 text-amber-800 flex items-center justify-center shrink-0 mt-0.5';

        } else {
            // Active
            if (badgeStatus) {
                badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-blue-100 text-blue-800 border border-blue-300 flex items-center gap-1.5';
                badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span> ACTIVE (MONITORING)';
            }
            if (statusTitle) statusTitle.textContent = `Active: Ready to Scrape ${monthName} 20${y2}`;
            if (statusDesc) statusDesc.textContent = `Cron job is enabled and ready to run for ${monthName} 20${y2} (Center: ${cCode}).`;
            if (resetBtn) resetBtn.classList.add('hidden');
            if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-blue-50 border-blue-200 text-blue-900';
            if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-blue-200 text-blue-800 flex items-center justify-center shrink-0 mt-0.5';
        }
    }

    async function toggleCronJob() {
        const toggleBtn = document.getElementById('cron-toggle-btn');
        const toggleKnob = document.getElementById('cron-toggle-knob');
        const toggleLabel = document.getElementById('cron-toggle-label');
        if (!currentCronConfig) return;

        const willEnable = !currentCronConfig.enabled;
        toggleBtn.disabled = true;

        // Instant optimistic animation
        toggleBtn.setAttribute('aria-checked', willEnable ? 'true' : 'false');
        if (willEnable) {
            toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-emerald-500 shadow-sm shadow-emerald-500/30';
            toggleKnob.style.transform = 'translateX(20px)';
            if (toggleLabel) toggleLabel.textContent = 'Cron Enabled';
        } else {
            toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
            toggleKnob.style.transform = 'translateX(0px)';
            if (toggleLabel) toggleLabel.textContent = 'Cron Disabled';
        }

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'toggle_cron');
            formData.append('enabled', willEnable ? '1' : '0');

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const rawText = await resp.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (err) {
                console.error('Non-JSON response:', rawText);
                alert('Server response error (' + resp.status + '): ' + rawText.substring(0, 180));
                loadCronConfig();
                return;
            }

            if (data.success) {
                currentCronConfig.enabled = data.enabled;
                loadCronConfig();
            } else {
                alert(data.error || 'Failed to toggle cron job.');
                loadCronConfig();
            }
        } catch (e) {
            console.error(e);
            alert('Connection error: ' + (e.message || 'Could not communicate with server.'));
            loadCronConfig();
        } finally {
            toggleBtn.disabled = false;
        }
    }

    async function saveCronConfig(e) {
        if (e) e.preventDefault();

        const form = document.getElementById('cron-config-form');
        const saveBtn = document.getElementById('btn-save-cron');
        const feedback = document.getElementById('cron-save-feedback');

        saveBtn.disabled = true;
        saveBtn.classList.add('opacity-70');

        try {
            const formData = new FormData(form);
            formData.append('action', 'save_cron_config');

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                body: formData
            });

            const rawText = await resp.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (err) {
                console.error('Non-JSON response:', rawText);
                alert('Server response error (' + resp.status + '): ' + rawText.substring(0, 180));
                return;
            }

            if (data.success) {
                currentCronConfig = data.config;
                feedback.classList.remove('opacity-0');
                setTimeout(() => feedback.classList.add('opacity-0'), 2500);
                loadCronConfig();
            } else {
                alert(data.error || 'Failed to save settings.');
            }
        } catch (e) {
            console.error(e);
            alert('Error saving cron configuration: ' + (e.message || e));
        } finally {
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-70');
        }
    }

    async function resetCronLock() {
        if (!confirm('Are you sure you want to reset the completion lock? This will allow the scraper to re-process results for this session.')) {
            return;
        }

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'reset_cron_status');

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await resp.json();
            if (data.success) {
                alert(data.message || 'Status lock reset successfully.');
                loadCronConfig();
            }
        } catch (e) {
            console.error(e);
            alert('Error resetting status lock.');
        }
    }

    async function runScraperNow() {
        const btn = document.getElementById('btn-cron-run-now');
        const btnText = document.getElementById('btn-cron-run-text');
        const outBox = document.getElementById('cron-run-output-box');
        const outText = document.getElementById('cron-run-output-text');

        btn.disabled = true;
        btn.classList.add('opacity-75');
        btnText.textContent = 'Scraping...';

        outBox.classList.remove('hidden');
        outText.textContent = 'Connecting to nat-test.jp and scraping results... Please wait...\n';

        const targetMonth  = document.getElementById('cron-target-month')?.value || (currentCronConfig?.target_month || '10');
        const targetYear   = document.getElementById('cron-target-year')?.value  || (currentCronConfig?.target_year  || '26');
        const targetCenter = document.getElementById('cron-center-code')?.value || (currentCronConfig?.center_code || '474');

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'run_cron_now');
            formData.append('target_month', targetMonth);
            formData.append('target_year', targetYear);
            formData.append('center_code', targetCenter);

            const adminResp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const rawText = await adminResp.text();
            let data = null;
            try {
                data = JSON.parse(rawText);
            } catch (jsonErr) {
                outText.textContent = rawText.trim() || 'Scraping completed.';
            }

            if (data) {
                if (data.output || data.logs) {
                    outText.textContent = data.output || (Array.isArray(data.logs) ? data.logs.join('\n') : JSON.stringify(data, null, 2));
                } else if (data.message) {
                    outText.textContent = data.message;
                } else if (data.error) {
                    outText.textContent = 'Error: ' + data.error;
                } else {
                    outText.textContent = JSON.stringify(data, null, 2);
                }

                if (data.config) {
                    currentCronConfig = data.config;
                }
            }

            loadCronConfig();
            loadDashboard();

        } catch (e) {
            console.error('Scraper run error:', e);
            outText.textContent = 'Execution error: ' + (e.message || 'Could not communicate with server.');
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-75');
            btnText.textContent = 'Run Now (Test Scrape)';
        }
    }

    // ─── CBT Portal Users Management ─────────────────────────
    let portalUsersList = [];

    async function loadUsersList() {
        const tbody = document.getElementById('users-table-body');
        const badge = document.getElementById('user-count-badge');
        const statUsers = document.getElementById('stat-portal-users');

        try {
            const resp = await fetch(ADMIN_API_URL + '?action=get_users');
            const data = await resp.json();

            if (data.success && Array.isArray(data.users)) {
                portalUsersList = data.users;
                if (badge) badge.textContent = `${portalUsersList.length} User${portalUsersList.length === 1 ? '' : 's'}`;
                if (statUsers) statUsers.textContent = portalUsersList.length;
                renderUsersTable(portalUsersList);
            } else {
                if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-500 font-medium">${data.error || 'Failed to load users.'}</td></tr>`;
            }
        } catch (e) {
            console.error('Failed to load portal users:', e);
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-400">Error connecting to server.</td></tr>`;
        }
    }

    function renderUsersTable(users) {
        const tbody = document.getElementById('users-table-body');
        if (!tbody) return;

        if (!users || !users.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="py-10 text-center text-slate-400">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <p class="font-medium text-slate-500">No portal users added yet.</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Click "Add New User" above to create credentials for institutes or staff.</p>
                        </div>
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = users.map(u => {
            const isActive = (u.status === 'active');
            const badgeClass = isActive 
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                : 'bg-slate-100 text-slate-500 border-slate-300';
            const badgeDot = isActive ? 'bg-emerald-500' : 'bg-slate-400';
            const userJson = JSON.stringify(u).replace(/"/g, '&quot;');

            return `
                <tr class="hover:bg-slate-50/80 transition">
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-900">${escapeHtml(u.name)}</div>
                        <div class="text-slate-500 text-[11px] font-mono">${escapeHtml(u.email)}</div>
                    </td>
                    <td class="py-3 px-4 text-slate-600 font-mono">
                        ${u.phone ? escapeHtml(u.phone) : '<span class="text-slate-300">—</span>'}
                    </td>
                    <td class="py-3 px-4 text-slate-700">
                        ${u.institute ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-medium text-[11px]">${escapeHtml(u.institute)}</span>` : '<span class="text-slate-300">—</span>'}
                    </td>
                    <td class="py-3 px-4">
                        <button type="button" onclick="toggleUserStatus(${u.id})" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide uppercase border transition cursor-pointer hover:opacity-80 ${badgeClass}" title="Click to ${isActive ? 'Deactivate' : 'Activate'} user">
                            <span class="w-1.5 h-1.5 rounded-full ${badgeDot}"></span>
                            <span>${isActive ? 'Active' : 'Inactive'}</span>
                        </button>
                    </td>
                    <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">
                        ${u.created_at ? u.created_at.substring(0, 10) : '—'}
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button type="button" onclick="openEditUserModal(${userJson})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium text-[11px] transition cursor-pointer" title="Edit user or reset password">
                                Edit
                            </button>
                            <button type="button" onclick="deleteUser(${u.id}, '${escapeHtml(u.name).replace(/'/g, "\\'")}')" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium text-[11px] transition cursor-pointer" title="Delete user">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    function filterUsersTable() {
        const q = (document.getElementById('user-search-input')?.value || '').trim().toLowerCase();
        if (!q) {
            renderUsersTable(portalUsersList);
            return;
        }
        const filtered = portalUsersList.filter(u => {
            return (u.name || '').toLowerCase().includes(q)
                || (u.email || '').toLowerCase().includes(q)
                || (u.phone || '').toLowerCase().includes(q)
                || (u.institute || '').toLowerCase().includes(q);
        });
        renderUsersTable(filtered);
    }

    function openAddUserModal() {
        document.getElementById('user-modal-title').textContent = 'Add New Portal User';
        document.getElementById('user-form-id').value = '';
        document.getElementById('user-form-name').value = '';
        document.getElementById('user-form-email').value = '';
        document.getElementById('user-form-phone').value = '';
        document.getElementById('user-form-institute').value = '';
        document.getElementById('user-form-password').value = '';
        document.getElementById('user-form-password').required = true;
        document.getElementById('user-form-pw-req').style.display = 'inline';
        document.getElementById('user-form-pw-hint').textContent = 'Min 6 characters. Passwords are securely hashed with bcrypt in the database.';
        document.getElementById('user-modal').classList.remove('hidden');
        document.getElementById('user-form-name').focus();
    }

    function openEditUserModal(user) {
        document.getElementById('user-modal-title').textContent = 'Edit Portal User: ' + user.name;
        document.getElementById('user-form-id').value = user.id;
        document.getElementById('user-form-name').value = user.name || '';
        document.getElementById('user-form-email').value = user.email || '';
        document.getElementById('user-form-phone').value = user.phone || '';
        document.getElementById('user-form-institute').value = user.institute || '';
        document.getElementById('user-form-password').value = '';
        document.getElementById('user-form-password').required = false;
        document.getElementById('user-form-pw-req').style.display = 'none';
        document.getElementById('user-form-pw-hint').textContent = 'Leave password blank to keep current password unchanged.';
        document.getElementById('user-modal').classList.remove('hidden');
    }

    function closeUserModal() {
        document.getElementById('user-modal').classList.add('hidden');
    }

    function generateRandomUserPassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
        let pwd = '';
        const uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lowers = 'abcdefghjkmnpqrstuvwxyz';
        const digits = '23456789';
        const specials = '!@#$%=';
        pwd += uppers.charAt(Math.floor(Math.random() * uppers.length));
        pwd += lowers.charAt(Math.floor(Math.random() * lowers.length));
        pwd += digits.charAt(Math.floor(Math.random() * digits.length));
        pwd += specials.charAt(Math.floor(Math.random() * specials.length));
        for (let i = 0; i < 6; i++) {
            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('user-form-password').value = pwd;
    }

    function copyUserPassword() {
        const val = document.getElementById('user-form-password').value;
        if (!val) {
            alert('Password field is empty.');
            return;
        }
        navigator.clipboard.writeText(val).then(() => {
            alert('Password copied to clipboard!');
        }).catch(() => {
            prompt('Copy password:', val);
        });
    }

    async function saveUserForm(e) {
        if (e) e.preventDefault();
        const saveBtn = document.getElementById('btn-save-user');
        const id        = document.getElementById('user-form-id').value;
        const name      = document.getElementById('user-form-name').value.trim();
        const email     = document.getElementById('user-form-email').value.trim();
        const phone     = document.getElementById('user-form-phone').value.trim();
        const institute = document.getElementById('user-form-institute').value.trim();
        const password  = document.getElementById('user-form-password').value.trim();

        if (!name || !email) {
            alert('Name and Email are required.');
            return;
        }
        if (!id && (!password || password.length < 6)) {
            alert('Password must be at least 6 characters.');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const formData = new URLSearchParams();
            formData.append('action', id ? 'update_user' : 'create_user');
            if (id) formData.append('id', id);
            formData.append('name', name);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('institute', institute);
            if (password) formData.append('password', password);

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await resp.json();
            if (data.success) {
                alert(data.message || 'Saved successfully.');
                closeUserModal();
                loadUsersList();
            } else {
                alert(data.error || 'Failed to save user.');
            }
        } catch (err) {
            console.error('Error saving user:', err);
            alert('Connection error saving user.');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save User';
        }
    }

    async function toggleUserStatus(id) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'toggle_user_status');
            formData.append('id', id);

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await resp.json();
            if (data.success) {
                loadUsersList();
            } else {
                alert(data.error || 'Failed to toggle status.');
            }
        } catch (e) {
            console.error('Toggle status error:', e);
            alert('Error communicating with server.');
        }
    }

    async function deleteUser(id, name) {
        if (!confirm(`Are you sure you want to permanently delete user account "${name}"? This action cannot be undone.`)) {
            return;
        }

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'delete_user');
            formData.append('id', id);

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await resp.json();
            if (data.success) {
                alert(data.message || 'User deleted.');
                loadUsersList();
            } else {
                alert(data.error || 'Failed to delete user.');
            }
        } catch (e) {
            console.error('Delete user error:', e);
            alert('Error communicating with server.');
        }
    }

    // ─── CBT Excluded Days Management ─────────────────────────
    let excludedDaysData = { "474": [], "476": [], "all": [] };
    let currentExcludedCenter = '474';
    let calendarViewDate = new Date();

    const CENTER_NAMES = {
        '474': 'ULSAI (Dhaka - 474)',
        '476': 'KHULNA (476)',
        'all': 'All Centers / Nationwide (all)'
    };

    async function loadExcludedDays() {
        try {
            const resp = await fetch(ADMIN_API_URL + '?action=get_excluded_days');
            const data = await resp.json();
            if (data.success && data.excludedDays) {
                excludedDaysData = {
                    "474": Array.isArray(data.excludedDays['474']) ? data.excludedDays['474'] : [],
                    "476": Array.isArray(data.excludedDays['476']) ? data.excludedDays['476'] : [],
                    "all": Array.isArray(data.excludedDays['all']) ? data.excludedDays['all'] : []
                };
                renderExcludedDaysUI();
            }
        } catch (e) {
            console.error('Failed to load excluded days:', e);
        }
    }

    function setExcludedCenterTab(center) {
        currentExcludedCenter = center;

        ['474', '476', 'all'].forEach(c => {
            const tab = document.getElementById(`tab-center-${c}`);
            if (tab) {
                if (c === center) {
                    tab.className = 'px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-rose-600 text-rose-700 bg-white shadow-sm cursor-pointer';
                } else {
                    tab.className = 'px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-600 hover:text-slate-900 cursor-pointer';
                }
            }
        });

        const labelEl = document.getElementById('current-center-label');
        if (labelEl) labelEl.textContent = CENTER_NAMES[center] || center;

        renderExcludedDaysUI();
    }

    function renderExcludedDaysUI() {
        // Update badge counts
        const b474 = document.getElementById('badge-count-474');
        const b476 = document.getElementById('badge-count-476');
        const bAll = document.getElementById('badge-count-all');
        if (b474) b474.textContent = (excludedDaysData['474'] || []).length;
        if (b476) b476.textContent = (excludedDaysData['476'] || []).length;
        if (bAll) bAll.textContent = (excludedDaysData['all'] || []).length;

        renderExcludedCalendar();
        renderExcludedDatesList();
    }

    function changeExcludedMonth(delta) {
        calendarViewDate.setMonth(calendarViewDate.getMonth() + delta);
        renderExcludedCalendar();
    }

    function resetExcludedMonthToToday() {
        calendarViewDate = new Date();
        renderExcludedCalendar();
    }

    function formatLocalDate(year, monthIndex, day) {
        const y = year;
        const m = String(monthIndex + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function renderExcludedCalendar() {
        const grid = document.getElementById('calendar-days-grid');
        const title = document.getElementById('calendar-month-year');
        if (!grid || !title) return;

        const year = calendarViewDate.getFullYear();
        const month = calendarViewDate.getMonth();
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        title.textContent = `${monthNames[month]} ${year}`;

        const firstDayIndex = new Date(year, month, 1).getDay(); // 0 (Sun) to 6 (Sat)
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = formatLocalDate(today.getFullYear(), today.getMonth(), today.getDate());

        let html = '';

        // Empty padding cells before first day
        for (let i = 0; i < firstDayIndex; i++) {
            html += `<div class="h-16 rounded-xl bg-slate-50/40 border border-transparent"></div>`;
        }

        const centerExclusions = excludedDaysData[currentExcludedCenter] || [];
        const allExclusions = excludedDaysData['all'] || [];

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = formatLocalDate(year, month, day);
            const dateObj = new Date(year, month, day);
            dateObj.setHours(0, 0, 0, 0);

            const isPastOrToday = dateObj <= today;
            const isCenterExcluded = centerExclusions.includes(dateStr);
            const isNationwideExcluded = allExclusions.includes(dateStr);

            let cellClass = '';
            let badgeHtml = '';
            let clickHandler = '';

            if (isPastOrToday) {
                cellClass = 'bg-slate-50 border-slate-200 text-slate-300 cursor-not-allowed opacity-50';
                badgeHtml = dateStr === todayStr ? '<span class="text-[9px] text-slate-400 font-bold block">Today</span>' : '';
            } else if (isCenterExcluded) {
                cellClass = 'bg-rose-50 border-rose-300 text-rose-800 font-bold hover:bg-rose-100 cursor-pointer shadow-sm';
                badgeHtml = '<span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-semibold bg-rose-600 text-white rounded">Excluded</span>';
                clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
            } else if (isNationwideExcluded && currentExcludedCenter !== 'all') {
                cellClass = 'bg-amber-50 border-amber-300 text-amber-800 font-bold hover:bg-amber-100 cursor-pointer shadow-sm';
                badgeHtml = '<span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-semibold bg-amber-600 text-white rounded">All Centers</span>';
                clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
            } else if (isNationwideExcluded && currentExcludedCenter === 'all') {
                cellClass = 'bg-rose-50 border-rose-300 text-rose-800 font-bold hover:bg-rose-100 cursor-pointer shadow-sm';
                badgeHtml = '<span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-semibold bg-rose-600 text-white rounded">Excluded</span>';
                clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
            } else {
                cellClass = 'bg-white border-slate-200 text-slate-700 hover:border-rose-400 hover:bg-rose-50/30 cursor-pointer';
                clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
            }

            html += `
                <div ${clickHandler} class="h-16 p-1.5 rounded-xl border flex flex-col justify-between transition group relative ${cellClass}">
                    <span class="text-xs font-semibold">${day}</span>
                    <div class="truncate">${badgeHtml}</div>
                </div>
            `;
        }

        grid.innerHTML = html;
    }

    async function toggleExcludedDayOnCalendar(dateStr) {
        const isCenterExcluded = (excludedDaysData[currentExcludedCenter] || []).includes(dateStr);
        const isNationwideExcluded = (excludedDaysData['all'] || []).includes(dateStr);

        if (currentExcludedCenter !== 'all' && isNationwideExcluded && !isCenterExcluded) {
            alert(`The date "${dateStr}" is currently excluded nationwide in the 'All Centers' tab. Switch to 'All Centers / Nationwide' tab if you want to remove it.`);
            return;
        }

        if (isCenterExcluded) {
            if (confirm(`Remove exclusion for date ${dateStr} from center ${CENTER_NAMES[currentExcludedCenter]}?`)) {
                await removeExcludedDate(currentExcludedCenter, dateStr);
            }
        } else {
            await addExcludedDate(currentExcludedCenter, dateStr);
        }
    }

    async function addExcludedDate(center, dateStr) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'add_excluded_day');
            formData.append('center', center);
            formData.append('date', dateStr);

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await resp.json();
            if (data.success && data.excludedDays) {
                excludedDaysData = data.excludedDays;
                renderExcludedDaysUI();
            } else {
                alert(data.error || 'Failed to add excluded date.');
            }
        } catch (e) {
            console.error('Error adding excluded date:', e);
            alert('Error communicating with server.');
        }
    }

    async function removeExcludedDate(center, dateStr) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'remove_excluded_day');
            formData.append('center', center);
            formData.append('date', dateStr);

            const resp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await resp.json();
            if (data.success && data.excludedDays) {
                excludedDaysData = data.excludedDays;
                renderExcludedDaysUI();
            } else {
                alert(data.error || 'Failed to remove excluded date.');
            }
        } catch (e) {
            console.error('Error removing excluded date:', e);
            alert('Error communicating with server.');
        }
    }

    async function quickAddExcludedDate() {
        const input = document.getElementById('input-quick-exclude-date');
        if (!input || !input.value) {
            alert('Please select a valid date.');
            return;
        }

        const dateStr = input.value;
        const todayStr = new Date().toISOString().split('T')[0];
        if (dateStr <= todayStr) {
            alert('Excluded dates should be future dates (from tomorrow onward). Past dates and today are already blocked automatically.');
            return;
        }

        await addExcludedDate(currentExcludedCenter, dateStr);
        input.value = '';
    }

    function renderExcludedDatesList() {
        const container = document.getElementById('excluded-dates-list');
        const countBadge = document.getElementById('active-center-total');
        if (!container) return;

        const centerDates = (excludedDaysData[currentExcludedCenter] || []).map(d => ({ date: d, scope: 'center' }));
        let combined = [...centerDates];

        if (currentExcludedCenter !== 'all') {
            const nationwideDates = (excludedDaysData['all'] || []).map(d => ({ date: d, scope: 'nationwide' }));
            combined = [...combined, ...nationwideDates];
        }

        // Sort chronologically
        combined.sort((a, b) => a.date.localeCompare(b.date));

        if (countBadge) {
            countBadge.textContent = `${combined.length} date${combined.length === 1 ? '' : 's'}`;
        }

        if (combined.length === 0) {
            container.innerHTML = `
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                    <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <p class="text-xs font-semibold">No excluded dates for this center</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Click any date on the calendar to exclude it.</p>
                </div>
            `;
            return;
        }

        let html = '';
        combined.forEach(item => {
            const dateParts = item.date.split('-');
            const dObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const weekday = dObj.toLocaleDateString('en-US', { weekday: 'short' });
            const monthName = dObj.toLocaleDateString('en-US', { month: 'short' });
            const dayNum = dateParts[2];
            const yearNum = dateParts[0];

            const isNationwide = item.scope === 'nationwide';
            const badgeClass = isNationwide
                ? 'bg-amber-100 text-amber-800 border-amber-200'
                : 'bg-rose-100 text-rose-700 border-rose-200';
            const badgeLabel = isNationwide ? 'Nationwide (all)' : 'Center Specific';

            html += `
                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex items-center justify-between gap-3 hover:border-slate-300 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 flex flex-col items-center justify-center text-center">
                            <span class="text-[9px] font-bold uppercase text-slate-500 leading-tight">${monthName}</span>
                            <span class="text-sm font-extrabold text-slate-800 leading-tight">${dayNum}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-slate-800">${item.date}</span>
                                <span class="text-slate-400 text-xs">(${weekday}, ${yearNum})</span>
                            </div>
                            <div class="mt-0.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border ${badgeClass}">
                                    ${badgeLabel}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        ${isNationwide && currentExcludedCenter !== 'all' ? `
                            <button type="button" onclick="setExcludedCenterTab('all')" class="text-[11px] font-semibold text-amber-700 hover:underline px-2 py-1 bg-amber-50 rounded-lg cursor-pointer" title="Manage in Nationwide tab">
                                Go to Nationwide
                            </button>
                        ` : `
                            <button type="button" onclick="removeExcludedDate('${currentExcludedCenter}', '${item.date}')" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer" title="Remove exclusion">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        `}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    loadDashboard();
    loadCronConfig();
    loadUsersList();
    loadExcludedDays();
    </script>

<?php endif; ?>
</body>
</html>