<?php
/**
 * Change Password — shown when a user must reset a temporary/default
 * password before continuing, or reachable any time from the account menu.
 */
require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/admin/lib/Helpers.php';

if (!isset($_SESSION['user_uuid'])) {
    header('Location: login.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_uuid = ? LIMIT 1");
$stmt->execute([$_SESSION['user_uuid']]);
$user = $stmt->fetch();
if (!$user) { header('Location: login.php'); exit; }

$forced = !empty($user['must_reset_password']);
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired — please try again.';
    } else {
        $current = trim($_POST['current_password'] ?? '');
        $new1    = trim($_POST['new_password'] ?? '');
        $new2    = trim($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new1 !== $new2) {
            $error = 'New passwords do not match.';
        } elseif (($policyErr = password_policy_check($new1)) !== '') {
            $error = $policyErr;
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE user_uuid = ?")
                ->execute([password_hash($new1, PASSWORD_BCRYPT), $user['user_uuid']]);
            try {
                $pdo->prepare("INSERT INTO audit_logs (school_uuid, user_email, action) VALUES (?, ?, ?)")
                    ->execute([$user['school_uuid'], $user['email'], 'Changed password']);
            } catch (Exception $e) {}

            if ($forced) {
                if ($user['role'] === 'Platform Manager') { header('Location: platform/index.php'); }
                elseif ($user['role'] === 'Student') { header('Location: student-portal.php'); }
                elseif ($user['role'] === 'Parent') { header('Location: parent-portal.php'); }
                elseif ($user['role'] === 'School Admin') { header('Location: admin/dashboard.php'); }
                else { header('Location: staff/index.php'); }
                exit;
            }
            $success = 'Password updated.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — Zetaphase EduCloud</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background:#0E1117;color:#F1F5F9;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;">
    <div style="max-width:400px;width:100%;padding:2rem;background:#11141B;border:1px solid #1E232D;border-radius:1rem;">
        <h1 style="font-size:1.1rem;font-weight:700;margin-bottom:0.5rem;">Change your password</h1>
        <?php if ($forced): ?>
            <p style="font-size:0.8rem;color:#94A3B8;margin-bottom:1rem;">Your account is using a temporary password. Please set a new one to continue.</p>
        <?php endif; ?>
        <?php if ($error): ?><div style="background:rgba(244,63,94,.1);color:#fb7185;padding:.5rem .75rem;border-radius:.5rem;font-size:.8rem;margin-bottom:1rem;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div style="background:rgba(16,185,129,.1);color:#34d399;padding:.5rem .75rem;border-radius:.5rem;font-size:.8rem;margin-bottom:1rem;"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="POST" style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php echo csrf_field(); ?>
            <div>
                <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;">Current password</label>
                <input type="password" name="current_password" required style="width:100%;background:#0A0D12;border:1px solid #1E232D;border-radius:.5rem;padding:.5rem .75rem;color:#F1F5F9;">
            </div>
            <div>
                <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;">New password</label>
                <input type="password" name="new_password" required style="width:100%;background:#0A0D12;border:1px solid #1E232D;border-radius:.5rem;padding:.5rem .75rem;color:#F1F5F9;">
            </div>
            <div>
                <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;">Confirm new password</label>
                <input type="password" name="confirm_password" required style="width:100%;background:#0A0D12;border:1px solid #1E232D;border-radius:.5rem;padding:.5rem .75rem;color:#F1F5F9;">
            </div>
            <p style="font-size:.7rem;color:#64748B;">At least 8 characters, with letters and numbers.</p>
            <button type="submit" style="background:#4F46E5;color:white;font-weight:700;padding:.6rem;border-radius:.5rem;border:none;cursor:pointer;">Update password</button>
        </form>
<?php
$role = $_SESSION['role'] ?? '';
if ($role === 'Platform Manager') { $backHref = 'platform/index.php'; }
elseif ($role === 'Student') { $backHref = 'student-portal.php'; }
elseif ($role === 'Parent') { $backHref = 'parent-portal.php'; }
elseif ($role === 'School Admin') { $backHref = 'admin/dashboard.php'; }
else { $backHref = 'staff/index.php'; }
?>
        <?php if (!$forced): ?><a href="<?php echo htmlspecialchars($backHref); ?>" style="display:block;text-align:center;margin-top:1rem;font-size:.75rem;color:#64748B;">← Back to dashboard</a><?php endif; ?>
    </div>
</body>
</html>
