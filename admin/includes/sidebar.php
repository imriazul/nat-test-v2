<?php
/**
 * Shared Admin Layout - Sidebar Navigation
 * Renders both the persistent desktop sidebar and the responsive mobile slide-out drawer.
 */

if (!isset($activePage)) {
    $activePage = 'dashboard';
}

$navItems = [
    [
        'category' => 'MAIN MENU',
        'items' => [
            [
                'id'    => 'dashboard',
                'label' => 'Overview',
                'url'   => 'index',
                'icon'  => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
                'desc'  => 'Stats & system status'
            ],
            [
                'id'    => 'tables',
                'label' => 'Exam Tables',
                'url'   => 'tables',
                'icon'  => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7zm0 4h16M10 4v16"></path></svg>',
                'desc'  => 'PBT & CBT month tables'
            ],
            [
                'id'    => 'import',
                'label' => 'Import Examinees',
                'url'   => 'import',
                'icon'  => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>',
                'desc'  => 'Upload CSV or Excel data'
            ],
        ]
    ],
    [
        'category' => 'AUTOMATION & OPERATIONS',
        'items' => [
            [
                'id'    => 'scrapers',
                'label' => 'Result Scrapers',
                'url'   => 'scrapers',
                'icon'  => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>',
                'desc'  => 'PBT & CBT auto-scraper cron'
            ],
            [
                'id'    => 'schedule',
                'label' => 'CBT Schedule',
                'url'   => 'schedule',
                'icon'  => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
                'desc'  => 'Excluded dates by center'
            ],
        ]
    ],
    [
        'category' => 'SECURITY & ACCESS',
        'items' => [
            [
                'id'    => 'users',
                'label' => 'Portal Users',
                'url'   => 'users',
                'icon'  => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>',
                'desc'  => 'CBT institutional accounts'
            ],
        ]
    ]
];
?>

<!-- ═══ DESKTOP SIDEBAR ═══ -->
<aside class="hidden md:flex flex-col w-64 bg-slate-900 border-r border-slate-800/80 text-slate-300 shrink-0 select-none">
    <div class="flex-1 py-5 px-3.5 space-y-6 overflow-y-auto custom-scrollbar">
        <?php foreach ($navItems as $group): ?>
            <div>
                <p class="px-3 mb-2 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    <?php echo htmlspecialchars($group['category']); ?>
                </p>
                <nav class="space-y-1">
                    <?php foreach ($group['items'] as $item): 
                        $isActive = ($activePage === $item['id']);
                        $linkClasses = $isActive
                            ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 font-bold'
                            : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/90 font-medium';
                        $iconClasses = $isActive
                            ? 'text-white'
                            : 'text-slate-400 group-hover:text-blue-400 transition-colors';
                    ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition duration-150 group <?php echo $linkClasses; ?>">
                            <span class="shrink-0 <?php echo $iconClasses; ?>">
                                <?php echo $item['icon']; ?>
                            </span>
                            <div class="flex-1 truncate">
                                <span class="block leading-tight"><?php echo htmlspecialchars($item['label']); ?></span>
                                <span class="block text-[10px] opacity-75 font-normal truncate mt-0.5">
                                    <?php echo htmlspecialchars($item['desc']); ?>
                                </span>
                            </div>
                            <?php if ($isActive): ?>
                                <span class="w-1.5 h-1.5 rounded-full bg-white shrink-0"></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Desktop Footer Pill: System Health -->
    <div class="p-3 border-t border-slate-800/80 bg-slate-950/40 text-[11px] text-slate-400">
        <div class="flex items-center justify-between px-2 py-1.5 rounded-lg bg-slate-900/60 border border-slate-800">
            <span class="flex items-center gap-1.5 font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>System Ready</span>
            </span>
            <span class="text-[10px] font-mono text-slate-500">PHP <?php echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?></span>
        </div>
    </div>
</aside>

<!-- ═══ MOBILE SIDEBAR DRAWER (OFF-CANVAS) ═══ -->
<div id="mobile-sidebar-backdrop"
     onclick="toggleMobileSidebar()"
     class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden md:hidden transition-opacity duration-200">
</div>

<aside id="mobile-sidebar"
       class="fixed top-0 bottom-0 left-0 z-50 w-72 bg-slate-900 border-r border-slate-800 text-slate-300 flex flex-col -translate-x-full md:hidden transition-transform duration-200 ease-in-out shadow-2xl">
    
    <!-- Drawer Header -->
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <img src="../logo.png" alt="NAT-TEST BD Logo" class="w-7 h-7 object-contain">
            <span class="font-bold text-white text-sm">Admin Navigation</span>
        </div>
        <button type="button" onclick="toggleMobileSidebar()" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Drawer Navigation Links -->
    <div class="flex-1 py-4 px-3 space-y-5 overflow-y-auto">
        <?php foreach ($navItems as $group): ?>
            <div>
                <p class="px-3 mb-2 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    <?php echo htmlspecialchars($group['category']); ?>
                </p>
                <nav class="space-y-1">
                    <?php foreach ($group['items'] as $item): 
                        $isActive = ($activePage === $item['id']);
                        $linkClasses = $isActive
                            ? 'bg-blue-600 text-white font-bold'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800 font-medium';
                    ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?php echo $linkClasses; ?>">
                            <span class="shrink-0 <?php echo $isActive ? 'text-white' : 'text-slate-400'; ?>">
                                <?php echo $item['icon']; ?>
                            </span>
                            <div class="flex-1 truncate">
                                <span class="block leading-tight"><?php echo htmlspecialchars($item['label']); ?></span>
                                <span class="block text-[10px] opacity-75 font-normal truncate mt-0.5">
                                    <?php echo htmlspecialchars($item['desc']); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Drawer Footer -->
    <div class="p-3 border-t border-slate-800 bg-slate-950/60 flex items-center justify-between">
        <a href="../user/dashboard" target="_blank" class="text-xs font-semibold text-cyan-400 hover:underline flex items-center gap-1">
            <span>CBT Seat Portal</span> →
        </a>
        <a href="?logout=true" class="text-xs font-bold text-rose-400 hover:underline">
            Logout
        </a>
    </div>
</aside>
