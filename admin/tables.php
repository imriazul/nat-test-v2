<?php
/**
 * Admin Panel - Exam Month Tables
 * Manage, filter, search, and delete PBT and CBT exam batch tables.
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

        <!-- Page Header & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Exam Month Tables</h1>
                    <span id="badge-total-tables" class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700">0</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Uploaded result batches for monthly PBT exams and weekly CBT test sessions.</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="loadTables()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 rounded-xl shadow-xs transition cursor-pointer">
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

        <!-- Generous Bottom Spacing so last row cards never get clipped -->
        <div class="h-12 sm:h-16" aria-hidden="true"></div>

    </main>
</div>

<script>
let currentTablesData = [];
let currentExamTableFilter = 'all'; // 'all', 'pbt', 'cbt'
let searchFilterQuery = '';

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

// ─── Real-Time Search ──────────────────────────────────────
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
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow group flex flex-col justify-between">
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-bold text-slate-900 font-mono">${escapeHtml(t.name)}</h3>
                            ${badgeHtml}
                        </div>
                        <button type="button" onclick="deleteTable('${escapeHtml(t.name)}')" class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-red-500 p-1 rounded hover:bg-red-50 cursor-pointer" title="Delete table">
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

                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500 flex items-center justify-between">
                    <span>Pass Rate:</span>
                    <strong class="text-slate-800 font-bold">${rate}${rate !== 'Pending' ? '%' : ''}</strong>
                </div>
            </div>`;
    }).join('');
}

// ─── Delete Table ──────────────────────────────────────────
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

// ─── Initialize ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadTables();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
