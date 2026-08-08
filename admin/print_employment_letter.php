<?php
/**
 * Standalone printable view of a single issued Letter of Employment.
 * Opens in a blank tab with only the printable content — no dashboard chrome.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';

if (!isset($_SESSION['user_uuid']) || empty($_SESSION['school_uuid'])) {
    header('Location: login.php');
    exit;
}
$school_uuid = $_SESSION['school_uuid'];
$letter_uuid = safe_str($_GET['letter_uuid'] ?? '');

$stmt = $pdo->prepare("SELECT l.*, s.name AS staff_name, sc.name AS school_name, sc.logo_path
    FROM hr_employment_letters_issued l
    LEFT JOIN staff s ON s.staff_uuid = l.staff_uuid
    LEFT JOIN schools sc ON sc.school_uuid = l.school_uuid
    WHERE l.letter_uuid = ? AND l.school_uuid = ? LIMIT 1");
$stmt->execute([$letter_uuid, $school_uuid]);
$letter = $stmt->fetch();

if (!$letter) {
    http_response_code(404);
    echo 'Letter not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Letter of Employment — <?php echo htmlspecialchars($letter['staff_name'] ?? ''); ?></title>
<style>
    body { font-family: Georgia, 'Times New Roman', serif; max-width: 720px; margin: 60px auto; padding: 0 20px; color: #1a1a1a; line-height: 1.7; }
    .header { text-align: center; margin-bottom: 40px; }
    .header img { max-height: 70px; margin-bottom: 10px; }
    .header h1 { font-size: 18px; margin: 0; }
    .date { text-align: right; margin-bottom: 30px; font-size: 14px; }
    .body { font-size: 15px; }
    @media print { body { margin: 0; padding: 20px; } }
</style>
</head>
<body>
    <div class="header">
        <?php if (!empty($letter['logo_path'])): ?><img src="<?php echo htmlspecialchars(asset_url($letter['logo_path'])); ?>" alt="Logo"><?php endif; ?>
        <h1><?php echo htmlspecialchars($letter['school_name'] ?? 'School'); ?></h1>
    </div>
    <div class="date"><?php echo date('F j, Y', strtotime($letter['issued_at'])); ?></div>
    <div class="body"><?php echo $letter['rendered_html']; ?></div>
    <script>window.onload = () => window.print();</script>
</body>
</html>
