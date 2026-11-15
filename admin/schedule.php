<?php
require_once __DIR__ . '/auth.php';

$pageTitle = 'CBT Exam Schedule & Excluded Days | NAT-TEST BD Admin';
$activePage = 'schedule';

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
                    <span class="text-slate-800">CBT Schedule</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">CBT Exam Schedule & Excluded Days</h1>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="loadExcludedDays()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl shadow-xs transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Refresh Data</span>
                </button>
            </div>
        </div>

        <!-- ── CBT Exam Schedule Card ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center font-bold shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-slate-800">Exam Center Exclusions</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-slate-100 text-slate-600 border border-slate-200">
                                excluded_days.json
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Manage non-exam days and center holidays. Excluded dates are immediately hidden from student registration.</p>
                    </div>
                </div>
            </div>

            <!-- Center Tabs -->
            <div class="px-6 pt-4 border-b border-slate-100 bg-slate-50/50 flex flex-wrap gap-2">
                <button type="button" id="tab-center-474" onclick="setExcludedCenterTab('474')" class="px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-rose-600 text-rose-700 bg-white shadow-sm cursor-pointer">
                    <span>ULSAI (Dhaka - 474)</span>
                    <span id="badge-count-474" class="px-2 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700">0</span>
                </button>
                <button type="button" id="tab-center-476" onclick="setExcludedCenterTab('476')" class="px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-600 hover:text-slate-900 cursor-pointer">
                    <span>KHULNA (476)</span>
                    <span id="badge-count-476" class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-600">0</span>
                </button>
                <button type="button" id="tab-center-all" onclick="setExcludedCenterTab('all')" class="px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-600 hover:text-slate-900 cursor-pointer">
                    <span>All Centers / Nationwide (all)</span>
                    <span id="badge-count-all" class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-600">0</span>
                </button>
            </div>

            <!-- Content Area: Calendar & List -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Left: Interactive Visual Calendar Picker -->
                    <div class="lg:col-span-7 space-y-4">
                        <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="changeExcludedMonth(-1)" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-600 transition cursor-pointer" title="Previous Month">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                </button>
                                <span id="calendar-month-year" class="font-bold text-slate-800 text-sm">November 2026</span>
                                <button type="button" onclick="changeExcludedMonth(1)" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-600 transition cursor-pointer" title="Next Month">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>
                            <button type="button" onclick="resetExcludedMonthToToday()" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg border border-rose-200/60 transition cursor-pointer">
                                Today
                            </button>
                        </div>

                        <!-- Days Header -->
                        <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-slate-400 uppercase tracking-wider py-1">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>

                        <!-- Calendar Grid -->
                        <div id="calendar-days-grid" class="grid grid-cols-7 gap-1.5 min-h-[260px]">
                            <!-- Dynamically populated -->
                        </div>

                        <!-- Legend & Quick Guide -->
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between text-xs text-slate-500 gap-2">
                            <div class="flex items-center gap-3 text-[11px]">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded bg-rose-500 border border-rose-600 inline-block"></span>
                                    <span>Center Excluded</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded bg-amber-500 border border-amber-600 inline-block"></span>
                                    <span>Nationwide (all)</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded bg-white border border-slate-300 inline-block"></span>
                                    <span>Available</span>
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400">💡 Click any upcoming day to toggle exclusion</span>
                        </div>

                        <!-- Quick Manual Date Input -->
                        <div class="pt-2 bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/80">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Quick Date Exclude (or click date in calendar above):
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="date" id="input-quick-exclude-date" class="flex-1 p-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none bg-white">
                                <button type="button" onclick="quickAddExcludedDate()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Exclude Date
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Excluded Dates List -->
                    <div class="lg:col-span-5 flex flex-col h-full space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <span>Excluded Dates for:</span>
                                <span id="current-center-label" class="text-rose-600 font-extrabold">ULSAI (Dhaka - 474)</span>
                            </h3>
                            <span id="active-center-total" class="text-[11px] font-semibold text-slate-400">0 dates</span>
                        </div>

                        <div id="excluded-dates-list" class="space-y-2 max-h-[380px] overflow-y-auto pr-1 flex-1">
                            <div class="py-10 text-center text-slate-400 text-xs">Loading excluded dates...</div>
                        </div>

                        <div class="text-[11px] text-slate-400 bg-amber-50/70 border border-amber-200/60 text-amber-800 p-2.5 rounded-xl">
                            <strong>Note:</strong> Past dates and today are automatically blocked from seat reservation. Dates configured here will be hidden from the reservation calendar.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
let currentExcludedCenter = '474';
let excludedDaysData = { "474": [], "476": [], "all": [] };
let calendarViewDate = new Date();

const CENTER_NAMES = {
    '474': 'ULSAI (Dhaka - 474)',
    '476': 'KHULNA (476)',
    'all': 'All Centers / Nationwide (all)'
};

async function loadExcludedDays() {
    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_excluded_days');
        const data = await resp.json();
        if (data.success && data.excludedDays) {
            excludedDaysData = {
                "474": Array.isArray(data.excludedDays['474']) ? data.excludedDays['474'] : [],
                "476": Array.isArray(data.excludedDays['476']) ? data.excludedDays['476'] : [],
                "all": Array.isArray(data.excludedDays['all']) ? data.excludedDays['all'] : []
            };
            renderExcludedDaysUI();
        }
    } catch (e) {
        console.error('Failed to load excluded days:', e);
        showAdminToast('Failed to load excluded days', 'error');
    }
}

function setExcludedCenterTab(center) {
    currentExcludedCenter = center;

    ['474', '476', 'all'].forEach(c => {
        const tab = document.getElementById(`tab-center-${c}`);
        if (tab) {
            if (c === center) {
                tab.className = 'px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition flex items-center gap-2 border-rose-600 text-rose-700 bg-white shadow-sm cursor-pointer';
            } else {
                tab.className = 'px-4 py-2.5 text-xs font-semibold rounded-t-xl border-b-2 transition flex items-center gap-2 border-transparent text-slate-600 hover:text-slate-900 cursor-pointer';
            }
        }
    });

    const labelEl = document.getElementById('current-center-label');
    if (labelEl) labelEl.textContent = CENTER_NAMES[center] || center;

    renderExcludedDaysUI();
}

function renderExcludedDaysUI() {
    const b474 = document.getElementById('badge-count-474');
    const b476 = document.getElementById('badge-count-476');
    const bAll = document.getElementById('badge-count-all');
    if (b474) b474.textContent = (excludedDaysData['474'] || []).length;
    if (b476) b476.textContent = (excludedDaysData['476'] || []).length;
    if (bAll) bAll.textContent = (excludedDaysData['all'] || []).length;

    renderExcludedCalendar();
    renderExcludedDatesList();
}

function changeExcludedMonth(delta) {
    calendarViewDate.setMonth(calendarViewDate.getMonth() + delta);
    renderExcludedCalendar();
}

function resetExcludedMonthToToday() {
    calendarViewDate = new Date();
    renderExcludedCalendar();
}

function formatLocalDate(year, monthIndex, day) {
    const y = year;
    const m = String(monthIndex + 1).padStart(2, '0');
    const d = String(day).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function renderExcludedCalendar() {
    const grid = document.getElementById('calendar-days-grid');
    const title = document.getElementById('calendar-month-year');
    if (!grid || !title) return;

    const year = calendarViewDate.getFullYear();
    const month = calendarViewDate.getMonth();
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    title.textContent = `${monthNames[month]} ${year}`;

    const firstDayIndex = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = formatLocalDate(today.getFullYear(), today.getMonth(), today.getDate());

    let html = '';

    for (let i = 0; i < firstDayIndex; i++) {
        html += `<div class="h-16 rounded-xl bg-slate-50/40 border border-transparent"></div>`;
    }

    const centerExclusions = excludedDaysData[currentExcludedCenter] || [];
    const allExclusions = excludedDaysData['all'] || [];

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = formatLocalDate(year, month, day);
        const dateObj = new Date(year, month, day);
        dateObj.setHours(0, 0, 0, 0);

        const isPastOrToday = dateObj <= today;
        const isCenterExcluded = centerExclusions.includes(dateStr);
        const isNationwideExcluded = allExclusions.includes(dateStr);

        let cellClass = '';
        let badgeHtml = '';
        let clickHandler = '';

        if (isPastOrToday) {
            cellClass = 'bg-slate-50 border-slate-200 text-slate-300 cursor-not-allowed opacity-50';
            badgeHtml = dateStr === todayStr ? '<span class="text-[9px] text-slate-400 font-bold block">Today</span>' : '';
        } else if (isCenterExcluded) {
            cellClass = 'bg-rose-50 border-rose-300 text-rose-800 font-bold hover:bg-rose-100 cursor-pointer shadow-sm';
            badgeHtml = '<span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-semibold bg-rose-600 text-white rounded">Excluded</span>';
            clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
        } else if (isNationwideExcluded && currentExcludedCenter !== 'all') {
            cellClass = 'bg-amber-50 border-amber-300 text-amber-800 font-bold hover:bg-amber-100 cursor-pointer shadow-sm';
            badgeHtml = '<span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-semibold bg-amber-600 text-white rounded">All Centers</span>';
            clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
        } else if (isNationwideExcluded && currentExcludedCenter === 'all') {
            cellClass = 'bg-rose-50 border-rose-300 text-rose-800 font-bold hover:bg-rose-100 cursor-pointer shadow-sm';
            badgeHtml = '<span class="mt-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-semibold bg-rose-600 text-white rounded">Excluded</span>';
            clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
        } else {
            cellClass = 'bg-white border-slate-200 text-slate-700 hover:border-rose-400 hover:bg-rose-50/30 cursor-pointer';
            clickHandler = `onclick="toggleExcludedDayOnCalendar('${dateStr}')"`;
        }

        html += `
            <div ${clickHandler} class="h-16 p-1.5 rounded-xl border flex flex-col justify-between transition group relative ${cellClass}">
                <span class="text-xs font-semibold">${day}</span>
                <div class="truncate">${badgeHtml}</div>
            </div>
        `;
    }

    grid.innerHTML = html;
}

async function toggleExcludedDayOnCalendar(dateStr) {
    const isCenterExcluded = (excludedDaysData[currentExcludedCenter] || []).includes(dateStr);
    const isNationwideExcluded = (excludedDaysData['all'] || []).includes(dateStr);

    if (currentExcludedCenter !== 'all' && isNationwideExcluded && !isCenterExcluded) {
        alert(`The date "${dateStr}" is currently excluded nationwide in the 'All Centers' tab. Switch to 'All Centers / Nationwide' tab if you want to remove it.`);
        return;
    }

    if (isCenterExcluded) {
        if (confirm(`Remove exclusion for date ${dateStr} from center ${CENTER_NAMES[currentExcludedCenter]}?`)) {
            await removeExcludedDate(currentExcludedCenter, dateStr);
        }
    } else {
        await addExcludedDate(currentExcludedCenter, dateStr);
    }
}

async function addExcludedDate(center, dateStr) {
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'add_excluded_day');
        formData.append('center', center);
        formData.append('date', dateStr);

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        const data = await resp.json();
        if (data.success && data.excludedDays) {
            excludedDaysData = data.excludedDays;
            renderExcludedDaysUI();
            showAdminToast(`Date ${dateStr} added to exclusions`, 'success');
        } else {
            alert(data.error || 'Failed to add excluded date.');
        }
    } catch (e) {
        console.error('Error adding excluded date:', e);
        showAdminToast('Error communicating with server', 'error');
    }
}

async function removeExcludedDate(center, dateStr) {
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'remove_excluded_day');
        formData.append('center', center);
        formData.append('date', dateStr);

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        const data = await resp.json();
        if (data.success && data.excludedDays) {
            excludedDaysData = data.excludedDays;
            renderExcludedDaysUI();
            showAdminToast(`Date ${dateStr} removed from exclusions`, 'info');
        } else {
            alert(data.error || 'Failed to remove excluded date.');
        }
    } catch (e) {
        console.error('Error removing excluded date:', e);
        showAdminToast('Error communicating with server', 'error');
    }
}

async function quickAddExcludedDate() {
    const input = document.getElementById('input-quick-exclude-date');
    if (!input || !input.value) {
        alert('Please select a valid date.');
        return;
    }

    const dateStr = input.value;
    const todayStr = new Date().toISOString().split('T')[0];
    if (dateStr <= todayStr) {
        alert('Excluded dates should be future dates (from tomorrow onward). Past dates and today are already blocked automatically.');
        return;
    }

    await addExcludedDate(currentExcludedCenter, dateStr);
    input.value = '';
}

function renderExcludedDatesList() {
    const container = document.getElementById('excluded-dates-list');
    const countBadge = document.getElementById('active-center-total');
    if (!container) return;

    const centerDates = (excludedDaysData[currentExcludedCenter] || []).map(d => ({ date: d, scope: 'center' }));
    let combined = [...centerDates];

    if (currentExcludedCenter !== 'all') {
        const nationwideDates = (excludedDaysData['all'] || []).map(d => ({ date: d, scope: 'nationwide' }));
        combined = [...combined, ...nationwideDates];
    }

    combined.sort((a, b) => a.date.localeCompare(b.date));

    if (countBadge) {
        countBadge.textContent = `${combined.length} date${combined.length === 1 ? '' : 's'}`;
    }

    if (combined.length === 0) {
        container.innerHTML = `
            <div class="py-12 text-center text-slate-400 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <p class="text-xs font-semibold">No excluded dates for this center</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Click any date on the calendar to exclude it.</p>
            </div>
        `;
        return;
    }

    let html = '';
    combined.forEach(item => {
        const dateParts = item.date.split('-');
        const dObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
        const weekday = dObj.toLocaleDateString('en-US', { weekday: 'short' });
        const monthName = dObj.toLocaleDateString('en-US', { month: 'short' });
        const dayNum = dateParts[2];
        const yearNum = dateParts[0];

        const isNationwide = item.scope === 'nationwide';
        const badgeClass = isNationwide
            ? 'bg-amber-100 text-amber-800 border-amber-200'
            : 'bg-rose-100 text-rose-700 border-rose-200';
        const badgeLabel = isNationwide ? 'Nationwide (all)' : 'Center Specific';

        html += `
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex items-center justify-between gap-3 hover:border-slate-300 transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 flex flex-col items-center justify-center text-center shrink-0">
                        <span class="text-[9px] font-bold uppercase text-slate-500 leading-tight">${monthName}</span>
                        <span class="text-sm font-extrabold text-slate-800 leading-tight">${dayNum}</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-slate-800">${item.date}</span>
                            <span class="text-slate-400 text-xs">(${weekday}, ${yearNum})</span>
                        </div>
                        <div class="mt-0.5">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border ${badgeClass}">
                                ${badgeLabel}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    ${isNationwide && currentExcludedCenter !== 'all' ? `
                        <button type="button" onclick="setExcludedCenterTab('all')" class="text-[11px] font-semibold text-amber-700 hover:underline px-2 py-1 bg-amber-50 rounded-lg cursor-pointer" title="Manage in Nationwide tab">
                            Go to Nationwide
                        </button>
                    ` : `
                        <button type="button" onclick="removeExcludedDate('${currentExcludedCenter}', '${item.date}')" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer" title="Remove exclusion">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    `}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// Initial load on page ready
document.addEventListener('DOMContentLoaded', () => {
    loadExcludedDays();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
