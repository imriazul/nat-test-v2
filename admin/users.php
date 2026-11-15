<?php
require_once __DIR__ . '/auth.php';

$pageTitle = 'CBT Portal Users | NAT-TEST BD Admin';
$activePage = 'users';

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
                    <span class="text-slate-800">Users</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">CBT Portal User Accounts</h1>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="openAddUserModal()"
                    class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-teal-500 hover:from-cyan-500 hover:to-teal-400 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    <span>Add New User</span>
                </button>
            </div>
        </div>

        <!-- ── User Accounts Card ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- Card Header -->
            <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-400 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-bold text-white">Authorized Portal Accounts</h2>
                            <span id="user-count-badge" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-cyan-950 text-cyan-300 border border-cyan-700">0 Users</span>
                        </div>
                        <p class="text-slate-400 text-xs mt-0.5">Manage member accounts permitted to reserve workstations on the CBT Seat Availability portal.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="../user/dashboard" target="_blank" class="px-3 py-2 bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-semibold rounded-xl border border-white/15 transition flex items-center gap-1.5" title="Open CBT User Portal">
                        <span>Open User Portal</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>
            </div>

            <!-- Users Table Body -->
            <div class="p-6">
                <!-- Search & Filters -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 mb-4">
                    <div class="relative w-full sm:w-80">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="user-search-input" oninput="filterUsersTable()" placeholder="Search name, email, institute..."
                            class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="button" onclick="loadUsersList()" class="text-xs text-slate-600 hover:text-slate-900 font-bold flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 transition cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Refresh Users</span>
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left text-xs border-collapse divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">User</th>
                                <th class="py-3 px-4">Phone Number</th>
                                <th class="py-3 px-4">Institute Name</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Created Date</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body" class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">Loading user accounts...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- ── Add / Edit User Modal ── -->
<div id="user-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-lg w-full my-8 overflow-hidden animate-fade-up">
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-900 text-white">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                <h3 class="font-bold text-base" id="user-modal-title">Add New Portal User</h3>
            </div>
            <button type="button" onclick="closeUserModal()" class="text-slate-400 hover:text-white transition p-1 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Modal Form -->
        <form id="user-form" onsubmit="saveUserForm(event)" class="p-6 space-y-4">
            <input type="hidden" id="user-form-id" value="">

            <!-- Full Name -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="user-form-name" required placeholder="e.g. John Doe"
                    class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
            </div>

            <!-- Email Address -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Email Address (Login Username) <span class="text-red-500">*</span>
                </label>
                <input type="email" id="user-form-email" required placeholder="user@example.com"
                    class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
            </div>

            <!-- Phone & Institute -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Phone Number
                    </label>
                    <input type="text" id="user-form-phone" placeholder="e.g. 01712345678"
                        class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Institute Name
                    </label>
                    <input type="text" id="user-form-institute" placeholder="e.g. Dhaka Japanese Academy"
                        class="w-full p-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                </div>
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Password <span id="user-form-pw-req" class="text-red-500">*</span>
                    </label>
                    <button type="button" onclick="generateRandomUserPassword()" class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 cursor-pointer">
                        🎲 Generate Password
                    </button>
                </div>
                <div class="relative">
                    <input type="text" id="user-form-password" placeholder="Enter password (min 6 chars)"
                        class="w-full p-2.5 pr-20 text-xs font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none">
                    <button type="button" onclick="copyUserPassword()" class="absolute right-2 top-2 px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-medium transition cursor-pointer" title="Copy password">
                        Copy
                    </button>
                </div>
                <p id="user-form-pw-hint" class="text-[11px] text-slate-400 mt-1">Min 6 characters. Passwords are securely hashed with bcrypt in the database.</p>
            </div>

            <!-- Modal Actions -->
            <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                <button type="button" onclick="closeUserModal()" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-save-user" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow transition cursor-pointer">
                    Save User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let usersCache = [];

async function loadUsersList() {
    const tbody = document.getElementById('users-table-body');
    const badge = document.getElementById('user-count-badge');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-slate-400">Loading user accounts...</td></tr>`;

    try {
        const resp = await fetch(ADMIN_API_URL + '?action=get_users');
        const data = await resp.json();

        if (data.success && Array.isArray(data.users)) {
            usersCache = data.users;
            if (badge) badge.textContent = `${usersCache.length} User${usersCache.length === 1 ? '' : 's'}`;
            renderUsersTable(usersCache);
        } else {
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-red-500">${escapeHtml(data.error || 'Failed to load users.')}</td></tr>`;
        }
    } catch (e) {
        console.error('Error loading users:', e);
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-red-500">Error connecting to server.</td></tr>`;
        showAdminToast('Failed to load portal users', 'error');
    }
}

function renderUsersTable(users) {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;

    if (!users || users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-12 text-center text-slate-400">
                    <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <p class="font-medium">No user accounts found.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Click "Add New User" to grant access to the portal.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = users.map(u => {
        const isActive = (u.status === 'active');
        const statusBadge = isActive
            ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 border border-emerald-200">Active</span>'
            : '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-600 border border-slate-200">Inactive</span>';

        const toggleBtnLabel = isActive ? 'Disable' : 'Activate';
        const toggleBtnClass = isActive
            ? 'text-amber-600 hover:text-amber-800 hover:bg-amber-50'
            : 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50';

        const safeUser = JSON.stringify(u).replace(/"/g, '&quot;');

        return `
            <tr class="hover:bg-slate-50/80 transition">
                <td class="py-3 px-4 font-medium text-slate-900">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-slate-700 text-xs shrink-0">
                            ${escapeHtml(u.name.charAt(0).toUpperCase())}
                        </div>
                        <div>
                            <span class="font-bold text-slate-800 block">${escapeHtml(u.name)}</span>
                            <span class="text-slate-400 font-mono text-[11px] block">${escapeHtml(u.email)}</span>
                        </div>
                    </div>
                </td>
                <td class="py-3 px-4 text-slate-600 font-mono">${escapeHtml(u.phone || '—')}</td>
                <td class="py-3 px-4 text-slate-700">${escapeHtml(u.institute || '—')}</td>
                <td class="py-3 px-4">${statusBadge}</td>
                <td class="py-3 px-4 text-slate-400 text-[11px] font-mono">${escapeHtml(u.created_at || '—')}</td>
                <td class="py-3 px-4 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <button type="button" onclick="openEditUserModal(${safeUser})" class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition cursor-pointer" title="Edit user">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <button type="button" onclick="toggleUserStatus(${u.id})" class="px-2 py-1 rounded text-[11px] font-semibold border border-slate-200 ${toggleBtnClass} transition cursor-pointer" title="Toggle status">
                            ${toggleBtnLabel}
                        </button>
                        <button type="button" onclick="deleteUser(${u.id}, '${escapeHtml(u.name)}')" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer" title="Delete account">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function filterUsersTable() {
    const input = document.getElementById('user-search-input');
    const term = (input ? input.value : '').toLowerCase().trim();

    if (!term) {
        renderUsersTable(usersCache);
        return;
    }

    const filtered = usersCache.filter(u => {
        return (u.name || '').toLowerCase().includes(term)
            || (u.email || '').toLowerCase().includes(term)
            || (u.phone || '').toLowerCase().includes(term)
            || (u.institute || '').toLowerCase().includes(term);
    });

    renderUsersTable(filtered);
}

function openAddUserModal() {
    document.getElementById('user-modal-title').textContent = 'Add New Portal User';
    document.getElementById('user-form-id').value = '';
    document.getElementById('user-form-name').value = '';
    document.getElementById('user-form-email').value = '';
    document.getElementById('user-form-phone').value = '';
    document.getElementById('user-form-institute').value = '';
    document.getElementById('user-form-password').value = '';
    document.getElementById('user-form-password').required = true;
    document.getElementById('user-form-pw-req').classList.remove('hidden');
    document.getElementById('user-form-pw-hint').textContent = 'Min 6 characters. Passwords are securely hashed with bcrypt in the database.';

    document.getElementById('user-modal').classList.remove('hidden');
    setTimeout(() => document.getElementById('user-form-name').focus(), 50);
}

function openEditUserModal(user) {
    document.getElementById('user-modal-title').textContent = 'Edit Portal User: ' + user.name;
    document.getElementById('user-form-id').value = user.id;
    document.getElementById('user-form-name').value = user.name || '';
    document.getElementById('user-form-email').value = user.email || '';
    document.getElementById('user-form-phone').value = user.phone || '';
    document.getElementById('user-form-institute').value = user.institute || '';
    document.getElementById('user-form-password').value = '';
    document.getElementById('user-form-password').required = false;
    document.getElementById('user-form-pw-req').classList.add('hidden');
    document.getElementById('user-form-pw-hint').textContent = 'Leave password blank if you do not want to change it.';

    document.getElementById('user-modal').classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
}

function generateRandomUserPassword() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    let pw = '';
    for (let i = 0; i < 10; i++) {
        pw += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById('user-form-password');
    if (input) {
        input.value = pw;
        input.type = 'text';
    }
}

function copyUserPassword() {
    const input = document.getElementById('user-form-password');
    if (!input || !input.value) {
        showAdminToast('No password to copy', 'error');
        return;
    }
    navigator.clipboard.writeText(input.value).then(() => {
        showAdminToast('Password copied to clipboard!', 'success');
    }).catch(() => {
        input.select();
        document.execCommand('copy');
        showAdminToast('Password copied to clipboard!', 'success');
    });
}

async function saveUserForm(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-user');
    const id = document.getElementById('user-form-id').value;
    const isEdit = !!id;

    const name = document.getElementById('user-form-name').value.trim();
    const email = document.getElementById('user-form-email').value.trim();
    const phone = document.getElementById('user-form-phone').value.trim();
    const institute = document.getElementById('user-form-institute').value.trim();
    const password = document.getElementById('user-form-password').value;

    if (!name || !email) {
        alert('Name and Email are required.');
        return;
    }
    if (!isEdit && (!password || password.length < 6)) {
        alert('Password must be at least 6 characters.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const formData = new URLSearchParams();
        formData.append('action', isEdit ? 'update_user' : 'create_user');
        if (isEdit) formData.append('id', id);
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('institute', institute);
        if (password) formData.append('password', password);

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await resp.json();

        if (data.success) {
            closeUserModal();
            await loadUsersList();
            showAdminToast(data.message || 'User saved successfully!', 'success');
        } else {
            alert(data.error || 'Failed to save user.');
        }
    } catch (err) {
        console.error('Error saving user:', err);
        showAdminToast('Failed to save user. Check console for details.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save User';
    }
}

async function toggleUserStatus(id) {
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'toggle_user_status');
        formData.append('id', id);

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await resp.json();

        if (data.success) {
            const user = usersCache.find(u => u.id == id);
            if (user) user.status = data.status;
            renderUsersTable(usersCache);
            showAdminToast(data.message || 'Status updated', 'info');
        } else {
            alert(data.error || 'Failed to update user status.');
        }
    } catch (e) {
        console.error('Error toggling status:', e);
        showAdminToast('Error toggling user status', 'error');
    }
}

async function deleteUser(id, name) {
    if (!confirm(`Are you sure you want to permanently delete the user account for "${name}"?\n\nThis will revoke their access to the CBT seat portal.`)) {
        return;
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'delete_user');
        formData.append('id', id);

        const resp = await fetch(ADMIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await resp.json();

        if (data.success) {
            usersCache = usersCache.filter(u => u.id != id);
            renderUsersTable(usersCache);
            const badge = document.getElementById('user-count-badge');
            if (badge) badge.textContent = `${usersCache.length} User${usersCache.length === 1 ? '' : 's'}`;
            showAdminToast(`User account "${name}" deleted.`, 'info');
        } else {
            alert(data.error || 'Failed to delete user.');
        }
    } catch (e) {
        console.error('Error deleting user:', e);
        showAdminToast('Error communicating with server', 'error');
    }
}

// Initial load on page ready
document.addEventListener('DOMContentLoaded', () => {
    loadUsersList();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
