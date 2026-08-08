<?php
$sb_base_url = $sb_base_url ?? 'dashboard.php';
// PHP Modular Dashboard Header with Dynamic Brand Resolution & User Avatar
// DISPLAY ONLY - All POST handling moved to dashboard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_uuid'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/Notify.php';

$schoolName   = 'Platform Admin System Control';
$schoolLogo   = '';
$themeColor   = '#4F46E5';
$userPhoto    = '';
$userName     = $_SESSION['name'] ?? 'User';
$userEmail    = $_SESSION['email'] ?? '';
$userRole     = $_SESSION['role'] ?? 'Staff';
$schoolUuid   = $_SESSION['school_uuid'] ?? '';
$userUuid     = $_SESSION['user_uuid'] ?? '';
$userTheme    = $_SESSION['user_theme'] ?? 'auto';

// Handle flash messages from session
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
$flash_warning = $_SESSION['flash_warning'] ?? null;
$flash_info = $_SESSION['flash_info'] ?? null;

// Clear flash messages after displaying
if ($flash_success) unset($_SESSION['flash_success']);
if ($flash_error) unset($_SESSION['flash_error']);
if ($flash_warning) unset($_SESSION['flash_warning']);
if ($flash_info) unset($_SESSION['flash_info']);

// ── Fetch school branding ──────────────────────────────────────────────────
if (!empty($schoolUuid)) {
    try {
        $stmt = $pdo->prepare("SELECT name, logo_path, theme_color, theme_mode FROM schools WHERE school_uuid = ? LIMIT 1");
        $stmt->execute([$schoolUuid]);
        $school = $stmt->fetch();
        if ($school) {
            $schoolName = $school['name'];
            $logo_path  = $school['logo_path'] ?? 'logo.jpeg';
            $schoolLogo = (!empty($logo_path) && strpos($logo_path, '/') !== 0)
                           ? '/../' . ltrim($logo_path, '/')
                           : $logo_path;
            $themeColor = $school['theme_color'] ?: '#4F46E5';
            $schoolTheme = $school['theme_mode'] ?? 'auto';
        }
    } catch (PDOException $e) {}
}

// ── Fetch user photo from appropriate table ────────────────────────────────
if (!empty($userUuid)) {
    try {
        // Check users table first
        $stmt = $pdo->prepare("SELECT photo_path, theme_preference FROM users WHERE user_uuid = ? LIMIT 1");
        $stmt->execute([$userUuid]);
        $userData = $stmt->fetch();
        if ($userData) {
            $userPhotoPath = $userData['photo_path'] ?? '';
            $userPhoto = (!empty($userPhotoPath) && strpos($userPhotoPath, '/') !== 0)
                          ? '/../' . ltrim($userPhotoPath, '/')
                          : $userPhotoPath;
            $userTheme = $userData['theme_preference'] ?? $userTheme;
        }

        // If no photo in users, try staff table
        if (empty($userPhoto) && !empty($schoolUuid)) {
            $stmt = $pdo->prepare("SELECT photo_path FROM staff WHERE user_uuid = ? AND school_uuid = ? LIMIT 1");
            $stmt->execute([$userUuid, $schoolUuid]);
            $staffPhotoPath = $stmt->fetchColumn();
            $userPhoto = (!empty($staffPhotoPath) && strpos($staffPhotoPath, '/') !== 0)
                          ? '/../' . ltrim($staffPhotoPath, '/')
                          : $staffPhotoPath;
        }

        // If no photo in staff, try parents table
        if (empty($userPhoto) && !empty($schoolUuid)) {
            $stmt = $pdo->prepare("SELECT photo_path FROM parents WHERE email = ? AND school_uuid = ? LIMIT 1");
            $stmt->execute([$userEmail, $schoolUuid]);
            $parentPhotoPath = $stmt->fetchColumn();
            $userPhoto = (!empty($parentPhotoPath) && strpos($parentPhotoPath, '/') !== 0)
                          ? '/../' . ltrim($parentPhotoPath, '/')
                          : $parentPhotoPath;
        }
    } catch (PDOException $e) {}
}

// ── Fetch unread billing reminders count for invoice badge ────────────────
$unreadBilling = 0;
if (!empty($schoolUuid)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscription_reminders WHERE school_uuid = ? AND is_read = 0");
        $stmt->execute([$schoolUuid]);
        $unreadBilling = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
}

// ── Fetch in-app notification bell data ────────────────────────────────────
$unreadNotifCount = 0; $recentNotifs = [];
if (!empty($schoolUuid) && !empty($userUuid)) {
    $unreadNotifCount = Notify::unreadCount($pdo, $schoolUuid, $userUuid, $userRole);
    $recentNotifs = Notify::listFor($pdo, $schoolUuid, $userUuid, $userRole, 8);
}
?>
<!-- ========== ALL HTML OUTPUT STARTS HERE ========== -->

<!-- Change Password Modal -->
<div id="changePasswordModal" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl relative text-left">
        <button onclick="closeChangePasswordModal()" class="absolute top-4 right-4 text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all cursor-pointer">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
        <div class="flex items-center space-x-2 text-indigo-400">
            <i data-lucide="key-round" class="w-5 h-5"></i>
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Change Account Password</h3>
        </div>
        <p class="text-xs text-[var(--text-secondary)]">Update your access credentials. Minimum 6 characters.</p>
        
        <form method="POST" action="<?php echo htmlspecialchars($sb_base_url); ?>" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_change_user_password" value="1">
            
            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Current Password</label>
                <input type="password" name="current_password" required placeholder="••••••••" 
                       class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-2.5 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 font-mono">
            </div>

            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">New Password</label>
                <input type="password" name="new_password" required placeholder="••••••••" minlength="6"
                       class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-2.5 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 font-mono">
            </div>

            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="••••••••" minlength="6"
                       class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-2.5 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 font-mono">
            </div>

            <div class="flex space-x-3 justify-end pt-2">
                <button type="button" onclick="closeChangePasswordModal()" 
                        class="px-4 py-2 bg-[var(--bg-tertiary)] hover:bg-[var(--bg-secondary)] text-[var(--text-secondary)] rounded-xl text-xs font-bold transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all cursor-pointer">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notification Container -->
<div id="notificationContainer" class="fixed top-20 right-4 z-50 space-y-3 max-w-sm w-full pointer-events-none">
    <!-- Notifications will be injected here -->
</div>

<script>
// Class → Arm cascading dropdown helper. Arms belong to one class (e.g.
// "A" under JSS1 is not the same arm as "A" under JSS2), so every arm
// dropdown across the dashboard loads its options from here only after a
// class has been chosen. `preselect` (optional) is re-selected once the
// arm list for that class has loaded, e.g. when re-opening an edit form.
function wireClassArm(classSelId, armSelId, preselect) {
    const classSel = document.getElementById(classSelId);
    const armSel   = document.getElementById(armSelId);
    if (!classSel || !armSel) return;

    function resetArm(placeholder) {
        armSel.innerHTML = '<option value="">' + placeholder + '</option>';
        armSel.disabled = true;
    }

    async function loadArms(keepValue) {
        const cls = classSel.value;
        if (!cls) { resetArm('Select a class first'); return; }
        resetArm('Loading…');
        try {
            const res = await fetch('api/get-arms.php?class_name=' + encodeURIComponent(cls), { credentials: 'same-origin' });
            const data = await res.json();
            const arms = data.arms || [];
            armSel.innerHTML = '<option value="">' + (arms.length ? 'Select arm' : 'No arms for this class') + '</option>';
            arms.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a; opt.textContent = a;
                if (keepValue && a === keepValue) opt.selected = true;
                armSel.appendChild(opt);
            });
            armSel.disabled = arms.length === 0;
        } catch (e) { resetArm('Failed to load arms'); }
    }

    classSel.addEventListener('change', () => loadArms(null));
    resetArm('Select a class first');
    if (classSel.value) loadArms(preselect || null);
}

// Theme Menu Functions
function toggleThemeMenu() {
    const menu = document.getElementById('themeMenu');
    menu.classList.toggle('hidden');
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

// Change Password Modal functions
function openChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Notification Bell toggle
function toggleNotifDropdown() {
    const d = document.getElementById('notifDropdown');
    if (d) d.classList.toggle('hidden');
}

// Close notification dropdown on outside click
document.addEventListener('click', function(e) {
    const d = document.getElementById('notifDropdown');
    const btn = e.target.closest('button[onclick="toggleNotifDropdown()"]');
    if (d && !d.classList.contains('hidden') && !d.contains(e.target) && !btn) {
        d.classList.add('hidden');
    }
});

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
});

// Global function to show notifications from any script
window.showNotification = showNotification;

// ── Near-real-time notification polling (Phase E) ───────────────────────────
(function() {
    const badge = document.getElementById('notifBadge');
    if (!badge) return; // not logged into a school context
    let lastSeenUuid = null;
    async function pollNotifications() {
        try {
            const res = await fetch('api/notif-count.php', { credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (typeof data.count === 'number') {
                if (data.count > 0) {
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                    badge.classList.remove('hidden'); badge.classList.add('flex');
                } else {
                    badge.classList.add('hidden'); badge.classList.remove('flex');
                }
            }
            if (data.latest && data.latest.notification_uuid && data.latest.notification_uuid !== lastSeenUuid) {
                if (lastSeenUuid !== null) { // don't toast on the very first poll (page just loaded)
                    showNotification(data.latest.title || data.latest.message || 'New notification', 'info');
                }
                lastSeenUuid = data.latest.notification_uuid;
            }
        } catch (e) { /* silent — polling is best-effort */ }
    }
    pollNotifications();
    setInterval(pollNotifications, 20000);
})();
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

<header style="height: 70px;" class="flex items-center justify-between w-full px-6 border-b border-[var(--border-color)] bg-[var(--bg-secondary)]/75 backdrop-blur-md">
    
     <!-- Left Section: Sidebar Toggle + Logo Branding -->
        <div class="flex items-center space-x-3.5">
            <!-- Sidebar Toggle Button (Mobile) -->
            <button id="sidebarToggle" 
                    class="w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all md:hidden"
                    aria-label="Toggle sidebar">
                <i data-lucide="menu" class="w-4 h-4"></i>
            </button>
            
            <!-- Sidebar Toggle Button (Desktop) -->
            <button id="sidebarToggleDesktop" 
                    class="w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all hidden md:flex"
                    aria-label="Toggle sidebar">
                <i data-lucide="panel-left" class="w-4 h-4"></i>
            </button>

            <!-- Logo Branding -->
            <div class="flex items-center space-x-3.5">
                <?php if (!empty($schoolLogo)): ?>
                    <img src="<?php echo htmlspecialchars($schoolLogo); ?>" 
                         alt="School Logo" 
                         class="w-8 h-8 rounded-lg object-cover border border-[var(--border-color)]" 
                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-8 h-8 rounded-lg flex items-center justify-center shadow-lg\' style=\'background-color:<?php echo htmlspecialchars($themeColor); ?>\'><i data-lucide=\'graduation-cap\' class=\'w-4 h-4 text-white\'></i></div>'; lucide.createIcons();" />
                <?php elseif (!empty($schoolUuid)): ?>
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-lg" style="background-color:<?php echo htmlspecialchars($themeColor); ?>">
                        <i data-lucide="graduation-cap" class="w-4 h-4 text-white"></i>
                    </div>
                <?php else: ?>
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg">
                        <i data-lucide="shield" class="w-4 h-4 text-white"></i>
                    </div>
                <?php endif; ?>
                
                <div>
                    <span class="font-extrabold text-[var(--text-primary)] text-sm tracking-tight block">
                        <?php echo htmlspecialchars($schoolName); ?>
                    </span>
                    <span class="text-[9px] text-[var(--text-secondary)] block font-mono">
                        <?php echo htmlspecialchars($userRole); ?> Workspace
                    </span>
                </div>
            </div>
        </div>

        <!-- Right Controls -->
        <div class="flex items-center space-x-4">
            
            <!-- Subdomain Badge -->
            <div class="hidden sm:inline-flex items-center space-x-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] px-3 py-1 rounded-full text-[10px] text-[var(--text-secondary)] font-mono">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span><?php
                    try {
                        if (!empty($schoolUuid)) {
                            $__hSub = $pdo->prepare("SELECT subdomain FROM schools WHERE school_uuid = ? LIMIT 1");
                            $__hSub->execute([$schoolUuid]);
                            $__hRow = $__hSub->fetch();
                            echo htmlspecialchars(($__hRow['subdomain'] ?? 'school') . '.zetaphase.com.ng');
                        } else {
                            echo 'platform.zetaphase.com.ng';
                        }
                    } catch (Exception $e) { echo 'zetaphase.com.ng'; }
                ?></span>
            </div>

            <!-- Invoice / Billing Badge -->
            <?php if ($unreadBilling > 0 && $userRole === 'School Admin'): ?>
                <a href="<?php echo htmlspecialchars($sb_base_url); ?>?section=settings" 
                   class="relative flex items-center space-x-1.5 bg-amber-500/10 border border-amber-500/20 px-3 py-1.5 rounded-full text-[10px] font-bold text-amber-400 hover:bg-amber-500/20 transition-all"
                   title="<?php echo $unreadBilling; ?> unread billing notice(s)">
                    <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                    <span>Invoice</span>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-amber-500 text-[var(--bg-primary)] text-[8px] font-black rounded-full flex items-center justify-center leading-none">
                        <?php echo $unreadBilling; ?>
                    </span>
                </a>
            <?php endif; ?>

            <!-- Theme Toggle -->
            <div class="relative group">
                <button onclick="toggleThemeMenu()" 
                        class="w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all">
                    <i data-lucide="<?php echo $userTheme === 'light' ? 'sun' : ($userTheme === 'dark' ? 'moon' : 'monitor'); ?>" class="w-4 h-4"></i>
                </button>
                
                <div id="themeMenu" class="hidden absolute right-0 mt-2 w-40 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl shadow-2xl z-50 p-2">
                    <form method="POST" action="<?php echo htmlspecialchars($sb_base_url); ?>" id="themeForm"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_change_theme" value="1">
                        <button type="submit" name="theme" value="auto" class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)] transition-all flex items-center space-x-2 <?php echo $userTheme === 'auto' ? 'bg-indigo-500/10 text-indigo-400' : ''; ?>">
                            <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                            <span>Auto</span>
                        </button>
                        <button type="submit" name="theme" value="light" class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)] transition-all flex items-center space-x-2 <?php echo $userTheme === 'light' ? 'bg-amber-500/10 text-amber-400' : ''; ?>">
                            <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                            <span>Light</span>
                        </button>
                        <button type="submit" name="theme" value="dark" class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)] transition-all flex items-center space-x-2 <?php echo $userTheme === 'dark' ? 'bg-indigo-500/10 text-indigo-400' : ''; ?>">
                            <i data-lucide="moon" class="w-3.5 h-3.5"></i>
                            <span>Dark</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Notification Bell -->
            <?php if (!empty($schoolUuid)): ?>
            <div class="relative">
                <button onclick="toggleNotifDropdown()" id="notifBellBtn" class="relative w-8 h-8 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    <span id="notifBadge" class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[8px] font-black rounded-full items-center justify-center leading-none <?php echo $unreadNotifCount > 0 ? 'flex' : 'hidden'; ?>">
                        <?php echo $unreadNotifCount > 9 ? '9+' : $unreadNotifCount; ?>
                    </span>
                </button>
                <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl shadow-2xl z-50">
                    <div class="p-3 border-b border-[var(--border-color)] flex items-center justify-between">
                        <span class="text-xs font-bold text-[var(--text-primary)]">Notifications</span>
                        <a href="<?php echo htmlspecialchars($sb_base_url); ?>?section=notifications" class="text-[10px] text-indigo-400 hover:underline">View all</a>
                    </div>
                    <?php if (empty($recentNotifs)): ?>
                        <p class="text-[11px] text-[var(--text-secondary)] p-4 text-center">No notifications yet.</p>
                    <?php endif; ?>
                    <?php foreach ($recentNotifs as $n): ?>
                    <a href="<?php echo htmlspecialchars($n['link'] ?: ($sb_base_url . '?section=notifications')); ?>" class="block p-3 border-b border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] <?php echo $n['is_read'] ? 'opacity-50' : ''; ?>">
                        <span class="text-[11px] font-bold text-[var(--text-primary)] block"><?php echo htmlspecialchars($n['title']); ?></span>
                        <span class="text-[10px] text-[var(--text-secondary)] block mt-0.5 line-clamp-2"><?php echo htmlspecialchars($n['message']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- User Info & Avatar -->
            <div class="flex items-center space-x-2.5">
                <div class="text-right hidden sm:block">
                    <span class="font-bold text-[var(--text-primary)] text-xs block"><?php echo htmlspecialchars($userName); ?></span>
                    <span class="text-[10px] text-[var(--text-secondary)] font-mono block"><?php echo htmlspecialchars($userEmail); ?></span>
                </div>
                
                <!-- User Avatar with fallback -->
                <div class="relative">
                    <?php if (!empty($userPhoto)): ?>
                        <img src="<?php echo htmlspecialchars($userPhoto); ?>" 
                             alt="User" 
                             class="w-9 h-9 rounded-full object-cover border-2 border-[var(--border-color)]"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                        <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 border-[var(--border-color)] text-[var(--text-secondary)]" 
                             style="display:none; background-color:<?php echo htmlspecialchars($themeColor); ?>20;">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                    <?php else: ?>
                        <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 border-[var(--border-color)] text-white" 
                             style="background-color:<?php echo htmlspecialchars($themeColor); ?>;">
                            <span class="text-xs font-bold"><?php echo strtoupper(substr($userName, 0, 2)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Change Password -->
                <button onclick="openChangePasswordModal()" 
                        title="Change Password" 
                        class="w-8 h-8 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 flex items-center justify-center text-indigo-400 transition-all cursor-pointer">
                    <i data-lucide="key-round" class="w-4 h-4"></i>
                </button>

                <!-- Logout -->
                <a href="/../../logout.php" 
                   title="Log Out" 
                   class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 flex items-center justify-center text-rose-400 transition-all">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
</header>