<?php
/**
 * API Endpoint: Submit School Onboarding Request
 * 
 * This ONLY saves the request as "Pending" for Platform Manager review.
 * No provisioning, user creation, or billing happens here.
 */

header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$school_name    = trim($_POST['school_name'] ?? '');
$subdomain      = trim($_POST['subdomain'] ?? '');
$contact_name   = trim($_POST['contact_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$applicant_role = trim($_POST['applicant_role'] ?? 'School Admin');
$student_count  = intval($_POST['student_count'] ?? 150);
$plan           = trim($_POST['plan'] ?? 'Standard');
$billing_cycle  = trim($_POST['billing_cycle'] ?? 'Monthly');

// Validate required fields
if (empty($school_name) || empty($subdomain) || empty($contact_name) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'All fields are strictly required.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Ensure subdomain contains only letters/numbers/hyphens
if (!preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
    echo json_encode(['success' => false, 'message' => 'Subdomain can only contain alphanumeric characters or hyphens.']);
    exit;
}

$clean_subdomain = strtolower($subdomain);

// Reserved subdomains that cannot be used
$reserved = ['www', 'mail', 'smtp', 'pop', 'ftp', 'admin', 'platform', 'api', 'app', 'dashboard', 'login', 'portal', 'school', 'student', 'parent', 'teacher', 'staff'];
if (in_array($clean_subdomain, $reserved)) {
    echo json_encode(['success' => false, 'message' => 'This subdomain is reserved. Please choose another.']);
    exit;
}

try {
    // Check if subdomain already exists in schools table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schools WHERE subdomain = ?");
    $stmt->execute([$clean_subdomain]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This school subdomain is already registered. Please choose another.']);
        exit;
    }

    // Check if subdomain already exists in pending onboarding requests
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM onboarding_requests WHERE subdomain = ? AND status = 'Pending'");
    $stmt->execute([$clean_subdomain]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A pending request for this subdomain already exists. Please wait for review or choose another.']);
        exit;
    }

    // Check if email already has a pending request
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM onboarding_requests WHERE email = ? AND status = 'Pending'");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have a pending onboarding request. Please wait for review.']);
        exit;
    }

    // ✅ ONLY INSERT INTO onboarding_requests WITH STATUS 'Pending'
    // NO provisioning, NO user creation, NO billing - that's the Platform Manager's job
    $stmt = $pdo->prepare("
        INSERT INTO onboarding_requests (
            school_name, subdomain, contact_name, email, phone, 
            applicant_role, student_count, plan, billing_cycle, 
            status, request_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURRENT_DATE)
    ");
    
    $stmt->execute([
        $school_name,
        $clean_subdomain,
        $contact_name,
        $email,
        $phone,
        $applicant_role,
        $student_count,
        $plan,
        $billing_cycle
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your onboarding request has been submitted successfully! Our team will review your application and you will receive an email with login credentials once approved. This typically takes 1-2 business days.'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}