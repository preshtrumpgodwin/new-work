<?php
/**
 * preview_result_slip_template.php — lets a School Admin see exactly what
 * a result slip template looks like, using realistic sample data, BEFORE
 * choosing it as the school's active template. Works for both the
 * platform's base templates and the school's own custom ones.
 *
 * Uses the exact same rst_render_sheet_html()/rst_render_field_html()
 * rendering as the real print (see print_result_slip.php) — this is not a
 * separate mock, so what you preview here is exactly what will print.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_uuid'])) { header('Location: ../login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['School Admin', 'Platform Manager'], true)) {
    http_response_code(403); echo 'Only a School Admin can preview result slip templates.'; exit;
}
$template_uuid = safe_str($_GET['template_uuid'] ?? '');

if (($_SESSION['role'] ?? '') === 'Platform Manager') {
    // Platform Manager can preview a base template (school_uuid IS NULL) or,
    // when given ?school_uuid=..., that specific school's active/custom
    // template — never blocked by a school_uuid of their own, since they
    // don't belong to one.
    $requested_school_uuid = safe_str($_GET['school_uuid'] ?? '');
    if ($requested_school_uuid !== '') {
        $tq = $pdo->prepare("SELECT * FROM result_slip_templates WHERE template_uuid=? AND (school_uuid IS NULL OR school_uuid=?) LIMIT 1");
        $tq->execute([$template_uuid, $requested_school_uuid]);
    } else {
        $tq = $pdo->prepare("SELECT * FROM result_slip_templates WHERE template_uuid=? AND school_uuid IS NULL LIMIT 1");
        $tq->execute([$template_uuid]);
    }
    $school_uuid = $requested_school_uuid;
} else {
    if (!isset($_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
    $school_uuid = $_SESSION['school_uuid'];
    // A school may preview one of the platform's base templates (school_uuid
    // IS NULL) or one of its own custom templates — never another school's.
    $tq = $pdo->prepare("SELECT * FROM result_slip_templates WHERE template_uuid=? AND (school_uuid IS NULL OR school_uuid=?) LIMIT 1");
    $tq->execute([$template_uuid, $school_uuid]);
}
$template = $tq->fetch();
if (!$template) { http_response_code(404); echo 'Template not found.'; exit; }

$template_layout = rst_normalize_layout($template['layout_json'] ?? null);
if (!$template_layout || empty($template_layout['elements'])) {
    echo 'This template has no fields on its sheet yet.'; exit;
}

$sc = $pdo->prepare("SELECT * FROM schools WHERE school_uuid=? LIMIT 1");
$sc->execute([$school_uuid]);
$school = $sc->fetch() ?: ['name' => 'Sample School', 'logo_path' => null];

// Realistic sample data — the same fictional student used in the A4
// canvas builder's live preview, so the template looks the same here as
// it did while you were designing it.
$student = [
    'name' => 'Adaeze Okafor', 'student_uuid' => 'STU-2026-0142', 'roll_number' => 'STU-2026-0142',
    'class' => 'SS2', 'arm' => 'Science', 'photo_path' => null,
];
$session_name = $school['current_session'] ?? '2025/2026';
$term_name = $school['current_term'] ?? 'First Term';

$assess_cols = ['dynamic' => true, 'configured' => true, 'columns' => [
    ['key' => 'ca', 'label' => 'CA', 'max' => 20],
    ['key' => 'exam', 'label' => 'Exam', 'max' => 80],
]];
$sample_subjects = ['Mathematics', 'English Language', 'Biology', 'Chemistry', 'Physics'];
$results = [];
$dynamic_scores = [];
foreach ($sample_subjects as $i => $subj) {
    $ca = 14 + ($i % 5); $exam = 55 + ($i * 5) % 30;
    $tot = $ca + $exam;
    $results[] = ['subject_name' => $subj, 'total_score' => $tot, 'grade' => $tot >= 70 ? 'A1' : ($tot >= 60 ? 'B2' : ($tot >= 50 ? 'C4' : 'D7')), 'subject_teacher_remark' => 'A consistent, hardworking student.'];
    $dynamic_scores[$subj] = ['ca' => $ca, 'exam' => $exam];
}
$total = array_sum(array_column($results, 'total_score'));
$avg = round($total / count($results), 1);

$ctx = compact('school', 'student', 'results', 'assess_cols', 'dynamic_scores', 'session_name', 'term_name', 'total', 'avg');
$ctx['position'] = '3rd of 32';
$ctx['attendance'] = '58/60';
$ctx['affective_note'] = 'Excellent';
$ctx['psychomotor_note'] = 'Good';
$ctx['principal_remark'] = 'Keep up the good work.';
$ctx['next_term_begins'] = date('F j, Y', strtotime('+3 weeks'));

echo rst_render_sheet_html($template_layout, $ctx, 'Preview — ' . $template['name'], false);
