<?php
require_once __DIR__ . '/../../config/security.php';
secure_session_start();
$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['theme']) && in_array($data['theme'], ['auto','light','dark'])) {
    $_SESSION['platform_theme'] = $data['theme'];
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false]);
}