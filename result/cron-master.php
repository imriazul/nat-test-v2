<?php
// 1. Error handling & setup
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$isMasterCli = (php_sapi_name() === 'cli' || defined('STDIN'));

// ─── SECURITY: Restrict Web Access ───────────────────────────
if (!$isMasterCli) {
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
        die("403 Forbidden");
    }
}

// Set timezone & execution budget
date_default_timezone_set('Asia/Dhaka');
@set_time_limit(360);
$startTime = microtime(true);
$runTimestamp = date('Y-m-d H:i:s');

// Define internal flag so scripts expose functions without early exit
if (!defined('ADMIN_INTERNAL_EXECUTION')) {
    define('ADMIN_INTERNAL_EXECUTION', true);
}

// Load both scraper engines
require_once __DIR__ . '/cron-job.php';
require_once __DIR__ . '/cbt-cron-job.php';

// Parse arguments
$params = array_change_key_case($_GET ?? [], CASE_LOWER);
if ($isMasterCli && isset($argv) && is_array($argv)) {
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
$isForce = !empty($params['force']);

// Establish shared DB connection
list($pdo, $dbErr) = getCronDbConnection();

// ── 1. Execute PBT Scraper ──
$pbtResult = null;
if ($runPbt && function_exists('executePbtResultScraper')) {
    $pbtResult = executePbtResultScraper($params, $pdo);
} elseif ($runPbt) {
    $pbtResult = [
        'success' => false,
        'status'  => 'ERROR',
        'output'  => 'executePbtResultScraper function not available.',
        'logs'    => ['executePbtResultScraper function not available.']
    ];
}

// ── 2. Execute CBT Scraper ──
$cbtResult = null;
if ($runCbt && function_exists('executeCbtResultScraper')) {
    $cbtParams = $params;
    $cbtResult = executeCbtResultScraper($cbtParams, $pdo);
} elseif ($runCbt) {
    $cbtResult = [
        'success' => false,
        'status'  => 'ERROR',
        'output'  => 'executeCbtResultScraper function not available.',
        'logs'    => ['executeCbtResultScraper function not available.']
    ];
}

$duration = round(microtime(true) - $startTime, 2);

// ══════════════════════════════════════════════════════════════
// CLI OUTPUT (For cPanel Cron / Terminal Execution)
// ══════════════════════════════════════════════════════════════
if ($isMasterCli) {
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║          🚀 NAT-TEST MASTER CRON RUNNER (PBT & CBT)              ║\n";
    echo "║  Execution: {$runTimestamp} (Asia/Dhaka)                      ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

    if ($runPbt && $pbtResult) {
        echo "┌── [1/2] RUNNING PBT (Paper-Based Test) SCRAPER ──────────────\n";
        echo trim($pbtResult['output'] ?? '') . "\n";
        echo "└──────────────────────────────────────────────────────────────\n\n";
    }

    if ($runCbt && $cbtResult) {
        echo "┌── [2/2] RUNNING CBT (Computer-Based Test) SCRAPER ───────────\n";
        echo trim($cbtResult['output'] ?? '') . "\n";
        echo "└──────────────────────────────────────────────────────────────\n\n";
    }

    echo "✓ Master cron execution completed in {$duration}s.\n";
    exit(0);
}

// ══════════════════════════════════════════════════════════════
// WEB UI FOR LOGGED-IN ADMINS
// ══════════════════════════════════════════════════════════════
function getScraperMeta($status) {
    switch (strtoupper($status ?? '')) {
        case 'SUCCESS':
        case 'COMPLETED':
            return [
                'badge' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                'dot'   => 'bg-emerald-400',
                'title' => 'Completed / Synced',
                'desc'  => 'Official results successfully found and synced into database.'
            ];
        case 'STOPPED_COMPLETED':
            return [
                'badge' => 'bg-teal-500/15 text-teal-400 border-teal-500/30',
                'dot'   => 'bg-teal-400',
                'title' => 'Halted (Already Synced)',
                'desc'  => 'Target results already processed. Auto-halted to save server resources.'
            ];
        case 'WAITING':
            return [
                'badge' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                'dot'   => 'bg-amber-400',
                'title' => 'Waiting (Not Published Yet)',
                'desc'  => 'nat-test.jp cutoff matches known data. Standing by for next cycle.'
            ];
        case 'DISABLED':
            return [
                'badge' => 'bg-slate-700/50 text-slate-300 border-slate-600',
                'dot'   => 'bg-slate-400',
                'title' => 'Cron Disabled',
                'desc'  => 'Scraper is disabled in Admin Panel. Run with ?force=1 to test now.'
            ];
        case 'RESET':
            return [
                'badge' => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
                'dot'   => 'bg-blue-400',
                'title' => 'Cutoff Reset',
                'desc'  => 'Tracking lock was reset by administrator.'
            ];
        case 'ERROR':
        default:
            return [
                'badge' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                'dot'   => 'bg-rose-400',
                'title' => 'Error / Attention',
                'desc'  => 'Encountered network or scraping issue while contacting nat-test.jp.'
            ];
    }
}

$pbtMeta = $pbtResult ? getScraperMeta($pbtResult['status'] ?? '') : null;
$cbtMeta = $cbtResult ? getScraperMeta($cbtResult['status'] ?? '') : null;
$cronCommand = '/usr/local/bin/php ' . __FILE__ . ' >/dev/null 2>&1';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Master Cron Runner | NAT-TEST BD Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        code, pre { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 flex flex-col antialiased selection:bg-blue-500 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="bg-slate-900/90 backdrop-blur border-b border-slate-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-purple-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold text-white tracking-tight">NAT-TEST Master Cron Engine</h1>
                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Live</span>
                    </div>
                    <p class="text-xs text-slate-400">PBT &amp; CBT Automated Results Scraper Monitor</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="../admin/scrapers" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Scrapers Dashboard</span>
                </a>
                <a href="cron-master.php" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-500 text-white transition flex items-center gap-2 shadow-sm shadow-blue-600/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>Run Again</span>
                </a>
                <a href="cron-master.php?force=1" class="px-3 py-2 text-xs font-semibold rounded-xl bg-amber-600 hover:bg-amber-500 text-white transition flex items-center gap-1.5 shadow-sm" title="Force execute both scrapers ignoring locks">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>Force Run</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">

        <!-- Execution KPI Header Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl relative overflow-hidden">
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-purple-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-semibold tracking-wider uppercase text-slate-400">Execution Completed</span>
                        <?php if ($isForce): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">Force Mode Active</span>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-2xl font-bold text-white tracking-tight">Master Runner Execution Cycle</h2>
                    <p class="text-sm text-slate-400 mt-1">Both PBT and CBT scrapers executed synchronously in single process.</p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div class="bg-slate-950/60 border border-slate-800/80 rounded-xl p-3.5">
                        <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">Executed At</div>
                        <div class="text-sm font-bold text-slate-200 mt-1"><?php echo date('H:i:s', strtotime($runTimestamp)); ?></div>
                        <div class="text-[10px] text-slate-500"><?php echo date('d M Y', strtotime($runTimestamp)); ?></div>
                    </div>
                    <div class="bg-slate-950/60 border border-slate-800/80 rounded-xl p-3.5">
                        <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">Time Taken</div>
                        <div class="text-sm font-bold text-cyan-400 mt-1"><?php echo $duration; ?>s</div>
                        <div class="text-[10px] text-slate-500">Fast In-Process</div>
                    </div>
                    <div class="bg-slate-950/60 border border-slate-800/80 rounded-xl p-3.5">
                        <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">PBT Scraper</div>
                        <div class="text-sm font-bold mt-1 <?php echo strpos($pbtResult['status'] ?? '', 'COMPLETED') !== false ? 'text-emerald-400' : 'text-amber-400'; ?>">
                            <?php echo htmlspecialchars($pbtResult['status'] ?? 'N/A'); ?>
                        </div>
                        <div class="text-[10px] text-slate-500">Paper-Based</div>
                    </div>
                    <div class="bg-slate-950/60 border border-slate-800/80 rounded-xl p-3.5">
                        <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">CBT Scraper</div>
                        <div class="text-sm font-bold mt-1 <?php echo strpos($cbtResult['status'] ?? '', 'COMPLETED') !== false ? 'text-emerald-400' : 'text-purple-400'; ?>">
                            <?php echo htmlspecialchars($cbtResult['status'] ?? 'N/A'); ?>
                        </div>
                        <div class="text-[10px] text-slate-500">Computer-Based</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Scrapers Side-by-Side Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Card 1: PBT Scraper -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl flex flex-col shadow-lg overflow-hidden">
                <!-- Card Header -->
                <div class="p-5 bg-gradient-to-r from-blue-950/40 to-slate-900 border-b border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-white text-base">PBT Result Scraper</h3>
                                <span class="text-xs text-slate-500">[1/2]</span>
                            </div>
                            <p class="text-xs text-slate-400">Paper-Based Test Official Results</p>
                        </div>
                    </div>
                    <?php if ($pbtMeta): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold border flex items-center gap-1.5 <?php echo $pbtMeta['badge']; ?>">
                            <span class="w-2 h-2 rounded-full <?php echo $pbtMeta['dot']; ?>"></span>
                            <?php echo $pbtMeta['title']; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Card Body / Target Info -->
                <div class="p-5 space-y-4 flex-1">
                    <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800/80 text-xs flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <span class="text-slate-400">Session Target:</span>
                            <span class="font-semibold text-slate-200 ml-1">
                                <?php 
                                    $pbtMonth = $pbtResult['config']['target_month'] ?? '10';
                                    $pbtYear = $pbtResult['config']['target_year'] ?? date('Y');
                                    $pbtCenter = $pbtResult['config']['center_code'] ?? '10101';
                                    echo date('F', mktime(0, 0, 0, (int)$pbtMonth, 10)) . " {$pbtYear} (Center {$pbtCenter})";
                                ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400">Cron Status:</span>
                            <span class="font-semibold ml-1 <?php echo !empty($pbtResult['config']['enabled']) ? 'text-emerald-400' : 'text-slate-400'; ?>">
                                <?php echo !empty($pbtResult['config']['enabled']) ? '● Enabled' : '○ Disabled'; ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($pbtMeta): ?>
                        <div class="text-xs text-slate-300 bg-blue-950/20 border border-blue-900/30 rounded-xl p-3 flex items-start gap-2">
                            <span class="text-blue-400 font-bold mt-0.5">ℹ</span>
                            <span><?php echo htmlspecialchars($pbtMeta['desc']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Log Console -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                                Live Execution Log
                            </span>
                            <button type="button" onclick="copyConsoleLogs('pbt-logs', this)" class="text-[11px] text-slate-400 hover:text-white px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 transition cursor-pointer">
                                Copy Log
                            </button>
                        </div>
                        <pre id="pbt-logs" class="p-4 rounded-xl bg-slate-950 border border-slate-800/90 text-cyan-300 text-[11px] leading-relaxed overflow-x-auto max-h-56 select-all font-mono whitespace-pre-wrap"><?php echo htmlspecialchars(trim($pbtResult['output'] ?? 'No logs recorded.')); ?></pre>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="p-4 bg-slate-950/60 border-t border-slate-800/70 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Run individual test:</span>
                    <a href="cron-master.php?type=pbt" class="px-3 py-1.5 rounded-lg bg-blue-600/20 text-blue-400 hover:bg-blue-600/30 font-medium transition">Run PBT Only →</a>
                </div>
            </div>

            <!-- Card 2: CBT Scraper -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl flex flex-col shadow-lg overflow-hidden">
                <!-- Card Header -->
                <div class="p-5 bg-gradient-to-r from-purple-950/40 to-slate-900 border-b border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-white text-base">CBT Result Scraper</h3>
                                <span class="text-xs text-slate-500">[2/2]</span>
                            </div>
                            <p class="text-xs text-slate-400">Computer-Based Test Weekly Results</p>
                        </div>
                    </div>
                    <?php if ($cbtMeta): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold border flex items-center gap-1.5 <?php echo $cbtMeta['badge']; ?>">
                            <span class="w-2 h-2 rounded-full <?php echo $cbtMeta['dot']; ?>"></span>
                            <?php echo $cbtMeta['title']; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Card Body / Target Info -->
                <div class="p-5 space-y-4 flex-1">
                    <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800/80 text-xs flex items-center justify-between flex-wrap gap-2">
                        <div class="truncate max-w-full">
                            <span class="text-slate-400">Current Cutoff:</span>
                            <span class="font-semibold text-slate-200 ml-1 truncate">
                                <?php 
                                    $cbtCutoff = $cbtResult['config']['last_published_cutoff'] ?? '';
                                    echo htmlspecialchars($cbtCutoff ?: 'Automatic nat-test.jp Weekly Cutoff');
                                ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400">Cron Status:</span>
                            <span class="font-semibold ml-1 <?php echo !empty($cbtResult['config']['enabled']) ? 'text-purple-400' : 'text-slate-400'; ?>">
                                <?php echo !empty($cbtResult['config']['enabled']) ? '● Enabled' : '○ Disabled'; ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($cbtMeta): ?>
                        <div class="text-xs text-slate-300 bg-purple-950/20 border border-purple-900/30 rounded-xl p-3 flex items-start gap-2">
                            <span class="text-purple-400 font-bold mt-0.5">ℹ</span>
                            <span><?php echo htmlspecialchars($cbtMeta['desc']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Log Console -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
                                Live Execution Log
                            </span>
                            <button type="button" onclick="copyConsoleLogs('cbt-logs', this)" class="text-[11px] text-slate-400 hover:text-white px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 transition cursor-pointer">
                                Copy Log
                            </button>
                        </div>
                        <pre id="cbt-logs" class="p-4 rounded-xl bg-slate-950 border border-slate-800/90 text-purple-300 text-[11px] leading-relaxed overflow-x-auto max-h-56 select-all font-mono whitespace-pre-wrap"><?php echo htmlspecialchars(trim($cbtResult['output'] ?? 'No logs recorded.')); ?></pre>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="p-4 bg-slate-950/60 border-t border-slate-800/70 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Run individual test:</span>
                    <a href="cron-master.php?type=cbt" class="px-3 py-1.5 rounded-lg bg-purple-600/20 text-purple-400 hover:bg-purple-600/30 font-medium transition">Run CBT Only →</a>
                </div>
            </div>

        </div>

        <!-- Automation & Server Crontab Reference Box -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-md">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-slate-200 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        cPanel Master Cron Configuration
                    </h4>
                    <p class="text-xs text-slate-400">
                        This single command runs both PBT &amp; CBT in one scheduled cron execution without shell/exec restrictions.
                    </p>
                </div>
                <button type="button" onclick="copyConsoleLogs('cron-cmd-box', this)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-medium transition cursor-pointer shrink-0">
                    Copy Cron Command
                </button>
            </div>

            <div class="mt-3">
                <code id="cron-cmd-box" class="block w-full p-3 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-xs font-mono select-all overflow-x-auto whitespace-nowrap"><?php echo htmlspecialchars($cronCommand); ?></code>
            </div>
        </div>

    </main>

    <!-- Page Footer -->
    <footer class="bg-slate-900/60 border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
        NAT-TEST Bangladesh &bull; Official Result Automation Engine &bull; Server Time: <?php echo date('Y-m-d H:i:s T'); ?>
    </footer>

    <!-- Copy Helper Script -->
    <script>
        function copyConsoleLogs(elementId, btn) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.innerText || el.textContent;
            navigator.clipboard.writeText(text).then(() => {
                const orig = btn.innerText;
                btn.innerText = '✓ Copied!';
                btn.classList.add('text-emerald-400');
                setTimeout(() => {
                    btn.innerText = orig;
                    btn.classList.remove('text-emerald-400');
                }, 2000);
            }).catch(() => {
                alert('Copy failed. Please select text manually.');
            });
        }
    </script>
</body>
</html>
