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

// Load admin configuration (DB connection, admin credentials & settings)
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
                // Redirect back to the requested page or admin root
                $redirectUrl = $_SERVER['REQUEST_URI'] ?? './';
                header("Location: " . $redirectUrl);
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

// Detect AJAX requests
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || isset($_GET['action'])
              || (isset($_POST['action']) && $_POST['action'] !== 'login');

if (!$isLoggedIn) {
    if ($isAjaxRequest) {
        header('Content-Type: application/json', true, 401);
        echo json_encode(['error' => 'Session expired. Please log in again.']);
        exit;
    }

    // Render standalone login page
    $cssFile = __DIR__ . '/../assets/css/style.min.css';
    $cssVer  = file_exists($cssFile) ? filemtime($cssFile) : '1';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
        <title>Admin Login | NAT-TEST BD</title>
        <!-- Favicon -->
        <link rel="icon" type="image/x-icon" href="../favicon.ico?v=2">
        <link rel="shortcut icon" type="image/x-icon" href="../favicon.ico?v=2">
        <!-- Production Compiled Tailwind CSS -->
        <link rel="stylesheet" href="../assets/css/style.min.css?v=<?php echo $cssVer; ?>">
    </head>
    <body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 font-sans text-slate-800">
        <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl w-full max-w-md border border-slate-100 animate-fade-up">
            <div class="flex items-center justify-center mb-6">
                <img src="../logo.png" alt="NAT-TEST BD Logo" class="w-16 h-16 object-contain drop-shadow">
            </div>
            <h1 class="text-2xl font-black text-center text-slate-900 mb-1">Admin Access</h1>
            <p class="text-center text-slate-400 text-sm mb-6">Sign in to manage NAT-TEST examination portal</p>

            <?php if (!empty($message)): ?>
                <div class="mb-5 p-3.5 rounded-xl text-xs font-semibold bg-red-50 text-red-600 border border-red-200 flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'] ?? ''); ?>">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Username</label>
                    <input type="text" name="username" required autofocus placeholder="Enter admin username"
                        class="w-full px-4 py-3 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Password</label>
                    <input type="password" name="password" required placeholder="••••••••"
                        class="w-full px-4 py-3 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                </div>
                <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-150 cursor-pointer text-sm">
                    Sign In to Dashboard
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <a href="../" class="text-xs text-slate-400 hover:text-slate-600 transition flex items-center justify-center gap-1">
                    ← Back to Public Website
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
