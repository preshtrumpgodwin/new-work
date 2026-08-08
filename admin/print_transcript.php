<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
$school_uuid = $_SESSION['school_uuid'];
$student_uuid = safe_str($_GET['student_uuid'] ?? '');

$siq = $pdo->prepare("SELECT * FROM students WHERE student_uuid=? AND school_uuid=?");
$siq->execute([$student_uuid, $school_uuid]);
$student = $siq->fetch();
if (!$student) { http_response_code(404); echo 'Student not found.'; exit; }

$sc = $pdo->prepare("SELECT name, logo_path FROM schools WHERE school_uuid=? LIMIT 1");
$sc->execute([$school_uuid]);
$school = $sc->fetch();

$rq = $pdo->prepare("SELECT * FROM results WHERE school_uuid=? AND student_uuid=? ORDER BY session_name, term_name, subject_name");
$rq->execute([$student_uuid, $school_uuid]);
$grouped = [];
foreach ($rq->fetchAll() as $r) $grouped[$r['session_name']][$r['term_name']][] = $r;
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Transcript — <?php echo htmlspecialchars($student['name']); ?></title>
<style>
 body{font-family:Georgia,serif;max-width:760px;margin:40px auto;padding:0 20px;color:#111;}
 .header{text-align:center;margin-bottom:10px;} .header img{max-height:60px;}
 h1{font-size:18px;margin:6px 0 2px;} .sub{font-size:12px;color:#555;margin:0;}
 .student-info{margin:20px 0;font-size:13px;} .student-info b{display:inline-block;width:120px;}
 h2{font-size:14px;border-bottom:1px solid #999;padding-bottom:4px;margin-top:24px;}
 h3{font-size:12px;margin:10px 0 4px;} table{width:100%;border-collapse:collapse;font-size:12px;}
 th,td{border:1px solid #ccc;padding:4px 8px;text-align:left;}
 @media print{body{margin:0;padding:20px;}}
</style></head><body>
<div class="header">
    <?php if (!empty($school['logo_path'])): ?><img src="<?php echo htmlspecialchars(asset_url($school['logo_path'])); ?>" alt="Logo"><?php endif; ?>
    <h1><?php echo htmlspecialchars($school['name'] ?? ''); ?></h1>
    <p class="sub">Official Academic Transcript</p>
</div>
<div class="student-info">
    <div><b>Name:</b> <?php echo htmlspecialchars($student['name']); ?></div>
    <div><b>Class:</b> <?php echo htmlspecialchars($student['class'] ?? ''); ?></div>
    <div><b>Student ID:</b> <?php echo htmlspecialchars($student['student_uuid']); ?></div>
    <div><b>Date Issued:</b> <?php echo date('F j, Y'); ?></div>
</div>
<?php foreach ($grouped as $session => $terms): ?>
<h2><?php echo htmlspecialchars($session); ?></h2>
<?php foreach ($terms as $term => $subjects):
    // Assessment columns for this session/term, live from Settings →
    // Assessment Configuration — no hardcoded CA1/CA2/Exam fallback.
    $t_assess_cols = getAssessmentColumns($pdo, $school_uuid, $session, $term, $student['class'] ?? '');
    $t_dynamic_scores = $t_assess_cols['dynamic'] ? getStudentDynamicScores($pdo, $school_uuid, $student_uuid, $session, $term) : [];
?>
<h3><?php echo htmlspecialchars($term); ?></h3>
<table>
<thead><tr><th>Subject</th>
<?php foreach ($t_assess_cols['columns'] as $col): ?><th><?php echo htmlspecialchars($col['label']); ?><?php echo $col['max'] !== null ? ' /' . (int)$col['max'] : ''; ?></th><?php endforeach; ?>
<th>Total</th><th>Grade</th></tr></thead>
<tbody>
<?php foreach ($subjects as $s): ?>
<tr><td><?php echo htmlspecialchars($s['subject_name']); ?></td>
<?php foreach ($t_assess_cols['columns'] as $col): ?>
    <td><?php
        if ($t_assess_cols['dynamic']) {
            echo htmlspecialchars((string)($t_dynamic_scores[$s['subject_name']][$col['key']] ?? 0));
        } else {
            echo htmlspecialchars((string)($s[$col['key']] ?? 0));
        }
    ?></td>
<?php endforeach; ?>
<td><?php echo htmlspecialchars($s['total_score']); ?></td><td><?php echo htmlspecialchars($s['grade'] ?? ''); ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endforeach; ?>
<?php endforeach; ?>
<?php if (empty($grouped)): ?><p><i>No results on file.</i></p><?php endif; ?>
<script>window.onload = () => window.print();</script>
</body></html>
