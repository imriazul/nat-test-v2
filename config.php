<?php
// Enable comprehensive error reporting and file-based logging
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Do not expose raw errors directly to visitors in production
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Set error log destination file in root directory
$logFilePath = __DIR__ . '/error.log';
ini_set('error_log', $logFilePath);

// Custom error & exception handlers to ensure complete details (file, line, stack trace) are logged
set_exception_handler(function (Throwable $e) use ($logFilePath) {
    $entry = sprintf(
        "[%s] Uncaught Exception: %s in %s:%d\nStack trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($entry, 3, $logFilePath);
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($logFilePath) {
    // Respect error_reporting mask
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $typeStr = match ($errno) {
        E_ERROR, E_USER_ERROR => 'Fatal Error',
        E_WARNING, E_USER_WARNING => 'Warning',
        E_NOTICE, E_USER_NOTICE => 'Notice',
        E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
        default => 'Error (' . $errno . ')'
    };
    $entry = sprintf(
        "[%s] PHP %s: %s in %s on line %d\n",
        date('Y-m-d H:i:s'),
        $typeStr,
        $errstr,
        $errfile,
        $errline
    );
    error_log($entry, 3, $logFilePath);
    return false; // let standard internal handler proceed if needed
});
// Function to load .env variables manually
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die(".env file not found.");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            // Set environment variable
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load the .env file from the current directory
loadEnv(__DIR__ . '/.env');

// Fetch credentials from the environment
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

// Establish PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ensure users table exists for user portal authentication
    createUsersTableIfNotExists($pdo);
} catch(PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Database Connection Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // If request is an AJAX or API request, return structured JSON
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
           || (isset($_GET['action']) || isset($_POST['action']));
    
    if ($isAjax) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'error' => 'Database connection failed: ' . $e->getMessage(),
            'code'  => $e->getCode()
        ]);
        exit;
    }

    die("Database connection failed. Please check error.log for details.");
}

// ─── Month Mappings ──────────────────────────────────────────
$MONTH_MAP = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
    '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
    '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec'
];

$MONTH_FULL = [
    '01' => 'January',   '02' => 'February', '03' => 'March',
    '04' => 'April',     '05' => 'May',      '06' => 'June',
    '07' => 'July',      '08' => 'August',   '09' => 'September',
    '10' => 'October',   '11' => 'November', '12' => 'December'
];

/**
 * Parse a 14-digit examinee number.
 * Format: YYMMDDCCCLXXXX
 *   YY   = Year (e.g. 26 = 2026)
 *   MM   = Month (e.g. 08 = August)
 *   DD   = Exam day / date type (e.g. 00)
 *   CCC  = Exam Center code (e.g. 474 = ULSAI Dhaka)
 *   L    = Level (e.g. 5 = Q5)
 *   XXXX = Roll number
 */
function parseExamineeNumber($number) {
    $number = preg_replace('/[^0-9]/', '', trim($number));
    if (strlen($number) < 14) return null;

    $centerCode = substr($number, 6, 3);

    return [
        'year'        => substr($number, 0, 2),
        'month'       => substr($number, 2, 2),
        'exam_type'   => substr($number, 4, 2),
        'center_code' => $centerCode,
        'site_code'   => $centerCode, // Alias for backward compatibility
        'level'       => substr($number, 9, 1),
        'roll'        => substr($number, 10, 4),
        'raw'         => $number
    ];
}

/**
 * Exam Centers Map (CCC: 3-digit center code in examinee number)
 */
$EXAM_CENTERS = [
    '474' => [
        'name'     => 'ULSAI (Dhaka)',
        'site'     => 'Dhaka Site 1',
    ],
    '476' => [
        'name'     => 'Khulna Center', 
        'site'     => 'Khulna', 
    ],
];

/**
 * Get Exam Center details from center code (CCC).
 * Default fallback is 474: ULSAI (Dhaka)
 */
function getExamCenterInfo($centerCode) {
    global $EXAM_CENTERS;
    $centerCode = trim((string)$centerCode);
    if (isset($EXAM_CENTERS[$centerCode])) {
        $info = $EXAM_CENTERS[$centerCode];
        $info['code'] = $centerCode;
        return $info;
    }
    return [
        'name'     => 'ULSAI (Dhaka)',
        'site'     => 'Dhaka Site 1',
        'code'     => '474',
    ];
}

/**
 * Get table name from month and year codes (and optional exam type).
 * e.g., ('08', '26', '00') => 'Aug_26_PBT'
 *       ('08', '26', '12') => 'Aug_26_CBT'
 *       ('08', '26')       => 'Aug_26' (legacy)
 */
function getTableName($monthCode, $yearCode, $examType = null) {
    global $MONTH_MAP;
    $monthName = $MONTH_MAP[$monthCode] ?? null;
    if (!$monthName) return null;
    if ($examType !== null) {
        $examType = trim((string)$examType);
        $typeSuffix = ($examType === '00' || $examType === '0' || $examType === '') ? 'PBT' : 'CBT';
        return $monthName . '_' . $yearCode . '_' . $typeSuffix;
    }
    return $monthName . '_' . $yearCode;
}

/**
 * Create a month-wise examinee table if it doesn't exist.
 */
function createMonthTable($pdo, $tableName) {
    if (!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_\d{2}(_(CBT|PBT))?$/', $tableName)) {
        throw new Exception("Invalid table name format: $tableName");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `$tableName` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        examinee_number VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        dob DATE NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Create users table for user portal authentication if it doesn't exist.
 */
function createUsersTableIfNotExists($pdo) {
    if (!$pdo) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `phone` VARCHAR(50) NULL,
            `institute` VARCHAR(255) NULL,
            `password` VARCHAR(255) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`email`),
            INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] createUsersTableIfNotExists Error: " . $e->getMessage());
    }
}

/**
 * Get all month tables with record counts and pass/fail stats.
 */
function getMonthTables($pdo) {
    $tables = [];

    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($allTables as $table) {
        if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)_(\d{2})(?:_(CBT|PBT))?$/', $table, $m)) {
            try {
                $countStmt = $pdo->query("SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN status='Fail' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status='Pending' OR status IS NULL OR (status != 'Pass' AND status != 'Fail') THEN 1 ELSE 0 END) as pending
                    FROM `$table`");
                $s = $countStmt->fetch(PDO::FETCH_ASSOC);
                $tables[] = [
                    'name'    => $table,
                    'month'   => $m[1],
                    'year'    => $m[2],
                    'type'    => $m[3] ?? '',
                    'total'   => (int)($s['total']   ?? 0),
                    'passed'  => (int)($s['passed']  ?? 0),
                    'failed'  => (int)($s['failed']  ?? 0),
                    'pending' => (int)($s['pending'] ?? 0),
                ];
            } catch (Exception $e) { /* skip */ }
        }
    }

    return $tables;
}

/**
 * Mask an email address (e.g., "john.doe@gmail.com" => "j***e@g***l.com")
 */
function maskEmail($email) {
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return '***@***.com';
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];

    // Mask name
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '***';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
    }

    // Mask domain
    $domainParts = explode('.', $domain);
    $dName = $domainParts[0];
    $tld = isset($domainParts[1]) ? implode('.', array_slice($domainParts, 1)) : 'com';
    $dLen = strlen($dName);
    if ($dLen <= 2) {
        $maskedDomain = substr($dName, 0, 1) . '***';
    } else {
        $maskedDomain = substr($dName, 0, 1) . str_repeat('*', min(3, $dLen - 2)) . substr($dName, -1);
    }

    return $maskedName . '@' . $maskedDomain . '.' . $tld;
}

/**
 * Mask phone number (e.g., "+8801712345678" => "+88017****5678")
 */
function maskPhone($phone) {
    if (!$phone) return '***';
    $len = strlen($phone);
    return substr($phone, 0, 5) . str_repeat('*', max(3, $len - 8)) . substr($phone, -3);
}

// ─── Resend Email Client (Native cURL - Zero Dependencies) ─────────
// Support Composer autoload if present in the future
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * @param string|array $toEmail Recipient email address or array of emails
 * @param string       $subject Email subject line
 * @param string       $htmlBody HTML email content
 * @param string       $textBody Plain text alternative (optional)
 * @return bool True if accepted and dispatched by Resend, false on failure
 */
function sendResendEmail($toEmail, $subject, $htmlBody, $textBody = '') {
    $apiKey = trim($_ENV['RESEND_API_KEY'] ?? '');
    if (empty($apiKey) || $apiKey === 're_your_api_key_here') {
        $msg = "Resend API Key is missing or unconfigured in .env (RESEND_API_KEY)";
        $GLOBALS['smtp_last_error'] = $msg;
        error_log("[" . date('Y-m-d H:i:s') . "] $msg");
        return false;
    }

    $fromEmail = trim($_ENV['RESEND_FROM_EMAIL'] ?? 'verify@nat-test.bd');
    $fromName  = trim($_ENV['RESEND_FROM_NAME']  ?? 'NAT-TEST BD');
    $replyTo   = trim($_ENV['RESEND_REPLY_TO']   ?? 'support@nat-test.bd');

    // Build RFC 2822 "From: Name <email>" header
    if (strpos($fromEmail, '<') !== false && strpos($fromEmail, '>') !== false) {
        $from = $fromEmail;
    } elseif (!empty($fromName)) {
        $from = "{$fromName} <{$fromEmail}>";
    } else {
        $from = $fromEmail;
    }

    // Prepare recipient list (Resend API requires an array of strings)
    if (is_array($toEmail)) {
        $recipients = array_values(array_filter(array_map('trim', $toEmail)));
    } else {
        $recipients = [trim($toEmail)];
    }

    $payload = [
        'from'    => $from,
        'to'      => $recipients,
        'subject' => $subject,
        'html'    => $htmlBody,
        'text'    => !empty($textBody) ? $textBody : strip_tags($htmlBody),
    ];

    if (!empty($replyTo)) {
        $payload['reply_to'] = $replyTo;
    }

    $jsonPayload = json_encode($payload);

    // Primary: Native cURL execution
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: NAT-TEST-cURL-Mailer/1.0',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $msg = "Resend cURL connection error: " . $curlError;
            $GLOBALS['smtp_last_error'] = $msg;
            error_log("[" . date('Y-m-d H:i:s') . "] $msg");
            return false;
        }
    } else {
        // Fallback using PHP stream wrappers if curl extension is disabled
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Authorization: Bearer {$apiKey}\r\n" .
                                   "Content-Type: application/json\r\n" .
                                   "User-Agent: NAT-TEST-cURL-Mailer/1.0\r\n",
                'content'       => $jsonPayload,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
            ]
        ]);

        $response = @file_get_contents('https://api.resend.com/emails', false, $context);
        $httpCode = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            if (preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match)) {
                $httpCode = (int)$match[1];
            }
        }
    }

    $result = json_decode((string)$response, true) ?: [];

    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['id'])) {
        $GLOBALS['resend_last_id'] = $result['id'];
        $GLOBALS['smtp_last_error'] = null;
        return true;
    }

    // Extract detailed error description from Resend response
    $errorMsg = $result['message'] ?? ($result['error'] ?? ("HTTP status {$httpCode}: " . substr((string)$response, 0, 250)));
    $GLOBALS['smtp_last_error'] = "Resend API Error ({$httpCode}): " . $errorMsg;
    error_log("[" . date('Y-m-d H:i:s') . "] Resend send error ({$httpCode}): " . $errorMsg);
    return false;
}

// Built-in Resend SDK Wrapper (Allows $resend = Resend::client(...) syntax without Composer)
if (!class_exists('Resend')) {
    class Resend {
        public static function client(string $apiKey) {
            return new class($apiKey) {
                public $emails;
                public function __construct(string $apiKey) {
                    $this->emails = new class($apiKey) {
                        private string $apiKey;
                        public function __construct(string $apiKey) {
                            $this->apiKey = $apiKey;
                        }
                        public function send(array $params) {
                            $to = $params['to'] ?? '';
                            $subject = $params['subject'] ?? '';
                            $html = $params['html'] ?? '';
                            $text = $params['text'] ?? '';
                            $sent = sendResendEmail($to, $subject, $html, $text);
                            if (!$sent) {
                                throw new Exception($GLOBALS['smtp_last_error'] ?? 'Resend send failed');
                            }
                            return (object)['id' => $GLOBALS['resend_last_id'] ?? uniqid('resend_')];
                        }
                    };
                }
            };
        }
    }
}

/**
 * Send email via SMTP (Supports SSL port 465, TLS/STARTTLS port 587, and standard port 25)
 */
function sendSmtpEmail($toEmail, $subject, $htmlBody, $textBody = '') {
    $host     = $_ENV['SMTP_HOST'] ?? '';
    $port     = (int)($_ENV['SMTP_PORT'] ?? 465);
    $secure   = strtolower($_ENV['SMTP_SECURE'] ?? 'ssl'); // 'ssl', 'tls', or ''
    $user     = $_ENV['SMTP_USER'] ?? '';
    $pass     = $_ENV['SMTP_PASS'] ?? '';
    $from         = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@nat-test.bd';
    $fromName     = $_ENV['SMTP_FROM_NAME'] ?? 'NAT-TEST Management';
    $envelopeFrom = $_ENV['SMTP_ENVELOPE_FROM'] ?? (!empty($user) && filter_var($user, FILTER_VALIDATE_EMAIL) ? $user : $from);
    $replyTo      = $_ENV['SMTP_REPLY_TO'] ?? 'support@nat-test.bd';

    $logError = function($msg) {
        $GLOBALS['smtp_last_error'] = $msg;
        error_log("[" . date('Y-m-d H:i:s') . "] $msg");
    };

    if (empty($host) || empty($user) || empty($pass)) {
        $logError("SMTP Configuration missing in .env (host, user, or pass is empty)");
        return false;
    }

    $socketHost = ($secure === 'ssl') ? "ssl://$host" : $host;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'SNI_enabled'       => true,
            'peer_name'         => $host,
        ]
    ]);

    $socket = @stream_socket_client("$socketHost:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $logError("SMTP connection failed to $socketHost:$port — $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, 15);

    $readResponse = function() use ($socket) {
        $response = "";
        while (!feof($socket) && ($str = fgets($socket, 515))) {
            $response .= $str;
            if (isset($str[3]) && $str[3] === " ") break;
        }
        return $response;
    };

    $sendCommand = function($cmd) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse();
    };

    $initial = $readResponse();
    if (substr($initial, 0, 3) !== "220") {
        $logError("SMTP Unexpected banner: " . trim($initial));
        fclose($socket);
        return false;
    }

    $helo = $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

    // Handle STARTTLS for port 587
    if ($secure === 'tls' || ($port === 587 && stripos($helo, 'STARTTLS') !== false)) {
        $sendCommand("STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    }

    // Authenticate (AUTH LOGIN)
    $authResp = $sendCommand("AUTH LOGIN");
    if (substr($authResp, 0, 3) !== "334") {
        $logError("SMTP AUTH LOGIN rejected: " . trim($authResp));
        fclose($socket);
        return false;
    }

    $userResp = $sendCommand(base64_encode($user));
    if (substr($userResp, 0, 3) !== "334") {
        $logError("SMTP Username rejected: " . trim($userResp));
        fclose($socket);
        return false;
    }

    $passResp = $sendCommand(base64_encode($pass));
    if (substr($passResp, 0, 3) !== "235") {
        $logError("SMTP Authentication failed: " . trim($passResp));
        fclose($socket);
        return false;
    }

    // Envelope
    $mailFrom = $sendCommand("MAIL FROM: <$envelopeFrom>");
    if (substr($mailFrom, 0, 3) !== "250") {
        $logError("SMTP MAIL FROM failed: " . trim($mailFrom));
        fclose($socket);
        return false;
    }

    $rcptTo = $sendCommand("RCPT TO: <$toEmail>");
    if (substr($rcptTo, 0, 3) !== "250") {
        $logError("SMTP RCPT TO failed: " . trim($rcptTo));
        fclose($socket);
        return false;
    }

    $dataCmd = $sendCommand("DATA");
    if (substr($dataCmd, 0, 3) !== "354") {
        $logError("SMTP DATA rejected: " . trim($dataCmd));
        fclose($socket);
        return false;
    }

    // Headers & Body
    $boundary = "----=_Part_" . md5(uniqid());
    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
    $headers .= "Reply-To: <$replyTo>\r\n";
    $headers .= "Return-Path: <$envelopeFrom>\r\n";
    $headers .= "To: <$toEmail>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "X-Mailer: NAT-TEST Portal Mailer\r\n\r\n";

    $content  = "--$boundary\r\n";
    $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $content .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $content .= chunk_split(base64_encode($textBody ?: strip_tags($htmlBody))) . "\r\n";

    $content .= "--$boundary\r\n";
    $content .= "Content-Type: text/html; charset=UTF-8\r\n";
    $content .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $content .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $content .= "--$boundary--\r\n";

    fputs($socket, $headers . $content . "\r\n.\r\n");
    $sendResp = $readResponse();

    $sendCommand("QUIT");
    fclose($socket);

    if (substr($sendResp, 0, 3) === "250") {
        return true;
    } else {
        $logError("SMTP Message sending failed: " . trim($sendResp));
        return false;
    }
}

/**
 * Send OTP verification email (Supports Resend API with SMTP fallback)
 */
function sendOtpEmail($toEmail, $otpCode, $examineeNumber) {
    $subject = "Your NAT-TEST Verification Code: $otpCode";

    // Beautiful HTML template
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #f8fafc; padding: 30px 10px; margin: 0;">
        <div style="max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="background: #0f172a; padding: 24px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.5px;">NAT-TEST Result Portal</h1>
                <p style="color: #94a3b8; margin: 4px 0 0 0; font-size: 13px;">Official Examination Identity Verification</p>
            </div>
            <div style="padding: 32px 24px; text-align: center;">
                <p style="color: #475569; font-size: 14px; margin-top: 0;">Use Your Verification Code to show your detailed result of Examinee Number: <strong>' . htmlspecialchars($examineeNumber) . '</strong></p>
                <div style="margin: 24px 0; background: #f1f5f9; border-radius: 12px; padding: 20px; border: 1px dashed #cbd5e1;">
                    <div style="font-size: 12px; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: 1px; margin-bottom: 6px;">Your 6-Digit Code</div>
                    <div style="font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #2563eb; font-family: monospace;">' . htmlspecialchars($otpCode) . '</div>
                </div>
                <p style="color: #64748b; font-size: 13px; line-height: 1.5; margin: 0 0 16px 0;">This verification code is valid for <strong>10 minutes</strong>. Do not share this code with anyone.</p>
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">If you did not make this request, please safely ignore this email.</p>
            </div>
            <div style="background: #f8fafc; padding: 16px; text-align: center; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 12px;">
                Copyright &copy; ' . date('Y') . ' NAT-TEST BD. All rights reserved.
            </div>
        </div>
    </body>
    </html>';

    $textBody = "NAT-TEST Result Portal\r\n\r\n"
              . "Your 6-digit verification code for Examinee #" . $examineeNumber . " is:\r\n"
              . ">>> " . $otpCode . " <<<\r\n\r\n"
              . "Valid for 10 minutes. If you did not make this request, please disregard.\r\n";

    // 1. If Resend is configured, prioritize Resend
    if (!empty($_ENV['RESEND_API_KEY']) && $_ENV['RESEND_API_KEY'] !== 're_your_api_key_here') {
        $sent = sendResendEmail($toEmail, $subject, $htmlBody, $textBody);
        if ($sent) {
            return true;
        }
        // If Resend fails, attempt SMTP fallback if SMTP credentials exist
        if (!empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_USER']) && !empty($_ENV['SMTP_PASS'])) {
            $smtpSent = sendSmtpEmail($toEmail, $subject, $htmlBody, $textBody);
            if ($smtpSent) {
                return true;
            }
        }
        return false;
    }

    // 2. Otherwise attempt standard SMTP
    if (!empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_USER']) && !empty($_ENV['SMTP_PASS'])) {
        $sent = sendSmtpEmail($toEmail, $subject, $htmlBody, $textBody);
        if ($sent) {
            return true;
        }
    }

    // 3. Fallback to PHP mail() only if SMTP/Resend aren't configured AND mail() function is available
    if (empty($_ENV['SMTP_HOST']) && empty($_ENV['RESEND_API_KEY'])) {
        if (function_exists('mail')) {
            $headers = "From: " . ($_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@nat-test.bd') . "\r\n" .
                       "Reply-To: " . ($_ENV['SMTP_REPLY_TO'] ?? 'support@nat-test.bd') . "\r\n" .
                       "Content-Type: text/html; charset=UTF-8\r\n";
            $sent = @mail($toEmail, $subject, $htmlBody, $headers);
        } else {
            $msg = "PHP mail() is disabled on server and Resend / SMTP credentials are not configured in .env";
            $GLOBALS['smtp_last_error'] = $msg;
            error_log("[" . date('Y-m-d H:i:s') . "] Email delivery failed: $msg");
        }
    }

    return (bool)($sent ?? false);
}
?>