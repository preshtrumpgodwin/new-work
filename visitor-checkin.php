<?php
/**
 * visitor-checkin.php — public, no-login front-end for the visitor gate QR
 * poster (see admin/sections/gate_scanner.php). A visitor scans the poster,
 * lands here with ?school=<uuid>, fills a short form, and the entry is
 * stored for the school's Gate Scanner > Visitor Log to see.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/admin/lib/Helpers.php';

$school_uuid = safe_str($_GET['school'] ?? $_POST['school'] ?? '');
$school = null;
if ($school_uuid !== '') {
    $sq = $pdo->prepare("SELECT school_uuid, name, logo_path, theme_color FROM schools WHERE school_uuid=? LIMIT 1");
    $sq->execute([$school_uuid]);
    $school = $sq->fetch();
}

$submitted = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $school) {
    $name    = safe_str($_POST['name'] ?? '');
    $phone   = safe_str($_POST['phone'] ?? '');
    $purpose = safe_str($_POST['purpose'] ?? '');
    $host    = safe_str($_POST['host_name'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Please enter your name and phone number.';
    } else {
        try {
            $pdo->prepare("INSERT INTO visitor_logs (visitor_uuid, school_uuid, name, phone, purpose, host_name, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Checked In')")
                ->execute([uid('vis'), $school_uuid, $name, $phone, $purpose, $host]);
            $submitted = true;
        } catch (Exception $e) {
            $error = 'Something went wrong — please tell the front desk you checked in.';
        }
    }
}

$brand_color = safe_color($school['theme_color'] ?? '#4F46E5');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Visitor Check-In<?php echo $school ? ' — ' . htmlspecialchars($school['name']) : ''; ?></title>
<style>
    :root { <?php echo accent_shade_vars($brand_color); ?> }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0E1117; color: #fff; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { max-width: 400px; width: 100%; background: #161B22; border: 1px solid #262C36; border-radius: 20px; padding: 32px 24px; }
    .card img.logo { max-height: 60px; display: block; margin: 0 auto 16px; }
    h1 { font-size: 18px; text-align: center; margin: 0 0 4px; }
    p.sub { text-align: center; color: #9CA3AF; font-size: 12px; margin: 0 0 24px; }
    label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #9CA3AF; margin-bottom: 4px; margin-top: 14px; }
    input { width: 100%; box-sizing: border-box; background: #0E1117; border: 1px solid #262C36; border-radius: 12px; padding: 10px 12px; color: #fff; font-size: 14px; }
    button { width: 100%; margin-top: 20px; background: var(--color-indigo-600); color: #fff; border: none; border-radius: 12px; padding: 12px; font-weight: 700; font-size: 14px; cursor: pointer; }
    .success { text-align: center; padding: 20px 0; }
    .success .check { font-size: 40px; color: #10B981; }
    .error { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); color: #FCA5A5; font-size: 12px; padding: 10px 12px; border-radius: 10px; margin-top: 14px; }
</style>
</head>
<body>
<div class="card">
    <?php if (!$school): ?>
        <h1>Invalid Link</h1>
        <p class="sub">This visitor check-in link is missing or invalid. Please ask the front desk for help.</p>
    <?php elseif ($submitted): ?>
        <div class="success">
            <div class="check">✓</div>
            <h1>You're checked in!</h1>
            <p class="sub">Thank you for visiting <?php echo htmlspecialchars($school['name']); ?>. Please proceed to the front desk.</p>
        </div>
    <?php else: ?>
        <?php if (!empty($school['logo_path'])): ?><img class="logo" src="<?php echo htmlspecialchars(asset_url($school['logo_path'])); ?>" alt="Logo"><?php endif; ?>
        <h1><?php echo htmlspecialchars($school['name']); ?></h1>
        <p class="sub">Visitor Check-In</p>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="school" value="<?php echo htmlspecialchars($school_uuid); ?>">
            <label>Full Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            <label>Phone Number</label>
            <input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            <label>Purpose of Visit</label>
            <input type="text" name="purpose" value="<?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?>" placeholder="e.g. Meeting with a teacher">
            <label>Who are you here to see?</label>
            <input type="text" name="host_name" value="<?php echo htmlspecialchars($_POST['host_name'] ?? ''); ?>">
            <button type="submit">Check In</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
