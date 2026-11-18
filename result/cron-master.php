<?php
// 1. Error handling & setup
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));

// ─── SECURITY: Restrict Web Access ───────────────────────────
if (!$isCli) {
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

// Set timezone & execution budget
date_default_timezone_set('Asia/Dhaka');
@set_time_limit(360);

// Define internal flag so scripts expose functions without early exit
if (!defined('ADMIN_INTERNAL_EXECUTION')) {
    define('ADMIN_INTERNAL_EXECUTION', true);
}

// Load both scraper engines
require_once __DIR__ . '/cron-job.php';
require_once __DIR__ . '/cbt-cron-job.php';

// Parse arguments
$params = array_change_key_case($_GET ?? [], CASE_LOWER);
if ($isCli && isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $arg) {
        $lower = strtolower(trim($arg));
        if ($lower === '--force' || $lower === '-f' || $lower === 'force' || $lower === 'force=1') {
            $params['force'] = '1';
        } elseif ($lower === '--reset' || $lower === 'reset') {
            $params['action'] = 'reset';
        } elseif ($lower === 'pbt' || $lower === '--pbt') {
            $params['type'] = 'pbt';
        } elseif ($lower === 'cbt' || $lower === '--cbt') {
            $params['type'] = 'cbt';
        } elseif (strpos($arg, '=') !== false) {
            list($k, $v) = explode('=', ltrim($arg, '-'), 2);
            $params[strtolower(trim($k))] = trim($v);
        }
    }
}

$runPbt = true;
$runCbt = true;
if (!empty($params['type'])) {
    if ($params['type'] === 'pbt') $runCbt = false;
    if ($params['type'] === 'cbt') $runPbt = false;
}

// Establish shared DB connection
list($pdo, $dbErr) = getCronDbConnection();

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║          🚀 NAT-TEST MASTER CRON RUNNER (PBT & CBT)              ║\n";
echo "║  Execution: " . date('Y-m-d H:i:s') . " (Asia/Dhaka)                      ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// ── 1. Execute PBT Scraper ──
if ($runPbt && function_exists('executePbtResultScraper')) {
    echo "┌── [1/2] RUNNING PBT (Paper-Based Test) SCRAPER ──────────────\n";
    $pbtResult = executePbtResultScraper($params, $pdo);
    echo trim($pbtResult['output']) . "\n";
    echo "└──────────────────────────────────────────────────────────────\n\n";
} elseif ($runPbt) {
    echo "⚠️ [PBT] executePbtResultScraper function not available.\n\n";
}

// ── 2. Execute CBT Scraper ──
if ($runCbt && function_exists('executeCbtResultScraper')) {
    echo "┌── [2/2] RUNNING CBT (Computer-Based Test) SCRAPER ───────────\n";
    $cbtParams = $params;
    $cbtResult = executeCbtResultScraper($cbtParams, $pdo);
    echo trim($cbtResult['output']) . "\n";
    echo "└──────────────────────────────────────────────────────────────\n\n";
} elseif ($runCbt) {
    echo "⚠️ [CBT] executeCbtResultScraper function not available.\n\n";
}

echo "✓ Master cron execution cycle completed successfully.\n";
exit(0);
