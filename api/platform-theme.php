<?php
/**
 * Platform Theme API
 * Allows platform manager to switch themes
 */

require_once __DIR__ . '/../config/security.php';
secure_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$theme = $data['theme'] ?? 'auto';

if (!in_array($theme, ['auto', 'light', 'dark'])) {
    $theme = 'auto';
}

$_SESSION['platform_theme'] = $theme;

echo json_encode(['success' => true, 'theme' => $theme]);