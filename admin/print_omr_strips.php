<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
$school_uuid = $_SESSION['school_uuid'];
$sheet_uuid = safe_str($_GET['sheet_uuid'] ?? '');

$sq = $pdo->prepare("SELECT * FROM omr_sheets WHERE sheet_uuid=? AND school_uuid=? LIMIT 1");
$sq->execute([$sheet_uuid, $school_uuid]);
$sheet = $sq->fetch();
if (!$sheet) { http_response_code(404); echo 'Sheet not found.'; exit; }

$sc = $pdo->prepare("SELECT name, logo_path FROM schools WHERE school_uuid=? LIMIT 1");
$sc->execute([$school_uuid]);
$school = $sc->fetch() ?: [];

$stq = $pdo->prepare("SELECT * FROM omr_sheet_students WHERE sheet_uuid=? ORDER BY student_name ASC");
$stq->execute([$sheet_uuid]);
$strips = $stq->fetchAll();

$layout = json_decode(file_get_contents(__DIR__ . '/../scripts/omr_layout.json'), true);
$CW = $layout['canvas_w']; $CH = $layout['canvas_h'];
$FID = $layout['fiducial']; $ID = $layout['id_grid']; $ANS = $layout['answer_grid'];
function pct_x($px, $CW) { return round($px / $CW * 100, 3); }
function pct_y($px, $CH) { return round($px / $CH * 100, 3); }

$total_q = (int)$sheet['total_questions'];
$ans_cols = (int)ceil($total_q / $ANS['questions_per_col']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>OMR Strips — <?php echo htmlspecialchars($sheet['exam_title']); ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #eee; }
    .toolbar { padding: 12px; text-align: center; background: #fff; border-bottom: 1px solid #ccc; }
    .toolbar button { padding: 8px 18px; font-weight: bold; font-size: 13px; border-radius: 8px; border: none; background: #0891b2; color: #fff; cursor: pointer; }
    .page { width: 210mm; min-height: 297mm; margin: 10mm auto; background: #fff; padding: 6mm; }
    .strip {
        position: relative;
        width: 100%;
        aspect-ratio: <?php echo $CW; ?> / <?php echo $CH; ?>;
        border: 1px solid #000;
        margin-bottom: 4mm;
        overflow: hidden;
    }
    .strip:not(:last-child) { border-bottom: 1px dashed #999; }
    .cut-note { font-size: 6px; color: #999; position: absolute; left: 2px; bottom: -8px; }
    .fid { position: absolute; background: #000; }
    .header { position: absolute; left: 8%; top: 2%; font-size: 1.6cqh; }
    .headline { font-weight: bold; font-size: 14px; }
    .subline { font-size: 10px; color: #333; }
    .idlabel { position: absolute; font-size: 8px; font-weight: bold; }
    .bubble { position: absolute; border: 1px solid #000; border-radius: 50%; }
    .bubble.filled { background: #000; }
    .bubble-label { position: absolute; font-size: 7px; text-align: center; }
    .qnum { position: absolute; font-size: 8px; font-weight: bold; }
    @media print {
        .toolbar { display: none; }
        body { background: #fff; }
        .page { margin: 0; width: auto; min-height: auto; page-break-after: always; }
    }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print</button> — <?php echo count($strips); ?> strip(s), 3 per A4 page. Cut along the dashed lines.</div>

<?php
$chunks = array_chunk($strips, 3);
foreach ($chunks as $chunk):
?>
<div class="page">
    <?php foreach ($chunk as $s):
        $digits = str_split($s['serial_code']);
    ?>
    <div class="strip">
        <!-- Fiducial corner markers -->
        <?php
        $fx = pct_x($FID['margin'], $CW); $fy = pct_y($FID['margin'], $CH);
        $fw = pct_x($FID['size'], $CW); $fh = pct_y($FID['size'], $CH);
        $fx2 = pct_x($CW - $FID['margin'] - $FID['size'], $CW);
        $fy2 = pct_y($CH - $FID['margin'] - $FID['size'], $CH);
        ?>
        <div class="fid" style="left:<?php echo $fx; ?>%;top:<?php echo $fy; ?>%;width:<?php echo $fw; ?>%;height:<?php echo $fh; ?>%;"></div>
        <div class="fid" style="left:<?php echo $fx2; ?>%;top:<?php echo $fy; ?>%;width:<?php echo $fw; ?>%;height:<?php echo $fh; ?>%;"></div>
        <div class="fid" style="left:<?php echo $fx2; ?>%;top:<?php echo $fy2; ?>%;width:<?php echo $fw; ?>%;height:<?php echo $fh; ?>%;"></div>
        <div class="fid" style="left:<?php echo $fx; ?>%;top:<?php echo $fy2; ?>%;width:<?php echo $fw; ?>%;height:<?php echo $fh; ?>%;"></div>

        <!-- Header -->
        <div class="header">
            <div class="headline"><?php echo htmlspecialchars($school['name'] ?? 'School'); ?> — <?php echo htmlspecialchars($sheet['exam_title']); ?></div>
            <div class="subline"><?php echo htmlspecialchars($s['student_name']); ?><?php echo $s['roll_number'] ? ' · Roll ' . htmlspecialchars($s['roll_number']) : ''; ?> · <?php echo htmlspecialchars($sheet['class_name']); ?></div>
        </div>

        <!-- ID bubble grid (pre-shaded by the system — do not alter) -->
        <div class="idlabel" style="left:<?php echo pct_x($ID['x0'] - 40, $CW); ?>%; top:<?php echo pct_y($ID['y0'] - 25, $CH); ?>%;">ID</div>
        <?php for ($col = 0; $col < $ID['digits']; $col++): ?>
            <?php for ($row = 0; $row < $ID['rows']; $row++):
                $cx = $ID['x0'] + $col * $ID['col_w'];
                $cy = $ID['y0'] + $row * $ID['row_h'];
                $bx = pct_x($cx - $ID['bubble_r'], $CW); $by = pct_y($cy - $ID['bubble_r'], $CH);
                $bw = pct_x($ID['bubble_r'] * 2, $CW); $bh = pct_y($ID['bubble_r'] * 2, $CH);
                $filled = isset($digits[$col]) && (int)$digits[$col] === $row;
            ?>
            <div class="bubble<?php echo $filled ? ' filled' : ''; ?>" style="left:<?php echo $bx; ?>%;top:<?php echo $by; ?>%;width:<?php echo $bw; ?>%;height:<?php echo $bh; ?>%;"></div>
            <?php endfor; ?>
        <?php endfor; ?>

        <!-- Answer grid — student bubbles these in by hand -->
        <?php for ($c = 0; $c < $ans_cols; $c++):
            $cx0 = $ANS['x0'] + $c * $ANS['col_w'];
        ?>
            <?php for ($r = 0; $r < $ANS['questions_per_col']; $r++):
                $qn = $c * $ANS['questions_per_col'] + $r + 1;
                if ($qn > $total_q) break;
                $cy = $ANS['y0'] + $r * $ANS['row_h'];
            ?>
            <div class="qnum" style="left:<?php echo pct_x($cx0 - 22, $CW); ?>%; top:<?php echo pct_y($cy - 5, $CH); ?>%;"><?php echo $qn; ?></div>
            <?php foreach ($ANS['options'] as $oi => $opt):
                $ox = $cx0 + $oi * $ANS['opt_dx'];
                $bx = pct_x($ox - $ANS['bubble_r'], $CW); $by = pct_y($cy - $ANS['bubble_r'], $CH);
                $bw = pct_x($ANS['bubble_r'] * 2, $CW); $bh = pct_y($ANS['bubble_r'] * 2, $CH);
            ?>
            <div class="bubble" style="left:<?php echo $bx; ?>%;top:<?php echo $by; ?>%;width:<?php echo $bw; ?>%;height:<?php echo $bh; ?>%;"></div>
            <?php endforeach; ?>
            <?php endfor; ?>
        <?php endfor; ?>

        <div class="cut-note">Strip ID: <?php echo htmlspecialchars($s['serial_code']); ?> — do not shade the ID grid above, it's pre-set.</div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (empty($strips)): ?>
<div class="page" style="text-align:center;padding-top:40px;color:#888;">No strips generated yet for this sheet. Go back and click "Generate Strips" first.</div>
<?php endif; ?>

</body>
</html>
