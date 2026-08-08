<?php
// Forgot Password — request a reset link. Subdomain-aware & school-branded,
// mirrors login.php's context resolution so the reset flow stays scoped to
// whichever portal (platform / school / root) the person is on.
require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/subdomain.php';
require_once __DIR__ . '/admin/lib/Helpers.php';
require_once __DIR__ . '/admin/lib/AuditLog.php';
require_once __DIR__ . '/admin/lib/Mailer.php';

$ctx        = resolve_subdomain($pdo);
$is_platform = $ctx['is_platform'];
$is_school   = $ctx['is_school'];
$school      = $ctx['school'];

$brand_name  = 'Zetaphase EduCloud';
$brand_sub   = 'zetaphase.com.ng';
$brand_logo  = 'logo.jpeg';
$brand_color = '#4F46E5';

if ($is_platform) {
    $brand_name = 'Zetaphase — Platform';
    $brand_sub  = 'platform.zetaphase.com.ng';
}
if ($is_school && $school) {
    $brand_name  = $school['name'];
    $brand_sub   = $school['subdomain'] . '.zetaphase.com.ng';
    $brand_logo  = $school['logo_path'] ?? 'logo.jpeg';
    $brand_color = $school['theme_color'] ?? '#4F46E5';
}

$hour = (int)date('H');
$theme_mode = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
$html_class = $theme_mode === 'dark' ? 'dark' : '';

$bg_primary   = $theme_mode === 'light' ? '#FFFFFF' : '#0E1117';
$bg_secondary = $theme_mode === 'light' ? '#F8FAFC' : '#11141B';
$bg_tertiary  = $theme_mode === 'light' ? '#F1F5F9' : '#0A0D12';
$border_color = $theme_mode === 'light' ? '#E2E8F0' : '#1E232D';
$text_primary = $theme_mode === 'light' ? '#0F172A' : '#F1F5F9';
$text_secondary = $theme_mode === 'light' ? '#475569' : '#94A3B8';

$error   = '';
$sent    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired — please refresh and try again.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Throttle: max 3 reset requests per email in a rolling 15 minutes,
            // regardless of whether the account exists (avoids leaking existence
            // through timing/response differences and avoids mail-bombing a user).
            $throttleStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM password_reset_tokens prt
                 JOIN users u ON u.user_uuid = prt.user_uuid
                 WHERE u.email = ? AND prt.created_at > (NOW() - INTERVAL 15 MINUTE)"
            );
            $throttleStmt->execute([$email]);
            $recentCount = (int)$throttleStmt->fetchColumn();

            if ($recentCount >= 3) {
                // Still show the generic success message — don't reveal throttling either.
                $sent = true;
            } else {
                // Scope the lookup exactly like login.php does.
                if ($is_school && $school) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND school_uuid = ? LIMIT 1");
                    $stmt->execute([$email, $school['school_uuid']]);
                } elseif ($is_platform) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'Platform Manager' LIMIT 1");
                    $stmt->execute([$email]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                }
                $user = $stmt->fetch();

                if ($user) {
                    $rawToken  = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $tokenUuid = 'prt-' . bin2hex(random_bytes(8));
                    $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
                    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

                    $pdo->prepare(
                        "INSERT INTO password_reset_tokens (token_uuid, user_uuid, token_hash, requested_ip, expires_at)
                         VALUES (?, ?, ?, ?, ?)"
                    )->execute([$tokenUuid, $user['user_uuid'], $tokenHash, $ip, $expiresAt]);

                    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host    = $_SERVER['HTTP_HOST'] ?? $brand_sub;
                    $resetUrl = "{$scheme}://{$host}/reset-password.php?token=" . urlencode($rawToken);

                    $schoolSettings = [];
                    if (!empty($user['school_uuid'])) {
                        $ssStmt = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid = ? LIMIT 1");
                        $ssStmt->execute([$user['school_uuid']]);
                        $schoolSettings = $ssStmt->fetch() ?: [];
                    }

                    $bodyHtml = '<p>Hi ' . htmlspecialchars($user['name']) . ',</p>'
                        . '<p>We received a request to reset your password for ' . htmlspecialchars($brand_name) . '.</p>'
                        . '<p><a href="' . htmlspecialchars($resetUrl) . '">Click here to set a new password</a>. '
                        . 'This link expires in 30 minutes.</p>'
                        . '<p>If you did not request this, you can safely ignore this email — your password will not change.</p>';

                    Mailer::send($schoolSettings, $user['email'], $user['name'], 'Reset your password', $bodyHtml);

                    try {
                        AuditLog::write($pdo, $user['school_uuid'] ?? '', $user['user_uuid'], 'auth.password_reset_requested', $user['user_uuid'], 'Password reset link requested');
                    } catch (Exception $e) {}
                }
                // Always show the same success state, whether or not the email matched.
                $sent = true;
            }
        } catch (PDOException $e) {
            error_log('Forgot-password error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth <?php echo $html_class; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($brand_name); ?> — Reset Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo htmlspecialchars($brand_logo); ?>">
    <script src="assets/js/lucide.min.js"></script>
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
        .brand-btn  { background-color: var(--brand-color); }
        .brand-btn:hover { filter: brightness(1.15); }
        .brand-ring:focus { outline-color: var(--brand-color); border-color: var(--brand-color); }
        .brand-text { color: var(--brand-color); }
    </style>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <header class="p-6 border-b border-[var(--border-color)] max-w-5xl mx-auto w-full">
        <div class="flex items-center space-x-3">
            <img src="logo.jpeg" alt="Logo" class="w-10 h-10 rounded-xl object-cover border border-[var(--border-color)] shadow-lg"
                 onerror="this.onerror=null; this.style.display='none';">
            <div>
                <span class="font-extrabold text-[var(--text-primary)] text-sm tracking-tight block"><?php echo htmlspecialchars($brand_name); ?></span>
                <span class="text-[10px] brand-text block font-mono"><?php echo htmlspecialchars($brand_sub); ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto w-full px-6 py-10 space-y-6 flex-1">

        <div class="text-center space-y-2">
            <h1 class="text-xl font-bold text-[var(--text-primary)] tracking-tight">Reset your password</h1>
            <p class="text-xs text-[var(--text-secondary)]">Enter the email address on your account and we'll send you a reset link.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 p-3.5 rounded-xl text-xs text-rose-400 flex items-center space-x-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($sent): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-xl text-xs text-emerald-400 flex items-start space-x-2">
                <i data-lucide="mail-check" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span>If an account exists for that email, a reset link is on its way. Check your inbox (and spam folder) — the link expires in 30 minutes.</span>
            </div>
            <div class="text-center">
                <a href="login.php" class="text-[11px] text-[var(--text-secondary)] hover:text-[var(--text-primary)] font-semibold transition-all">&larr; Back to login</a>
            </div>
        <?php else: ?>
            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-6 rounded-2xl shadow-2xl space-y-4">
                <form action="forgot-password.php" method="POST" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Email Address</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-3 text-[var(--text-secondary)]"><i data-lucide="mail" class="w-4 h-4"></i></span>
                            <input type="email" name="email" required autofocus
                                placeholder="you@example.com"
                                class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl pl-10 pr-4 py-2.5 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all font-mono brand-ring">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2.5 brand-btn text-white rounded-xl text-xs font-bold transition-all shadow-lg flex items-center justify-center space-x-2">
                        <span>Send Reset Link</span>
                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                    </button>
                    <div class="text-center pt-1">
                        <a href="login.php" class="text-[11px] text-[var(--text-secondary)] hover:text-[var(--text-primary)] font-semibold transition-all">&larr; Back to login</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <footer class="p-6 text-center text-[11px] text-[var(--text-secondary)] border-t border-[var(--border-color)]">
        &copy; <?php echo date('Y'); ?> Zetaphase EduCloud &mdash; zetaphase.com.ng
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
