<?php
/**
 * Shared Admin Layout - Header
 * Provides HTML doctype, meta headers, stylesheet links, and the top navigation bar.
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard | NAT-TEST BD';
}
if (!isset($activePage)) {
    $activePage = 'dashboard';
}

$cssFile = __DIR__ . '/../../assets/css/style.min.css';
$cssVer  = file_exists($cssFile) ? filemtime($cssFile) : '1';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="../favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png?v=2">
    <!-- Compiled Tailwind CSS -->
    <link rel="stylesheet" href="../assets/css/style.min.css?v=<?php echo $cssVer; ?>">
    <!-- SheetJS (Excel / CSV parsing) -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
</head>
<body class="h-full bg-slate-950 font-sans text-slate-800 flex flex-col antialiased">

    <!-- ═══ TOP NAVBAR ═══ -->
    <header class="bg-slate-900 border-b border-slate-800/80 sticky top-0 z-40 text-slate-100 shadow-sm shrink-0">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Left: Mobile Toggle & Logo -->
                <div class="flex items-center gap-3">
                    <button type="button" onclick="toggleMobileSidebar()"
                        class="md:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                        aria-label="Toggle Navigation">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <a href="./" class="flex items-center gap-3 group">
                        <img src="../logo.png" alt="NAT-TEST BD Logo" class="w-8 h-8 object-contain drop-shadow group-hover:scale-105 transition-transform">
                        <div class="flex flex-col">
                            <span class="font-extrabold text-white text-base leading-tight tracking-tight flex items-center gap-2">
                                NAT-TEST BD
                                <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                    Admin
                                </span>
                            </span>
                            <span class="text-[11px] text-slate-400 font-medium">Japanese Examination Portal</span>
                        </div>
                    </a>
                </div>

                <!-- Right: Quick Portal Link & Admin Actions -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="../user/dashboard" target="_blank"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 transition"
                        title="Open CBT User Portal in a new tab">
                        <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        <span>CBT Seat Portal</span>
                    </a>

                    <div class="h-6 w-px bg-slate-800 hidden sm:block"></div>

                    <!-- Administrator Tag -->
                    <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/60 border border-slate-700/50">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-bold text-slate-300">Administrator</span>
                    </div>

                    <!-- Logout Button -->
                    <a href="?logout=true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition cursor-pointer"
                        title="Sign out of admin session">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Logout</span>
                    </a>
                </div>

            </div>
        </div>
    </header>

    <!-- ═══ APP BODY (SIDEBAR + MAIN CANVAS) ═══ -->
    <div class="flex-1 flex overflow-hidden">
