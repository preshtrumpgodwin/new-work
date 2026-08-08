<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
$school_uuid = $_SESSION['school_uuid'];
$paper_uuid = safe_str($_GET['paper_uuid'] ?? '');

$pq = $pdo->prepare("SELECT * FROM printed_exam_papers WHERE paper_uuid=? AND school_uuid=? LIMIT 1");
$pq->execute([$paper_uuid, $school_uuid]);
$paper = $pq->fetch();
if (!$paper) { http_response_code(404); echo 'Paper not found.'; exit; }

$sc = $pdo->prepare("SELECT name, logo_path FROM schools WHERE school_uuid=? LIMIT 1");
$sc->execute([$school_uuid]);
$school = $sc->fetch();

$qids = array_filter(explode(',', $paper['question_uuids']));
$questions = [];
if ($qids) {
    $in = implode(',', array_fill(0, count($qids), '?'));
    $qq = $pdo->prepare("SELECT * FROM question_bank WHERE question_uuid IN ($in)");
    $qq->execute($qids);
    $byUuid = [];
    foreach ($qq->fetchAll() as $r) $byUuid[$r['question_uuid']] = $r;
    foreach ($qids as $id) if (isset($byUuid[$id])) $questions[] = $byUuid[$id];
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?php echo htmlspecialchars($paper['title']); ?></title>
<style>
 body{font-family:Georgia,serif;max-width:760px;margin:40px auto;padding:0 20px;color:#111;}
 .header{text-align:center;margin-bottom:20px;} .header img{max-height:60px;}
 h1{font-size:18px;margin:6px 0 2px;} .sub{font-size:12px;color:#555;margin:0;}
 .instructions{font-style:italic;font-size:13px;margin:16px 0;text-align:center;}
 .q{margin:18px 0;} .q .num{font-weight:bold;} .opts{margin:6px 0 0 20px;font-size:13px;}
 .opts div{margin:3px 0;} .theory-space{border-bottom:1px dashed #999;height:60px;margin-top:8px;}
 @media print{body{margin:0;padding:20px;}}
</style></head><body>
<div class="header">
    <?php if (!empty($school['logo_path'])): ?><img src="<?php echo htmlspecialchars(asset_url($school['logo_path'])); ?>" alt="Logo"><?php endif; ?>
    <h1><?php echo htmlspecialchars($school['name'] ?? ''); ?></h1>
    <p class="sub"><?php echo htmlspecialchars($paper['title']); ?><?php echo $paper['subject_name'] ? ' — ' . htmlspecialchars($paper['subject_name']) : ''; ?><?php echo $paper['class_name'] ? ' (' . htmlspecialchars($paper['class_name']) . ')' : ''; ?></p>
</div>
<?php if ($paper['instructions']): ?><p class="instructions"><?php echo htmlspecialchars($paper['instructions']); ?></p><?php endif; ?>
<?php foreach ($questions as $i => $qn): ?>
<div class="q">
    <span class="num"><?php echo $i+1; ?>.</span> <?php echo htmlspecialchars($qn['question_text']); ?>
    <?php if ($qn['question_type'] === 'objective'): ?>
    <div class="opts">
        <?php foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter => $col): ?>
        <?php if (!empty($qn[$col])): ?><div><?php echo $letter; ?>. <?php echo htmlspecialchars($qn[$col]); ?></div><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="theory-space"></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<script>window.onload = () => window.print();</script>
</body></html>
