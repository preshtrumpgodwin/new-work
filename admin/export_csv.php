<?php
/**
 * admin/export_csv.php — generic CSV export, scoped to the logged-in
 * school. Usage: export_csv.php?type=students|staff|parents|results
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lib/Helpers.php';

if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) { header('Location: ../login.php'); exit; }
$school_uuid = $_SESSION['school_uuid'];
$type = safe_str($_GET['type'] ?? '');

$queries = [
    'students' => ["SELECT name, class, arm, gender, date_of_birth, status FROM students WHERE school_uuid=? ORDER BY name", 'students'],
    'staff'    => ["SELECT name, role, department, email, phone, status FROM staff WHERE school_uuid=? ORDER BY name", 'staff'],
    'parents'  => ["SELECT name, email, phone, occupation FROM parents WHERE school_uuid=? ORDER BY name", 'parents'],
    'results'  => ["SELECT student_uuid, session_name, term_name, class_name, subject_name, total_score, grade FROM results WHERE school_uuid=? ORDER BY session_name, term_name, class_name", 'results'],
];

if (!isset($queries[$type])) { http_response_code(400); echo 'Unknown export type.'; exit; }
[$sql, $filename] = $queries[$type];

$stmt = $pdo->prepare($sql);
$stmt->execute([$school_uuid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) fputcsv($out, $row);
} else {
    fputcsv($out, ['No data']);
}
fclose($out);
