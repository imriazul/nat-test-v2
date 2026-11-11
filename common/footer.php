    </div>
    <!-- End Main Content Wrapper -->

    <footer class="relative bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 text-slate-300 overflow-hidden">
        <!-- Decorative top edge -->
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/40 to-transparent"></div>

        <!-- Subtle grid texture -->
        <div class="absolute inset-0 bg-[radial-gradient(#ffffff_0.5px,transparent_0.5px)] [background-size:24px_24px] opacity-[0.03] pointer-events-none"></div>

        <!-- Main Footer Content -->
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-10">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-10 md:gap-8">

                <!-- Column 1: Brand & Description -->
                <div class="md:col-span-5">
                    <div class="flex items-center gap-3 mb-4">
                        <picture>
                            <source srcset="<?php echo htmlspecialchars($logoWebpUrl ?? 'logo.webp'); ?>" type="image/webp">
                            <img src="<?php echo htmlspecialchars($logoUrl ?? 'logo.png'); ?>" alt="NAT-TEST BD Logo" width="40" height="40" loading="lazy" decoding="async" class="w-10 h-10 object-contain drop-shadow">
                        </picture>
                        <div>
                            <span class="text-lg font-extrabold text-white tracking-tight">NAT-TEST</span>
                            <span class="text-lg font-extrabold text-blue-400 tracking-tight ml-0.5">BD</span>
                        </div>
                    </div>
                    <p class="text-sm text-slate-400 leading-relaxed mb-5 max-w-sm">
                        The dedicated <strong>NAT-TEST Result</strong> checking portal for Bangladesh. 
                        Verify your official scores for both <strong>PBT</strong> and <strong>CBT NAT-TEST</strong> Japanese language examinations quickly and securely with <strong>NAT-TEST BD</strong>.
                    </p>
                    <!-- Social / Contact mini-icons -->
                    <div class="flex items-center gap-2">
                        <a href="mailto:support@nat-test.bd" class="w-9 h-9 rounded-lg bg-slate-800 hover:bg-blue-600 flex items-center justify-center text-slate-400 hover:text-white transition-all duration-200 group" title="Email us">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </a>
                        <a href="#" class="w-9 h-9 rounded-lg bg-slate-800 hover:bg-blue-600 flex items-center justify-center text-slate-400 hover:text-white transition-all duration-200 group" title="Facebook">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path></svg>
                        </a>
                    </div>
                </div>

                <!-- Column 2: Quick Links -->
                <div class="md:col-span-3">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-4">Quick Links</h3>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="/" class="text-sm text-slate-400 hover:text-white transition-colors duration-200 flex items-center gap-2 group">
                                <span class="w-1 h-1 rounded-full bg-slate-600 group-hover:bg-blue-500 transition-colors"></span>
                                Home
                            </a>
                        </li>
                        <li>
                            <a href="result" class="text-sm text-slate-400 hover:text-white transition-colors duration-200 flex items-center gap-2 group">
                                <span class="w-1 h-1 rounded-full bg-slate-600 group-hover:bg-blue-500 transition-colors"></span>
                                Check Result
                            </a>
                        </li>
                        <li>
                            <a href="/#about" class="text-sm text-slate-400 hover:text-white transition-colors duration-200 flex items-center gap-2 group">
                                <span class="w-1 h-1 rounded-full bg-slate-600 group-hover:bg-blue-500 transition-colors"></span>
                                About NAT-TEST
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Column 3: Exam Info -->
                <div class="md:col-span-4">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-4">Exam Information</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-slate-300">Levels Supported</span>
                                <p class="text-xs text-slate-500">N5, N4, N3, N2, N1 (PBT &amp; CBT)</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-slate-300">Exam Centers</span>
                                <p class="text-xs text-slate-500">ULSAI (Dhaka), Khulna &amp; more</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-slate-300">Secure Verification</span>
                                <p class="text-xs text-slate-500">Email OTP for personal data access</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="mt-12 pt-7 border-t border-slate-800/80">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <!-- Copyright -->
                    <p class="text-xs text-slate-500 text-center sm:text-left">
                       Copyright &copy; <?php echo date('Y'); ?> <span class="font-semibold text-slate-400">NAT-TEST BD</span>. All rights reserved.
                    </p>

                    <!-- Powered by & misc -->
                    <div class="flex items-center gap-3 text-xs text-slate-600">
                        <span class="flex items-center gap-1.5">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                            </span>
                            System Online
                        </span>
                        <span class="text-slate-700">|</span>
                        <span>&#x1F1E7;&#x1F1E9; Bangladesh</span>
                        <span class="text-slate-700">|</span>
                        <span>&#x1F1EF;&#x1F1F5; 日本語能力試験</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Decorative bottom ambient glow -->
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-96 h-32 bg-blue-600/5 rounded-full blur-3xl pointer-events-none"></div>
    </footer>

</body>
</html>