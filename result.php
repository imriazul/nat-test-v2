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

// 1. Include the configuration and database connection
require_once 'config.php';

// ─── Helper function to find examinee in database ───────────
function findExamineeRecord($pdo, $searchQuery) {
    $searchQuery = trim($searchQuery);
    $parsed = parseExamineeNumber($searchQuery);
    if ($parsed) {
        $examType = $parsed['exam_type'] ?? '00';
        $specificTable = getTableName($parsed['month'], $parsed['year'], $examType);
        if ($specificTable) {
            $tableCheck = $pdo->query("SHOW TABLES LIKE '$specificTable'");
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT id, examinee_number, name, dob, email, status FROM `$specificTable` WHERE examinee_number = :num LIMIT 1");
                $stmt->execute(['num' => $searchQuery]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return ['record' => $row, 'table' => $specificTable];
            }
        }

        // Fallback: legacy month table (e.g. Aug_26)
        $legacyTable = getTableName($parsed['month'], $parsed['year']);
        if ($legacyTable && $legacyTable !== $specificTable) {
            $tableCheck = $pdo->query("SHOW TABLES LIKE '$legacyTable'");
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT id, examinee_number, name, dob, email, status FROM `$legacyTable` WHERE examinee_number = :num LIMIT 1");
                $stmt->execute(['num' => $searchQuery]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return ['record' => $row, 'table' => $legacyTable];
            }
        }
    }

    // Fallback: legacy examinees table
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'examinees'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT id, examinee_number, name, dob, email, status FROM examinees WHERE examinee_number = :num LIMIT 1");
            $stmt->execute(['num' => $searchQuery]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return ['record' => $row, 'table' => 'examinees'];
        }
    } catch (Exception $e) {}

    return null;
}

// ─── Rate Limiting Constants ────────────────────────────────
define('OTP_COOLDOWN_SECONDS', 60);       // Must wait 60s between email dispatches
define('MAX_OTP_HOURLY_REQUESTS', 5);     // Max 5 OTP requests per hour per examinee/IP
define('MAX_VERIFY_ATTEMPTS', 5);         // Max 5 failed OTP attempts before lockout

// ─── AJAX Endpoint 1: Send OTP with Email Confirmation ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $examineeNumber = trim($_POST['examinee_number'] ?? '');
    $inputEmail     = strtolower(trim($_POST['email'] ?? ''));

    if (empty($examineeNumber)) {
        echo json_encode(['error' => 'Examinee registration number is required.']);
        exit;
    }

    if (empty($inputEmail)) {
        echo json_encode(['error' => 'Please enter your registered email address to verify identity.']);
        exit;
    }

    $found = findExamineeRecord($pdo, $examineeNumber);
    if (!$found || empty($found['record']['email'])) {
        echo json_encode(['error' => 'No valid records found for this examinee number.']);
        exit;
    }

    $record = $found['record'];
    $dbEmail = strtolower(trim($record['email']));

    // 1. SECURITY CHECK: Verify the provided email matches DB
    if ($inputEmail !== $dbEmail) {
        echo json_encode(['error' => 'The email address entered does not match the registered email for this examinee.']);
        exit;
    }

    // 2. RATE LIMITING: Check Cooldown (60 seconds)
    $sessionKey = 'otp_data_' . $examineeNumber;
    $now = time();

    if (isset($_SESSION[$sessionKey]['last_sent'])) {
        $timeSinceLast = $now - $_SESSION[$sessionKey]['last_sent'];
        if ($timeSinceLast < OTP_COOLDOWN_SECONDS) {
            $waitSec = OTP_COOLDOWN_SECONDS - $timeSinceLast;
            echo json_encode(['error' => "Please wait $waitSec seconds before requesting another code."]);
            exit;
        }
    }

    // 3. RATE LIMITING: Hourly requests limiter
    if (!isset($_SESSION[$sessionKey]['hourly_count'])) {
        $_SESSION[$sessionKey]['hourly_count'] = 1;
        $_SESSION[$sessionKey]['hourly_start'] = $now;
    } else {
        if ($now - $_SESSION[$sessionKey]['hourly_start'] > 3600) {
            // Reset hour window
            $_SESSION[$sessionKey]['hourly_count'] = 1;
            $_SESSION[$sessionKey]['hourly_start'] = $now;
        } else {
            if ($_SESSION[$sessionKey]['hourly_count'] >= MAX_OTP_HOURLY_REQUESTS) {
                echo json_encode(['error' => 'Too many code requests for this examinee. Please try again in an hour.']);
                exit;
            }
            $_SESSION[$sessionKey]['hourly_count']++;
        }
    }

    // 4. Generate 6-digit numeric OTP code
    $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION[$sessionKey]['code']             = $otpCode;
    $_SESSION[$sessionKey]['email']            = $dbEmail;
    $_SESSION[$sessionKey]['expires']          = $now + 600; // 10 minutes
    $_SESSION[$sessionKey]['last_sent']        = $now;
    $_SESSION[$sessionKey]['attempts']         = 0;
    $_SESSION[$sessionKey]['verified']         = false;

    // Send email
    $sent = sendOtpEmail($dbEmail, $otpCode, $examineeNumber);

    if (!$sent) {
        // Rollback hourly count so examinee isn't locked out due to server mail delivery issues
        $_SESSION[$sessionKey]['hourly_count'] = max(0, ($_SESSION[$sessionKey]['hourly_count'] ?? 1) - 1);
        unset($_SESSION[$sessionKey]['last_sent']);

        $errMsg = 'Unable to send verification email.';
        if (!empty($GLOBALS['smtp_last_error'])) {
            $errMsg .= ' [' . $GLOBALS['smtp_last_error'] . ']';
        } else {
            $errMsg .= ' Please check Resend/SMTP email settings in .env or contact the administrator.';
        }

        echo json_encode([
            'error' => $errMsg
        ]);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'cooldown'     => OTP_COOLDOWN_SECONDS,
        'message'      => 'Verification code successfully sent to your email.'
    ]);
    exit;
}

// ─── AJAX Endpoint 2: Verify OTP ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $examineeNumber = trim($_POST['examinee_number'] ?? '');
    $userOtp        = trim($_POST['otp'] ?? '');

    $sessionKey = 'otp_data_' . $examineeNumber;
    if (!isset($_SESSION[$sessionKey]) || empty($_SESSION[$sessionKey]['code'])) {
        echo json_encode(['error' => 'No active verification code found. Please request a new code.']);
        exit;
    }

    $otpData = &$_SESSION[$sessionKey];

    // Check expiration
    if (time() > $otpData['expires']) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['error' => 'Verification code expired. Please request a new code.']);
        exit;
    }

    // Check failed attempts (brute-force defense)
    if (($otpData['attempts'] ?? 0) >= MAX_VERIFY_ATTEMPTS) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['error' => 'Too many failed attempts. Code has been invalidated. Please request a new code.']);
        exit;
    }

    if ($otpData['code'] !== $userOtp) {
        $otpData['attempts'] = ($otpData['attempts'] ?? 0) + 1;
        $remaining = MAX_VERIFY_ATTEMPTS - $otpData['attempts'];
        echo json_encode([
            'error' => "Incorrect verification code. $remaining attempts remaining."
        ]);
        exit;
    }

    // Success: Mark as verified
    $otpData['verified'] = true;

    // Fetch complete unmasked record
    $found = findExamineeRecord($pdo, $examineeNumber);
    if (!$found) {
        echo json_encode(['error' => 'Record not found.']);
        exit;
    }

    $record = $found['record'];
    echo json_encode([
        'success' => true,
        'data'    => [
            'name'            => $record['name'] ?? '',
            'examinee_number' => $record['examinee_number'] ?? '',
            'dob'             => $record['dob'] ?? '',
            'status'          => $record['status'] ?? ''
        ]
    ]);
    exit;
}

// ─── Standard Search Request Handling ───────────────────────
$result = null;
$searched = false;
$maskedEmail = '';
$isVerified = false;

$examMonthName = 'September';
$examYearFull = date('Y');
$examLevelName = '5Q (N5)';
$examCenterInfo = getExamCenterInfo('474');
$examCenterName = $examCenterInfo['name'];
$examCenterSite = $examCenterInfo['site'];
$examCenterCode = $examCenterInfo['code'];
$examTestType   = 'PBT';

// ─── Post-Redirect-Get (PRG) Pattern ────────────────────────
// Eliminates browser "Confirm Form Resubmission" and black screen on Back / Reload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && isset($_POST['examinee_number'])) {
    $cleanNum = trim($_POST['examinee_number']);
    if (!empty($cleanNum)) {
        $_SESSION['last_search'] = $cleanNum;
    } else {
        unset($_SESSION['last_search']);
    }
    // Redirect with HTTP 303 See Other to a clean GET request on the canonical path
    $redirectTarget = preg_replace('/\.php$/', '', strtok($_SERVER['REQUEST_URI'], '?'));
    header('Location: ' . $redirectTarget, true, 303);
    exit;
}

// Reset / Clear Search
if (isset($_GET['reset'])) {
    unset($_SESSION['last_search']);
    $redirectTarget = preg_replace('/\.php$/', '', strtok($_SERVER['REQUEST_URI'], '?'));
    header('Location: ' . $redirectTarget, true, 303);
    exit;
}

// Support search from GET parameter, session (via PRG), or direct POST fallback
$searchQuery = trim($_GET['examinee_number'] ?? ($_SESSION['last_search'] ?? ($_POST['examinee_number'] ?? '')));
if (!empty($_GET['examinee_number'])) {
    $_SESSION['last_search'] = $searchQuery;
}

if (!empty($searchQuery)) {
    $searched = true;
    $found = findExamineeRecord($pdo, $searchQuery);
    
    if ($found) {
        $result = $found['record'];
        $maskedEmail = maskEmail($result['email'] ?? '');

        // Extract exam month, year, level, and center
        $parsedExaminee = parseExamineeNumber($result['examinee_number']);
        if ($parsedExaminee && isset($MONTH_FULL[$parsedExaminee['month']])) {
            $examMonthName = $MONTH_FULL[$parsedExaminee['month']];
        } elseif ($parsedExaminee && isset($MONTH_MAP[$parsedExaminee['month']])) {
            $examMonthName = $MONTH_MAP[$parsedExaminee['month']];
        }
        if ($parsedExaminee && !empty($parsedExaminee['year'])) {
            $examYearFull = '20' . $parsedExaminee['year'];
        }
        if ($parsedExaminee && !empty($parsedExaminee['level'])) {
            $lvl = $parsedExaminee['level'];
            $examLevelName = $lvl . 'Q (N' . $lvl . ')';
        }

        // CCC (e.g. 474) resolves to exam center: ULSAI (Dhaka)
        $centerCode = $parsedExaminee['center_code'] ?? ($parsedExaminee['site_code'] ?? '474');
        $examCenterInfo = getExamCenterInfo($centerCode);
        $examCenterName = $examCenterInfo['name'];
        $examCenterSite = $examCenterInfo['site'];
        $examCenterCode = $examCenterInfo['code'];

        // Determine CBT or PBT from examinee number (digits 5-6: 00 = PBT, other = CBT)
        $cleanExNum = preg_replace('/[^0-9]/', '', $result['examinee_number'] ?? '');
        $examTypeCode = (strlen($cleanExNum) >= 6) ? substr($cleanExNum, 4, 2) : ($parsedExaminee['exam_type'] ?? '00');
        $examTestType = ($examTypeCode === '00' || $examTypeCode === '0' || $examTypeCode === '') ? 'PBT NAT-TEST' : 'CBT NAT-TEST';

        // Check if session already verified this examinee
        $sessionKey = 'otp_data_' . $searchQuery;
        if (!empty($_SESSION[$sessionKey]['verified'])) {
            $isVerified = true;
        }
    }
}

// ─── Dynamic Celebration Messages for Pass Result ────────────
$passMessages = [
    [
        'headline' => 'Hey Buddy, You Did It! 🎉',
        'message' => 'You conquered the %s NAT-TEST! Your hard work is paying off!'
    ],
    [
        'headline' => 'A Big Win for You! 🏆',
        'message' => 'Amazing work! You passed the %s NAT-TEST. Time to celebrate!'
    ],
    [
        'headline' => 'Your Hard Work Paid Off! ✨',
        'message' => 'You made it! Your %s NAT-TEST success is another step toward your dreams.'
    ],
    [
        'headline' => 'Mission Accomplished! 🚀',
        'message' => 'Another milestone unlocked! Congratulations on passing the %s NAT-TEST!'
    ],
    [
        'headline' => 'You Should Be Proud! 🌟',
        'message' => 'You did a fantastic job in the %s NAT-TEST. Keep believing in yourself!'
    ],
    [
        'headline' => 'The Future Looks Brighter! 🎊',
        'message' => 'Your success in the %s NAT-TEST brings you one step closer to your goals!'
    ],
    [
        'headline' => 'Look Who Made It! 🥳',
        'message' => 'You turned your preparation into success! Congratulations on passing the %s NAT-TEST!'
    ],
    [
        'headline' => 'Dream Big, Buddy! 🌈',
        'message' => 'Your %s NAT-TEST success proves that every step forward counts. Keep chasing your dreams!'
    ],
    [
        'headline' => 'You Are on Fire! 🔥',
        'message' => 'What an achievement! You passed the %s NAT-TEST. Keep that winning spirit alive!'
    ],
    [
        'headline' => 'A New Chapter Begins! 📖',
        'message' => 'Congratulations! Passing the %s NAT-TEST opens the door to new opportunities. Keep going!'
    ],
    [
        'headline' => 'Your Success Story Continues! 💫',
        'message' => 'You have every reason to smile! Celebrate your %s NAT-TEST success and keep moving forward.'
    ],
    [
        'headline' => 'You Made It Happen! 💪',
        'message' => 'Your dedication brought you here! Congratulations on passing the %s NAT-TEST. You earned it!'
    ],
    [
        'headline' => 'One Step Closer to Japan! 🇯🇵',
        'message' => 'Your %s NAT-TEST success is another step toward your Japanese-language goals. Keep shining!'
    ],
    [
        'headline' => 'This Is Your Moment! 🎯',
        'message' => 'Take a moment to celebrate! You passed the %s NAT-TEST, and your next adventure awaits!'
    ],
    [
        'headline' => 'Cheers to Your Achievement! 🥂',
        'message' => 'All your effort deserves this moment! Congratulations on your wonderful %s NAT-TEST result!'
    ],
    [
        'headline' => 'You Are Moving Forward! 🚀',
        'message' => 'Every achievement brings you closer to your dreams. Congratulations on passing the %s NAT-TEST!'
    ],
    [
        'headline' => 'Give Yourself a High Five! 🙌',
        'message' => 'You worked for it, and you earned it! Celebrate your %s NAT-TEST success with a big smile!'
    ],
    [
        'headline' => 'Success Looks Good on You! 😎',
        'message' => 'You have done something worth celebrating! Congratulations on passing the %s NAT-TEST. Keep growing!'
    ],
    [
        'headline' => 'Your Next Adventure Awaits! 🌏',
        'message' => 'Congratulations on your %s NAT-TEST success! Keep learning, keep improving, and keep dreaming big!'
    ],
    [
        'headline' => 'A Proud Moment for You! 🥇',
        'message' => 'You proved that your effort matters! Celebrate passing the %s NAT-TEST and prepare for your next goal.'
    ],
    [
        'headline' => 'Keep Shining, Champion! 🌟',
        'message' => 'This is a wonderful achievement! Congratulations on passing the %s NAT-TEST. Your journey continues!'
    ],
];

// ─── Consistent Celebration Message for Same Examinee ────────
$targetExaminee = !empty($result['examinee_number']) ? trim($result['examinee_number']) : trim($searchQuery);
$totalMessages = count($passMessages);

if (!empty($targetExaminee)) {
    $cookieKey = 'nat_msg_' . substr(md5($targetExaminee), 0, 10);
    $sessionKey = 'pass_msg_' . $targetExaminee;

    if (isset($_COOKIE[$cookieKey]) && is_numeric($_COOKIE[$cookieKey]) && (int)$_COOKIE[$cookieKey] >= 0 && (int)$_COOKIE[$cookieKey] < $totalMessages) {
        $msgIndex = (int)$_COOKIE[$cookieKey];
    } elseif (isset($_SESSION[$sessionKey]) && is_numeric($_SESSION[$sessionKey]) && (int)$_SESSION[$sessionKey] >= 0 && (int)$_SESSION[$sessionKey] < $totalMessages) {
        $msgIndex = (int)$_SESSION[$sessionKey];
    } else {
        // Deterministic hash based on examinee number ensures each candidate gets
        // a consistent, distinct message out of the 21 options across any browser/device
        $msgIndex = abs(crc32($targetExaminee)) % $totalMessages;
    }

    // Persist in session and cookie (30 days) for instant retrieval on reload
    $_SESSION[$sessionKey] = $msgIndex;
    if (!headers_sent() && (!isset($_COOKIE[$cookieKey]) || $_COOKIE[$cookieKey] != $msgIndex)) {
        setcookie($cookieKey, (string)$msgIndex, time() + (86400 * 30), '/', '', false, true);
    }
} else {
    $msgIndex = 0;
}

$selectedPassMsg = $passMessages[$msgIndex];
$passHeadline = $selectedPassMsg['headline'];
$highlightMonth = '<span class="text-amber-300 font-extrabold underline decoration-amber-400/60 decoration-2 underline-offset-4">&ldquo;' . htmlspecialchars($examMonthName) . '&rdquo;</span>';
$passMessageHtml = sprintf(htmlspecialchars($selectedPassMsg['message']), $highlightMonth);

// Dynamic SEO Meta & Structured Data
$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'nat-test.bd');

if ($searched && $result) {
    $cleanNum = htmlspecialchars($result['examinee_number']);
    $pageTitle    = "NAT-TEST Result: {$cleanNum} ({$examTestType}) — NAT-TEST BD";
    $pageDesc     = "Official NAT-TEST Result for examinee {$cleanNum}. Exam Format: {$examTestType}, Level: {$examLevelName}, Center: {$examCenterName}. Verified by NAT-TEST BD.";
    $pageKeywords = "NAT-TEST Result, NAT-TEST BD, NAT-TEST, CBT NAT-TEST, PBT NAT-TEST, Examinee {$cleanNum}, Japanese language proficiency result";
    $schemaJson   = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'NAT-TEST BD Home',
                'item'     => $siteUrl . '/'
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => 'Check NAT-TEST Result',
                'item'     => $siteUrl . '/result'
            ],
            [
                '@type'    => 'ListItem',
                'position' => 3,
                'name'     => 'Examinee ' . $cleanNum
            ]
        ]
    ];
} else {
    $pageTitle    = 'Check NAT-TEST Result Online | NAT-TEST BD — PBT & CBT Verification';
    $pageDesc     = 'Check your official NAT-TEST Result online in Bangladesh with NAT-TEST BD. Instant score verification for PBT and CBT NAT-TEST examinees across levels N5 to N1.';
    $pageKeywords = 'NAT-TEST Result, NAT-TEST BD, NAT-TEST, CBT NAT-TEST, PBT NAT-TEST, Japanese proficiency test Bangladesh, check NAT TEST result online';
    $schemaJson   = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'NAT-TEST BD Home',
                'item'     => $siteUrl . '/'
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => 'Check NAT-TEST Result',
                'item'     => $siteUrl . '/result'
            ]
        ]
    ];
}

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>';
require_once __DIR__ . '/common/header.php';
?>

    <main class="w-full max-w-3xl px-3 sm:px-4 py-2">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="mb-2.5">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200/80 shadow-xs">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <span>Official Score Verification</span>
                </span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900 mb-2">NAT-TEST Result Check</h1>
            <p class="text-slate-500 text-sm sm:text-base max-w-md mx-auto">Check official PBT &amp; CBT NAT-TEST scores in Bangladesh with NAT-TEST BD</p>
        </div>

        <!-- Search Bar (POST to keep URL static) -->
        <form method="POST" action="result" class="relative group mb-10">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                <svg class="w-5 h-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input type="text" 
                   name="examinee_number" 
                   value="<?php echo !empty($searchQuery) ? htmlspecialchars($searchQuery) : ''; ?>"
                   placeholder="26010012340000" 
                   required
                   class="block w-full p-4 pl-12 <?php echo !empty($searchQuery) ? 'pr-36' : 'pr-28'; ?> text-base sm:text-lg text-slate-900 border border-slate-200 rounded-2xl bg-white shadow-sm focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
            <?php if (!empty($searchQuery)): ?>
                <a href="result?reset=1" title="Clear search" class="absolute right-28 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 text-xs font-bold transition">✕</a>
            <?php endif; ?>
            <button type="submit" class="absolute right-2.5 bottom-2.5 top-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl text-sm px-6 transition-colors shadow-md shadow-blue-500/20 flex items-center">
                Search
            </button>
        </form>

        <!-- Result Display -->
        <?php if ($searched): ?>
            <?php if ($result): ?>
                
                <?php if ($result['status'] === 'Pass'): ?>
                    <!-- ══════════════════════════════════════════════════════════════
                         SECTION 1: RESULT STATUS (CELEBRATION HERO)
                    ══════════════════════════════════════════════════════════════ -->
                    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-900 p-8 sm:p-12 text-white shadow-2xl shadow-emerald-700/20 border border-emerald-400/30 mb-8 animate-float text-center">
                        <!-- Atmospheric ambient glows -->
                        <div class="absolute -right-10 -top-10 w-72 h-72 bg-emerald-400/20 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="absolute -left-10 -bottom-10 w-72 h-72 bg-teal-300/15 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:16px_16px] opacity-10 pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col items-center">
                            <!-- Floating 3D Trophy / Celebration Icon with pulsing aura -->
                            <div class="relative mb-6">
                                <div class="absolute inset-0 rounded-3xl bg-amber-400/30 blur-2xl animate-pulse"></div>
                                <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-gradient-to-br from-amber-300 via-amber-400 to-amber-500 p-0.5 shadow-2xl shadow-amber-500/40">
                                    <div class="w-full h-full bg-slate-900/40 backdrop-blur-sm rounded-[22px] flex items-center justify-center">
                                        <span class="text-4xl sm:text-5xl select-none">🏆</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pill Category -->
                            <div class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold bg-white/15 backdrop-blur-md text-emerald-200 border border-white/20 mb-3.5 shadow-sm">
                                <span>✨ Official Result Notification</span>
                            </div>

                            <!-- Main Headline & Dynamic Celebration Message -->
                            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight drop-shadow-md mb-3 leading-tight">
                                <?php echo htmlspecialchars($passHeadline); ?>
                            </h2>
                            <p class="text-emerald-100 text-sm sm:text-base font-medium max-w-lg mb-7 leading-relaxed">
                                <?php echo $passMessageHtml; ?>
                            </p>

                            <!-- Big Shiny Status Pill -->
                            <div class="inline-flex items-center gap-3 px-6 py-3.5 rounded-2xl bg-white text-emerald-800 font-black text-sm sm:text-base shadow-xl shadow-slate-950/20 tracking-wider uppercase">
                                <span class="relative flex h-3.5 w-3.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-80"></span>
                                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                                </span>
                                <span>RESULT: PASSED</span>
                            </div>
                        </div>
                    </div>

                    <!-- Confetti Celebration for Passed Examinees -->
                    <script>
                        var duration = 3.5 * 1000;
                        var end = Date.now() + duration;
                        (function frame() {
                            confetti({ particleCount: 5, angle: 60, spread: 55, origin: { x: 0 }, colors: ['#10B981', '#3B82F6', '#F59E0B'] });
                            confetti({ particleCount: 5, angle: 120, spread: 55, origin: { x: 1 }, colors: ['#10B981', '#3B82F6', '#F59E0B'] });
                            if (Date.now() < end) requestAnimationFrame(frame);
                        }());
                    </script>

                <?php elseif ($result['status'] === 'Pending'): ?>
                    <!-- ══════════════════════════════════════════════════════════════
                         SECTION 1: RESULT STATUS (PENDING / AWAITED HERO)
                    ══════════════════════════════════════════════════════════════ -->
                    <div class="relative overflow-hidden rounded-3xl border border-amber-200 bg-gradient-to-b from-amber-50/60 via-white to-white shadow-xl shadow-amber-900/5 mb-8">
                        <!-- Top vibrant accent bar -->
                        <div class="h-1.5 bg-gradient-to-r from-amber-400 via-orange-400 to-amber-500"></div>

                        <!-- Background ambient decoration -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-amber-100/50 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 bg-orange-100/40 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 p-7 sm:p-11 text-center max-w-xl mx-auto">
                            <!-- Icon -->
                            <div class="mx-auto mb-5 flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-amber-50 border border-amber-200/80 shadow-sm text-amber-600">
                                <svg class="w-8 h-8 sm:w-10 sm:h-10 animate-spin" style="animation-duration: 8s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2"/>
                                </svg>
                            </div>

                            <!-- Status label -->
                            <div class="inline-flex items-center gap-2 rounded-full bg-amber-100/80 border border-amber-200 px-3.5 py-1.5 mb-3.5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                </span>
                                <span class="text-xs font-bold tracking-wide text-amber-800 uppercase">
                                    Official Notice
                                </span>
                            </div>

                            <!-- Heading -->
                            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 mb-2.5">
                                Result Not Published Yet
                            </h2>

                            <!-- Description -->
                            <p class="text-sm sm:text-base leading-relaxed text-slate-600 mb-6">
                                The official NAT-TEST examination results for this examinee number have not been published yet or are currently under evaluation. Please check back later.
                            </p>

                            <!-- Status box -->
                            <div class="inline-flex items-center gap-2.5 px-6 py-3 rounded-2xl border border-amber-200 bg-amber-50/80 text-amber-900 font-extrabold text-xs sm:text-sm shadow-sm tracking-wider uppercase">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                                </span>
                                <span>STATUS: PENDING / UNDER EVALUATION</span>
                            </div>

                            <!-- Footer note -->
                            <p class="mt-5 text-xs text-slate-400 flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Results will automatically appear once released by Japan Headquarters.</span>
                            </p>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ══════════════════════════════════════════════════════════════
                         SECTION 1: RESULT STATUS (FAIL HERO)
                    ══════════════════════════════════════════════════════════════ -->
                    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-8 sm:p-12 text-white shadow-2xl border border-slate-700/70 mb-8 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-800/90 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-slate-700/80 text-slate-300 shadow-inner">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-semibold bg-white/10 text-slate-300 border border-white/10 mb-3">
                            <span>Official Result Notification</span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mb-2 tracking-tight">Keep Trying &amp; Don't Give Up</h2>
                        <p class="text-slate-300 text-sm sm:text-base max-w-md mx-auto mb-6 leading-relaxed">
                            Language mastery is a rewarding journey. Review your exam sections and prepare with confidence for the next session!
                        </p>
                        <div class="inline-block px-6 py-3 rounded-2xl bg-slate-800 text-slate-200 font-extrabold text-xs sm:text-sm tracking-wider uppercase border border-slate-700 shadow-sm">
                            RESULT: NOT PASS
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════════════
                     SECTION 2: INFORMATION (BASIC INFO + UNREVEAL INFO)
                ══════════════════════════════════════════════════════════════ -->
                <div class="bg-white rounded-3xl p-6 sm:p-9 shadow-xl shadow-slate-900/5 border border-slate-200/90 mb-8">
                    
                    <!-- SUB-SECTION A: Basic Information (Public / Visible without verification) -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100 flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-slate-50 border border-slate-200/80 p-1.5 flex items-center justify-center shrink-0">
                                    <picture>
                                        <source srcset="<?php echo htmlspecialchars($logoWebpUrl ?? 'logo.webp'); ?>" type="image/webp">
                                        <img src="<?php echo htmlspecialchars($logoUrl ?? 'logo.png'); ?>" alt="NAT-TEST Logo" width="32" height="32" loading="lazy" decoding="async" class="w-full h-full object-contain">
                                    </picture>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-slate-900">Official Exam Records</h3>
                                    <p class="text-xs text-slate-400">Authenticated examination credentials</p>
                                </div>
                            </div>
                            <?php 
                            $badgeColorClass = ($result['status'] === 'Pass')
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : (($result['status'] === 'Pending')
                                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                                    : 'bg-slate-100 text-slate-600 border-slate-200');
                            ?>
                            <span class="text-xs font-bold px-3 py-1 rounded-full border <?php echo $badgeColorClass; ?> flex items-center gap-1.5 shadow-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <?php echo htmlspecialchars($examTestType); ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Examinee Number -->
                            <div class="bg-slate-50/70 hover:bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 transition-colors">
                                <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                                    Examinee Number
                                </span>
                                <span class="font-mono font-extrabold text-slate-900 text-base sm:text-lg tracking-wider select-all"><?php echo htmlspecialchars($result['examinee_number']); ?></span>
                            </div>

                            <!-- Exam Level -->
                            <div class="bg-slate-50/70 hover:bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 transition-colors">
                                <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                                    Exam Level
                                </span>
                                <span class="font-extrabold text-slate-900 text-base sm:text-lg flex items-center gap-2">
                                    <span><?php echo htmlspecialchars($examLevelName); ?></span>
                                </span>
                            </div>

                            <!-- Exam Month -->
                            <div class="bg-slate-50/70 hover:bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 transition-colors">
                                <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Exam Session
                                </span>
                                <span class="font-bold text-slate-900 text-base sm:text-lg">
                                    <?php echo htmlspecialchars($examMonthName . ' ' . $examYearFull); ?>
                                </span>
                            </div>

                            <!-- Exam Center -->
                            <div class="bg-slate-50/70 hover:bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 transition-colors">
                                <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Exam Center &amp; Site
                                </span>
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <span class="font-bold text-slate-900 text-base sm:text-lg">
                                        <?php echo htmlspecialchars($examCenterName); ?>
                                    </span>
                                    <span class="text-[11px] bg-slate-200/80 text-slate-700 px-2.5 py-0.5 rounded-full font-semibold border border-slate-300/60">
                                        <?php echo htmlspecialchars($examCenterSite); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-SECTION B: Personal Information (Protected with email verification) -->
                    <div class="pt-6 border-t border-slate-100">
                        <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100 flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200/80 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-slate-900">Examinee Personal Details</h3>
                                    <p class="text-xs text-slate-400">Identity protected by two-step email verification</p>
                                </div>
                            </div>
                            <span id="badge-verification-status" class="text-xs font-semibold px-3 py-1 rounded-full border <?php echo $isVerified ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?> flex items-center gap-1.5 shadow-xs">
                                <?php echo $isVerified ? '✓ Email Verified' : '🔒 Protected'; ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Full Name -->
                            <div class="bg-slate-50/70 hover:bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 transition-colors">
                                <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5">Candidate Full Name</span>
                                <span id="field-name" class="<?php echo $isVerified ? 'font-extrabold text-slate-900 text-base sm:text-lg' : 'font-extrabold text-slate-900 text-base sm:text-lg blur-sens select-none'; ?>">
                                    <?php echo $isVerified ? htmlspecialchars($result['name']) : '•••••••• ••••••••'; ?>
                                </span>
                            </div>

                            <!-- Date of Birth -->
                            <div class="bg-slate-50/70 hover:bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 transition-colors">
                                <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5">Date of Birth (DOB)</span>
                                <span id="field-dob" class="<?php echo $isVerified ? 'font-bold text-slate-900 text-base sm:text-lg font-mono' : 'font-bold text-slate-900 text-base sm:text-lg font-mono blur-sens select-none'; ?>">
                                    <?php echo $isVerified ? htmlspecialchars($result['dob'] ?? '') : '••••-••-••'; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Verification Banner / CTA -->
                        <?php if (!$isVerified): ?>
                            <div id="verify-cta-card" class="mt-6 p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white flex flex-col sm:flex-row items-center justify-between gap-5 border border-slate-800 shadow-xl shadow-slate-950/20">
                                <div class="flex items-center gap-3.5 text-center sm:text-left">
                                    <div class="w-11 h-11 rounded-2xl bg-white/10 flex items-center justify-center shrink-0 border border-white/10">
                                        <svg class="w-5 h-5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm sm:text-base text-white">Confidential Personal Details</div>
                                        <p class="text-xs text-slate-300 mt-0.5">Verify your registered email address to reveal candidate name and date of birth.</p>
                                    </div>
                                </div>
                                <button onclick="openOtpModal()" class="w-full sm:w-auto shrink-0 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-xl transition-all shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2 active:scale-95">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <span>Verify Email Now</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Not Found Card -->
                <div class="rounded-3xl border border-slate-200 bg-white p-8 sm:p-12 text-center shadow-lg shadow-slate-900/5 mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-4 border border-rose-100">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-slate-900 mb-2">No Examinee Record Found</h3>
                    <p class="text-slate-500 text-sm max-w-md mx-auto mb-6">
                        We could not find any result for examinee number <strong class="font-mono text-slate-800"><?php echo htmlspecialchars($searchQuery); ?></strong>.
                    </p>
                    <div class="inline-flex items-center gap-2 p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 mb-6 text-left max-w-md mx-auto">
                        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Please ensure you entered the full 14-digit number printed on your exam admission ticket.</span>
                    </div>
                    <div>
                        <a href="result?reset=1" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-md">
                            <span>Clear &amp; Try Again</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>

    <!-- ═══ Two-Step OTP Verification Modal ═══ -->
    <div id="otp-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md hidden p-4 sm:p-6">
        <div class="bg-white w-full max-w-md rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-100 relative animate-float">
            <!-- Close button -->
            <button onclick="closeOtpModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <!-- ── STEP 1: Confirm Registered Email Address ── -->
            <div id="modal-step-email">
                <div class="text-center mb-5">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-3 border border-blue-100">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Confirm Your Identity</h3>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">To protect your personal data, confirm your registered email address before requesting a verification code.</p>
                </div>

                <!-- Email Hint Card -->
                <div class="bg-blue-50/60 border border-blue-100 rounded-2xl p-4 mb-5 text-center">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-0.5">Email Hint</span>
                    <span class="font-mono text-base font-bold text-blue-700">
                        <?php echo htmlspecialchars($maskedEmail); ?>
                    </span>
                </div>

                <!-- Email Input Form -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Enter Full Email Address</label>
                        <input type="email" 
                               id="input-confirm-email" 
                               placeholder="your.email@example.com" 
                               class="w-full text-base font-medium py-3.5 px-4 border border-slate-300 rounded-2xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition">
                    </div>

                    <div id="email-error-msg" class="text-xs text-red-500 text-center font-medium hidden"></div>

                    <button id="btn-send-code" onclick="submitSendOtp()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-blue-500/30 transition flex items-center justify-center gap-2 active:scale-95">
                        <span id="send-btn-text">Send Verification Code</span>
                        <div id="send-spinner" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin hidden"></div>
                    </button>
                </div>
            </div>

            <!-- ── STEP 2: Enter 6-Digit OTP Code ── -->
            <div id="modal-step-otp" class="hidden">
                <div class="text-center mb-5">
                    <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-3 border border-emerald-100">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Enter Verification Code</h3>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">We sent a 6-digit verification code to your email.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2 text-center">6-Digit Code</label>
                        <input type="text" 
                               id="otp-input" 
                               maxlength="6" 
                               placeholder="••••••" 
                               autocomplete="one-time-code"
                               class="w-full text-center text-3xl font-mono tracking-widest font-black py-3.5 px-4 border border-slate-300 rounded-2xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition">
                    </div>

                    <div id="otp-error-msg" class="text-xs text-red-500 text-center font-medium hidden"></div>

                    <button id="btn-verify-otp" onclick="submitOtpVerification()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-emerald-500/30 transition flex items-center justify-center gap-2 active:scale-95">
                        <span id="verify-btn-text">Verify &amp; Reveal Data</span>
                        <div id="verify-spinner" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin hidden"></div>
                    </button>

                    <!-- Cooldown Timer & Resend Button -->
                    <div class="text-center pt-2 flex flex-col items-center gap-1">
                        <button id="btn-resend-otp" onclick="resendOtpCode()" disabled class="text-xs font-semibold text-slate-400 cursor-not-allowed transition">
                            Resend Code
                        </button>
                        <span id="cooldown-timer-text" class="text-xs text-slate-400"></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ JavaScript Logic ═══ -->
    <script>
    // Keep browser address bar static and clean without query parameters
    if (window.history.replaceState && window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    const currentExaminee = "<?php echo isset($result['examinee_number']) ? htmlspecialchars($result['examinee_number']) : ''; ?>";
    let cooldownTimer = null;
    let cooldownRemaining = 0;
    let verifiedEmail = '';

    function openOtpModal() {
        document.getElementById('otp-modal').classList.remove('hidden');
        document.getElementById('modal-step-email').classList.remove('hidden');
        document.getElementById('modal-step-otp').classList.add('hidden');
        document.getElementById('email-error-msg').classList.add('hidden');
        document.getElementById('otp-error-msg').classList.add('hidden');
        
        const emailInput = document.getElementById('input-confirm-email');
        emailInput.value = '';
        setTimeout(() => emailInput.focus(), 150);
    }

    function closeOtpModal() {
        document.getElementById('otp-modal').classList.add('hidden');
    }

    // Step 1: Submit email confirmation to request code
    async function submitSendOtp() {
        const emailInput = document.getElementById('input-confirm-email');
        const emailVal = emailInput.value.trim();
        const errEl = document.getElementById('email-error-msg');
        const btn = document.getElementById('btn-send-code');
        const btnText = document.getElementById('send-btn-text');
        const spinner = document.getElementById('send-spinner');

        if (!emailVal || !emailVal.includes('@')) {
            errEl.textContent = 'Please enter a valid email address.';
            errEl.classList.remove('hidden');
            return;
        }

        errEl.classList.add('hidden');
        btn.disabled = true;
        btnText.textContent = 'Verifying email…';
        spinner.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('action', 'send_otp');
            formData.append('examinee_number', currentExaminee);
            formData.append('email', emailVal);

            const resp = await fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();
            btn.disabled = false;
            btnText.textContent = 'Send Verification Code';
            spinner.classList.add('hidden');

            if (data.success) {
                verifiedEmail = emailVal;
                // Switch to Step 2 (OTP Input)
                document.getElementById('modal-step-email').classList.add('hidden');
                document.getElementById('modal-step-otp').classList.remove('hidden');
                
                // Start 60-second cooldown timer
                startCooldown(data.cooldown || 60);

                const otpInput = document.getElementById('otp-input');
                otpInput.value = '';
                setTimeout(() => otpInput.focus(), 150);
            } else {
                errEl.textContent = data.error || 'Email verification failed.';
                errEl.classList.remove('hidden');
            }
        } catch (err) {
            btn.disabled = false;
            btnText.textContent = 'Send Verification Code';
            spinner.classList.add('hidden');
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.remove('hidden');
        }
    }

    // Resend code with cooldown protection
    async function resendOtpCode() {
        if (cooldownRemaining > 0 || !verifiedEmail) return;

        const resendBtn = document.getElementById('btn-resend-otp');
        const timerText = document.getElementById('cooldown-timer-text');
        timerText.textContent = 'Sending new code…';

        try {
            const formData = new FormData();
            formData.append('action', 'send_otp');
            formData.append('examinee_number', currentExaminee);
            formData.append('email', verifiedEmail);

            const resp = await fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();
            if (data.success) {
                startCooldown(data.cooldown || 60);
                timerText.textContent = 'New code sent to your email!';
            } else {
                timerText.textContent = data.error || 'Could not resend code.';
            }
        } catch (e) {
            timerText.textContent = 'Network error while resending.';
        }
    }

    // Cooldown countdown handler
    function startCooldown(seconds) {
        cooldownRemaining = seconds;
        const resendBtn = document.getElementById('btn-resend-otp');
        const timerText = document.getElementById('cooldown-timer-text');

        resendBtn.disabled = true;
        resendBtn.className = 'text-xs font-semibold text-slate-400 cursor-not-allowed transition';

        if (cooldownTimer) clearInterval(cooldownTimer);

        function updateTimer() {
            if (cooldownRemaining > 0) {
                timerText.textContent = `Resend available in ${cooldownRemaining}s`;
                cooldownRemaining--;
            } else {
                clearInterval(cooldownTimer);
                timerText.textContent = '';
                resendBtn.disabled = false;
                resendBtn.className = 'text-xs font-semibold text-blue-600 hover:text-blue-800 transition cursor-pointer';
            }
        }

        updateTimer();
        cooldownTimer = setInterval(updateTimer, 1000);
    }

    // Step 2: Submit OTP verification
    async function submitOtpVerification() {
        const otpInput = document.getElementById('otp-input');
        const code = otpInput.value.trim();
        const errorMsg = document.getElementById('otp-error-msg');
        const btnText = document.getElementById('verify-btn-text');
        const spinner = document.getElementById('verify-spinner');
        const btn = document.getElementById('btn-verify-otp');

        if (code.length < 4) {
            errorMsg.textContent = 'Please enter the 6-digit verification code.';
            errorMsg.classList.remove('hidden');
            return;
        }

        errorMsg.classList.add('hidden');
        btn.disabled = true;
        btnText.textContent = 'Verifying…';
        spinner.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('action', 'verify_otp');
            formData.append('examinee_number', currentExaminee);
            formData.append('otp', code);

            const resp = await fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            });

            const res = await resp.json();
            btn.disabled = false;
            btnText.textContent = 'Verify & Reveal Data';
            spinner.classList.add('hidden');

            if (res.success && res.data) {
                // Reveal all blurred fields (Full Name & Date of Birth)
                const nameEl = document.getElementById('field-name');
                const dobEl  = document.getElementById('field-dob');

                if (nameEl) {
                    nameEl.textContent = res.data.name;
                    nameEl.classList.remove('blur-sens');
                }
                if (dobEl) {
                    dobEl.textContent = res.data.dob;
                    dobEl.classList.remove('blur-sens');
                }

                // Hide the CTA card inside details
                const ctaCard = document.getElementById('verify-cta-card');
                if (ctaCard) ctaCard.classList.add('hidden');

                // Update verification badge
                const badgeEl = document.getElementById('badge-verification-status');
                if (badgeEl) {
                    badgeEl.textContent = '✓ Email Verified';
                    badgeEl.className = 'text-[11px] font-semibold px-2.5 py-1 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200';
                }

                closeOtpModal();
            } else {
                errorMsg.textContent = res.error || 'Verification failed. Please try again.';
                errorMsg.classList.remove('hidden');
            }
        } catch (err) {
            btn.disabled = false;
            btnText.textContent = 'Verify & Reveal Data';
            spinner.classList.add('hidden');
            errorMsg.textContent = 'Network error during verification.';
            errorMsg.classList.remove('hidden');
        }
    }
    </script>

<?php require_once __DIR__ . '/common/footer.php'; ?>