<?php
// PHP Modular Sidebar Navigation with school_feature_access filtering
// Grouped into fixed headings. Every non-core item MUST pass isFeatureEnabled()
// before it renders — no feature is allowed to bypass school_feature_access.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active_role = $_SESSION['role'] ?? 'Teacher';
$section = $_GET['section'] ?? 'overview';
$school_uuid = $_SESSION['school_uuid'] ?? '';
$user_uuid = $_SESSION['user_uuid'] ?? '';

require_once __DIR__ . '/../../config/db.php';

// Resolve school branding
$_sb_school_name = 'Zetaphase EduCloud';
$_sb_school_logo = '';
$_sb_theme_color = '#4F46E5';
$_sb_school_subdomain = 'zetaphase.com.ng';

if (!empty($school_uuid)) {
    try {
        $__sbStmt = $pdo->prepare("SELECT name, logo_path, theme_color, subdomain FROM schools WHERE school_uuid = ? LIMIT 1");
        $__sbStmt->execute([$school_uuid]);
        $__sbSchool = $__sbStmt->fetch();
        if ($__sbSchool) {
            $_sb_school_name = $__sbSchool['name'];
            $_sb_school_logo = $__sbSchool['logo_path'] ?? '';
            $_sb_theme_color = $__sbSchool['theme_color'] ?? '#4F46E5';
            $_sb_school_subdomain = ($__sbSchool['subdomain'] ?? 'school') . '.zetaphase.com.ng';
        }
    } catch (Exception $e) {}
}

// Feature visibility is resolved per-key via isFeatureEnabled() below
// (which defers to the same safe ceiling check used elsewhere in the app),
// so no upfront full-list fetch is needed here.

function isFeatureEnabled($key) {
    global $active_role, $school_uuid;
    if ($active_role === 'Platform Manager') return true;
    // Delegate to the same ceiling check used everywhere else (report cards,
    // broadsheet, etc.) instead of the raw school_feature_access lookup this
    // used to do directly. That raw lookup had no safe default: a school
    // with no explicit row for a feature — which is exactly what happens
    // for any feature added after that school was provisioned, like
    // "result_slip_templates" — was simply missing from $enabledFeatures
    // and its sidebar link never appeared, even though the feature itself
    // worked fine once you knew the URL. isSectionEnabled()/
    // getSchoolFeatureCeiling() default a missing row to 'full' (visible)
    // for exactly this reason, so this now matches every other gate in the app.
    return isSectionEnabled($key, $school_uuid);
}

// Staff permission check
function getStaffAccessLevel($feature_key, $role, $user_uuid, $school_uuid) {
    global $pdo;
    if ($role === 'Platform Manager' || $role === 'School Admin') return 'manage';
    try {
        $stmt = $pdo->prepare("SELECT access_level FROM staff_feature_permissions WHERE school_uuid = ? AND staff_uuid = (SELECT staff_uuid FROM staff WHERE user_uuid = ? LIMIT 1) AND feature_key = ? LIMIT 1");
        $stmt->execute([$school_uuid, $user_uuid, $feature_key]);
        $access = $stmt->fetchColumn();
        return $access ?: 'view';
    } catch (Exception $e) { return 'view'; }
}

// Unread billing count (used for the Settings badge and In-App Notifications badge)
$_sb_unread_billing = 0;
if (!empty($school_uuid)) {
    try {
        $__ubStmt = $pdo->prepare("SELECT COUNT(*) FROM subscription_reminders WHERE school_uuid = ? AND is_read = 0");
        $__ubStmt->execute([$school_uuid]);
        $_sb_unread_billing = (int)$__ubStmt->fetchColumn();
    } catch (Exception $e) {}
}

/**
 * Nav link renderer.
 */
function sbLink($sec, $section, $icon, $iconColorClass, $label, $badge = null) {
    global $sb_base_url;
    $base = $sb_base_url ?? 'dashboard.php';
    $active = ($section === $sec) ? 'bg-indigo-600 text-white shadow-md' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]';
    echo '<a href="' . htmlspecialchars($base) . '?section=' . htmlspecialchars($sec) . '" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all ' . $active . '">';
    echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-4 h-4 ' . htmlspecialchars($iconColorClass) . '"></i>';
    echo '<span class="flex-1">' . htmlspecialchars($label) . '</span>';
    if ($badge !== null && $badge > 0) {
        echo '<span class="ml-auto px-1.5 py-0.5 bg-amber-500 text-[var(--bg-primary)] text-[9px] font-black rounded-full leading-none">' . (int)$badge . '</span>';
    }
    echo '</a>';
}

/**
 * Group header renderer — only ever printed by sbGroup() once real content exists.
 */
function sbGroupHeader($label) {
    echo '<span class="text-[9px] font-bold text-[var(--text-secondary)] uppercase tracking-widest block px-3 mt-4 mb-2 font-mono">' . htmlspecialchars($label) . '</span>';
}
?>

<!-- Sidebar Content (Scrollable) -->
<div class="sidebar-content">

    <div class="space-y-0.5">
        <?php if ($active_role === 'Platform Manager'): ?>

            <a href="platform-manager.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-bold transition-all bg-indigo-600 text-white shadow-lg">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                <span>Platform Manager</span>
            </a>

        <?php else: ?>

            <!-- ══════════════ DASHBOARD OVERVIEW ══════════════ -->
            <?php sbGroupHeader('Dashboard Overview'); ?>
            <?php sbLink('overview', $section, 'layout-dashboard', 'text-indigo-400', 'Dashboard Overview'); ?>

            <!-- ══════════════ STUDENT MANAGEMENT ══════════════ -->
            <?php if (isFeatureEnabled('roster') || isFeatureEnabled('id_cards')): ?>
                <?php sbGroupHeader('Student Management'); ?>
                <?php if (isFeatureEnabled('roster')): ?>
                    <?php sbLink('roster', $section, 'users', '', 'Student Roster'); ?>
                <?php endif; ?>
                <?php if (isFeatureEnabled('id_cards')): ?>
                    <?php sbLink('id_cards', $section, 'badge-check', 'text-emerald-400', 'ID Card Designer'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ STAFF & HR DIRECTORY ══════════════ -->
            <?php if ($active_role === 'School Admin' && (isFeatureEnabled('staff') || isFeatureEnabled('condition_of_service'))): ?>
                <?php sbGroupHeader('Staff & HR Directory'); ?>
                <?php if (isFeatureEnabled('staff')): ?>
                    <?php sbLink('hr', $section, 'briefcase', '', 'Staff & HR Directory'); ?>
                <?php endif; ?>
                <?php if (isFeatureEnabled('condition_of_service')): ?>
                    <?php sbLink('condition_of_service', $section, 'file-text', 'text-slate-400', 'Condition of Service'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ PARENT RECORDS ══════════════ -->
            <?php if (isFeatureEnabled('parents')): ?>
                <?php sbGroupHeader('Parent Records'); ?>
                <?php sbLink('parents', $section, 'user-round', 'text-blue-400', 'Parent Records'); ?>
            <?php endif; ?>

            <!-- ══════════════ ATTENDANCE LOG ══════════════ -->
            <?php
                $_sb_showAttendance = isFeatureEnabled('attendance') && getStaffAccessLevel('attendance', $active_role, $user_uuid, $school_uuid) !== 'none';
                $_sb_showGate = isFeatureEnabled('gate_scanner');
            ?>
            <?php if ($_sb_showAttendance || $_sb_showGate): ?>
                <?php sbGroupHeader('Attendance Log'); ?>
                <?php if ($_sb_showAttendance): ?>
                    <?php sbLink('attendance', $section, 'calendar-check', '', 'Attendance Log'); ?>
                <?php endif; ?>
                <?php if ($_sb_showGate): ?>
                    <?php sbLink('gate_scanner', $section, 'scan-line', 'text-cyan-400', 'Gate QR Check-In'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ TIMETABLE ══════════════ -->
            <?php if (isFeatureEnabled('timetable') && getStaffAccessLevel('timetable', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Timetable'); ?>
                <?php sbLink('timetable', $section, 'clock', '', 'Timetable'); ?>
            <?php endif; ?>

            <!-- ══════════════ ACADEMIC TOOLS (Phase C/D) ══════════════ -->
            <?php sbGroupHeader('Academic Tools'); ?>
            <?php if (isFeatureEnabled('question_bank')): ?><?php sbLink('question_bank', $section, 'book-open-check', '', 'Question Bank'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('transcripts')): ?><?php sbLink('transcripts', $section, 'file-text', '', 'Transcript Generator'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('annual_report')): ?><?php sbLink('annual_report', $section, 'bar-chart-3', '', 'Annual Report'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('virtual_classroom')): ?><?php sbLink('virtual_classroom', $section, 'video', '', 'Virtual Classroom'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('career_advisory')): ?><?php sbLink('career_advisory', $section, 'compass', '', 'Career Advisory'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('staff_attendance')): ?><?php sbLink('staff_attendance', $section, 'calendar-check', '', 'Staff Attendance'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('result_slip_templates') && in_array($active_role, ['School Admin','Platform Manager'], true)): ?><?php sbLink('result_slip_templates', $section, 'layout-template', '', 'Result Slip Templates'); ?><?php endif; ?>
            <?php if (isFeatureEnabled('student_history')): ?><?php sbLink('student_history', $section, 'history', '', 'Student History Timeline'); ?><?php endif; ?>

            <!-- ══════════════ LESSON PLANS & SCHEMES ══════════════ -->
            <?php if (isFeatureEnabled('lesson_plans') || isFeatureEnabled('assignments')): ?>
                <?php sbGroupHeader('Lesson Plans & Schemes'); ?>
                <?php if (isFeatureEnabled('lesson_plans')): ?>
                    <?php sbLink('lesson_plans', $section, 'book-marked', 'text-purple-400', 'Lesson Plans & Schemes'); ?>
                <?php endif; ?>
                <?php if (isFeatureEnabled('assignments')): ?>
                    <?php sbLink('assignments', $section, 'file-check-2', 'text-blue-400', 'Assignments'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ RESULTS ENTRY ══════════════ -->
            <?php if (isFeatureEnabled('results')): ?>
                <?php sbGroupHeader('Results Entry'); ?>
                <?php sbLink('results', $section, 'file-spreadsheet', 'text-amber-300', 'Results Entry'); ?>
            <?php endif; ?>

            <!-- ══════════════ REPORT CARDS ══════════════ -->
            <?php if (isFeatureEnabled('report_cards')): ?>
                <?php sbGroupHeader('Report Cards'); ?>
                <?php sbLink('report_cards', $section, 'award', 'text-amber-300', 'Report Cards'); ?>
                <?php if (isFeatureEnabled('broadsheet') && getFeatureAccessLevel('broadsheet', $active_role, $user_uuid, $school_uuid) !== 'hide'): ?>
                <?php sbLink('broadsheet', $section, 'table-properties', 'text-emerald-300', 'Class Broadsheet'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ CBT QUIZZES ══════════════ -->
            <?php if (isFeatureEnabled('cbt') && getStaffAccessLevel('cbt', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('CBT Quizzes'); ?>
                <?php sbLink('cbt', $section, 'laptop', '', 'CBT Quizzes'); ?>
            <?php endif; ?>

            <!-- ══════════════ OMR SHEETS & MARKING ══════════════ -->
            <?php if (isFeatureEnabled('omr')): ?>
                <?php sbGroupHeader('OMR Sheets & Marking'); ?>
                <?php sbLink('omr', $section, 'grid', 'text-cyan-400', 'OMR Sheets & Marking'); ?>
            <?php endif; ?>

            <!-- ══════════════ LIBRARY MANAGEMENT ══════════════ -->
            <?php if (isFeatureEnabled('library') && getStaffAccessLevel('library', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Library Management'); ?>
                <?php sbLink('library', $section, 'book-open', '', 'Library Management'); ?>
            <?php endif; ?>

            <!-- ══════════════ INVENTORY / STORE ══════════════ -->
            <?php if (isFeatureEnabled('inventory') || isFeatureEnabled('cafeteria_meals')): ?>
                <?php sbGroupHeader('Inventory / Store'); ?>
                <?php if (isFeatureEnabled('inventory')): ?>
                    <?php sbLink('school_store', $section, 'shopping-cart', 'text-teal-400', 'Inventory / Store'); ?>
                <?php endif; ?>
                <?php if (isFeatureEnabled('cafeteria_meals')): ?>
                    <?php sbLink('cafeteria_meals', $section, 'utensils', 'text-orange-400', 'Cafeteria & Meals'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ HOSTEL & DORMS ══════════════ -->
            <?php if (isFeatureEnabled('hostel') && getStaffAccessLevel('hostel', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Hostel & Dorms'); ?>
                <?php sbLink('hostels', $section, 'home', '', 'Hostel & Dorms'); ?>
            <?php endif; ?>

            <!-- ══════════════ TRANSPORT & LOGISTICS ══════════════ -->
            <?php if (isFeatureEnabled('transport') && getStaffAccessLevel('transport', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Transport & Logistics'); ?>
                <?php sbLink('transport', $section, 'bus', '', 'Transport & Logistics'); ?>
            <?php endif; ?>

            <!-- ══════════════ FINANCE & INVOICING ══════════════ -->
            <?php if (isFeatureEnabled('finance') && getStaffAccessLevel('finance', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Finance & Invoicing'); ?>
                <?php sbLink('finance', $section, 'credit-card', 'text-emerald-400', 'Finance & Invoicing'); ?>
            <?php endif; ?>

            <!-- ══════════════ HEALTHCARE RECORDS ══════════════ -->
            <?php if (isFeatureEnabled('healthcare') && getStaffAccessLevel('healthcare', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Healthcare Records'); ?>
                <?php sbLink('healthcare', $section, 'heart-pulse', 'text-rose-400', 'Healthcare Records'); ?>
            <?php endif; ?>

            <!-- ══════════════ DISCIPLINARY RECORDS ══════════════ -->
            <?php if (isFeatureEnabled('disciplinary') && getStaffAccessLevel('disciplinary', $active_role, $user_uuid, $school_uuid) !== 'none'): ?>
                <?php sbGroupHeader('Disciplinary Records'); ?>
                <?php sbLink('disciplinary', $section, 'shield-alert', 'text-amber-400', 'Disciplinary Records'); ?>
            <?php endif; ?>

            <!-- ══════════════ NOTICE BOARD / NEWS ══════════════ -->
            <?php if (isFeatureEnabled('news_notices')): ?>
                <?php sbGroupHeader('Notice Board / News'); ?>
                <?php sbLink('notice_board', $section, 'megaphone', 'text-purple-400', 'Notice Board / News'); ?>
            <?php endif; ?>

            <!-- ══════════════ SMS & BROADCAST CENTRE ══════════════ -->
            <?php if (isFeatureEnabled('sms_broadcast') && ($active_role === 'School Admin' || $active_role === 'Platform Manager')): ?>
                <?php sbGroupHeader('SMS & Broadcast Centre'); ?>
                <?php sbLink('broadcast', $section, 'send', 'text-cyan-300', 'SMS & Broadcast Centre'); ?>
            <?php endif; ?>

            <!-- ══════════════ EMAIL CENTRE ══════════════ -->
            <?php if (isFeatureEnabled('email_centre')): ?>
                <?php sbGroupHeader('Email Centre'); ?>
                <?php sbLink('email_centre', $section, 'mail', 'text-blue-400', 'Email Centre'); ?>
            <?php endif; ?>

            <!-- ══════════════ IN-APP NOTIFICATIONS ══════════════ -->
            <?php if (isFeatureEnabled('in_app_notifications')): ?>
                <?php sbGroupHeader('In-App Notifications'); ?>
                <?php sbLink('notifications', $section, 'bell', 'text-amber-400', 'In-App Notifications', $_sb_unread_billing); ?>
            <?php endif; ?>

            <!-- ══════════════ MESSAGING & AI CONFIGURATION ══════════════ -->
            <?php if (isFeatureEnabled('essay_ocr') || isFeatureEnabled('consultations')): ?>
                <?php sbGroupHeader('Messaging & AI Configuration'); ?>
                <?php if (isFeatureEnabled('essay_ocr')): ?>
                    <?php sbLink('essay_ocr', $section, 'sparkles', 'text-amber-400', 'AI Essay & OCR'); ?>
                <?php endif; ?>
                <?php if (isFeatureEnabled('consultations')): ?>
                    <?php sbLink('consultations', $section, 'message-square', 'text-pink-400', 'Parent-Teacher Chat'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ ONLINE ADMISSIONS ══════════════ -->
            <?php if (isFeatureEnabled('admissions')): ?>
                <?php sbGroupHeader('Online Admissions'); ?>
                <?php sbLink('admissions', $section, 'file-input', 'text-emerald-400', 'Online Admissions'); ?>
                <?php if ($active_role === 'School Admin' && isFeatureEnabled('primary_ops')): ?>
                    <?php sbLink('primary_ops', $section, 'layers', 'text-purple-400', 'Sessions & Classes'); ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ══════════════ ALUMNI NETWORK ══════════════ -->
            <?php if (isFeatureEnabled('alumni')): ?>
                <?php sbGroupHeader('Alumni Network'); ?>
                <?php sbLink('alumni', $section, 'graduation-cap', 'text-amber-400', 'Alumni Network'); ?>
            <?php endif; ?>

            <!-- ══════════════ SCHOOL SETTINGS & THEME ══════════════ -->
            <?php if ($active_role === 'School Admin' && isFeatureEnabled('settings')): ?>
                <?php sbGroupHeader('School Settings & Theme'); ?>
                <?php sbLink('settings', $section, 'settings', 'text-indigo-400', 'School Settings & Theme', $_sb_unread_billing); ?>
            <?php endif; ?>

            <!-- ══════════════ AUDIT LOG ══════════════ -->
            <?php if (in_array($active_role, ['School Admin', 'Platform Manager'], true)): ?>
                <?php sbGroupHeader('Audit Log'); ?>
                <?php sbLink('audit_log', $section, 'shield-check', 'text-cyan-400', 'Audit Log'); ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Fixed Bottom Section -->
<div class="sidebar-bottom">
    <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl border border-[var(--border-color)] space-y-1">
        <span class="text-[9px] font-bold text-emerald-400 block font-mono truncate"><?php echo htmlspecialchars($_sb_school_subdomain); ?></span>
        <p class="text-[9px] text-[var(--text-secondary)] truncate"><strong class="text-[var(--text-primary)]"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></strong></p>
        <p class="text-[9px] text-[var(--text-secondary)]">Role: <strong class="text-indigo-300"><?php echo htmlspecialchars($active_role); ?></strong></p>
    </div>
</div>