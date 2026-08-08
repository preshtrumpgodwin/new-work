<?php
/**
 * print_result_slip.php — renders a student's result slip using the
 * school's actively-selected template (built on the A4 canvas in
 * Result Slip Templates), positioned exactly as designed: each field at
 * its saved x/y/w/h (mm), with its saved font, color, and alignment.
 *
 * There is intentionally no hardcoded default layout here. If the school
 * hasn't selected an active template yet, we say so plainly and link to
 * where they pick one — we do not silently substitute a built-in layout.
 *
 * Rendering itself (rst_normalize_layout / rst_render_field_html /
 * rst_render_sheet_html) lives in lib/Helpers.php, shared with
 * preview_result_slip_template.php, so a preview and the real printed
 * slip can never drift apart.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
$school_uuid = $_SESSION['school_uuid'];
$student_uuid = safe_str($_GET['student_uuid'] ?? '');
$session_name = safe_str($_GET['session_name'] ?? '');
$term_name = safe_str($_GET['term_name'] ?? '');

$sc = $pdo->prepare("SELECT * FROM schools WHERE school_uuid=? LIMIT 1");
$sc->execute([$school_uuid]);
$school = $sc->fetch();

$stq = $pdo->prepare("SELECT * FROM students WHERE student_uuid=? AND school_uuid=?");
$stq->execute([$student_uuid, $school_uuid]);
$student = $stq->fetch();
if (!$student) { http_response_code(404); echo 'Student not found.'; exit; }

$template_layout = null;
if (!empty($school['active_result_slip_template_uuid'])) {
    $tq = $pdo->prepare("SELECT layout_json FROM result_slip_templates WHERE template_uuid=?");
    $tq->execute([$school['active_result_slip_template_uuid']]);
    $template_layout = rst_normalize_layout($tq->fetchColumn() ?: null);
}

if (!$template_layout || empty($template_layout['elements'])) {
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>No Result Slip Template Selected</title></head>
    <body style="font-family:Arial,sans-serif;max-width:560px;margin:80px auto;text-align:center;color:#333;">
        <h2>No result slip template selected</h2>
        <p>This school hasn't chosen an active result slip template yet. Go to <b>Result Slip Templates</b> in the dashboard sidebar and select or build one before printing result slips.</p>
    </body></html><?php
    exit;
}

$rq = $pdo->prepare("SELECT * FROM results WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? ORDER BY subject_name");
$rq->execute([$school_uuid, $student_uuid, $session_name, $term_name]);
$results = $rq->fetchAll();
$total = array_sum(array_column($results, 'total_score'));
$avg = $results ? round($total / count($results), 1) : 0;

// Assessment columns (dynamic per Settings → Assessment Configuration).
$assess_cols = getAssessmentColumns($pdo, $school_uuid, $session_name, $term_name, $student['class'] ?? '');
$dynamic_scores = $assess_cols['dynamic'] ? getStudentDynamicScores($pdo, $school_uuid, $student_uuid, $session_name, $term_name) : [];

$ctx = compact('school', 'student', 'results', 'assess_cols', 'dynamic_scores', 'session_name', 'term_name', 'total', 'avg');

echo rst_render_sheet_html($template_layout, $ctx, 'Result Slip — ' . $student['name'], true);
