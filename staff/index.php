<?php
/**
 * Zetaphase EduCloud — Staff Portal Entry Point
 *
 * Standalone module for staff (Teacher, Bursar, and other non-admin roles):
 * own URL, own front door, decoupled from admin/dashboard.php. Shares the
 * same session/schema/feature-flag/data-hoisting bootstrap as the admin
 * dashboard (lib/dashboard-bootstrap.php) so the two entry points can never
 * drift out of sync on schema migrations or permission logic — but renders
 * through this file, not admin/dashboard.php.
 *
 * Admin-only management screens (School Settings & Theme, Staff & HR
 * Directory / feature-access assignment, Sessions & Classes structure,
 * Condition of Service / School Policy) are hard-locked out at the
 * bootstrap level regardless of role — see the admin-only section check in
 * lib/dashboard-bootstrap.php. That lock applies here identically to
 * admin/dashboard.php, so it can't be bypassed by hitting this URL directly.
 *
 * Every other section (attendance, results, CBT, lesson plans, assignments,
 * timetable, and anything else a School Admin has granted this staff
 * member) continues to work exactly as it already does via the existing
 * per-feature access system (getFeatureAccessLevel) — nothing about that
 * was narrowed here.
 */
define('ADMIN_DIR', __DIR__ . '/../admin');
require_once ADMIN_DIR . '/lib/dashboard-bootstrap.php';

// Defense in depth: this front door is for staff, so if a School Admin or
// Platform Manager account somehow lands here, send them to their own
// dashboard instead of rendering the staff shell around admin content.
if (in_array($active_role, ['School Admin', 'Platform Manager'], true)) {
    header('Location: ../admin/dashboard.php' . ($section !== 'overview' ? ('?section=' . urlencode($section)) : ''));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $tm; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school['name'] ?? 'School Dashboard'); ?> — Staff Portal</title>
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo htmlspecialchars($brand_logo); ?>">
    <link rel="stylesheet" href="/../assets/css/style.css">
    <script src="/../assets/js/lucide.min.js"></script>
    <style>
        :root{
            <?php echo accent_shade_vars($tc); ?>
            --bg-primary:<?php echo $tm==='light'?'#FFFFFF':'#0E1117';?>;
            --bg-secondary:<?php echo $tm==='light'?'#F8FAFC':'#11141B';?>;
            --bg-tertiary:<?php echo $tm==='light'?'#F1F5F9':'#0A0D12';?>;
            --border-color:<?php echo $tm==='light'?'#E2E8F0':'#1E232D';?>;
            --text-primary:<?php echo $tm==='light'?'#0F172A':'#F1F5F9';?>;
            --text-secondary:<?php echo $tm==='light'?'#475569':'#94A3B8';?>;
        }
        .brand-accent{color:var(--brand-color);}
        .brand-bg{background-color:var(--brand-color);}
        .bg-primary{background-color:var(--bg-primary);}
        .bg-secondary{background-color:var(--bg-secondary);}
        .bg-tertiary{background-color:var(--bg-tertiary);}
        .border-theme{border-color:var(--border-color);}
        .text-primary{color:var(--text-primary);}
        .text-secondary{color:var(--text-secondary);}
        
        /* Fixed Header - Top of screen */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 40;
            height: 70px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }
        
        /* Fixed Sidebar - Full height with bottom fixed */
        .fixed-sidebar {
            position: fixed;
            top: 70px; /* Below header */
            left: 0;
            bottom: 0;
            width: 256px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            z-index: 30;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
            overflow: hidden;
        }
        
        .fixed-sidebar.hidden {
            transform: translateX(-100%);
            opacity: 0;
            width: 0;
            border-right: none;
        }
        
        /* Scrollable sidebar content */
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        }
        
        /* Fixed bottom section of sidebar */
        .sidebar-bottom {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
            flex-shrink: 0;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 256px;
            margin-top: 70px;
            padding: 1.5rem 2rem;
            min-height: calc(100vh - 70px);
            background: var(--bg-primary);
            transition: margin-left 0.3s ease-in-out;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
            }
            .fixed-sidebar {
                transform: translateX(-100%);
            }
            .fixed-sidebar:not(.hidden) {
                transform: translateX(0);
            }
        }
        
        /* Scrollbar styling */
        .sidebar-content::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-content::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-content::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }
        
        /* Main content scroll */
        .main-content {
            overflow-y: auto;
            max-height: calc(100vh - 70px);
        }
    </style>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen font-sans selection:bg-indigo-500 selection:text-white">
    
    <!-- Fixed Header -->
    <div class="fixed-header">
        <?php $sb_base_url = 'index.php'; include_once ADMIN_DIR . '/components/header.php'; ?>
    </div>

    <!-- Fixed Sidebar -->
    <aside class="fixed-sidebar" id="mainSidebar">
        <?php include_once ADMIN_DIR . '/components/sidebar.php'; ?>
    </aside>

    <!-- Main Content - Scrollable -->
    <main class="main-content" id="mainContent">
        <?php if (!empty($success_msg)): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-xl text-xs text-emerald-400 flex items-center gap-2 shadow-lg mb-4">
            <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
        <div class="bg-rose-500/10 border border-rose-500/20 p-4 rounded-xl text-xs text-rose-400 flex items-center gap-2 shadow-lg mb-4">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
        <?php endif; ?>

        <?php
        // ── 7. Section router ─────────────────────────────────────────
        $section_file = ADMIN_DIR . '/sections/' . $section . '.php';
        if (file_exists($section_file)) {
            include $section_file;
        } else {
            include ADMIN_DIR . '/sections/overview.php';
        }
        ?>
    </main>

    <script>
    lucide.createIcons();

    function filterDropdown(inputId, selectId) {
        const f = document.getElementById(inputId).value.toUpperCase();
        const s = document.getElementById(selectId);
        for (let i = 0; i < s.options.length; i++)
            s.options[i].style.display = s.options[i].text.toUpperCase().includes(f) ? '' : 'none';
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape')
            document.querySelectorAll('.fixed.z-50').forEach(m => m.classList.add('hidden'));
    });

    document.querySelectorAll('.fixed.z-50').forEach(modal => {
        modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });
    });
    
    // Sidebar toggle for mobile and desktop
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('mainSidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleMobile = document.getElementById('sidebarToggle');
        const toggleDesktop = document.getElementById('sidebarToggleDesktop');
        
        // Check if sidebar is already hidden (from localStorage)
        const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
        if (sidebarHidden && window.innerWidth >= 768) {
            sidebar.classList.add('hidden');
            if (toggleDesktop) {
                toggleDesktop.querySelector('i').setAttribute('data-lucide', 'panel-right');
            }
            mainContent.classList.add('expanded');
            lucide.createIcons();
        }
        
        // Mobile toggle
        if (toggleMobile && sidebar) {
            toggleMobile.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('hidden')) {
                    icon.setAttribute('data-lucide', 'panel-right');
                    mainContent.classList.add('expanded');
                } else {
                    icon.setAttribute('data-lucide', 'menu');
                    mainContent.classList.remove('expanded');
                }
                lucide.createIcons();
            });
        }
        
        // Desktop toggle
        if (toggleDesktop && sidebar) {
            toggleDesktop.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                localStorage.setItem('sidebarHidden', sidebar.classList.contains('hidden'));
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('hidden')) {
                    icon.setAttribute('data-lucide', 'panel-right');
                    mainContent.classList.add('expanded');
                } else {
                    icon.setAttribute('data-lucide', 'panel-left');
                    mainContent.classList.remove('expanded');
                }
                lucide.createIcons();
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth < 768) {
                if (toggleDesktop) toggleDesktop.classList.add('hidden');
                if (toggleMobile) toggleMobile.classList.remove('hidden');
                if (!sidebar.classList.contains('hidden')) {
                    sidebar.classList.add('hidden');
                    mainContent.classList.add('expanded');
                }
            } else {
                if (toggleDesktop) toggleDesktop.classList.remove('hidden');
                if (toggleMobile) toggleMobile.classList.add('hidden');
                
                const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
                if (sidebarHidden) {
                    sidebar.classList.add('hidden');
                    if (toggleDesktop) {
                        toggleDesktop.querySelector('i').setAttribute('data-lucide', 'panel-right');
                    }
                    mainContent.classList.add('expanded');
                } else {
                    sidebar.classList.remove('hidden');
                    if (toggleDesktop) {
                        toggleDesktop.querySelector('i').setAttribute('data-lucide', 'panel-left');
                    }
                    mainContent.classList.remove('expanded');
                }
                lucide.createIcons();
            }
        });
        
        // Trigger resize on load
        window.dispatchEvent(new Event('resize'));
    });
    </script>
</body>
