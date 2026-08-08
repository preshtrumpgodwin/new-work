<?php
/**
 * API: Gate Scan — Phase 4
 * Decodes a scanned gate-pass code, validates it, and logs a Check-In/
 * Check-Out event. Called via fetch() from admin/sections/gate_scanner.php.
 */
require_once __DIR__ . '/../../config/security.php';
secure_session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/Helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$school_uuid = $_SESSION['school_uuid'];
$user_uuid   = $_SESSION['user_uuid'];
$active_role = $_SESSION['role'] ?? 'Teacher';

// Must have write/full access on the gate scanner, or be School Admin / Platform Manager.
$access = getFeatureAccessLevel('gate_scanner', $active_role, $user_uuid, $school_uuid);
if (!in_array($active_role, ['School Admin', 'Platform Manager']) && !can_manage($active_role, $access)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to operate the gate scanner.']);
    exit;
}

$code = trim($_POST['code'] ?? '');
if ($code === '') {
    echo json_encode(['success' => false, 'error' => 'No code scanned.']);
    exit;
}

$result = parseGatePassCode($pdo, $school_uuid, $code);

if (!$result['ok']) {
    // Not a valid daily gate-pass — try the permanent, HMAC-signed ID card
    // format instead (SEC-3 fix). A printed ID card should be scannable at
    // the gate just like the daily pass.
    $id_result = parseIdCardCode($pdo, $school_uuid, $code);
    if ($id_result['ok']) {
        $result = [
            'ok'          => true,
            'person_type' => $id_result['person_type'],
            'person_uuid' => $id_result['person_uuid'],
            'qr_date'     => date('Y-m-d'), // ID cards aren't date-bound; log against today.
            'expired'     => false,
            'reason'      => '',
        ];
    }
}

if (!$result['ok']) {
    // Log the rejected attempt too, so security has a record of invalid scans.
    try {
        $pdo->prepare("
            INSERT INTO gate_attendance_logs (log_uuid, school_uuid, person_type, person_uuid, person_name, check_type, qr_date, status, scanned_by)
            VALUES (?, ?, 'Unknown', 'unknown', 'Unrecognized', 'Check-In', NULL, 'Invalid', ?)
        ")->execute([uid('gate'), $school_uuid, $user_uuid]);
    } catch (Exception $e) {}
    echo json_encode(['success' => false, 'error' => $result['reason']]);
    exit;
}

$person_type = $result['person_type']; // 'Student' or 'Staff'
$person_uuid = $result['person_uuid'];
$status      = $result['expired'] ? 'Expired' : 'Valid';

// Look up the person's display name.
$person_name = 'Unknown';
try {
    if ($person_type === 'Student') {
        $q = $pdo->prepare("SELECT name FROM students WHERE school_uuid=? AND student_uuid=? LIMIT 1");
    } else {
        $q = $pdo->prepare("SELECT name FROM staff WHERE school_uuid=? AND staff_uuid=? LIMIT 1");
    }
    $q->execute([$school_uuid, $person_uuid]);
    $person_name = $q->fetchColumn() ?: 'Unknown';
} catch (Exception $e) {}

// Alternate Check-In / Check-Out based on this person's last log today.
$check_type = 'Check-In';
try {
    $last = $pdo->prepare("
        SELECT check_type FROM gate_attendance_logs
        WHERE school_uuid=? AND person_uuid=? AND DATE(timestamp) = CURDATE() AND status != 'Invalid'
        ORDER BY timestamp DESC LIMIT 1
    ");
    $last->execute([$school_uuid, $person_uuid]);
    $last_type = $last->fetchColumn();
    if ($last_type === 'Check-In') $check_type = 'Check-Out';
} catch (Exception $e) {}

try {
    $pdo->prepare("
        INSERT INTO gate_attendance_logs (log_uuid, school_uuid, person_type, person_uuid, person_name, check_type, qr_date, status, scanned_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([uid('gate'), $school_uuid, $person_type, $person_uuid, $person_name, $check_type, $result['qr_date'], $status, $user_uuid]);

    echo json_encode([
        'success'     => $status === 'Valid',
        'warning'     => $status === 'Expired' ? $result['reason'] : null,
        'person_name' => $person_name,
        'person_type' => $person_type,
        'check_type'  => $check_type,
        'status'      => $status,
        'time'        => date('H:i:s'),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => safe_error('Could not log scan', $e)]);
}
