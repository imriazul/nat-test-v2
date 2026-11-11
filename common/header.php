<?php

// Dynamically compute base URL and project root path (supports root domain & subfolders like /NAT-TEST BD/)
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$siteProtocol = $isHttps ? 'https' : 'http';
$siteHost     = $_SERVER['HTTP_HOST'] ?? 'nat-test.bd';

// Detect if running inside a subfolder (e.g. /NAT-TEST BD or /project)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBaseDir = rtrim($scriptDir, '/');
if (basename($appBaseDir) === 'admin' || basename($appBaseDir) === 'common') {
    $appBaseDir = dirname($appBaseDir);
}
$appBaseDir = ($appBaseDir === '/' || $appBaseDir === '.') ? '' : $appBaseDir;
$siteBaseUrl  = $siteProtocol . '://' . $siteHost . $appBaseDir;

// Clean canonical path (stripping query string unless explicitly passed)
$requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$subPath = $requestPath;
if ($appBaseDir !== '' && strpos($subPath, $appBaseDir) === 0) {
    $subPath = substr($subPath, strlen($appBaseDir));
}
$cleanPath = preg_replace('/(\/index\.php|\/index)$/', '/', $subPath);
$cleanPath = preg_replace('/\.php$/', '', $cleanPath);

$pageTitle    = $pageTitle ?? 'NAT-TEST Result Check Online | NAT-TEST BD — PBT & CBT Result Portal';
$pageDesc     = $pageDesc  ?? 'Check your official NAT-TEST Result online in Bangladesh with NAT-TEST BD. Instant verification for PBT and CBT NAT-TEST Japanese proficiency examinations (N5 to N1).';
$pageKeywords = $pageKeywords ?? 'NAT-TEST Result, NAT-TEST BD, NAT-TEST, CBT NAT-TEST, PBT NAT-TEST, Japanese language test Bangladesh, NAT-TEST online result, ULSAI Dhaka NAT TEST, NAT-TEST score verification';
$canonicalUrl = $canonicalUrl ?? ($siteBaseUrl . $cleanPath);
$ogImage      = $ogImage ?? ($siteBaseUrl . '/logo.png');
$ogType       = $ogType ?? 'website';
$logoUrl      = $logoUrl ?? ($appBaseDir . '/logo.png');
$logoWebpUrl  = $logoWebpUrl ?? ($appBaseDir . '/logo.webp');
$faviconUrl   = $faviconUrl ?? ($appBaseDir . '/favicon-icon.png');
$bodyClass    = $bodyClass ?? '';
$extraHead    = $extraHead ?? '';

// Global Organization & Website JSON-LD Schema
$baseSchema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id'   => $siteBaseUrl . '/#organization',
            'name'  => 'NAT-TEST BD',
            'alternateName' => ['NAT TEST BD', 'NAT-TEST Bangladesh'],
            'url'   => $siteBaseUrl . '/',
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => $siteBaseUrl . '/logo.png',
                'caption' => 'NAT-TEST BD Portal'
            ],
            'description' => 'NAT TEST Result Checker and Information Center for Bangladesh.',
        ],
        [
            '@type' => 'WebSite',
            '@id'   => $siteBaseUrl . '/#website',
            'url'   => $siteBaseUrl . '/',
            'name'  => 'NAT-TEST BD',
            'alternateName' => ['NAT TEST BD', 'NAT-TEST Bangladesh'],
            'description' => 'Online NAT-TEST Result Portal for Bangladesh (PBT & CBT NAT-TEST)',
            'publisher' => [
                '@id' => $siteBaseUrl . '/#organization'
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $siteBaseUrl . '/result?examinee_number={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Primary SEO Meta Tags -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author" content="NAT-TEST BD">
    <meta name="language" content="English">
    <meta name="geo.region" content="BD">
    <meta name="geo.placename" content="Dhaka, Bangladesh">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?php echo htmlspecialchars($ogType); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta property="og:site_name" content="NAT-TEST BD">
    <meta property="og:locale" content="en_US">
    <meta property="og:locale:alternate" content="bn_BD">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">

    <!-- Mobile & Theme UI -->
    <meta name="theme-color" content="#0f172a">
    <meta name="application-name" content="NAT-TEST BD">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NAT-TEST BD">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($appBaseDir . '/favicon.ico?v=2'); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($appBaseDir . '/favicon.ico?v=2'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($appBaseDir . '/favicon-32x32.png?v=2'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($appBaseDir . '/favicon-16x16.png?v=2'); ?>">
    <link rel="icon" type="image/png" sizes="128x128" href="<?php echo htmlspecialchars($appBaseDir . '/favicon-icon.png?v=2'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($appBaseDir . '/apple-touch-icon.png?v=2'); ?>">

    <!-- Production Compiled Tailwind CSS (Preloaded & Minified) -->
    <?php $cssPath = $appBaseDir . '/assets/css/style.min.css?v=' . (file_exists(__DIR__ . '/../assets/css/style.min.css') ? filemtime(__DIR__ . '/../assets/css/style.min.css') : '1'); ?>
    <link rel="preload" href="<?php echo htmlspecialchars($cssPath); ?>" as="style">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath); ?>">

    <!-- Google Fonts — Inter (Optimized Non-Blocking with System Font Fallback) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    </noscript>

    <!-- Google Analytics (gtag.js) — High-Performance Deferred Loading -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        (function() {
            var gaLoaded = false;
            function loadGA() {
                if (gaLoaded) return;
                gaLoaded = true;
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=G-Q0FKRSXSGH';
                document.head.appendChild(s);
                gtag('config', 'G-Q0FKRSXSGH');
            }
            ['scroll', 'touchstart', 'mousemove', 'keydown'].forEach(function(e) {
                window.addEventListener(e, loadGA, { once: true, passive: true });
            });
            if ('requestIdleCallback' in window) {
                requestIdleCallback(function() { setTimeout(loadGA, 2500); });
            } else {
                window.addEventListener('load', function() { setTimeout(loadGA, 2500); });
            }
        })();
    </script>

    <!-- Schema.org JSON-LD Structured Data (Core) -->
    <script type="application/ld+json">
    <?php echo json_encode($baseSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>

    <!-- Optional Page-Specific Schema Markup -->
    <?php if (!empty($schemaJson)): ?>
    <script type="application/ld+json">
    <?php echo is_array($schemaJson) ? json_encode($schemaJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : $schemaJson; ?>
    </script>
    <?php endif; ?>

    <?php if (!empty($extraHead)) echo $extraHead . "\n"; ?>


</head>

<body class="bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 min-h-screen font-sans antialiased text-slate-800 flex flex-col overflow-x-hidden w-full <?php echo htmlspecialchars($bodyClass); ?>">

    <!-- ═══════════════════════════════════════════════════════
         HEADER / NAVIGATION BAR
    ═══════════════════════════════════════════════════════ -->
    <header class="sticky top-0 z-50 nav-glass bg-white/80 border-b border-slate-200/60 shadow-sm shadow-slate-200/40">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo & Brand -->
                <a href="<?php echo htmlspecialchars($appBaseDir ?: '/'); ?>" class="flex items-center gap-2.5 sm:gap-3 group select-none" aria-label="NAT-TEST BD Homepage">
                    <!-- Logo mark -->
                    <div class="relative flex items-center justify-center shrink-0">
                        <div class="absolute inset-0 bg-blue-600/15 rounded-xl blur-md group-hover:blur-lg transition-all duration-300"></div>
                        <picture>
                            <source srcset="<?php echo htmlspecialchars($logoWebpUrl); ?>" type="image/webp">
                            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NAT-TEST BD Logo" width="40" height="40" fetchpriority="high" decoding="async" class="relative w-9 h-9 sm:w-10 sm:h-10 object-contain group-hover:scale-105 transition-transform duration-300 drop-shadow-sm">
                        </picture>
                    </div>
                    <!-- Brand text (Visible on both mobile & desktop) -->
                    <div class="flex flex-col">
                        <div class="flex items-center leading-tight">
                            <span class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">NAT-TEST</span>
                            <span class="text-base sm:text-lg font-extrabold text-blue-600 tracking-tight ml-1">BD</span>
                        </div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold uppercase tracking-widest text-slate-400 -mt-0.5">Result Portal</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="/" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-blue-600 hover:bg-blue-50/80 rounded-xl transition-all duration-200">
                        Home
                    </a>
                    <a href="result" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-blue-600 hover:bg-blue-50/80 rounded-xl transition-all duration-200">
                        Check Result
                    </a>
                    <a href="/#about" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-blue-600 hover:bg-blue-50/80 rounded-xl transition-all duration-200">
                        About
                    </a>
                </nav>

                <!-- Right side: Status badge + Mobile toggle -->
                <div class="flex items-center gap-3">
                    <!-- Live status indicator -->
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/80">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Online</span>
                    </div>

                    <!-- Mobile hamburger -->
                    <button id="mobile-menu-btn" onclick="toggleMobileMenu()" class="md:hidden w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-100 transition-colors" aria-label="Toggle menu">
                        <svg id="hamburger-icon" class="w-5 h-5 text-slate-700 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        <svg id="close-icon" class="w-5 h-5 text-slate-700 hidden transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="md:hidden mobile-menu-enter border-t border-slate-200/60">
            <div class="px-4 py-3 space-y-1 bg-white/90 nav-glass">
                <a href="/" class="block px-4 py-2.5 text-sm font-semibold text-slate-700 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                    🏠 Home
                </a>
                <a href="result" class="block px-4 py-2.5 text-sm font-semibold text-slate-700 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                    🔍 Check Result
                </a>
                <a href="/#about" class="block px-4 py-2.5 text-sm font-semibold text-slate-700 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                    📖 About
                </a>
            </div>
        </div>
    </header>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const hamburger = document.getElementById('hamburger-icon');
            const closeIcon = document.getElementById('close-icon');
            const isOpen = menu.classList.contains('open');

            if (isOpen) {
                menu.classList.remove('open');
                hamburger.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            } else {
                menu.classList.add('open');
                hamburger.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            }
        }
    </script>

    <!-- Main Content Wrapper (flex-1 pushes footer to bottom) -->
    <div class="flex-1 flex flex-col items-center pt-6 pb-16 px-3 sm:px-4 w-full overflow-x-hidden">