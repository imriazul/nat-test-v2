<?php

$pageTitle    = 'NAT-TEST Result Check Online | PBT & CBT Result';
$pageDesc     = 'Check your official NAT-TEST Result online. Fast, verified score and accurate result for PBT and CBT NAT-TEST.';
$pageKeywords = 'NAT-TEST Result, NAT-TEST BD, NAT-TEST, CBT NAT-TEST, PBT NAT-TEST, NAT-TEST result check, Japanese language proficiency exam BD';

$schemaJson = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebApplication',
            'name'  => 'NAT-TEST BD Result Verification Portal',
            'url'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'nat-test.bd') . '/',
            'applicationCategory' => 'EducationalApplication',
            'operatingSystem' => 'All',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'BDT'
            ],
            'description' => 'Free online system for Bangladesh examinees to instantly look up and verify their PBT and CBT NAT-TEST Results.'
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'What is the NAT-TEST?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'The NAT-TEST (Nihongo Nouryoku Test) is an internationally recognized Japanese language proficiency examination conducted by Senmon Kyouiku Shuppan in Japan. It evaluates Japanese skills across 5 levels from 5Q (N5 beginner) to 1Q (N1 advanced).'
                    ]
                ],
                [
                    '@type' => 'Question',
                    'name' => 'How often are NAT-TEST and CBT NAT-TEST held in Bangladesh?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'NAT-TEST (Paper-Based Test) is held every month across Bangladesh. CBT NAT-TEST (Computer-Based Test) is held every day with 3 daily slots: morning, noon, and afternoon. For complete scheduling and registration details, visit https://ulsai.com.bd/cbt.'
                    ]
                ],
                [
                    '@type' => 'Question',
                    'name' => 'How can I check my NAT-TEST Result on NAT-TEST BD?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Enter your 14-digit examinee registration number into the NAT-TEST BD search bar. You will instantly view your NAT-TEST Result including exam type (PBT or CBT NAT-TEST), exam level, center, and pass/fail status.'
                    ]
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Where can I find my 14-digit examinee number?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Your 14-digit registration number is printed on your NAT-TEST examination admission ticket (受験票) and was also sent via SMS or email by your exam center (e.g. ULSAI Dhaka Code 474).'
                    ]
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Are NAT-TEST and CBT NAT-TEST certificates valid for Japanese visas?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Yes. Certificates issued for passing NAT-TEST and CBT NAT-TEST are officially accepted by the Immigration Services Agency of Japan for student visas, Specified Skilled Worker (SSW) visas, and technical training programs.'
                    ]
                ]
            ]
        ]
    ]
];

require_once __DIR__ . '/common/header.php';
?>

    <section class="w-full max-w-5xl mx-auto pt-6 sm:pt-8 pb-8 sm:pb-12 px-2" id="search">
        <div class="relative overflow-hidden py-4 rounded-3xl">
            <!-- Decorative background glows -->
            <div class="absolute -top-20 -left-20 w-72 h-72 bg-blue-400/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-20 -right-20 w-72 h-72 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative text-center max-w-3xl mx-auto">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-50 border border-blue-200/80 shadow-sm">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                        </span>
                        <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">NAT-TEST Result Portal</span>
                    </div>

                <!-- Primary H1 Heading targeting core focus keywords -->
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 leading-[1.1] mb-5">
                    Check Your
                    <span class="relative inline-block">
                        <span class="relative z-10 text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700">NAT-TEST Result</span>
                        <span class="absolute bottom-1 left-0 right-0 h-3 bg-blue-200/50 rounded-full -z-0"></span>
                    </span>
                </h1>

                <!-- Keyword-optimized Subtitle -->
                <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl mx-auto mb-10">
                    The fastest and most reliable portal to check your <strong class="text-slate-900 font-semibold">NAT-TEST Result</strong> in Bangladesh. Instant score verification for both <strong class="text-blue-700 font-semibold">PBT</strong> and <strong class="text-purple-700 font-semibold">CBT NAT-TEST</strong> examinees across all proficiency levels (N5 to N1).
                </p>

                <!-- Search Box -->
                <form method="GET" action="result" class="relative group max-w-xl mx-auto mb-6" role="search" aria-label="NAT-TEST Examinee Number Search">
                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-500/20 via-indigo-500/20 to-blue-500/20 rounded-[20px] blur-lg opacity-0 group-focus-within:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative flex items-center">
                        <label for="examinee-number-input" class="sr-only">Enter 14-digit examinee registration number</label>
                        <div class="absolute left-4 pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text"
                               id="examinee-number-input"
                               name="examinee_number"
                               placeholder="Enter 14-digit examinee number…"
                               required
                               autocomplete="off"
                               inputmode="numeric"
                               pattern="[0-9]{14}"
                               title="Please enter a valid 14-digit NAT-TEST examinee number"
                               class="block w-full p-4 pl-12 pr-32 text-base sm:text-lg text-slate-900 border border-slate-200 rounded-2xl bg-white shadow-lg shadow-slate-200/50 focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all placeholder:text-slate-400 font-mono sm:font-sans">
                        <button type="submit"
                                aria-label="Search NAT-TEST Result"
                                class="absolute right-2 top-2 bottom-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-sm px-6 transition-all shadow-md shadow-blue-500/25 hover:shadow-blue-500/40 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                    </div>
                </form>

                <!-- Quick hint -->
                <p class="text-xs text-slate-400">
                    Example: <span class="font-mono font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">26010023450000</span> (14 digits)
                </p>
            </div>
        </div>
    </section>

    <section class="w-full max-w-5xl mx-auto pb-16">
        <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 py-6 px-6 rounded-2xl bg-white/60 border border-slate-200/60 shadow-sm backdrop-blur-sm">
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <span class="text-base font-bold text-purple-600">💻</span>
                <span class="font-semibold">PBT &amp; CBT Result</span>
            </div>
            
            <span class="hidden sm:block w-px h-5 bg-slate-200"></span>
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span class="font-semibold">Instant NAT-TEST Result</span>
            </div>
            <span class="hidden sm:block w-px h-5 bg-slate-200"></span>
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <span class="font-semibold">Secured information</span>
            </div>
        </div>
    </section>

    <section class="w-full max-w-5xl mx-auto pb-20" id="about-nat-test">
        <div class="bg-white rounded-3xl p-5 sm:p-12 border border-slate-200/80 shadow-sm">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <picture>
                        <source srcset="<?php echo htmlspecialchars($logoWebpUrl ?? 'logo.webp'); ?>" type="image/webp">
                        <img src="<?php echo htmlspecialchars($logoUrl ?? 'logo.png'); ?>" alt="NAT-TEST Logo" width="48" height="48" loading="lazy" decoding="async" class="w-12 h-12 object-contain mx-auto mb-3 drop-shadow-sm">
                    </picture>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 mb-3 uppercase tracking-wider">
                        Japanese Language Proficiency Guide
                    </span>
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight">
                        About NAT-TEST &amp; CBT NAT-TEST in Bangladesh
                    </h2>
                    <p class="text-slate-500 text-sm sm:text-base mt-3 leading-relaxed">
                        Understand the examination structure, certificate validity, and differences between Paper-Based (PBT) and Computer-Based (CBT) NAT-TEST.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                    <!-- PBT Column -->
                    <div class="p-6 rounded-2xl bg-gradient-to-br from-blue-50/50 to-indigo-50/40 border border-blue-100 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-blue-500/20">
                                    PBT
                                </div>
                                <span class="text-xs font-bold bg-blue-100 text-blue-800 px-3 py-1 rounded-full border border-blue-200">
                                    🗓️ Held Every Month
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">Paper-Based Test (NAT-TEST)</h3>
                            <p class="text-sm text-slate-600 leading-relaxed mb-4">
                                The traditional pen-and-paper <strong>NAT-TEST is held every month</strong> in Bangladesh. Examinees answer questions on OMR answer sheets at centralized testing venues including ULSAI Dhaka and affiliated centers.
                            </p>
                        </div>
                        <div class="text-xs font-semibold text-blue-700 bg-blue-50/80 p-2.5 rounded-xl border border-blue-100 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            <span>Frequency: Monthly examinations (N5 to N1)</span>
                        </div>
                    </div>

                    <!-- CBT Column -->
                    <div class="p-6 rounded-2xl bg-gradient-to-br from-purple-50/50 to-pink-50/40 border border-purple-100 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center font-bold shadow-md shadow-purple-500/20">
                                    CBT
                                </div>
                                <span class="text-xs font-bold bg-purple-100 text-purple-800 px-3 py-1 rounded-full border border-purple-200">
                                    ⚡ 3 Slots Daily
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">Computer-Based Test (CBT NAT-TEST)</h3>
                            <p class="text-sm text-slate-600 leading-relaxed mb-4">
                                <strong>CBT NAT-TEST is held every day with 3 slots: Morning, Noon, and Afternoon</strong>. Take the exam on designated computer terminals with rapid computerized evaluation and flexible test date booking.
                            </p>
                        </div>
                        <div class="pt-3 border-t border-purple-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="text-xs font-semibold text-blue-700 bg-blue-50/80 p-2.5 rounded-xl border border-blue-100 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            <span>Slot: Morning, Noon and Afternoon</span>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Levels & Visa Acceptance -->
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80">
                    <h3 class="font-bold text-slate-900 text-base mb-3 flex items-center gap-2">
                        <span class="text-emerald-600 text-lg">✓</span> Recognized for Japanese Visas &amp; Employment
                    </h3>
                    <p class="text-sm text-slate-600 leading-relaxed mb-4">
                        Both <strong>NAT-TEST</strong> and <strong>CBT NAT-TEST</strong> certifications are officially accredited by the Immigration Services Agency of Japan. Whether you are applying for a Japanese student visa, Specified Skilled Worker (SSW), or Technical Intern Training Program (TITP), NAT-TEST results fulfill all standard language criteria.
                    </p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="px-3 py-1 bg-white font-semibold text-slate-700 rounded-lg border border-slate-200">5Q = JLPT N5 (Beginner)</span>
                        <span class="px-3 py-1 bg-white font-semibold text-slate-700 rounded-lg border border-slate-200">4Q = JLPT N4 (Basic)</span>
                        <span class="px-3 py-1 bg-white font-semibold text-slate-700 rounded-lg border border-slate-200">3Q = JLPT N3 (Intermediate)</span>
                        <span class="px-3 py-1 bg-white font-semibold text-slate-700 rounded-lg border border-slate-200">2Q = JLPT N2 (Pre-Advanced)</span>
                        <span class="px-3 py-1 bg-white font-semibold text-slate-700 rounded-lg border border-slate-200">1Q = JLPT N1 (Advanced)</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="w-full max-w-5xl mx-auto pb-20">
        <div class="text-center mb-12">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 mb-3 uppercase tracking-wider">
                Simple Verification
            </span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                How to Check Your NAT-TEST Result
            </h2>
            <p class="text-slate-500 text-sm mt-2 max-w-lg mx-auto">Access your examination records on NAT-TEST BD in three simple steps.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Step 1 -->
            <div class="group relative bg-white rounded-3xl p-7 border border-slate-200/80 shadow-sm hover:shadow-xl hover:shadow-blue-500/5 hover:-translate-y-1 transition-all duration-300 text-center">
                <div class="absolute -top-3 left-6 w-8 h-8 bg-blue-600 text-white rounded-xl flex items-center justify-center text-xs font-black shadow-lg shadow-blue-500/30">1</div>
                <div class="w-14 h-14 mx-auto bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl flex items-center justify-center mb-5 border border-blue-100 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">Enter Examinee Number</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Type your 14-digit registration number found on your NAT-TEST admission ticket (受験票).</p>
            </div>

            <!-- Step 2 -->
            <div class="group relative bg-white rounded-3xl p-7 border border-slate-200/80 shadow-sm hover:shadow-xl hover:shadow-emerald-500/5 hover:-translate-y-1 transition-all duration-300 text-center">
                <div class="absolute -top-3 left-6 w-8 h-8 bg-emerald-600 text-white rounded-xl flex items-center justify-center text-xs font-black shadow-lg shadow-emerald-500/30">2</div>
                <div class="w-14 h-14 mx-auto bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl flex items-center justify-center mb-5 border border-emerald-100 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">View NAT-TEST Result</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Instantly see whether you passed, exam month, level (N5–N1), and test center code.</p>
            </div>

            <!-- Step 3 -->
            <div class="group relative bg-white rounded-3xl p-7 border border-slate-200/80 shadow-sm hover:shadow-xl hover:shadow-amber-500/5 hover:-translate-y-1 transition-all duration-300 text-center">
                <div class="absolute -top-3 left-6 w-8 h-8 bg-amber-500 text-white rounded-xl flex items-center justify-center text-xs font-black shadow-lg shadow-amber-500/30">3</div>
                <div class="w-14 h-14 mx-auto bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl flex items-center justify-center mb-5 border border-amber-100 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">Verify via Email OTP</h3>
                <p class="text-sm text-slate-500 leading-relaxed">Receive a secure 6-digit code to your registered email to reveal confidential full name and DOB.</p>
            </div>
        </div>
    </section>

    <section class="w-full max-w-5xl mx-auto pb-20">
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
                <!-- Left: Visual Decoder -->
                <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-5 sm:p-10 text-white">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-white/10 text-slate-300 border border-white/10 mb-4 uppercase tracking-wider">
                        📋 Reference Guide
                    </span>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-white mb-2 tracking-tight">
                        Examinee Number Format
                    </h2>
                    <p class="text-sm text-slate-400 mb-8 leading-relaxed">
                        Your 14-digit registration number contains all key information about your <strong>NAT-TEST</strong> session:
                    </p>

                    <!-- Visual number breakdown -->
                    <div class="mb-8">
                        <div class="flex flex-wrap justify-center gap-1 text-md sm:text-xl font-bold font-mono mb-3">
                            <span class="bg-blue-500/20 text-blue-300 px-2.5 py-1.5 rounded-lg border border-blue-500/30">26</span>
                            <span class="bg-emerald-500/20 text-emerald-300 px-2.5 py-1.5 rounded-lg border border-emerald-500/30">08</span>
                            <span class="bg-slate-500/20 text-slate-300 px-2.5 py-1.5 rounded-lg border border-slate-500/30">00</span>
                            <span class="bg-amber-500/20 text-amber-300 px-2.5 py-1.5 rounded-lg border border-amber-500/30">474</span>
                            <span class="bg-purple-500/20 text-purple-300 px-2.5 py-1.5 rounded-lg border border-purple-500/30">5</span>
                            <span class="bg-rose-500/20 text-rose-300 px-2.5 py-1.5 rounded-lg border border-rose-500/30">0001</span>
                        </div>
                        <div class="flex flex-wrap justify-center gap-1.5 text-[9px] sm:text-[10px] font-bold uppercase tracking-widest text-slate-500">
                            <span class="w-[44px] text-center">Year</span>
                            <span class="w-[44px] text-center">Month</span>
                            <span class="w-[44px] text-center">Type</span>
                            <span class="w-[52px] text-center">Center</span>
                            <span class="w-[32px] text-center">Level</span>
                            <span class="w-[60px] text-center">Roll</span>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs text-slate-400">
                        <p><span class="text-blue-300 font-bold">Type:</span> <code class="bg-white/10 px-1.5 py-0.5 rounded text-slate-300">00</code> = Paper Test (PBT), <code class="bg-white/10 px-1.5 py-0.5 rounded text-purple-300 font-bold">01–31</code> = <strong>CBT NAT-TEST</strong></p>
                        <p><span class="text-purple-300 font-bold">Level:</span> 5 = N5 (5Q), 4 = N4 (4Q), 3 = N3 (3Q), 2 = N2 (2Q), 1 = N1 (1Q)</p>
                        <p><span class="text-amber-300 font-bold">Center:</span> 474 = ULSAI (Dhaka), 476 = Khulna Center</p>
                    </div>
                </div>

                <!-- Right: Supported Exams -->
                <div class="p-5 sm:p-10">
                    <h3 class="text-lg font-bold text-slate-900 mb-6">Supported Exam Formats</h3>

                    <div class="space-y-4">
                        <!-- PBT -->
                        <div class="flex items-start gap-4 p-4 bg-blue-50/60 rounded-2xl border border-blue-100/80">
                            <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-sm">PBT NAT-TEST (Paper-Based)</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Official examination held <strong>every month</strong> across Bangladesh</p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">N5</span>
                                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">N4</span>
                                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">N3</span>
                                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">N2</span>
                                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">N1</span>
                                </div>
                            </div>
                        </div>

                        <!-- CBT -->
                        <div class="flex items-start gap-4 p-4 bg-purple-50/60 rounded-2xl border border-purple-100/80">
                            <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-sm">CBT NAT-TEST (Computer-Based)</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Held <strong>every day with 3 slots: Morning, Noon &amp; Afternoon</strong></p>
                                <p class="text-[11px] text-purple-700 font-semibold mt-1">
                                    For details &amp; booking: <a href="https://ulsai.com.bd/cbt" target="_blank" rel="noopener noreferrer" class="underline hover:text-purple-900 font-bold">ulsai.com.bd/cbt</a>
                                </p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <span class="text-[10px] font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">N5</span>
                                    <span class="text-[10px] font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">N4</span>
                                    <span class="text-[10px] font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">N3</span>
                                    <span class="text-[10px] font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">N2</span>
                                    <span class="text-[10px] font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">N1</span>
                                </div>
                            </div>
                        </div>

                        <!-- Centers -->
                        <div class="flex items-start gap-4 p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80">
                            <div class="w-11 h-11 bg-slate-100 rounded-xl flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-sm">Authorized Bangladesh Test Centers</h4>
                                <p class="text-xs text-slate-500 mt-0.5">ULSAI (Dhaka CCC 474), Khulna &amp; affiliated exam venues</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="w-full max-w-5xl mx-auto pb-20">
        <div class="text-center mb-12">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 mb-3 uppercase tracking-wider">
                Benefits
            </span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Why Choose NAT-TEST BD?</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Feature 1 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mb-1">Instant Results</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Direct query response under 2 seconds without tedious forms.</p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mb-1">Privacy Protected</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Confidential names and birthdates shielded with 6-digit OTP verification.</p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="w-11 h-11 bg-purple-50 rounded-xl flex items-center justify-center mb-4">
                    <span class="text-lg">💻</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mb-1">CBT &amp; PBT Support</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Unified search covering both paper and computer-based examinations.</p>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="w-11 h-11 bg-rose-50 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mb-1">100% Free</h3>
                <p class="text-xs text-slate-500 leading-relaxed">No sign-up fee, membership requirement, or hidden costs.</p>
            </div>
        </div>
    </section>

    <section class="w-full max-w-3xl mx-auto pb-20" id="about">
        <div class="text-center mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 mb-3 uppercase tracking-wider">
                Frequently Asked Questions
            </span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                NAT-TEST &amp; CBT NAT-TEST FAQs
            </h2>
        </div>

        <div class="space-y-4">
            <!-- FAQ 1 -->
            <details class="group bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" open>
                <summary class="flex items-center justify-between p-5 cursor-pointer select-none hover:bg-slate-50/60 transition-colors">
                    <span class="text-sm font-bold text-slate-900">What is the NAT-TEST?</span>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>
                <div class="px-5 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-4">
                    The <strong>NAT-TEST</strong> (Nihongo Nouryoku Test) is an official Japanese language proficiency exam conducted worldwide by Senmon Kyouiku Shuppan (専門教育出版) in Japan. It evaluates proficiency levels from 5Q (N5 beginner) to 1Q (N1 advanced) and is recognized by Japanese language schools, universities, companies, and immigration authorities.
                </div>
            </details>

            <!-- FAQ 2 -->
            <details class="group bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <summary class="flex items-center justify-between p-5 cursor-pointer select-none hover:bg-slate-50/60 transition-colors">
                    <span class="text-sm font-bold text-slate-900">How often are NAT-TEST and CBT NAT-TEST held in Bangladesh?</span>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>
                <div class="px-5 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-4">
                    <strong>NAT-TEST (Paper-Based) is held every month</strong> across examination centers in Bangladesh. Meanwhile, <strong>CBT NAT-TEST (Computer-Based) is held every day with 3 daily slots: Morning, Noon, and Afternoon</strong>. For complete test guidelines, available seats, and registration details, please visit <a href="https://ulsai.com.bd/cbt" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 font-bold underline">ulsai.com.bd/cbt</a>.
                </div>
            </details>

            <!-- FAQ 3 -->
            <details class="group bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <summary class="flex items-center justify-between p-5 cursor-pointer select-none hover:bg-slate-50/60 transition-colors">
                    <span class="text-sm font-bold text-slate-900">How can I check my NAT-TEST Result on NAT-TEST BD?</span>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>
                <div class="px-5 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-4">
                    Simply enter your 14-digit examinee registration number into the search box on <strong>NAT-TEST BD</strong>. You will instantly see your examination format (PBT or <strong>CBT NAT-TEST</strong>), level, exam month, center code, and official Pass/Fail result status.
                </div>
            </details>

            <!-- FAQ 4 -->
            <details class="group bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <summary class="flex items-center justify-between p-5 cursor-pointer select-none hover:bg-slate-50/60 transition-colors">
                    <span class="text-sm font-bold text-slate-900">Where do I find my 14-digit examinee number?</span>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>
                <div class="px-5 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-4">
                    Your 14-digit registration number is printed on your NAT-TEST admission ticket (受験票). It is also sent to you via email or SMS by your exam center or language institute. The standard format is: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono text-slate-700">YYMMTTCCCL XXXX</code>.
                </div>
            </details>

            <!-- FAQ 5 -->
            <details class="group bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <summary class="flex items-center justify-between p-5 cursor-pointer select-none hover:bg-slate-50/60 transition-colors">
                    <span class="text-sm font-bold text-slate-900">Are NAT-TEST certificates valid for Japanese student and work visas?</span>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </summary>
                <div class="px-5 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-4">
                    Yes. Certificates earned by passing <strong>NAT-TEST</strong> or <strong>CBT NAT-TEST</strong> are officially certified by the Immigration Services Agency of Japan for student visas, Specified Skilled Worker (SSW 1 &amp; 2) visas, and Technical Intern Training.
                </div>
            </details>
        </div>
    </section>


    <section class="w-full max-w-3xl mx-auto pb-8">
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-3xl p-8 sm:p-12 text-center shadow-2xl shadow-slate-900/20">
            <div class="absolute -right-10 -top-10 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#ffffff_0.5px,transparent_0.5px)] [background-size:20px_20px] opacity-[0.04] pointer-events-none"></div>

            <div class="relative z-10">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-blue-500/30">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white mb-3 tracking-tight">Ready to Check Your NAT-TEST Result?</h2>
                <p class="text-slate-400 text-sm sm:text-base mb-7 max-w-md mx-auto">Enter your examinee number and find out your official PBT or CBT NAT-TEST result right now.</p>
                <a href="/result" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm px-8 py-3.5 rounded-xl transition-all shadow-lg shadow-blue-600/30 hover:shadow-blue-500/40 hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                    Check NAT-TEST Result Now
                </a>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/common/footer.php'; ?>