<?php
require_once __DIR__ . '/../../config/security.php';
secure_session_start();

if (isset($_POST['action_approve'])) {
    $req_id = (int)$_POST['action_approve'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM onboarding_requests WHERE id=? AND status='Pending'");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();
        if (!$req) {
            $_SESSION['flash_error'] = 'Request not found or already processed';
            header('Location: index.php?page=requests');
            exit;
        }
        $pdo->beginTransaction();
        $school_uuid = uid('sch');
        $user_uuid = uid('usr');
        $temp_password = generate_temp_password();
        
        // Insert school
        $pdo->prepare("INSERT INTO schools (school_uuid, name, subdomain, admin_email, status, plan, monthly_fee, created_date) VALUES (?, ?, ?, ?, 'Active', ?, 65000, NOW())")
            ->execute([$school_uuid, $req['school_name'], $req['subdomain'], $req['email'], $req['plan']]);
        
        // Insert user - forced to reset this temporary password on first login
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+72 hours'));
        $pdo->prepare("INSERT INTO users (user_uuid, school_uuid, name, email, password_hash, role, must_reset_password, temp_password_expires_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)")
            ->execute([$user_uuid, $school_uuid, $req['contact_name'], $req['email'], password_hash($temp_password, PASSWORD_BCRYPT), 'School Admin', $temp_expiry]);
        
        // Update onboarding request
        $pdo->prepare("UPDATE onboarding_requests SET status='Approved' WHERE id=?")->execute([$req_id]);
        $pdo->commit();
        $_SESSION['flash_success'] = "School '{$req['school_name']}' provisioned! Temporary admin password: $temp_password (share securely — they'll be asked to set a new one on first login)";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = safe_error('Approval failed', $e);
    }
    header('Location: index.php?page=requests');
    exit;
}

if (isset($_POST['action_reject'])) {
    $req_id = (int)$_POST['action_reject'];
    try {
        $stmt = $pdo->prepare("UPDATE onboarding_requests SET status='Rejected' WHERE id=? AND status='Pending'");
        $stmt->execute([$req_id]);
        $_SESSION['flash_success'] = 'Request rejected';
    } catch (Exception $e) {
        $_SESSION['flash_error'] = safe_error('Reject failed', $e);
    }
    header('Location: index.php?page=requests');
    exit;
}