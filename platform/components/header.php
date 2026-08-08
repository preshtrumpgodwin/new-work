<?php
/**
 * Platform Manager Header
 * Dynamic theme switcher with time-based auto mode
 */

$current_page = $_GET['page'] ?? 'tenants';
$theme_mode = $_SESSION['platform_theme'] ?? 'auto';

// Determine current display mode
if ($theme_mode === 'auto') {
    $hour = (int)date('H');
    $display_mode = ($hour >= 6 && $hour < 18) ? 'Light' : 'Dark';
} else {
    $display_mode = ucfirst($theme_mode);
}

// Handle session messages (flash messages)
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
$flash_warning = $_SESSION['flash_warning'] ?? null;
$flash_info = $_SESSION['flash_info'] ?? null;

// Clear messages after displaying
if ($flash_success) unset($_SESSION['flash_success']);
if ($flash_error) unset($_SESSION['flash_error']);
if ($flash_warning) unset($_SESSION['flash_warning']);
if ($flash_info) unset($_SESSION['flash_info']);
?>
<header class="border-b border-[var(--border-color)] bg-[var(--bg-secondary)]/75 backdrop-blur-md sticky top-0 z-40">
    <div class="px-6 py-3.5 flex items-center justify-between relative">
        
        <!-- Left Section: Sidebar Toggle + Logo Branding -->
        <div class="flex items-center space-x-3.5">
            <!-- Sidebar Toggle Button (Mobile & Desktop) -->
            <button id="sidebarToggle" 
                    class="w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all md:hidden"
                    aria-label="Toggle sidebar">
                <i data-lucide="menu" class="w-4 h-4"></i>
            </button>
            
            <button id="sidebarToggleDesktop" 
                    class="w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all hidden md:flex"
                    aria-label="Toggle sidebar">
                <i data-lucide="panel-left" class="w-4 h-4"></i>
            </button>

            <!-- Logo Branding -->
            <div class="flex items-center space-x-3.5">
                <img src="<?php echo htmlspecialchars($platform_logo); ?>" 
                     alt="Zetaphase Logo" 
                     class="w-8 h-8 rounded-lg object-cover border border-[var(--border-color)]"
                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg\'><i data-lucide=\'shield\' class=\'w-4 h-4 text-white\'></i></div>'; lucide.createIcons();">
                
                <div>
                    <span class="font-extrabold text-[var(--text-primary)] text-sm tracking-tight block">
                        <?php echo htmlspecialchars($platform_name); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Right Controls -->
        <div class="flex items-center space-x-4">
            
            <!-- Theme Toggle -->
            <div class="relative group">
                <button onclick="toggleThemeMenu()" 
                        class="w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all">
                    <i data-lucide="<?php echo $theme_mode === 'light' ? 'sun' : ($theme_mode === 'dark' ? 'moon' : 'monitor'); ?>" class="w-4 h-4"></i>
                </button>
                
                <div id="themeMenu" class="hidden absolute right-0 mt-2 w-40 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl shadow-2xl z-50 p-2">
                    <button onclick="setTheme('auto')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)] transition-all flex items-center space-x-2 <?php echo $theme_mode === 'auto' ? 'bg-indigo-500/10 text-indigo-400' : ''; ?>">
                        <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                        <span>Auto (<?php echo $display_mode; ?>)</span>
                    </button>
                    <button onclick="setTheme('light')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)] transition-all flex items-center space-x-2 <?php echo $theme_mode === 'light' ? 'bg-amber-500/10 text-amber-400' : ''; ?>">
                        <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                        <span>Light</span>
                    </button>
                    <button onclick="setTheme('dark')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)] transition-all flex items-center space-x-2 <?php echo $theme_mode === 'dark' ? 'bg-indigo-500/10 text-indigo-400' : ''; ?>">
                        <i data-lucide="moon" class="w-3.5 h-3.5"></i>
                        <span>Dark</span>
                    </button>
                </div>
            </div>

            <!-- User Info -->
            <div class="flex items-center space-x-2.5">
                <div class="text-right hidden sm:block">
                    <span class="font-bold text-[var(--text-primary)] text-xs block">
                        <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
                    </span>
                    <span class="text-[10px] text-[var(--text-secondary)] font-mono block">
                        <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                    </span>
                </div>
                
                <a href="../logout.php" 
                   title="Log Out" 
                   class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 flex items-center justify-center text-rose-400 transition-all">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Notification Container -->
<div id="notificationContainer" class="fixed top-20 right-4 z-50 space-y-3 max-w-sm w-full pointer-events-none">
    <!-- Notifications will be injected here -->
</div>

<script>
// Theme Menu Functions
function toggleThemeMenu() {
    const menu = document.getElementById('themeMenu');
    menu.classList.toggle('hidden');
}

function setTheme(mode) {
    fetch('../api/platform-theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: mode })
    }).then(() => {
        location.reload();
    });
}

// Close theme menu on outside click
document.addEventListener('click', function(e) {
    const menu = document.getElementById('themeMenu');
    const btn = e.target.closest('button[onclick="toggleThemeMenu()"]');
    if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target) && !btn) {
        menu.classList.add('hidden');
    }
});

// Notification System
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notificationContainer');
    
    // Define notification styles with solid backgrounds
    const configs = {
        success: {
            icon: 'check-circle',
            bg: 'bg-emerald-500',
            border: 'border-emerald-600',
            text: 'text-white',
            iconColor: 'text-white'
        },
        error: {
            icon: 'x-circle',
            bg: 'bg-rose-500',
            border: 'border-rose-600',
            text: 'text-white',
            iconColor: 'text-white'
        },
        warning: {
            icon: 'alert-triangle',
            bg: 'bg-amber-500',
            border: 'border-amber-600',
            text: 'text-white',
            iconColor: 'text-white'
        },
        info: {
            icon: 'info',
            bg: 'bg-blue-500',
            border: 'border-blue-600',
            text: 'text-white',
            iconColor: 'text-white'
        }
    };
    
    const config = configs[type] || configs.info;
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `pointer-events-auto p-4 rounded-xl border ${config.bg} ${config.border} shadow-lg transform translate-x-full opacity-0 transition-all duration-300 ease-out`;
    notification.style.transform = 'translateX(100%)';
    notification.style.opacity = '0';
    
    notification.innerHTML = `
        <div class="flex items-start justify-between">
            <div class="flex items-start space-x-3">
                <i data-lucide="${config.icon}" class="w-5 h-5 ${config.iconColor} mt-0.5"></i>
                <div>
                    <span class="text-sm font-bold ${config.text} block">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                    <span class="text-sm ${config.text} block">${message}</span>
                </div>
            </div>
            <button onclick="this.closest('.pointer-events-auto').remove()" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Initialize Lucide icons
    lucide.createIcons();
    
    // Trigger animation
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
        notification.style.opacity = '1';
    }, 50);
    
    // Auto-remove after duration
    const timeoutId = setTimeout(() => {
        removeNotification(notification);
    }, duration);
    
    // Store timeout ID for manual removal
    notification.dataset.timeoutId = timeoutId;
    
    return notification;
}

function removeNotification(notification) {
    if (!notification) return;
    
    // Clear auto-remove timeout
    if (notification.dataset.timeoutId) {
        clearTimeout(parseInt(notification.dataset.timeoutId));
    }
    
    // Animate out
    notification.style.transform = 'translateX(100%)';
    notification.style.opacity = '0';
    
    // Remove from DOM after animation
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 300);
}

// Display flash messages on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($flash_success): ?>
    showNotification("<?php echo addslashes($flash_success); ?>", 'success');
    <?php endif; ?>
    
    <?php if ($flash_error): ?>
    showNotification("<?php echo addslashes($flash_error); ?>", 'error');
    <?php endif; ?>
    
    <?php if ($flash_warning): ?>
    showNotification("<?php echo addslashes($flash_warning); ?>", 'warning');
    <?php endif; ?>
    
    <?php if ($flash_info): ?>
    showNotification("<?php echo addslashes($flash_info); ?>", 'info');
    <?php endif; ?>
    
    // Sidebar Toggle Functions
    const sidebar = document.querySelector('aside');
    const toggleMobile = document.getElementById('sidebarToggle');
    const toggleDesktop = document.getElementById('sidebarToggleDesktop');
    const mainContent = document.getElementById('mainContent');
    
    // Check if sidebar is already hidden (from localStorage)
    const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
    if (sidebarHidden && window.innerWidth >= 768) {
        sidebar.classList.add('hidden');
        toggleDesktop.querySelector('i').setAttribute('data-lucide', 'panel-right');
        mainContent.classList.add('expanded');
        lucide.createIcons();
    }
    
    // Mobile toggle
    if (toggleMobile && sidebar) {
        toggleMobile.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
            // Update icon
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
            // Save state to localStorage
            localStorage.setItem('sidebarHidden', sidebar.classList.contains('hidden'));
            // Update icon
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
    
    // Handle window resize for responsive behavior
    window.addEventListener('resize', function() {
        if (window.innerWidth < 768) {
            // On mobile, show sidebar toggle (it's already visible)
            toggleDesktop.classList.add('hidden');
            toggleMobile.classList.remove('hidden');
            // On mobile, sidebar is always hidden initially
            if (!sidebar.classList.contains('hidden')) {
                sidebar.classList.add('hidden');
                mainContent.classList.add('expanded');
            }
        } else {
            // On desktop
            toggleDesktop.classList.remove('hidden');
            toggleMobile.classList.add('hidden');
            
            // Restore sidebar state from localStorage
            const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
            if (sidebarHidden) {
                sidebar.classList.add('hidden');
                toggleDesktop.querySelector('i').setAttribute('data-lucide', 'panel-right');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('hidden');
                toggleDesktop.querySelector('i').setAttribute('data-lucide', 'panel-left');
                mainContent.classList.remove('expanded');
            }
            lucide.createIcons();
        }
    });
    
    // Trigger resize on load
    window.dispatchEvent(new Event('resize'));
});

// Global function to show notifications from any script
window.showNotification = showNotification;
</script>

<style>
/* Notification animations */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Notification container positioning */
#notificationContainer {
    max-height: calc(100vh - 100px);
    overflow-y: auto;
}

/* Scrollbar styling for notification container */
#notificationContainer::-webkit-scrollbar {
    width: 4px;
}

#notificationContainer::-webkit-scrollbar-track {
    background: transparent;
}

#notificationContainer::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 10px;
}

/* Make sure notifications are on top of everything */
#notificationContainer {
    z-index: 9999 !important;
}
</style>