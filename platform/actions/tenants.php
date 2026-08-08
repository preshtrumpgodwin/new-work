<?php
// All actions redirect with flash messages
require_once __DIR__ . '/../../config/security.php';
secure_session_start();

if (isset($_POST['action_toggle_suspend'])) {
    $school_uuid = trim($_POST['school_uuid'] ?? '');
    $new_status = trim($_POST['new_status'] ?? 'Suspended');
    if (!empty($school_uuid) && in_array($new_status, ['Suspended','Active'], true)) {
        try {
            $pdo->prepare("UPDATE schools SET status = ? WHERE school_uuid = ?")->execute([$new_status, $school_uuid]);
            $_SESSION['flash_success'] = $new_status === 'Suspended' ? 'School suspended' : 'School reactivated';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = safe_error('Status update failed', $e);
        }
    }
    header('Location: index.php?page=tenants');
    exit;
}

if (isset($_POST['action_manual_billing_adjust'])) {
    $school_uuid = trim($_POST['school_uuid'] ?? '');
    $new_fee = floatval($_POST['monthly_fee'] ?? 0);
    $new_plan = trim($_POST['plan'] ?? 'Standard');
    $billing_cycle = trim($_POST['billing_cycle'] ?? 'Monthly');
    if (!empty($school_uuid)) {
        try {
            $pdo->prepare("UPDATE schools SET monthly_fee=?, plan=?, billing_cycle=? WHERE school_uuid=?")
                ->execute([$new_fee, $new_plan, $billing_cycle, $school_uuid]);
            $_SESSION['flash_success'] = "Billing updated: ₦".number_format($new_fee).", $new_plan, $billing_cycle";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = safe_error('Billing update failed', $e);
        }
    }
    header('Location: index.php?page=tenants');
    exit;
}

if (isset($_POST['action_update_school_features'])) {
    $school_uuid = trim($_POST['school_uuid'] ?? '');
    $feature_rights = $_POST['school_feature_rights'] ?? [];
    if (!empty($school_uuid)) {
        try {
            $catalogKeys = $pdo->query("SELECT feature_key FROM platform_feature_catalog")->fetchAll(PDO::FETCH_COLUMN);
            $pdo->beginTransaction();
            $upsert = $pdo->prepare("INSERT INTO school_feature_access (school_uuid, feature_key, is_enabled) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)");
            foreach ($catalogKeys as $fk) {
                $enabled = isset($feature_rights[$fk]) ? 1 : 0;
                $upsert->execute([$school_uuid, $fk, $enabled]);
            }
            $pdo->commit();
            $_SESSION['flash_success'] = 'Feature access updated';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = safe_error('Feature update failed', $e);
        }
    }
    header('Location: index.php?page=tenants');
    exit;
}

if (isset($_POST['action_reset_admin_password'])) {
    $admin_email = trim($_POST['admin_email'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    if (!empty($admin_email) && !empty($new_password)) {
        try {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND role = 'School Admin' LIMIT 1");
            $stmt->execute([$hash, $admin_email]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['flash_success'] = "Password reset for $admin_email";
            } else {
                $_SESSION['flash_error'] = "No school admin found with email $admin_email";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = safe_error('Password reset failed', $e);
        }
    }
    header('Location: index.php?page=tenants');
    exit;
}

if (isset($_POST['action_delete_school'])) {
    $school_uuid = trim($_POST['school_uuid'] ?? '');
    if (!empty($school_uuid)) {
        try {
            $pdo->beginTransaction();
            $name = $pdo->prepare("SELECT name FROM schools WHERE school_uuid=?")->execute([$school_uuid])->fetchColumn();
            $pdo->prepare("DELETE FROM schools WHERE school_uuid=?")->execute([$school_uuid]);
            $pdo->commit();
            $_SESSION['flash_success'] = "School '$name' deleted permanently";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = safe_error('Delete failed', $e);
        }
    }
    header('Location: index.php?page=tenants');
    exit;
}