<?php
// 1. Error handling & setup
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Buffer output for web / CLI
$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));
$isAdminInternal = defined('ADMIN_INTERNAL_EXECUTION');

// ─── SECURITY: Restrict Web Access ───────────────────────────
// Only CLI (Server Cron) or authenticated Admin execution is permitted.
if (!$isCli && !$isAdminInternal) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $adminEnv = dirname(__DIR__) . '/admin/.env';
    $adminSession = 'admin_logged_in';
    if (file_exists($adminEnv)) {
        $lines = file($adminEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'ADMIN_SESSION_NAME=') === 0) {
                list(, $v) = explode('=', $line, 2);
                $adminSession = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    if (empty($_SESSION[$adminSession])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die("403 Forbidden: Public web access is blocked. This script is restricted to server cron jobs and admin backend only.");
    }
}

// Set timezone
date_default_timezone_set('Asia/Dhaka');

// 2. Load environment & database connection
function getCronDbConnection() {
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) return [null, 'configaration file not found'];
    
    $env = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }

    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';

    if (empty($dbname)) return [null, 'DB_NAME is not configured in .env'];

    $dsns = [
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4"
    ];

    $lastError = '';
    foreach ($dsns as $dsn) {
        try {
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return [$pdo, null];
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
        }
    }

    return [null, $lastError];
}

// ─── HTTP Fetch & Scrape Helper Functions ────────────────────
@set_time_limit(180);

function fetchUrl($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res !== false && !empty($res)) return $res;
        return false;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    return @file_get_contents($url, false, $ctx);
}

function scrapeNatTestResultLinks($targetYear4, $targetMonthCode) {
    $indexUrl = "https://nat-test.jp/contents/result.html";
    $baseUrl  = "https://nat-test.jp/contents/";

    $html = fetchUrl($indexUrl);
    if (!$html) {
        return ['error' => 'Failed to reach nat-test.jp result page. Check network connection.'];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query("//table//tr");

    $currentYear = "";
    $targetMonthNumber = (int)$targetMonthCode;
    $monthNames = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    $targetMonthName = $monthNames[$targetMonthNumber] ?? '';

    $matchedLinks = [];

    foreach ($rows as $row) {
        $ths = $xpath->query(".//th", $row);
        $thText = "";
        foreach ($ths as $th) {
            $t = trim($th->nodeValue);
            if (preg_match('/(\d{4})年/', $t, $ym)) {
                $currentYear = $ym[1];
            }
            $thText .= " " . $t;
        }

        $monthPattern = "/({$targetMonthNumber}月|{$targetMonthName})/i";
        if ($currentYear === $targetYear4 && preg_match($monthPattern, $thText)) {
            $links = $xpath->query(".//a", $row);
            foreach ($links as $a) {
                $href = $a->getAttribute("href");
                $text = strtoupper(trim($a->nodeValue));

                $fullUrl = (strpos($href, 'http') === 0) ? $href : $baseUrl . ltrim($href, '/');
                $matchedLinks[$text] = $fullUrl;
            }
            break;
        }
    }

    return $matchedLinks;
}

// ─── Core PBT Scraper Worker Function ────────────────────────
function executePbtResultScraper($options = [], $passedPdo = null) {
    $executionLogs = [];
    $onLog = $options['on_log'] ?? null;
    $log = function($msg) use (&$executionLogs, $onLog) {
        $executionLogs[] = $msg;
        if (is_callable($onLog)) {
            call_user_func($onLog, $msg);
        }
    };

    $configFile = __DIR__ . '/cron-config.json';
    $cronConfig = [
        'enabled'                  => false,
        'target_month'             => '10',
        'target_year'              => date('y'),
        'center_code'              => '474',
        'auto_disable_on_complete' => true,
        'last_run'                 => null,
        'last_status'              => 'DISABLED',
        'updated_at'               => date('Y-m-d H:i:s')
    ];
    if (file_exists($configFile)) {
        $loadedConfig = json_decode(@file_get_contents($configFile), true);
        if (is_array($loadedConfig)) {
            $cronConfig = array_merge($cronConfig, $loadedConfig);
        }
    }

    $yearInput = $options['year'] ?? $cronConfig['target_year'] ?? date('y');
    $year2 = preg_replace('/[^0-9]/', '', (string)$yearInput);
    if (strlen($year2) === 4) $year2 = substr($year2, 2, 2);
    if (empty($year2)) $year2 = date('y');
    $year4 = '20' . $year2;

    $monthInput = strtolower(trim((string)($options['month'] ?? $cronConfig['target_month'] ?? '10')));
    $monthMap = [
        '1' => '01', '01' => '01', 'jan' => '01', 'january' => '01', '1月' => '01',
        '2' => '02', '02' => '02', 'feb' => '02', 'february' => '02', '2月' => '02',
        '3' => '03', '03' => '03', 'mar' => '03', 'march' => '03', '3月' => '03',
        '4' => '04', '04' => '04', 'apr' => '04', 'april' => '04', '4月' => '04',
        '5' => '05', '05' => '05', 'may' => '05', '5月' => '05',
        '6' => '06', '06' => '06', 'jun' => '06', 'june' => '06', '6月' => '06',
        '7' => '07', '07' => '07', 'jul' => '07', 'july' => '07', '7月' => '07',
        '8' => '08', '08' => '08', 'aug' => '08', 'august' => '08', '8月' => '08',
        '9' => '09', '09' => '09', 'sep' => '09', 'september' => '09', '9月' => '09',
        '10' => '10', 'oct' => '10', 'october' => '10', '10月' => '10',
        '11' => '11', 'nov' => '11', 'november' => '11', '11月' => '11',
        '12' => '12', 'dec' => '12', 'december' => '12', '12月' => '12'
    ];
    $monthCode = $monthMap[$monthInput] ?? '10';

    $monthNamesFull = [
        '01' => 'January', '02' => 'February', '03' => 'March',
        '04' => 'April', '05' => 'May', '06' => 'June',
        '07' => 'July', '08' => 'August', '09' => 'September',
        '10' => 'October', '11' => 'November', '12' => 'December'
    ];
    $monthAbbr = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar',
        '04' => 'Apr', '05' => 'May', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Aug', '09' => 'Sep',
        '10' => 'Oct', '11' => 'Nov', '12' => 'Dec'
    ];
    $monthName = $monthNamesFull[$monthCode] ?? 'October';
    $monthShort = $monthAbbr[$monthCode] ?? 'Oct';

    $centerInput = strtolower(trim((string)($options['center'] ?? $cronConfig['center_code'] ?? '474')));
    if ($centerInput === 'ulsai' || $centerInput === 'dhaka' || $centerInput === 'dhaka1') $centerInput = '474';
    if ($centerInput === 'khulna') $centerInput = '476';
    $centerCode = preg_replace('/[^0-9a-z]/', '', $centerInput);
    if (empty($centerCode)) $centerCode = '474';

    $isForce = !empty($options['force']);
    $action  = $options['action'] ?? 'run';

    $statusFile = __DIR__ . '/cron-status.json';
    $allStatuses = [];
    if (file_exists($statusFile)) {
        $decoded = json_decode(@file_get_contents($statusFile), true);
        if (is_array($decoded)) $allStatuses = $decoded;
    }

    $jobKey = "{$year2}_{$monthCode}_{$centerCode}";

    // Handle Reset action
    if ($action === 'reset') {
        unset($allStatuses[$jobKey]);
        @file_put_contents($statusFile, json_encode($allStatuses, JSON_PRETTY_PRINT));
        $msg = "[RESET] Status for {$jobKey} has been reset. Cron can now run again.";
        $log($msg);
        return [
            'success' => true,
            'status'  => 'RESET',
            'output'  => $msg,
            'logs'    => $executionLogs,
            'config'  => $cronConfig
        ];
    }

    // CHECK LOCK: If already COMPLETED and not forced, stop execution
    if (!$isForce && isset($allStatuses[$jobKey]) && ($allStatuses[$jobKey]['status'] ?? '') === 'COMPLETED') {
        $msg = "[CRON STOPPED] Result status update for {$jobKey} ({$monthName} {$year4}, Center {$centerCode}) is already COMPLETED. Cron halted.";
        $log($msg);
        $cronConfig['last_run'] = date('Y-m-d H:i:s');
        $cronConfig['last_status'] = 'COMPLETED';
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));
        return [
            'success'              => true,
            'status'               => 'STOPPED_COMPLETED',
            'output'               => $msg,
            'logs'                 => $executionLogs,
            'total_passed'         => $allStatuses[$jobKey]['total_passed'] ?? 0,
            'total_passed_updated' => $allStatuses[$jobKey]['total_passed_updated'] ?? 0,
            'total_failed_updated' => $allStatuses[$jobKey]['total_failed_updated'] ?? 0,
            'total_updated'        => $allStatuses[$jobKey]['total_updated'] ?? 0,
            'levels'               => $allStatuses[$jobKey]['levels'] ?? [],
            'config'               => $cronConfig
        ];
    }

    if (empty($cronConfig['enabled']) && !$isForce) {
        $msg = "[CRON DISABLED] Result scraping cron job is currently disabled in the Admin Panel.";
        $log($msg);
        $log("Target session: {$monthName} {$year4}, Center: {$centerCode}. To run, enable it in Admin Panel or pass force=1 / --force.");
        $cronConfig['last_status'] = 'DISABLED';
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));
        return [
            'success' => true,
            'status'  => 'DISABLED',
            'output'  => $msg,
            'logs'    => $executionLogs,
            'config'  => $cronConfig
        ];
    }

    // Record current run time immediately
    $cronConfig['last_run'] = date('Y-m-d H:i:s');
    @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

    $log("[START] Checking NAT-TEST results for Year: {$year4}, Month: {$monthCode} ({$monthName}), Center: {$centerCode}");

    $linksMap = scrapeNatTestResultLinks($year4, $monthCode);

    if (isset($linksMap['error'])) {
        $overallStatus = "ERROR";
        $log("[ERROR] " . $linksMap['error']);
        $cronConfig['last_status'] = 'ERROR';
        $cronConfig['last_message'] = $linksMap['error'];
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));
        return [
            'success' => false,
            'status'  => 'ERROR',
            'output'  => implode("\n", $executionLogs),
            'logs'    => $executionLogs,
            'config'  => $cronConfig
        ];
    }

    if (empty($linksMap)) {
        $overallStatus = "WAITING";
        $msg = "[WAITING] Results for {$monthName} {$year4} are not yet published on nat-test.jp. Standing by for next cron cycle.";
        $log($msg);

        $allStatuses[$jobKey] = [
            'status'     => 'WAITING',
            'last_check' => date('Y-m-d H:i:s'),
            'year'       => $year4,
            'month'      => $monthCode,
            'center'     => $centerCode,
            'message'    => 'Official results not yet posted by nat-test.jp'
        ];
        @file_put_contents($statusFile, json_encode($allStatuses, JSON_PRETTY_PRINT));

        $cronConfig['last_run'] = date('Y-m-d H:i:s');
        $cronConfig['last_status'] = 'WAITING';
        $cronConfig['last_message'] = "Official results not yet posted on nat-test.jp";
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

        return [
            'success' => true,
            'status'  => 'WAITING',
            'output'  => $msg,
            'logs'    => $executionLogs,
            'config'  => $cronConfig
        ];
    }

    $log("[FOUND] Detected official result links for: " . implode(', ', array_keys($linksMap)));

    $pdo = $passedPdo;
    if (!$pdo) {
        list($pdo, $dbError) = getCronDbConnection();
    }

    $tablesToUpdate = [];
    if ($pdo) {
        $pbtTable = $monthShort . '_' . $year2 . '_PBT';
        $chk = $pdo->query("SHOW TABLES LIKE '$pbtTable'");
        if ($chk && $chk->rowCount() > 0) $tablesToUpdate[] = $pbtTable;

        $legacyTable = $monthShort . '_' . $year2;
        if ($legacyTable !== $pbtTable) {
            $chk2 = $pdo->query("SHOW TABLES LIKE '$legacyTable'");
            if ($chk2 && $chk2->rowCount() > 0) $tablesToUpdate[] = $legacyTable;
        }

        $chk3 = $pdo->query("SHOW TABLES LIKE 'examinees'");
        if ($chk3 && $chk3->rowCount() > 0) $tablesToUpdate[] = 'examinees';
    }

    if (empty($tablesToUpdate)) {
        $log("[NOTICE] No specific month table found (e.g. {$monthShort}_{$year2}_PBT). Database updates will be skipped unless tables exist.");
    } else {
        $log("[DATABASE] Target tables to update: " . implode(', ', $tablesToUpdate));
    }

    $levelDefinitions = [
        'N5' => ['digit' => '5', 'q' => '5Q', 'name' => 'Level N5 (5Q)'],
        'N4' => ['digit' => '4', 'q' => '4Q', 'name' => 'Level N4 (4Q)'],
        'N3' => ['digit' => '3', 'q' => '3Q', 'name' => 'Level N3 (3Q)'],
        'N2' => ['digit' => '2', 'q' => '2Q', 'name' => 'Level N2 (2Q)'],
        'N1' => ['digit' => '1', 'q' => '1Q', 'name' => 'Level N1 (1Q)'],
    ];

    $levelsSummary = [];
    $grandTotalFound = 0;
    $grandTotalUpdated = 0;
    $grandTotalFailedUpdated = 0;

    foreach ($levelDefinitions as $levelKey => $meta) {
        $qKey = $meta['q'];
        $levelDigit = $meta['digit'];

        if (!isset($linksMap[$qKey])) {
            $log("[SKIP] {$meta['name']} not published or not offered in this session.");
            $levelsSummary[$levelKey] = [
                'offered' => false,
                'url'     => null,
                'found'   => 0,
                'updated' => 0
            ];
            continue;
        }

        $levelUrl = $linksMap[$qKey];
        $log("[FETCH] Downloading {$meta['name']} passed IDs from: {$levelUrl}");

        $pageHtml = fetchUrl($levelUrl);
        if (!$pageHtml) {
            $log("[ERROR] Could not download results page for {$meta['name']}");
            continue;
        }

        preg_match_all('/\b\d{14}\b/', $pageHtml, $idMatches);
        $allIdsOnPage = array_unique($idMatches[0] ?? []);

        $centerPattern = ($centerCode === 'all') ? '\d{3}' : preg_quote($centerCode, '/');
        $idRegex = "/\b(\d{2}){$monthCode}00{$centerPattern}{$levelDigit}\d{4}\b/";

        $matchedPassedIds = [];
        foreach ($allIdsOnPage as $candidateId) {
            if (preg_match($idRegex, $candidateId)) {
                $matchedPassedIds[] = $candidateId;
            }
        }

        $matchedCount = count($matchedPassedIds);
        $grandTotalFound += $matchedCount;
        $log("  ➜ [{$levelKey}] Found {$matchedCount} passing examinee IDs matching Center {$centerCode} and Level {$levelDigit}.");

        $levelPassedUpdated = 0;
        $levelFailedUpdated = 0;

        if ($pdo && !empty($tablesToUpdate)) {
            foreach ($tablesToUpdate as $tbl) {
                if ($matchedCount > 0) {
                    $chunks = array_chunk($matchedPassedIds, 100);
                    foreach ($chunks as $chunk) {
                        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                        $updateSql = "UPDATE `$tbl` 
                                      SET status = 'Pass' 
                                      WHERE examinee_number IN ($placeholders) 
                                        AND (status IS NULL OR TRIM(status) != 'Pass')";
                        $stmt = $pdo->prepare($updateSql);
                        $stmt->execute($chunk);
                        $levelPassedUpdated += $stmt->rowCount();
                    }
                }

                $failSql = "UPDATE `$tbl`
                            SET status = 'Fail'
                            WHERE (status IS NULL OR TRIM(status) != 'Pass')
                              AND CHAR_LENGTH(TRIM(examinee_number)) >= 14
                              AND SUBSTRING(TRIM(examinee_number), 1, 2) = ?
                              AND SUBSTRING(TRIM(examinee_number), 3, 2) = ?
                              AND SUBSTRING(TRIM(examinee_number), 5, 2) = '00'
                              AND SUBSTRING(TRIM(examinee_number), 10, 1) = ?";
                $failParams = [$year2, $monthCode, (string)$levelDigit];

                if ($centerCode !== 'all') {
                    $failSql .= " AND SUBSTRING(TRIM(examinee_number), 7, 3) = ?";
                    $failParams[] = $centerCode;
                }

                $stmtFail = $pdo->prepare($failSql);
                $stmtFail->execute($failParams);
                $levelFailedUpdated += $stmtFail->rowCount();
            }

            $grandTotalUpdated += $levelPassedUpdated;
            $grandTotalFailedUpdated += $levelFailedUpdated;
            $log("  ➜ [{$levelKey} DB] Database updated: {$levelPassedUpdated} set to 'Pass', {$levelFailedUpdated} set to 'Fail'.");
        }

        $levelsSummary[$levelKey] = [
            'offered'      => true,
            'url'          => $levelUrl,
            'found'        => $matchedCount,
            'updated_pass' => $levelPassedUpdated,
            'updated_fail' => $levelFailedUpdated,
            'sample'       => array_slice($matchedPassedIds, 0, 5)
        ];
    }

    $overallStatus = "COMPLETED";
    $log("[SUCCESS] All levels (N5 through N1) processed successfully.");
    $log("[CRON STOPPED] Updating lock state to COMPLETED. Cron job will not execute again unless reset.");

    $allStatuses[$jobKey] = [
        'status'               => 'COMPLETED',
        'completed_at'         => date('Y-m-d H:i:s'),
        'year'                 => $year4,
        'year_short'           => $year2,
        'month'                => $monthCode,
        'month_name'           => $monthName,
        'center'               => $centerCode,
        'total_passed'         => $grandTotalFound,
        'total_passed_updated' => $grandTotalUpdated,
        'total_failed_updated' => $grandTotalFailedUpdated,
        'total_updated'        => ($grandTotalUpdated + $grandTotalFailedUpdated),
        'levels'               => $levelsSummary
    ];

    @file_put_contents($statusFile, json_encode($allStatuses, JSON_PRETTY_PRINT));

    // Hardcoded auto-disable once results are published and database updated
    $cronConfig['enabled'] = false;
    $cronConfig['last_run'] = date('Y-m-d H:i:s');
    $cronConfig['last_status'] = 'COMPLETED';
    $cronConfig['last_message'] = "Results published & database updated ({$grandTotalUpdated} Pass, {$grandTotalFailedUpdated} Fail). Cron auto-disabled.";
    @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

    return [
        'success'              => true,
        'status'               => 'COMPLETED',
        'output'               => implode("\n", $executionLogs),
        'logs'                 => $executionLogs,
        'total_passed'         => $grandTotalFound,
        'total_passed_updated' => $grandTotalUpdated,
        'total_failed_updated' => $grandTotalFailedUpdated,
        'total_updated'        => ($grandTotalUpdated + $grandTotalFailedUpdated),
        'levels'               => $levelsSummary,
        'config'               => $cronConfig
    ];
}

// ─── Direct Script Entry Point ───────────────────────────────
if (!defined('ADMIN_INTERNAL_EXECUTION')) {
    $params = array_change_key_case($_GET ?? [], CASE_LOWER);
    if ($isCli && isset($argv) && is_array($argv)) {
        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--force' || $arg === '-f' || $arg === 'force' || $arg === 'force=1') {
                $params['force'] = '1';
            } elseif ($arg === '--reset' || $arg === 'reset') {
                $params['action'] = 'reset';
            } elseif (strpos($arg, '=') !== false) {
                list($k, $v) = explode('=', ltrim($arg, '-'), 2);
                $params[strtolower(trim($k))] = trim($v);
            }
        }
    }

    $result = executePbtResultScraper($params);

    if (isset($params['format']) && strtolower($params['format']) === 'json') {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    echo $result['output'] . "\n";
    exit($result['success'] ? 0 : 1);
}