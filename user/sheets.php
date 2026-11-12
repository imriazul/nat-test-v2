<?php
// Start session & load user configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// Strict Authentication Check: Only logged-in users or admins can access sheet data
$isUserLoggedIn = (isset($_SESSION[$user_session_name]) && $_SESSION[$user_session_name] === true)
               || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

if (!$isUserLoggedIn) {
    http_response_code(401);
    @header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(["error" => "Unauthorized. Please log in to view seat data."]);
    exit;
}

// Set strict security headers
@header('Content-Type: application/json; charset=UTF-8');
@header('X-Content-Type-Options: nosniff');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Safe CORS handling (Only allow same host or verified origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host   = $_SERVER['HTTP_HOST'] ?? '';
if (!empty($origin)) {
    $parsedOrigin = parse_url($origin, PHP_URL_HOST);
    if ($parsedOrigin && ($parsedOrigin === $host || $parsedOrigin === 'localhost' || $parsedOrigin === '127.0.0.1')) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit(0);
}

// Set timezone to match local exam system time
date_default_timezone_set('Asia/Dhaka');

// ─── 1. Load Environment Variables from root .env ────────────
if (!function_exists('getEnvValue')) {
    function getEnvValue($key, $default = '') {
        // Check $_ENV, getenv, and $_SERVER
        if (!empty($_ENV[$key])) return $_ENV[$key];
        if (getenv($key) !== false && getenv($key) !== '') return getenv($key);
        if (!empty($_SERVER[$key])) return $_SERVER[$key];

        // Fallback: parse root .env directly if not already populated
        static $envLoaded = false;
        static $envCache = [];
        if (!$envLoaded) {
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    $envCache[$k] = $v;
                    if (!isset($_ENV[$k])) $_ENV[$k] = $v;
                }
            }
            $envLoaded = true;
        }
        return $envCache[$key] ?? $default;
    }
}

// Whitelisted CBT Exam Centers (Prevents arbitrary Sheet ID injection)
$centers = [
    'dhaka' => [
        'id'       => 'dhaka',
        'name'     => 'ULSAI (Dhaka)',
        'sheet_id' => getEnvValue('GOOGLE_SHEET_ID_DHAKA1', '')
    ],
    'dhaka1' => [
        'id'       => 'dhaka1',
        'name'     => 'ULSAI (Dhaka)',
        'sheet_id' => getEnvValue('GOOGLE_SHEET_ID_DHAKA1', '')
    ],
    'khulna' => [
        'id'       => 'khulna',
        'name'     => 'KHULNA',
        'sheet_id' => getEnvValue('GOOGLE_SHEET_ID_KHULNA', '')
    ]
];

// Determine selected center (default: dhaka1)
$siteParam = strtolower(trim($_GET['site'] ?? 'dhaka1'));
$activeCenter = $centers[$siteParam] ?? $centers['dhaka1'];
$spreadsheetId = $activeCenter['sheet_id'];
$apiKey        = getEnvValue('GOOGLE_SHEETS_API_KEY', '');

if (empty($spreadsheetId)) {
    http_response_code(500);
    echo json_encode(["error" => "GOOGLE_SHEET_ID is not configured for the selected center"]);
    exit;
}

// ACTION: Return list of available centers
if (isset($_GET['action']) && $_GET['action'] === 'centers') {
    echo json_encode(array_values(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $centers)));
    exit;
}

// ─── 2. Route Requests: Sheet Data vs Date List ──────────────
$action = $_GET['action'] ?? '';
$sheetParam = $_GET['sheet'] ?? '';

// ACTION: Fetch Detailed Grid Data for a Single Sheet
if ($action === 'data' || !empty($sheetParam)) {
    if (empty($apiKey)) {
        http_response_code(500);
        echo json_encode(["error" => "Google Sheets API key is not configured"]);
        exit;
    }

    $targetSheet = trim($sheetParam);
    // Strict Validation: Sheet names must match YYYY/MM/DD
    if (!preg_match('#^\d{4}/\d{2}/\d{2}$#', $targetSheet)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid sheet date format. Expected YYYY/MM/DD."]);
        exit;
    }

    // Enforce that reservations are only allowed for future dates (starting from tomorrow)
    $minDate = date('Y/m/d', strtotime('+1 day'));
    if ($targetSheet < $minDate) {
        http_response_code(400);
        echo json_encode(["error" => "Exam reservations are only available starting from tomorrow onward."]);
        exit;
    }

    // Check if this date is excluded in excluded_days.json
    if (isDateExcluded($targetSheet, $activeCenter['id'])) {
        http_response_code(403);
        echo json_encode(["error" => "No examination is conducted on {$targetSheet} for {$activeCenter['name']} (Excluded Date)."]);
        exit;
    }

    // Lightweight 30-second Server-Side Cache (prevents hitting Google API rate limits)
    $cacheDir = sys_get_temp_dir() . '/nat_test_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheKey = 'sheet_' . md5($activeCenter['id'] . '_' . $spreadsheetId . '_' . $targetSheet) . '.json';
    $cacheFile = $cacheDir . '/' . $cacheKey;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 30)) {
        $cachedData = @file_get_contents($cacheFile);
        if ($cachedData !== false) {
            echo $cachedData;
            exit;
        }
    }

    // Call Google Sheets API v4 Securely Server-Side
    $range = "'" . $targetSheet . "'!A:Z";
    $apiUrl = "https://sheets.googleapis.com/v4/spreadsheets/" . rawurlencode($spreadsheetId)
            . "?includeGridData=true"
            . "&ranges=" . rawurlencode($range)
            . "&fields=" . rawurlencode("sheets(data(rowData(values(formattedValue,effectiveFormat(textFormat(foregroundColor))))))")
            . "&key=" . rawurlencode($apiKey);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        http_response_code($httpCode >= 400 ? $httpCode : 502);
        $decoded = json_decode($response, true);
        $errMsg = $decoded['error']['message'] ?? ($curlErr ?: "Failed to retrieve sheet data from Google API");
        echo json_encode(["error" => $errMsg]);
        exit;
    }

    // Store in cache for 30s
    @file_put_contents($cacheFile, $response);

    echo $response;
    exit;
}

// ─── ACTION: List Available Date Sheets ───────────────────────
// 1. Determine "Today" and "Tomorrow" (enforce reservations strictly from tomorrow onward)
$todayInput = $_GET['today'] ?? '';
if (!empty($todayInput) && preg_match('#^\d{4}[-/]\d{2}[-/]\d{2}$#', $todayInput)) {
    $today = str_replace('-', '/', $todayInput);
} else {
    $today = date('Y/m/d');
}

// Reservations are strictly open from tomorrow onward (no past dates or today)
$tomorrow = date('Y/m/d', strtotime(str_replace('/', '-', $today) . ' +1 day'));
$minDate  = $tomorrow;

// 2. Determine Range Mode
$days  = isset($_GET['days']) ? (int)$_GET['days'] : null;
$range = $_GET['range'] ?? '';

if ($range === '7days')  $days = 7;
if ($range === '15days') $days = 15;

if (!empty($_GET['start']) && !empty($_GET['end'])
    && preg_match('#^\d{4}[-/]\d{2}[-/]\d{2}$#', $_GET['start'])
    && preg_match('#^\d{4}[-/]\d{2}[-/]\d{2}$#', $_GET['end'])) {
    // Custom start and end date (must not be earlier than tomorrow)
    $reqStart  = str_replace('-', '/', $_GET['start']);
    $startDate = max($reqStart, $minDate);
    $endDate   = str_replace('-', '/', $_GET['end']);
    $filterType = 'custom';
} elseif ($days !== null && $days > 0 && $days <= 365) {
    // Next N days starting from tomorrow
    $startDate = $minDate;
    $endDate   = date('Y/m/d', strtotime(str_replace('/', '-', $today) . " +{$days} days"));
    $filterType = 'days';
} else {
    // Default: From tomorrow onward for the current month
    $startDate = $minDate;
    $currentMonthPrefix = substr($today, 0, 7) . '/';
    $filterType = 'month';
}

// 3. Fetch Google Spreadsheet HTML metadata with 60-second caching
$listCacheFile = (sys_get_temp_dir() . '/nat_test_cache') . '/sheet_list_' . md5($activeCenter['id'] . '_' . $spreadsheetId) . '.html';
$html = false;

if (file_exists($listCacheFile) && filesize($listCacheFile) > 1000 && (time() - filemtime($listCacheFile) < 60)) {
    $html = @file_get_contents($listCacheFile);
}

if ($html === false || empty($html) || strlen($html) < 1000) {
    $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit";
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            CURLOPT_TIMEOUT        => 12
        ]);
        $html = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0\r\n"
            ]
        ]);
        $html = @file_get_contents($url, false, $context);
    }

    if (!empty($html) && strlen($html) > 1000) {
        if (!is_dir(dirname($listCacheFile))) {
            @mkdir(dirname($listCacheFile), 0755, true);
        }
        @file_put_contents($listCacheFile, $html);
    }
}

if ($html === false || empty($html)) {
    http_response_code(502);
    echo json_encode(["error" => "Failed to fetch spreadsheet structure."]);
    exit;
}

// 4. Extract all sheets matching YYYY/MM/DD
preg_match_all('/\\\"(\d+)\\\",\[\{\\\"1\\\":\[\[\d+,\d+,\\\"([^\\\"]+)\\\"/', $html, $matches, PREG_SET_ORDER);

$filteredSheets = [];

foreach ($matches as $match) {
    $gid  = $match[1];
    $name = $match[2];

    // Must match date pattern YYYY/MM/DD
    if (preg_match('#^\d{4}/\d{2}/\d{2}$#', $name)) {
        // Enforce: strictly exclude all past dates and today (reservations allowed from tomorrow onward)
        if ($name < $minDate) {
            continue;
        }

        // Skip dates excluded in excluded_days.json
        if (isDateExcluded($name, $activeCenter['id'])) {
            continue;
        }

        if ($filterType === 'month') {
            // Include dates of the current month that are >= tomorrow
            if (str_starts_with($name, $currentMonthPrefix)) {
                $filteredSheets[] = ["name" => $name, "gid" => $gid];
            }
        } else {
            // In range between startDate and endDate
            if ($name >= $startDate && $name <= $endDate) {
                $filteredSheets[] = ["name" => $name, "gid" => $gid];
            }
        }
    }
}

// If current month filter is empty (e.g. today is before/after scheduled dates),
// gracefully return upcoming dates instead of a blank screen
if ($filterType === 'month' && empty($filteredSheets) && !empty($matches)) {
    foreach ($matches as $match) {
        $gid  = $match[1];
        $name = $match[2];
        if (preg_match('#^\d{4}/\d{2}/\d{2}$#', $name) && $name >= $startDate) {
            // Skip dates excluded in excluded_days.json
            if (isDateExcluded($name, $activeCenter['id'])) {
                continue;
            }
            $filteredSheets[] = ["name" => $name, "gid" => $gid];
            if (count($filteredSheets) >= 30) break;
        }
    }
}

echo json_encode($filteredSheets);