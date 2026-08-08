<?php
/**
 * API: Staff Self Check-In — Phase B (QR/Barcode Attendance)
 * A logged-in staff member scans the school's printed gate-location QR
 * poster from inside their own dashboard. We already know who they are
 * from their session, so this just validates the scanned code belongs to
 * their school and logs a Check-In/Check-Out event for them — no one else
 * needs to scan their ID.
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

// Only staff roles self-check-in this way (School Admin/Platform Manager
// aren't tracked on the gate log the same way; adjust if that changes).
$staff_q = $pdo->prepare("SELECT staff_uuid, name FROM staff WHERE school_uuid=? AND user_uuid=? LIMIT 1");
$staff_q->execute([$school_uuid, $user_uuid]);
$staff = $staff_q->fetch();
if (!$staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No staff record is linked to your login.']);
    exit;
}

$code = trim($_POST['code'] ?? '');
if ($code === '' || parseSchoolGateLocationCode($pdo, $school_uuid, $code) === null) {
    echo json_encode(['success' => false, 'error' => 'That QR code is not this school\'s gate poster.']);
    exit;
}

$check_type = 'Check-In';
try {
    $last = $pdo->prepare("SELECT check_type FROM gate_attendance_logs WHERE school_uuid=? AND person_uuid=? AND DATE(timestamp)=CURDATE() AND status != 'Invalid' ORDER BY timestamp DESC LIMIT 1");
    $last->execute([$school_uuid, $staff['staff_uuid']]);
    if ($last->fetchColumn() === 'Check-In') $check_type = 'Check-Out';
} catch (Exception $e) {}

try {
    $pdo->prepare("INSERT INTO gate_attendance_logs (log_uuid, school_uuid, person_type, person_uuid, person_name, check_type, qr_date, status, scanned_by)
        VALUES (?, ?, 'Staff', ?, ?, ?, ?, 'Valid', ?)")
        ->execute([uid('gate'), $school_uuid, $staff['staff_uuid'], $staff['name'], $check_type, date('Y-m-d'), $user_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'gate.self_checkin', $staff['staff_uuid'], $check_type);
    echo json_encode(['success' => true, 'check_type' => $check_type, 'time' => date('H:i:s')]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => safe_error('Could not log check-in', $e)]);
}
