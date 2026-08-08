<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
$school_uuid = $_SESSION['school_uuid'];
$session_name = safe_str($_GET['session'] ?? '');

$sc = $pdo->prepare("SELECT name, logo_path FROM schools WHERE school_uuid=? LIMIT 1");
$sc->execute([$school_uuid]);
$school = $sc->fetch();

$c = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_uuid=? AND status='Active'"); $c->execute([$school_uuid]); $total_students = (int)$c->fetchColumn();
$c = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE school_uuid=? AND status='Active'"); $c->execute([$school_uuid]); $total_staff = (int)$c->fetchColumn();
$c = $pdo->prepare("SELECT COUNT(DISTINCT student_uuid) FROM alumni WHERE school_uuid=? AND graduation_year=?"); $c->execute([$school_uuid, substr($session_name,0,4)]); $graduates = (int)$c->fetchColumn();
$c = $pdo->prepare("SELECT AVG(total_score) FROM results WHERE school_uuid=? AND session_name=?"); $c->execute([$school_uuid, $session_name]); $avg_score = round((float)$c->fetchColumn(), 1);

$cq = $pdo->prepare("SELECT class_name, AVG(total_score) avg_score, COUNT(DISTINCT student_uuid) student_count FROM results WHERE school_uuid=? AND session_name=? GROUP BY class_name ORDER BY class_name");
$cq->execute([$school_uuid, $session_name]);
$per_class = $cq->fetchAll();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Annual Report — <?php echo htmlspecialchars($session_name); ?></title>
<style>
 body{font-family:Georgia,serif;max-width:760px;margin:40px auto;padding:0 20px;color:#111;}
 .header{text-align:center;margin-bottom:20px;} .header img{max-height:70px;}
 h1{font-size:20px;margin:8px 0 2px;} .sub{font-size:13px;color:#555;}
 .stats{display:flex;gap:16px;margin:24px 0;flex-wrap:wrap;}
 .stat{border:1px solid #ccc;border-radius:8px;padding:12px 18px;text-align:center;flex:1;min-width:120px;}
 .stat b{display:block;font-size:22px;} .stat span{font-size:11px;color:#666;text-transform:uppercase;}
 table{width:100%;border-collapse:collapse;font-size:12px;margin-top:16px;}
 th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}
 @media print{body{margin:0;padding:20px;}}
</style></head><body>
<div class="header">
    <?php if (!empty($school['logo_path'])): ?><img src="<?php echo htmlspecialchars(asset_url($school['logo_path'])); ?>" alt="Logo"><?php endif; ?>
    <h1><?php echo htmlspecialchars($school['name'] ?? ''); ?></h1>
    <p class="sub">Annual Report — <?php echo htmlspecialchars($session_name); ?></p>
</div>
<div class="stats">
    <div class="stat"><b><?php echo $total_students; ?></b><span>Active Students</span></div>
    <div class="stat"><b><?php echo $total_staff; ?></b><span>Active Staff</span></div>
    <div class="stat"><b><?php echo $graduates; ?></b><span>Graduates</span></div>
    <div class="stat"><b><?php echo $avg_score ?: '—'; ?></b><span>Avg. Score</span></div>
</div>
<table>
<thead><tr><th>Class</th><th>Students Assessed</th><th>Average Score</th></tr></thead>
<tbody>
<?php foreach ($per_class as $pc): ?>
<tr><td><?php echo htmlspecialchars($pc['class_name']); ?></td><td><?php echo (int)$pc['student_count']; ?></td><td><?php echo round((float)$pc['avg_score'],1); ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<script>window.onload = () => window.print();</script>
</body></html>
