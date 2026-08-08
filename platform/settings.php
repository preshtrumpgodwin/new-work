<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/security.php';
    secure_session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/Crypto.php';
require_once __DIR__ . '/../admin/lib/Helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['platform_error'] = 'Your session expired — please try again.';
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'settings.php'));
    exit;
}

/**
 * School Settings Page (Platform Manager Version)
 * Full settings: Branding, Report Card, Policies, SMTP, SMS, WhatsApp, Payment, AI
 * With proper logo upload handling and dynamic Assessment Configuration
 */

// Platform Manager theme preference
$platform_theme = $_SESSION['platform_theme'] ?? 'auto';
if ($platform_theme === 'auto') {
    $hour = (int)date('H');
    $platform_theme = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
}

// Override school theme with platform theme for the settings page
$theme_mode = $platform_theme;

$school_uuid = $_GET['school_uuid'] ?? '';
$referrer = isset($_GET['from']) ? urldecode($_GET['from']) : 'index.php?page=tenants';

if (empty($school_uuid)) {
    header('Location: ' . $referrer);
    exit;
}

// ── Fetch school and settings ──────────────────────────────────────────────
$school = [];
$school_settings = [];
$report_card_config = [];
$roster_classes = [];
$sessions = [];
$terms = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE school_uuid = ? LIMIT 1");
    $stmt->execute([$school_uuid]);
    $school = $stmt->fetch();
    
    $stmt2 = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid = ? LIMIT 1");
    $stmt2->execute([$school_uuid]);
    $school_settings = $stmt2->fetch();
    
    // Parse report card config
    if (!empty($school['report_card_config_json'])) {
        $rc = json_decode($school['report_card_config_json'], true);
        if (is_array($rc)) {
            $report_card_config = $rc;
        }
    }

    // Fetch academic classes
    $cls = $pdo->prepare("SELECT class_name FROM academic_classes WHERE school_uuid = ? ORDER BY id ASC");
    $cls->execute([$school_uuid]);
    $roster_classes = $cls->fetchAll(PDO::FETCH_COLUMN);
    if (empty($roster_classes)) $roster_classes = ['JSS1','JSS2','JSS3','SSS1','SSS2','SSS3'];

    // Fetch sessions & terms
    $sess = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid = ? ORDER BY id DESC");
    $sess->execute([$school_uuid]);
    $sessions = $sess->fetchAll(PDO::FETCH_COLUMN);

    $trm = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid = ? ORDER BY id ASC");
    $trm->execute([$school_uuid]);
    $terms = $trm->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {}

if (!$school) {
    header('Location: ' . $referrer);
    exit;
}

// Default report card config
$report_card_defaults = [
    'show_photo' => 1,
    'show_logo' => 1,
    'show_letterhead' => 1,
    'show_signature' => 1,
    'show_attendance' => 1,
    'show_position' => 1,
    'show_class_avg' => 1,
    'show_grade_scale' => 1,
    'show_teacher_comment' => 1,
    'show_principal_comment' => 1,
    'show_healthcare' => 1,
];
$report_card_config = array_merge($report_card_defaults, $report_card_config);

// ── Handle form submissions (one action per section, own redirect) ─────────
$__redirect = 'settings.php?school_uuid=' . urlencode($school_uuid);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_branding'])) {
    $school_name = trim($_POST['school_name'] ?? '');
    $motto = trim($_POST['motto'] ?? '');
    $theme_color = trim($_POST['theme_color'] ?? '#4F46E5');
    $theme_mode = trim($_POST['theme_mode'] ?? 'dark');
    $existing_logo = trim($_POST['existing_logo'] ?? '');

    $upload_dir = __DIR__ . '/../uploads/school_logos/';
    $photo_error = null;
    $logo_path = handle_image_upload('logo_upload', $upload_dir, 'logo_' . $school_uuid . '_', $existing_logo, 5_242_880, $photo_error);
    if (!empty($photo_error)) {
        $_SESSION['platform_error'] = $photo_error;
        header('Location: ' . $__redirect); exit;
    }

    try {
        $pdo->prepare("UPDATE schools SET name = ?, theme_color = ?, theme_mode = ?, logo_path = ? WHERE school_uuid = ?")
            ->execute([$school_name, $theme_color, $theme_mode, $logo_path, $school_uuid]);
        $pdo->prepare("UPDATE school_settings SET school_name = ?, motto = ? WHERE school_uuid = ?")
            ->execute([$school_name, $motto, $school_uuid]);
        $_SESSION['platform_success'] = 'Branding & identity saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update branding', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_report_card'])) {
    $report_card_config = [];
    foreach (array_keys($report_card_defaults) as $key) {
        $report_card_config[$key] = isset($_POST['rc_show_' . $key]) ? 1 : 0;
    }
    $report_card_json = json_encode($report_card_config);
    try {
        $pdo->prepare("UPDATE schools SET report_card_config_json = ? WHERE school_uuid = ?")
            ->execute([$report_card_json, $school_uuid]);
        $_SESSION['platform_success'] = 'Report card display settings saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update report card settings', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_contact'])) {
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    try {
        $pdo->prepare("UPDATE school_settings SET address = ?, phone = ?, email = ?, website = ? WHERE school_uuid = ?")
            ->execute([$address, $phone, $email, $website, $school_uuid]);
        $_SESSION['platform_success'] = 'Contact information saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update contact information', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_academic'])) {
    $current_session = trim($_POST['current_session'] ?? '');
    $current_term = trim($_POST['current_term'] ?? '');
    $principal_name = trim($_POST['principal_name'] ?? '');
    try {
        $pdo->prepare("UPDATE school_settings SET current_session = ?, current_term = ?, principal_name = ? WHERE school_uuid = ?")
            ->execute([$current_session, $current_term, $principal_name, $school_uuid]);
        $_SESSION['platform_success'] = 'Academic configuration saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update academic configuration', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_smtp'])) {
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = (int)($_POST['smtp_port'] ?? 587);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_encryption = trim($_POST['smtp_encryption'] ?? 'tls');
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');
    $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
    try {
        $pdo->prepare("UPDATE school_settings SET smtp_host=?, smtp_port=?, smtp_username=?, smtp_password=?, smtp_encryption=?, smtp_from_name=?, smtp_from_email=? WHERE school_uuid=?")
            ->execute([$smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $smtp_from_name, $smtp_from_email, $school_uuid]);
        $_SESSION['platform_success'] = 'SMTP email settings saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update SMTP settings', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_sms'])) {
    $sms_provider = trim($_POST['sms_provider'] ?? 'termii');
    $sms_api_key = trim($_POST['sms_api_key'] ?? '');
    $sms_sender_id = trim($_POST['sms_sender_id'] ?? '');
    try {
        $pdo->prepare("UPDATE school_settings SET sms_provider=?, sms_api_key=?, sms_sender_id=? WHERE school_uuid=?")
            ->execute([$sms_provider, $sms_api_key, $sms_sender_id, $school_uuid]);
        $_SESSION['platform_success'] = 'SMS gateway settings saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update SMS settings', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_whatsapp'])) {
    $whatsapp_provider = trim($_POST['whatsapp_provider'] ?? 'twilio');
    $whatsapp_account_sid = trim($_POST['whatsapp_account_sid'] ?? '');
    $whatsapp_auth_token = trim($_POST['whatsapp_auth_token'] ?? '');
    $whatsapp_from_number = trim($_POST['whatsapp_from_number'] ?? '');
    $whatsapp_meta_token = trim($_POST['whatsapp_meta_token'] ?? '');
    $whatsapp_meta_phone_id = trim($_POST['whatsapp_meta_phone_id'] ?? '');
    try {
        $pdo->prepare("UPDATE school_settings SET whatsapp_provider=?, whatsapp_account_sid=?, whatsapp_auth_token=?, whatsapp_from_number=?, whatsapp_meta_token=?, whatsapp_meta_phone_id=? WHERE school_uuid=?")
            ->execute([$whatsapp_provider, $whatsapp_account_sid, $whatsapp_auth_token, $whatsapp_from_number, $whatsapp_meta_token, $whatsapp_meta_phone_id, $school_uuid]);
        $_SESSION['platform_success'] = 'WhatsApp settings saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update WhatsApp settings', $e);
    }
    header('Location: ' . $__redirect); exit;
}

// ── Payment Gateways (Paystack + Flutterwave) — moved here from school admin ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_payment'])) {
    $paystack_public_key = trim($_POST['paystack_public_key'] ?? '');
    $paystack_secret_key_raw = trim($_POST['paystack_secret_key'] ?? '');
    $flutterwave_public_key = trim($_POST['flutterwave_public_key'] ?? '');
    $flutterwave_secret_key_raw = trim($_POST['flutterwave_secret_key'] ?? '');
    $payments_enabled = isset($_POST['payments_enabled']) ? 1 : 0;
    $flutterwave_enabled = isset($_POST['flutterwave_enabled']) ? 1 : 0;

    // Keep existing (already-encrypted) value if left blank; otherwise encrypt the new one.
    $cur = $pdo->prepare("SELECT paystack_secret_key, flutterwave_secret_key FROM school_settings WHERE school_uuid = ?");
    $cur->execute([$school_uuid]);
    $cur_row = $cur->fetch() ?: [];

    $paystack_secret_key = $paystack_secret_key_raw === ''
        ? (string)($cur_row['paystack_secret_key'] ?? '')
        : Crypto::encrypt($paystack_secret_key_raw);

    $flutterwave_secret_key = $flutterwave_secret_key_raw === ''
        ? (string)($cur_row['flutterwave_secret_key'] ?? '')
        : Crypto::encrypt($flutterwave_secret_key_raw);

    try {
        $pdo->prepare("UPDATE school_settings SET
                paystack_public_key = ?, paystack_secret_key = ?, payments_enabled = ?,
                flutterwave_public_key = ?, flutterwave_secret_key = ?, flutterwave_enabled = ?
            WHERE school_uuid = ?")
            ->execute([
                $paystack_public_key, $paystack_secret_key, $payments_enabled,
                $flutterwave_public_key, $flutterwave_secret_key, $flutterwave_enabled,
                $school_uuid
            ]);
        $_SESSION['platform_success'] = 'Payment gateway settings saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update payment gateway settings', $e);
    }
    header('Location: ' . $__redirect); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_ai'])) {
    $ai_provider = trim($_POST['ai_provider'] ?? 'openai');
    $ai_api_key = trim($_POST['ai_api_key'] ?? '');
    $ai_model = trim($_POST['ai_model'] ?? 'gpt-4o-mini');
    $ai_essay_prompt = trim($_POST['ai_essay_prompt'] ?? '');
    $ai_lesson_prompt = trim($_POST['ai_lesson_prompt'] ?? '');
    try {
        $pdo->prepare("UPDATE school_settings SET ai_provider=?, ai_api_key=?, ai_model=?, ai_essay_prompt=?, ai_lesson_prompt=? WHERE school_uuid=?")
            ->execute([$ai_provider, $ai_api_key, $ai_model, $ai_essay_prompt, $ai_lesson_prompt, $school_uuid]);
        $_SESSION['platform_success'] = 'AI configuration saved.';
    } catch (Exception $e) {
        $_SESSION['platform_error'] = safe_error('Failed to update AI configuration', $e);
    }
    header('Location: ' . $__redirect); exit;
}

// ── Theme CSS variables ─────────────────────────────────────────────────────
$bg_primary   = $theme_mode === 'light' ? '#FFFFFF' : '#0E1117';
$bg_secondary = $theme_mode === 'light' ? '#F8FAFC' : '#11141B';
$bg_tertiary  = $theme_mode === 'light' ? '#F1F5F9' : '#0A0D12';
$border_color = $theme_mode === 'light' ? '#E2E8F0' : '#1E232D';
$text_primary = $theme_mode === 'light' ? '#0F172A' : '#F1F5F9';
$text_secondary = $theme_mode === 'light' ? '#475569' : '#94A3B8';
$brand_color = $school['theme_color'] ?? '#4F46E5';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme_mode; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school['name'] ?? 'School'); ?> — Settings</title>
    <link rel="shortcut icon" type="image/jpeg" href="../logo.jpeg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <style>
        :root {
            <?php echo accent_shade_vars($brand_color); ?>
            --bg-primary: <?php echo $bg_primary; ?>;
            --bg-secondary: <?php echo $bg_secondary; ?>;
            --bg-tertiary: <?php echo $bg_tertiary; ?>;
            --border-color: <?php echo $border_color; ?>;
            --text-primary: <?php echo $text_primary; ?>;
            --text-secondary: <?php echo $text_secondary; ?>;
        }
        .brand-accent { color: var(--brand-color); }
        .brand-bg { background-color: var(--brand-color); }
        .brand-bg-soft { background-color: color-mix(in srgb, var(--brand-color) 10%, transparent); border-color: color-mix(in srgb, var(--brand-color) 25%, transparent); }
        .bg-primary { background-color: var(--bg-primary); }
        .bg-secondary { background-color: var(--bg-secondary); }
        .bg-tertiary { background-color: var(--bg-tertiary); }
        .border-theme { border-color: var(--border-color); }
        .text-primary { color: var(--text-primary); }
        .text-secondary { color: var(--text-secondary); }
        .brand-ring:focus { outline-color: var(--brand-color); border-color: var(--brand-color); }
    </style>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen flex flex-col font-sans selection:bg-indigo-500 selection:text-white">

    <header class="border-b border-[var(--border-color)] bg-[var(--bg-secondary)]/75 backdrop-blur-md sticky top-0 z-40 px-6 py-4">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="<?php echo htmlspecialchars($referrer); ?>" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <img src="../logo.jpeg" alt="Logo" class="w-8 h-8 rounded-lg object-cover border border-[var(--border-color)]">
                <div>
                    <span class="font-extrabold text-[var(--text-primary)] text-sm tracking-tight">
                        <?php echo htmlspecialchars($school['name'] ?? 'School'); ?> Settings
                    </span>
                    <span class="text-[10px] brand-accent block font-mono">Platform Manager Configuration</span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto w-full px-6 py-10 space-y-8 flex-1">
        
        <?php if (!empty($_SESSION['platform_success'])): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-xl text-xs text-emerald-400 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                <span><?php echo htmlspecialchars($_SESSION['platform_success']); ?></span>
            </div>
            <?php unset($_SESSION['platform_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['platform_error'])): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 p-4 rounded-xl text-xs text-rose-400 flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span><?php echo htmlspecialchars($_SESSION['platform_error']); ?></span>
            </div>
            <?php unset($_SESSION['platform_error']); ?>
        <?php endif; ?>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_branding" value="1">
                <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($school['logo_path'] ?? ''); ?>">
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-indigo-400">1. Branding & Identity</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">School Name</label>
                            <input type="text" name="school_name" value="<?php echo htmlspecialchars($school['name'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Motto</label>
                            <input type="text" name="motto" value="<?php echo htmlspecialchars($school_settings['motto'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Upload Logo</label>
                            <input type="file" name="logo_upload" accept="image/jpeg,image/png,image/webp,image/gif" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs file:bg-indigo-600 file:text-white file:rounded-lg file:border-0 file:px-3 file:py-1">
                            <p class="text-[9px] text-[var(--text-secondary)] mt-1">Max 2MB. JPEG, PNG, WebP, GIF.</p>
                            <?php 
                            $logo_path = $school['logo_path'] ?? '';
                            if (!empty($logo_path)): ?>
                                <div class="mt-2 p-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl">
                                    <p class="text-[9px] text-[var(--text-secondary)] mb-1">Current Logo:</p>
                                    <img src="<?php echo htmlspecialchars(asset_url($logo_path)); ?>" 
                                        alt="School Logo" 
                                        class="max-h-16 w-auto object-contain rounded-lg border border-[var(--border-color)] bg-white p-1">
                                    <p class="text-[9px] text-[var(--text-secondary)] mt-1 font-mono"><?php echo htmlspecialchars(basename($logo_path)); ?></p>
                                </div>
                            <?php else: ?>
                                <p class="text-[9px] text-[var(--text-secondary)] mt-1">No logo uploaded yet.</p>
                            <?php endif; ?>                        
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Accent Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="theme_color" value="<?php echo htmlspecialchars($school['theme_color'] ?? '#4F46E5'); ?>" 
                                       class="w-10 h-9 rounded-lg border border-[var(--border-color)] cursor-pointer bg-transparent p-0.5">
                                <span class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($school['theme_color'] ?? '#4F46E5'); ?></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Theme Mode</label>
                            <select name="theme_mode" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <option value="dark" <?php echo ($school['theme_mode'] ?? 'dark') === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                                <option value="light" <?php echo ($school['theme_mode'] ?? 'dark') === 'light' ? 'selected' : ''; ?>>Light Mode</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_report_card" value="1">
                <!-- 2. Report Card Display -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-emerald-400">2. Report Card Display</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($report_card_defaults as $key => $default): ?>
                            <label class="flex items-center space-x-3 p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs cursor-pointer">
                                <input type="checkbox" name="rc_show_<?php echo $key; ?>" 
                                       <?php echo ($report_card_config[$key] ?? 1) ? 'checked' : ''; ?> 
                                       class="w-4 h-4 accent-indigo-600">
                                <span class="font-semibold text-[var(--text-primary)]">
                                    <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_contact" value="1">
                <!-- 3. Contact Info -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-amber-400">3. Contact Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($school_settings['address'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($school_settings['phone'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold uppercase mb-1">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($school_settings['email'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_academic" value="1">
                <!-- 4. Academic Configuration -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-purple-400">4. Academic Configuration</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Current Session</label>
                            <input type="text" name="current_session" value="<?php echo htmlspecialchars($school_settings['current_session'] ?? '2025/2026'); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Current Term</label>
                            <input type="text" name="current_term" value="<?php echo htmlspecialchars($school_settings['current_term'] ?? 'First Term'); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Principal Name</label>
                            <input type="text" name="principal_name" value="<?php echo htmlspecialchars($school_settings['principal_name'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_smtp" value="1">
                <!-- 6. SMTP Email -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-blue-400">6. SMTP Email</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Host</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($school_settings['smtp_host'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Port</label>
                            <input type="number" name="smtp_port" value="<?php echo $school_settings['smtp_port'] ?? '587'; ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Username</label>
                            <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($school_settings['smtp_username'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Password</label>
                            <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($school_settings['smtp_password'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Encryption</label>
                            <select name="smtp_encryption" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <option value="tls" <?php echo (($school_settings['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">From Name</label>
                            <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($school_settings['smtp_from_name'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase mb-1">From Email</label>
                            <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($school_settings['smtp_from_email'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_sms" value="1">
                <!-- 7. SMS Gateway -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-green-400">7. SMS Gateway</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Provider</label>
                            <select name="sms_provider" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <option value="termii" <?php echo (($school_settings['sms_provider'] ?? 'termii') === 'termii') ? 'selected' : ''; ?>>Termii</option>
                                <option value="africas_talking">Africa's Talking</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">API Key</label>
                            <input type="password" name="sms_api_key" value="<?php echo htmlspecialchars($school_settings['sms_api_key'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Sender ID</label>
                            <input type="text" name="sms_sender_id" value="<?php echo htmlspecialchars($school_settings['sms_sender_id'] ?? ''); ?>" maxlength="11" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_whatsapp" value="1">
                <!-- 8. WhatsApp -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-emerald-400">8. WhatsApp</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Provider</label>
                            <select name="whatsapp_provider" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <option value="twilio" <?php echo (($school_settings['whatsapp_provider'] ?? 'twilio') === 'twilio') ? 'selected' : ''; ?>>Twilio</option>
                                <option value="meta">Meta Cloud API</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Account SID</label>
                            <input type="password" name="whatsapp_account_sid" value="<?php echo htmlspecialchars($school_settings['whatsapp_account_sid'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Auth Token</label>
                            <input type="password" name="whatsapp_auth_token" value="<?php echo htmlspecialchars($school_settings['whatsapp_auth_token'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">From Number</label>
                            <input type="text" name="whatsapp_from_number" value="<?php echo htmlspecialchars($school_settings['whatsapp_from_number'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Meta Token</label>
                            <input type="password" name="whatsapp_meta_token" value="<?php echo htmlspecialchars($school_settings['whatsapp_meta_token'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Meta Phone ID</label>
                            <input type="text" name="whatsapp_meta_phone_id" value="<?php echo htmlspecialchars($school_settings['whatsapp_meta_phone_id'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_payment" value="1">
                <!-- 9. Payment Gateways -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-indigo-400">9. Payment Gateways</h3>
                    <p class="text-[11px] text-[var(--text-secondary)]">Paystack and Flutterwave let parents pay invoices online from the Parent Portal. These are configured here by the platform manager only.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Paystack Public</label>
                            <input type="text" name="paystack_public_key" value="<?php echo htmlspecialchars($school_settings['paystack_public_key'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Paystack Secret</label>
                            <input type="password" name="paystack_secret_key" placeholder="<?php echo !empty($school_settings['paystack_secret_key']) ? '•••••••• (leave blank to keep current)' : 'sk_live_...'; ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div class="flex items-end pb-1.5">
                            <label class="flex items-center gap-2 text-[10px] font-bold uppercase"><input type="checkbox" name="payments_enabled" <?php echo !empty($school_settings['payments_enabled']) ? 'checked' : ''; ?> class="w-3.5 h-3.5 accent-indigo-600"> Enable Paystack for parents</label>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Flutterwave Public</label>
                            <input type="text" name="flutterwave_public_key" value="<?php echo htmlspecialchars($school_settings['flutterwave_public_key'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Flutterwave Secret</label>
                            <input type="password" name="flutterwave_secret_key" placeholder="<?php echo !empty($school_settings['flutterwave_secret_key']) ? '•••••••• (leave blank to keep current)' : 'FLWSECK_...'; ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div class="flex items-end pb-1.5">
                            <label class="flex items-center gap-2 text-[10px] font-bold uppercase"><input type="checkbox" name="flutterwave_enabled" <?php echo !empty($school_settings['flutterwave_enabled']) ? 'checked' : ''; ?> class="w-3.5 h-3.5 accent-orange-600"> Enable Flutterwave for parents</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4">
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_ai" value="1">
                <!-- 10. AI Configuration -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-purple-400">10. AI API Configuration</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">AI Provider</label>
                            <select name="ai_provider" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <option value="openai" <?php echo (($school_settings['ai_provider'] ?? 'openai') === 'openai') ? 'selected' : ''; ?>>OpenAI</option>
                                <option value="google">Google AI</option>
                                <option value="anthropic">Anthropic Claude</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">API Key</label>
                            <input type="password" name="ai_api_key" value="<?php echo htmlspecialchars($school_settings['ai_api_key'] ?? ''); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase mb-1">Model</label>
                            <input type="text" name="ai_model" value="<?php echo htmlspecialchars($school_settings['ai_model'] ?? 'gpt-4o-mini'); ?>" 
                                   class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-[10px] font-bold uppercase mb-1">Essay Marking Prompt (optional)</label>
                            <textarea name="ai_essay_prompt" rows="2" 
                                      class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($school_settings['ai_essay_prompt'] ?? ''); ?></textarea>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-[10px] font-bold uppercase mb-1">Lesson Plan Prompt (optional)</label>
                            <textarea name="ai_lesson_prompt" rows="2" 
                                      class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($school_settings['ai_lesson_prompt'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save</button>
            </form>
        </div>

    </main>

    <footer class="border-t border-[var(--border-color)] bg-[var(--bg-secondary)]/40 py-6 px-6 text-center text-xs text-[var(--text-secondary)]">
        <p>&copy; <?php echo date('Y'); ?> Zetaphase EduCloud. Platform Manager Configuration.</p>
    </footer>

    <script>
    lucide.createIcons();
    </script>
</body>
</html>
