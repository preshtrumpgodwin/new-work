<?php
// Simple PHP Logout Script - Clears Sessions & Redirects
require_once __DIR__ . '/config/security.php';
secure_session_start();

// Destroy all session information
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login gate
header("Location: login.php");
exit;