<?php
/**
 * admin/api/get-arms.php — returns the arms belonging to a single class for
 * the logged-in school. Arms are tied to a class (e.g. "JSS1 A" is not the
 * same arm as "JSS2 A"), so every arm dropdown in the dashboard loads its
 * options from here only after a class has been picked.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/Helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$school_uuid = $_SESSION['school_uuid'];
$class_name  = safe_str($_GET['class_name'] ?? '');

if ($class_name === '') {
    echo json_encode(['arms' => []]);
    exit;
}

$arms = [];
try {
    $q = $pdo->prepare("SELECT arm_name FROM academic_arms WHERE school_uuid=? AND class_name=? ORDER BY id ASC");
    $q->execute([$school_uuid, $class_name]);
    $arms = $q->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Exception $e) {}

echo json_encode(['arms' => $arms]);
