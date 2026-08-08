<?php
/**
 * Platform Manager Reset Script - FIXED VERSION
 * 
 * This uses password_hash() to generate a NEW hash with a known password
 * that we can use to log in.
 */

// ============================================================
// CONFIGURATION
// ============================================================

// Database connection settings
$db_host = 'localhost';
$db_name = 'zetaphase_cloud';
$db_user = 'root';          // CHANGE THIS
$db_pass = '';              // CHANGE THIS

// NEW PASSWORD - CHANGE THIS TO WHAT YOU WANT
$new_plaintext_password = 'Precious$1999';

// Platform Manager credentials
$platform_manager = [
    'user_uuid' => 'usr-platform-mgr-0001',
    'name'      => 'Precious Philip Godwin',
    'email'     => 'preshtrumpgodwin@gmail.com',
    'role'      => 'Platform Manager',
    'portal_type' => 'platform',
    'department'  => 'Platform'
];

// ============================================================
// GENERATE NEW HASH
// ============================================================

$new_hash = password_hash($new_plaintext_password, PASSWORD_BCRYPT);

// ============================================================
// CONNECT TO DATABASE
// ============================================================

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Platform Manager Reset - FIXED</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        .success { color: #155724; background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; }
        .error { color: #721c24; background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; }
        .info { color: #0c5460; background: #d1ecf1; padding: 15px; border-radius: 5px; border: 1px solid #bee5eb; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffc107; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>🔄 Platform Manager Reset - FIXED</h1>
<p>This script will remove any existing platform managers and insert <strong>{$platform_manager['name']}</strong> with a <strong>NEW password</strong>.</p>
<hr>";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("<div class='error'>❌ Database connection failed: " . $conn->connect_error . "</div></body></html>");
}

echo "<div class='info'>✅ Connected to database: <strong>{$db_name}</strong></div>";

// ============================================================
// STEP 1: DELETE EXISTING PLATFORM MANAGERS
// ============================================================

echo "<h2>Step 1: Removing existing platform managers</h2>";

$count_query = "SELECT COUNT(*) as count FROM `users` WHERE `portal_type` = 'platform'";
$count_result = $conn->query($count_query);
$existing_count = $count_result->fetch_assoc()['count'];
echo "<p>Found <strong>{$existing_count}</strong> existing platform manager(s).</p>";

$delete_query = "DELETE FROM `users` WHERE `portal_type` = 'platform'";
if ($conn->query($delete_query)) {
    $deleted = $conn->affected_rows;
    echo "<div class='success'>✅ Removed <strong>{$deleted}</strong> platform manager account(s).</div>";
} else {
    die("<div class='error'>❌ Failed to delete platform managers: " . $conn->error . "</div></body></html>");
}

// ============================================================
// STEP 2: INSERT WITH NEW HASH
// ============================================================

echo "<h2>Step 2: Inserting new platform manager with NEW password hash</h2>";

// Display the new hash info
echo "<div class='info'>";
echo "<p><strong>New plaintext password:</strong> " . htmlspecialchars($new_plaintext_password) . "</p>";
echo "<p><strong>New hash generated:</strong> " . substr($new_hash, 0, 30) . "...</p>";
echo "<p><strong>Hash algorithm:</strong> bcrypt (PASSWORD_BCRYPT)</p>";
echo "</div>";

$insert_query = "INSERT INTO `users` (
    `user_uuid`, `school_uuid`, `name`, `email`, `password_hash`, 
    `role`, `portal_type`, `phone`, `photo_path`, `department`, 
    `created_at`, `failed_login_attempts`, `locked_until`, 
    `must_reset_password`, `last_login_at`, `temp_password_expires_at`
) VALUES (
    '" . $conn->real_escape_string($platform_manager['user_uuid']) . "',
    NULL,
    '" . $conn->real_escape_string($platform_manager['name']) . "',
    '" . $conn->real_escape_string($platform_manager['email']) . "',
    '" . $conn->real_escape_string($new_hash) . "',
    '" . $conn->real_escape_string($platform_manager['role']) . "',
    '" . $conn->real_escape_string($platform_manager['portal_type']) . "',
    NULL,
    NULL,
    '" . $conn->real_escape_string($platform_manager['department']) . "',
    NOW(),
    0,
    NULL,
    0,
    NULL,
    NULL
)";

if ($conn->query($insert_query)) {
    echo "<div class='success'>✅ Platform manager inserted successfully with NEW password hash!</div>";
} else {
    die("<div class='error'>❌ Failed to insert platform manager: " . $conn->error . "</div></body></html>");
}

// ============================================================
// STEP 3: VERIFY THE HASH WORKS
// ============================================================

echo "<h2>Step 3: Verifying the password works</h2>";

// Get the stored hash
$verify_query = "SELECT `password_hash` FROM `users` WHERE `portal_type` = 'platform'";
$verify_result = $conn->query($verify_query);

if ($verify_result && $verify_result->num_rows > 0) {
    $row = $verify_result->fetch_assoc();
    $stored_hash = $row['password_hash'];
    
    // Test if our password verifies against the stored hash
    $test_verify = password_verify($new_plaintext_password, $stored_hash);
    
    if ($test_verify) {
        echo "<div class='success'>✅ Password verification test PASSED!";
        echo "<br>The password '<strong>" . htmlspecialchars($new_plaintext_password) . "</strong>' correctly verifies against the stored hash.</div>";
    } else {
        echo "<div class='error'>❌ Password verification test FAILED!";
        echo "<br>The password '<strong>" . htmlspecialchars($new_plaintext_password) . "</strong>' does NOT verify against the stored hash.</div>";
    }
    
    echo "<pre>";
    echo "Stored hash: " . $stored_hash . "\n";
    echo "Hash length: " . strlen($stored_hash) . " characters\n";
    echo "Algorithm: " . (strpos($stored_hash, '$2y$') === 0 ? 'bcrypt ($2y$)' : (strpos($stored_hash, '$2b$') === 0 ? 'bcrypt ($2b$)' : 'unknown'));
    echo "</pre>";
}

// ============================================================
// STEP 4: LOGIN INFORMATION
// ============================================================

echo "<h2>🔐 Login Information</h2>";
echo "<div class='success'>";
echo "<p><strong>URL:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'platform.yourdomain.com') . "</p>";
echo "<p><strong>Email:</strong> " . $platform_manager['email'] . "</p>";
echo "<p><strong>Password:</strong> " . htmlspecialchars($new_plaintext_password) . "</p>";
echo "<p><strong>Portal:</strong> Platform Manager Dashboard</p>";
echo "<p><strong>Note:</strong> The password hash has been generated fresh using PHP's <code>password_hash()</code> function. This is the recommended way to store passwords and will work with your login page's <code>password_verify()</code> check.</p>";
echo "</div>";

// ============================================================
// SHOW ALL USERS
// ============================================================

echo "<h2>Current Users (All Roles)</h2>";

$all_users_query = "SELECT `user_uuid`, `name`, `email`, `role`, `portal_type` FROM `users` ORDER BY `portal_type`, `role`";
$all_users_result = $conn->query($all_users_query);

if ($all_users_result && $all_users_result->num_rows > 0) {
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f4f4f4;'><th style='padding:8px; border:1px solid #ddd; text-align:left;'>User UUID</th>
              <th style='padding:8px; border:1px solid #ddd; text-align:left;'>Name</th>
              <th style='padding:8px; border:1px solid #ddd; text-align:left;'>Email</th>
              <th style='padding:8px; border:1px solid #ddd; text-align:left;'>Role</th>
              <th style='padding:8px; border:1px solid #ddd; text-align:left;'>Portal Type</th></tr>";
    
    while ($row = $all_users_result->fetch_assoc()) {
        $bg = ($row['portal_type'] === 'platform') ? '#d4edda' : '';
        echo "<tr style='background:$bg;'>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($row['user_uuid']) . "</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($row['portal_type']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found in the database.</p>";
}

// ============================================================
// CLOSE CONNECTION
// ============================================================

$conn->close();

echo "<hr>";
echo "<p><small>Script executed at: " . date('Y-m-d H:i:s') . "</small></p>";
echo "</body></html>";
?>