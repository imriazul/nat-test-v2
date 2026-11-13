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
function getCbtCronDbConnection() {
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) return [null, 'Configuration file (.env) not found'];
    
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

    // In CLI on localhost, try standard socket and 127.0.0.1 fallbacks
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

// ─── HTTP Fetch Helper ───────────────────────────────────────
function fetchCbtUrl($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res !== false && !empty($res)) return $res;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    return @file_get_contents($url, false, $ctx);
}

// ─── Email Notification Helper ───────────────────────────────
function sendCbtNotificationEmails($recipientsStr, $subject, $body) {
    $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
    $recipients = array_filter(array_map('trim', explode(',', (string)$recipientsStr)));
    if (empty($recipients)) return $results;

    $envFile = dirname(__DIR__) . '/.env';
    $env = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }

    $smtpHost   = $env['VERIFY_SMTP_HOST'] ?? $env['SMTP_HOST'] ?? '';
    $smtpPort   = (int)($env['VERIFY_SMTP_PORT'] ?? $env['SMTP_PORT'] ?? 465);
    $smtpSecure = strtolower($env['VERIFY_SMTP_SECURE'] ?? $env['SMTP_SECURE'] ?? 'ssl');
    $smtpUser   = $env['VERIFY_SMTP_USER'] ?? $env['SMTP_USER'] ?? '';
    $smtpPass   = $env['VERIFY_SMTP_PASS'] ?? $env['SMTP_PASS'] ?? '';
    $smtpFrom   = $env['VERIFY_SMTP_FROM_EMAIL'] ?? $env['SMTP_FROM_EMAIL'] ?? 'verify@nat-test.bd';
    $fromName   = $env['VERIFY_SMTP_FROM_NAME'] ?? $env['SMTP_FROM_NAME'] ?? 'NAT-TEST BD CBT System';
    $resendKey  = $env['RESEND_API_KEY'] ?? '';

    foreach ($recipients as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

        $sent = false;
        $lastErr = '';

        // Priority 1: Direct SMTP Socket Client
        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            try {
                $socketHost = ($smtpSecure === 'ssl' || $smtpPort === 465) ? "ssl://{$smtpHost}" : $smtpHost;
                $ctx = stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                        'SNI_enabled'       => true,
                        'peer_name'         => $smtpHost
                    ]
                ]);

                $errno = 0;
                $errstr = '';
                $socket = @stream_socket_client("{$socketHost}:{$smtpPort}", $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
                if ($socket) {
                    stream_set_timeout($socket, 12);
                    $read = function() use ($socket) {
                        $r = "";
                        while (!feof($socket) && ($s = fgets($socket, 515))) {
                            $r .= $s;
                            if (isset($s[3]) && $s[3] === " ") break;
                        }
                        return $r;
                    };
                    $cmd = function($c) use ($socket, $read) {
                        fputs($socket, $c . "\r\n");
                        return $read();
                    };

                    $read(); // Initial 220 banner
                    $cmd("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                    $cmd("AUTH LOGIN");
                    $cmd(base64_encode($smtpUser));
                    $auth = $cmd(base64_encode($smtpPass));

                    if (substr($auth, 0, 3) === "235") {
                        $cmd("MAIL FROM: <{$smtpUser}>");
                        $cmd("RCPT TO: <{$email}>");
                        $cmd("DATA");
                        $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$smtpFrom}>\r\n"
                                 . "To: <{$email}>\r\n"
                                 . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                                 . "MIME-Version: 1.0\r\n"
                                 . "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                        $cmd($headers . $body . "\r\n.");
                        $cmd("QUIT");
                        $sent = true;
                    } else {
                        $lastErr = "SMTP Authentication failed: " . trim($auth);
                    }
                    @fclose($socket);
                } else {
                    $lastErr = "Could not connect to SMTP server {$socketHost}:{$smtpPort} - {$errstr}";
                }
            } catch (Throwable $e) {
                $lastErr = "SMTP Error: " . $e->getMessage();
            }
        }

        // Priority 2: Resend API HTTP Fallback
        if (!$sent && !empty($resendKey) && function_exists('curl_init')) {
            try {
                $payload = json_encode([
                    'from'    => "{$fromName} <{$smtpFrom}>",
                    'to'      => [$email],
                    'subject' => $subject,
                    'text'    => $body
                ]);

                $ch = curl_init('https://api.resend.com/emails');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $resendKey,
                        'Content-Type: application/json'
                    ]
                ]);
                $res = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    $sent = true;
                } else {
                    $lastErr = "Resend API returned HTTP {$httpCode}: {$res}";
                }
            } catch (Throwable $e) {
                $lastErr = "Resend API Error: " . $e->getMessage();
            }
        }

        // Priority 3: Native PHP mail() only if function exists and is enabled
        if (!$sent && function_exists('mail')) {
            try {
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-type: text/plain; charset=UTF-8',
                    'From: ' . $fromName . ' <' . $smtpFrom . '>',
                    'X-Mailer: PHP/' . phpversion()
                ];
                $sent = @mail($email, $subject, $body, implode("\r\n", $headers));
                if (!$sent) {
                    $lastErr = "Native mail() returned false.";
                }
            } catch (Throwable $e) {
                $lastErr = "Native mail() error: " . $e->getMessage();
            }
        }

        if ($sent) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][$email] = $lastErr ?: 'Email delivery failed (no supported transport available or all methods failed).';
        }
    }

    return $results;
}

// ─── Core CBT Scraper Worker Function ─────────────────────────
function executeCbtResultScraper($options = [], $passedPdo = null) {
    @set_time_limit(180);
    $logs = [];
    $onLog = $options['on_log'] ?? null;
    $log = function($msg) use (&$logs, $onLog) {
        $logs[] = $msg;
        if (is_callable($onLog)) {
            call_user_func($onLog, $msg);
        }
    };

    $configFile = __DIR__ . '/cbt-cron-config.json';
    $statusFile = __DIR__ . '/cbt-cron-status.json';

    // 1. Load config
    $cronConfig = [
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
    if (file_exists($configFile)) {
        $loaded = json_decode(@file_get_contents($configFile), true);
        if (is_array($loaded)) $cronConfig = array_merge($cronConfig, $loaded);
    }

    $cbtStatus = [
        'status'        => 'INITIALIZED',
        'last_check'    => null,
        'last_cutoff'   => null,
        'total_passed'  => 0,
        'total_updated' => 0,
        'history'       => []
    ];
    if (file_exists($statusFile)) {
        $loadedStatus = json_decode(@file_get_contents($statusFile), true);
        if (is_array($loadedStatus)) $cbtStatus = array_merge($cbtStatus, $loadedStatus);
    }

    // 2. Determine options
    $isForce = !empty($options['force']);
    $center = $options['center'] ?? $cronConfig['center_code'] ?? 'all';
    if (!in_array($center, ['474', '476', 'all'], true)) $center = 'all';

    $action = $options['action'] ?? 'run';
    if ($action === 'reset') {
        $cronConfig['known_old_data']        = '';
        $cronConfig['last_published_cutoff'] = '';
        $cronConfig['last_published_date']   = '';
        $cronConfig['last_status']           = $cronConfig['enabled'] ? 'ACTIVE' : 'DISABLED';
        $cronConfig['last_message']          = 'KNOWN_OLD_DATA reset by administrator.';
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

        $cbtStatus['status'] = 'RESET';
        @file_put_contents($statusFile, json_encode($cbtStatus, JSON_PRETTY_PRINT));

        $resetMsg = '[RESET] KNOWN_OLD_DATA reset. Scraper will process next run as new publication.';
        $log($resetMsg);

        return [
            'success' => true,
            'status'  => 'RESET',
            'logs'    => $logs,
            'output'  => $resetMsg,
            'config'  => $cronConfig,
            'metrics' => $cbtStatus
        ];
    }

    // Check enabled
    if (empty($cronConfig['enabled']) && !$isForce) {
        $msg = "[CRON DISABLED] CBT Result scraping is currently disabled in Admin Panel. Enable it or trigger 'Run Now / Force'.";
        $log($msg);
        $cronConfig['last_status'] = 'DISABLED';
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

        return [
            'success' => true,
            'status'  => 'DISABLED',
            'logs'    => $logs,
            'output'  => $msg,
            'config'  => $cronConfig,
            'metrics' => $cbtStatus
        ];
    }

    $cbtUrl = "https://nat-test.jp/contents/result/result-id-cbt.html";
    $log("[1/6 START] Checking NAT-TEST CBT results...");
    $log("  ➜ Target URL: {$cbtUrl}");
    $log("  ➜ Exam Center: " . ($center === 'all' ? 'All Centers (474 Dhaka, 476 Khulna)' : "Center {$center}"));

    // 3. Database connection
    $pdo = $passedPdo;
    if (!$pdo) {
        list($pdo, $dbErr) = getCbtCronDbConnection();
        if (!$pdo) {
            $log("  ⚠️ [DB CONNECTION WARNING] Could not establish standalone PDO connection: {$dbErr}");
        } else {
            $log("  ✓ [DATABASE] Standalone PDO connected successfully.");
        }
    } else {
        $log("  ✓ [DATABASE] Using active admin session database connection.");
    }

    // 4. Fetch Official CBT Page
    $html = fetchCbtUrl($cbtUrl);
    if (!$html) {
        $errMsg = "❌ [ERROR] Failed to reach official CBT result page on nat-test.jp. Network timeout or connection error.";
        $log($errMsg);

        $cronConfig['last_run']     = date('Y-m-d H:i:s');
        $cronConfig['last_status']  = 'ERROR';
        $cronConfig['last_message'] = 'Failed to reach official CBT result page on nat-test.jp.';
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

        return [
            'success' => false,
            'status'  => 'ERROR',
            'logs'    => $logs,
            'output'  => implode("\n", $logs),
            'config'  => $cronConfig,
            'metrics' => $cbtStatus
        ];
    }

    $log("  ✓ Successfully fetched official page (" . number_format(strlen($html)) . " bytes).");

    // 5. Parse <h3> cutoff date
    $dateRegex = '/<h3>([\s\S]*?)<span class="en">([\s\S]*?)<\/span><\/h3>/i';
    $cutoffText = '';
    $extractedDateStr = '';
    $cutoffDateYmd = '';

    if (preg_match($dateRegex, $html, $dateMatch)) {
        $cutoffText = trim(strip_tags($dateMatch[2])); // e.g. "For tests held through November 9, 2026 (Mon)"

        if (preg_match('/through\s+([A-Za-z]+)\s+(\d{1,2}),\s+(\d{4})/i', $cutoffText, $dParts)) {
            $monthName = $dParts[1];
            $dayNum = str_pad($dParts[2], 2, '0', STR_PAD_LEFT);
            $yearNum = $dParts[3];
            $extractedDateStr = "{$dayNum} {$monthName} {$yearNum}";

            $monthNum = date('m', strtotime("1 {$monthName} 2000"));
            $cutoffDateYmd = "{$yearNum}-{$monthNum}-{$dayNum}";
        }
    }

    $log("[2/6 CUTOFF CHECK]");
    $log("  ➜ Website Published Cutoff: " . ($cutoffText ?: 'Could not parse <h3> cutoff tag'));
    if ($cutoffDateYmd) {
        $log("  ➜ Parsed Standard Date: {$cutoffDateYmd} ({$extractedDateStr})");
    }

    $knownOldData = trim((string)($cronConfig['known_old_data'] ?? $cronConfig['last_published_cutoff'] ?? ''));
    $log("  ➜ Current KNOWN_OLD_DATA:  " . ($knownOldData ?: '(None set)'));

    // Check if website matches KNOWN_OLD_DATA
    $isMatch = (!empty($cutoffText) && !empty($knownOldData) && $cutoffText === $knownOldData);

    if ($isMatch && !$isForce) {
        $log("  ➜ Comparison: Website data MATCHES KNOWN_OLD_DATA.");
        $log("⏸ [WAITING] Results on nat-test.jp are NOT updated yet. Standing by for next weekly publication.");

        $cronConfig['last_run']     = date('Y-m-d H:i:s');
        $cronConfig['last_status']  = 'WAITING';
        $cronConfig['last_message'] = "Checked. Website cutoff matches KNOWN_OLD_DATA ({$cutoffText}). Not updated yet.";
        @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

        $cbtStatus['status']     = 'WAITING';
        $cbtStatus['last_check'] = date('Y-m-d H:i:s');
        @file_put_contents($statusFile, json_encode($cbtStatus, JSON_PRETTY_PRINT));

        return [
            'success' => true,
            'status'  => 'WAITING',
            'logs'    => $logs,
            'output'  => implode("\n", $logs),
            'config'  => $cronConfig,
            'metrics' => $cbtStatus
        ];
    }

    if ($isMatch && $isForce) {
        $log("  ➜ Comparison: Website matches KNOWN_OLD_DATA, but proceeding anyway because 'Run Now (Force)' was triggered.");
    } else {
        $log("  🚀 [NEW UPDATE DETECTED] Website published date changed from KNOWN_OLD_DATA!");
    }

    // 6. Extract Passed IDs from <div id="successful-examinee">
    $log("[3/6 EXTRACTING PASS EXAMINEES]");
    $startIndex = strpos($html, 'id="successful-examinee"');
    $targetChunk = ($startIndex !== false) ? substr($html, $startIndex) : $html;

    $centerRegex = ($center === 'all') ? '(?:474|476)' : preg_quote($center, '/');
    $idRegex = "/\b\d{6}{$centerRegex}[1-5]\d{4}\b/";

    preg_match_all($idRegex, $targetChunk, $idMatches);
    $passedIds = array_values(array_unique($idMatches[0] ?? []));
    $totalPassed = count($passedIds);

    if ($totalPassed === 0) {
        $log("  ⚠️ [WARNING] No passed examinees found for Center {$center} on the official result page.");
    } else {
        $passed474 = 0;
        $passed476 = 0;
        $levelCounts = ['N1' => 0, 'N2' => 0, 'N3' => 0, 'N4' => 0, 'N5' => 0];

        foreach ($passedIds as $pid) {
            $cen = substr($pid, 6, 3);
            if ($cen === '474') $passed474++;
            if ($cen === '476') $passed476++;

            $lvl = 'N' . substr($pid, 9, 1);
            if (isset($levelCounts[$lvl])) $levelCounts[$lvl]++;
        }

        $log("  ✓ Found {$totalPassed} passed examinee IDs:");
        $log("    • Dhaka Center (474): {$passed474} passed");
        $log("    • Khulna Center (476): {$passed476} passed");
        $log("    • Levels Breakdown: N5: {$levelCounts['N5']}, N4: {$levelCounts['N4']}, N3: {$levelCounts['N3']}, N2: {$levelCounts['N2']}, N1: {$levelCounts['N1']}");
    }

    // 7. Database Synchronization & Table Checks
    $log("[4/6 DATABASE SYNCHRONIZATION]");
    $totalPassedUpdated = 0;
    $totalFailedUpdated = 0;
    $totalDbExaminees = 0;
    $cntBooking = 0;
    $cbtTables = [];

    if (!$pdo) {
        $log("  ❌ [ERROR] No database connection available. Skipping database updates.");
    } else {
        // Table A: booking_completed (Seat Booking Portal table)
        try {
            $chkBooking = $pdo->query("SHOW TABLES LIKE 'booking_completed'")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($chkBooking)) {
                $cntBooking = (int)$pdo->query("SELECT COUNT(*) FROM `booking_completed`")->fetchColumn();
                $totalDbExaminees += $cntBooking;

                if ($cntBooking === 0) {
                    $log("  ℹ️ Table 'booking_completed' located, but currently contains 0 booking records (empty).");
                } else {
                    $log("  ✓ Table 'booking_completed' located ({$cntBooking} total booking records):");

                    // Update Pass
                    $bookPassCount = 0;
                    if ($totalPassed > 0) {
                        $chunks = array_chunk($passedIds, 100);
                        foreach ($chunks as $chunk) {
                            $ph = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $pdo->prepare("UPDATE `booking_completed` SET `status` = 'Pass' WHERE `examinee_number` IN ($ph) AND (`status` IS NULL OR TRIM(`status`) != 'Pass')");
                            $stmt->execute($chunk);
                            $bookPassCount += $stmt->rowCount();
                        }
                    }

                    // Update Fail
                    $bookFailCount = 0;
                    if (!empty($cutoffDateYmd)) {
                        $failSql = "UPDATE `booking_completed` SET `status` = 'Fail' WHERE `date_of_test` <= ? AND (`status` IS NULL OR TRIM(`status`) NOT IN ('Pass', 'Fail'))";
                        $failParams = [$cutoffDateYmd];

                        if ($center !== 'all') {
                            $siteName = ($center === '474') ? 'Dhaka Site 1' : 'Khulna';
                            $failSql .= " AND (`test_site` = ? OR `test_site` = ?)";
                            $failParams[] = $center;
                            $failParams[] = $siteName;
                        }

                        $failStmt = $pdo->prepare($failSql);
                        $failStmt->execute($failParams);
                        $bookFailCount += $failStmt->rowCount();
                    }

                    $totalPassedUpdated += $bookPassCount;
                    $totalFailedUpdated += $bookFailCount;
                    $log("    • Updated: {$bookPassCount} marked 'Pass', {$bookFailCount} marked 'Fail' (date_of_test <= {$cutoffDateYmd}).");
                }
            } else {
                $log("  ⚠️ [NOTICE] Table 'booking_completed' does not exist in the database (seat booking records not found).");
            }
        } catch (Exception $e) {
            $log("  ⚠️ [WARNING] Error checking 'booking_completed': " . $e->getMessage());
        }

        // Table B: Monthly CBT Tables (e.g. Nov_26_CBT, Oct_26_CBT)
        try {
            $stmtTables = $pdo->query("SHOW TABLES");
            $allTables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allTables as $t) {
                if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}_CBT$/i', $t)) {
                    $cbtTables[] = $t;
                    $cntTbl = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                    $totalDbExaminees += $cntTbl;
                    $log("  ✓ Monthly CBT table '{$t}' found with {$cntTbl} examinee record(s):");

                    $tblPass = 0;
                    $tblFail = 0;

                    if ($totalPassed > 0) {
                        $chunks = array_chunk($passedIds, 100);
                        foreach ($chunks as $chunk) {
                            $ph = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $pdo->prepare("UPDATE `$t` SET `status` = 'Pass' WHERE `examinee_number` IN ($ph) AND (`status` IS NULL OR TRIM(`status`) != 'Pass')");
                            $stmt->execute($chunk);
                            $tblPass += $stmt->rowCount();
                        }
                    }

                    if (!empty($cutoffDateYmd)) {
                        $cutoffYYMMDD = date('ymd', strtotime($cutoffDateYmd));
                        $failSql = "UPDATE `$t` SET `status` = 'Fail' WHERE (`status` IS NULL OR TRIM(`status`) NOT IN ('Pass', 'Fail')) AND CHAR_LENGTH(TRIM(`examinee_number`)) >= 14 AND SUBSTRING(TRIM(`examinee_number`), 1, 6) <= ?";
                        $failParams = [$cutoffYYMMDD];

                        if ($center !== 'all') {
                            $failSql .= " AND SUBSTRING(TRIM(`examinee_number`), 7, 3) = ?";
                            $failParams[] = $center;
                        }

                        $stmtFail = $pdo->prepare($failSql);
                        $stmtFail->execute($failParams);
                        $tblFail += $stmtFail->rowCount();
                    }

                    $totalPassedUpdated += $tblPass;
                    $totalFailedUpdated += $tblFail;
                    $log("    • Updated in [{$t}]: {$tblPass} marked 'Pass', {$tblFail} marked 'Fail'.");
                }
            }

            if (empty($cbtTables)) {
                $log("  ℹ️ [NOTICE] No monthly batch CBT tables (e.g. Nov_26_CBT) found in database.");
            }
        } catch (Exception $e) {
            $log("  ⚠️ [WARNING] Error checking monthly CBT tables: " . $e->getMessage());
        }

        // Table C: Fallback examinees table
        try {
            $chkEx = $pdo->query("SHOW TABLES LIKE 'examinees'")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($chkEx)) {
                $cntEx = (int)$pdo->query("SELECT COUNT(*) FROM `examinees`")->fetchColumn();
                if ($cntEx > 0) {
                    $totalDbExaminees += $cntEx;
                    if ($totalPassed > 0) {
                        $chunks = array_chunk($passedIds, 100);
                        $exPass = 0;
                        foreach ($chunks as $chunk) {
                            $ph = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $pdo->prepare("UPDATE `examinees` SET `status` = 'Pass' WHERE `examinee_number` IN ($ph) AND (`status` IS NULL OR TRIM(`status`) != 'Pass')");
                            $stmt->execute($chunk);
                            $exPass += $stmt->rowCount();
                        }
                        $totalPassedUpdated += $exPass;
                        $log("    • Table [examinees] ({$cntEx} records): {$exPass} marked 'Pass'.");
                    }
                }
            }
        } catch (Exception $e) {}

        // Diagnose Examinee Sync Results
        if ($totalDbExaminees === 0) {
            $log("");
            $log("  ⚠️⚠️⚠️ [IMPORTANT NOTICE: NO CBT EXAMINEE DATA IN DATABASE] ⚠️⚠️⚠️");
            $log("    • Official website published {$totalPassed} passed examinees for Center {$center}.");
            $log("    • But YOUR DATABASE CURRENTLY HAS 0 CBT EXAMINEE RECORDS:");
            $log("      - Table 'booking_completed': " . (empty($chkBooking) ? "Does not exist" : "Empty (0 records)"));
            $log("      - Monthly CBT batch tables (%_CBT): None found");
            $log("    • Because you have not added or imported CBT examinee info yet, NO database rows could be updated.");
            $log("    • How to resolve: Upload/Import your CBT examinee list in the Import section or allow candidates to register via the Seat Booking portal.");
            $log("");
        } elseif ($totalPassedUpdated === 0 && $totalFailedUpdated === 0) {
            $log("");
            $log("  ⚠️ [NOTICE: 0 MATCHES FOUND]");
            $log("    • Found {$totalPassed} passed examinees on nat-test.jp.");
            $log("    • However, NONE of the scraped examinee numbers matched the {$totalDbExaminees} examinee record(s) in your database.");
            $log("    • Please verify examinee roll numbers format (14 digits, e.g. 26110247450001).");
            $log("");
        } else {
            $log("  📊 Database Sync Summary: {$totalPassedUpdated} marked 'Pass', {$totalFailedUpdated} marked 'Fail' across {$totalDbExaminees} database records.");
        }
    }

    // 8. Email Notifications
    $log("[5/6 EMAIL NOTIFICATIONS]");
    if (!empty($cronConfig['email_notifications'])) {
        $displayDate = $extractedDateStr ?: ($cutoffText ?: date('Y-m-d'));
        $subject = "NAT-TEST CBT Result Published! (Through {$displayDate})";

        $dbNote = "";
        if ($totalDbExaminees === 0) {
            $dbNote = "\n\n⚠️ [সতর্কতা] আপনার ডাটাবেসে কোনো CBT পরীক্ষার্থীর রেকর্ড নেই (0 examinees)। কোনো ফলাফল আপডেট করা সম্ভব হয়নি। দয়া করে অ্যাডমিন প্যানেল থেকে পরীক্ষার্থীদের তালিকা আপলোড করুন।";
        } elseif ($totalPassedUpdated === 0 && $totalFailedUpdated === 0) {
            $dbNote = "\n\n⚠️ [বিজ্ঞপ্তি] অফিসিয়াল রেজাল্টের রোল নম্বরগুলোর সাথে ডাটাবেসের কোনো পরীক্ষার্থীর রোল নম্বর মেলেনি (Updated: 0)।";
        } else {
            $dbNote = "\n\n✓ ডেটাবেসে মোট {$totalPassedUpdated} জন 'Pass' এবং {$totalFailedUpdated} জন 'Fail' হিসেবে আপডেট সম্পন্ন হয়েছে।";
        }

        $adminBody = "Dear Admin,\n\n"
                   . "{$displayDate} পর্যন্ত অনুষ্ঠিত হওয়া সকল CBT পরীক্ষার্থীর পরীক্ষার ফলাফল প্রকাশ করা হয়েছে।\n\n"
                   . "অফিসিয়াল সাইটে উত্তীর্ণ: {$totalPassed} জন (ঢাকা: " . ($passed474 ?? 0) . ", খুলনা: " . ($passed476 ?? 0) . ")"
                   . $dbNote . "\n\n"
                   . "---\nNAT-TEST BD CBT System";

        $modBody = "Dear বুঝলামনা'র দল!,\n\n"
                 . "{$displayDate} পর্যন্ত অনুষ্ঠিত হওয়া সকল CBT পরীক্ষার্থীর পরীক্ষার ফলাফল প্রকাশ হয়ে গেছে!\n\n"
                 . "মোট উত্তীর্ণ: {$totalPassed} জন"
                 . $dbNote . "\n\n"
                 . "Admin থেকে অ্যাপ্রুভাল নিয়ে যত দ্রুত সম্ভব NAT-TEST BD তে ফলাফল প্রকাশের ব্যবস্থা করো।\n\n"
                 . "---\nNAT-TEST BD CBT System";

        if (!empty($cronConfig['admin_emails'])) {
            $adminRes = sendCbtNotificationEmails($cronConfig['admin_emails'], $subject, $adminBody);
            if ($adminRes['sent'] > 0) {
                $log("  ✓ Notification email dispatched to Admins ({$cronConfig['admin_emails']}).");
            } else {
                $errStr = !empty($adminRes['errors']) ? implode('; ', $adminRes['errors']) : 'delivery failed';
                $log("  ⚠️ [EMAIL NOTICE] Admin notification could not be delivered: {$errStr}");
            }
        } else {
            $log("  [INFO] No admin emails configured in cbt-cron-config.json.");
        }

        if (!empty($cronConfig['mod_emails'])) {
            $modRes = sendCbtNotificationEmails($cronConfig['mod_emails'], $subject, $modBody);
            if ($modRes['sent'] > 0) {
                $log("  ✓ Notification email dispatched to Moderators ({$cronConfig['mod_emails']}).");
            } else {
                $errStr = !empty($modRes['errors']) ? implode('; ', $modRes['errors']) : 'delivery failed';
                $log("  ⚠️ [EMAIL NOTICE] Moderator notification could not be delivered: {$errStr}");
            }
        } else {
            $log("  [INFO] No moderator emails configured in cbt-cron-config.json.");
        }
    } else {
        $log("  [INFO] Email notifications are disabled in cbt-cron-config.json.");
    }

    // 9. Update Config & History
    $log("[6/6 FINALIZING & UPDATING KNOWN_OLD_DATA]");
    if (!empty($cutoffText)) {
        $cronConfig['known_old_data']        = $cutoffText;
        $cronConfig['last_published_cutoff'] = $cutoffText;
        $cronConfig['last_published_date']   = $extractedDateStr;
        $log("  ✓ KNOWN_OLD_DATA updated to: '{$cutoffText}'.");
    }

    $cronConfig['last_run']     = date('Y-m-d H:i:s');
    $cronConfig['last_status']  = 'COMPLETED';

    if ($totalDbExaminees === 0) {
        $cronConfig['last_message'] = "Scraped cutoff {$cutoffText}. Official site has {$totalPassed} passed, but 0 CBT examinees found in database to update.";
    } elseif ($totalPassedUpdated === 0 && $totalFailedUpdated === 0) {
        $cronConfig['last_message'] = "Scraped cutoff {$cutoffText}. {$totalPassed} passed on website, but 0 matched existing {$totalDbExaminees} database records.";
    } else {
        $cronConfig['last_message'] = "Processed cutoff {$cutoffText}. Updated: {$totalPassedUpdated} Pass, {$totalFailedUpdated} Fail.";
    }

    @file_put_contents($configFile, json_encode($cronConfig, JSON_PRETTY_PRINT));

    $cbtStatus['status']               = 'COMPLETED';
    $cbtStatus['last_check']           = date('Y-m-d H:i:s');
    $cbtStatus['last_cutoff']          = $cutoffText;
    $cbtStatus['total_passed']         = $totalPassed;
    $cbtStatus['total_passed_updated'] = $totalPassedUpdated;
    $cbtStatus['total_failed_updated'] = $totalFailedUpdated;
    $cbtStatus['total_updated']        = ($totalPassedUpdated + $totalFailedUpdated);

    if (!isset($cbtStatus['history']) || !is_array($cbtStatus['history'])) $cbtStatus['history'] = [];
    array_unshift($cbtStatus['history'], [
        'timestamp'      => date('Y-m-d H:i:s'),
        'cutoff'         => $cutoffText,
        'total_passed'   => $totalPassed,
        'passed_dhaka'   => $passed474 ?? 0,
        'passed_khulna'  => $passed476 ?? 0,
        'updated_pass'   => $totalPassedUpdated,
        'updated_fail'   => $totalFailedUpdated
    ]);
    $cbtStatus['history'] = array_slice($cbtStatus['history'], 0, 10);
    @file_put_contents($statusFile, json_encode($cbtStatus, JSON_PRETTY_PRINT));

    $log("✓ [SUCCESS] CBT result scraping and sync workflow completed.");

    return [
        'success'       => true,
        'status'        => 'COMPLETED',
        'logs'          => $logs,
        'output'        => implode("\n", $logs),
        'total_passed'  => $totalPassed,
        'updated_pass'  => $totalPassedUpdated,
        'updated_fail'  => $totalFailedUpdated,
        'config'        => $cronConfig,
        'metrics'       => $cbtStatus
    ];
}

// ─── Entry Point Execution (CLI vs Web) ───────────────────────
if ($isCli && !defined('ADMIN_INTERNAL_EXECUTION')) {
    $cliParams = [];
    if (isset($argv) && is_array($argv)) {
        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--force' || $arg === '-f' || $arg === 'force' || $arg === 'force=1') {
                $cliParams['force'] = true;
            } elseif ($arg === '--reset' || $arg === 'reset') {
                $cliParams['action'] = 'reset';
            } elseif (strpos($arg, '=') !== false) {
                list($k, $v) = explode('=', ltrim($arg, '-'), 2);
                $cliParams[strtolower(trim($k))] = trim($v);
            }
        }
    }

    $cliParams['on_log'] = function($msg) {
        echo $msg . PHP_EOL;
    };

    $result = executeCbtResultScraper($cliParams);
    exit($result['success'] ? 0 : 1);
}