<?php
/**
 * Admin Panel - Exam Month Tables
 * Manage, filter, search, inspect examinee records, and edit candidate results.
 */
require_once __DIR__ . '/auth.php';

$pageTitle = 'Exam Month Tables | NAT-TEST BD Admin';
$activePage = 'tables';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 overflow-y-auto bg-slate-50">
    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 pb-28 sm:pb-36 space-y-6">

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- VIEW 1: EXAM MONTH TABLES LIST                            -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div id="section-tables-list" class="space-y-6">

            <!-- Page Header & Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Exam Month Tables</h1>
                        <span id="badge-total-tables" class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700">0</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Click any batch card to inspect and manage candidate information and result status.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="loadTables()" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 rounded-xl shadow-xs transition cursor-pointer">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Refresh</span>
                    </button>
                    <a href="import" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Import Batch</span>
                    </a>
                </div>
            </div>

            <!-- Metric KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-slate-400 text-[11px] font-semibold uppercase tracking-wider block">Total Tables</span>
                    <span id="stat-total-tables" class="text-2xl font-black text-slate-900 mt-1 block">0</span>
                    <span class="text-[11px] text-slate-400">All registered batches</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-blue-500 text-[11px] font-semibold uppercase tracking-wider block">PBT Batches</span>
                    <span id="stat-pbt-tables" class="text-2xl font-black text-blue-600 mt-1 block">0</span>
                    <span class="text-[11px] text-slate-400">Paper-based tests</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-purple-500 text-[11px] font-semibold uppercase tracking-wider block">CBT Batches</span>
                    <span id="stat-cbt-tables" class="text-2xl font-black text-purple-600 mt-1 block">0</span>
                    <span class="text-[11px] text-slate-400">Computer-based tests</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-slate-400 text-[11px] font-semibold uppercase tracking-wider block">Total Examinees</span>
                    <span id="stat-total-examinees" class="text-2xl font-black text-slate-900 mt-1 block">0</span>
                    <span class="text-[11px] text-slate-400">Combined candidate records</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs col-span-2 lg:col-span-1">
                    <span class="text-emerald-500 text-[11px] font-semibold uppercase tracking-wider block">Overall Pass Rate</span>
                    <span id="stat-overall-pass-rate" class="text-2xl font-black text-emerald-600 mt-1 block">—</span>
                    <span class="text-[11px] text-slate-400">Evaluated candidates</span>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
                <!-- Filter Tabs: All, PBT, CBT -->
                <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs shrink-0">
                    <button type="button" id="tab-table-all" onclick="setExamTableFilter('all')" class="px-3.5 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 bg-white text-slate-900 shadow-sm cursor-pointer">
                        <span>All</span>
                        <span id="badge-table-count-all" class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">0</span>
                    </button>
                    <button type="button" id="tab-table-pbt" onclick="setExamTableFilter('pbt')" class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>PBT</span>
                        <span id="badge-table-count-pbt" class="px-1.5 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700 font-bold">0</span>
                    </button>
                    <button type="button" id="tab-table-cbt" onclick="setExamTableFilter('cbt')" class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span>CBT</span>
                        <span id="badge-table-count-cbt" class="px-1.5 py-0.5 rounded-full text-[10px] bg-purple-100 text-purple-700 font-bold">0</span>
                    </button>
                </div>

                <!-- Search and Order Controls -->
                <div class="flex items-center gap-2.5 w-full md:w-auto">
                    <div class="relative flex-1 md:w-64">
                        <input type="text" id="table-search-input" oninput="handleTableSearch()" placeholder="Search tables (e.g. 2026, Mar)..." class="w-full pl-9 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <select id="table-sort-order" onchange="renderTableCards()" class="bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="chronological_desc">Newest First</option>
                        <option value="chronological_asc">Oldest First</option>
                        <option value="records_desc">Most Records</option>
                        <option value="name_asc">Name A → Z</option>
                    </select>
                </div>
            </div>

            <!-- Tables Grid Container -->
            <div id="tables-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="col-span-full text-center py-16 bg-white rounded-2xl border border-slate-200">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-blue-600 mb-3"></div>
                    <p class="text-slate-500 font-medium text-sm">Loading exam month tables…</p>
                </div>
            </div>

        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- VIEW 2: EXAMINEE INFORMATION TABLE (DETAIL VIEW)          -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div id="section-table-detail" class="hidden space-y-6">

            <!-- Detail Breadcrumb & Action Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeTableExaminees()" class="p-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition shadow-xs cursor-pointer group" title="Back to tables list">
                        <svg class="w-5 h-5 transform group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </button>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Exam Batch:</span>
                            <h2 id="detail-table-title" class="text-xl sm:text-2xl font-black font-mono text-slate-900 tracking-tight">Nov_26_CBT</h2>
                            <span id="detail-table-badge" class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-purple-100 text-purple-700 border border-purple-200">CBT</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Manage candidates, edit information, and update Pass / Fail / Pending results.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="loadTableExaminees(currentPage)" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl shadow-xs transition cursor-pointer">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Refresh</span>
                    </button>

                    <!-- CSV Download Dropdown -->
                    <div class="relative inline-block text-left" id="export-dropdown-container">
                        <button type="button" onclick="toggleExportDropdown(event)" id="btn-export-dropdown" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl shadow-xs transition cursor-pointer">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Download CSV</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div id="export-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-white shadow-xl border border-slate-200 py-1.5 z-30 animate-fade-up">
                            <div class="px-3.5 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                Export Options
                            </div>

                            <!-- 1. All Users -->
                            <button type="button" onclick="downloadExamineesCsv('all')" class="w-full text-left px-3.5 py-2.5 text-xs text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition flex items-center justify-between cursor-pointer">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span class="font-semibold">All Examinees</span>
                                </span>
                                <span id="export-count-all" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">0</span>
                            </button>

                            <!-- 2. Pass Users -->
                            <button type="button" onclick="downloadExamineesCsv('pass')" class="w-full text-left px-3.5 py-2.5 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition flex items-center justify-between cursor-pointer">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span class="font-semibold">Pass Examinees</span>
                                </span>
                                <span id="export-count-pass" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">0</span>
                            </button>

                            <!-- 3. Fail Users -->
                            <button type="button" onclick="downloadExamineesCsv('fail')" class="w-full text-left px-3.5 py-2.5 text-xs text-slate-700 hover:bg-rose-50 hover:text-rose-700 transition flex items-center justify-between cursor-pointer">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span class="font-semibold">Fail Examinees</span>
                                </span>
                                <span id="export-count-fail" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">0</span>
                            </button>
                        </div>
                    </div>

                    <a id="btn-import-more" href="import" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Add Candidates</span>
                    </a>
                </div>
            </div>

            <!-- Active Batch KPI Stats Ribbon -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Total Candidates</span>
                    <span id="detail-stat-total" class="text-xl font-black text-slate-900 mt-0.5 block">0</span>
                </div>
                <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-emerald-600 text-[10px] font-bold uppercase tracking-wider block">Passed</span>
                    <span id="detail-stat-pass" class="text-xl font-black text-emerald-600 mt-0.5 block">0</span>
                </div>
                <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-rose-600 text-[10px] font-bold uppercase tracking-wider block">Failed</span>
                    <span id="detail-stat-fail" class="text-xl font-black text-rose-600 mt-0.5 block">0</span>
                </div>
                <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                    <span class="text-amber-600 text-[10px] font-bold uppercase tracking-wider block">Pending</span>
                    <span id="detail-stat-pending" class="text-xl font-black text-amber-600 mt-0.5 block">0</span>
                </div>
                <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs col-span-2 sm:col-span-1">
                    <span class="text-blue-600 text-[10px] font-bold uppercase tracking-wider block">Pass Rate</span>
                    <span id="detail-stat-rate" class="text-xl font-black text-blue-600 mt-0.5 block">—</span>
                </div>
            </div>

            <!-- Examinee Search & Filter Toolbar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
                <!-- Status Filter Pills & Level Filter Dropdown -->
                <div class="flex items-center flex-wrap gap-2.5">
                    <!-- Status Filter Pills -->
                    <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs shrink-0 flex-wrap gap-1">
                        <button type="button" id="tab-status-all" onclick="setExamineeStatusFilter('all')" class="px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 bg-white text-slate-900 shadow-sm cursor-pointer">
                            <span>All</span>
                            <span id="badge-status-count-all" class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">0</span>
                        </button>
                        <button type="button" id="tab-status-pass" onclick="setExamineeStatusFilter('Pass')" class="px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-emerald-700 cursor-pointer">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Pass</span>
                            <span id="badge-status-count-pass" class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-bold">0</span>
                        </button>
                        <button type="button" id="tab-status-fail" onclick="setExamineeStatusFilter('Fail')" class="px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-rose-700 cursor-pointer">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span>Fail</span>
                            <span id="badge-status-count-fail" class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-100 text-rose-800 font-bold">0</span>
                        </button>
                        <button type="button" id="tab-status-pending" onclick="setExamineeStatusFilter('Pending')" class="px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-amber-700 cursor-pointer">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>Pending</span>
                            <span id="badge-status-count-pending" class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-100 text-amber-800 font-bold">0</span>
                        </button>
                    </div>

                    <!-- Exam Level Dropdown Filter -->
                    <div class="relative shrink-0">
                        <select id="examinee-level-filter" onchange="changeExamineeLevelFilter()" class="appearance-none bg-slate-100 hover:bg-slate-200/80 border border-slate-200 text-slate-800 font-bold text-xs rounded-xl pl-3 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer transition shadow-2xs">
                            <option value="all">All Levels</option>
                            <option value="5">N5</option>
                            <option value="4">N4</option>
                            <option value="3">N3</option>
                            <option value="2">N2</option>
                            <option value="1">N1</option>
                        </select>
                        <svg class="w-3.5 h-3.5 text-slate-500 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </div>

                <!-- Search Input & Per Page -->
                <div class="flex items-center gap-2.5 w-full md:w-auto">
                    <div class="relative flex-1 md:w-80">
                        <input type="text" id="examinee-search-input" oninput="handleExamineeSearch()" placeholder="Search examinee #, name, or email..." class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <button type="button" id="btn-clear-examinee-search" onclick="clearExamineeSearch()" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold p-0.5">✕</button>
                    </div>

                    <select id="examinee-limit-select" onchange="changeExamineeLimit()" class="bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                        <option value="50">50 / page</option>
                        <option value="100" selected>100 / page</option>
                        <option value="250">250 / page</option>
                        <option value="500">500 / page</option>
                    </select>
                </div>
            </div>

            <!-- Examinees Data Table Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200 select-none">
                            <tr>
                                <th class="py-3.5 px-4 w-12 text-slate-400 text-center">#</th>
                                <th class="py-3.5 px-4">Examinee Number</th>
                                <th class="py-3.5 px-3 text-center">Level</th>
                                <th class="py-3.5 px-4">Candidate Name</th>
                                <th class="py-3.5 px-4">Email Address</th>
                                <th class="py-3.5 px-4">Phone</th>
                                <th class="py-3.5 px-4">Date of Birth</th>
                                <th class="py-3.5 px-4 text-center">Result Status</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="examinees-tbody" class="divide-y divide-slate-100">
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-slate-200 border-t-blue-600 mb-2"></div>
                                    <p>Loading examinee records…</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <div>
                        Showing <span id="pag-start" class="font-bold text-slate-800">0</span> to <span id="pag-end" class="font-bold text-slate-800">0</span> of <span id="pag-total" class="font-bold text-slate-800">0</span> candidates
                    </div>
                    <div id="pag-controls" class="flex items-center gap-1">
                        <button type="button" id="btn-pag-prev" onclick="prevExamineePage()" class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Previous
                        </button>
                        <span id="pag-indicator" class="px-3 py-1.5 font-bold text-slate-800">Page 1 of 1</span>
                        <button type="button" id="btn-pag-next" onclick="nextExamineePage()" class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Generous Bottom Spacing so last row cards never get clipped -->
        <div class="h-12 sm:h-16" aria-hidden="true"></div>

    </main>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- MODAL: EDIT EXAMINEE INFORMATION                              -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="modal-edit-examinee" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm hidden p-4">
    <div class="bg-white w-full max-w-lg rounded-3xl p-6 sm:p-7 shadow-2xl border border-slate-100 relative animate-fade-up">
        <!-- Close Button -->
        <button type="button" onclick="closeEditExamineeModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
            <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Edit Examinee Information</h3>
                <p id="edit-modal-sub" class="text-xs text-slate-500">Updating record in table</p>
            </div>
        </div>

        <form id="form-edit-examinee" onsubmit="saveExamineeEdit(event)" class="space-y-4">
            <input type="hidden" id="edit-id" name="id">
            <input type="hidden" id="edit-table-name" name="table_name">

            <div id="edit-error-banner" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="edit-error-text">Failed to save changes.</span>
            </div>

            <!-- Full Name -->
            <div>
                <label for="edit-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Candidate Full Name <span class="text-red-500">*</span></label>
                <input type="text" id="edit-name" name="name" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium transition" placeholder="e.g. MOHAMMAD RAHMAN">
            </div>

            <!-- Examinee Number & Result Status (Grid 2 cols) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label for="edit-examinee-number" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Examinee Number <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-examinee-number" name="examinee_number" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="14-digit number">
                </div>

                <div>
                    <label for="edit-status" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Result Status <span class="text-red-500">*</span></label>
                    <select id="edit-status" name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition cursor-pointer">
                        <option value="Pass" class="text-emerald-700 font-bold">Pass</option>
                        <option value="Fail" class="text-rose-700 font-bold">Fail</option>
                        <option value="Pending" class="text-amber-700 font-bold">Pending</option>
                    </select>
                </div>
            </div>

            <!-- Email Address -->
            <div>
                <label for="edit-email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                <input type="email" id="edit-email" name="email" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="candidate@example.com">
            </div>

            <!-- DOB & Phone (Grid 2 cols) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label for="edit-dob" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Date of Birth</label>
                    <input type="date" id="edit-dob" name="dob" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>

                <div>
                    <label for="edit-phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" id="edit-phone" name="phone" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="+8801...">
                </div>
            </div>

            <!-- Form Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeEditExamineeModal()" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-save-examinee" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                    <span id="btn-save-examinee-text">Save Changes</span>
                    <div id="spinner-save-examinee" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin hidden"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentTablesData = [];
let currentExamTableFilter = 'all'; // 'all', 'pbt', 'cbt'
let searchFilterQuery = '';

// Examinee detail view state
let activeDetailTable = '';
let currentExamineesList = [];
let currentExamineeSearch = '';
let currentExamineeStatus = 'all';
let currentExamineeLevel = 'all';
let currentPage = 1;
let currentLimit = 100;
let totalExamineePages = 1;
let searchDebounceTimeout = null;

// ─── Set Active Filter Tab ─────────────────────────────────
function setExamTableFilter(filter) {
    currentExamTableFilter = filter;

    const tabs = [
        { id: 'tab-table-all', type: 'all' },
        { id: 'tab-table-pbt', type: 'pbt' },
        { id: 'tab-table-cbt', type: 'cbt' }
    ];

    tabs.forEach(t => {
        const btn = document.getElementById(t.id);
        if (!btn) return;
        if (t.type === filter) {
            btn.className = "px-3.5 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 bg-white text-slate-900 shadow-sm cursor-pointer";
        } else {
            btn.className = "px-3.5 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900 cursor-pointer";
        }
    });

    renderTableCards();
}

// ─── Real-Time Table Search ────────────────────────────────
function handleTableSearch() {
    searchFilterQuery = (document.getElementById('table-search-input').value || '').trim().toLowerCase();
    renderTableCards();
}

// ─── Fetch Tables from Backend ─────────────────────────────
async function loadTables() {
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=stats');
        const data = await resp.json();
        currentTablesData = data.tables || [];

        // Aggregate statistics
        let totalRecords = 0, totalPass = 0, totalFail = 0, totalPending = 0;
        let cbtCount = 0, pbtCount = 0;

        currentTablesData.forEach(t => {
            totalRecords += (t.total || 0);
            totalPass    += (t.passed || 0);
            totalFail    += (t.failed || 0);
            totalPending += (t.pending || 0);

            if (t.type === 'CBT' || t.name.endsWith('_CBT')) {
                cbtCount++;
            } else {
                pbtCount++;
            }
        });

        // Update KPI card badges
        document.getElementById('stat-total-tables').textContent = currentTablesData.length;
        document.getElementById('stat-pbt-tables').textContent   = pbtCount;
        document.getElementById('stat-cbt-tables').textContent   = cbtCount;
        document.getElementById('stat-total-examinees').textContent = totalRecords.toLocaleString();

        const evaluatedTotal = totalPass + totalFail;
        const overallRate = evaluatedTotal > 0 ? ((totalPass / evaluatedTotal) * 100).toFixed(1) + '%' : (totalPending > 0 ? 'Pending' : '—');
        document.getElementById('stat-overall-pass-rate').textContent = overallRate;

        // Header and tab counts
        const bTotal = document.getElementById('badge-total-tables');
        if (bTotal) bTotal.textContent = currentTablesData.length;

        const bAll = document.getElementById('badge-table-count-all');
        if (bAll) bAll.textContent = currentTablesData.length;

        const bPbt = document.getElementById('badge-table-count-pbt');
        if (bPbt) bPbt.textContent = pbtCount;

        const bCbt = document.getElementById('badge-table-count-cbt');
        if (bCbt) bCbt.textContent = cbtCount;

        renderTableCards();

        // Check if a specific table was requested in URL parameter (e.g. ?table=Nov_26_CBT)
        const urlParams = new URLSearchParams(window.location.search);
        const reqTable = urlParams.get('table');
        if (reqTable && currentTablesData.some(t => t.name === reqTable) && !activeDetailTable) {
            openTableExaminees(reqTable);
        }
    } catch (e) {
        console.error('Failed to load tables:', e);
        showAdminToast('Failed to load exam tables from server.', 'error');
    }
}

// ─── Render Table Cards ────────────────────────────────────
function renderTableCards() {
    const grid = document.getElementById('tables-grid');
    if (!grid) return;

    let filtered = currentTablesData;

    // Filter by type tab
    if (currentExamTableFilter === 'cbt') {
        filtered = filtered.filter(t => t.type === 'CBT' || t.name.endsWith('_CBT'));
    } else if (currentExamTableFilter === 'pbt') {
        filtered = filtered.filter(t => t.type === 'PBT' || t.name.endsWith('_PBT') || (!t.type && !t.name.endsWith('_CBT')));
    }

    // Filter by text search query
    if (searchFilterQuery) {
        filtered = filtered.filter(t => {
            const name = (t.name || '').toLowerCase();
            const year = (t.year || '').toString().toLowerCase();
            const month = (t.month || '').toLowerCase();
            return name.includes(searchFilterQuery) || year.includes(searchFilterQuery) || month.includes(searchFilterQuery);
        });
    }

    // Sort order
    const sortMode = document.getElementById('table-sort-order')?.value || 'chronological_desc';
    const monthOrder = {
        'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4,
        'May': 5, 'Jun': 6, 'Jul': 7, 'Aug': 8,
        'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12
    };

    filtered = [...filtered].sort((a, b) => {
        if (sortMode === 'records_desc') {
            return (b.total || 0) - (a.total || 0);
        }
        if (sortMode === 'name_asc') {
            return (a.name || '').localeCompare(b.name || '');
        }

        const yA = parseInt(a.year, 10) || 0;
        const yB = parseInt(b.year, 10) || 0;
        const mA = monthOrder[a.month] || 99;
        const mB = monthOrder[b.month] || 99;

        if (sortMode === 'chronological_asc') {
            if (yA !== yB) return yA - yB;
            if (mA !== mB) return mA - mB;
            return (a.name || '').localeCompare(b.name || '');
        } else {
            // chronological_desc (Newest First)
            if (yA !== yB) return yB - yA;
            if (mA !== mB) return mB - mA;
            return (b.name || '').localeCompare(a.name || '');
        }
    });

    if (filtered.length === 0) {
        let emptyMsg = 'No exam tables match your criteria.';
        if (searchFilterQuery) {
            emptyMsg = `No tables found matching "${escapeHtml(searchFilterQuery)}". Try clearing the search.`;
        } else if (currentExamTableFilter === 'cbt') {
            emptyMsg = 'No CBT exam tables found. Go to "Import Examinees" to upload CBT batches.';
        } else if (currentExamTableFilter === 'pbt') {
            emptyMsg = 'No PBT exam tables found. Go to "Import Examinees" to upload PBT batches.';
        }

        grid.innerHTML = `
            <div class="col-span-full text-center py-16 bg-white rounded-2xl border border-slate-200">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                <p class="text-slate-500 font-medium text-sm">${emptyMsg}</p>
                <a href="import" class="mt-4 inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800">
                    <span>Import New Batch</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>`;
        return;
    }

    grid.innerHTML = filtered.map(t => {
        const pendingCount = t.pending || 0;
        const evaluated = (t.passed || 0) + (t.failed || 0);
        const rate = evaluated > 0 ? (((t.passed || 0) / evaluated) * 100).toFixed(1) : (pendingCount > 0 ? 'Pending' : '0');
        const barWidth = evaluated > 0 ? Math.round(((t.passed || 0) / evaluated) * 100) : 0;

        let badgeHtml = '';
        if (t.type === 'CBT' || t.name.endsWith('_CBT')) {
            badgeHtml = '<span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase bg-purple-100 text-purple-700 border border-purple-200 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>CBT</span>';
        } else if (t.type === 'PBT' || t.name.endsWith('_PBT')) {
            badgeHtml = '<span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase bg-blue-100 text-blue-700 border border-blue-200 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>PBT</span>';
        } else {
            badgeHtml = '<span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">Legacy PBT</span>';
        }

        return `
            <div onclick="openTableExaminees('${escapeHtml(t.name)}')" 
                 class="bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-lg hover:border-blue-400 hover:-translate-y-0.5 transition-all duration-200 group flex flex-col justify-between cursor-pointer">
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-bold text-slate-900 font-mono group-hover:text-blue-600 transition-colors">${escapeHtml(t.name)}</h3>
                            ${badgeHtml}
                        </div>
                        <button type="button" onclick="event.stopPropagation(); deleteTable('${escapeHtml(t.name)}');" class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-red-500 p-1 rounded-lg hover:bg-red-50 cursor-pointer" title="Delete table">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>

                    <div class="flex items-baseline gap-2 mb-4">
                        <span class="text-2xl font-black text-slate-900">${(t.total || 0).toLocaleString()}</span>
                        <span class="text-xs text-slate-400 font-medium">examinees</span>
                    </div>

                    <!-- Pass/Fail bar -->
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden mb-2">
                        <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width:${barWidth}%"></div>
                    </div>
                    <div class="flex justify-between items-center text-xs font-semibold gap-1 flex-wrap">
                        <span class="text-emerald-600">${(t.passed || 0).toLocaleString()} Pass</span>
                        ${pendingCount > 0 ? `<span class="text-amber-600 font-medium">${pendingCount.toLocaleString()} Pending</span>` : ''}
                        <span class="text-red-500 font-medium">${(t.failed || 0).toLocaleString()} Fail</span>
                    </div>
                </div>

                <div class="px-5 py-3 bg-slate-50 group-hover:bg-blue-50/40 border-t border-slate-100 text-xs text-slate-500 flex items-center justify-between transition-colors">
                    <span class="inline-flex items-center gap-1 font-semibold text-blue-600 group-hover:text-blue-700">
                        <span>View Examinees</span>
                        <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </span>
                    <span class="text-[11px] text-slate-400">Pass Rate: <strong class="text-slate-800 font-bold">${rate}${rate !== 'Pending' ? '%' : ''}</strong></span>
                </div>
            </div>`;
    }).join('');
}

// ══════════════════════════════════════════════════════════════
// EXAMINEE DETAIL VIEW LOGIC
// ══════════════════════════════════════════════════════════════

function openTableExaminees(tableName) {
    activeDetailTable = tableName;
    currentPage = 1;
    currentExamineeSearch = '';
    currentExamineeStatus = 'all';
    currentExamineeLevel = 'all';

    // Update URL parameter without page reload
    if (window.history.pushState) {
        const newUrl = window.location.pathname + '?table=' + encodeURIComponent(tableName);
        window.history.pushState({ table: tableName }, '', newUrl);
    }

    // Switch views
    document.getElementById('section-tables-list').classList.add('hidden');
    document.getElementById('section-table-detail').classList.remove('hidden');

    // Set header info
    document.getElementById('detail-table-title').textContent = tableName;
    const isCbt = tableName.endsWith('_CBT');
    const badge = document.getElementById('detail-table-badge');
    badge.textContent = isCbt ? 'CBT' : 'PBT';
    badge.className = isCbt 
        ? 'px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-purple-100 text-purple-700 border border-purple-200'
        : 'px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-blue-100 text-blue-700 border border-blue-200';

    // Set "Add Candidates" link
    document.getElementById('btn-import-more').href = 'import?table=' + encodeURIComponent(tableName);

    // Reset search input, status filter tabs & level filter dropdown
    document.getElementById('examinee-search-input').value = '';
    document.getElementById('btn-clear-examinee-search').classList.add('hidden');
    const lvlFilter = document.getElementById('examinee-level-filter');
    if (lvlFilter) lvlFilter.value = 'all';
    updateExamineeStatusTabs();

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Load records
    loadTableExaminees(1);
}

function closeTableExaminees() {
    activeDetailTable = '';

    // Clean up URL parameter
    if (window.history.pushState) {
        window.history.pushState({}, '', window.location.pathname);
    }

    document.getElementById('section-table-detail').classList.add('hidden');
    document.getElementById('section-tables-list').classList.remove('hidden');

    // Refresh overview cards
    loadTables();
}

async function loadTableExaminees(page = 1) {
    if (!activeDetailTable) return;
    currentPage = page;

    const tbody = document.getElementById('examinees-tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="9" class="py-12 text-center text-slate-400">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-slate-200 border-t-blue-600 mb-2"></div>
                <p>Loading candidate records…</p>
            </td>
        </tr>`;

    try {
        const queryParams = new URLSearchParams({
            action: 'get_table_examinees',
            table_name: activeDetailTable,
            search: currentExamineeSearch,
            status: currentExamineeStatus,
            level: currentExamineeLevel,
            page: currentPage,
            limit: currentLimit
        });

        const resp = await fetch(ADMIN_API_URL + '?' + queryParams.toString());
        const data = await resp.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="9" class="py-10 text-center text-rose-500 font-semibold">${escapeHtml(data.error || 'Failed to load records')}</td></tr>`;
            return;
        }

        currentExamineesList = data.examinees || [];
        totalExamineePages = data.totalPages || 1;

        // Update active batch ribbon KPI stats
        document.getElementById('detail-stat-total').textContent = (data.total || 0).toLocaleString();
        document.getElementById('detail-stat-pass').textContent = (data.passed || 0).toLocaleString();
        document.getElementById('detail-stat-fail').textContent = (data.failed || 0).toLocaleString();
        document.getElementById('detail-stat-pending').textContent = (data.pending || 0).toLocaleString();

        const evaluated = (data.passed || 0) + (data.failed || 0);
        const passRate = evaluated > 0 ? (((data.passed || 0) / evaluated) * 100).toFixed(1) + '%' : (data.pending > 0 ? 'Pending' : '—');
        document.getElementById('detail-stat-rate').textContent = passRate;

        // Update status filter pill counts
        document.getElementById('badge-status-count-all').textContent = data.total || 0;
        document.getElementById('badge-status-count-pass').textContent = data.passed || 0;
        document.getElementById('badge-status-count-fail').textContent = data.failed || 0;
        document.getElementById('badge-status-count-pending').textContent = data.pending || 0;

        // Update CSV export dropdown counts
        const elExportAll = document.getElementById('export-count-all');
        const elExportPass = document.getElementById('export-count-pass');
        const elExportFail = document.getElementById('export-count-fail');
        if (elExportAll) elExportAll.textContent = (data.total || 0).toLocaleString();
        if (elExportPass) elExportPass.textContent = (data.passed || 0).toLocaleString();
        if (elExportFail) elExportFail.textContent = (data.failed || 0).toLocaleString();

        // Update pagination numbers
        const filteredCount = data.filteredCount || 0;
        const startRecord = filteredCount > 0 ? (currentPage - 1) * currentLimit + 1 : 0;
        const endRecord = Math.min(currentPage * currentLimit, filteredCount);

        document.getElementById('pag-start').textContent = startRecord;
        document.getElementById('pag-end').textContent = endRecord;
        document.getElementById('pag-total').textContent = filteredCount.toLocaleString();
        document.getElementById('pag-indicator').textContent = `Page ${currentPage} of ${Math.max(1, totalExamineePages)}`;

        document.getElementById('btn-pag-prev').disabled = (currentPage <= 1);
        document.getElementById('btn-pag-next').disabled = (currentPage >= totalExamineePages);

        renderExamineesTable();
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="9" class="py-10 text-center text-rose-500 font-semibold">Network error while fetching candidate records.</td></tr>`;
    }
}

function renderExamineesTable() {
    const tbody = document.getElementById('examinees-tbody');
    if (!tbody) return;

    if (!currentExamineesList || currentExamineesList.length === 0) {
        let msg = 'No examinees found in this batch.';
        if (currentExamineeSearch) {
            msg = `No examinees matching "${escapeHtml(currentExamineeSearch)}".`;
        } else if (currentExamineeStatus !== 'all' && currentExamineeLevel !== 'all') {
            msg = `No examinees found with status "${escapeHtml(currentExamineeStatus)}" in level N${escapeHtml(currentExamineeLevel)}.`;
        } else if (currentExamineeStatus !== 'all') {
            msg = `No examinees with status "${escapeHtml(currentExamineeStatus)}".`;
        } else if (currentExamineeLevel !== 'all') {
            msg = `No examinees found in level N${escapeHtml(currentExamineeLevel)}.`;
        }
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="py-12 text-center text-slate-400">
                    <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <p class="font-medium">${escapeHtml(msg)}</p>
                </td>
            </tr>`;
        return;
    }

    const startIndex = (currentPage - 1) * currentLimit;

    tbody.innerHTML = currentExamineesList.map((row, idx) => {
        const rawStatus = (row.status || 'Pending').trim();
        let statusBadge = '';

        if (/^pass/i.test(rawStatus)) {
            statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Pass</span>';
        } else if (/^fail/i.test(rawStatus)) {
            statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Fail</span>';
        } else {
            statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Pending</span>';
        }

        // Determine Level badge
        const cleanNum = String(row.examinee_number || '').replace(/[^0-9]/g, '');
        const lvlDigit = cleanNum.length >= 10 ? cleanNum.charAt(9) : '';
        let levelBadge = '<span class="text-slate-300 font-mono text-[11px]">—</span>';
        if (['1', '2', '3', '4', '5'].includes(lvlDigit)) {
            const levelColors = {
                '5': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                '4': 'bg-blue-50 text-blue-700 border-blue-200',
                '3': 'bg-purple-50 text-purple-700 border-purple-200',
                '2': 'bg-amber-50 text-amber-700 border-amber-200',
                '1': 'bg-rose-50 text-rose-700 border-rose-200'
            };
            const col = levelColors[lvlDigit] || 'bg-slate-100 text-slate-700 border-slate-200';
            levelBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-extrabold border ${col}">N${lvlDigit}</span>`;
        }

        const formattedDob = row.dob ? row.dob : '—';
        const rowJson = escapeHtml(JSON.stringify(row));

        return `
            <tr id="examinee-row-${row.id}" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-4 text-center text-slate-400 font-mono text-[11px]">${startIndex + idx + 1}</td>
                <td class="py-3 px-4">
                    <span class="font-mono font-bold text-slate-900 text-xs">${escapeHtml(row.examinee_number)}</span>
                </td>
                <td class="py-3 px-3 text-center">
                    ${levelBadge}
                </td>
                <td class="py-3 px-4">
                    <span class="font-bold text-slate-800 text-xs">${escapeHtml(row.name)}</span>
                </td>
                <td class="py-3 px-4 text-slate-600">
                    ${row.email ? `<span class="inline-flex items-center gap-1 text-[11px]"><svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>${escapeHtml(row.email)}</span>` : '<span class="text-slate-300">—</span>'}
                </td>
                <td class="py-3 px-4 text-slate-600 text-[11px]">
                    ${row.phone ? escapeHtml(row.phone) : '<span class="text-slate-300">—</span>'}
                </td>
                <td class="py-3 px-4 text-slate-600 text-[11px] font-mono">
                    ${formattedDob}
                </td>
                <td class="py-3 px-4 text-center">
                    ${statusBadge}
                </td>
                <td class="py-3 px-4 text-right">
                    <div class="inline-flex items-center gap-1">
                        <button type="button" onclick="openEditExamineeModal(${rowJson})" class="p-1.5 rounded-lg text-slate-600 hover:text-blue-600 hover:bg-blue-50 transition cursor-pointer" title="Edit examinee">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <button type="button" onclick="deleteExaminee(${row.id}, '${escapeHtml(row.examinee_number)}')" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer" title="Delete record">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// ─── Search & Filter Controls ──────────────────────────────
function handleExamineeSearch() {
    clearTimeout(searchDebounceTimeout);
    const input = document.getElementById('examinee-search-input');
    const val = (input.value || '').trim();
    const clearBtn = document.getElementById('btn-clear-examinee-search');

    if (val) {
        clearBtn.classList.remove('hidden');
    } else {
        clearBtn.classList.add('hidden');
    }

    searchDebounceTimeout = setTimeout(() => {
        currentExamineeSearch = val;
        loadTableExaminees(1);
    }, 300);
}

function clearExamineeSearch() {
    const input = document.getElementById('examinee-search-input');
    input.value = '';
    document.getElementById('btn-clear-examinee-search').classList.add('hidden');
    currentExamineeSearch = '';
    loadTableExaminees(1);
    input.focus();
}

function setExamineeStatusFilter(status) {
    currentExamineeStatus = status;
    updateExamineeStatusTabs();
    loadTableExaminees(1);
}

function changeExamineeLevelFilter() {
    const el = document.getElementById('examinee-level-filter');
    currentExamineeLevel = el ? el.value : 'all';
    loadTableExaminees(1);
}

function updateExamineeStatusTabs() {
    const tabs = [
        { id: 'tab-status-all', val: 'all' },
        { id: 'tab-status-pass', val: 'Pass' },
        { id: 'tab-status-fail', val: 'Fail' },
        { id: 'tab-status-pending', val: 'Pending' }
    ];

    tabs.forEach(t => {
        const btn = document.getElementById(t.id);
        if (!btn) return;
        if (t.val === currentExamineeStatus) {
            btn.className = "px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 bg-white text-slate-900 shadow-sm cursor-pointer";
        } else {
            btn.className = "px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 text-slate-600 hover:text-slate-900 cursor-pointer";
        }
    });
}

function changeExamineeLimit() {
    currentLimit = parseInt(document.getElementById('examinee-limit-select')?.value, 10) || 100;
    loadTableExaminees(1);
}

function prevExamineePage() {
    if (currentPage > 1) {
        loadTableExaminees(currentPage - 1);
    }
}

function nextExamineePage() {
    if (currentPage < totalExamineePages) {
        loadTableExaminees(currentPage + 1);
    }
}

// ══════════════════════════════════════════════════════════════
// EDIT EXAMINEE MODAL LOGIC
// ══════════════════════════════════════════════════════════════

function openEditExamineeModal(examinee) {
    if (!examinee) return;

    document.getElementById('edit-id').value = examinee.id;
    document.getElementById('edit-table-name').value = activeDetailTable;
    document.getElementById('edit-name').value = examinee.name || '';
    document.getElementById('edit-examinee-number').value = examinee.examinee_number || '';
    document.getElementById('edit-email').value = examinee.email || '';
    document.getElementById('edit-phone').value = examinee.phone || '';
    document.getElementById('edit-dob').value = examinee.dob || '';

    // Status mapping: Pass, Fail, or Pending
    const statusSelect = document.getElementById('edit-status');
    const rawStatus = (examinee.status || 'Pending').toLowerCase();
    if (rawStatus.startsWith('pass') || rawStatus === 'p') {
        statusSelect.value = 'Pass';
    } else if (rawStatus.startsWith('fail') || rawStatus === 'f') {
        statusSelect.value = 'Fail';
    } else {
        statusSelect.value = 'Pending';
    }

    document.getElementById('edit-modal-sub').textContent = `Batch table: ${activeDetailTable} (ID: #${examinee.id})`;
    document.getElementById('edit-error-banner').classList.add('hidden');

    document.getElementById('modal-edit-examinee').classList.remove('hidden');
    setTimeout(() => document.getElementById('edit-name').focus(), 100);
}

function closeEditExamineeModal() {
    document.getElementById('modal-edit-examinee').classList.add('hidden');
}

async function saveExamineeEdit(e) {
    if (e) e.preventDefault();

    const form = document.getElementById('form-edit-examinee');
    const btn = document.getElementById('btn-save-examinee');
    const btnText = document.getElementById('btn-save-examinee-text');
    const spinner = document.getElementById('spinner-save-examinee');
    const errorBanner = document.getElementById('edit-error-banner');
    const errorText = document.getElementById('edit-error-text');

    errorBanner.classList.add('hidden');
    btn.disabled = true;
    btnText.textContent = 'Saving…';
    spinner.classList.remove('hidden');

    try {
        const formData = new FormData(form);
        formData.append('action', 'update_examinee');

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        btn.disabled = false;
        btnText.textContent = 'Save Changes';
        spinner.classList.add('hidden');

        if (data.success) {
            closeEditExamineeModal();
            showAdminToast(data.message || 'Examinee updated successfully.', 'success');
            // Refresh table records smoothly
            loadTableExaminees(currentPage);
        } else {
            errorText.textContent = data.error || 'Failed to update examinee.';
            errorBanner.classList.remove('hidden');
        }
    } catch (err) {
        console.error(err);
        btn.disabled = false;
        btnText.textContent = 'Save Changes';
        spinner.classList.add('hidden');
        errorText.textContent = 'Network error while saving changes.';
        errorBanner.classList.remove('hidden');
    }
}

// ─── Delete Examinee ───────────────────────────────────────
async function deleteExaminee(id, examineeNumber) {
    if (!confirm(`Are you sure you want to delete examinee #${examineeNumber} from ${activeDetailTable}?\n\nThis cannot be undone.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete_examinee');
        formData.append('table_name', activeDetailTable);
        formData.append('id', id);

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (data.success) {
            showAdminToast(data.message || 'Examinee deleted.', 'success');
            loadTableExaminees(currentPage);
        } else {
            showAdminToast(data.error || 'Failed to delete examinee.', 'error');
        }
    } catch (e) {
        console.error(e);
        showAdminToast('Network error while deleting examinee.', 'error');
    }
}

// ─── Delete Entire Table ───────────────────────────────────
async function deleteTable(tableName) {
    if (!confirm(`Are you sure you want to delete table "${tableName}" and all of its examinee records?\n\nThis action cannot be undone.`)) {
        return;
    }

    try {
        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_table&table_name=${encodeURIComponent(tableName)}`
        });
        const data = await resp.json();
        if (data.success) {
            showAdminToast(`Table "${tableName}" deleted successfully.`, 'success');
            loadTables();
        } else {
            showAdminToast(data.error || 'Failed to delete table.', 'error');
        }
    } catch (e) {
        showAdminToast('Network error while deleting table.', 'error');
    }
}

// ─── Export CSV Dropdown & Download ────────────────────────
function toggleExportDropdown(event, forceState) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('export-dropdown-menu');
    if (!menu) return;

    if (forceState !== undefined) {
        if (forceState) menu.classList.remove('hidden');
        else menu.classList.add('hidden');
    } else {
        menu.classList.toggle('hidden');
    }
}

function downloadExamineesCsv(filter) {
    if (!activeDetailTable) return;
    toggleExportDropdown(null, false);

    const filterLabels = {
        'pass': 'Passed',
        'fail': 'Failed',
        'all': 'All'
    };
    const label = filterLabels[filter] || 'All';
    showAdminToast(`Preparing ${label} examinees CSV export…`, 'info');

    let downloadUrl = ADMIN_API_URL + '?action=export_table_csv&table_name=' + encodeURIComponent(activeDetailTable) + '&filter=' + encodeURIComponent(filter);
    if (currentExamineeLevel && currentExamineeLevel !== 'all') {
        downloadUrl += '&level=' + encodeURIComponent(currentExamineeLevel);
    }
    window.location.href = downloadUrl;
}

// Close export dropdown when clicking outside
document.addEventListener('click', (e) => {
    const container = document.getElementById('export-dropdown-container');
    if (container && !container.contains(e.target)) {
        toggleExportDropdown(null, false);
    }
});

// ─── Keyboard & Back Button Support ────────────────────────
window.addEventListener('popstate', (e) => {
    if (e.state && e.state.table) {
        openTableExaminees(e.state.table);
    } else if (activeDetailTable) {
        closeTableExaminees();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modal-edit-examinee');
        if (modal && !modal.classList.contains('hidden')) {
            closeEditExamineeModal();
        }
    }
});

// ─── Initialize ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadTables();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
