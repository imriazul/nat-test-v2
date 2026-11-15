<?php
/**
 * Shared Admin Layout - Footer
 * Closes page markup, provides CSRF token, global fetch hooks, and common utilities.
 */
?>
    </div> <!-- /flex-1 flex overflow-hidden -->

    <!-- Global Toast Container -->
    <div id="admin-toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none max-w-sm w-full"></div>

    <script>
    // ─── CSRF Token & Global Fetch Interceptor ────────────────────
    const ADMIN_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_csrf_token'] ?? ''); ?>;
    const ADMIN_API_URL    = 'api';

    const _originalAdminFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        if (options && options.method && options.method.toUpperCase() === 'POST') {
            options.headers = options.headers || {};
            if (options.headers instanceof Headers) {
                options.headers.set('X-CSRF-Token', ADMIN_CSRF_TOKEN);
            } else {
                options.headers['X-CSRF-Token'] = ADMIN_CSRF_TOKEN;
            }
        }
        return _originalAdminFetch(url, options);
    };

    // ─── Mobile Sidebar Drawer Toggle ────────────────────────────
    function toggleMobileSidebar() {
        const drawer = document.getElementById('mobile-sidebar');
        const backdrop = document.getElementById('mobile-sidebar-backdrop');
        if (!drawer || !backdrop) return;

        const isClosed = drawer.classList.contains('-translate-x-full');
        if (isClosed) {
            drawer.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        } else {
            drawer.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    // ─── Common Date Helpers ─────────────────────────────────────
    const MONTHS_SHORT = {
        '01':'Jan','02':'Feb','03':'Mar','04':'Apr','05':'May','06':'Jun',
        '07':'Jul','08':'Aug','09':'Sep','10':'Oct','11':'Nov','12':'Dec'
    };
    const MONTHS_FULL = {
        '01':'January','02':'February','03':'March','04':'April','05':'May','06':'June',
        '07':'July','08':'August','09':'September','10':'October','11':'November','12':'December'
    };

    function escapeHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ─── Global Toast Notification Helper ────────────────────────
    function showAdminToast(message, type = 'info') {
        const container = document.getElementById('admin-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto flex items-center gap-3 p-3.5 rounded-xl shadow-xl text-xs font-semibold text-white animate-fade-up border transition-all duration-300';
        
        if (type === 'success') {
            toast.classList.add('bg-emerald-900', 'border-emerald-700/80', 'text-emerald-100');
            toast.innerHTML = `<svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span class="flex-1">${escapeHtml(message)}</span>`;
        } else if (type === 'error') {
            toast.classList.add('bg-rose-900', 'border-rose-700/80', 'text-rose-100');
            toast.innerHTML = `<svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><span class="flex-1">${escapeHtml(message)}</span>`;
        } else {
            toast.classList.add('bg-slate-800', 'border-slate-700', 'text-slate-100');
            toast.innerHTML = `<svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span class="flex-1">${escapeHtml(message)}</span>`;
        }

        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    </script>
</body>
</html>
