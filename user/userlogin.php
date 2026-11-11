<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

$errorMessage   = '';
$successMessage = '';

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION[$user_session_name]);
    unset($_SESSION[$user_session_name . '_user_id']);
    unset($_SESSION[$user_session_name . '_name']);
    unset($_SESSION[$user_session_name . '_email']);
    unset($_SESSION[$user_session_name . '_institute']);
    unset($_SESSION[$user_session_name . '_role']);
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['user_login_attempts']);
    unset($_SESSION['user_login_lockout']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    startSecureUserSession();
    $successMessage = 'You have been logged out successfully.';
}

// If already logged in, redirect straight to user dashboard (GET requests only, never block POST login)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['logout'])) {
    $isUserLoggedIn = (isset($_SESSION[$user_session_name]) && $_SESSION[$user_session_name] === true);
    if ($isUserLoggedIn) {
        header("Location: dashboard");
        exit;
    }
}

// Handle Login POST with Brute-Force Rate Limiting & CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // 1. Verify CSRF Token
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedCsrf)) {
        $errorMessage = 'Security token validation failed. Please refresh the page and try again.';
    } else {
        // 2. Check Brute-Force Lockout (Max 5 attempts, 15-minute cooldown)
        $lockoutUntil = (int)($_SESSION['user_login_lockout'] ?? 0);
        $attempts     = (int)($_SESSION['user_login_attempts'] ?? 0);
        $currentTime  = time();

        if ($lockoutUntil > $currentTime) {
            $remainingMins = max(1, (int)ceil(($lockoutUntil - $currentTime) / 60));
            $errorMessage = "Too many failed login attempts. Account access is temporarily locked. Please wait {$remainingMins} minute(s) before trying again.";
        } else {
            // Reset lockout if cooldown period has passed
            if ($lockoutUntil > 0 && $lockoutUntil <= $currentTime) {
                unset($_SESSION['user_login_lockout']);
                $_SESSION['user_login_attempts'] = 0;
                $attempts = 0;
            }

            $email    = trim((string)($_POST['email'] ?? ''));
            $password = trim((string)($_POST['password'] ?? ''));

            $loginRes = verifyUserLogin($email, $password);

            if ($loginRes['success']) {
                // Clear lockout counters
                unset($_SESSION['user_login_attempts']);
                unset($_SESSION['user_login_lockout']);

                $_SESSION[$user_session_name]              = true;
                $_SESSION[$user_session_name . '_user_id'] = $loginRes['user']['id'];
                $_SESSION[$user_session_name . '_name']    = $loginRes['user']['name'];
                $_SESSION[$user_session_name . '_email']   = $loginRes['user']['email'];
                $_SESSION[$user_session_name . '_institute'] = $loginRes['user']['institute'];
                $_SESSION[$user_session_name . '_role']    = $loginRes['user']['role'];
                if (($loginRes['user']['role'] ?? '') === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                } else {
                    unset($_SESSION['admin_logged_in']);
                }

                // Explicitly commit session before redirecting
                session_write_close();

                header("Location: dashboard");
                exit;
            } else {
                $attempts++;
                $_SESSION['user_login_attempts'] = $attempts;

                if ($attempts >= 5) {
                    $_SESSION['user_login_lockout'] = $currentTime + 900; // 15 minutes lockout
                    $errorMessage = "Too many failed login attempts. Access temporarily locked for 15 minutes.";
                } else {
                    $remaining = 5 - $attempts;
                    $err = $loginRes['error'] ?? 'Invalid email or password.';
                    $errorMessage = "{$err} ({$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining).";
                }
            }
        }
    }
}

// Dynamically compute base URL and paths for icons and assets
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$siteProtocol = $isHttps ? 'https' : 'http';
$siteHost     = $_SERVER['HTTP_HOST'] ?? 'nat-test.bd';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBaseDir = rtrim($scriptDir, '/');
if (basename($appBaseDir) === 'admin' || basename($appBaseDir) === 'common' || basename($appBaseDir) === 'user') {
    $appBaseDir = dirname($appBaseDir);
}
$appBaseDir = ($appBaseDir === '/' || $appBaseDir === '.') ? '' : $appBaseDir;
$siteBaseUrl  = $siteProtocol . '://' . $siteHost . $appBaseDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>User Login | NAT-TEST BD Portal</title>

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($appBaseDir . '/favicon.ico?v=2'); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($appBaseDir . '/favicon.ico?v=2'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($appBaseDir . '/favicon-32x32.png?v=2'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($appBaseDir . '/favicon-16x16.png?v=2'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($appBaseDir . '/apple-touch-icon.png?v=2'); ?>">

    <!-- Production Compiled Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($appBaseDir . '/assets/css/style.min.css?v=' . (file_exists(dirname(__DIR__) . '/assets/css/style.min.css') ? filemtime(dirname(__DIR__) . '/assets/css/style.min.css') : '1')); ?>">

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 flex flex-col justify-between p-4 sm:p-6 relative selection:bg-blue-600 selection:text-white">

    <!-- Subtle Ambient Background Accent -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[600px] h-[350px] bg-gradient-to-b from-blue-100/60 via-indigo-50/40 to-transparent rounded-full blur-3xl opacity-70"></div>
    </div>

    <!-- Top Minimal Bar -->
    <div class="max-w-5xl w-full mx-auto flex justify-between items-center py-2">
        <a href="<?php echo htmlspecialchars($appBaseDir ?: '/'); ?>" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-blue-600 transition font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span>Back to Homepage</span>
        </a>
        <a href="<?php echo htmlspecialchars($appBaseDir . '/result'); ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 bg-white border border-slate-200/90 px-3 py-1.5 rounded-full shadow-xs hover:shadow transition">
            <span>Result Checker</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>
    </div>

    <!-- Main Login Card -->
    <div class="flex-1 flex items-center justify-center my-6">
        <div class="bg-white border border-slate-200/90 rounded-3xl p-7 sm:p-9 shadow-xl shadow-slate-200/60 w-full max-w-md">
            
            <!-- Logo & Brand Header -->
            <div class="flex justify-center mb-4">
                <a href="<?php echo htmlspecialchars($appBaseDir ?: '/'); ?>" class="inline-block transition transform hover:scale-105">
                    <img src="<?php echo htmlspecialchars($appBaseDir . '/logo.png'); ?>" alt="NAT-TEST BD Logo" class="w-16 h-16 object-contain drop-shadow-sm">
                </a>
            </div>
            
            <h1 class="text-2xl sm:text-3xl font-extrabold text-center text-slate-900 tracking-tight mb-1.5">
                CBT Seat Portal
            </h1>
            <p class="text-center text-slate-500 text-xs sm:text-sm mb-6 leading-relaxed">
                Sign in with your authorized email and password to view real-time CBT seat availability & station logs.
            </p>

            <!-- Error Banner -->
            <?php if (!empty($errorMessage)): ?>
                <div class="mb-5 p-3.5 rounded-xl text-xs sm:text-sm bg-red-50 text-red-700 border border-red-200 flex items-start gap-2.5 animate-fade-in">
                    <svg class="w-4 h-4 shrink-0 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?php echo htmlspecialchars($errorMessage); ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Banner (e.g. on logout) -->
            <?php if (!empty($successMessage)): ?>
                <div class="mb-5 p-3.5 rounded-xl text-xs sm:text-sm bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center gap-2.5">
                    <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span><?php echo htmlspecialchars($successMessage); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                <!-- Email / Username Field -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Email Address or Username <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <input type="text" name="email" required autofocus
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            placeholder="admin or name@institute.com"
                            autocomplete="username"
                            class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition">
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <input type="password" id="userPassword" name="password" required placeholder="Enter your provided password"
                            class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-10 pr-10 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none transition" title="Show/Hide Password">
                            <svg id="eyeIconOpen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg id="eyeIconClosed" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full mt-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-blue-500/20 hover:shadow-xl hover:shadow-blue-500/30 transition duration-200 active:scale-[0.99] text-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    <span>Sign In to Portal</span>
                </button>
            </form>

            <!-- Notice about administration passwords -->
            <div class="mt-5 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-center">
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    <strong class="text-slate-700">Account Access:</strong> User accounts are issued by NAT-TEST BD administration. If you require access credentials, please contact support.
                </p>
            </div>

            <!-- Back to Homepage -->
            <div class="mt-5 pt-4 border-t border-slate-100 text-center">
                <a href="<?php echo htmlspecialchars($appBaseDir ?: '/'); ?>" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-blue-600 transition font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Return to NAT-TEST BD Homepage</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="py-3 text-center text-xs text-slate-400">
        <p>&copy; <?php echo date('Y'); ?> NAT-TEST BD. All rights reserved. CBT Seat Verification System.</p>
    </footer>

    <script>
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('userPassword');
            const iconOpen = document.getElementById('eyeIconOpen');
            const iconClosed = document.getElementById('eyeIconClosed');
            if (!pwdInput || !iconOpen || !iconClosed) return;
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                iconOpen.classList.add('hidden');
                iconClosed.classList.remove('hidden');
            } else {
                pwdInput.type = 'password';
                iconOpen.classList.remove('hidden');
                iconClosed.classList.add('hidden');
            }
        }
    </script>
</body>
</html>