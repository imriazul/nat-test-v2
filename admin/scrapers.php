<?php
require_once __DIR__ . '/auth.php';

$pageTitle = 'Result Scrapers & Cron Service | NAT-TEST BD Admin';
$activePage = 'scrapers';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ═══ MAIN CONTENT CANVAS ═══ -->
<main class="flex-1 bg-slate-100 overflow-y-auto p-4 sm:p-6 lg:p-8 pb-28 sm:pb-36">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Page Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-slate-200">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                    <a href="./" class="hover:text-blue-600 transition">Admin</a>
                    <span>/</span>
                    <span class="text-slate-800">Scrapers & Cron</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Automated Result Scrapers</h1>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="loadCronConfig(); loadCbtCronConfig();"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl shadow-xs transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Refresh Scraper Status</span>
                </button>
            </div>
        </div>

        <!-- ── Result Scraper Main Card ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- Card Header & Service Tabs -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white p-6 pb-0">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-400 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-white">Automated Result Scraper (Cron Service)</h2>
                            <p class="text-slate-400 text-xs mt-0.5">Automates scraping official pass examinee IDs from nat-test.jp and updates database statuses once published.</p>
                        </div>
                    </div>
                </div>

                <!-- Scraper Mode Tabs: PBT (Monthly) vs CBT (Weekly) -->
                <div class="flex items-center gap-2 pt-3">
                    <button type="button" id="tab-scraper-pbt" onclick="switchScraperTab('pbt')" class="px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-blue-500 text-blue-400 bg-slate-800/80 cursor-pointer">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>PBT Result Scraper (Monthly)</span>
                        <span id="tab-badge-pbt" class="px-2 py-0.5 rounded-full text-[10px] bg-slate-700 text-slate-300 font-bold">OFF</span>
                    </button>
                    <button type="button" id="tab-scraper-cbt" onclick="switchScraperTab('cbt')" class="px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-400 hover:text-white cursor-pointer">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span>CBT Result Scraper (Weekly)</span>
                        <span id="tab-badge-cbt" class="px-2 py-0.5 rounded-full text-[10px] bg-slate-700 text-slate-300 font-bold">OFF</span>
                    </button>
                </div>
            </div>

            <!-- Panel 1: PBT Scraper -->
            <div id="scraper-panel-pbt">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">PBT Scraper Status:</span>
                        <span id="cron-badge-status" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-slate-200 text-slate-600 border border-slate-300 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            <span>Loading...</span>
                        </span>
                    </div>

                    <!-- Toggle Switch -->
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-slate-600" id="cron-toggle-label">Cron Disabled</span>
                        <button type="button" id="cron-toggle-btn" onclick="toggleCronJob()" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600" role="switch" aria-checked="false" title="Click to Enable/Disable PBT Cron">
                            <span id="cron-toggle-knob" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0" style="transform: translateX(0px); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);"></span>
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <form id="cron-config-form" onsubmit="saveCronConfig(event)" class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Scraping Exam Month -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Scraping Exam Month <span class="text-red-500">*</span>
                                </label>
                                <select id="cron-target-month" name="target_month" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition">
                                    <option value="01">01 - January</option>
                                    <option value="02">02 - February</option>
                                    <option value="03">03 - March</option>
                                    <option value="04">04 - April</option>
                                    <option value="05">05 - May</option>
                                    <option value="06">06 - June</option>
                                    <option value="07">07 - July</option>
                                    <option value="08">08 - August</option>
                                    <option value="09">09 - September</option>
                                    <option value="10">10 - October</option>
                                    <option value="11">11 - November</option>
                                    <option value="12">12 - December</option>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1.5">Exam session month to scrape</p>
                            </div>

                            <!-- Target Exam Year -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Exam Year <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center">
                                    <span class="bg-slate-200 text-slate-600 px-3 py-2.5 rounded-l-lg border border-r-0 border-slate-300 text-sm font-bold">20</span>
                                    <input type="text" id="cron-target-year" name="target_year" maxlength="2" class="w-full bg-white border border-slate-300 rounded-r-lg p-2.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition font-mono" placeholder="26">
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1.5">2-digit year (e.g. 26 = 2026)</p>
                            </div>

                            <!-- Target Center Code -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Center Code
                                </label>
                                <select id="cron-center-code" name="center_code" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition">
                                    <option value="474">474 - Dhaka Center</option>
                                    <option value="476">476 - Khulna Center</option>
                                    <option value="all">All Centers (474, 476)</option>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1.5">Dhaka (474), Khulna (476)</p>
                            </div>
                        </div>

                        <!-- Live Cron Status Banner -->
                        <div id="cron-status-banner" class="p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 border-slate-200 text-slate-700">
                            <div class="flex items-start gap-3">
                                <div id="cron-status-icon" class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-sm text-slate-800" id="cron-status-title">Cron Job Status</span>
                                        <span id="cron-last-run" class="text-xs text-slate-400 font-mono">Last run: Never</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5" id="cron-status-desc">Cron is currently disabled. Toggle the switch above to activate automated scraping.</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" onclick="runScraperNow()" id="btn-cron-run-now" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-sm cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span id="btn-cron-run-text">Run Now (Test Scrape)</span>
                                </button>
                                <button type="button" onclick="resetCronLock()" id="btn-cron-reset" class="px-3 py-2 bg-slate-200 hover:bg-red-50 hover:text-red-600 text-slate-700 text-xs font-semibold rounded-lg transition hidden cursor-pointer">
                                    Reset Completed Lock
                                </button>
                            </div>
                        </div>

                        <!-- Footer: Save & Crontab hint -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-3 border-t border-slate-100">
                            <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap min-w-0 flex-1">
                                <span class="font-bold text-slate-700 whitespace-nowrap">cPanel Master Cron Command (Runs Both PBT &amp; CBT):</span>
                                <code class="bg-slate-100 text-slate-800 px-2 py-1 rounded text-[11px] font-mono border border-slate-200 select-all break-all" id="cron-crontab-snippet">
                                    /usr/local/bin/php <?php echo dirname(__DIR__); ?>/result/cron-master.php >/dev/null 2>&1
                                </code>
                            </div>
                            <div class="flex items-center justify-end gap-3 shrink-0 w-full md:w-auto">
                                <span id="cron-save-feedback" class="text-xs font-semibold text-emerald-600 opacity-0 transition-opacity duration-300 whitespace-nowrap">✓ Settings saved!</span>
                                <button type="submit" id="btn-save-cron" class="bg-slate-900 hover:bg-slate-800 text-white font-medium px-5 py-2.5 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2 cursor-pointer shrink-0 whitespace-nowrap">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span class="whitespace-nowrap">Save Settings</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Live Scraper Output Console -->
                    <div id="cron-run-output-box" class="hidden mt-5 p-4 rounded-xl bg-slate-950 border border-slate-800 text-cyan-400 font-mono text-xs">
                        <div class="flex justify-between items-center mb-2 pb-2 border-b border-slate-800">
                            <span class="font-bold text-slate-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                                PBT Scraper Log Output:
                            </span>
                            <button type="button" onclick="document.getElementById('cron-run-output-box').classList.add('hidden')" class="text-slate-400 hover:text-white text-xs cursor-pointer">Close [✕]</button>
                        </div>
                        <pre id="cron-run-output-text" class="overflow-x-auto whitespace-pre-wrap max-h-60 leading-relaxed text-[11px] text-cyan-300 font-mono"></pre>
                    </div>
                </div>
            </div>

            <!-- Panel 2: CBT Scraper -->
            <div id="scraper-panel-cbt" class="hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">CBT Scraper Status:</span>
                        <span id="cbt-cron-badge-status" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-slate-200 text-slate-600 border border-slate-300 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            <span>Loading...</span>
                        </span>
                    </div>

                    <!-- Toggle Switch -->
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-slate-600" id="cbt-cron-toggle-label">CBT Cron Disabled</span>
                        <button type="button" id="cbt-cron-toggle-btn" onclick="toggleCbtCronJob()" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600" role="switch" aria-checked="false" title="Click to Enable/Disable CBT Cron">
                            <span id="cbt-cron-toggle-knob" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0" style="transform: translateX(0px); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);"></span>
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <form id="cbt-cron-config-form" onsubmit="saveCbtCronConfig(event)" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Target Center Code -->
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Exam Center Code
                                </label>
                                <select id="cbt-cron-center-code" name="center_code" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-purple-500 outline-none transition">
                                    <option value="all">All Centers (474 Dhaka, 476 Khulna)</option>
                                    <option value="474">474 - Dhaka Center</option>
                                    <option value="476">476 - Khulna Center</option>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1.5">Scrapes examinees for selected center</p>
                            </div>

                            <!-- KNOWN_OLD_DATA Input Field -->
                            <div class="md:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                        KNOWN_OLD_DATA (Baseline Cutoff String) <span class="text-purple-600">*</span>
                                    </label>
                                    <span class="text-[11px] font-mono text-purple-600 font-semibold">Weekly Variable</span>
                                </div>
                                <input type="text" id="cbt-cron-known-old-data" name="known_old_data" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-900 font-mono font-bold focus:ring-2 focus:ring-purple-500 outline-none transition" placeholder="For tests held through November 2, 2026 (Mon)">
                                <p class="text-[11px] text-slate-500 mt-1.5">
                                    When website changes to a new date, new results will be published/updated.
                                </p>
                            </div>
                        </div>

                        <!-- Live CBT Cron Status Banner -->
                        <div id="cbt-cron-status-banner" class="p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 border-slate-200 text-slate-700">
                            <div class="flex items-start gap-3">
                                <div id="cbt-cron-status-icon" class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-sm text-slate-800" id="cbt-cron-status-title">CBT Cron Job Status</span>
                                        <span id="cbt-cron-last-run" class="text-xs text-slate-400 font-mono">Last run: Never</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5" id="cbt-cron-status-desc">CBT Scraper is currently disabled. Toggle the switch above to activate automated weekly scraping.</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" onclick="runCbtScraperNow()" id="btn-cbt-cron-run-now" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-sm cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span id="btn-cbt-cron-run-text">Run Now (Test CBT Scrape)</span>
                                </button>
                                <button type="button" onclick="resetCbtCronLock()" id="btn-cbt-cron-reset" class="px-3 py-2 bg-slate-200 hover:bg-purple-50 hover:text-purple-700 text-slate-700 text-xs font-semibold rounded-lg transition cursor-pointer" title="Reset cutoff lock to re-process current week's results">
                                    Reset Cutoff Lock
                                </button>
                            </div>
                        </div>

                        <!-- Footer: Save & Crontab hint -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-3 border-t border-slate-100">
                            <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap min-w-0 flex-1">
                                <span class="font-bold text-slate-700 whitespace-nowrap">cPanel Master Cron Command (Runs Both PBT &amp; CBT):</span>
                                <code class="bg-slate-100 text-slate-800 px-2 py-1 rounded text-[11px] font-mono border border-slate-200 select-all break-all" id="cbt-cron-crontab-snippet">
                                    /usr/local/bin/php <?php echo dirname(__DIR__); ?>/result/cron-master.php >/dev/null 2>&1
                                </code>
                            </div>
                            <div class="flex items-center justify-end gap-3 shrink-0 w-full md:w-auto">
                                <span id="cbt-cron-save-feedback" class="text-xs font-semibold text-purple-600 opacity-0 transition-opacity duration-300 whitespace-nowrap">✓ Settings saved!</span>
                                <button type="submit" id="btn-save-cbt-cron" class="bg-slate-900 hover:bg-slate-800 text-white font-medium px-5 py-2.5 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2 cursor-pointer shrink-0 whitespace-nowrap">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span class="whitespace-nowrap">Save Settings</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Live CBT Scraper Output Console -->
                    <div id="cbt-cron-run-output-box" class="hidden mt-5 p-4 rounded-xl bg-slate-950 border border-slate-800 text-purple-300 font-mono text-xs">
                        <div class="flex justify-between items-center mb-2 pb-2 border-b border-slate-800">
                            <span class="font-bold text-slate-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                                CBT Scraper Log Output:
                            </span>
                            <button type="button" onclick="document.getElementById('cbt-cron-run-output-box').classList.add('hidden')" class="text-slate-400 hover:text-white text-xs cursor-pointer">Close [✕]</button>
                        </div>
                        <pre id="cbt-cron-run-output-text" class="overflow-x-auto whitespace-pre-wrap max-h-60 leading-relaxed text-[11px] text-purple-300 font-mono"></pre>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
let currentCronConfig = null;
let currentCbtCronConfig = null;

// ─── PBT Scraper Functions ───────────────────────────────────
async function loadCronConfig() {
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_cron_config');
        const data = await resp.json();
        if (!data.success) return;

        currentCronConfig = data.config;
        const statuses = data.statuses || {};

        const mSelect = document.getElementById('cron-target-month');
        const yInput  = document.getElementById('cron-target-year');
        const cSelect = document.getElementById('cron-center-code');

        if (mSelect && currentCronConfig.target_month) mSelect.value = currentCronConfig.target_month;
        if (yInput && currentCronConfig.target_year)   yInput.value = currentCronConfig.target_year;
        if (cSelect && currentCronConfig.center_code) cSelect.value = currentCronConfig.center_code;

        updateCronUI(currentCronConfig, statuses);
    } catch (e) {
        console.error('Failed to load cron config:', e);
    }
}

function updateCronUI(cfg, statuses = {}) {
    const toggleBtn   = document.getElementById('cron-toggle-btn');
    const toggleKnob  = document.getElementById('cron-toggle-knob');
    const toggleLabel = document.getElementById('cron-toggle-label');
    const badgeStatus = document.getElementById('cron-badge-status');
    const tabBadgePbt = document.getElementById('tab-badge-pbt');
    const lastRunSpan = document.getElementById('cron-last-run');
    const statusTitle = document.getElementById('cron-status-title');
    const statusDesc  = document.getElementById('cron-status-desc');
    const resetBtn    = document.getElementById('btn-cron-reset');
    const statusBanner= document.getElementById('cron-status-banner');
    const statusIcon  = document.getElementById('cron-status-icon');

    const isEnabled = !!cfg.enabled;

    if (tabBadgePbt) {
        if (isEnabled) {
            tabBadgePbt.className = 'px-2 py-0.5 rounded-full text-[10px] bg-blue-500/20 text-blue-300 font-bold border border-blue-500/30';
            tabBadgePbt.textContent = 'ON';
        } else {
            tabBadgePbt.className = 'px-2 py-0.5 rounded-full text-[10px] bg-slate-700 text-slate-300 font-bold';
            tabBadgePbt.textContent = 'OFF';
        }
    }

    if (toggleBtn && toggleKnob) {
        toggleBtn.setAttribute('aria-checked', isEnabled ? 'true' : 'false');
        toggleKnob.style.transition = 'transform 0.2s cubic-bezier(0.4, 0, 0.2, 1)';
        if (isEnabled) {
            toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-emerald-500 shadow-sm shadow-emerald-500/30';
            toggleKnob.style.transform = 'translateX(20px)';
            if (toggleLabel) toggleLabel.textContent = 'Cron Enabled';
        } else {
            toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
            toggleKnob.style.transform = 'translateX(0px)';
            if (toggleLabel) toggleLabel.textContent = 'Cron Disabled';
        }
    }

    const y2 = (cfg.target_year || '26').slice(-2);
    const m2 = (cfg.target_month || '10');
    const cCode = cfg.center_code || '474';
    const jobKey = `${y2}_${m2}_${cCode}`;
    const activeJob = statuses[jobKey] || null;

    if (lastRunSpan) {
        lastRunSpan.textContent = cfg.last_run ? `Last run: ${cfg.last_run}` : 'Last run: Never';
    }

    const monthName = MONTHS_FULL[m2] || 'October';

    if (!isEnabled) {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-slate-800 text-slate-300 border border-slate-700 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> DISABLED';
        }
        if (statusTitle) statusTitle.textContent = 'Cron Job is Disabled (OFF)';
        if (statusDesc) statusDesc.textContent = `Automated result scraping is currently turned off. To monitor ${monthName} 20${y2} results, toggle the switch above to Enable.`;
        if (resetBtn) resetBtn.classList.add('hidden');
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 border-slate-200 text-slate-700';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5';

    } else if (activeJob && activeJob.status === 'COMPLETED') {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> COMPLETED (HALTED)';
        }
        if (statusTitle) statusTitle.textContent = `Completed & Statuses Updated for ${monthName} 20${y2}`;
        const totalPass = activeJob.total_passed || 0;
        const passUp = activeJob.total_passed_updated !== undefined ? activeJob.total_passed_updated : (activeJob.total_updated || 0);
        const failUp = activeJob.total_failed_updated || 0;
        if (statusDesc) statusDesc.textContent = `Official results processed (${totalPass} passed examinees found). Database updated: ${passUp} set to Pass, ${failUp} set to Fail. Cron has auto-halted to save resources.`;
        if (resetBtn) resetBtn.classList.remove('hidden');
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-emerald-50 border-emerald-200 text-emerald-900';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-emerald-200 text-emerald-800 flex items-center justify-center shrink-0 mt-0.5';

    } else if (activeJob && activeJob.status === 'WAITING') {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> WAITING FOR PUBLICATION';
        }
        if (statusTitle) statusTitle.textContent = `Active: Monitoring ${monthName} 20${y2} Results`;
        if (statusDesc) statusDesc.textContent = `Results have not posted yet.`;
        if (resetBtn) resetBtn.classList.add('hidden');
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-amber-50 border-amber-200 text-amber-900';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-amber-200 text-amber-800 flex items-center justify-center shrink-0 mt-0.5';

    } else {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-blue-100 text-blue-800 border border-blue-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span> ACTIVE (MONITORING)';
        }
        if (statusTitle) statusTitle.textContent = `Active: Ready to Scrape ${monthName} 20${y2}`;
        if (statusDesc) statusDesc.textContent = `Cron job is enabled and ready to run for ${monthName} 20${y2} (Center: ${cCode}).`;
        if (resetBtn) resetBtn.classList.add('hidden');
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-blue-50 border-blue-200 text-blue-900';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-blue-200 text-blue-800 flex items-center justify-center shrink-0 mt-0.5';
    }
}

async function toggleCronJob() {
    const toggleBtn = document.getElementById('cron-toggle-btn');
    const toggleKnob = document.getElementById('cron-toggle-knob');
    const toggleLabel = document.getElementById('cron-toggle-label');
    if (!currentCronConfig) return;

    const willEnable = !currentCronConfig.enabled;
    toggleBtn.disabled = true;

    toggleBtn.setAttribute('aria-checked', willEnable ? 'true' : 'false');
    if (willEnable) {
        toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-emerald-500 shadow-sm shadow-emerald-500/30';
        toggleKnob.style.transform = 'translateX(20px)';
        if (toggleLabel) toggleLabel.textContent = 'Cron Enabled';
    } else {
        toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
        toggleKnob.style.transform = 'translateX(0px)';
        if (toggleLabel) toggleLabel.textContent = 'Cron Disabled';
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'toggle_cron');
        formData.append('enabled', willEnable ? '1' : '0');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        const data = await resp.json();
        if (data.success) {
            currentCronConfig.enabled = data.enabled;
            loadCronConfig();
            showAdminToast(data.enabled ? 'PBT Scraper Enabled' : 'PBT Scraper Disabled', 'info');
        } else {
            alert(data.error || 'Failed to toggle cron job.');
            loadCronConfig();
        }
    } catch (e) {
        console.error(e);
        alert('Connection error: ' + (e.message || 'Could not communicate with server.'));
        loadCronConfig();
    } finally {
        toggleBtn.disabled = false;
    }
}

async function saveCronConfig(e) {
    if (e) e.preventDefault();

    const form = document.getElementById('cron-config-form');
    const saveBtn = document.getElementById('btn-save-cron');
    const feedback = document.getElementById('cron-save-feedback');

    saveBtn.disabled = true;
    saveBtn.classList.add('opacity-70');

    try {
        const formData = new FormData(form);
        formData.append('action', 'save_cron_config');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (data.success) {
            currentCronConfig = data.config;
            feedback.classList.remove('opacity-0');
            setTimeout(() => feedback.classList.add('opacity-0'), 2500);
            loadCronConfig();
            showAdminToast('PBT Scraper settings saved', 'success');
        } else {
            alert(data.error || 'Failed to save settings.');
        }
    } catch (e) {
        console.error(e);
        alert('Error saving cron configuration: ' + (e.message || e));
    } finally {
        saveBtn.disabled = false;
        saveBtn.classList.remove('opacity-70');
    }
}

async function resetCronLock() {
    if (!confirm('Are you sure you want to reset the completion lock? This will allow the scraper to re-process results for this session.')) {
        return;
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'reset_cron_status');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await resp.json();
        if (data.success) {
            showAdminToast(data.message || 'Status lock reset successfully.', 'success');
            loadCronConfig();
        }
    } catch (e) {
        console.error(e);
        alert('Error resetting status lock.');
    }
}

async function runScraperNow() {
    const btn = document.getElementById('btn-cron-run-now');
    const btnText = document.getElementById('btn-cron-run-text');
    const outBox = document.getElementById('cron-run-output-box');
    const outText = document.getElementById('cron-run-output-text');

    btn.disabled = true;
    btn.classList.add('opacity-75');
    btnText.textContent = 'Scraping...';

    outBox.classList.remove('hidden');
    outText.textContent = 'Connecting to nat-test.jp and scraping results... Please wait...\n';

    const targetMonth  = document.getElementById('cron-target-month')?.value || (currentCronConfig?.target_month || '10');
    const targetYear   = document.getElementById('cron-target-year')?.value  || (currentCronConfig?.target_year  || '26');
    const targetCenter = document.getElementById('cron-center-code')?.value || (currentCronConfig?.center_code || '474');

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'run_cron_now');
        formData.append('target_month', targetMonth);
        formData.append('target_year', targetYear);
        formData.append('center_code', targetCenter);

        const adminResp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        const rawText = await adminResp.text();
        let data = null;
        try {
            data = JSON.parse(rawText);
        } catch (jsonErr) {
            outText.textContent = rawText.trim() || 'Scraping completed.';
        }

        if (data) {
            if (data.output && data.output.trim() !== '') {
                outText.textContent = data.output;
            } else if (Array.isArray(data.logs) && data.logs.length > 0) {
                outText.textContent = data.logs.join('\n');
            } else if (data.message) {
                outText.textContent = data.message;
            } else if (data.error) {
                outText.textContent = 'Error: ' + data.error;
            } else {
                outText.textContent = JSON.stringify(data, null, 2);
            }

            if (data.config) {
                currentCronConfig = data.config;
            }
        }

        loadCronConfig();
        showAdminToast('Scrape execution finished', 'info');

    } catch (e) {
        console.error('Scraper run error:', e);
        outText.textContent = 'Execution error: ' + (e.message || 'Could not communicate with server.');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-75');
        btnText.textContent = 'Run Now (Test Scrape)';
    }
}

// ─── Scraper Tabs (PBT vs CBT) ────────────────────────────────
function switchScraperTab(tab) {
    const pbtTab = document.getElementById('tab-scraper-pbt');
    const cbtTab = document.getElementById('tab-scraper-cbt');
    const pbtPanel = document.getElementById('scraper-panel-pbt');
    const cbtPanel = document.getElementById('scraper-panel-cbt');

    if (tab === 'pbt') {
        if (pbtTab) pbtTab.className = 'px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-blue-500 text-blue-400 bg-slate-800/80 cursor-pointer';
        if (cbtTab) cbtTab.className = 'px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-400 hover:text-white cursor-pointer';
        if (pbtPanel) pbtPanel.classList.remove('hidden');
        if (cbtPanel) cbtPanel.classList.add('hidden');
    } else {
        if (cbtTab) cbtTab.className = 'px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-purple-500 text-purple-400 bg-slate-800/80 cursor-pointer';
        if (pbtTab) pbtTab.className = 'px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-400 hover:text-white cursor-pointer';
        if (cbtPanel) cbtPanel.classList.remove('hidden');
        if (pbtPanel) pbtPanel.classList.add('hidden');
    }
}

// ─── CBT Result Scraper JavaScript ────────────────────────────
async function loadCbtCronConfig() {
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_cbt_cron_config');
        const data = await resp.json();
        if (!data.success) return;

        currentCbtCronConfig = data.config;
        const metrics = data.metrics || {};

        const cSelect = document.getElementById('cbt-cron-center-code');
        const knownOldInput = document.getElementById('cbt-cron-known-old-data');

        if (cSelect && currentCbtCronConfig.center_code) cSelect.value = currentCbtCronConfig.center_code;
        if (knownOldInput) {
            knownOldInput.value = currentCbtCronConfig.known_old_data || currentCbtCronConfig.last_published_cutoff || '';
        }

        updateCbtCronUI(currentCbtCronConfig, metrics);
    } catch (e) {
        console.error('Failed to load CBT cron config:', e);
    }
}

function updateCbtCronUI(cfg, metrics = {}) {
    const toggleBtn    = document.getElementById('cbt-cron-toggle-btn');
    const toggleKnob   = document.getElementById('cbt-cron-toggle-knob');
    const toggleLabel  = document.getElementById('cbt-cron-toggle-label');
    const badgeStatus  = document.getElementById('cbt-cron-badge-status');
    const tabBadge     = document.getElementById('tab-badge-cbt');
    const lastRunSpan  = document.getElementById('cbt-cron-last-run');
    const statusTitle  = document.getElementById('cbt-cron-status-title');
    const statusDesc   = document.getElementById('cbt-cron-status-desc');
    const statusBanner = document.getElementById('cbt-cron-status-banner');
    const statusIcon   = document.getElementById('cbt-cron-status-icon');

    const isEnabled = !!cfg.enabled;

    if (tabBadge) {
        if (isEnabled) {
            tabBadge.className = 'px-2 py-0.5 rounded-full text-[10px] bg-purple-500/20 text-purple-300 font-bold border border-purple-500/30';
            tabBadge.textContent = 'ON';
        } else {
            tabBadge.className = 'px-2 py-0.5 rounded-full text-[10px] bg-slate-700 text-slate-300 font-bold';
            tabBadge.textContent = 'OFF';
        }
    }

    if (toggleBtn && toggleKnob) {
        toggleBtn.setAttribute('aria-checked', isEnabled ? 'true' : 'false');
        toggleKnob.style.transition = 'transform 0.2s cubic-bezier(0.4, 0, 0.2, 1)';
        if (isEnabled) {
            toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-purple-600 shadow-sm shadow-purple-600/30';
            toggleKnob.style.transform = 'translateX(20px)';
            if (toggleLabel) toggleLabel.textContent = 'CBT Cron Enabled';
        } else {
            toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
            toggleKnob.style.transform = 'translateX(0px)';
            if (toggleLabel) toggleLabel.textContent = 'CBT Cron Disabled';
        }
    }

    if (lastRunSpan) {
        lastRunSpan.textContent = cfg.last_run ? `Last run: ${cfg.last_run}` : 'Last run: Never';
    }

    if (!isEnabled) {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-slate-200 text-slate-600 border border-slate-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> DISABLED';
        }
        if (statusTitle) statusTitle.textContent = 'CBT Scraper is Disabled (OFF)';
        if (statusDesc) statusDesc.textContent = 'Weekly CBT result scraping is currently disabled. Toggle the switch above to activate automatic monitoring.';
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 border-slate-200 text-slate-700';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5';

    } else if (cfg.last_status === 'COMPLETED') {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> COMPLETED (SYNCED)';
        }
        if (statusTitle) statusTitle.textContent = 'CBT Results Synced Successfully';
        if (statusDesc) statusDesc.textContent = cfg.last_message || `Official results through ${cfg.last_published_cutoff} processed and database updated.`;
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-emerald-50 border-emerald-200 text-emerald-900';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-emerald-200 text-emerald-800 flex items-center justify-center shrink-0 mt-0.5';

    } else if (cfg.last_status === 'WAITING') {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-purple-100 text-purple-800 border border-purple-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></span> STANDING BY';
        }
        if (statusTitle) statusTitle.textContent = 'Monitoring: Waiting for Next Weekly CBT Release';
        if (statusDesc) statusDesc.textContent = cfg.last_message || 'Website results are current. Standing by for next release.';
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-purple-50 border-purple-200 text-purple-900';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-purple-200 text-purple-800 flex items-center justify-center shrink-0 mt-0.5';

    } else {
        if (badgeStatus) {
            badgeStatus.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-purple-100 text-purple-800 border border-purple-300 flex items-center gap-1.5';
            badgeStatus.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></span> ACTIVE (MONITORING)';
        }
        if (statusTitle) statusTitle.textContent = 'CBT Scraper Active & Ready';
        if (statusDesc) statusDesc.textContent = 'Cron will check weekly CBT result releases and immediately sync results to the database.';
        if (statusBanner) statusBanner.className = 'p-4 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3 bg-purple-50 border-purple-200 text-purple-900';
        if (statusIcon) statusIcon.className = 'w-8 h-8 rounded-lg bg-purple-200 text-purple-800 flex items-center justify-center shrink-0 mt-0.5';
    }
}

async function toggleCbtCronJob() {
    const toggleBtn = document.getElementById('cbt-cron-toggle-btn');
    const toggleKnob = document.getElementById('cbt-cron-toggle-knob');
    const toggleLabel = document.getElementById('cbt-cron-toggle-label');
    if (!currentCbtCronConfig) return;

    const willEnable = !currentCbtCronConfig.enabled;
    toggleBtn.disabled = true;

    toggleBtn.setAttribute('aria-checked', willEnable ? 'true' : 'false');
    if (willEnable) {
        toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-purple-600 shadow-sm shadow-purple-600/30';
        toggleKnob.style.transform = 'translateX(20px)';
        if (toggleLabel) toggleLabel.textContent = 'CBT Cron Enabled';
    } else {
        toggleBtn.className = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
        toggleKnob.style.transform = 'translateX(0px)';
        if (toggleLabel) toggleLabel.textContent = 'CBT Cron Disabled';
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'toggle_cbt_cron');
        formData.append('enabled', willEnable ? '1' : '0');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        const data = await resp.json();
        if (data.success) {
            currentCbtCronConfig.enabled = data.enabled;
            loadCbtCronConfig();
            showAdminToast(data.enabled ? 'CBT Scraper Enabled' : 'CBT Scraper Disabled', 'info');
        } else {
            alert(data.error || 'Failed to toggle CBT cron job.');
            loadCbtCronConfig();
        }
    } catch (e) {
        console.error(e);
        alert('Connection error: ' + (e.message || 'Could not communicate with server.'));
        loadCbtCronConfig();
    } finally {
        toggleBtn.disabled = false;
    }
}

async function saveCbtCronConfig(e) {
    if (e) e.preventDefault();

    const form = document.getElementById('cbt-cron-config-form');
    const saveBtn = document.getElementById('btn-save-cbt-cron');
    const feedback = document.getElementById('cbt-cron-save-feedback');

    saveBtn.disabled = true;
    saveBtn.classList.add('opacity-70');

    try {
        const formData = new FormData(form);
        formData.append('action', 'save_cbt_cron_config');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (data.success) {
            currentCbtCronConfig = data.config;
            feedback.classList.remove('opacity-0');
            setTimeout(() => feedback.classList.add('opacity-0'), 2500);
            loadCbtCronConfig();
            showAdminToast('CBT Scraper settings saved', 'success');
        } else {
            alert(data.error || 'Failed to save CBT settings.');
        }
    } catch (e) {
        console.error(e);
        alert('Error saving CBT cron configuration: ' + (e.message || e));
    } finally {
        saveBtn.disabled = false;
        saveBtn.classList.remove('opacity-70');
    }
}

async function resetCbtCronLock() {
    if (!confirm('Are you sure you want to reset the CBT cutoff lock? This will allow the scraper to re-process and re-sync the latest result batch.')) {
        return;
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'reset_cbt_cron_status');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await resp.json();
        if (data.success) {
            showAdminToast('CBT KNOWN_OLD_DATA reset successfully.', 'success');
            const knownOldInput = document.getElementById('cbt-cron-known-old-data');
            if (knownOldInput) knownOldInput.value = '';
            loadCbtCronConfig();
        }
    } catch (e) {
        console.error(e);
        alert('Error resetting CBT status lock.');
    }
}

async function runCbtScraperNow() {
    const btn = document.getElementById('btn-cbt-cron-run-now');
    const btnText = document.getElementById('btn-cbt-cron-run-text');
    const outBox = document.getElementById('cbt-cron-run-output-box');
    const outText = document.getElementById('cbt-cron-run-output-text');

    btn.disabled = true;
    btn.classList.add('opacity-75');
    btnText.textContent = 'Scraping CBT...';

    outBox.classList.remove('hidden');
    outText.textContent = 'Connecting to nat-test.jp and scraping weekly CBT results... Please wait...\n';

    const targetCenter = document.getElementById('cbt-cron-center-code')?.value || (currentCbtCronConfig?.center_code || 'all');

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'run_cbt_cron_now');
        formData.append('center_code', targetCenter);

        const adminResp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        if (!adminResp.ok) {
            const rawText = await adminResp.text();
            outText.textContent = `❌ Server Error (HTTP ${adminResp.status}):\n${rawText || adminResp.statusText}`;
            return;
        }

        if (adminResp.body && typeof adminResp.body.getReader === 'function') {
            const reader = adminResp.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let isFirstChunk = true;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                let chunk = decoder.decode(value, { stream: true });
                if (isFirstChunk) {
                    chunk = chunk.replace(/^:[ ]*\n/, '');
                    if (chunk.trim() !== '') {
                        outText.textContent = '';
                        isFirstChunk = false;
                    }
                }
                if (chunk) {
                    outText.textContent += chunk;
                    outText.scrollTop = outText.scrollHeight;
                }
            }
        } else {
            const rawText = await adminResp.text();
            outText.textContent = rawText.replace(/^:[ ]*\n/, '');
        }

        loadCbtCronConfig();
        showAdminToast('CBT Scrape execution finished', 'info');

    } catch (e) {
        console.error('CBT Scraper run error:', e);
        outText.textContent = 'Execution error: ' + (e.message || 'Could not communicate with server.');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-75');
        btnText.textContent = 'Run Now (Test CBT Scrape)';
    }
}

// Initial load on page ready
document.addEventListener('DOMContentLoaded', () => {
    loadCronConfig();
    loadCbtCronConfig();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>