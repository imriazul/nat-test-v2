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