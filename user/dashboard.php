<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// Strict Authentication Guard
$isUserLoggedIn = (isset($_SESSION[$user_session_name]) && $_SESSION[$user_session_name] === true)
               || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

if (!$isUserLoggedIn) {
    header("Location: login");
    exit;
}

// Dynamically compute base URL and paths
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

$userName = $_SESSION[$user_session_name . '_name'] ?? (!empty($_SESSION['admin_logged_in']) ? 'Administrator' : 'Authorized Member');
$userInstitute = $_SESSION[$user_session_name . '_institute'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>CBT Seat Availability Checker | NAT-TEST BD</title>
    <meta name="description" content="Check real-time Japanese language CBT NAT-TEST seat availability across examination dates and sessions in Bangladesh.">

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
            background-color: #111827; /* Dark slate */
        }
        .desk {
            transition: all 0.2s ease-in-out;
        }
        .desk:active {
            transform: scale(0.94);
        }
        .glow-green {
            box-shadow: 0 0 14px rgba(34, 197, 94, 0.2);
        }
        .glow-gray {
            box-shadow: 0 0 14px rgba(156, 163, 175, 0.2);
        }
        .glow-red {
            box-shadow: 0 0 18px rgba(239, 68, 68, 0.35);
        }
        .glow-amber {
            box-shadow: 0 0 16px rgba(245, 158, 11, 0.35);
        }
        input[type="date"] {
            color-scheme: dark;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
            opacity: 0.9;
        }
        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }
        ::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #1f2937;
        }
        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }
    </style>
</head>

<body class="min-h-screen p-4 md:p-8 flex flex-col items-center text-gray-100">

    <div class="max-w-6xl w-full">

        <!-- Top Navigation Bar -->
        <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
            <a href="<?php echo htmlspecialchars($appBaseDir ?: '/'); ?>" class="inline-flex items-center gap-2 text-xs font-semibold text-gray-400 hover:text-cyan-400 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>Back to Homepage</span>
            </a>
            <div class="flex items-center gap-2.5">
                <a href="<?php echo htmlspecialchars($appBaseDir . '/result'); ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-800 hover:bg-gray-700 text-cyan-400 border border-gray-700 px-3 py-1.5 rounded-lg transition">
                    <span>Check Result</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
                <div class="hidden sm:inline-flex items-center gap-1.5 text-xs text-gray-300 bg-gray-900 border border-gray-700/80 px-2.5 py-1.5 rounded-lg shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Logged in: <strong class="text-cyan-300"><?php echo htmlspecialchars($userName); ?></strong><?php if (!empty($userInstitute)): ?> <span class="text-gray-400 font-normal">(<?php echo htmlspecialchars($userInstitute); ?>)</span><?php endif; ?></span>
                </div>
                <a href="login?logout=1" class="inline-flex items-center gap-1 text-xs font-semibold bg-red-950/40 hover:bg-red-900/60 text-red-400 hover:text-red-300 border border-red-800/60 px-3 py-1.5 rounded-lg transition cursor-pointer" title="Log out from user portal">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="text-3xl md:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-teal-300 to-pink-500 mb-2">
                CBT Seat Availability Checker
            </h1>
            <p class="text-gray-400 text-sm md:text-base">Real-time CBT Seat Availability Verification &bull; NAT-TEST BD</p>
        </div>

        <!-- Date & Session Selector Bar -->
        <div class="bg-gray-800 rounded-2xl border border-gray-700 p-4 mb-6 shadow-xl">

            <!-- Step 1: Exam Center / Venue Selector -->
            <div class="flex items-center gap-2 flex-wrap pb-3.5 mb-3.5 border-b border-gray-700/80">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider mr-1">1. Center:</span>
                <button id="siteBtn-dhaka1"
                    class="site-btn px-3.5 py-1.5 text-xs font-semibold rounded-lg border border-gray-600 bg-gray-900 text-gray-300 hover:border-cyan-400 transition"
                    onclick="switchSite('dhaka1', this)">
                    ULSAI (Dhaka)
                </button>
                <button id="siteBtn-khulna"
                    class="site-btn px-3.5 py-1.5 text-xs font-semibold rounded-lg border border-gray-600 bg-gray-900 text-gray-300 hover:border-cyan-400 transition"
                    onclick="switchSite('khulna', this)">
                    KHULNA
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">

                <!-- Step 2: Date Dropdown -->
                <div class="flex items-center gap-2 flex-1 min-w-[220px] max-w-sm">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">2. Date:</span>
                    <select id="dateSelect" disabled
                        class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-1.5 text-xs sm:text-sm text-cyan-400 font-semibold focus:outline-none focus:border-cyan-400 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        onchange="onDateSelected()">
                        <option value="">-- Select Center First --</option>
                    </select>
                </div>

                <!-- Step 3: Session / Time Slot Tabs -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">3. Slot:</span>
                    <div id="slotContainer" class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs text-gray-500 font-mono italic">Select Date first</span>
                    </div>
                </div>

                <!-- Range Buttons -->
                <div class="flex items-center gap-1.5 flex-wrap ml-auto">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider mr-1">Range:</span>
                    <button id="btnMonth"
                        class="range-btn px-2.5 py-1 text-xs font-medium rounded-lg border border-cyan-400 bg-cyan-950 text-cyan-300 font-semibold transition"
                        onclick="setRangeParam('range=month', this)">This Month</button>
                    <button id="btn7Days"
                        class="range-btn px-2.5 py-1 text-xs font-medium rounded-lg border border-gray-600 bg-gray-900 text-gray-300 hover:border-cyan-400 transition"
                        onclick="setRangeParam('days=7', this)">7 Days</button>
                    <button id="btn15Days"
                        class="range-btn px-2.5 py-1 text-xs font-medium rounded-lg border border-gray-600 bg-gray-900 text-gray-300 hover:border-cyan-400 transition"
                        onclick="setRangeParam('days=15', this)">15 Days</button>
                </div>

            </div>

            <!-- Status Banner -->
            <div id="statusBanner" class="mt-3.5 pt-3 border-t border-gray-700/80 text-xs text-gray-400 flex items-center justify-between">
                <span id="statusText">Please select an Exam Center to begin.</span>
                <span id="siteInfo" class="text-cyan-400 font-mono"></span>
            </div>
        </div>

        <!-- Floor Plan Card Container -->
        <div class="bg-gray-800 p-5 sm:p-8 rounded-3xl shadow-2xl border border-gray-700 w-full mb-10">

            <!-- A. Empty / Initial Prompt State (Shown initially before selection) -->
            <div id="emptyPromptState" class="py-14 sm:py-20 px-4 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-cyan-950/60 border border-cyan-800/80 flex items-center justify-center text-cyan-400 shadow-lg shadow-cyan-950/50">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"></line>
                        <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"></line>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-200 mb-1">Select Center &gt; Date &gt; Slot</h3>
                <p class="text-xs text-gray-400 max-w-md mx-auto leading-relaxed">
                    Choose an examination center, test date, and session slot above to view real-time Seat availability.
                </p>
                <div class="flex items-center justify-center gap-2 mt-4 text-[11px] font-mono text-cyan-400/80">
                    <span class="px-2 py-0.5 rounded bg-gray-900 border border-gray-700">1. Center</span>
                    <span>&rarr;</span>
                    <span class="px-2 py-0.5 rounded bg-gray-900 border border-gray-700">2. Date</span>
                    <span>&rarr;</span>
                    <span class="px-2 py-0.5 rounded bg-gray-900 border border-gray-700">3. Slot</span>
                </div>
            </div>

            <!-- B. Loading Effect State (Shown while fetching data) -->
            <div id="loadingState" class="hidden py-10 px-4">
                <div class="flex items-center justify-center gap-2.5 text-cyan-400 font-semibold text-xs sm:text-sm mb-6">
                    <svg class="w-4 h-4 animate-spin text-cyan-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span id="loadingMessage">Fetching real-time Seat availability...</span>
                </div>
                <!-- Shimmer Skeleton Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4 justify-items-center">
                    <div class="animate-pulse bg-gray-900/70 border border-gray-700/60 rounded-2xl p-4 flex flex-col items-center justify-center aspect-square max-w-[130px] w-full">
                        <div class="w-8 h-8 rounded-lg bg-gray-700/50 mb-2"></div>
                        <div class="w-10 h-2.5 rounded bg-gray-700/50 mb-1"></div>
                        <div class="w-14 h-2 rounded bg-gray-800"></div>
                    </div>
                    <div class="animate-pulse bg-gray-900/70 border border-gray-700/60 rounded-2xl p-4 flex flex-col items-center justify-center aspect-square max-w-[130px] w-full">
                        <div class="w-8 h-8 rounded-lg bg-gray-700/50 mb-2"></div>
                        <div class="w-10 h-2.5 rounded bg-gray-700/50 mb-1"></div>
                        <div class="w-14 h-2 rounded bg-gray-800"></div>
                    </div>
                    <div class="animate-pulse bg-gray-900/70 border border-gray-700/60 rounded-2xl p-4 flex flex-col items-center justify-center aspect-square max-w-[130px] w-full">
                        <div class="w-8 h-8 rounded-lg bg-gray-700/50 mb-2"></div>
                        <div class="w-10 h-2.5 rounded bg-gray-700/50 mb-1"></div>
                        <div class="w-14 h-2 rounded bg-gray-800"></div>
                    </div>
                    <div class="animate-pulse bg-gray-900/70 border border-gray-700/60 rounded-2xl p-4 flex flex-col items-center justify-center aspect-square max-w-[130px] w-full">
                        <div class="w-8 h-8 rounded-lg bg-gray-700/50 mb-2"></div>
                        <div class="w-10 h-2.5 rounded bg-gray-700/50 mb-1"></div>
                        <div class="w-14 h-2 rounded bg-gray-800"></div>
                    </div>
                    <div class="animate-pulse bg-gray-900/70 border border-gray-700/60 rounded-2xl p-4 flex flex-col items-center justify-center aspect-square max-w-[130px] w-full">
                        <div class="w-8 h-8 rounded-lg bg-gray-700/50 mb-2"></div>
                        <div class="w-10 h-2.5 rounded bg-gray-700/50 mb-1"></div>
                        <div class="w-14 h-2 rounded bg-gray-800"></div>
                    </div>
                    <div class="animate-pulse bg-gray-900/70 border border-gray-700/60 rounded-2xl p-4 flex flex-col items-center justify-center aspect-square max-w-[130px] w-full">
                        <div class="w-8 h-8 rounded-lg bg-gray-700/50 mb-2"></div>
                        <div class="w-10 h-2.5 rounded bg-gray-700/50 mb-1"></div>
                        <div class="w-14 h-2 rounded bg-gray-800"></div>
                    </div>
                </div>
            </div>

            <!-- C. Loaded Floor Plan Content (Only visible when data is fully loaded!) -->
            <div id="floorPlanContent" class="hidden">

                <!-- Legend Inside Card -->
                <div class="flex justify-center items-center flex-wrap gap-4 md:gap-6 mb-6 pb-4 border-b border-gray-700/80">
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg border border-green-500/50 bg-gray-900 flex items-center justify-center glow-green">
                            <span class="w-2 h-2 rounded-full bg-green-400"></span>
                        </div>
                        <span class="text-xs font-medium text-gray-300">Available</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg border border-gray-500/50 bg-gray-900 flex items-center justify-center glow-gray">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        </div>
                        <span class="text-xs font-medium text-gray-300">Pending</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg border border-amber-500/50 bg-gray-900 flex items-center justify-center glow-amber">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        </div>
                        <span class="text-xs font-medium text-amber-300 font-semibold">15-Min Hold</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg border border-red-500/80 bg-gray-900 flex items-center justify-center glow-red">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        </div>
                        <span class="text-xs font-medium text-gray-300">Booked / Paid</span>
                    </div>
                </div>

                <!-- Header with Seat Counters -->
                <div class="flex flex-wrap justify-between items-center mb-6 border-b border-gray-700/80 pb-3 gap-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-base sm:text-lg font-bold text-gray-200">Station Floor Plan</h2>
                        <span id="activeSlotBadge" class="text-xs bg-cyan-950 text-cyan-400 border border-cyan-800 px-2.5 py-0.5 rounded-full font-mono"></span>
                    </div>

                    <!-- Dynamic Seat Stats -->
                    <div class="flex items-center gap-2 sm:gap-3 text-xs font-mono bg-gray-900/80 px-3.5 py-1.5 rounded-xl border border-gray-700/80">
                        <span class="text-gray-300">Total: <strong id="totalSeatsCount" class="text-cyan-400 font-bold">0</strong></span>
                        <span class="text-gray-600">|</span>
                        <span class="text-red-400 font-semibold">&#x1F534; <span id="bookedSeatsCount">0</span> Paid</span>
                        <span class="text-gray-400 font-semibold">&#x26AA; <span id="pendingSeatsCount">0</span> Pending</span>
                        <span class="text-amber-400 font-semibold">&#x1F7E1; <span id="holdingSeatsCount">0</span> Hold</span>
                        <span class="text-green-400 font-semibold">&#x1F7E2; <span id="availableSeatsCount">0</span> Available</span>
                    </div>
                </div>

                <!-- Dynamic Station Desks Grid -->
                <div id="floor-plan" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4 justify-items-center">
                    <!-- Desks injected dynamically -->
                </div>
            </div>

        </div>

    </div>

    <!-- Details Modal -->
    <div id="detailModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4 z-50"
        onclick="closeModal(event)">
        <div class="bg-gray-800 border border-gray-700 max-w-sm w-full rounded-2xl p-6 shadow-2xl relative"
            onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalStationTitle" class="text-lg font-black text-cyan-400">Desk-01</h3>
                <span id="modalStatusBadge" class="text-xs font-bold px-2.5 py-0.5 rounded-full">Booked</span>
            </div>
            <div class="space-y-3 text-sm" id="modalBody">
                <!-- Injected dynamically -->
            </div>
            <button onclick="closeModal()"
                class="w-full mt-5 bg-gray-700 hover:bg-gray-600 text-gray-200 font-bold py-2 rounded-xl transition text-xs">
                Close
            </button>
        </div>
    </div>

    <!-- ═══ 2-Step CBT Seat Booking Modal ═══ -->
    <div id="bookingModal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-gray-800 border border-gray-700 max-w-lg w-full rounded-3xl p-6 sm:p-7 shadow-2xl relative my-8" onclick="event.stopPropagation()">
            
            <!-- Modal Header -->
            <div class="flex justify-between items-start gap-3 mb-5 border-b border-gray-700 pb-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                        <span id="bmStationBadge" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-lg bg-cyan-950 text-cyan-300 border border-cyan-700">Desk-01</span>
                        <span id="bmSiteBadge" class="text-xs font-semibold px-2.5 py-0.5 rounded-lg bg-gray-900 text-gray-300 border border-gray-700">Khulna (Center 476)</span>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h3 class="text-xl font-black text-gray-100 flex items-center gap-2">
                            <span>CBT Registration</span>
                        </h3>
                        <!-- 15-Minute Countdown Lock Banner (Appears in Step 2) -->
                        <div id="bmLockBanner" class="hidden items-center gap-1.5 px-2.5 py-1 rounded-xl bg-gradient-to-r from-amber-950/90 to-amber-900/60 border border-amber-500/80 text-amber-200 text-xs shadow-sm">
                            <svg class="w-3.5 h-3.5 text-amber-400 shrink-0 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"></path></svg>
                            <span class="font-medium text-amber-300">Remaining:</span>
                            <span id="bmTimerDisplay" class="font-mono text-xs font-black px-1.5 py-0.5 rounded-md bg-amber-950 border border-amber-400/80 text-amber-300">14:59</span>
                        </div>
                    </div>
                    <p id="bmSubheader" class="text-xs text-gray-400 font-mono mt-0.5">2026-12-05 • Session 2 (12:00 ~ 14:30)</p>
                </div>
                <button type="button" onclick="closeBookingModal()" class="w-8 h-8 rounded-full bg-gray-900 hover:bg-gray-700 text-gray-400 hover:text-gray-200 flex items-center justify-center transition shrink-0" title="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Step Progress Indicator -->
            <div class="grid grid-cols-2 gap-2 mb-5">
                <div id="bmStepTab1" class="p-2.5 rounded-xl border border-cyan-400 bg-cyan-950/60 text-cyan-300 text-xs font-bold flex items-center gap-2 transition">
                    <span class="w-5 h-5 rounded-full bg-cyan-400 text-gray-950 flex items-center justify-center text-[10px] font-black">1</span>
                    <span>Exam Level</span>
                </div>
                <div id="bmStepTab2" class="p-2.5 rounded-xl border border-gray-700 bg-gray-900/60 text-gray-500 text-xs font-bold flex items-center gap-2 transition">
                    <span class="w-5 h-5 rounded-full bg-gray-700 text-gray-400 flex items-center justify-center text-[10px] font-black">2</span>
                    <span>Candidate Info</span>
                </div>
            </div>

            <!-- Error Banner -->
            <div id="bmErrorBanner" class="hidden mb-4 p-3 rounded-xl bg-red-950/70 border border-red-800 text-red-300 text-xs flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="bmErrorText"></span>
            </div>

            <!-- Notice Banner (For pre-reserved seats) -->
            <div id="bmNoticeBanner" class="hidden mb-4 p-3 rounded-xl bg-cyan-950/70 border border-cyan-800 text-cyan-300 text-xs flex items-start gap-2">
                <svg class="w-4 h-4 text-cyan-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="bmNoticeText"></span>
            </div>

            <!-- ── STEP 1: Select Exam Level ── -->
            <div id="bmStep1Content" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                        Select Examination Level <span class="text-red-400">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2.5" id="bmLevelGrid">
                        <button type="button" onclick="selectExamLevel('5Q')" class="bm-level-btn p-3 rounded-xl border border-gray-700 bg-gray-900 text-gray-300 font-bold text-left transition hover:border-gray-500" data-level="5Q">
                            <div class="text-sm font-black">5Q (N5)</div>
                            <div class="text-[10px] text-gray-400 font-normal">Introductory</div>
                        </button>
                        <button type="button" onclick="selectExamLevel('4Q')" class="bm-level-btn p-3 rounded-xl border border-gray-700 bg-gray-900 text-gray-300 font-bold text-left transition hover:border-gray-500" data-level="4Q">
                            <div class="text-sm font-black">4Q (N4)</div>
                            <div class="text-[10px] text-gray-400 font-normal">Basic</div>
                        </button>
                        <button type="button" onclick="selectExamLevel('3Q')" class="bm-level-btn p-3 rounded-xl border border-gray-700 bg-gray-900 text-gray-300 font-bold text-left transition hover:border-gray-500" data-level="3Q">
                            <div class="text-sm font-black">3Q (N3)</div>
                            <div class="text-[10px] text-gray-400 font-normal">Intermediate</div>
                        </button>
                    </div>
                </div>

                <!-- Seat Payload Preview -->
                <div class="p-3.5 rounded-2xl bg-gray-900/80 border border-gray-700 text-xs space-y-1.5 font-mono text-gray-300">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Exam Center:</span>
                        <span id="bmPreviewCenter" class="text-gray-200 font-bold">Khulna (476)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Exam Date:</span>
                        <span id="bmPreviewDate" class="text-gray-200 font-bold">2026-12-05</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Session Slot:</span>
                        <span id="bmPreviewSlot" class="text-cyan-400 font-bold">Slot 2</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Seat:</span>
                        <span id="bmPreviewPc" class="text-green-400 font-bold">Desk-01</span>
                    </div>
                </div>

                <button id="bmConfirmLevelBtn" type="button" disabled onclick="handleConfirmLevel()" class="w-full mt-2 bg-gray-700 text-gray-400 font-black py-3 px-4 rounded-xl shadow-none transition text-sm flex items-center justify-center gap-2 opacity-60 cursor-not-allowed">
                    <span id="bmConfirmBtnText">Select Exam Level Above</span>
                    <svg id="bmConfirmSpinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                </button>
            </div>

            <!-- ── STEP 2: Candidate Personal Information ── -->
            <form id="bmStep2Form" class="hidden space-y-3.5" onsubmit="handleFinalSubmit(event)">
                <!-- Assigned Examinee ID & Fee Display -->
                <div class="p-3 rounded-2xl bg-gray-900 border border-cyan-800 flex justify-between items-center text-xs">
                    <div>
                        <span class="text-gray-400 block text-[10px] uppercase tracking-wider">Official Examinee ID:</span>
                        <span id="bmAssignedId" class="text-cyan-300 font-mono font-black text-sm tracking-wide">26120547651010</span>
                    </div>
                    <div class="text-right">
                        <span class="text-gray-400 block text-[10px] uppercase tracking-wider">Test Fee:</span>
                        <span id="bmAssignedFee" class="text-emerald-400 font-bold text-sm">N/A</span>
                    </div>
                </div>

                <!-- Workstation Auto-Assignment Notice (When official server assigns earlier available desk) -->
                <div id="bmDeskSwitchedBanner" class="hidden p-3 rounded-2xl bg-blue-950/80 border border-blue-500/80 text-blue-200 text-xs flex items-start gap-2.5 shadow-sm">
                    <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <span class="font-bold text-blue-300 block mb-0.5">Workstation Auto-Assigned</span>
                        <span id="bmDeskSwitchedText">The official examination server allocates desks sequentially. Workstation Desk-01 has been assigned for you.</span>
                    </div>
                </div>

                <!-- Candidate Inputs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                            Candidate Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" id="bmCandidateName" required placeholder="MD RAHIM UDDIN"
                            class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-cyan-400 transition uppercase"
                            oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                            Date of Birth <span class="text-red-400">*</span>
                        </label>
                        <input type="date" id="bmCandidateBirthday" required
                            class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-cyan-400 transition [color-scheme:dark]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                            Phone Number <span class="text-red-400">*</span>
                        </label>
                        <input type="tel" id="bmCandidatePhone" required placeholder="017XXXXXXXX"
                            maxlength="11"
                            pattern="01[3-9][0-9]{8}"
                            title="11-digit mobile number starting with 013-019 (e.g. 01712345678)"
                            class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-cyan-400 transition font-mono"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <p class="text-[10px] text-gray-500 mt-1">11 digits starting with 013–019</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                            Email Address <span class="text-red-400">*</span>
                        </label>
                        <input type="email" id="bmCandidateEmail" required placeholder="candidate@example.com"
                            class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-cyan-400 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                        Residential Address <span class="text-red-400">*</span>
                    </label>
                    <input type="text" id="bmCandidateAddress" required placeholder="Street, Area, City"
                        class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-cyan-400 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                            Zip / Postal Code <span class="text-red-400">*</span>
                        </label>
                        <input type="text" id="bmCandidateZipcode" required placeholder="1112311"
                            class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-100 placeholder:text-gray-500 focus:outline-none focus:border-cyan-400 transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-1">
                            Country <span class="text-red-400">*</span>
                        </label>
                        <input type="text" id="bmCandidateCountry" value="BANGLADESH" readonly tabindex="-1"
                            class="w-full bg-gray-900/60 border border-gray-700/80 rounded-xl px-3 py-2 text-xs text-gray-400 cursor-not-allowed select-none focus:outline-none pointer-events-none">
                    </div>
                </div>

                <!-- Submit / Close Action Buttons -->
                <div class="flex gap-2.5 pt-2">
                    <button type="button" onclick="closeBookingModal()" class="w-1/3 bg-gray-700 hover:bg-gray-600 text-gray-300 font-semibold py-2.5 px-3 rounded-xl transition text-xs">
                        Close
                    </button>
                    <button id="bmSubmitBtn" type="submit" class="w-2/3 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-gray-950 font-black py-2.5 px-4 rounded-xl shadow-lg shadow-emerald-500/20 transition transform active:scale-98 text-xs flex items-center justify-center gap-2">
                        <span id="bmSubmitBtnText">Submit Registration</span>
                        <svg id="bmSubmitSpinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    </button>
                </div>
            </form>

            <!-- ── STEP 3: Registration Success Confirmation Receipt ── -->
            <div id="bmSuccessContent" class="hidden text-center py-2 space-y-4">
                <div class="w-14 h-14 mx-auto rounded-full bg-emerald-500/20 border-2 border-emerald-400 flex items-center justify-center text-emerald-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div>
                    <h4 class="text-xl font-black text-gray-100">Registration Submitted!</h4>
                    <p class="text-xs text-amber-400 font-semibold mt-1">Status: Pending Payment Confirmation</p>
                </div>
                <div class="p-4 rounded-2xl bg-gray-900 border border-gray-700 text-left text-xs font-mono space-y-2 text-gray-300">
                    <div class="flex justify-between border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Registration Status:</span>
                        <span class="text-amber-400 font-bold bg-amber-950/80 px-2 py-0.5 rounded border border-amber-800 text-[11px]">PENDING (Unpaid)</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Candidate:</span>
                        <span id="rcCandidateName" class="text-gray-100 font-bold"></span>
                    </div>
                    <div class="flex justify-between border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Examinee ID:</span>
                        <span id="rcExamineeId" class="text-cyan-300 font-black"></span>
                    </div>
                    <div class="flex justify-between border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Center &amp; Station:</span>
                        <span id="rcStation" class="text-gray-200"></span>
                    </div>
                    <div class="flex justify-between border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Date &amp; Slot:</span>
                        <span id="rcDateSlot" class="text-gray-200"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Test Fee:</span>
                        <span id="rcTestFee" class="text-emerald-400 font-bold"></span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="printReceipt()" class="w-1/2 bg-gray-700 hover:bg-gray-600 text-gray-200 font-bold py-2.5 rounded-xl transition text-xs flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print Receipt</span>
                    </button>
                    <button type="button" onclick="finishBookingSuccess()" class="w-1/2 bg-cyan-600 hover:bg-cyan-500 text-gray-950 font-black py-2.5 rounded-xl transition text-xs">
                        Done
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Global State (Zero sensitive API keys in client-side code!)
        let currentSite = null;
        let currentRangeParam = 'range=month';
        let activeRangeButton = null;
        let availableDates = [];
        let currentSheet = null;
        let currentSlot = null; // 'ALL' or '1' or '2' or '3' or session name
        let sheetSessions = []; // [{ name: "10:00 ~ 13:00", startCol: 1 }, ...]
        let pcDataMap = {}; // { pcNum: [ { session, id, name, status, color } ] }
        let cachedRawRows = null; // cached sheet rows for the active sheet date

        // SVGs
        const svgAvailable = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8 md:w-9 md:h-9 text-green-400 mb-1">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>`;

        const svgPending = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8 md:w-9 md:h-9 text-gray-400 mb-1">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
            <circle cx="12" cy="10" r="3"></circle>
            <polyline points="12 8 12 10 13.5 11.5"></polyline>
        </svg>`;

        const svgBooked = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8 md:w-9 md:h-9 text-red-500 mb-1">
            <rect x="10" y="3" width="12" height="10" rx="1" ry="1"></rect>
            <line x1="16" y1="13" x2="16" y2="17"></line>
            <circle cx="6" cy="11" r="3"></circle>
            <path d="M2 21v-2a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v2"></path>
        </svg>`;

        const svgHolding = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8 md:w-9 md:h-9 text-amber-400 mb-1 animate-pulse">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <circle cx="12" cy="10" r="3"></circle>
            <polyline points="12 8 12 10 13.5 10"></polyline>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>`;

        // Style presets
        const styleAvailable = ['bg-gray-900', 'border-green-500/40', 'text-green-400', 'hover:border-green-400', 'glow-green'];
        const stylePending   = ['bg-gray-900', 'border-gray-500/40', 'text-gray-400', 'hover:border-gray-400', 'glow-gray'];
        const styleBooked    = ['bg-gray-900', 'border-red-500/80', 'text-red-500', 'hover:border-red-400', 'glow-red'];
        const styleHolding   = ['bg-gray-900', 'border-amber-400/90', 'text-amber-300', 'hover:border-amber-300', 'glow-amber'];

        // Base API & Login Endpoints
        const SHEETS_API  = <?php echo json_encode($appBaseDir . '/user/sheets'); ?>;
        const BOOKING_API = <?php echo json_encode($appBaseDir . '/user/booking'); ?>;
        const LOGIN_URL   = <?php echo json_encode($appBaseDir . '/user/login'); ?>;
        const CSRF_TOKEN  = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

        // Active database locks & booking state
        let activeLocks = [];
        let currentBooking = null;
        let countdownInterval = null;

        // Local hold tracking so page reloads or closed modals always recognize user's own holds
        function saveMyHold(examineeNumber) {
            if (!examineeNumber) return;
            try {
                let holds = JSON.parse(localStorage.getItem('my_seat_holds') || '{}');
                holds[examineeNumber] = Date.now();
                localStorage.setItem('my_seat_holds', JSON.stringify(holds));
            } catch(e) {}
        }

        function removeMyHold(examineeNumber) {
            if (!examineeNumber) return;
            try {
                let holds = JSON.parse(localStorage.getItem('my_seat_holds') || '{}');
                delete holds[examineeNumber];
                localStorage.setItem('my_seat_holds', JSON.stringify(holds));
            } catch(e) {}
        }

        function isMyHoldLocal(examineeNumber) {
            if (!examineeNumber) return false;
            try {
                let holds = JSON.parse(localStorage.getItem('my_seat_holds') || '{}');
                return !!holds[examineeNumber];
            } catch(e) { return false; }
        }

        const siteNames = {
            'dhaka':  'ULSAI (Dhaka)',
            'dhaka1': 'ULSAI (Dhaka)',
            'khulna': 'KHULNA'
        };

        const siteActiveClasses = ['border-cyan-400', 'bg-cyan-950', 'text-cyan-300', 'font-semibold'];
        const siteInactiveClasses = ['border-gray-600', 'bg-gray-900', 'text-gray-300', 'hover:border-cyan-400'];

        const slotBtnActiveClasses = ['border-cyan-400', 'bg-cyan-950', 'text-cyan-300', 'font-semibold'];
        const slotBtnInactiveClasses = ['border-gray-700', 'bg-gray-900', 'text-gray-400', 'hover:border-cyan-500', 'hover:text-gray-200'];

        // Range button active/inactive classes
        const rangeActiveClasses = ['border-cyan-400', 'bg-cyan-950', 'text-cyan-300', 'font-semibold'];
        const rangeInactiveClasses = ['border-gray-600', 'bg-gray-900', 'text-gray-300', 'hover:border-cyan-400'];

        // View Transition Helpers
        function showEmptyPrompt(title = null, desc = null) {
            const emptyEl = document.getElementById("emptyPromptState");
            if (title) {
                const titleEl = emptyEl.querySelector("h3");
                if (titleEl) titleEl.textContent = title;
            }
            if (desc) {
                const descEl = emptyEl.querySelector("p");
                if (descEl) descEl.textContent = desc;
            }
            emptyEl.classList.remove("hidden");
            document.getElementById("loadingState").classList.add("hidden");
            document.getElementById("floorPlanContent").classList.add("hidden");
        }

        function showLoadingState(msg = "Fetching real-time Seat availability...") {
            document.getElementById("loadingMessage").textContent = msg;
            document.getElementById("emptyPromptState").classList.add("hidden");
            document.getElementById("loadingState").classList.remove("hidden");
            document.getElementById("floorPlanContent").classList.add("hidden");
        }

        function showFloorPlan() {
            document.getElementById("emptyPromptState").classList.add("hidden");
            document.getElementById("loadingState").classList.add("hidden");
            document.getElementById("floorPlanContent").classList.remove("hidden");
        }

        function setRangeActive(button) {
            document.querySelectorAll(".range-btn").forEach(b => {
                b.classList.remove(...rangeActiveClasses);
                b.classList.add(...rangeInactiveClasses);
            });
            if (button) {
                button.classList.remove(...rangeInactiveClasses);
                button.classList.add(...rangeActiveClasses);
            }
        }

        function setRangeParam(param, button) {
            currentRangeParam = param;
            setRangeActive(button);
            if (currentSite) {
                loadDateList(param, button);
            }
        }

        function getClientDate() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            return `${y}/${m}/${d}`;
        }

        // ─── STEP 1: Switch Exam Center ───
        function switchSite(siteId, button) {
            currentSite = siteId;
            currentSheet = null;
            currentSlot = null;
            cachedRawRows = null;
            sheetSessions = [];
            pcDataMap = {};

            document.querySelectorAll(".site-btn").forEach(b => {
                b.classList.remove(...siteActiveClasses);
                b.classList.add(...siteInactiveClasses);
            });
            if (button) {
                button.classList.remove(...siteInactiveClasses);
                button.classList.add(...siteActiveClasses);
            }

            const siteLabel = siteNames[currentSite] || currentSite;
            document.getElementById("siteInfo").textContent = siteLabel;

            const dateSelect = document.getElementById("dateSelect");
            dateSelect.disabled = true;
            dateSelect.innerHTML = `<option value="">Loading exam dates for ${siteLabel}...</option>`;

            document.getElementById("slotContainer").innerHTML = `<span class="text-xs text-gray-500 font-mono italic">Select Date first</span>`;

            showEmptyPrompt(
                `Loading Dates for ${siteLabel}...`,
                `Retrieving scheduled test dates for ${siteLabel}. Please wait a moment.`
            );

            loadDateList(currentRangeParam, activeRangeButton);
        }

        // ─── STEP 2: Load Date List (Does NOT load sheet data automatically) ───
        async function loadDateList(param = 'range=month', button = null) {
            if (!currentSite) return;
            currentRangeParam = param;
            if (button) {
                activeRangeButton = button;
                setRangeActive(button);
            }
            const siteLabel = siteNames[currentSite] || currentSite;
            setStatus(`Loading available dates for ${siteLabel}...`);

            const clientToday = getClientDate();
            const sep = param.includes('?') ? '&' : (param ? '&' : '');
            const url = `${SHEETS_API}?site=${encodeURIComponent(currentSite)}&${param}${sep}today=${encodeURIComponent(clientToday)}&_t=${Date.now()}`;

            try {
                const res = await fetch(url, { cache: 'no-store' });
                if (res.status === 401) {
                    alert('Session expired. Please sign in again.');
                    window.location.href = LOGIN_URL;
                    return;
                }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                availableDates = await res.json();

                const select = document.getElementById("dateSelect");
                select.innerHTML = "";

                if (!Array.isArray(availableDates) || !availableDates.length) {
                    select.innerHTML = `<option value="">No exam dates found</option>`;
                    select.disabled = true;
                    setStatus(`No exam dates found for ${siteLabel} in this range.`);
                    showEmptyPrompt(
                        `No Exam Dates Found (${siteLabel})`,
                        `There are no scheduled exam dates in this date range. Try switching to 7 Days or 15 Days.`
                    );
                    return;
                }

                // Populate with an explicit placeholder (DO NOT auto-select!)
                select.disabled = false;
                select.innerHTML = `<option value="">-- Choose Exam Date (${availableDates.length} available) --</option>`;

                availableDates.forEach((s, idx) => {
                    const opt = document.createElement("option");
                    opt.value = idx;
                    const isToday = (s.name === clientToday);
                    opt.textContent = isToday ? `${s.name} (Today)` : s.name;
                    select.appendChild(opt);
                });

                setStatus(`Found ${availableDates.length} exam dates for ${siteLabel}. Please select an Exam Date.`);
                showEmptyPrompt(
                    `Step 2: Select Exam Date`,
                    `Choose an exam date from the dropdown above for ${siteLabel}.`
                );

            } catch (err) {
                setStatus(`Failed to load dates: ${err.message}`, true);
                const select = document.getElementById("dateSelect");
                select.innerHTML = `<option value="">Error loading dates</option>`;
                select.disabled = true;
            }
        }

        // ─── STEP 2 (cont): Date Selected by User ───
        function onDateSelected() {
            const select = document.getElementById("dateSelect");
            const val = select.value;
            if (val === "" || isNaN(parseInt(val, 10))) {
                currentSheet = null;
                currentSlot = null;
                cachedRawRows = null;
                document.getElementById("slotContainer").innerHTML = `<span class="text-xs text-gray-500 font-mono italic">Select Date first</span>`;
                showEmptyPrompt(
                    `Step 2: Select Exam Date`,
                    `Choose an exam date from the dropdown above to continue.`
                );
                return;
            }

            const index = parseInt(val, 10);
            if (!availableDates[index]) return;

            currentSheet = availableDates[index];
            currentSlot = null;
            cachedRawRows = null;
            sheetSessions = [];
            pcDataMap = {};

            const siteLabel = siteNames[currentSite] || currentSite;
            setStatus(`Selected Date: ${currentSheet.name}. Now select a Session Slot above.`);

            // Render slot choices
            renderInitialSlotButtons();

            showEmptyPrompt(
                `Step 3: Select Session Slot`,
                `Selected ${siteLabel} on ${currentSheet.name}. Click a session slot above (All Sessions, Slot 1, Slot 2, or Slot 3) to view Seat floor plan.`
            );
        }

        function renderInitialSlotButtons() {
            const container = document.getElementById("slotContainer");
            container.innerHTML = "";

            const defaultSlots = [
                { id: 'ALL', label: 'All Sessions' },
                { id: '1',   label: 'Slot 1' },
                { id: '2',   label: 'Slot 2' },
                { id: '3',   label: 'Slot 3' }
            ];

            defaultSlots.forEach(s => {
                const btn = document.createElement("button");
                btn.type = "button";
                btn.setAttribute("data-slot", s.id);
                btn.className = `slot-btn px-3 py-1 text-xs rounded-lg border transition font-medium ${slotBtnInactiveClasses.join(' ')}`;
                btn.textContent = s.label;
                btn.onclick = () => selectSlot(s.id, btn);
                container.appendChild(btn);
            });
        }

        // ─── STEP 3: Slot Selected -> Fetch Data with Loading Effect ───
        async function selectSlot(slotId, button = null) {
            if (!currentSheet) {
                alert("Please select an exam date first.");
                return;
            }

            currentSlot = slotId;

            // Highlight active slot button
            document.querySelectorAll(".slot-btn").forEach(b => {
                const isTarget = b.getAttribute("data-slot") === slotId;
                if (isTarget) {
                    b.classList.remove(...slotBtnInactiveClasses);
                    b.classList.add(...slotBtnActiveClasses);
                } else {
                    b.classList.remove(...slotBtnActiveClasses);
                    b.classList.add(...slotBtnInactiveClasses);
                }
            });

            const siteLabel = siteNames[currentSite] || currentSite;

            // Fast path: if rows for currentSheet are already cached in memory
            if (cachedRawRows) {
                await fetchActiveLocks();
                renderFloorPlan();
                updateActiveSlotBadge();
                showFloorPlan();
                return;
            }

            // Otherwise, show skeleton loading shimmer & fetch
            showLoadingState(`Fetching real-time Seat availability for ${siteLabel} (${currentSheet.name})...`);
            setStatus(`Loading Seat data for ${siteLabel} (${currentSheet.name})...`);

            const url = `${SHEETS_API}?action=data&site=${encodeURIComponent(currentSite)}&sheet=${encodeURIComponent(currentSheet.name)}&_t=${Date.now()}`;

            try {
                const res = await fetch(url, { cache: 'no-store' });
                if (res.status === 401) {
                    alert('Session expired. Please sign in again.');
                    window.location.href = LOGIN_URL;
                    return;
                }
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);

                const rows = data.sheets?.[0]?.data?.[0]?.rowData || [];
                cachedRawRows = rows;
                parseSheetRows(rows);

                // Fetch active database holds and bookings
                await fetchActiveLocks();

                // Update slot button labels with actual parsed session times
                updateSlotButtonsWithSessionNames();

                // Render Seat floor plan
                renderFloorPlan();
                updateActiveSlotBadge();

                // Floor plan layout ONLY becomes visible when data is fully loaded!
                showFloorPlan();
                setStatus(`Seat availability loaded for ${siteLabel} (${currentSheet.name}).`);

            } catch (err) {
                showEmptyPrompt(
                    `Unable to Load Seat Data`,
                    `Error: ${err.message}. Please check connection or select another date.`
                );
                setStatus(`Error loading sheet: ${err.message}`, true);
            }
        }

        async function loadSheetData(sheet) {
            cachedRawRows = null;
            await selectSlot(currentSlot || 'ALL');
        }

        function parseSheetRows(rows) {
            sheetSessions = [];
            pcDataMap = {};

            // A. Detect Sessions / Time slots (contains '~')
            for (let r = 0; r < Math.min(15, rows.length); r++) {
                const cells = rows[r].values || [];
                for (let c = 0; c < cells.length; c++) {
                    const val = String(cells[c]?.formattedValue || "").trim();
                    if (val.includes("~")) {
                        sheetSessions.push({ name: val, startCol: c });
                    }
                }
                if (sheetSessions.length > 0) break;
            }

            if (!sheetSessions.length) {
                sheetSessions.push({ name: "Session 1", startCol: 1 });
            }

            // B. Parse Computer Number rows & Examinees
            for (let r = 0; r < rows.length; r++) {
                const cells = rows[r].values || [];
                const pcColVal = String(cells[0]?.formattedValue || "").trim();
                if (!/^\d+$/.test(pcColVal)) continue;

                const pcNum = parseInt(pcColVal, 10);
                pcDataMap[pcNum] = [];

                for (let s = 0; s < sheetSessions.length; s++) {
                    const sessionName = sheetSessions[s].name;
                    const startC = sheetSessions[s].startCol;
                    const endC = s < sheetSessions.length - 1 ? sheetSessions[s + 1].startCol : cells.length;

                    let examineeId = "";
                    let examineeName = "";
                    let isRed = false;

                    for (let c = startC; c < endC; c++) {
                        const fv = String(cells[c]?.formattedValue || "").trim();
                        if (/^\d{14}$/.test(fv)) {
                            examineeId = fv;
                            const rgb = cells[c]?.effectiveFormat?.textFormat?.foregroundColor || {};
                            isRed = (rgb.red > 0.5) && (!rgb.green || rgb.green < 0.3) && (!rgb.blue || rgb.blue < 0.3);

                            for (let k = c + 1; k < endC; k++) {
                                const nv = String(cells[k]?.formattedValue || "").trim();
                                if (nv && !/^\d+$/.test(nv)) {
                                    examineeName = nv;
                                    break;
                                }
                            }
                            break;
                        }
                    }

                    if (examineeId) {
                        const record = {
                            pc: pcNum,
                            session: sessionName,
                            id: examineeId,
                            name: examineeName || "—",
                            status: isRed ? "booked" : "pending" // Red = Booked/Paid, Black = Pending
                        };
                        pcDataMap[pcNum].push(record);
                    }
                }
            }
        }

        function updateSlotButtonsWithSessionNames() {
            const container = document.getElementById("slotContainer");
            sheetSessions.forEach((sess, idx) => {
                const slotNum = (idx + 1).toString();
                const btn = container.querySelector(`[data-slot="${slotNum}"]`);
                if (btn) {
                    btn.textContent = `Slot ${slotNum} (${sess.name})`;
                }
            });
        }

        function updateActiveSlotBadge() {
            const siteLabel = siteNames[currentSite] || currentSite;
            let slotDisplay = 'All Sessions';
            if (currentSlot && currentSlot !== 'ALL') {
                const slotIdx = parseInt(currentSlot, 10) - 1;
                if (!isNaN(slotIdx) && sheetSessions[slotIdx]) {
                    slotDisplay = `Slot ${currentSlot} (${sheetSessions[slotIdx].name})`;
                } else {
                    slotDisplay = `Slot ${currentSlot}`;
                }
            }
            const badge = document.getElementById("activeSlotBadge");
            if (badge) {
                badge.textContent = `${siteLabel} • ${slotDisplay}`;
            }
        }

        function getSessionsToRender() {
            if (!currentSlot || currentSlot === 'ALL') {
                return sheetSessions;
            }
            const slotIdx = parseInt(currentSlot, 10) - 1;
            if (!isNaN(slotIdx) && sheetSessions[slotIdx]) {
                return [sheetSessions[slotIdx]];
            }
            const byName = sheetSessions.filter(s => s.name === currentSlot);
            if (byName.length > 0) return byName;

            return sheetSessions;
        }

        // 4. Generate the Floor Plan UI Dynamically
        function createDeskElement(pcNum, sessionName, record) {
            const sessionIdx = sheetSessions.findIndex(s => s.name === sessionName);
            const slotNum = sessionIdx >= 0 ? (sessionIdx + 1) : 1;

            // Check if this Seat is locked in local DB (booking_completed or seat_bookings)
            const dbLock = activeLocks.find(l => l.pc === pcNum && l.timeSlot === slotNum);

            let state = "available";
            let iconSvg = svgAvailable;
            let style = styleAvailable;
            let labelText = "Available";
            let labelClass = "text-green-400";
            let lockInfo = null;

            if (dbLock) {
                lockInfo = dbLock;
                if (dbLock.status === "CONFIRMED" || dbLock.status === "PENDING") {
                    state = "pending";
                    iconSvg = svgPending;
                    style = stylePending;
                    labelText = "Pending";
                    labelClass = "text-gray-400";
                } else if (dbLock.status === "HOLDING" && dbLock.remainingSeconds > 0) {
                    state = "holding";
                    iconSvg = svgHolding;
                    style = styleHolding;
                    const m = Math.floor(dbLock.remainingSeconds / 60).toString().padStart(2, '0');
                    const s = (dbLock.remainingSeconds % 60).toString().padStart(2, '0');
                    const isMine = !!(dbLock.isMine || isMyHoldLocal(dbLock.examineeNumber));
                    const prefix = isMine ? "Mine" : "Held";
                    labelText = `${prefix} <span class="desk-timer font-mono text-[11px] font-bold" data-pc="${pcNum}" data-slot="${slotNum}">${m}:${s}</span>`;
                    labelClass = "text-amber-300 font-bold";
                } else if (dbLock.status === "HOLD_EXPIRED" || (dbLock.status === "HOLDING" && dbLock.remainingSeconds <= 0)) {
                    // Turn GREEN and available for register with reserved degree condition
                    state = "hold_expired";
                    iconSvg = svgAvailable;
                    style = styleAvailable;
                    labelText = `Avail (${dbLock.degree || '5Q'})`;
                    labelClass = "text-green-400 font-bold";
                }
            } else if (record) {
                state = record.status === "booked" ? "booked" : "pending";
                if (state === "booked") {
                    iconSvg = svgBooked;
                    style = styleBooked;
                    labelText = "Paid";
                    labelClass = "text-red-400";
                } else if (state === "pending") {
                    iconSvg = svgPending;
                    style = stylePending;
                    labelText = "Pending";
                    labelClass = "text-gray-400";
                }
            }

            const desk = document.createElement('button');
            desk.className = 'desk w-full aspect-square max-w-[130px] rounded-2xl border-2 flex flex-col items-center justify-center cursor-pointer p-2 relative overflow-hidden focus:outline-none';
            desk.classList.add(...style);

            desk.innerHTML = `
                <div class="pointer-events-none">${iconSvg}</div>
                <span class="text-xs font-mono font-bold tracking-tight pointer-events-none mt-0.5">Desk-${pcNum.toString().padStart(2, '0')}</span>
                <span class="text-[10px] ${labelClass} font-semibold uppercase tracking-wider pointer-events-none mt-0.5 flex items-center justify-center gap-1">${labelText}</span>
            `;

            desk.onclick = () => {
                if (state === "available") {
                    openBookingModal(pcNum, sessionName, slotNum);
                } else if (state === "holding") {
                    const isMine = !!(lockInfo && (lockInfo.isMine || isMyHoldLocal(lockInfo.examineeNumber)));
                    if (isMine) {
                        resumeBookingHold(lockInfo, sessionName);
                    } else {
                        const remSec = lockInfo ? lockInfo.remainingSeconds : 0;
                        const m = Math.floor(remSec / 60);
                        const s = remSec % 60;
                        alert(`This Seat (Desk-${pcNum.toString().padStart(2, '0')}) is currently on a 15-minute hold by another applicant.\n\nRemaining time until release: ${m}m ${s}s.`);
                    }
                } else if (state === "hold_expired") {
                    takeoverExpiredHold(lockInfo, sessionName);
                } else {
                    const infoRecords = record ? [record] : (lockInfo ? [{
                        session: sessionName,
                        name: lockInfo.candidateName || 'Candidate',
                        id: lockInfo.examineeNumber,
                        degree: lockInfo.degree,
                        status: (lockInfo.status === 'CONFIRMED' || lockInfo.status === 'booked') ? 'booked' : 'pending'
                    }] : []);
                    openModal(pcNum, state, sessionName, infoRecords);
                }
            };

            return { desk, state };
        }

        let floorPlanTimerInterval = null;

        function startFloorPlanTimers() {
            clearInterval(floorPlanTimerInterval);
            floorPlanTimerInterval = setInterval(() => {
                let hasExpired = false;

                activeLocks.forEach(lock => {
                    if (lock.status === "HOLDING" && lock.remainingSeconds > 0) {
                        lock.remainingSeconds--;
                        if (lock.remainingSeconds <= 0) {
                            lock.status = "HOLD_EXPIRED";
                            hasExpired = true;
                        }
                    }
                });

                if (hasExpired) {
                    renderFloorPlan();
                } else {
                    document.querySelectorAll('.desk-timer').forEach(el => {
                        const pc = parseInt(el.getAttribute('data-pc'), 10);
                        const slot = parseInt(el.getAttribute('data-slot'), 10);
                        const lk = activeLocks.find(l => l.pc === pc && l.timeSlot === slot);
                        if (lk && lk.remainingSeconds > 0) {
                            const m = Math.floor(lk.remainingSeconds / 60).toString().padStart(2, '0');
                            const s = (lk.remainingSeconds % 60).toString().padStart(2, '0');
                            el.textContent = `${m}:${s}`;
                        }
                    });
                }
            }, 1000);
        }

        function renderFloorPlan() {
            const floorPlanContainer = document.getElementById('floor-plan');
            floorPlanContainer.innerHTML = '';

            const pcNumbers = Object.keys(pcDataMap).map(Number);
            const totalPcs = pcNumbers.length ? Math.max(...pcNumbers) : 0;

            let overallAvailable = 0;
            let overallHolding = 0;
            let overallPending = 0;
            let overallBooked = 0;

            const sessionsToRender = getSessionsToRender();

            // Single session mode: render direct grid
            if (sessionsToRender.length === 1) {
                floorPlanContainer.className = "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 md:gap-5 justify-items-center w-full";
                const session = sessionsToRender[0];

                for (let i = 1; i <= totalPcs; i++) {
                    const assignments = pcDataMap[i] || [];
                    const record = assignments.find(a => a.session === session.name);

                    const { desk, state } = createDeskElement(i, session.name, record);
                    if (state === "booked") overallBooked++;
                    else if (state === "pending") overallPending++;
                    else if (state === "holding") overallHolding++;
                    else overallAvailable++;

                    floorPlanContainer.appendChild(desk);
                }
            } else {
                // "All Sessions" mode: render each session in its own organized section
                floorPlanContainer.className = "space-y-6 w-full";

                sessionsToRender.forEach(session => {
                    let sBooked = 0, sPending = 0, sHolding = 0, sAvail = 0;

                    const sessionCard = document.createElement("div");
                    sessionCard.className = "bg-gray-900/60 rounded-2xl p-4 md:p-5 border border-gray-700/60";

                    const sessionHeader = document.createElement("div");
                    sessionHeader.className = "flex flex-wrap justify-between items-center mb-4 pb-2 border-b border-gray-800 gap-2";

                    const sessionGrid = document.createElement("div");
                    sessionGrid.className = "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-4 justify-items-center";

                    for (let i = 1; i <= totalPcs; i++) {
                        const assignments = pcDataMap[i] || [];
                        const record = assignments.find(a => a.session === session.name);

                        const { desk, state } = createDeskElement(i, session.name, record);
                        if (state === "booked") { sBooked++; overallBooked++; }
                        else if (state === "pending") { sPending++; overallPending++; }
                        else if (state === "holding") { sHolding++; overallHolding++; }
                        else { sAvail++; overallAvailable++; }

                        sessionGrid.appendChild(desk);
                    }

                    sessionHeader.innerHTML = `
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                            <span class="font-bold text-sm text-cyan-300 font-mono tracking-wide">${escapeHtml(session.name)}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="text-gray-400">${totalPcs} Seats:</span>
                            <span class="text-red-400 font-semibold">&#x1F534; ${sBooked} Paid</span>
                            <span class="text-gray-400 font-semibold">&#x26AA; ${sPending} Pending</span>
                            <span class="text-amber-400 font-semibold">&#x1F7E1; ${sHolding} Hold</span>
                            <span class="text-green-400 font-semibold">&#x1F7E2; ${sAvail} Available</span>
                        </div>
                    `;

                    sessionCard.appendChild(sessionHeader);
                    sessionCard.appendChild(sessionGrid);
                    floorPlanContainer.appendChild(sessionCard);
                });
            }

            const totalSeats = overallBooked + overallPending + overallHolding + overallAvailable;

            // Update Dynamic Seat Counter in header
            document.getElementById('totalSeatsCount').innerText = totalSeats;
            document.getElementById('bookedSeatsCount').innerText = overallBooked;
            document.getElementById('pendingSeatsCount').innerText = overallPending;
            const holdingEl = document.getElementById('holdingSeatsCount');
            if (holdingEl) holdingEl.innerText = overallHolding;
            document.getElementById('availableSeatsCount').innerText = overallAvailable;

            startFloorPlanTimers();
        }

        // 5. Modal Details
        function openModal(pcNum, state, sessionName, records) {
            const modal = document.getElementById("detailModal");
            const title = document.getElementById("modalStationTitle");
            const badge = document.getElementById("modalStatusBadge");
            const body = document.getElementById("modalBody");

            title.innerText = `Seat Desk-${pcNum.toString().padStart(2, '0')}`;

            if (state === "booked") {
                badge.className = "text-xs font-bold px-2.5 py-0.5 rounded-full bg-red-950 text-red-400 border border-red-800";
                badge.innerText = "Paid / Booked";
            } else if (state === "pending") {
                badge.className = "text-xs font-bold px-2.5 py-0.5 rounded-full bg-gray-900 text-gray-300 border border-gray-700";
                badge.innerText = "Pending";
            } else if (state === "holding") {
                badge.className = "text-xs font-bold px-2.5 py-0.5 rounded-full bg-amber-950 text-amber-300 border border-amber-600";
                badge.innerText = "15-Min Hold";
            } else {
                badge.className = "text-xs font-bold px-2.5 py-0.5 rounded-full bg-green-950 text-green-400 border border-green-800";
                badge.innerText = "Available";
            }

            if (!records.length) {
                const sessionIdx = sheetSessions.findIndex(s => s.name === sessionName);
                const slotNum = sessionIdx >= 0 ? (sessionIdx + 1) : 1;
                body.innerHTML = `
                    <div class="text-center py-3">
                        <p class="text-green-400 font-semibold mb-1">Station is vacant.</p>
                        <p class="text-xs text-gray-400">Session: <strong class="text-gray-300">${escapeHtml(sessionName)}</strong></p>
                        <p class="text-[11px] text-gray-500 mt-1 mb-4">Available for official candidate registration.</p>
                        <button type="button" onclick="closeModal(); openBookingModal(${pcNum}, '${escapeHtml(sessionName)}', ${slotNum})" class="w-full bg-gradient-to-r from-cyan-500 to-teal-400 hover:from-cyan-400 hover:to-teal-300 text-gray-950 font-black py-2.5 px-4 rounded-xl shadow-lg transition text-xs flex items-center justify-center gap-2">
                            <span>Book Seat Desk-${pcNum.toString().padStart(2, '0')}</span>
                        </button>
                    </div>
                `;
            } else {
                body.innerHTML = records.map(r => `
                    <div class="p-3 bg-gray-900/80 rounded-xl border border-gray-700">
                        <div class="flex justify-between items-center text-xs text-cyan-400 mb-1 font-mono">
                            <span>Session: ${escapeHtml(r.session || sessionName)}</span>
                            <span class="${r.status === 'booked' ? 'text-red-400' : 'text-gray-400'} font-bold uppercase">${r.status === 'booked' ? 'Paid' : 'Pending'}</span>
                        </div>
                        <div class="font-bold text-gray-100 text-sm">${escapeHtml(r.name)}</div>
                        <div class="text-xs text-gray-400 font-mono mt-0.5">ID: ${escapeHtml(r.id)}</div>
                    </div>
                `).join("");
            }

            modal.classList.remove("hidden");
            modal.classList.add("flex");
        }

        function closeModal() {
            const modal = document.getElementById("detailModal");
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        }

        // ════════════════════════════════════════════════════════════════════════
        // 5B. CBT Seat 2-Step Booking Logic
        // ════════════════════════════════════════════════════════════════════════
        async function fetchActiveLocks() {
            if (!currentSheet || !currentSheet.name) return;
            try {
                const dateStr = currentSheet.name.replace(/\//g, '-');
                const res = await fetch(`${BOOKING_API}?action=get_active_locks&site=${encodeURIComponent(currentSite)}&date=${encodeURIComponent(dateStr)}&_t=${Date.now()}`, { cache: 'no-store' });
                if (res.ok) {
                    let json;
                try {
                    json = await res.json();
                } catch (parseErr) {
                    throw new Error(`Server returned HTTP ${res.status} (${res.statusText || 'Internal Error'}). Please check server logs or retry.`);
                }
                    if (json.success) {
                        activeLocks = json.locks || [];
                    }
                }
            } catch (e) {
                console.error("fetchActiveLocks error", e);
            }
        }

        function openBookingModal(pcNum, sessionName, slotNum, preselectedDegree = null) {
            closeModal();
            currentBooking = {
                pc: pcNum,
                sessionName: sessionName,
                slotNum: slotNum,
                date: (currentSheet?.name || '').replace(/\//g, '-'),
                site: currentSite,
                degree: preselectedDegree || '',
                examineeNumber: '',
                testFee: 'N/A',
                remainingSeconds: 900
            };

            // Set Header Badges
            document.getElementById('bmStationBadge').textContent = `Desk-${pcNum.toString().padStart(2, '0')}`;
            const siteLabel = siteNames[currentSite] || currentSite;
            document.getElementById('bmSiteBadge').textContent = siteLabel;
            document.getElementById('bmSubheader').textContent = `${currentBooking.date} • ${sessionName}`;

            // Set Preview Fields
            document.getElementById('bmPreviewCenter').textContent = siteLabel;
            document.getElementById('bmPreviewDate').textContent = currentBooking.date;
            document.getElementById('bmPreviewSlot').textContent = `Slot ${slotNum} (${sessionName})`;
            document.getElementById('bmPreviewPc').textContent = `Desk-${pcNum.toString().padStart(2, '0')}`;

            const countryEl = document.getElementById('bmCandidateCountry');
            if (countryEl && (!countryEl.value || countryEl.value.toLowerCase() === 'bangladesh')) {
                countryEl.value = 'BANGLADESH';
            }

            const swBanner = document.getElementById('bmDeskSwitchedBanner');
            if (swBanner) swBanner.classList.add('hidden');

            // Reset all Level buttons to unselected state
            document.querySelectorAll('.bm-level-btn').forEach(btn => {
                btn.className = "bm-level-btn p-3 rounded-xl border border-gray-700 bg-gray-900 text-gray-300 font-bold text-left transition hover:border-gray-500";
                btn.onclick = () => selectExamLevel(btn.getAttribute('data-level'));
            });

            const confirmBtn = document.getElementById('bmConfirmLevelBtn');
            const confirmBtnText = document.getElementById('bmConfirmBtnText');

            if (preselectedDegree) {
                selectExamLevel(preselectedDegree);
                // Pre-locked degree: disable choosing other levels
                document.querySelectorAll('.bm-level-btn').forEach(btn => {
                    const lvl = btn.getAttribute('data-level');
                    if (lvl !== preselectedDegree) {
                        btn.className = "bm-level-btn p-3 rounded-xl border border-gray-800 bg-gray-950 text-gray-600 font-bold text-left transition opacity-40 cursor-not-allowed";
                        btn.onclick = () => {
                            showBookingError(`This seat only available for ${preselectedDegree} and cannot be changed.`);
                        };
                    } else {
                        btn.onclick = () => {
                            showBookingNotice(`This Seat only avaiable for ${preselectedDegree}.`);
                        };
                    }
                });
                showBookingNotice(`This Seat only avaiable for ${preselectedDegree}.`);
            } else {
                hideBookingNotice();
                confirmBtn.disabled = true;
                confirmBtn.className = "w-full mt-2 bg-gray-700 text-gray-400 font-black py-3 px-4 rounded-xl shadow-none transition text-sm flex items-center justify-center gap-2 opacity-60 cursor-not-allowed";
                confirmBtnText.textContent = "Select Exam Level Above";
            }

            // Switch to Step 1 view
            setBookingModalStep(1);
            hideBookingError();

            const modal = document.getElementById('bookingModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        async function takeoverExpiredHold(lockInfo, sessionName) {
            openBookingModal(lockInfo.pc, sessionName, lockInfo.timeSlot, lockInfo.degree);
            await handleConfirmLevel();
        }

        function setBookingModalStep(step) {
            const step1   = document.getElementById('bmStep1Content');
            const step2   = document.getElementById('bmStep2Form');
            const success = document.getElementById('bmSuccessContent');
            const tab1    = document.getElementById('bmStepTab1');
            const tab2    = document.getElementById('bmStepTab2');
            const banner  = document.getElementById('bmLockBanner');

            step1.classList.add('hidden');
            step2.classList.add('hidden');
            success.classList.add('hidden');

            if (step === 1) {
                step1.classList.remove('hidden');
                banner.classList.add('hidden');
                banner.classList.remove('flex');
                tab1.className = "p-2.5 rounded-xl border border-cyan-400 bg-cyan-950/60 text-cyan-300 text-xs font-bold flex items-center gap-2";
                tab2.className = "p-2.5 rounded-xl border border-gray-700 bg-gray-900/60 text-gray-500 text-xs font-bold flex items-center gap-2";
            } else if (step === 2) {
                step2.classList.remove('hidden');
                banner.classList.remove('hidden');
                banner.classList.add('flex');
                tab1.className = "p-2.5 rounded-xl border border-emerald-500 bg-emerald-950/40 text-emerald-300 text-xs font-bold flex items-center gap-2";
                tab2.className = "p-2.5 rounded-xl border border-cyan-400 bg-cyan-950/60 text-cyan-300 text-xs font-bold flex items-center gap-2";
            } else if (step === 3) {
                success.classList.remove('hidden');
                banner.classList.add('hidden');
                banner.classList.remove('flex');
                tab1.className = "p-2.5 rounded-xl border border-emerald-500 bg-emerald-950/40 text-emerald-300 text-xs font-bold flex items-center gap-2";
                tab2.className = "p-2.5 rounded-xl border border-emerald-500 bg-emerald-950/40 text-emerald-300 text-xs font-bold flex items-center gap-2";
            }
        }

        function selectExamLevel(level) {
            if (!currentBooking) return;
            currentBooking.degree = level;
            document.querySelectorAll('.bm-level-btn').forEach(btn => {
                if (btn.getAttribute('data-level') === level) {
                    btn.className = "bm-level-btn p-3 rounded-xl border border-cyan-400 bg-cyan-950 text-cyan-300 font-bold text-left transition shadow-lg shadow-cyan-950/50";
                } else {
                    btn.className = "bm-level-btn p-3 rounded-xl border border-gray-700 bg-gray-900 text-gray-300 font-bold text-left transition hover:border-gray-500";
                }
            });

            // Enable Confirm Level button once user selects a level
            const confirmBtn = document.getElementById('bmConfirmLevelBtn');
            const confirmBtnText = document.getElementById('bmConfirmBtnText');
            confirmBtn.disabled = false;
            confirmBtn.className = "w-full mt-2 bg-gradient-to-r from-cyan-500 to-teal-400 hover:from-cyan-400 hover:to-teal-300 text-gray-950 font-black py-3 px-4 rounded-xl shadow-lg shadow-cyan-500/20 transition transform active:scale-98 text-sm flex items-center justify-center gap-2 cursor-pointer";
            confirmBtnText.textContent = `Confirm Level (${level})`;
            hideBookingError();
        }

        async function handleConfirmLevel() {
            if (!currentBooking || !currentBooking.degree) {
                showBookingError('Please select an examination level before confirming.');
                return;
            }
            hideBookingError();

            const btn = document.getElementById('bmConfirmLevelBtn');
            const btnText = document.getElementById('bmConfirmBtnText');
            const spinner = document.getElementById('bmConfirmSpinner');

            btn.disabled = true;
            btnText.textContent = "Holding seat...";
            spinner.classList.remove('hidden');

            try {
                // Extract slot time from the seat user selected (leave empty if no time pattern is found)
                let sessionTime = '';
                const rawSession = currentBooking.sessionName || (sheetSessions && sheetSessions[currentBooking.slotNum - 1]?.name) || '';
                const timeMatch = rawSession.match(/(\d{1,2}:\d{2})/);
                if (timeMatch) {
                    sessionTime = timeMatch[1].padStart(5, '0');
                }

                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN);
                formData.append('action', 'confirm_level');
                formData.append('site', currentBooking.site);
                formData.append('date', currentBooking.date);
                formData.append('time_slot', currentBooking.slotNum);
                formData.append('time', sessionTime);
                formData.append('pc', currentBooking.pc);
                formData.append('degree', currentBooking.degree);

                const res = await fetch(BOOKING_API, {
                    method: 'POST',
                    body: formData
                });

                if (res.status === 401) {
                    alert('Session expired. Please sign in again.');
                    window.location.href = LOGIN_URL;
                    return;
                }

                let json;
                try {
                    json = await res.json();
                } catch (parseErr) {
                    throw new Error(`Server returned HTTP ${res.status} (${res.statusText || 'Internal Error'}). Please check server logs or retry.`);
                }
                if (!json.success) {
                    showBookingError(json.error || 'Seat could not be locked. Please select another level or slot.');
                    return;
                }

                // Step 1 success! Save assigned Examinee ID & Fee
                currentBooking.examineeNumber = json.examineeNumber;
                currentBooking.testFee = json.testFee || 'N/A';
                currentBooking.remainingSeconds = json.remainingSeconds || 900;
                saveMyHold(json.examineeNumber);

                // Option A: If official examination server assigned a different sequential desk
                const swBanner = document.getElementById('bmDeskSwitchedBanner');
                const swText   = document.getElementById('bmDeskSwitchedText');
                if (json.switchedDesk && json.pc && json.pc !== currentBooking.pc) {
                    const requestedDesk = currentBooking.pc;
                    currentBooking.pc = json.pc;

                    // Update header badge and subheaders to the official assigned desk
                    const deskPadded = currentBooking.pc.toString().padStart(2, '0');
                    document.getElementById('bmStationBadge').textContent = `Desk-${deskPadded}`;
                    document.getElementById('bmPreviewPc').textContent = `Desk-${deskPadded}`;

                    if (swBanner && swText) {
                        swText.textContent = `The official exam server allocates desks sequentially. You selected Desk-${requestedDesk.toString().padStart(2, '0')}, but Desk-${deskPadded} has been assigned for you.`;
                        swBanner.classList.remove('hidden');
                    }
                } else {
                    if (swBanner) swBanner.classList.add('hidden');
                }

                document.getElementById('bmAssignedId').textContent = currentBooking.examineeNumber;
                document.getElementById('bmAssignedFee').textContent = currentBooking.testFee;

                // Start 15-minute countdown clock
                startCountdownTimer(currentBooking.remainingSeconds);

                // Transition to Step 2
                setBookingModalStep(2);

                // Refresh locks in background so floor plan reflects hold
                fetchActiveLocks().then(renderFloorPlan);

            } catch (err) {
                showBookingError(`Connection error: ${err.message}`);
            } finally {
                btn.disabled = false;
                btnText.textContent = "Confirm Level";
                spinner.classList.add('hidden');
            }
        }

        function startCountdownTimer(seconds) {
            clearInterval(countdownInterval);
            let rem = seconds;

            const updateTimerDisplay = () => {
                const m = Math.floor(rem / 60).toString().padStart(2, '0');
                const s = (rem % 60).toString().padStart(2, '0');
                const el = document.getElementById('bmTimerDisplay');
                if (el) el.textContent = `${m}:${s}`;
                if (rem <= 0) {
                    clearInterval(countdownInterval);
                    if (currentBooking && currentBooking.examineeNumber) {
                        removeMyHold(currentBooking.examineeNumber);
                    }
                    alert('Your 15-minute seat hold has expired. The seat has been released.');
                    closeBookingModal();
                    loadSheetData(currentSheet);
                }
                rem--;
            };

            updateTimerDisplay();
            countdownInterval = setInterval(updateTimerDisplay, 1000);
        }

        async function handleFinalSubmit(e) {
            e.preventDefault();
            if (!currentBooking || !currentBooking.examineeNumber) {
                showBookingError('Seat is not currently held. Please select and lock a seat first.');
                return;
            }
            hideBookingError();

            const candName = document.getElementById('bmCandidateName').value.trim().toUpperCase();
            const candCountry = document.getElementById('bmCandidateCountry').value.trim() || 'BANGLADESH';
            const candBirthday = document.getElementById('bmCandidateBirthday').value.trim();
            const candPhone = document.getElementById('bmCandidatePhone').value.trim();
            const candEmail = document.getElementById('bmCandidateEmail').value.trim();
            const candAddress = document.getElementById('bmCandidateAddress').value.trim();
            const candZipcode = document.getElementById('bmCandidateZipcode').value.trim();

            // 1. Mobile Number Validation: 11 digits starting with 013-019
            const bdPhoneRegex = /^01[3-9]\d{8}$/;
            if (!bdPhoneRegex.test(candPhone)) {
                showBookingError('Invalid mobile number. Must be 11 digits starting with 013, 014, 015, 016, 017, 018, or 019 (e.g. 01712345678).');
                const phoneInput = document.getElementById('bmCandidatePhone');
                if (phoneInput) phoneInput.focus();
                return;
            }

            // 2. Email Address Validation
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(candEmail)) {
                showBookingError('Invalid email address. Please enter a valid email (e.g. candidate@example.com).');
                const emailInput = document.getElementById('bmCandidateEmail');
                if (emailInput) emailInput.focus();
                return;
            }

            const btn = document.getElementById('bmSubmitBtn');
            const btnText = document.getElementById('bmSubmitBtnText');
            const spinner = document.getElementById('bmSubmitSpinner');

            btn.disabled = true;
            btnText.textContent = "Registering Candidate...";
            spinner.classList.remove('hidden');

            try {
                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN);
                formData.append('action', 'submit_registration');
                formData.append('examineeNumber', currentBooking.examineeNumber);
                formData.append('name', candName);
                formData.append('birthday', candBirthday);
                formData.append('phoneNumber', candPhone);
                formData.append('email', candEmail);
                formData.append('address', candAddress);
                formData.append('zipcode', candZipcode);
                formData.append('country', candCountry);

                const res = await fetch(BOOKING_API, {
                    method: 'POST',
                    body: formData
                });

                if (res.status === 401) {
                    alert('Session expired. Please sign in again.');
                    window.location.href = LOGIN_URL;
                    return;
                }

                let json;
                try {
                    json = await res.json();
                } catch (parseErr) {
                    throw new Error(`Server returned HTTP ${res.status} (${res.statusText || 'Internal Error'}). Please check server logs or retry.`);
                }
                if (!json.success) {
                    showBookingError(json.error || 'Registration failed. Please check candidate information.');
                    return;
                }

                // Stop countdown timer
                clearInterval(countdownInterval);
                removeMyHold(currentBooking.examineeNumber);

                // Populate Step 3 Confirmation Receipt
                document.getElementById('rcCandidateName').textContent = (json.candidateName || candName).toUpperCase();
                document.getElementById('rcExamineeId').textContent = currentBooking.examineeNumber;
                document.getElementById('rcStation').textContent = `${siteNames[currentBooking.site] || currentBooking.site} • Desk-${currentBooking.pc.toString().padStart(2, '0')}`;
                document.getElementById('rcDateSlot').textContent = `${currentBooking.date} • ${currentBooking.sessionName}`;
                document.getElementById('rcTestFee').textContent = json.testFee || currentBooking.testFee || 'N/A';

                setBookingModalStep(3);

                // Refresh seat floor plan
                loadSheetData(currentSheet);

            } catch (err) {
                showBookingError(`Registration error: ${err.message}`);
            } finally {
                btn.disabled = false;
                btnText.textContent = "Submit Registration";
                spinner.classList.add('hidden');
            }
        }

        function cancelHoldAndClose() {
            closeBookingModal();
        }

        function resumeBookingHold(lockInfo, sessionName) {
            saveMyHold(lockInfo.examineeNumber);
            openBookingModal(lockInfo.pc, sessionName, lockInfo.timeSlot, lockInfo.degree);
            currentBooking.examineeNumber = lockInfo.examineeNumber;
            currentBooking.remainingSeconds = lockInfo.remainingSeconds;
            currentBooking.degree = lockInfo.degree || '5Q';
            currentBooking.testFee = lockInfo.testFee || 'N/A';
            document.getElementById('bmAssignedId').textContent = currentBooking.examineeNumber;
            document.getElementById('bmAssignedFee').textContent = currentBooking.testFee;
            startCountdownTimer(currentBooking.remainingSeconds);
            setBookingModalStep(2);
        }

        function closeBookingModal() {
            clearInterval(countdownInterval);
            const modal = document.getElementById('bookingModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            currentBooking = null;
        }

        function finishBookingSuccess() {
            closeBookingModal();
            loadSheetData(currentSheet);
        }

        function printReceipt() {
            window.print();
        }

        function showBookingError(msg) {
            hideBookingNotice();
            const banner = document.getElementById('bmErrorBanner');
            const txt = document.getElementById('bmErrorText');
            txt.textContent = msg;
            banner.classList.remove('hidden');
        }

        function hideBookingError() {
            const banner = document.getElementById('bmErrorBanner');
            banner.classList.add('hidden');
        }

        function showBookingNotice(msg) {
            hideBookingError();
            const banner = document.getElementById('bmNoticeBanner');
            const txt = document.getElementById('bmNoticeText');
            if (banner && txt) {
                txt.textContent = msg;
                banner.classList.remove('hidden');
            }
        }

        function hideBookingNotice() {
            const banner = document.getElementById('bmNoticeBanner');
            if (banner) banner.classList.add('hidden');
        }

        function setStatus(text, isError = false) {
            const el = document.getElementById("statusText");
            el.innerText = text;
            el.className = isError ? "text-red-400 font-semibold" : "text-gray-400";
        }

        function escapeHtml(str) {
            return String(str || "").replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
        }

        // Initialize on load: Fast & clean - do NOT load any data by default
        window.onload = () => {
            activeRangeButton = document.getElementById("btnMonth");
            setRangeActive(activeRangeButton);

            // Ensure center buttons are unselected
            document.querySelectorAll(".site-btn").forEach(b => {
                b.classList.remove(...siteActiveClasses);
                b.classList.add(...siteInactiveClasses);
            });

            // Date selector disabled until center is chosen
            const select = document.getElementById("dateSelect");
            select.disabled = true;
            select.innerHTML = `<option value="">-- Select Center First --</option>`;

            // Slot container prompt
            document.getElementById("slotContainer").innerHTML = `<span class="text-xs text-gray-500 font-mono italic">Select Date first</span>`;

            // Initial banner and empty state prompt
            setStatus("Please select an Exam Center (ULSAI (Dhaka) or KHULNA) above to begin.");
            document.getElementById("siteInfo").textContent = "";

            showEmptyPrompt(
                "Select Center > Date > Slot",
                "Choose an examination center, test date, and session slot above to view real-time Seat availability."
            );
        };
    </script>
</body>
</html>