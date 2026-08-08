<?php
// PHP SaaS Landing Page with Secure Onboarding Form & Pricing Section
// Auto theme: light for day, dark for night

require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/subdomain.php';

// ── Subdomain routing ───────────────────────────────────────────────────────
// School subdomains and platform subdomain should never land here
$ctx = resolve_subdomain($pdo);
if (!$ctx['is_root']) {
    header('Location: login.php');
    exit;
}

// Auto theme detection based on Nigeria time
$hour = (int)date('H');
$theme_mode = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
$html_class = $theme_mode === 'dark' ? 'dark' : '';

// ── Fetch packages ──────────────────────────────────────────────────────────
$packages = [];
try {
    $packages = $pdo->query("SELECT * FROM pricing_packages ORDER BY monthly_price ASC")->fetchAll();
} catch (Exception $e) {
    $packages = [];
}

// Branding
$brand_name = 'Zetaphase EduCloud';
$brand_sub = 'zetaphase.com.ng';
$brand_logo = 'logo.jpeg';

// Theme CSS variables
$bg_primary   = $theme_mode === 'light' ? '#FFFFFF' : '#0E1117';
$bg_secondary = $theme_mode === 'light' ? '#F8FAFC' : '#11141B';
$bg_tertiary  = $theme_mode === 'light' ? '#F1F5F9' : '#0A0D12';
$border_color = $theme_mode === 'light' ? '#E2E8F0' : '#1E232D';
$text_primary = $theme_mode === 'light' ? '#0F172A' : '#F1F5F9';
$text_secondary = $theme_mode === 'light' ? '#475569' : '#94A3B8';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth <?php echo $html_class; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($brand_name); ?> - Unified Cloud School OS</title>
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo htmlspecialchars($brand_logo); ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <script src="assets/js/lucide.min.js"></script>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen selection:bg-indigo-500 selection:text-white">
<style>
    /* Override Tailwind's color variables with your dynamic values */
    :root {
        /* Tailwind's color system — overridden with your dynamic theme */
        --color-white: <?php echo $theme_mode === 'dark' ? '#0E1117' : '#FFFFFF'; ?>;
        --color-slate-50: <?php echo $theme_mode === 'dark' ? '#0A0D12' : '#F8FAFC'; ?>;
        --color-slate-100: <?php echo $theme_mode === 'dark' ? '#0E1117' : '#F1F5F9'; ?>;
        --color-slate-200: <?php echo $theme_mode === 'dark' ? '#1E232D' : '#E2E8F0'; ?>;
        --color-slate-300: <?php echo $theme_mode === 'dark' ? '#2D333B' : '#CBD5E1'; ?>;
        --color-slate-400: <?php echo $theme_mode === 'dark' ? '#94A3B8' : '#94A3B8'; ?>;
        --color-slate-500: <?php echo $theme_mode === 'dark' ? '#64748B' : '#64748B'; ?>;
        --color-slate-600: <?php echo $theme_mode === 'dark' ? '#475569' : '#475569'; ?>;
        --color-slate-700: <?php echo $theme_mode === 'dark' ? '#334155' : '#334155'; ?>;
        --color-slate-800: <?php echo $theme_mode === 'dark' ? '#1E293B' : '#1E293B'; ?>;
        --color-slate-900: <?php echo $theme_mode === 'dark' ? '#0F172A' : '#0F172A'; ?>;
        --color-slate-950: <?php echo $theme_mode === 'dark' ? '#020617' : '#020617'; ?>;
        --color-black: <?php echo $theme_mode === 'dark' ? '#0E1117' : '#000000'; ?>;
        
        /* Your custom variables for your own classes */
        --bg-primary: <?php echo $bg_primary; ?>;
        --bg-secondary: <?php echo $bg_secondary; ?>;
        --bg-tertiary: <?php echo $bg_tertiary; ?>;
        --border-color: <?php echo $border_color; ?>;
        --text-primary: <?php echo $text_primary; ?>;
        --text-secondary: <?php echo $text_secondary; ?>;
        --brand-color: <?php echo htmlspecialchars($brand_color); ?>;
    }
    
    /* Your existing utility classes */
    .brand-accent { color: var(--brand-color); }
    .brand-bg { background-color: var(--brand-color); }
    .bg-primary { background-color: var(--bg-primary); }
    .bg-secondary { background-color: var(--bg-secondary); }
    .bg-tertiary { background-color: var(--bg-tertiary); }
    .border-theme { border-color: var(--border-color); }
    .text-primary { color: var(--text-primary); }
    .text-secondary { color: var(--text-secondary); }
    .selection\:bg-indigo-500 ::selection { background-color: #6366f1; }
    .selection\:text-white ::selection { color: #ffffff; }
</style>
    <!-- Header Navigation -->
    <header class="border-b border-[var(--border-color)] bg-[var(--bg-secondary)]/75 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="<?php echo htmlspecialchars($brand_logo); ?>" alt="Logo" class="w-9 h-9 rounded-xl object-cover border border-[var(--border-color)] shadow-lg"
                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/20" style="display:none;">
                    <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <span class="font-extrabold text-[var(--text-primary)] text-base tracking-tight">Zetaphase EduCloud</span>
                    <span class="text-[10px] text-indigo-400 block font-mono">zetaphase.com.ng</span>
                </div>
            </div>

            <nav class="hidden md:flex items-center space-x-8 text-xs font-semibold text-[var(--text-secondary)]">
                <a href="#overview" class="hover:text-[var(--text-primary)] transition-colors">Core Walkthrough</a>
                <a href="#pricing" class="hover:text-[var(--text-primary)] transition-colors">Pricing Tiers</a>
                <a href="#onboarding-request" class="hover:text-[var(--text-primary)] transition-colors">Deploy School</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="max-w-5xl mx-auto px-6 pt-16 pb-20 text-center space-y-8">
        <div class="inline-flex items-center space-x-2 bg-indigo-500/10 border border-indigo-500/20 px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-400">
            <i data-lucide="sparkles" class="w-4 h-4"></i>
            <span>Next-Generation Nigerian Educational Infrastructure</span>
        </div>

        <h1 class="text-4xl sm:text-6xl font-extrabold text-[var(--text-primary)] tracking-tight leading-[1.1]">
            Enterprise-Grade Cloud OS for <br />
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400">
                Modern Schools & Academies
            </span>
        </h1>

        <p class="text-xs sm:text-sm text-[var(--text-secondary)] max-w-2xl mx-auto leading-relaxed">
            A comprehensive, secure cloud-based School Management System featuring granular staff permission controls, automated billing invoices, computer-based testing, and isolated tenant environments.
        </p>

        <div class="flex flex-wrap justify-center gap-4 pt-4">
            <a href="#pricing" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-indigo-600/30">
                View Pricing Plans
            </a>
            <a href="#onboarding-request" class="px-6 py-3 bg-[var(--bg-tertiary)] hover:bg-[var(--bg-secondary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl transition-all border border-[var(--border-color)]">
                Deploy Your School
            </a>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-16 px-6 max-w-7xl mx-auto border-t border-[var(--border-color)] space-y-12">
        <div class="text-center space-y-3">
            <span class="text-[10px] font-bold text-indigo-400 bg-indigo-500/10 px-2.5 py-1 rounded-md uppercase tracking-wider font-mono">
                Flexible Subscription Packages
            </span>
            <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)] tracking-tight">Institutional Pricing Tiers</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php if (!empty($packages)): ?>
                <?php foreach ($packages as $pkg): ?>
                    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 flex flex-col justify-between space-y-6 shadow-xl relative overflow-hidden">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($pkg['tier_name']); ?> Tier</h3>
                                <span class="text-[10px] font-mono bg-indigo-500/10 text-indigo-400 px-2.5 py-1 rounded-md">Up to <?php echo $pkg['max_students']; ?> Students</span>
                            </div>
                            <p class="text-xs text-[var(--text-secondary)] leading-relaxed"><?php echo htmlspecialchars($pkg['description']); ?></p>
                            
                            <div class="pt-2 pb-4 border-b border-[var(--border-color)]">
                                <span class="text-3xl font-extrabold text-[var(--text-primary)] font-mono">₦<?php echo number_format($pkg['monthly_price']); ?></span>
                                <span class="text-xs text-[var(--text-secondary)]"> / month</span>
                                <div class="text-[10px] text-[var(--text-secondary)] mt-1 font-mono">Or ₦<?php echo number_format($pkg['yearly_price']); ?> / year </div>
                            </div>

                            <div class="space-y-2.5">
                                <span class="text-[10px] font-bold text-[var(--text-secondary)] uppercase font-mono block">Included Features:</span>
                                <ul class="space-y-2 text-xs text-[var(--text-primary)]">
                                    <?php 
                                    $features = json_decode($pkg['features_json'], true) ?: [];
                                    foreach ($features as $feat): 
                                    ?>
                                        <li class="flex items-center space-x-2">
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400 shrink-0"></i>
                                            <span><?php echo htmlspecialchars($feat); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <a href="#onboarding-request" onclick="selectPlan('<?php echo htmlspecialchars($pkg['tier_name']); ?>')" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition-all shadow-md text-center block">
                            Select <?php echo htmlspecialchars($pkg['tier_name']); ?> Plan
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-3 text-center text-xs text-[var(--text-secondary)] py-8" style="display:none">Pricing packages database loading or not initialized.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Walkthrough -->
    <section id="overview" class="py-16 px-6 max-w-7xl mx-auto border-t border-[var(--border-color)] space-y-12">
        <div class="text-center space-y-3">
            <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">Platform Walkthrough</h2>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)] max-w-2xl mx-auto">
                Review how Zetaphase EduCloud models school operations with secure isolation, school admin control, and staff permissions.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center bg-[var(--bg-secondary)]/40 border border-[var(--border-color)] p-8 rounded-2xl">
            <div class="lg:col-span-5 space-y-4">
                <span class="text-[10px] font-bold text-indigo-400 bg-indigo-500/10 px-2.5 py-1 rounded-md uppercase tracking-wider font-mono">
                    01. School Admin & Staff Access
                </span>
                <h3 class="text-xl font-bold text-[var(--text-primary)]">Granular Staff Feature Permissions</h3>
                <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                    Each school has a designated <strong>School Admin</strong> who holds full operational control and grants selective feature access to other staff members.
                </p>
                <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                    Any feature or module not granted to a staff member is automatically hidden from their sidebar, dashboard, and navigation.
                </p>
            </div>
            <div class="lg:col-span-7 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-5 space-y-4 font-mono text-xs text-[var(--text-secondary)]">
                <div class="flex items-center space-x-2 pb-2 border-b border-[var(--border-color)]">
                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                    <span class="font-semibold text-[var(--text-primary)]">Secure .com.ng Subdomain Resolver</span>
                </div>
                <div class="space-y-2">
                    <p class="text-[var(--text-secondary)]">// Tenant Isolation & Billing Engine</p>
                    <div class="bg-[var(--bg-secondary)] p-3 rounded-lg border border-[var(--border-color)] space-y-1">
                        <p><span class="text-purple-400">HTTP/1.1 GET</span> <span class="text-indigo-400">https://standrews.zetaphase.com.ng</span></p>
                        <p class="text-emerald-400">→ Secure tenant resolved successfully</p>
                        <p class="text-[var(--text-primary)]">Active Package: <span class="text-indigo font-bold">Standard Tier</span></p>
                        <p class="text-[var(--text-primary)]">Billing Auto-Invoice: <span class="text-emerald-400 font-bold">Active & Synchronized</span></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Onboarding Request Section -->
    <section id="onboarding-request" class="py-16 px-6 max-w-4xl mx-auto border-t border-[var(--border-color)] space-y-10">
        <div class="text-center space-y-3">
            <span class="text-[10px] font-bold text-indigo-400 bg-indigo-500/10 px-2.5 py-1 rounded-md uppercase tracking-wider font-mono">
                Deploy For Your School
            </span>
            <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)] tracking-tight">Request Platform Activation</h2>
            <p class="text-xs text-[var(--text-secondary)] max-w-lg mx-auto leading-relaxed">
                Fill out the secure onboarding form below to deploy your isolated Zetaphase EduCloud (.com.ng) instance.
            </p>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
            <!-- Loading Indicator -->
            <div id="loadingOverlay" class="absolute inset-0 bg-[var(--bg-secondary)]/90 backdrop-blur-sm flex items-center justify-center hidden z-50">
                <div class="text-center space-y-3">
                    <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs text-[var(--text-secondary)] font-bold">Registering Application securely...</p>
                </div>
            </div>

            <!-- Success Alert State -->
            <div id="successState" class="hidden text-center py-8 space-y-4">
                <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-[var(--text-primary)]">Request Successfully Submitted!</h3>
                <p class="text-xs text-[var(--text-secondary)] max-w-md mx-auto leading-relaxed">
                    Your school activation application has been registered securely on zetaphase.com.ng. Our administration team will review your requested system configuration and setup details. You will receive an email confirmation with login credentials once deployed.
                </p>
                <button type="button" onclick="resetForm()" class="px-4 py-2 bg-[var(--bg-tertiary)] hover:bg-[var(--bg-secondary)] text-[var(--text-secondary)] text-xs font-bold rounded-lg transition-all">
                    Submit Another Request
                </button>
            </div>

            <!-- Onboarding Form -->
            <form id="onboardingForm" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">School Name</label>
                        <input type="text" name="school_name" required placeholder="e.g. St. Andrews Academy" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 placeholder-[var(--text-secondary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Requested Domain</label>
                        <div class="flex items-center">
                            <input type="text" name="subdomain" required placeholder="e.g. standrews" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-l-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 placeholder-[var(--text-secondary)] transition-all font-mono">
                            <span class="bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)] border-l-0 rounded-r-xl px-3 py-3 text-xs text-[var(--text-secondary)] font-mono">.zetaphase.com.ng</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Contact Full Name</label>
                        <input type="text" name="contact_name" required placeholder="e.g. Dr. Emmanuel Vance" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 placeholder-[var(--text-secondary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Your Role in School</label>
                        <select name="applicant_role" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all">
                            <option value="School Admin" selected>School Admin / Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Email Address</label>
                        <input type="email" name="email" required placeholder="principal@yourschool.ng" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 placeholder-[var(--text-secondary)] transition-all font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Phone Number</label>
                        <input type="tel" name="phone" required placeholder="+234 803 000 0000" 
                        pattern="[0-9]+" 
                        title="Please enter numbers only"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 placeholder-[var(--text-secondary)] transition-all font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Subscription Plan Tier</label>
                        <select name="plan" id="planSelect" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all">
                            <option value="Basic">Basic Tier (₦25,000/mo)</option>
                            <option value="Standard" selected>Standard Tier (₦65,000/mo)</option>
                            <option value="Pro">Pro Enterprise Tier (₦150,000/mo)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Billing Cycle</label>
                        <select name="billing_cycle" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all">
                            <option value="Monthly">Monthly Billing</option>
                            <option value="Yearly">Yearly Billing</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Estimated Student Capacity</label>
                        <select name="student_count" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all">
                            <option value="150">1 - 150 students</option>
                            <option value="350" selected>151 - 500 students</option>
                            <option value="1000">501 - 1000 students</option>
                            <option value="2000">1000+ students</option>
                        </select>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-indigo-600/30 flex items-center justify-center space-x-2">
                        <span>Submit Secure Activation Request</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-[var(--border-color)] bg-[var(--bg-secondary)]/30 py-8 px-6 text-center text-xs text-[var(--text-secondary)]">
        <p>© 2026 Zetaphase EduCloud. All rights reserved.</p>
    </footer>

    <!-- Javascript Handlers -->
    <script>
        lucide.createIcons();

        function selectPlan(tierName) {
            const select = document.getElementById('planSelect');
            if (select) {
                for (let opt of select.options) {
                    if (opt.value === tierName) {
                        opt.selected = true;
                        break;
                    }
                }
            }
        }

        const form = document.getElementById('onboardingForm');
        const loading = document.getElementById('loadingOverlay');
        const success = document.getElementById('successState');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            loading.classList.remove('hidden');

            const formData = new FormData(form);
            try {
                const response = await fetch('api/submit-onboarding.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                loading.classList.add('hidden');
                if (result.success) {
                    form.classList.add('hidden');
                    success.classList.remove('hidden');
                } else {
                    alert('Submission Error: ' + result.message);
                }
            } catch (error) {
                loading.classList.add('hidden');
                alert('Database submission failed. Ensure Apache and MySQL are running in your XAMPP Control Panel!');
            }
        });

        function resetForm() {
            form.reset();
            form.classList.remove('hidden');
            success.classList.add('hidden');
        }
    </script>
</body>
</html>