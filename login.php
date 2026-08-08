<?php
// Unified Multi-Role Login Portal — Subdomain-Aware & School-Branded with Auto Theme
require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/subdomain.php';
require_once __DIR__ . '/admin/lib/Helpers.php';
require_once __DIR__ . '/admin/lib/AuditLog.php';

// ── Resolve context from subdomain ──────────────────────────────────────────
$ctx        = resolve_subdomain($pdo);
$is_platform = $ctx['is_platform'];
$is_school   = $ctx['is_school'];
$school      = $ctx['school'];     // null unless on a school subdomain

// Branding defaults
$brand_name    = 'Zetaphase EduCloud';
$brand_sub     = 'zetaphase.com.ng';
$brand_logo    = 'logo.jpeg';
$brand_color   = '#4F46E5';
$school_uuid_ctx = '';
$theme_mode    = 'auto'; // auto, light, dark

if ($is_platform) {
    $brand_name  = 'Zetaphase — Platform';
    $brand_sub   = 'platform.zetaphase.com.ng';
    $brand_logo  = 'logo.jpeg';
}

if ($is_school && $school) {
    $brand_name      = $school['name'];
    $brand_sub       = $school['subdomain'] . '.zetaphase.com.ng';
    $brand_logo      = $school['logo_path'] ?? 'logo.jpeg';
    $brand_color     = $school['theme_color'] ?? '#4F46E5';
    $theme_mode      = $school['theme_mode'] ?? 'auto';
    $school_uuid_ctx = $school['school_uuid'];
}

// Auto theme detection based on Nigeria time
$hour = (int)date('H');
$theme_mode = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
$html_class = $theme_mode === 'dark' ? 'dark' : '';


// ── Redirect already-authenticated users ───────────────────────────────────
if (isset($_SESSION['user_uuid'])) {
    if ($_SESSION['role'] === 'Platform Manager') {
        header('Location: platform/index.php'); exit;
    } elseif ($_SESSION['role'] === 'Student') {
        header('Location: student-portal.php'); exit;
    } elseif ($_SESSION['role'] === 'Parent') {
        header('Location: parent-portal.php'); exit;
    } elseif ($_SESSION['role'] === 'School Admin') {
        header('Location: admin/dashboard.php'); exit;
    } else {
        header('Location: staff/index.php'); exit;
    }
}

// ── Login POST handler ──────────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired — please try again.';
    } elseif (!empty($email) && !empty($password)) {
        if (is_login_locked_out($pdo, $email)) {
            $error = 'Too many failed attempts. This account is temporarily locked — please try again in 15 minutes.';
        } else {
        try {
            // On a school subdomain, restrict login to users belonging to that school
            if ($is_school && $school) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND school_uuid = ? LIMIT 1");
                $stmt->execute([$email, $school['school_uuid']]);
            } elseif ($is_platform) {
                // Platform subdomain: only Platform Manager accounts
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'Platform Manager' LIMIT 1");
                $stmt->execute([$email]);
            } else {
                // Root domain fallback (direct file access) — unrestricted
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
            }

            $user = $stmt->fetch();
            $password_ok = $user && password_verify($password, $user['password_hash']);

            // Block access if the tenant school has been suspended by the platform manager
            if ($password_ok && !empty($user['school_uuid']) && $user['role'] !== 'Platform Manager') {
                $schStmt = $pdo->prepare("SELECT status, name FROM schools WHERE school_uuid = ? LIMIT 1");
                $schStmt->execute([$user['school_uuid']]);
                $schRow = $schStmt->fetch();
                if ($schRow && $schRow['status'] === 'Suspended') {
                    $password_ok = false;
                    $error = 'This school account has been suspended by the platform administrator. Please contact support to reactivate access.';
                }
            }

            if ($password_ok) {
                reset_failed_login($pdo, $email);

                // Regenerate the session ID on privilege change to prevent session fixation.
                session_regenerate_id(true);

                $_SESSION['user_uuid']   = $user['user_uuid'];
                $_SESSION['school_uuid'] = $user['school_uuid'];
                $_SESSION['name']        = $user['name'];
                $_SESSION['email']       = $user['email'];
                $_SESSION['role']        = $user['role'];

                try {
                    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_uuid = ?")->execute([$user['user_uuid']]);
                    AuditLog::write(
                        $pdo,
                        $user['school_uuid'] ?? '',
                        $user['user_uuid'],
                        'auth.login',
                        $user['user_uuid'],
                        'Authenticated via ' . ($is_platform ? 'platform' : ($is_school ? $ctx['subdomain'] : 'root')) . ' portal'
                    );
                } catch (Exception $e) {}

                if (!empty($user['must_reset_password'])) {
                    // A temp password that's never been claimed shouldn't stay valid
                    // forever. If it's expired, don't let it authenticate at all —
                    // send the person back to login with a clear message instead of
                    // silently honoring a months-old temp password.
                    if (!empty($user['temp_password_expires_at']) && strtotime($user['temp_password_expires_at']) < time()) {
                        session_destroy();
                        header('Location: login.php?error=' . urlencode('Your temporary password has expired. Please ask your school admin to issue a new one.'));
                        exit;
                    }
                    header('Location: change-password.php'); exit;
                }

                if ($user['role'] === 'Platform Manager') {
                    header('Location: platform/index.php');
                } elseif ($user['role'] === 'Student') {
                    header('Location: student-portal.php');
                } elseif ($user['role'] === 'Parent') {
                    header('Location: parent-portal.php');
                } elseif ($user['role'] === 'School Admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    // Every other role (Teacher, Bursar, and any other staff
                    // role) lands on the standalone staff portal, not the
                    // admin dashboard.
                    header('Location: staff/index.php');
                }
                exit;
            } else {
                if ($user) {
                    record_failed_login($pdo, $email);
                    try {
                        AuditLog::write($pdo, $user['school_uuid'] ?? '', $user['user_uuid'] ?? '', 'auth.login_failed', '', 'Failed login attempt for ' . $email);
                    } catch (Exception $e) {}
                }
                if (empty($error)) {
                    $error = $is_platform
                        ? 'Invalid credentials or insufficient access level.'
                        : 'Invalid email address or password.';
                }
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Something went wrong while signing you in. Please try again.';
        }
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

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
    <title><?php echo htmlspecialchars($brand_name); ?> — Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        .brand-bg-soft { background-color: color-mix(in srgb, var(--brand-color) 10%, transparent); border-color: color-mix(in srgb, var(--brand-color) 25%, transparent); }
        .bg-primary { background-color: var(--bg-primary); }
        .bg-secondary { background-color: var(--bg-secondary); }
        .bg-tertiary { background-color: var(--bg-tertiary); }
        .border-theme { border-color: var(--border-color); }
        .text-primary { color: var(--text-primary); }
        .text-secondary { color: var(--text-secondary); }
    </style>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- Header -->
    <header class="p-6 border-b border-[var(--border-color)] max-w-5xl mx-auto w-full">
        <div class="flex items-center space-x-3">
            <?php if (!empty($brand_logo) && $brand_logo !== 'logo.jpeg'): ?>
                <img src="<?php echo htmlspecialchars(asset_url($brand_logo)); ?>" alt="Logo" class="w-10 h-10 rounded-xl object-cover border border-[var(--border-color)] shadow-lg">
            <?php else: ?>
                <img src="logo.jpeg" alt="Logo" class="w-10 h-10 rounded-xl object-cover border border-[var(--border-color)] shadow-lg"
                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg brand-btn" style="display:none;">
                    <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                </div>
            <?php endif; ?>
            <div>
                <span class="font-extrabold text-[var(--text-primary)] text-sm tracking-tight block"><?php echo htmlspecialchars($brand_name); ?></span>
                <span class="text-[10px] brand-text block font-mono"><?php echo htmlspecialchars($brand_sub); ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto w-full px-6 py-10 space-y-6 flex-1">

        <!-- Title -->
        <div class="text-center space-y-2">
            <?php if ($is_platform): ?>
                <div class="inline-flex items-center space-x-2 brand-bg-soft border px-3 py-1 rounded-full text-[10px] font-bold brand-text mb-2">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                    <span>Platform Manager Access Only</span>
                </div>
                <h1 class="text-xl font-bold text-[var(--text-primary)] tracking-tight">Platform Control Centre</h1>
                <p class="text-xs text-[var(--text-secondary)]">This portal is restricted to authorised Zetaphase platform administrators.</p>
            <?php elseif ($is_school && $school): ?>
                <h1 class="text-xl font-bold text-[var(--text-primary)] tracking-tight">Welcome to <?php echo htmlspecialchars($school['name']); ?></h1>
                <p class="text-xs text-[var(--text-secondary)]">Log in to access the staff dashboard, or use the links below to apply.</p>
            <?php else: ?>
                <h1 class="text-xl font-bold text-[var(--text-primary)] tracking-tight">Portal Authentication</h1>
                <p class="text-xs text-[var(--text-secondary)]">Log in to access your school workspace.</p>
            <?php endif; ?>
        </div>

        <!-- Error -->
        <?php if (!empty($error)): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 p-3.5 rounded-xl text-xs text-rose-400 flex items-center space-x-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-6 rounded-2xl shadow-2xl space-y-4">
            <form action="login.php" method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-3 text-[var(--text-secondary)]"><i data-lucide="mail" class="w-4 h-4"></i></span>
                        <input type="email" id="emailInput" name="email" required
                            placeholder="<?php echo $is_school ? 'name@' . $ctx['subdomain'] . '.edu.ng' : 'email@school.ng'; ?>"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl pl-10 pr-4 py-2.5 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all font-mono brand-ring">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-3 text-[var(--text-secondary)]"><i data-lucide="key-round" class="w-4 h-4"></i></span>
                        <input type="password" id="passwordInput" name="password" required
                            placeholder="••••••••••••"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl pl-10 pr-4 py-2.5 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all font-mono brand-ring">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 brand-btn text-white rounded-xl text-xs font-bold transition-all shadow-lg flex items-center justify-center space-x-2">
                    <span>Sign In</span>
                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                </button>
            </form>
        </div>

        <?php if ($is_school && $school): ?>
        <!-- Apply links — only on school subdomains -->
        <div class="brand-bg-soft border rounded-2xl p-5 space-y-3 shadow-xl">
            <div class="flex items-center space-x-2 brand-text">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                <span class="text-xs font-bold">New to <?php echo htmlspecialchars($school['name']); ?>?</span>
            </div>
            <p class="text-[10px] text-[var(--text-secondary)]">Submit an application to join the school community.</p>
            <div class="grid grid-cols-2 gap-3 pt-1">
                <a href="apply-student.php" class="flex items-center justify-center space-x-2 p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500/40 rounded-xl text-xs font-bold text-[var(--text-primary)] transition-all">
                    <i data-lucide="user-plus" class="w-4 h-4 brand-text"></i>
                    <span>Student Admission</span>
                </a>
                <a href="apply-staff.php" class="flex items-center justify-center space-x-2 p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-emerald-500/40 rounded-xl text-xs font-bold text-[var(--text-primary)] transition-all">
                    <i data-lucide="briefcase" class="w-4 h-4 text-emerald-400"></i>
                    <span>Staff Application</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_platform && !$is_school): ?>
        <!-- Dev/test helper — only shown when accessing login.php directly (root domain) -->
        <div class="bg-[var(--bg-secondary)] border border-indigo-500/10 p-5 rounded-2xl space-y-2 shadow-xl">
            <div class="flex items-center space-x-2 text-indigo-400">
                <i data-lucide="info" class="w-4 h-4"></i>
                <span class="text-xs font-bold">Local development</span>
            </div>
            <p class="text-[10px] text-[var(--text-secondary)]">Sign in with any account from your seeded database. There are no built-in demo credentials — accounts and their real passwords are set when a school/staff/parent/student record is created.</p>
        </div>
        <?php endif; ?>

    </main>

    <footer class="p-6 text-center text-[11px] text-[var(--text-secondary)] border-t border-[var(--border-color)]">
        <?php if ($is_school): ?>
            Powered by <span class="text-[var(--text-primary)] font-semibold">Zetaphase EduCloud</span>
        <?php else: ?>
            &copy; <?php echo date('Y'); ?> Zetaphase EduCloud &mdash; zetaphase.com.ng
        <?php endif; ?>
    </footer>

    <script>
        lucide.createIcons();
        function populate(email, password) {
            document.getElementById('emailInput').value  = email;
            document.getElementById('passwordInput').value = password;
        }
    </script>
</body>
</html>