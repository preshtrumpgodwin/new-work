<?php
/**
 * Public ID Card Verification.
 *
 * Deliberately requires NO login — this is what a security guard, front-desk
 * staff, or a parent scans from a printed ID card to confirm "is this person
 * really who the card says, and are they still active here?" without needing
 * app credentials.
 *
 * Trust model: the code printed on the card is HMAC-signed per-school
 * (see buildIdCardCode()/parseIdCardCode() in admin/lib/Helpers.php) using a
 * secret that never leaves the server, so a card's payload can't be
 * hand-edited to impersonate a different person or a different school. This
 * page only ever trusts a code after parseIdCardCode() validates the
 * signature — never anything read directly out of the URL.
 *
 * Because this page is public, it intentionally shows the MINIMUM needed to
 * verify identity: name, photo, type (student/staff), class or department,
 * and active/inactive status. It never shows health records, contact info,
 * fee/finance data, addresses, or dates of birth — those stay behind login.
 */

require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/admin/lib/Helpers.php';

$code = trim($_GET['code'] ?? '');
$result = null;
$error = '';

if ($code === '') {
    $error = 'No code provided. Scan the QR code on a printed ID card, or enter the code below.';
} else {
    // The code's payload carries which school it belongs to (needed to look
    // up that school's HMAC secret) but the payload itself isn't trusted
    // until the signature check inside parseIdCardCode() passes.
    $parts = explode('.', $code);
    $school_uuid_from_code = '';
    if (count($parts) === 2) {
        $decoded = json_decode(base64_decode($parts[0]), true);
        $school_uuid_from_code = $decoded['school'] ?? '';
    }

    if ($school_uuid_from_code === '') {
        $error = 'This code is not a recognized ID card format.';
    } else {
        try {
            $parsed = parseIdCardCode($pdo, $school_uuid_from_code, $code);
            if (!$parsed['ok']) {
                $error = $parsed['reason'] ?: 'This code could not be verified.';
            } else {
                $school_stmt = $pdo->prepare("SELECT name, logo_path, theme_color FROM schools WHERE school_uuid = ? LIMIT 1");
                $school_stmt->execute([$school_uuid_from_code]);
                $school = $school_stmt->fetch();

                if ($parsed['person_type'] === 'staff') {
                    $p_stmt = $pdo->prepare("SELECT name, role AS sub_label, department, status, photo_path FROM staff WHERE staff_uuid = ? AND school_uuid = ? LIMIT 1");
                } else {
                    $p_stmt = $pdo->prepare("SELECT name, CONCAT(class,' ',arm) AS sub_label, NULL AS department, status, photo_path FROM students WHERE student_uuid = ? AND school_uuid = ? LIMIT 1");
                }
                $p_stmt->execute([$parsed['person_uuid'], $school_uuid_from_code]);
                $person = $p_stmt->fetch();

                if (!$person) {
                    $error = 'This ID card is signed correctly, but the person it belongs to no longer has an active record at this school.';
                } else {
                    $result = [
                        'person_type' => $parsed['person_type'],
                        'name'        => $person['name'],
                        'sub_label'   => $person['sub_label'],
                        'department'  => $person['department'],
                        'status'      => $person['status'],
                        'photo_path'  => $person['photo_path'],
                        'school_name' => $school['name'] ?? 'School',
                        'theme_color' => $school['theme_color'] ?? '#0d9488',
                    ];
                }
            }
        } catch (Throwable $e) {
            $error = safe_error('ID card verification', $e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ID Verification — Zetaphase EduCloud</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: #0d1117; color: #e2e8f0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        padding: 24px;
    }
    .card {
        background: #161b22; border: 1px solid #30363d; border-radius: 20px;
        max-width: 380px; width: 100%; padding: 28px; text-align: center;
    }
    .badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
        border-radius: 999px; font-size: 12px; font-weight: 700; margin-bottom: 18px;
    }
    .badge.ok { background: rgba(52,211,153,0.15); color: #34d399; }
    .badge.warn { background: rgba(251,191,36,0.15); color: #fbbf24; }
    .badge.bad { background: rgba(248,113,113,0.15); color: #f87171; }
    .photo {
        width: 84px; height: 84px; border-radius: 50%; margin: 0 auto 14px;
        object-fit: cover; border: 3px solid #30363d; display: block;
    }
    .photo-fallback {
        width: 84px; height: 84px; border-radius: 50%; margin: 0 auto 14px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 24px; color: #fff; border: 3px solid #30363d;
    }
    h1 { font-size: 18px; margin: 0 0 4px; }
    p.sub { font-size: 13px; color: #94a3b8; margin: 0 0 2px; }
    .school { font-size: 11px; color: #64748b; margin-top: 16px; letter-spacing: 0.03em; }
    .error-icon { font-size: 40px; margin-bottom: 12px; }
    form { margin-top: 8px; }
    input[type=text] {
        width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #30363d;
        background: #0d1117; color: #e2e8f0; font-size: 13px; margin-bottom: 10px;
    }
    button {
        width: 100%; padding: 10px; border-radius: 10px; border: none; background: #4f46e5;
        color: #fff; font-weight: 700; font-size: 13px; cursor: pointer;
    }
</style>
</head>
<body>
<div class="card">
    <?php if ($result): ?>
        <?php if (strtolower($result['status']) === 'active'): ?>
            <span class="badge ok">✓ Verified &amp; Active</span>
        <?php else: ?>
            <span class="badge warn">⚠ Verified — Status: <?php echo htmlspecialchars($result['status']); ?></span>
        <?php endif; ?>

        <?php if (!empty($result['photo_path'])): ?>
            <img class="photo" src="<?php echo htmlspecialchars(asset_url($result['photo_path'])); ?>" alt="">
        <?php else: ?>
            <div class="photo-fallback" style="background-color: <?php echo htmlspecialchars($result['theme_color']); ?>">
                <?php echo htmlspecialchars(strtoupper(substr($result['name'], 0, 2))); ?>
            </div>
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($result['name']); ?></h1>
        <p class="sub"><?php echo htmlspecialchars($result['sub_label']); ?></p>
        <?php if (!empty($result['department'])): ?>
            <p class="sub"><?php echo htmlspecialchars($result['department']); ?></p>
        <?php endif; ?>
        <p class="sub"><?php echo $result['person_type'] === 'staff' ? 'Staff Member' : 'Student'; ?></p>

        <div class="school"><?php echo htmlspecialchars($result['school_name']); ?></div>
    <?php else: ?>
        <div class="error-icon">⚠</div>
        <span class="badge bad">Not Verified</span>
        <p class="sub" style="margin-top:8px;"><?php echo htmlspecialchars($error); ?></p>

        <form method="get" action="verify-id.php">
            <input type="text" name="code" placeholder="Paste ID card code" value="<?php echo htmlspecialchars($code); ?>">
            <button type="submit">Verify</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
