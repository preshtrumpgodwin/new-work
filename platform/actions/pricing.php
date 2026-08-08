<?php
require_once __DIR__ . '/../../config/security.php';
secure_session_start();

if (isset($_POST['action_update_pricing'])) {
    $tier = trim($_POST['tier_name'] ?? '');
    $monthly = floatval($_POST['monthly_price'] ?? 0);
    $yearly = floatval($_POST['yearly_price'] ?? 0);
    $max = (int)($_POST['max_students'] ?? 100);
    $desc = trim($_POST['description'] ?? '');
    $features = $_POST['package_features'] ?? [];
    if (!empty($tier)) {
        try {
            $pdo->prepare("UPDATE pricing_packages SET monthly_price=?, yearly_price=?, max_students=?, description=?, features_json=? WHERE tier_name=?")
                ->execute([$monthly, $yearly, $max, $desc, json_encode(array_values($features)), $tier]);
            $_SESSION['flash_success'] = "Package '$tier' updated";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = safe_error('Update failed', $e);
        }
    }
    header('Location: index.php?page=pricing');
    exit;
}