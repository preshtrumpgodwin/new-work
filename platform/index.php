<?php
/**
 * Platform Manager - Main Router
 */
require_once __DIR__ . '/../config/security.php';
secure_session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/lib/Helpers.php';

// Auth check
if (!isset($_SESSION['user_uuid']) || $_SESSION['role'] !== 'Platform Manager') {
    header('Location: ../login.php');
    exit;
}

// Determine page
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['page']) : 'tenants';
$allowed = ['tenants', 'requests', 'pricing', 'billing', 'audit', 'result_slip_builder'];
if (!in_array($page, $allowed)) $page = 'tenants';

// Handle POST actions (they will redirect back)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        header('Location: index.php?page=' . urlencode($page) . '&error=' . urlencode('Your session expired — please try again.'));
        exit;
    }
    require_once __DIR__ . '/actions/tenants.php';
    require_once __DIR__ . '/actions/requests.php';
    require_once __DIR__ . '/actions/pricing.php';
    require_once __DIR__ . '/actions/billing.php';
    require_once __DIR__ . '/actions/result_slip_builder.php';
    exit; // actions should redirect
}

// Theme
$theme_mode = $_SESSION['platform_theme'] ?? 'auto';
if ($theme_mode === 'auto') {
    $hour = (int)date('H');
    $theme_mode = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
}

// Platform branding
$platform_name = 'Zetaphase EduCloud';
$platform_subdomain = 'platform.zetaphase.com.ng';
$platform_logo = '../logo.jpeg';

// Stats for sidebar
$stats = [];
try {
    $stats['active_schools'] = (int)$pdo->query("SELECT COUNT(*) FROM schools WHERE status='Active'")->fetchColumn();
    $stats['pending_reqs'] = (int)$pdo->query("SELECT COUNT(*) FROM onboarding_requests WHERE status='Pending'")->fetchColumn();
    $stats['total_revenue'] = (float)$pdo->query("SELECT SUM(monthly_fee) FROM schools WHERE status='Active'")->fetchColumn();
} catch (Exception $e) {}

// Include page
$page_file = __DIR__ . '/pages/' . $page . '.php';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme_mode; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform_name); ?> — Platform Manager</title>
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo htmlspecialchars($platform_logo); ?>">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <style>
        :root {
            --bg-primary: <?php echo $theme_mode === 'light' ? '#FFFFFF' : '#0E1117'; ?>;
            --bg-secondary: <?php echo $theme_mode === 'light' ? '#F8FAFC' : '#11141B'; ?>;
            --bg-tertiary: <?php echo $theme_mode === 'light' ? '#F1F5F9' : '#0A0D12'; ?>;
            --border-color: <?php echo $theme_mode === 'light' ? '#E2E8F0' : '#1E232D'; ?>;
            --text-primary: <?php echo $theme_mode === 'light' ? '#0F172A' : '#F1F5F9'; ?>;
            --text-secondary: <?php echo $theme_mode === 'light' ? '#475569' : '#94A3B8'; ?>;
            --brand-color: #4F46E5;
        }
        .brand-accent { color: var(--brand-color); }
        .brand-bg { background-color: var(--brand-color); }
        
        /* Sidebar transitions */
        aside {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
        }
        
        aside.hidden {
            transform: translateX(-100%);
            opacity: 0;
            width: 0 !important;
            min-width: 0 !important;
            overflow: hidden;
            border-right: none !important;
        }
        
        /* Main content transition */
        .main-content {
            transition: margin-left 0.3s ease-in-out;
            margin-left: 256px; /* 64 * 4 = 256px */
        }
        
        .main-content.expanded {
            margin-left: 0 !important;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen flex font-sans selection:bg-indigo-500 selection:text-white">
    <div class="flex min-h-screen w-full relative">
        <?php include __DIR__ . '/components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col min-w-0 main-content" id="mainContent">
            <?php include __DIR__ . '/components/header.php'; ?>
            <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
                <?php if (file_exists($page_file)): ?>
                    <?php include $page_file; ?>
                <?php else: ?>
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-6 text-xs text-rose-400">Page not found.</div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>