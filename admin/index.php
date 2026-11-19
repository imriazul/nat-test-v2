<?php
/**
 * Admin Panel - Dashboard Overview
 * Central hub for NAT-TEST BD administration: KPIs, quick actions, scraper health, and recent batches.
 */
require_once __DIR__ . '/auth.php';

// Backward compatibility: Forward any legacy API requests to api.php
if (isset($_GET['action']) || isset($_POST['action'])) {
    require __DIR__ . '/api.php';
    exit;
}

$pageTitle = 'Dashboard | NAT-TEST BD Admin';
$activePage = 'dashboard';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 overflow-y-auto bg-slate-50">
    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 pb-28 sm:pb-36 space-y-6">

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-6 sm:p-8 text-white shadow-lg border border-slate-700/50">
            <div class="absolute -right-12 -top-12 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-12 -bottom-12 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/10 text-xs font-semibold text-slate-200 mb-3 backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>All Systems Operational</span>
                        <span class="text-slate-400">•</span>
                        <span id="banner-clock" class="font-mono text-slate-300"><?php echo date('d M Y'); ?></span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                        Admin Command Center
                    </h1>
                    <p class="text-slate-300 text-xs sm:text-sm mt-1.5 max-w-2xl leading-relaxed">
                        Manage PBT and CBT exam result batches, configure automated scraper crons, coordinate CBT schedule calendars, and manage portal accounts.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="import" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-md transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Import Examinees</span>
                    </a>
                    <a href="scrapers" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Scrapers</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Examinees -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider block">Total Examinees</span>
                    <span id="kpi-total-examinees" class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 block">0</span>
                    <span id="kpi-examinees-sub" class="text-[11px] text-slate-400">Loading records…</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>

            <!-- Exam Batches -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider block">Exam Batches</span>
                    <span id="kpi-total-tables" class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 block">0</span>
                    <div class="flex items-center gap-2 mt-1">
                        <span id="kpi-pbt-count" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">0 PBT</span>
                        <span id="kpi-cbt-count" class="text-[11px] font-semibold text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded">0 CBT</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>

            <!-- Scraper Automation -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider block">Scraper Status</span>
                    <div class="flex items-center gap-1.5 mt-1.5">
                        <span id="kpi-pbt-status-dot" class="w-2 h-2 rounded-full bg-slate-300"></span>
                        <span id="kpi-pbt-status-text" class="text-xs font-bold text-slate-700">PBT: ...</span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span id="kpi-cbt-status-dot" class="w-2 h-2 rounded-full bg-slate-300"></span>
                        <span id="kpi-cbt-status-text" class="text-xs font-bold text-slate-700">CBT: ...</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-cyan-50 border border-cyan-100 flex items-center justify-center text-cyan-600 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>

            <!-- CBT Portal Users -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider block">Portal Accounts</span>
                    <span id="kpi-total-users" class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 block">0</span>
                    <span id="kpi-users-sub" class="text-[11px] text-emerald-600 font-semibold">Active accounts</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-600 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
        </div>

        <?php $personalDetailsEnabled = isPersonalDetailsEnabled(); ?>
        <!-- Portal Configuration: Personal Details Visibility -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 sm:p-6 transition-all">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200/80 flex items-center justify-center text-amber-600 shrink-0 mt-0.5">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">Examinee Personal Details Section</h3>
                            <span id="badge-personal-details-status" class="px-2.5 py-0.5 rounded-full text-xs font-semibold border flex items-center gap-1.5 shadow-2xs <?php echo $personalDetailsEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-300'; ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo $personalDetailsEnabled ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                <span><?php echo $personalDetailsEnabled ? 'Visible (Email OTP Protected)' : 'Hidden (Exam Scores Only)'; ?></span>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 max-w-3xl leading-relaxed">
                            Control whether examinees can see and verify confidential personal details (Photo, Candidate Name, DOB, Phone) via email OTP on the public result page.
                        </p>
                    </div>
                </div>

                <!-- Toggle Switch Action -->
                <div class="flex items-center justify-between sm:justify-end gap-3 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100 shrink-0">
                    <div class="text-right">
                        <span id="label-personal-details-state" class="block text-xs font-bold <?php echo $personalDetailsEnabled ? 'text-emerald-700' : 'text-slate-500'; ?>">
                            <?php echo $personalDetailsEnabled ? 'Display Active' : 'Display Hidden'; ?>
                        </span>
                        <span id="sublabel-personal-details-state" class="block text-[10px] text-slate-400">
                            <?php echo $personalDetailsEnabled ? 'Section is visible' : 'Section is hidden'; ?>
                        </span>
                    </div>
                    <button type="button" id="btn-toggle-personal-details" onclick="togglePersonalDetails()" 
                        data-enabled="<?php echo $personalDetailsEnabled ? '1' : '0'; ?>"
                        class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?php echo $personalDetailsEnabled ? 'bg-blue-600' : 'bg-slate-300'; ?>" 
                        role="switch" aria-checked="<?php echo $personalDetailsEnabled ? 'true' : 'false'; ?>" title="Toggle Examinee Personal Details">
                        <span id="knob-personal-details" class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?php echo $personalDetailsEnabled ? 'translate-x-5' : 'translate-x-0'; ?>"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Action Launcher Hub -->
        <div>
            <h2 class="text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Administrative Modules</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <!-- Tables -->
                <a href="tables" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-blue-300 transition-all group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-blue-600 transition-colors">Exam Tables</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">View, filter, and inspect registered monthly batches.</p>
                    </div>
                    <span class="mt-4 text-[11px] font-bold text-blue-600 inline-flex items-center gap-1">Open Tables →</span>
                </a>

                <!-- Import -->
                <a href="import" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-emerald-300 transition-all group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-emerald-600 transition-colors">Import Batches</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Upload CSV/Excel examinees with auto column mapping.</p>
                    </div>
                    <span class="mt-4 text-[11px] font-bold text-emerald-600 inline-flex items-center gap-1">Upload Data →</span>
                </a>

                <!-- Scrapers -->
                <a href="scrapers" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-cyan-300 transition-all group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-cyan-600 transition-colors">Auto Scrapers</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Configure crons and live streaming scraper logs.</p>
                    </div>
                    <span class="mt-4 text-[11px] font-bold text-cyan-600 inline-flex items-center gap-1">Configure Crons →</span>
                </a>

                <!-- Schedule -->
                <a href="schedule" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-amber-300 transition-all group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-amber-600 transition-colors">CBT Schedule</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Manage exam centers (474, 476) and excluded dates.</p>
                    </div>
                    <span class="mt-4 text-[11px] font-bold text-amber-600 inline-flex items-center gap-1">Manage Calendar →</span>
                </a>

                <!-- Users -->
                <a href="users" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-purple-300 transition-all group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-purple-600 transition-colors">Portal Users</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Create and manage CBT portal agent credentials.</p>
                    </div>
                    <span class="mt-4 text-[11px] font-bold text-purple-600 inline-flex items-center gap-1">Manage Accounts →</span>
                </a>
            </div>
        </div>

        <!-- Two Column Section: Recent Tables & Scraper Status -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent Exam Tables (2 Cols) -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden flex flex-col">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">Recent Exam Batches</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Latest uploaded examination result tables</p>
                    </div>
                    <a href="tables" class="text-xs font-bold text-blue-600 hover:text-blue-800">View All Tables →</a>
                </div>

                <div class="p-0 overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Batch Table</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4 text-right">Examinees</th>
                                <th class="py-3 px-4 text-right">Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody id="recent-tables-tbody" class="divide-y divide-slate-100">
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400">Loading recent exam batches…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Scrapers Health Card (1 Col) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-slate-900 text-sm">Scraper Health</h3>
                        <a href="scrapers" class="text-xs font-bold text-blue-600 hover:text-blue-800">Manage →</a>
                    </div>

                    <!-- PBT Scraper Box -->
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 mb-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span>PBT Scraper</span>
                            </span>
                            <span id="pbt-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-600">Checking…</span>
                        </div>
                        <p id="pbt-last-run" class="text-[11px] text-slate-500">Last run: —</p>
                    </div>

                    <!-- CBT Scraper Box -->
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                <span>CBT Scraper</span>
                            </span>
                            <span id="cbt-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-600">Checking…</span>
                        </div>
                        <p id="cbt-last-run" class="text-[11px] text-slate-500">Last run: —</p>
                    </div>
                </div>

                <!-- Server Environment Info -->
                <div class="pt-4 border-t border-slate-100 text-[11px] text-slate-400 space-y-1">
                    <div class="flex justify-between">
                        <span>PHP Version:</span>
                        <span class="font-mono text-slate-600"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Server Time:</span>
                        <span class="font-mono text-slate-600"><?php echo date('H:i T'); ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Generous Bottom Spacing -->
        <div class="h-12 sm:h-16" aria-hidden="true"></div>

    </main>
</div>

<script>
// ─── Load Dashboard Data ───────────────────────────────────
async function initDashboard() {
    // 1. Fetch tables stats
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=stats');
        const data = await resp.json();
        const tables = data.tables || [];

        let totalRecords = 0, totalPass = 0, totalFail = 0;
        let cbtCount = 0, pbtCount = 0;

        tables.forEach(t => {
            totalRecords += (t.total || 0);
            totalPass    += (t.passed || 0);
            totalFail    += (t.failed || 0);

            if (t.type === 'CBT' || t.name.endsWith('_CBT')) {
                cbtCount++;
            } else {
                pbtCount++;
            }
        });

        document.getElementById('kpi-total-examinees').textContent = totalRecords.toLocaleString();
        document.getElementById('kpi-examinees-sub').textContent   = `${tables.length} batches evaluated`;
        document.getElementById('kpi-total-tables').textContent    = tables.length;
        document.getElementById('kpi-pbt-count').textContent       = `${pbtCount} PBT`;
        document.getElementById('kpi-cbt-count').textContent       = `${cbtCount} CBT`;

        // Render Recent 5 Tables
        renderRecentTables(tables.slice(0, 5));
    } catch (e) {
        console.error('Failed to load stats:', e);
    }

    // 2. Fetch PBT Scraper Status
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_cron_config');
        const data = await resp.json();
        if (data.success && data.config) {
            const enabled = !!data.config.enabled;
            const dot = document.getElementById('kpi-pbt-status-dot');
            const txt = document.getElementById('kpi-pbt-status-text');
            const badge = document.getElementById('pbt-badge');
            const lastRun = document.getElementById('pbt-last-run');

            if (dot && txt) {
                dot.className = `w-2 h-2 rounded-full ${enabled ? 'bg-emerald-500' : 'bg-slate-400'}`;
                txt.textContent = `PBT: ${enabled ? 'Active' : 'Paused'}`;
            }
            if (badge) {
                badge.className = `px-2 py-0.5 rounded text-[10px] font-bold ${enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}`;
                badge.textContent = enabled ? 'Active' : 'Paused';
            }
            if (lastRun && data.statuses?.latest?.timestamp) {
                lastRun.textContent = `Last run: ${data.statuses.latest.timestamp}`;
            }
        }
    } catch (e) {
        console.error('Failed to load PBT cron config:', e);
    }

    // 3. Fetch CBT Scraper Status
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_cbt_cron_config');
        const data = await resp.json();
        if (data.success && data.config) {
            const enabled = !!data.config.enabled;
            const dot = document.getElementById('kpi-cbt-status-dot');
            const txt = document.getElementById('kpi-cbt-status-text');
            const badge = document.getElementById('cbt-badge');
            const lastRun = document.getElementById('cbt-last-run');

            if (dot && txt) {
                dot.className = `w-2 h-2 rounded-full ${enabled ? 'bg-emerald-500' : 'bg-slate-400'}`;
                txt.textContent = `CBT: ${enabled ? 'Active' : 'Paused'}`;
            }
            if (badge) {
                badge.className = `px-2 py-0.5 rounded text-[10px] font-bold ${enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}`;
                badge.textContent = enabled ? 'Active' : 'Paused';
            }
            if (lastRun && data.statuses?.latest?.timestamp) {
                lastRun.textContent = `Last run: ${data.statuses.latest.timestamp}`;
            }
        }
    } catch (e) {
        console.error('Failed to load CBT cron config:', e);
    }

    // 4. Fetch Users count
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_users');
        const data = await resp.json();
        if (data.success && Array.isArray(data.users)) {
            document.getElementById('kpi-total-users').textContent = data.users.length;
            const activeCount = data.users.filter(u => parseInt(u.is_active, 10) === 1).length;
            document.getElementById('kpi-users-sub').textContent = `${activeCount} active user${activeCount === 1 ? '' : 's'}`;
        }
    } catch (e) {
        console.error('Failed to load users:', e);
    }

    // 5. Fetch Portal Settings
    await loadSystemSettings();
}

// ─── Portal Settings: Personal Details Section ─────────────
async function loadSystemSettings() {
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_system_settings');
        const data = await resp.json();
        if (data.success && data.settings) {
            updatePersonalDetailsUI(!!data.settings.show_personal_details);
        }
    } catch (e) {
        console.error('Failed to load system settings:', e);
    }
}

function updatePersonalDetailsUI(isEnabled) {
    const btn = document.getElementById('btn-toggle-personal-details');
    const knob = document.getElementById('knob-personal-details');
    const badge = document.getElementById('badge-personal-details-status');
    const label = document.getElementById('label-personal-details-state');
    const sublabel = document.getElementById('sublabel-personal-details-state');

    if (!btn || !knob || !badge || !label || !sublabel) return;

    btn.setAttribute('aria-checked', isEnabled ? 'true' : 'false');
    btn.dataset.enabled = isEnabled ? '1' : '0';

    if (isEnabled) {
        btn.className = 'relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-blue-600';
        knob.className = 'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-5';
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200 flex items-center gap-1.5 shadow-2xs';
        badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span>Visible (Email OTP Protected)</span>';
        label.textContent = 'Display Active';
        label.className = 'block text-xs font-bold text-emerald-700';
        sublabel.textContent = 'Section is visible';
    } else {
        btn.className = 'relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-300';
        knob.className = 'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-0';
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-slate-100 text-slate-600 border-slate-300 flex items-center gap-1.5 shadow-2xs';
        badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span><span>Hidden (Exam Scores Only)</span>';
        label.textContent = 'Display Hidden';
        label.className = 'block text-xs font-bold text-slate-500';
        sublabel.textContent = 'Section is hidden';
    }
}

async function togglePersonalDetails() {
    const btn = document.getElementById('btn-toggle-personal-details');
    if (!btn) return;

    const currentlyEnabled = btn.dataset.enabled === '1';
    const targetState = !currentlyEnabled;

    btn.disabled = true;
    btn.classList.add('opacity-70');

    try {
        const formData = new FormData();
        formData.append('action', 'toggle_personal_details');
        formData.append('enabled', targetState ? '1' : '0');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (data.success) {
            updatePersonalDetailsUI(data.enabled);
            showAdminToast(data.message, 'success');
        } else {
            showAdminToast(data.error || 'Failed to update setting.', 'error');
        }
    } catch (e) {
        console.error(e);
        showAdminToast('Network error while updating setting.', 'error');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-70');
    }
}

// ─── Render Recent Tables Tbody ────────────────────────────
function renderRecentTables(tables) {
    const tbody = document.getElementById('recent-tables-tbody');
    if (!tbody) return;

    if (!tables || tables.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-6 text-center text-slate-400">No exam tables found.</td></tr>`;
        return;
    }

    tbody.innerHTML = tables.map(t => {
        const isCbt = t.type === 'CBT' || t.name.endsWith('_CBT');
        const badge = isCbt
            ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700">CBT</span>'
            : '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">PBT</span>';

        const evaluated = (t.passed || 0) + (t.failed || 0);
        const passRate = evaluated > 0 ? (((t.passed || 0) / evaluated) * 100).toFixed(1) + '%' : (t.pending > 0 ? 'Pending' : '—');

        return `
            <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-4 font-mono font-bold text-slate-900">${escapeHtml(t.name)}</td>
                <td class="py-3 px-4">${badge}</td>
                <td class="py-3 px-4 text-right font-semibold text-slate-700">${(t.total || 0).toLocaleString()}</td>
                <td class="py-3 px-4 text-right font-bold text-emerald-600">${passRate}</td>
            </tr>
        `;
    }).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';