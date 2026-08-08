<?php
/**
 * admin/api/update-timetable-slot.php
 *
 * Saves one cell of the whole-school timetable grid on change — no modal,
 * no full page reload. Upserts by (school, class, arm, day, period) so a
 * cell can be filled in even if auto-fill never touched it.
 *
 * POST params:
 *   class_name, arm_name, day_of_week, period_time (required — identify the cell)
 *   subject        (optional) — subject name; empty/omitted = clear to Free Period
 *   teacher_name   (optional) — teacher name; auto-looked-up if omitted
 *   csrf_token     (required)
 */
require_once __DIR__ . '/../../config/security.php';
secure_session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/Helpers.php';
require_once __DIR__ . '/../lib/AuditLog.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$school_uuid = $_SESSION['school_uuid'];
$user_uuid   = $_SESSION['user_uuid'];
$active_role = $_SESSION['role'] ?? 'Teacher';

if (!(in_array($active_role, ['School Admin', 'Platform Manager']) || can_manage($active_role, feature_access('timetable')))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to edit the timetable.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired session token. Please refresh the page.']);
    exit;
}

$class_name   = safe_str($_POST['class_name'] ?? '');
$arm_name     = safe_str($_POST['arm_name'] ?? '');
$day          = safe_str($_POST['day_of_week'] ?? '');
$period       = safe_str($_POST['period_time'] ?? '');
$subject      = safe_str($_POST['subject'] ?? '');
$teacher_name = safe_str($_POST['teacher_name'] ?? '');

if ($class_name === '' || $day === '' || $period === '') {
    echo json_encode(['success' => false, 'error' => 'Missing slot reference.']);
    exit;
}

if ($subject === '') {
    $subject = '— Free Period —';
    $teacher_name = 'Unassigned';
} elseif ($teacher_name === '') {
    // Auto-fill the teacher from the current subject-teacher assignments
    // for this class — same source the dropdown options came from.
    $tq = $pdo->prepare("
        SELECT s.name AS teacher_name
        FROM staff_subject_assignments ssa
        JOIN staff s ON s.staff_uuid = ssa.staff_uuid
        WHERE ssa.school_uuid = ? AND ssa.class_name = ? AND ssa.subject_name = ?
          AND (ssa.arm_name = ? OR ssa.arm_name IS NULL OR ssa.arm_name = '')
        LIMIT 1
    ");
    $tq->execute([$school_uuid, $class_name, $subject, $arm_name]);
    $teacher_name = $tq->fetchColumn() ?: 'Unassigned';
}

// Double-booking check (skip for free/unassigned periods).
$has_clash = 0;
if ($teacher_name !== 'Unassigned') {
    $clash = $pdo->prepare("
        SELECT class_name, arm_name FROM timetables
        WHERE school_uuid=? AND day_of_week=? AND period_time=? AND teacher_name=?
          AND NOT (class_name=? AND arm_name=?)
        LIMIT 1
    ");
    $clash->execute([$school_uuid, $day, $period, $teacher_name, $class_name, $arm_name]);
    $conflict = $clash->fetch();
    if ($conflict) {
        $has_clash = 1; // saved anyway, flagged — admin can override or fix
    }
}

$existing = $pdo->prepare("SELECT timetable_uuid FROM timetables WHERE school_uuid=? AND class_name=? AND arm_name=? AND day_of_week=? AND period_time=?");
$existing->execute([$school_uuid, $class_name, $arm_name, $day, $period]);
$uuid = $existing->fetchColumn();

if ($uuid) {
    $pdo->prepare("UPDATE timetables SET subject=?, teacher_name=?, has_clash=?, clash_overridden=0 WHERE timetable_uuid=?")
        ->execute([$subject, $teacher_name, $has_clash, $uuid]);
} else {
    $uuid = uid('tt');
    $pdo->prepare("INSERT INTO timetables (timetable_uuid, school_uuid, class_name, arm_name, day_of_week, period_time, subject, teacher_name, has_clash) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$uuid, $school_uuid, $class_name, $arm_name, $day, $period, $subject, $teacher_name, $has_clash]);
}

// If this teacher now clashes elsewhere, flag the other side of the clash too.
if ($has_clash) {
    $pdo->prepare("UPDATE timetables SET has_clash=1 WHERE school_uuid=? AND day_of_week=? AND period_time=? AND teacher_name=?")
        ->execute([$school_uuid, $day, $period, $teacher_name]);
}

AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable_slot.update', $uuid,
    "$class_name $arm_name: $day $period → $subject ($teacher_name)");

echo json_encode([
    'success' => true,
    'subject' => $subject,
    'teacher_name' => $teacher_name,
    'has_clash' => (bool)$has_clash,
]);
