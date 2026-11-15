<?php
require_once __DIR__ . '/auth.php';

$pageTitle = 'Import Examinees | NAT-TEST BD Admin';
$activePage = 'import';

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
                    <span class="text-slate-800">Import</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Import Examinee Records</h1>
            </div>

            <div class="flex items-center gap-2">
                <a href="tables"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl shadow-xs transition">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7zm0 4h16M10 4v16"></path>
                    </svg>
                    <span>View Exam Tables</span>
                </a>
            </div>
        </div>

        <!-- ── Upload Section ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white">
                <h2 class="text-lg font-bold text-white">Upload Examinee Spreadsheet</h2>
                <p class="text-slate-400 text-xs mt-1">Upload a CSV or Excel file. The system auto-detects the exam month and test type (CBT / PBT) from 14-digit examinee numbers.</p>
            </div>

            <div class="p-6 grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- Left: File Upload Dropzone -->
                <div class="lg:col-span-3 space-y-4">
                    <div id="dropzone" class="border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center transition-colors duration-200 cursor-pointer hover:bg-slate-50 relative">
                        <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <span id="file-name-display" class="font-bold text-blue-600 block text-base">Click or drag CSV / Excel file here</span>
                        <p class="text-xs text-slate-400 mt-2">Accepts .csv, .xlsx, or .xls — Max 10 MB</p>
                        <input id="file-upload" type="file" accept=".csv,.xlsx,.xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    </div>
                </div>

                <!-- Right: Examinee Number Decoder Guide -->
                <div class="lg:col-span-2 bg-slate-50 rounded-2xl p-5 border border-slate-200">
                    <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-3">14-Digit Examinee Number Formula</h3>
                    <div class="font-mono text-center mb-4">
                        <div class="flex justify-center gap-1 text-base sm:text-lg font-bold">
                            <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded">26</span>
                            <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded">08</span>
                            <span class="bg-slate-200 text-slate-600 px-2 py-1 rounded">00</span>
                            <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded">474</span>
                            <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded">5</span>
                            <span class="bg-rose-100 text-rose-700 px-2 py-1 rounded">0001</span>
                        </div>
                        <div class="flex justify-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 mt-2">
                            <span class="w-[34px]">Year</span>
                            <span class="w-[34px]">Mon</span>
                            <span class="w-[34px]">Type</span>
                            <span class="w-[42px]">Site</span>
                            <span class="w-[26px]">Lvl</span>
                            <span class="w-[50px]">Roll</span>
                        </div>
                    </div>
                    <div class="text-xs text-slate-600 space-y-1.5 pt-2 border-t border-slate-200">
                        <p><strong>Type:</strong> <span class="text-blue-600 font-bold">00 = PBT</span> (Paper-based), <span class="text-purple-600 font-bold">01–31 = CBT</span> (Computer-based)</p>
                        <p><strong>Level:</strong> 5 = 5Q (N5), 4 = 4Q (N4), 3 = 3Q (N3)…</p>
                        <p><strong>Table:</strong> Auto-routes to <code class="bg-slate-200 px-1 rounded font-bold">Aug_26_PBT</code> or <code class="bg-slate-200 px-1 rounded font-bold">Aug_26_CBT</code></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Column Mapping Modal (hidden by default) ── -->
        <div id="mapping-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-3xl w-full my-8 overflow-hidden animate-fade-up">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white p-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500/20 border border-blue-400/30 rounded-xl flex items-center justify-center text-blue-400 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">Select & Match File Columns</h2>
                            <p class="text-xs text-slate-300" id="mapping-file-title">File loaded — please match the required columns below</p>
                        </div>
                    </div>
                    <button type="button" onclick="cancelMapping()" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer" title="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 max-h-[75vh] overflow-y-auto space-y-6">
                    <!-- Instruction notice -->
                    <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-sm text-blue-900 flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div>
                            <p class="font-bold text-slate-800">Columns can be in any order.</p>
                            <p class="text-xs text-slate-600 mt-0.5">Please map each required database field to the corresponding column from your file. Matching columns have been automatically pre-selected where detected.</p>
                        </div>
                    </div>

                    <!-- Error notification box -->
                    <div id="mapping-error-box" class="hidden p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700 flex items-start gap-3">
                        <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div id="mapping-error-text" class="font-medium text-xs"></div>
                    </div>

                    <!-- Column Mapping Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Examinee Number -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Examinee Number <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-blue-700 font-semibold bg-blue-100 px-2 py-0.5 rounded">14-digit No</span>
                            </label>
                            <select id="map-examinee-number" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-mono truncate" id="sample-examinee-number">Sample: —</p>
                        </div>

                        <!-- Examinee Name -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Examinee Name <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">Full Name</span>
                            </label>
                            <select id="map-name" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 truncate" id="sample-name">Sample: —</p>
                        </div>

                        <!-- Date of Birth -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Date of Birth (DOB) <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">YYYY-MM-DD</span>
                            </label>
                            <select id="map-dob" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-mono truncate" id="sample-dob">Sample: —</p>
                        </div>

                        <!-- Phone Number -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Phone Number <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">Contact / Mobile</span>
                            </label>
                            <select id="map-phone" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-mono truncate" id="sample-phone">Sample: —</p>
                        </div>

                        <!-- Email Address -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Email Address <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-slate-600 font-semibold bg-slate-200 px-2 py-0.5 rounded">Email</span>
                            </label>
                            <select id="map-email" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 truncate" id="sample-email">Sample: —</p>
                        </div>

                        <!-- Result Status -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <label class="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                <span>Result Status <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-2 py-0.5 rounded">Pass / Fail</span>
                            </label>
                            <select id="map-status" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition mapping-select">
                                <option value="">-- Select Column --</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1.5 font-semibold truncate" id="sample-status">Sample: —</p>
                        </div>
                    </div>

                    <!-- File Data Preview Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-inner">
                        <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">File Preview (First 3 Rows)</span>
                            <span class="text-[11px] text-slate-500" id="preview-col-count">0 columns</span>
                        </div>
                        <div class="overflow-x-auto max-h-48">
                            <table class="w-full text-xs text-left text-slate-600">
                                <thead class="bg-slate-50 text-slate-700 uppercase font-semibold border-b border-slate-200 sticky top-0" id="mapping-preview-thead">
                                </thead>
                                <tbody id="mapping-preview-tbody" class="divide-y divide-slate-100 bg-white font-mono text-[11px]">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button type="button" onclick="cancelMapping()" class="px-5 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" onclick="applyColumnMapping()" class="px-6 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-md shadow-blue-500/20 flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Confirm Mapping & Preview
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Preview Section (hidden until file parsed) ── -->
        <div id="preview-section" class="hidden animate-fade-up">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100 flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-bold text-slate-800">File Analysis Complete</h2>
                            <span id="preview-type-badge" class="hidden"></span>
                        </div>
                        <p class="text-xs text-slate-500" id="preview-file-info">—</p>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Preview Stats -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
                            <p class="text-2xl font-black text-slate-900" id="preview-total">0</p>
                            <p class="text-xs font-bold text-slate-400 uppercase">Total Rows</p>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-100">
                            <p class="text-2xl font-black text-emerald-600" id="preview-pass">0</p>
                            <p class="text-xs font-bold text-emerald-400 uppercase">Passed</p>
                        </div>
                        <div class="bg-red-50 rounded-xl p-4 text-center border border-red-100">
                            <p class="text-2xl font-black text-red-600" id="preview-fail">0</p>
                            <p class="text-xs font-bold text-red-400 uppercase">Failed</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-100">
                            <p class="text-2xl font-black text-amber-600" id="preview-pending">0</p>
                            <p class="text-xs font-bold text-amber-500 uppercase">Pending</p>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                            <p class="text-2xl font-black text-blue-600" id="preview-rate">0%</p>
                            <p class="text-xs font-bold text-blue-400 uppercase">Pass Rate</p>
                        </div>
                    </div>

                    <!-- Table info -->
                    <div id="preview-table-info" class="mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800 hidden"></div>

                    <!-- Preview Mapped Records Sample -->
                    <div class="mb-5 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-200 flex justify-between items-center text-xs text-slate-700 font-bold uppercase tracking-wider">
                            <span>Mapped Data Preview (First 3 Records)</span>
                            <span class="text-[11px] text-emerald-600 font-semibold font-mono">Ready to import</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-200 text-[11px]">
                                    <tr>
                                        <th class="px-3 py-2 border-r border-slate-200">Examinee No</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Name</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Date of Birth</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Email</th>
                                        <th class="px-3 py-2 border-r border-slate-200">Phone</th>
                                        <th class="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-sample-tbody" class="divide-y divide-slate-100 bg-white font-mono text-[11px]">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex flex-wrap gap-3">
                        <button id="btn-confirm" onclick="confirmImport()" class="flex-1 min-w-[200px] bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition shadow-md shadow-blue-500/20 flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Confirm Import to Database</span>
                        </button>
                        <button onclick="reopenMappingModal()" class="px-5 py-3 bg-slate-100 text-slate-700 font-medium rounded-xl hover:bg-slate-200 transition flex items-center gap-2 text-sm cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            <span>Change Column Mapping</span>
                        </button>
                        <button onclick="cancelImport()" class="px-6 py-3 bg-slate-100 text-slate-600 font-medium rounded-xl hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Real-time Progress Bar & Stats (hidden) ── -->
        <div id="progress-container" class="hidden animate-fade-up">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-md">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div id="progress-spinner" class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                        <span class="font-bold text-slate-800 text-base" id="progress-text">Preparing import…</span>
                    </div>
                    <span class="font-extrabold text-blue-600 text-lg" id="progress-percent">0%</span>
                </div>

                <!-- Animated Progress Bar -->
                <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden mb-4 border border-slate-200/60 p-0.5">
                    <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-200 ease-out" style="width: 0%"></div>
                </div>

                <!-- Live Metrics Footer -->
                <div class="flex flex-wrap items-center justify-between text-xs text-slate-500 gap-2 pt-2 border-t border-slate-100">
                    <div class="flex items-center gap-4">
                        <span>Imported: <strong id="progress-count" class="text-slate-800 font-bold">0</strong> of <strong id="progress-total" class="text-slate-800 font-bold">0</strong></span>
                        <span class="text-slate-300">|</span>
                        <span class="text-emerald-600 font-medium">✓ <strong id="progress-ok-count">0</strong> OK</span>
                        <span class="text-red-500 font-medium">✗ <strong id="progress-err-count">0</strong> Errors</span>
                    </div>
                    <div id="progress-eta" class="text-slate-400 font-medium">Starting…</div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
let currentUploadedFile = null;
let analysisResult      = null;

const dropzone        = document.getElementById('dropzone');
const fileInput       = document.getElementById('file-upload');
const fileNameDisplay = document.getElementById('file-name-display');

// Drag & Drop event bindings
['dragenter','dragover','dragleave','drop'].forEach(e =>
    dropzone.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); }, false));
['dragenter','dragover'].forEach(e =>
    dropzone.addEventListener(e, () => dropzone.classList.add('bg-blue-50/50', 'border-blue-400'), false));
['dragleave','drop'].forEach(e =>
    dropzone.addEventListener(e, () => dropzone.classList.remove('bg-blue-50/50', 'border-blue-400'), false));

dropzone.addEventListener('drop', e => {
    fileInput.files = e.dataTransfer.files;
    handleFileSelect();
}, false);

fileInput.addEventListener('change', handleFileSelect);

function handleFileSelect() {
    const file = fileInput.files[0];
    if (!file) return;

    const ext = file.name.split('.').pop().toLowerCase();
    if (!['csv', 'xlsx', 'xls'].includes(ext)) {
        alert('Unsupported file type. Please upload a .csv, .xlsx, or .xls file.');
        fileInput.value = '';
        return;
    }

    fileNameDisplay.innerHTML = `<span class="text-emerald-600 font-bold">${escapeHtml(file.name)}</span>`;

    const reader = new FileReader();
    reader.onload = e => {
        try {
            let rows = [];
            if (ext === 'csv') {
                try {
                    const wb = XLSX.read(e.target.result, { type: 'array', cellDates: true, raw: false });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', blankrows: false });
                } catch (sheetErr) {
                    const decoder = new TextDecoder('utf-8');
                    const text = decoder.decode(e.target.result);
                    rows = parseCSVToRows(text);
                }
            } else {
                const wb = XLSX.read(e.target.result, { type: 'array', cellDates: true, raw: false });
                const ws = wb.Sheets[wb.SheetNames[0]];
                rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', blankrows: false });
            }

            rows = (rows || []).filter(r => Array.isArray(r) && r.some(c => c !== null && c !== undefined && String(c).trim() !== ''));

            if (!rows || rows.length < 2) {
                alert('The uploaded file is empty or does not contain data rows.');
                cancelMapping();
                return;
            }

            const headers = rows[0].map((h, i) => {
                const str = String(h !== null && h !== undefined ? h : '').trim();
                return str || `Column ${i + 1}`;
            });

            const dataRows = rows.slice(1);

            currentUploadedFile = {
                fileName: file.name,
                headers: headers,
                dataRows: dataRows,
                savedMapping: {}
            };

            openMappingModal(currentUploadedFile);

        } catch (err) {
            console.error(err);
            alert('Error reading file: ' + (err.message || 'Unknown error'));
            cancelMapping();
        }
    };

    reader.readAsArrayBuffer(file);
}

function parseCSVToRows(text) {
    const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
    return lines.map(line => parseCSVLine(line));
}

function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (inQuotes) {
            if (ch === '"' && line[i+1] === '"') { current += '"'; i++; }
            else if (ch === '"') { inQuotes = false; }
            else { current += ch; }
        } else {
            if (ch === '"') { inQuotes = true; }
            else if (ch === ',') { result.push(current); current = ''; }
            else { current += ch; }
        }
    }
    result.push(current);
    return result;
}

function isExcelDateSerial(val) {
    if (val === null || val === undefined || val === '') return false;
    const num = Math.floor(Number(val));
    return !isNaN(num) && num >= 1000 && num <= 60000 && String(num).length <= 5;
}

function normalizeDate(val) {
    if (val === null || val === undefined || val === '') return '';
    if (val instanceof Date) {
        if (!isNaN(val.getTime())) {
            const yr = val.getFullYear();
            const mo = String(val.getMonth() + 1).padStart(2, '0');
            const dy = String(val.getDate()).padStart(2, '0');
            return `${yr}-${mo}-${dy}`;
        }
    }
    if (isExcelDateSerial(val)) {
        const num = Number(val);
        const d = new Date(Math.round((num - 25569) * 86400 * 1000));
        if (!isNaN(d.getTime())) {
            const yr = d.getUTCFullYear();
            const mo = String(d.getUTCMonth() + 1).padStart(2, '0');
            const dy = String(d.getUTCDate()).padStart(2, '0');
            return `${yr}-${mo}-${dy}`;
        }
    }
    const str = String(val).trim();
    if (!str) return '';

    let m = str.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
    if (m) {
        return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`;
    }

    m = str.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
    if (m) {
        return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
    }

    const dt = new Date(str);
    if (!isNaN(dt.getTime()) && dt.getFullYear() > 1900 && dt.getFullYear() < 2100) {
        const yr = dt.getFullYear();
        const mo = String(dt.getMonth() + 1).padStart(2, '0');
        const dy = String(dt.getDate()).padStart(2, '0');
        return `${yr}-${mo}-${dy}`;
    }

    return str;
}

function normalizeStatus(val) {
    if (val === undefined || val === null) return 'Pending';
    const s = String(val).trim().toLowerCase();
    if (!s || s === 'n/a' || s === 'na' || s === '-' || s === 'pending' || s === 'waiting' || s === 'null' || s === 'none') {
        return 'Pending';
    }
    if (s.startsWith('pass') || s === 'p') return 'Pass';
    if (s.startsWith('fail') || s === 'f') return 'Fail';
    return 'Pending';
}

const MAPPING_FIELDS = [
    {
        key: 'examinee_number',
        name: 'Examinee Number',
        selectId: 'map-examinee-number',
        sampleId: 'sample-examinee-number',
        test: h => (/examinee.*(no|num|id)/i.test(h) || /candidate.*(no|num|id)/i.test(h) || /roll/i.test(h) || /reg(istration)?.*(no|num|id)?/i.test(h) || /^id$/i.test(h) || (/examinee/i.test(h) && !/name/i.test(h)) || /exam.*no/i.test(h))
    },
    {
        key: 'name',
        name: 'Examinee Name',
        selectId: 'map-name',
        sampleId: 'sample-name',
        test: h => ((/name/i.test(h) && !/father|mother|guard|sur/i.test(h)) || (/student|candidate|examinee/i.test(h) && !/no|num|id|roll|reg|date|phone|mail/i.test(h)))
    },
    {
        key: 'dob',
        name: 'Date of Birth (DOB)',
        selectId: 'map-dob',
        sampleId: 'sample-dob',
        test: h => (/dob|birth|bday/i.test(h) || /date.*of.*birth/i.test(h))
    },
    {
        key: 'phone',
        name: 'Phone Number',
        selectId: 'map-phone',
        sampleId: 'sample-phone',
        test: h => (/phone|mobile|contact|cell|telephone|^tel|whats?app|ph[._\s-]?no|mob[._\s-]?no|電話|連絡先|携帯|ফোন|মোবাইল/i.test(h))
    },
    {
        key: 'email',
        name: 'Email Address',
        selectId: 'map-email',
        sampleId: 'sample-email',
        test: h => (/email|mail|e-mail/i.test(h))
    },
    {
        key: 'status',
        name: 'Result Status',
        selectId: 'map-status',
        sampleId: 'sample-status',
        test: h => (/status|result|verdict|grade|pass.*fail|remarks?/i.test(h))
    }
];

function getSampleValueForCol(dataRows, colIdx) {
    if (!dataRows || colIdx === '' || colIdx === null || colIdx === undefined) return null;
    for (let r = 0; r < Math.min(dataRows.length, 10); r++) {
        const val = dataRows[r][colIdx];
        if (val !== undefined && val !== null && String(val).trim() !== '') {
            return String(val).trim();
        }
    }
    return '';
}

function openMappingModal(fileData) {
    document.getElementById('mapping-file-title').textContent =
        `${fileData.fileName}  •  ${fileData.headers.length} columns detected  •  ${fileData.dataRows.length.toLocaleString()} rows`;

    document.getElementById('preview-col-count').textContent = `${fileData.headers.length} columns`;

    const errBox = document.getElementById('mapping-error-box');
    errBox.classList.add('hidden');
    document.getElementById('mapping-error-text').textContent = '';

    const assignedCols = new Set();
    const autoSelections = {};

    if (fileData.savedMapping && Object.keys(fileData.savedMapping).length > 0) {
        Object.assign(autoSelections, fileData.savedMapping);
    } else {
        MAPPING_FIELDS.forEach(field => {
            let matchedIdx = null;
            for (let i = 0; i < fileData.headers.length; i++) {
                if (assignedCols.has(i)) continue;
                const header = fileData.headers[i];
                if (field.test(header)) {
                    matchedIdx = i;
                    assignedCols.add(i);
                    break;
                }
            }
            if (matchedIdx !== null) {
                autoSelections[field.key] = String(matchedIdx);
                assignedCols.add(matchedIdx);
            }
        });
    }

    MAPPING_FIELDS.forEach(field => {
        const selectEl = document.getElementById(field.selectId);
        const sampleEl = document.getElementById(field.sampleId);

        selectEl.innerHTML = '<option value="">-- Select Column --</option>' +
            fileData.headers.map((h, i) => {
                let sample = getSampleValueForCol(fileData.dataRows, i);
                if (field.key === 'dob' || isExcelDateSerial(sample) || /dob|birth|date/i.test(h)) {
                    const d = normalizeDate(sample);
                    if (d) sample = d;
                }
                const sampleStr = sample ? ` (${sample.length > 20 ? sample.substring(0, 18) + '…' : sample})` : '';
                return `<option value="${i}">${escapeHtml(h)}${escapeHtml(sampleStr)}</option>`;
            }).join('');

        if (autoSelections[field.key] !== undefined) {
            selectEl.value = autoSelections[field.key];
        }

        const updateSample = () => {
            const val = selectEl.value;
            if (val === '') {
                sampleEl.textContent = 'Sample: —';
                sampleEl.className = 'text-[11px] text-slate-400 mt-1.5 truncate';
            } else {
                let sample = getSampleValueForCol(fileData.dataRows, parseInt(val, 10));
                if (field.key === 'dob' || isExcelDateSerial(sample)) {
                    const d = normalizeDate(sample);
                    if (d) sample = d;
                }
                sampleEl.textContent = 'Sample: ' + (sample || '(empty)');
                sampleEl.className = 'text-[11px] text-blue-600 font-semibold mt-1.5 truncate';
            }
        };

        selectEl.onchange = updateSample;
        updateSample();
    });

    const thead = document.getElementById('mapping-preview-thead');
    const tbody = document.getElementById('mapping-preview-tbody');

    thead.innerHTML = '<tr>' +
        fileData.headers.map((h, i) =>
            `<th class="px-3 py-2 border-r border-slate-200 whitespace-nowrap bg-slate-100">#${i + 1}: ${escapeHtml(h)}</th>`
        ).join('') + '</tr>';

    const previewRows = fileData.dataRows.slice(0, 3);
    tbody.innerHTML = previewRows.map((row) => {
        return `<tr class="hover:bg-slate-50 transition-colors">` +
            fileData.headers.map((h, cIdx) => {
                let cellVal = row[cIdx] !== undefined && row[cIdx] !== null ? row[cIdx] : '';
                if (/dob|birth|date/i.test(h) || isExcelDateSerial(cellVal)) {
                    const d = normalizeDate(cellVal);
                    if (d) cellVal = d;
                }
                const cellStr = String(cellVal).trim();
                return `<td class="px-3 py-2 border-r border-slate-100 whitespace-nowrap text-slate-700">${escapeHtml(cellStr) || '<span class="text-slate-300 italic">null</span>'}</td>`;
            }).join('') +
        `</tr>`;
    }).join('');

    document.getElementById('mapping-modal').classList.remove('hidden');
}

function cancelMapping() {
    document.getElementById('mapping-modal').classList.add('hidden');
    if (document.getElementById('preview-section').classList.contains('hidden')) {
        currentUploadedFile = null;
        fileInput.value = '';
        fileNameDisplay.innerHTML = '<span class="text-blue-600">Click or drag CSV / Excel file here</span>';
    }
}

function reopenMappingModal() {
    if (!currentUploadedFile) {
        alert('No uploaded file in memory. Please select a file again.');
        return;
    }
    openMappingModal(currentUploadedFile);
}

function applyColumnMapping() {
    const errBox  = document.getElementById('mapping-error-box');
    const errText = document.getElementById('mapping-error-text');
    errBox.classList.add('hidden');

    const currentMapping = {};
    const missingFields = [];
    const usedCols = {};

    MAPPING_FIELDS.forEach(field => {
        const selectEl = document.getElementById(field.selectId);
        const val = selectEl.value;
        if (val === '') {
            missingFields.push(field.name);
        } else {
            currentMapping[field.key] = parseInt(val, 10);
            if (usedCols[val]) {
                usedCols[val].push(field.name);
            } else {
                usedCols[val] = [field.name];
            }
        }
    });

    if (missingFields.length > 0) {
        errText.textContent = `Please select a matching column for all required fields: ${missingFields.join(', ')}.`;
        errBox.classList.remove('hidden');
        return;
    }

    const duplicates = Object.entries(usedCols).filter(([col, fields]) => fields.length > 1);
    if (duplicates.length > 0) {
        const conflictDesc = duplicates.map(([col, fields]) => `Column ${parseInt(col, 10) + 1} is assigned to multiple fields (${fields.join(' & ')})`).join('; ');
        errText.textContent = `Conflict detected: ${conflictDesc}. Each field must map to a distinct column.`;
        errBox.classList.remove('hidden');
        return;
    }

    currentUploadedFile.savedMapping = currentMapping;

    let total = 0, pass = 0, fail = 0, pending = 0;
    const validRows = [];
    const tableGroups = {};

    const idxNum    = currentMapping['examinee_number'];
    const idxName   = currentMapping['name'];
    const idxDob    = currentMapping['dob'];
    const idxPhone  = currentMapping['phone'];
    const idxEmail  = currentMapping['email'];
    const idxStatus = currentMapping['status'];

    for (const row of currentUploadedFile.dataRows) {
        const rawNum = row[idxNum];
        if (rawNum === undefined || rawNum === null || String(rawNum).trim() === '') continue;

        const cleanNum = String(rawNum).trim().replace(/[^0-9]/g, '');
        if (!cleanNum || cleanNum.length < 14) continue;

        const detectedYear  = cleanNum.substring(0, 2);
        const detectedMonth = cleanNum.substring(2, 4);
        const monthShort    = MONTHS_SHORT[detectedMonth];
        const monthFull     = MONTHS_FULL[detectedMonth];
        if (!monthShort) continue;

        const typeCode = cleanNum.substring(4, 6);
        const testType = (typeCode === '00') ? 'PBT' : 'CBT';
        const tableName = `${monthShort}_${detectedYear}_${testType}`;

        const cleanName = String(row[idxName] || '').trim();
        const normDob   = normalizeDate(row[idxDob]);
        const cleanMail = String(row[idxEmail] || '').trim();
        const cleanTel  = String(row[idxPhone] || '').trim();
        const normStat  = normalizeStatus(row[idxStatus]);

        total++;
        if (normStat === 'Pass') pass++;
        else if (normStat === 'Fail') fail++;
        else pending++;

        const mappedRow = [cleanNum, cleanName, normDob, cleanMail, cleanTel, normStat];
        validRows.push(mappedRow);

        if (!tableGroups[tableName]) {
            tableGroups[tableName] = {
                tableName,
                month: detectedMonth,
                year: detectedYear,
                monthShort,
                monthFull,
                testType,
                rows: [],
                pass: 0,
                fail: 0,
                pending: 0
            };
        }
        tableGroups[tableName].rows.push(mappedRow);
        if (normStat === 'Pass') {
            tableGroups[tableName].pass++;
        } else if (normStat === 'Fail') {
            tableGroups[tableName].fail++;
        } else {
            tableGroups[tableName].pending++;
        }
    }

    const groupKeys = Object.keys(tableGroups);
    if (validRows.length === 0 || groupKeys.length === 0) {
        errText.textContent = 'No valid data rows found with 14-digit examinee numbers. Please check your mapping.';
        errBox.classList.remove('hidden');
        return;
    }

    analysisResult = {
        fileName: currentUploadedFile.fileName,
        total,
        pass,
        fail,
        pending,
        tableGroups,
        groupKeys,
        rows: validRows
    };

    document.getElementById('mapping-modal').classList.add('hidden');
    showPreview(analysisResult);
}

async function showPreview(analysis) {
    document.getElementById('preview-total').textContent = analysis.total.toLocaleString();
    document.getElementById('preview-pass').textContent  = analysis.pass.toLocaleString();
    document.getElementById('preview-fail').textContent  = analysis.fail.toLocaleString();
    const pendEl = document.getElementById('preview-pending');
    if (pendEl) pendEl.textContent = (analysis.pending || 0).toLocaleString();

    const evaluated = analysis.pass + analysis.fail;
    const rate = evaluated > 0 ? ((analysis.pass / evaluated) * 100).toFixed(1) + '%' : (analysis.pending > 0 ? 'Pending' : '0%');
    document.getElementById('preview-rate').textContent  = rate;

    const badge = document.getElementById('preview-type-badge');
    const infoBox = document.getElementById('preview-table-info');

    if (analysis.groupKeys.length === 1) {
        const grp = analysis.tableGroups[analysis.groupKeys[0]];
        document.getElementById('preview-file-info').textContent =
            `${analysis.fileName}  •  ${grp.monthFull} 20${grp.year}  •  Table: ${grp.tableName}`;

        if (badge) {
            badge.classList.remove('hidden');
            if (grp.testType === 'CBT') {
                badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-purple-100 text-purple-700 border border-purple-200 flex items-center gap-1.5';
                badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> CBT (Computer Based)';
            } else {
                badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-100 text-blue-700 border border-blue-200 flex items-center gap-1.5';
                badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> PBT (Paper Based)';
            }
        }

        try {
            const resp = await fetch(`${ADMIN_API_URL}?action=check_table&table=${grp.tableName}`);
            const data = await resp.json();
            if (data.exists) {
                infoBox.innerHTML = `⚠ Table <strong>${grp.tableName}</strong> (${grp.testType}) already exists with <strong>${data.count.toLocaleString()}</strong> records. Duplicates will be updated.`;
                infoBox.className = 'mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800';
            } else {
                infoBox.innerHTML = `✓ Table <strong>${grp.tableName}</strong> (${grp.testType}) will be created automatically.`;
                infoBox.className = 'mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-800';
            }
            infoBox.classList.remove('hidden');
        } catch (e) { infoBox.classList.add('hidden'); }

    } else {
        document.getElementById('preview-file-info').textContent =
            `${analysis.fileName}  •  Smart Auto-Split (${analysis.groupKeys.length} Tables Detected)`;

        if (badge) {
            badge.classList.remove('hidden');
            badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-indigo-100 text-indigo-700 border border-indigo-200 flex items-center gap-1.5';
            badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Multi-Session (${analysis.groupKeys.length} Tables)`;
        }

        try {
            const tableChecks = await Promise.all(analysis.groupKeys.map(async (tblName) => {
                try {
                    const resp = await fetch(`${ADMIN_API_URL}?action=check_table&table=${tblName}`);
                    const data = await resp.json();
                    return { tableName: tblName, exists: data.exists, count: data.count || 0 };
                } catch (e) {
                    return { tableName: tblName, exists: false, count: 0 };
                }
            }));

            const statusMap = {};
            tableChecks.forEach(tc => { statusMap[tc.tableName] = tc; });

            const cardsHtml = analysis.groupKeys.map(tblName => {
                const grp = analysis.tableGroups[tblName];
                const stat = statusMap[tblName] || { exists: false, count: 0 };
                const typeColor = grp.testType === 'CBT'
                    ? 'bg-purple-100 text-purple-700 border-purple-200'
                    : 'bg-blue-100 text-blue-700 border-blue-200';
                const existPill = stat.exists
                    ? `<span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">Exists (${stat.count.toLocaleString()})</span>`
                    : `<span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">New Table</span>`;

                const pendingBadge = grp.pending > 0 ? `, <span class="text-amber-600 font-medium">${grp.pending} Pending</span>` : '';

                return `
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono font-bold text-slate-900">${escapeHtml(grp.tableName)}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border ${typeColor}">${grp.testType}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">${grp.rows.length.toLocaleString()} examinees &bull; <span class="text-emerald-600 font-medium">${grp.pass} Pass</span>, <span class="text-red-500 font-medium">${grp.fail} Fail</span>${pendingBadge}</p>
                        </div>
                        <div>${existPill}</div>
                    </div>`;
            }).join('');

            infoBox.className = 'mb-5 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-800';
            infoBox.innerHTML = `
                <div class="space-y-3">
                    <div class="flex items-center gap-2 font-bold text-slate-800">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Smart Multi-Table Routing (${analysis.groupKeys.length} Tables Detected)</span>
                    </div>
                    <p class="text-xs text-slate-600">This file contains examinees across multiple exam sessions/months. Each examinee will be imported into their matching database table automatically:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                        ${cardsHtml}
                    </div>
                </div>`;
            infoBox.classList.remove('hidden');
        } catch (e) { infoBox.classList.add('hidden'); }
    }

    const sampleTbody = document.getElementById('preview-sample-tbody');
    if (sampleTbody && analysis.rows) {
        sampleTbody.innerHTML = analysis.rows.slice(0, 3).map(r => {
            let statClass = 'text-amber-600 font-semibold';
            if (r[5] === 'Pass') statClass = 'text-emerald-600 font-bold';
            else if (r[5] === 'Fail') statClass = 'text-red-500 font-bold';

            return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-3 py-2 border-r border-slate-100 font-bold text-blue-600">${escapeHtml(r[0])}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-slate-800 font-sans">${escapeHtml(r[1])}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-slate-700 font-semibold">${escapeHtml(r[2])}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-slate-600 font-sans">${escapeHtml(r[3]) || '<span class="text-slate-300 italic">—</span>'}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-slate-700">${escapeHtml(r[4]) || '<span class="text-slate-300 italic">—</span>'}</td>
                <td class="px-3 py-2 ${statClass}">${escapeHtml(r[5])}</td>
            </tr>
        `}).join('');
    }

    document.getElementById('preview-section').classList.remove('hidden');
}

async function confirmImport() {
    if (!analysisResult || !analysisResult.groupKeys || analysisResult.groupKeys.length === 0) {
        alert('No valid records to import.');
        return;
    }

    const btn = document.getElementById('btn-confirm');
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');

    const progContainer = document.getElementById('progress-container');
    const progBar       = document.getElementById('progress-bar');
    const progText      = document.getElementById('progress-text');
    const progPercent   = document.getElementById('progress-percent');
    const progCount     = document.getElementById('progress-count');
    const progTotal     = document.getElementById('progress-total');
    const progOkCount   = document.getElementById('progress-ok-count');
    const progErrCount  = document.getElementById('progress-err-count');
    const progEta       = document.getElementById('progress-eta');
    const progSpinner   = document.getElementById('progress-spinner');

    progContainer.classList.remove('hidden');
    progSpinner.classList.remove('hidden');
    progBar.style.width = '0%';
    progBar.className = 'bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-200 ease-out';
    progText.textContent = 'Initializing tables…';
    progPercent.textContent = '0%';
    progTotal.textContent = analysisResult.total.toLocaleString();
    progCount.textContent = '0';
    progOkCount.textContent = '0';
    progErrCount.textContent = '0';
    progEta.textContent = 'Starting…';

    const BATCH_SIZE = 150;
    const totalRows = analysisResult.total;
    let processedRows = 0;
    let totalSuccess = 0;
    let totalErrors = 0;
    const startTime = Date.now();

    for (const tblName of analysisResult.groupKeys) {
        const group = analysisResult.tableGroups[tblName];

        progText.textContent = `Preparing table ${group.tableName}…`;
        try {
            const initFormData = new FormData();
            initFormData.append('action', 'init_table');
            initFormData.append('table_name', group.tableName);

            const initResp = await fetch(ADMIN_API_URL, {
                method: 'POST',
                body: initFormData
            });

            if (initResp.status === 401) {
                alert('Session expired. Please log in again.');
                window.location.reload();
                return;
            }

            const initData = await initResp.json();
            if (initData.error) {
                alert(`Error creating table ${group.tableName}: ` + initData.error);
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                return;
            }
        } catch (err) {
            alert(`Failed to initialize database table ${group.tableName}.`);
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            return;
        }

        for (let i = 0; i < group.rows.length; i += BATCH_SIZE) {
            const chunk = group.rows.slice(i, i + BATCH_SIZE);
            const batchFormData = new FormData();
            batchFormData.append('action', 'import_batch');
            batchFormData.append('table_name', group.tableName);
            batchFormData.append('rows', JSON.stringify(chunk));

            try {
                const resp = await fetch(ADMIN_API_URL, {
                    method: 'POST',
                    body: batchFormData
                });

                if (resp.status === 401) {
                    alert('Session expired. Please log in again.');
                    window.location.reload();
                    return;
                }

                const data = await resp.json();
                if (data.error) {
                    progText.textContent = `Error importing into ${group.tableName}`;
                    alert(data.error);
                    break;
                }

                totalSuccess += (data.successCount || 0);
                totalErrors  += (data.errorCount || 0);
                processedRows = Math.min(totalRows, processedRows + chunk.length);

                const pct = Math.round((processedRows / totalRows) * 100);
                progBar.style.width = pct + '%';
                progPercent.textContent = pct + '%';
                progCount.textContent = processedRows.toLocaleString();
                progOkCount.textContent = totalSuccess.toLocaleString();
                progErrCount.textContent = totalErrors.toLocaleString();

                const elapsedSec = (Date.now() - startTime) / 1000;
                const rowsPerSec = processedRows / elapsedSec;
                const remainingRows = totalRows - processedRows;
                const etaSec = rowsPerSec > 0 ? Math.ceil(remainingRows / rowsPerSec) : 0;
                progEta.textContent = `${Math.round(rowsPerSec)} records/sec • ETA: ${etaSec}s`;
                progText.textContent = `Importing into ${group.tableName}… (${processedRows}/${totalRows})`;

            } catch (batchErr) {
                console.error('Batch error:', batchErr);
                totalErrors += chunk.length;
                processedRows = Math.min(totalRows, processedRows + chunk.length);
                progErrCount.textContent = totalErrors.toLocaleString();
            }
        }
    }

    progBar.style.width = '100%';
    progPercent.textContent = '100%';
    progBar.className = 'bg-emerald-500 h-2 rounded-full transition-all duration-300';
    progSpinner.classList.add('hidden');

    const tblCount = analysisResult.groupKeys.length;
    progText.innerHTML = `<span class="text-emerald-600 font-bold">Import Completed!</span> <span class="text-slate-500 font-normal">(${totalSuccess.toLocaleString()} records across ${tblCount} table${tblCount > 1 ? 's' : ''})</span>`;
    progEta.textContent = `Completed in ${((Date.now() - startTime) / 1000).toFixed(1)}s`;

    btn.disabled = false;
    btn.classList.remove('opacity-50', 'cursor-not-allowed');
    showAdminToast(`Import completed: ${totalSuccess.toLocaleString()} records saved!`, 'success');

    setTimeout(() => {
        document.getElementById('preview-section').classList.add('hidden');
    }, 1500);
}

function cancelImport() {
    analysisResult = null;
    currentUploadedFile = null;
    fileInput.value = '';
    fileNameDisplay.innerHTML = '<span class="text-blue-600">Click or drag CSV / Excel file here</span>';
    document.getElementById('preview-section').classList.add('hidden');
    document.getElementById('progress-container').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
