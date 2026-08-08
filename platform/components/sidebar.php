<?php
$current_page = $_GET['page'] ?? 'tenants';
$platform_subdomain = $platform_subdomain ?? 'platform.zetaphase.com.ng';
$platform_logo = $platform_logo ?? '../logo.jpeg';
$platform_name = $platform_name ?? 'Zetaphase EduCloud';
?>
<aside class="w-64 bg-[var(--bg-secondary)] border-r border-[var(--border-color)] shrink-0 hidden md:flex flex-col fixed top-0 left-0 h-screen overflow-y-auto z-30 transition-all duration-300"
     style="height: 100vh; max-height: 100vh;">
    <div class="p-6 space-y-6 flex-1">
        <div class="flex items-center space-x-3 pb-5 border-b border-[var(--border-color)]/60">
            <img src="<?php echo htmlspecialchars($platform_logo); ?>" alt="Logo" class="w-8 h-8 rounded-lg object-cover border border-[var(--border-color)]"
                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg\'><i data-lucide=\'shield\' class=\'w-4 h-4 text-white\'></i></div>'; lucide.createIcons();">
            <div>
                <span class="text-[9px] text-indigo-400 font-mono">Platform Manager</span>
            </div>
        </div>
        <div class="space-y-1">
            <span class="text-[9px] font-bold text-[var(--text-secondary)] uppercase tracking-widest px-3 mb-2 font-mono">SaaS Navigation</span>
            <a href="index.php?page=tenants" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all <?php echo ($current_page === 'tenants') ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'; ?>">
                <i data-lucide="building" class="w-4 h-4"></i><span>School Tenants</span>
            </a>
            <a href="index.php?page=requests" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all <?php echo ($current_page === 'requests') ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'; ?>">
                <i data-lucide="user-plus" class="w-4 h-4"></i><span>Onboarding Requests</span>
            </a>
            <a href="index.php?page=pricing" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all <?php echo ($current_page === 'pricing') ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'; ?>">
                <i data-lucide="tag" class="w-4 h-4"></i><span>Pricing & Packages</span>
            </a>
            <a href="index.php?page=billing" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all <?php echo ($current_page === 'billing') ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'; ?>">
                <i data-lucide="receipt" class="w-4 h-4"></i><span>Billing & Invoices</span>
            </a>
            <a href="index.php?page=result_slip_builder" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all <?php echo ($current_page === 'result_slip_builder') ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'; ?>">
                <i data-lucide="layout-template" class="w-4 h-4"></i><span>Result Slip Templates</span>
            </a>
            <a href="index.php?page=audit" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all <?php echo ($current_page === 'audit') ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'; ?>">
                <i data-lucide="shield" class="w-4 h-4"></i><span>Audit Logs</span>
            </a>
        </div>
    </div>
    <div class="p-4 border-t border-[var(--border-color)] bg-[var(--bg-secondary)] sticky bottom-0">
        <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl border border-[var(--border-color)] space-y-2">
            <span class="text-[10px] font-bold text-emerald-400 font-mono"><?php echo htmlspecialchars($platform_subdomain); ?></span>
            <p class="text-[9px] text-[var(--text-secondary)]">Platform Manager session active</p>
        </div>
    </div>
</aside>