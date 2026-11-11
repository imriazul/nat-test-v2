<?php
// Helper to safely parse .env files if not already loaded
if (!function_exists('loadEnv')) {
    function loadEnv($filePath) {
        if (!file_exists($filePath)) return;
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load root .env (contains DB_HOST, DB_NAME, GOOGLE_SHEET_ID_..., etc.)
$rootEnv = dirname(__DIR__) . '/.env';
if (file_exists($rootEnv)) {
    loadEnv($rootEnv);
}

// Start secure session with hardened cookie flags
if (!function_exists('startSecureUserSession')) {
    function startSecureUserSession() {
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
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}
startSecureUserSession();

// User session configuration
$user_session_name = 'user_portal_logged_in';
$user_portal_title = 'CBT Seat Checker Portal';

/**
 * Ensure portal tables exist without breaking the PDO connection if privileges are restricted
 */
function ensurePortalTablesExist(PDO $pdo): void {
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS `seat_bookings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `examinee_number` VARCHAR(30) NOT NULL UNIQUE,
            `user_id` INT NULL,
            `user_name` VARCHAR(255) NULL,
            `user_email` VARCHAR(255) NULL,
            `country_test` VARCHAR(100) NOT NULL DEFAULT 'BANGLADESH',
            `city_test` VARCHAR(100) NOT NULL,
            `test_site` VARCHAR(20) NOT NULL,
            `date_of_test` DATE NOT NULL,
            `time_slot` INT NOT NULL,
            `pc_number` INT NOT NULL,
            `degree_of_test` VARCHAR(10) NOT NULL,
            `test_fee` VARCHAR(50) NULL,
            `candidate_name` VARCHAR(255) NULL,
            `birthday` DATE NULL,
            `address` TEXT NULL,
            `phone_number` VARCHAR(50) NULL,
            `zipcode` VARCHAR(50) NULL,
            `country` VARCHAR(100) NULL,
            `email` VARCHAR(255) NULL,
            `personal_picture` LONGTEXT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'HOLDING',
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`examinee_number`),
            INDEX (`status`),
            INDEX (`expires_at`),
            INDEX (`test_site`, `date_of_test`, `time_slot`, `pc_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `booking_completed` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `examinee_number` VARCHAR(30) NOT NULL UNIQUE,
            `user_id` INT NULL,
            `user_name` VARCHAR(255) NULL,
            `user_email` VARCHAR(255) NULL,
            `country_test` VARCHAR(100) NOT NULL DEFAULT 'BANGLADESH',
            `city_test` VARCHAR(100) NOT NULL,
            `test_site` VARCHAR(20) NOT NULL,
            `date_of_test` DATE NOT NULL,
            `time_slot` INT NOT NULL,
            `pc_number` INT NOT NULL,
            `degree_of_test` VARCHAR(10) NOT NULL,
            `test_fee` VARCHAR(50) NULL,
            `candidate_name` VARCHAR(255) NOT NULL,
            `birthday` DATE NOT NULL,
            `address` TEXT NOT NULL,
            `phone_number` VARCHAR(50) NOT NULL,
            `zipcode` VARCHAR(50) NOT NULL,
            `country` VARCHAR(100) NOT NULL DEFAULT 'BANGLADESH',
            `email` VARCHAR(255) NOT NULL,
            `personal_picture` LONGTEXT NULL,
            `market_url` VARCHAR(500) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`examinee_number`),
            INDEX (`status`),
            INDEX (`test_site`, `date_of_test`, `time_slot`, `pc_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Log warning but never crash the active database connection
        error_log("[" . date('Y-m-d H:i:s') . "] ensurePortalTablesExist notice: " . $e->getMessage());
    }
}

/**
 * Get or initialize PDO connection for user portal
 *
 * @return PDO|null
 */
function getUserPortalPDO(): ?PDO {
    static $portalPdo = null;
    if ($portalPdo !== null) return $portalPdo;

    // 1. Check if global $pdo already established in root config
    global $pdo;
    if ($pdo instanceof PDO) {
        $portalPdo = $pdo;
        return $portalPdo;
    }

    $host   = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: '';
    $user   = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass   = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: '';
    $port   = (int)($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

    if (empty($dbname)) return null;

    $attempts = [];
    $attempts[] = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    if ($host === 'localhost') {
        $attempts[] = "mysql:host=127.0.0.1;port={$port};dbname={$dbname};charset=utf8mb4";
    }
    $commonSockets = ['/tmp/mysql.sock', '/var/mysql/mysql.sock', '/opt/homebrew/var/mysql/mysql.sock', '/Applications/MAMP/tmp/mysql/mysql.sock'];
    foreach ($commonSockets as $sock) {
        if (file_exists($sock)) {
            $attempts[] = "mysql:unix_socket={$sock};dbname={$dbname};charset=utf8mb4";
        }
    }

    $lastError = '';
    foreach ($attempts as $dsn) {
        try {
            $instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            ensurePortalTablesExist($instance);

            $portalPdo = $instance;
            return $portalPdo;
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
        }
    }

    error_log("[" . date('Y-m-d H:i:s') . "] User Portal PDO connection error: " . $lastError);
    return null;
}

/**
 * Verify user login using Email or Username and Password against database `users` table
 * Also supports admin credentials & emergency fallback.
 *
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'error' => string|null, 'user' => array|null]
 */
function verifyUserLogin(string $email, string $password): array {
    $identifier = trim($email);
    $identClean = strtolower($identifier);
    $passRaw    = (string)$password;
    $passClean  = trim($password);

    if (empty($identClean) || empty($passClean)) {
        return ['success' => false, 'error' => 'Please enter both your email address/username and password.'];
    }

    $dbChecked = false;

    // 1. Check MySQL `users` table
    $pdo = getUserPortalPDO();
    if ($pdo) {
        $dbChecked = true;
        try {
            // Support login by email OR by name/username OR by phone number
            $identPhone = preg_replace('/[^\d+]/', '', $identifier);
            if (!empty($identPhone)) {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE LOWER(email) = ? OR LOWER(name) = ? OR REPLACE(phone, ' ', '') = ? OR REPLACE(phone, '-', '') = ? LIMIT 1");
                $stmt->execute([$identClean, $identClean, $identPhone, $identPhone]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE LOWER(email) = ? OR LOWER(name) = ? LIMIT 1");
                $stmt->execute([$identClean, $identClean]);
            }
            $user = $stmt->fetch();

            if ($user) {
                // Check if account is active
                if (isset($user['status']) && $user['status'] !== 'active') {
                    return [
                        'success' => false,
                        'error'   => 'Your account is deactivated. Please contact the NAT-TEST BD administrator.'
                    ];
                }

                // Verify encrypted / hashed password with automatic legacy upgrade
                $passwordMatched = false;
                if (password_verify($passRaw, $user['password']) || password_verify($passClean, $user['password'])) {
                    $passwordMatched = true;
                    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                        $newHash = password_hash($passClean, PASSWORD_BCRYPT);
                        $rehashStmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                        $rehashStmt->execute([$newHash, $user['id']]);
                    }
                } elseif ($passRaw === $user['password'] || $passClean === $user['password']) {
                    // Legacy plain text match: immediately migrate to strong bcrypt hash
                    $passwordMatched = true;
                    $newHash = password_hash($passClean, PASSWORD_BCRYPT);
                    $rehashStmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                    $rehashStmt->execute([$newHash, $user['id']]);
                } elseif (hash_equals(strtolower($user['password']), md5($passClean)) || hash_equals(strtolower($user['password']), md5($passRaw))) {
                    // phpMyAdmin MD5 function match: immediately migrate to strong bcrypt hash
                    $passwordMatched = true;
                    $newHash = password_hash($passClean, PASSWORD_BCRYPT);
                    $rehashStmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                    $rehashStmt->execute([$newHash, $user['id']]);
                } elseif (hash_equals(strtolower($user['password']), sha1($passClean)) || hash_equals(strtolower($user['password']), sha1($passRaw))) {
                    // phpMyAdmin SHA1 match: immediately migrate to strong bcrypt hash
                    $passwordMatched = true;
                    $newHash = password_hash($passClean, PASSWORD_BCRYPT);
                    $rehashStmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                    $rehashStmt->execute([$newHash, $user['id']]);
                } elseif (strtoupper($user['password']) === ('*' . strtoupper(sha1(sha1($passClean, true))))) {
                    // MySQL PASSWORD() 41-char hash match: immediately migrate to strong bcrypt hash
                    $passwordMatched = true;
                    $newHash = password_hash($passClean, PASSWORD_BCRYPT);
                    $rehashStmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                    $rehashStmt->execute([$newHash, $user['id']]);
                }

                if ($passwordMatched) {
                    return [
                        'success' => true,
                        'user'    => [
                            'id'        => (int)$user['id'],
                            'name'      => $user['name'],
                            'email'     => $user['email'],
                            'phone'     => $user['phone'] ?? '',
                            'institute' => $user['institute'] ?? '',
                            'role'      => 'user'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error'   => 'Incorrect password. Please verify the credentials provided by administration.'
                    ];
                }
            }
        } catch (PDOException $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] verifyUserLogin DB query error: " . $e->getMessage());
        }
    }

    // 2. Admin fallback: administrator can log into user portal with admin credentials
    $adminEnv = dirname(__DIR__) . '/admin/.env';
    $admUser = $_ENV['ADMIN_USER'] ?? '';
    $admPass = $_ENV['ADMIN_PASS'] ?? '';

    if (empty($admUser) || empty($admPass)) {
        if (file_exists($adminEnv)) {
            loadEnv($adminEnv);
            $admUser = $_ENV['ADMIN_USER'] ?? '';
            $admPass = $_ENV['ADMIN_PASS'] ?? '';
        }
    }

    if (!empty($admUser) && !empty($admPass)) {
        $admUserClean = strtolower($admUser);
        $isAdminIdentifier = ($identClean === $admUserClean)
                          || ($identClean === 'admin@nat-test.bd')
                          || ($identClean === 'admin')
                          || (strpos($identClean, 'admin@') === 0);

        $isAdminPass = ($passRaw === $admPass)
                    || ($passClean === $admPass)
                    || password_verify($passRaw, $admPass)
                    || password_verify($passClean, $admPass);

        if ($isAdminIdentifier && $isAdminPass) {
            return [
                'success' => true,
                'user'    => [
                    'id'        => 0,
                    'name'      => 'System Administrator',
                    'email'     => 'admin@nat-test.bd',
                    'phone'     => '',
                    'institute' => 'NAT-TEST BD Administration',
                    'role'      => 'admin'
                ]
            ];
        }
    }

    // If database was down and credentials were not admin fallback, give clear guidance
    if (!$dbChecked) {
        return [
            'success' => false,
            'error'   => 'Database connection unavailable. If you are an administrator, please sign in with admin credentials, or check MySQL server status.'
        ];
    }

    return [
        'success' => false,
        'error'   => 'Invalid email or password. Please verify your credentials and try again.'
    ];
}

/**
 * Send Examinee OTP Verification Email via dedicated SMTP (mail.nat-test.bd:465 SSL)
 *
 * @param string $toEmail Recipient candidate email address
 * @param string $otpCode 6-digit numeric verification code
 * @return array ['success' => bool, 'error' => string]
 */
function sendExamineeVerificationEmail(string $toEmail, string $otpCode): array {
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid candidate email address format.'];
    }

    $host     = trim($_ENV['VERIFY_SMTP_HOST'] ?? 'mail.nat-test.bd');
    $port     = (int)($_ENV['VERIFY_SMTP_PORT'] ?? 465);
    $secure   = strtolower(trim($_ENV['VERIFY_SMTP_SECURE'] ?? 'ssl'));
    $user     = trim($_ENV['VERIFY_SMTP_USER'] ?? 'verify@nat-test.bd');
    $pass     = trim($_ENV['VERIFY_SMTP_PASS'] ?? '');
    $from     = trim($_ENV['VERIFY_SMTP_FROM_EMAIL'] ?? 'verify@nat-test.bd');
    $fromName = trim($_ENV['VERIFY_SMTP_FROM_NAME'] ?? 'NAT-TEST BD Verification');
    $replyTo  = trim($_ENV['VERIFY_SMTP_REPLY_TO'] ?? 'support@nat-test.bd');

    if (empty($host) || empty($user) || empty($pass)) {
        $errMsg = 'SMTP configuration incomplete. Please verify VERIFY_SMTP_HOST, USER, and PASS in .env.';
        error_log("[Examinee Verification] " . $errMsg);
        return ['success' => false, 'error' => $errMsg];
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

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client("$socketHost:$port", $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $errMsg = "SMTP connection to $socketHost:$port failed: $errstr ($errno)";
        error_log("[Examinee Verification] " . $errMsg);
        return ['success' => false, 'error' => 'Could not connect to mail server. Please try again shortly.'];
    }

    stream_set_timeout($socket, 12);

    $readResponse = function() use ($socket) {
        $response = "";
        while (!feof($socket) && ($line = fgets($socket, 515))) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $sendCommand = function(string $cmd) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse();
    };

    $banner = $readResponse();
    if (substr($banner, 0, 3) !== '220') {
        fclose($socket);
        error_log("[Examinee Verification] Unexpected SMTP banner: " . trim($banner));
        return ['success' => false, 'error' => 'Mail server returned unexpected banner.'];
    }

    $serverIdentifier = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $helo = $sendCommand("EHLO $serverIdentifier");

    // STARTTLS if configured
    if ($secure === 'tls' || ($port === 587 && stripos($helo, 'STARTTLS') !== false)) {
        $sendCommand("STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $sendCommand("EHLO $serverIdentifier");
    }

    // Authenticate (AUTH LOGIN)
    $authResp = $sendCommand("AUTH LOGIN");
    if (substr($authResp, 0, 3) !== '334') {
        fclose($socket);
        error_log("[Examinee Verification] AUTH LOGIN rejected: " . trim($authResp));
        return ['success' => false, 'error' => 'Mail server authentication initiation failed.'];
    }

    $userResp = $sendCommand(base64_encode($user));
    if (substr($userResp, 0, 3) !== '334') {
        fclose($socket);
        error_log("[Examinee Verification] SMTP User rejected: " . trim($userResp));
        return ['success' => false, 'error' => 'Mail server rejected sender username.'];
    }

    $passResp = $sendCommand(base64_encode($pass));
    if (substr($passResp, 0, 3) !== '235') {
        fclose($socket);
        error_log("[Examinee Verification] SMTP Pass rejected: " . trim($passResp));
        return ['success' => false, 'error' => 'Mail server authentication failed. Please verify email password.'];
    }

    // Envelope
    $mailFrom = $sendCommand("MAIL FROM: <$from>");
    if (substr($mailFrom, 0, 3) !== '250') {
        fclose($socket);
        error_log("[Examinee Verification] MAIL FROM rejected: " . trim($mailFrom));
        return ['success' => false, 'error' => 'Mail server rejected envelope sender.'];
    }

    $rcptTo = $sendCommand("RCPT TO: <$toEmail>");
    if (substr($rcptTo, 0, 3) !== '250') {
        fclose($socket);
        error_log("[Examinee Verification] RCPT TO rejected for $toEmail: " . trim($rcptTo));
        return ['success' => false, 'error' => "Recipient email address was rejected by mail server ($toEmail)."];
    }

    $dataCmd = $sendCommand("DATA");
    if (substr($dataCmd, 0, 3) !== '354') {
        fclose($socket);
        error_log("[Examinee Verification] DATA command rejected: " . trim($dataCmd));
        return ['success' => false, 'error' => 'Mail server failed to accept email payload.'];
    }

    // Build Email Body
    $subject = "Your NAT-TEST Verification Code: $otpCode";
    $boundary = "----=_Part_" . md5(uniqid((string)mt_rand(), true));

    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
    $headers .= "Reply-To: <$replyTo>\r\n";
    $headers .= "Return-Path: <$from>\r\n";
    $headers .= "To: <$toEmail>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "X-Mailer: NAT-TEST Portal Examinee Verifier\r\n\r\n";

    $plainText = "NAT-TEST BD - Examinee Email Verification\r\n\r\n"
               . "Your verification code is: $otpCode\r\n\r\n"
               . "This code is valid for 10 minutes.\r\n"
               . "Please enter this code on the registration page to confirm your examinee email address.\r\n\r\n"
               . "If you did not request this verification, you may safely ignore this email.\r\n\r\n"
               . "NAT-TEST Bangladesh Management";

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
</head>
<body style="margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 520px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);">
        <tr>
            <td style="padding: 28px 28px 20px 28px; text-align: center; border-bottom: 1px solid #334155; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div style="font-size: 20px; font-weight: 900; letter-spacing: 1px; color: #38bdf8;">NAT-TEST BANGLADESH</div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Official Examinee Email Verification</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px 28px 24px 28px;">
                <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #cbd5e1;">
                    Hello Examinee,
                </p>
                <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #cbd5e1;">
                    A request was submitted on the NAT-TEST BD to verify this email address for CBT NAT-TEST registration. Please use the verification code below:
                </p>
                <div style="background-color: #0f172a; border: 2px dashed #38bdf8; border-radius: 12px; padding: 20px; text-align: center; margin: 0 0 24px 0;">
                    <span style="font-family: \'Courier New\', Courier, monospace; font-size: 34px; font-weight: 900; letter-spacing: 8px; color: #38bdf8; display: inline-block;">' . htmlspecialchars($otpCode) . '</span>
                </div>
                <p style="margin: 0 0 12px 0; font-size: 13px; color: #f59e0b; text-align: center; font-weight: 600;">
                    ⏳ This code is valid for 10 minutes.
                </p>
                <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #94a3b8; text-align: center;">
                    If you did not make this request or are not registering for NAT-TEST, you can safely disregard this email.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 28px; background-color: #0f172a; border-top: 1px solid #334155; text-align: center; font-size: 11px; color: #64748b;">
                &copy; ' . date('Y') . ' NAT-TEST BD. All rights reserved.<br>
                This is an automated verification email sent via verify@nat-test.bd.
            </td>
        </tr>
    </table>
</body>
</html>';

    $payload  = "--$boundary\r\n";
    $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $payload .= chunk_split(base64_encode($plainText)) . "\r\n";

    $payload .= "--$boundary\r\n";
    $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
    $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $payload .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $payload .= "--$boundary--\r\n";

    fputs($socket, $headers . $payload . "\r\n.\r\n");
    $sendResp = $readResponse();

    $sendCommand("QUIT");
    fclose($socket);

    if (substr($sendResp, 0, 3) === '250') {
        return ['success' => true, 'error' => ''];
    }

    $errMsg = "SMTP payload sending failed: " . trim($sendResp);
    error_log("[Examinee Verification] " . $errMsg);
    return ['success' => false, 'error' => 'Mail delivery failed. Please check candidate email address and try again.'];
}