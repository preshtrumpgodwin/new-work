<?php
// PHP Parent Portal Workspace - Database-Driven and Fully Interactive
require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once 'config/db.php';
require_once __DIR__ . '/config/Crypto.php';
require_once 'admin/lib/Helpers.php';

// Authorization Gate - Must be logged in & role must be Parent
if (!isset($_SESSION['user_uuid']) || $_SESSION['role'] !== 'Parent') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: parent-portal.php?error=' . urlencode('Your session expired — please try again.'));
    exit;
}

// Handle personal Change Password action
$change_pwd_error = '';
$change_pwd_success = '';
$open_pwd_modal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_user_password'])) {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $open_pwd_modal   = true;

    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $change_pwd_error = 'New passwords do not match.';
        } elseif (($policyErr = password_policy_check($new_password)) !== '') {
            $change_pwd_error = $policyErr;
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_uuid = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_uuid']]);
                $user = $stmt->fetch();

                if ($user) {
                    $password_matches = password_verify($current_password, $user['password_hash']);

                    if ($password_matches) {
                        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE user_uuid = ?");
                        $upd->execute([$new_hash, $_SESSION['user_uuid']]);

                        $logStmt = $pdo->prepare("INSERT INTO audit_logs (school_uuid, user_email, action) VALUES (?, ?, ?)");
                        $logStmt->execute([$_SESSION['school_uuid'] ?? NULL, $_SESSION['email'], "Changed personal account password successfully"]);

                        $change_pwd_success = 'Your password has been changed successfully!';
                    } else {
                        $change_pwd_error = 'Current password or secure key is incorrect.';
                    }
                } else {
                    $change_pwd_error = 'User account not found.';
                }
            } catch (PDOException $e) {
                $change_pwd_error = safe_error('Database error', $e);
            }
        }
    } else {
        $change_pwd_error = 'Please complete all password fields.';
    }
}

$school_uuid = $_SESSION['school_uuid'];
$user_uuid   = $_SESSION['user_uuid'];

// ── Fetch school branding ──────────────────────────────────────────────────
$school_brand = [];
try {
    $__sb = $pdo->prepare("SELECT name, logo_path, theme_color, subdomain FROM schools WHERE school_uuid = ? LIMIT 1");
    $__sb->execute([$school_uuid]);
    $school_brand = $__sb->fetch() ?: [];
} catch (Exception $e) {}
$_brand_name  = $school_brand['name']       ?? 'School Portal';
$_brand_logo  = $school_brand['logo_path']   ?? '';
$_brand_color = $school_brand['theme_color'] ?? '#4F46E5';
$_brand_sub   = ($school_brand['subdomain']  ?? 'school') . '.zetaphase.com.ng';
// ───────────────────────────────────────────────────────────────────────────
$school_settings_pp = [];
try {
    $__ss = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid = ? LIMIT 1");
    $__ss->execute([$school_uuid]);
    $school_settings_pp = $__ss->fetch() ?: [];
} catch (Exception $e) {}
require_once __DIR__ . '/admin/lib/Notify.php';
// Helpers.php already loaded near the top of this file.
$success_msg = '';
$error_msg = '';

// Find Parent profile linked to this user (match by email — parents table has
// no user_uuid column, so that half of the old query always failed silently).
$parent = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM parents WHERE email = ? AND school_uuid = ? LIMIT 1");
    $stmt->execute([$_SESSION['email'], $school_uuid]);
    $parent = $stmt->fetch();
} catch (PDOException $e) {
    $parent = null;
}
if (!$parent) {
    // No parent record on file for this login — show an empty-state portal
    // instead of crashing or fabricating fake data.
    $parent = ['parent_uuid' => null, 'name' => $_SESSION['name'] ?? 'Parent', 'email' => $_SESSION['email'] ?? '', 'phone' => ''];
}
$parent_uuid = $parent['parent_uuid'];

// A parent may have more than one child; let them switch between wards.
$wards = [];
if ($parent_uuid) {
    try {
        $wardStmt = $pdo->prepare("SELECT * FROM students WHERE parent_uuid = ? AND school_uuid = ? ORDER BY name ASC");
        $wardStmt->execute([$parent_uuid, $school_uuid]);
        $wards = $wardStmt->fetchAll();
    } catch (Exception $e) { $wards = []; }
}
$selected_ward_uuid = trim((string)($_GET['ward'] ?? ''));
$ward = null;
foreach ($wards as $w) { if ($w['student_uuid'] === $selected_ward_uuid) { $ward = $w; break; } }
if (!$ward && !empty($wards)) $ward = $wards[0];
$student_uuid = $ward['student_uuid'] ?? null;

// Handle appointment request (parent-initiated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_appointment_parent']) && $parent_uuid) {
    $teacher_uuid = trim($_POST['teacher_uuid'] ?? '');
    $mdate = trim($_POST['meeting_date'] ?? '');
    $mtime = trim($_POST['meeting_time'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $tn = $pdo->prepare("SELECT name, user_uuid FROM staff WHERE staff_uuid=? AND school_uuid=?");
    $tn->execute([$teacher_uuid, $school_uuid]);
    $teacher = $tn->fetch();
    if ($teacher_uuid && $teacher && $mdate && $purpose) {
        $apt_uuid = uid('apt');
        $pdo->prepare("INSERT INTO parent_teacher_appointments (appointment_uuid,school_uuid,parent_uuid,parent_name,teacher_uuid,teacher_name,student_name,meeting_date,meeting_time,purpose,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,'Pending')")
            ->execute([$apt_uuid, $school_uuid, $parent_uuid, $parent['name'], $teacher_uuid, $teacher['name'], $ward['name'] ?? '', $mdate, $mtime, $purpose]);
        if (!empty($teacher['user_uuid'])) {
            Notify::user($pdo, $school_uuid, $teacher['user_uuid'], 'New appointment request', "{$parent['name']} requested a meeting on $mdate", 'info', 'dashboard.php?section=consultations');
        }
        $success_msg = 'Appointment request sent to ' . $teacher['name'] . '!';
    } else {
        $error_msg = 'Please select a teacher, date, and purpose.';
    }
}

// Payment request (parent-initiated) — Phase E
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_payment_parent']) && $parent_uuid) {
    $desc = trim((string)($_POST['description'] ?? ''));
    $amt  = trim((string)($_POST['amount'] ?? ''));
    $stu  = trim((string)($_POST['student_uuid'] ?? ''));
    if ($desc === '') {
        $error_msg = 'Please describe what the payment is for.';
    } else {
        $req_uuid = uid('payreq');
        $pdo->prepare("INSERT INTO payment_requests (request_uuid,school_uuid,parent_uuid,student_uuid,description,amount,status)
            VALUES (?,?,?,?,?,?,'Pending')")
            ->execute([$req_uuid, $school_uuid, $parent_uuid, $stu ?: null, $desc, $amt !== '' ? $amt : null]);
        $success_msg = 'Payment request sent to the school!';
    }
}

// Handle direct message to a teacher (parent-initiated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_send_pt_message_parent']) && $parent_uuid) {
    $receiver_uuid = trim($_POST['receiver_uuid'] ?? '');
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $text = trim($_POST['message_text'] ?? '');
    if ($receiver_uuid && $text) {
        $pdo->prepare("INSERT INTO parent_teacher_messages (message_uuid,school_uuid,sender_uuid,sender_name,sender_role,receiver_uuid,receiver_name,message_text)
            VALUES (?,?,?,?,'Parent',?,?,?)")
            ->execute([uid('ptm'), $school_uuid, $parent_uuid, $parent['name'], $receiver_uuid, $receiver_name, $text]);
        Notify::user($pdo, $school_uuid, $receiver_uuid, "New message from {$parent['name']}", mb_substr($text, 0, 140), 'info', 'dashboard.php?section=consultations');
        $success_msg = 'Message sent!';
    } else {
        $error_msg = 'Select a teacher and enter a message.';
    }
}

// Handle Online Payment via Paystack — initializes a real transaction and redirects
// to Paystack's hosted checkout. The invoice is only marked paid after
// paystack-callback.php independently verifies the transaction server-side.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pay_invoice_online'])) {
    $inv_uuid = trim($_POST['invoice_uuid'] ?? '');
    $pay_amt  = floatval($_POST['amount'] ?? 0);

    $psSt = $pdo->prepare("SELECT * FROM school_payment_settings WHERE school_uuid = ? LIMIT 1");
    $psSt->execute([$school_uuid]);
    $paySettings = $psSt->fetch();
    if ($paySettings) {
        $paySettings['paystack_secret_key'] = Crypto::decrypt($paySettings['paystack_secret_key'] ?? '');
    }

    if (empty($paySettings) || empty($paySettings['payments_enabled']) || empty($paySettings['paystack_secret_key'])) {
        $error_msg = 'Online payments are not yet enabled for this school. Please pay at the school office or contact the school admin.';
    } elseif (empty($inv_uuid) || $pay_amt <= 0) {
        $error_msg = 'Invalid payment request.';
    } else {
        $iq = $pdo->prepare("SELECT * FROM school_invoices WHERE invoice_uuid=? AND school_uuid=? LIMIT 1");
        $iq->execute([$inv_uuid, $school_uuid]);
        $inv = $iq->fetch();
        if (!$inv) {
            $error_msg = 'Invoice not found.';
        } else {
            $paidSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
            $paidSt->execute([$inv_uuid]);
            $paidSoFar = (float)$paidSt->fetchColumn();
            $balance   = (float)$inv['amount'] - $paidSoFar;
            $pay_amt   = min($pay_amt, max(0, $balance)); // never accept more than the outstanding balance

            if ($pay_amt <= 0) {
                $error_msg = 'This invoice has no outstanding balance.';
            } else {
                $reference = 'ZP-' . strtoupper(bin2hex(random_bytes(8)));
                $txn_uuid = uid('txn');

                $pdo->prepare("INSERT INTO payment_transactions (txn_uuid, school_uuid, invoice_uuid, student_uuid, parent_uuid, reference, amount, currency, status, gateway) VALUES (?,?,?,?,?,?,?,'NGN','Pending','Paystack')")
                    ->execute([$txn_uuid, $school_uuid, $inv_uuid, $student_uuid, $parent_uuid, $reference, $pay_amt]);

                $callback_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/paystack-callback.php';
                $ch = curl_init('https://api.paystack.co/transaction/initialize');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $paySettings['paystack_secret_key'],
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'email'        => $parent['email'],
                        'amount'       => (int) round($pay_amt * 100), // kobo
                        'reference'    => $reference,
                        'callback_url' => $callback_url,
                        'metadata'     => ['invoice_uuid' => $inv_uuid, 'school_uuid' => $school_uuid, 'txn_uuid' => $txn_uuid],
                    ]),
                    CURLOPT_TIMEOUT => 15,
                ]);
                $resp = curl_exec($ch);
                $curlErr = curl_error($ch);
                curl_close($ch);

                $json = $resp ? json_decode($resp, true) : null;
                if ($json && !empty($json['status']) && !empty($json['data']['authorization_url'])) {
                    header('Location: ' . $json['data']['authorization_url']);
                    exit;
                } else {
                    error_log('Paystack initialize failed: ' . ($curlErr ?: $resp));
                    $pdo->prepare("UPDATE payment_transactions SET status='Failed', gateway_response=? WHERE txn_uuid=?")
                        ->execute([$curlErr ?: substr((string)$resp, 0, 500), $txn_uuid]);
                    $error_msg = 'Could not start the payment — please try again shortly.';
                }
            }
        }
    }
}

// Handle Online Payment via Flutterwave — Phase E, mirrors the Paystack flow above
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pay_invoice_flutterwave'])) {
    $inv_uuid = trim($_POST['invoice_uuid'] ?? '');
    $pay_amt  = floatval($_POST['amount'] ?? 0);

    $fwSt = $pdo->prepare("SELECT * FROM flutterwave_settings WHERE school_uuid = ? LIMIT 1");
    $fwSt->execute([$school_uuid]);
    $fwSettings = $fwSt->fetch();
    if ($fwSettings) {
        $fwSettings['secret_key_enc'] = Crypto::decrypt($fwSettings['secret_key_enc'] ?? '');
    }

    if (empty($fwSettings) || empty($fwSettings['is_active']) || empty($fwSettings['secret_key_enc'])) {
        $error_msg = 'Flutterwave is not yet enabled for this school. Please try Paystack or pay at the school office.';
    } elseif (empty($inv_uuid) || $pay_amt <= 0) {
        $error_msg = 'Invalid payment request.';
    } else {
        $iq = $pdo->prepare("SELECT * FROM school_invoices WHERE invoice_uuid=? AND school_uuid=? LIMIT 1");
        $iq->execute([$inv_uuid, $school_uuid]);
        $inv = $iq->fetch();
        if (!$inv) {
            $error_msg = 'Invoice not found.';
        } else {
            $paidSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
            $paidSt->execute([$inv_uuid]);
            $paidSoFar = (float)$paidSt->fetchColumn();
            $balance   = (float)$inv['amount'] - $paidSoFar;
            $pay_amt   = min($pay_amt, max(0, $balance));

            if ($pay_amt <= 0) {
                $error_msg = 'This invoice has no outstanding balance.';
            } else {
                $reference = 'ZP-FW-' . strtoupper(bin2hex(random_bytes(8)));
                $txn_uuid = uid('txn');

                $pdo->prepare("INSERT INTO payment_transactions (txn_uuid, school_uuid, invoice_uuid, student_uuid, parent_uuid, reference, amount, currency, status, gateway) VALUES (?,?,?,?,?,?,?,'NGN','Pending','Flutterwave')")
                    ->execute([$txn_uuid, $school_uuid, $inv_uuid, $student_uuid, $parent_uuid, $reference, $pay_amt]);

                $callback_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/flutterwave-callback.php';
                $ch = curl_init('https://api.flutterwave.com/v3/payments');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $fwSettings['secret_key_enc'],
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'tx_ref'       => $reference,
                        'amount'       => $pay_amt,
                        'currency'     => 'NGN',
                        'redirect_url' => $callback_url,
                        'customer'     => ['email' => $parent['email'], 'name' => $parent['name'] ?? ''],
                        'meta'         => ['invoice_uuid' => $inv_uuid, 'school_uuid' => $school_uuid, 'txn_uuid' => $txn_uuid],
                    ]),
                    CURLOPT_TIMEOUT => 15,
                ]);
                $resp = curl_exec($ch);
                $curlErr = curl_error($ch);
                curl_close($ch);

                $json = $resp ? json_decode($resp, true) : null;
                if ($json && ($json['status'] ?? '') === 'success' && !empty($json['data']['link'])) {
                    header('Location: ' . $json['data']['link']);
                    exit;
                } else {
                    error_log('Flutterwave initialize failed: ' . ($curlErr ?: $resp));
                    $pdo->prepare("UPDATE payment_transactions SET status='Failed', gateway_response=? WHERE txn_uuid=?")
                        ->execute([$curlErr ?: substr((string)$resp, 0, 500), $txn_uuid]);
                    $error_msg = 'Could not start the Flutterwave payment — please try again shortly.';
                }
            }
        }
    }
}

// FETCH DATA FOR THE PARENT'S WARD
$invoices = []; $receipts = []; $report_card = null; $grades = []; $domain_ratings = []; $disciplinary = []; $ward_assignments = []; $credit_balance = 0;
if ($student_uuid) {
    try {
        // 1. Finance: Invoices (per-student, real schema) & Receipts
        $invStmt = $pdo->prepare("SELECT * FROM school_invoices WHERE student_uuid = ? AND school_uuid = ? ORDER BY id DESC");
        $invStmt->execute([$student_uuid, $school_uuid]);
        $invoices = $invStmt->fetchAll();
        foreach ($invoices as &$_inv) {
            $ps = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
            $ps->execute([$_inv['invoice_uuid']]);
            $_inv['paid_amount'] = (float)$ps->fetchColumn();
        }
        unset($_inv);

        $recStmt = $pdo->prepare("SELECT r.* FROM school_receipts r JOIN school_invoices i ON i.invoice_uuid=r.invoice_uuid WHERE i.student_uuid=? AND i.school_uuid=? ORDER BY r.id DESC");
        $recStmt->execute([$student_uuid, $school_uuid]);
        $receipts = $recStmt->fetchAll();

        $credit_balance = 0;
        try {
            $ccq = $pdo->prepare("SELECT balance FROM student_fee_credits WHERE school_uuid=? AND student_uuid=? LIMIT 1");
            $ccq->execute([$school_uuid, $student_uuid]);
            $credit_balance = (float)($ccq->fetchColumn() ?: 0);
        } catch (Exception $e) {}

        // 2. Report Card & Grades (current session/term)
        $cur_session = $school_settings_pp['current_session'] ?? '';
        $cur_term    = $school_settings_pp['current_term'] ?? '';
        $rcStmt = $pdo->prepare("SELECT * FROM report_cards WHERE student_uuid = ? AND school_uuid = ? AND status='Approved' ORDER BY created_at DESC LIMIT 1");
        $rcStmt->execute([$student_uuid, $school_uuid]);
        $report_card = $rcStmt->fetch() ?: null;

        if ($report_card) {
            $gStmt = $pdo->prepare("SELECT * FROM results WHERE student_uuid=? AND school_uuid=? AND session_name=? AND term_name=? ORDER BY subject_name ASC");
            $gStmt->execute([$student_uuid, $school_uuid, $report_card['session_name'], $report_card['term_name']]);
            $grades = $gStmt->fetchAll();

            $drStmt = $pdo->prepare("SELECT * FROM student_domain_ratings WHERE student_uuid=? AND school_uuid=? AND session_name=? AND term_name=?");
            $drStmt->execute([$student_uuid, $school_uuid, $report_card['session_name'], $report_card['term_name']]);
            $domain_ratings = $drStmt->fetchAll();
        }

        // 3. Disciplinary / Behavior Records
        $discStmt = $pdo->prepare("SELECT * FROM student_behavior_records WHERE student_uuid = ? AND school_uuid = ? ORDER BY recorded_at DESC");
        $discStmt->execute([$student_uuid, $school_uuid]);
        $disciplinary = $discStmt->fetchAll();

        // 4. Assignments — only ones approved by a full-access staff/admin
        //    (or approved via a confirmed parent meeting) ever reach here.
        $wardAssStmt = $pdo->prepare("
            SELECT a.*, s.status as submission_status, s.grade_score as grade, s.submitted_at
            FROM assignments a
            LEFT JOIN assignment_submissions s ON a.assignment_uuid = s.assignment_uuid AND s.student_uuid = ?
            WHERE a.school_uuid = ? AND a.class_name = ? AND a.approval_status = 'Approved'
            ORDER BY a.due_date DESC
        ");
        $wardAssStmt->execute([$student_uuid, $school_uuid, $student['class']]);
        $ward_assignments = $wardAssStmt->fetchAll();
    } catch (Exception $e) {
        // Fail soft — show empty states rather than crash the whole portal
    }
}

// 4. Messages & appointments with teachers (real schema, parent-initiated)
$pt_messages = []; $pt_appointments = []; $school_teachers = [];
if ($parent_uuid) {
    try {
        $mst = $pdo->prepare("SELECT * FROM parent_teacher_messages WHERE school_uuid=? AND (sender_uuid=? OR receiver_uuid=?) ORDER BY sent_at DESC LIMIT 30");
        $mst->execute([$school_uuid, $parent_uuid, $parent_uuid]);
        $pt_messages = $mst->fetchAll();

        $apt = $pdo->prepare("SELECT * FROM parent_teacher_appointments WHERE school_uuid=? AND parent_uuid=? ORDER BY meeting_date DESC");
        $apt->execute([$school_uuid, $parent_uuid]);
        $pt_appointments = $apt->fetchAll();

        $tch = $pdo->prepare("SELECT staff_uuid, user_uuid, name FROM staff WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
        $tch->execute([$school_uuid]);
        $school_teachers = $tch->fetchAll();
    } catch (Exception $e) {}
}

$active_tab = $_GET['tab'] ?? 'finance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_brand_name); ?> — Parent Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" type="image/jpeg" href="logo.png">
    <script src="assets/js/lucide.min.js"></script>
    <style>
        :root { <?php echo accent_shade_vars($_brand_color); ?> }
        .brand-accent  { color: var(--brand-color); }
        .brand-bg      { background-color: var(--brand-color); }
        .brand-bg-soft { background-color: color-mix(in srgb, var(--brand-color) 12%, transparent); border-color: color-mix(in srgb, var(--brand-color) 30%, transparent); }
        .brand-border  { border-color: color-mix(in srgb, var(--brand-color) 35%, transparent); }
    </style>
</head>
<body class="bg-brandDark text-slate-100 min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- Header layout -->
    <header class="border-b border-brandBorder bg-brandCard/85 backdrop-blur-md sticky top-0 z-40 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <?php if (!empty($_brand_logo)): ?>
                <img src="<?php echo htmlspecialchars(asset_url($_brand_logo)); ?>" alt="Logo" class="w-8 h-8 rounded-lg object-cover border border-brandBorder shadow-lg">
            <?php else: ?>
                <div class="w-8 h-8 rounded-lg brand-bg flex items-center justify-center shadow-lg">
                    <i data-lucide="users" class="w-4 h-4 text-white"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="font-extrabold text-white text-sm tracking-tight block"><?php echo htmlspecialchars($_brand_name); ?> — Parent Portal</h1>
                <span class="text-[9px] text-slate-500 block font-mono">Parent: <?php echo htmlspecialchars($parent['name']); ?><?php echo $ward ? ' • Ward: ' . htmlspecialchars($ward['name']) : ''; ?></span>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <?php if (count($wards) > 1): ?>
            <form method="GET" class="hidden sm:block">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab ?? 'finance'); ?>">
                <select name="ward" onchange="this.form.submit()" class="bg-brandDark border border-brandBorder rounded-lg px-2 py-1.5 text-[10px] text-white">
                    <?php foreach ($wards as $w2): ?>
                    <option value="<?php echo htmlspecialchars($w2['student_uuid']); ?>" <?php echo ($ward && $ward['student_uuid']===$w2['student_uuid']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($w2['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <?php if ($ward): ?>
            <div class="text-right">
                <span class="text-xs font-bold text-white block">Student Class: <?php echo htmlspecialchars($ward['class']); ?></span>
                <span class="text-[9px] text-slate-400 font-mono block brand-accent"><?php echo htmlspecialchars($_brand_sub); ?></span>
            </div>
            <?php endif; ?>
            <!-- Change Password Trigger -->
            <button onclick="openChangePasswordModal()" title="Change Password" class="w-8 h-8 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 flex items-center justify-center text-indigo-400 transition-all cursor-pointer">
                <i data-lucide="key-round" class="w-4 h-4"></i>
            </button>

            <a href="logout.php" title="Log Out" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 flex items-center justify-center text-rose-400 transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </header>

    <!-- Main Navigation & Workspace Frame -->
    <div class="max-w-7xl w-full mx-auto p-6 flex flex-col md:flex-row gap-6 flex-1">
        
        <!-- Sidebar Navigation -->
        <aside class="w-full md:w-64 space-y-2 shrink-0">
            <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl space-y-4">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest font-mono">Guardian Modules</p>
                <nav class="space-y-1">
                    <a href="?tab=finance" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'finance') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="wallet" class="w-4 h-4"></i>
                        <span>Fee Invoices & Ledger</span>
                    </a>
                    <a href="?tab=academic" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'academic') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="award" class="w-4 h-4"></i>
                        <span>Report Card & Grades</span>
                    </a>
                    <a href="?tab=assignments" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'assignments') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="book-open" class="w-4 h-4"></i>
                        <span>Assignments</span>
                    </a>
                    <a href="?tab=disciplinary" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'disciplinary') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                        <span>Behavior & Discipline</span>
                    </a>
                    <a href="?tab=messages" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'messages') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="message-square-text" class="w-4 h-4"></i>
                        <span>Communications Inbox</span>
                    </a>
                </nav>
            </div>

            <!-- Profile Info Card -->
            <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl space-y-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-full bg-slate-800 flex items-center justify-center border border-brandBorder text-slate-300 font-extrabold text-sm">
                        <?php echo substr($parent['name'], 0, 1); ?>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-white block"><?php echo htmlspecialchars($parent['name']); ?></span>
                        <span class="text-[9px] text-slate-400 block font-mono"><?php echo htmlspecialchars($parent['phone']); ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Workspace View Container -->
        <main class="flex-1 space-y-6">

            <!-- TAB 1: FINANCE LEDGER -->
            <?php if ($active_tab === 'finance'): ?>
                <div class="space-y-6">
                    <div class="bg-brandCard border border-brandBorder p-6 rounded-2xl relative overflow-hidden shadow-xl space-y-1">
                        <h2 class="text-lg font-bold text-white">Institutional Finance Ledger</h2>
                        <p class="text-xs text-slate-400">View and track term tuition fees, development levies, outstanding bills, and payment records.</p>
                    </div>

                    <?php if (!$ward): ?>
                        <div class="bg-brandCard border border-brandBorder rounded-2xl p-10 text-center">
                            <i data-lucide="user-x" class="w-8 h-8 text-slate-600 mx-auto mb-2"></i>
                            <p class="text-xs text-slate-500">No student record is linked to your account yet. Please contact the school office.</p>
                        </div>
                    <?php else: ?>
                    <?php if ($credit_balance > 0): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4 flex items-center gap-3">
                        <i data-lucide="wallet" class="w-5 h-5 text-emerald-400"></i>
                        <p class="text-xs text-emerald-400"><strong>₦<?php echo number_format($credit_balance, 2); ?> credit balance</strong> on file from a previous overpayment — this will be automatically applied to your next invoice.</p>
                    </div>
                    <?php endif; ?>
                    <!-- Payment Request (parent-initiated) — Phase E -->
                    <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-3">
                        <div class="flex items-center space-x-2 text-indigo-400">
                            <i data-lucide="send" class="w-4.5 h-4.5"></i>
                            <h3 class="text-sm font-bold text-white">Request a Payment / Invoice</h3>
                        </div>
                        <p class="text-[11px] text-gray-400">Need an invoice for something not yet billed (e.g. a trip, extra materials)? Send a request to the school office.</p>
                        <form method="POST" class="flex flex-col md:flex-row gap-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action_request_payment_parent" value="1">
                            <input type="hidden" name="student_uuid" value="<?php echo htmlspecialchars($ward['student_uuid'] ?? ''); ?>">
                            <input type="text" name="description" required placeholder="e.g. Excursion fee for Term 2" class="flex-1 bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white">
                            <input type="number" step="0.01" name="amount" placeholder="Amount (optional)" class="w-full md:w-40 bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl">Send Request</button>
                        </form>
                    </div>

                    <!-- Invoices list -->
                    <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                        <div class="border-b border-brandBorder pb-3 flex items-center space-x-2 text-indigo-400">
                            <i data-lucide="receipt" class="w-4.5 h-4.5"></i>
                            <h3 class="text-sm font-bold text-white">Invoices for <?php echo htmlspecialchars($ward['name']); ?></h3>
                        </div>

                        <div class="space-y-4">
                            <?php if (empty($invoices)): ?>
                                <p class="text-xs text-slate-500 py-4 text-center">No invoices on file for your ward yet.</p>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice):
                                    $balance = (float)$invoice['amount'] - (float)$invoice['paid_amount'];
                                ?>
                                    <div class="bg-brandDark border border-brandBorder p-5 rounded-2xl space-y-4 font-sans">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-brandBorder/60">
                                            <div>
                                                <h4 class="text-xs font-black text-white">Invoice No: <?php echo htmlspecialchars($invoice['invoice_no']); ?></h4>
                                                <span class="text-[10px] text-slate-400 font-mono block mt-0.5"><?php echo htmlspecialchars($invoice['plan']); ?><?php echo !empty($invoice['term_name']) ? ' — ' . htmlspecialchars($invoice['term_name']) . ' (' . htmlspecialchars($invoice['session_name']) . ')' : ''; ?></span>
                                            </div>
                                            <div class="text-right">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono inline-block <?php echo ($invoice['status'] === 'Paid') ? 'bg-emerald-500/10 text-emerald-400' : (($invoice['status']==='Partial') ? 'bg-amber-500/10 text-amber-400' : 'bg-rose-500/10 text-rose-400'); ?>">
                                                    <?php echo htmlspecialchars($invoice['status']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="bg-brandCard/40 p-3.5 rounded-xl border border-brandBorder text-xs space-y-2">
                                            <div class="flex justify-between font-bold text-white">
                                                <span>Total Billing Amount</span>
                                                <span class="text-indigo-400 font-mono">₦<?php echo number_format((float)$invoice['amount'], 2); ?></span>
                                            </div>
                                            <div class="flex justify-between text-xs text-emerald-400 font-bold">
                                                <span>Paid Amount</span>
                                                <span class="font-mono">₦<?php echo number_format((float)$invoice['paid_amount'], 2); ?></span>
                                            </div>
                                            <div class="flex justify-between text-xs text-rose-400 font-bold pt-2 border-t border-brandBorder">
                                                <span>Outstanding Balance</span>
                                                <span class="font-mono">₦<?php echo number_format($balance, 2); ?></span>
                                            </div>
                                        </div>

                                        <?php if ($balance > 0): ?>
                                            <div class="pt-3 border-t border-brandBorder/60 flex items-center justify-between flex-wrap gap-2">
                                                <span class="text-[10px] text-slate-400">Secure Direct Instant Payment Gateway</span>
                                                <div class="flex items-center space-x-2">
                                                    <form method="POST" action="parent-portal.php" class="inline"><?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action_pay_invoice_online" value="1">
                                                        <input type="hidden" name="invoice_uuid" value="<?php echo htmlspecialchars($invoice['invoice_uuid']); ?>">
                                                        <input type="hidden" name="amount" value="<?php echo $balance; ?>">
                                                        <button type="submit" name="gateway" value="Paystack" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] rounded-lg shadow-md flex items-center space-x-1">
                                                            <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                                                            <span>Pay with Paystack</span>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="parent-portal.php" class="inline"><?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action_pay_invoice_flutterwave" value="1">
                                                        <input type="hidden" name="invoice_uuid" value="<?php echo htmlspecialchars($invoice['invoice_uuid']); ?>">
                                                        <input type="hidden" name="amount" value="<?php echo $balance; ?>">
                                                        <button type="submit" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-[10px] rounded-lg shadow-md flex items-center space-x-1">
                                                            <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                                                            <span>Pay via Flutterwave</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment Receipts -->
                    <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                        <div class="border-b border-brandBorder pb-3 flex items-center space-x-2 text-indigo-400">
                            <i data-lucide="check-square" class="w-4.5 h-4.5"></i>
                            <h3 class="text-sm font-bold text-white">Recent Payment Receipts</h3>
                        </div>

                        <div class="space-y-3">
                            <?php if (empty($receipts)): ?>
                                <p class="text-xs text-slate-500 py-3 text-center">No payment receipts logged yet.</p>
                            <?php else: ?>
                                <?php foreach ($receipts as $receipt): ?>
                                    <div class="bg-brandDark border border-brandBorder p-4 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 font-mono text-xs">
                                        <div>
                                            <span class="text-slate-400">Receipt: </span><strong class="text-white"><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong>
                                            <span class="text-[10px] text-slate-500 block">Method: <?php echo htmlspecialchars($receipt['payment_method']); ?><?php echo !empty($receipt['received_by']) ? ' • Logged by: ' . htmlspecialchars($receipt['received_by']) : ''; ?></span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-emerald-400 font-extrabold block">+₦<?php echo number_format((float)$receipt['amount'], 2); ?></span>
                                            <span class="text-[10px] text-slate-500 block"><?php echo htmlspecialchars($receipt['payment_date']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 2: ACADEMIC PROGRESS -->
            <?php if ($active_tab === 'academic'): ?>
                <div class="bg-brandCard border border-brandBorder p-6 rounded-2xl shadow-xl space-y-6">
                    <div class="border-b border-brandBorder pb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">Ward Terminal Report Card</h3>
                            <p class="text-[11px] text-slate-500">Official scorecard pulled directly from the student grades database<?php echo $ward ? ' for ' . htmlspecialchars($ward['name']) : ''; ?>.</p>
                        </div>
                        <i data-lucide="award" class="w-5 h-5 text-indigo-400"></i>
                    </div>

                    <?php if (!$ward): ?>
                        <p class="text-xs text-slate-500 py-6 text-center">No student record is linked to your account yet.</p>
                    <?php elseif (!$report_card): ?>
                        <p class="text-xs text-slate-500 py-6 text-center">No approved report card published yet for this session/term.</p>
                    <?php else: ?>
                        <div class="space-y-6 font-sans">
                            <!-- Metadata -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-brandDark rounded-xl border border-brandBorder font-mono text-xs">
                                <div>
                                    <span class="text-[9px] text-slate-500 block">STUDENT</span>
                                    <strong class="text-white"><?php echo htmlspecialchars($ward['name']); ?></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-500 block">TERM / SESSION</span>
                                    <strong class="text-white"><?php echo htmlspecialchars($report_card['term_name'] . ' (' . $report_card['session_name'] . ')'); ?></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-500 block">CLASS</span>
                                    <strong class="text-white"><?php echo htmlspecialchars($report_card['class_name'] . ' ' . $report_card['arm_name']); ?></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-500 block">STATUS</span>
                                    <strong class="text-emerald-400"><?php echo htmlspecialchars($report_card['status']); ?></strong>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-brandBorder text-slate-400 bg-brandDark/50">
                                            <th class="py-2.5 px-3 font-bold">Subject</th>
                                            <th class="py-2.5 px-3 font-bold text-center">CA1</th>
                                            <th class="py-2.5 px-3 font-bold text-center">CA2</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Exam</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Total</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Grade</th>
                                            <th class="py-2.5 px-3 font-bold">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brandBorder">
                                        <?php if (empty($grades)): ?>
                                            <tr><td colspan="7" class="py-4 text-center text-slate-500 italic">No subject scores recorded yet.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr class="hover:bg-slate-800/10">
                                                <td class="py-3 px-3 font-bold text-white"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                                <td class="py-3 px-3 text-center text-slate-300 font-mono"><?php echo (float)$grade['ca1_score']; ?></td>
                                                <td class="py-3 px-3 text-center text-slate-300 font-mono"><?php echo (float)$grade['ca2_score']; ?></td>
                                                <td class="py-3 px-3 text-center text-slate-300 font-mono"><?php echo (float)$grade['exam_score']; ?></td>
                                                <td class="py-3 px-3 text-center text-indigo-400 font-mono font-bold"><?php echo (float)$grade['total_score']; ?></td>
                                                <td class="py-3 px-3 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400">
                                                        <?php echo htmlspecialchars($grade['grade'] ?? '—'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3 text-slate-400 font-medium"><?php echo htmlspecialchars($grade['subject_teacher_remark'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (!empty($domain_ratings)): ?>
                            <!-- Affective / Psychomotor -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach (['Affective','Psychomotor'] as $dtype):
                                    $rows = array_filter($domain_ratings, fn($r) => $r['domain_type'] === $dtype); ?>
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase block mb-2"><?php echo $dtype; ?> Domain</span>
                                    <?php if (empty($rows)): ?>
                                        <p class="text-[10px] text-slate-600 italic">Not rated yet.</p>
                                    <?php endif; ?>
                                    <?php foreach ($rows as $r): ?>
                                        <div class="flex justify-between text-xs py-1">
                                            <span class="text-slate-300"><?php echo htmlspecialchars($r['trait_name']); ?></span>
                                            <span class="font-mono text-indigo-400"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Comments -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase block mb-1">Class Teacher Remarks</span>
                                    <p class="text-xs text-slate-300 italic">"<?php echo htmlspecialchars($report_card['teacher_comment'] ?: 'No comment yet.'); ?>"</p>
                                </div>
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase block mb-1">Principal Remarks</span>
                                    <p class="text-xs text-slate-300 italic">"<?php echo htmlspecialchars($report_card['principal_comment'] ?: 'No comment yet.'); ?>"</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 3: DISCIPLINARY LOGS -->
            <?php if ($active_tab === 'assignments'): ?>
                <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                    <div class="border-b border-brandBorder pb-3 flex items-center space-x-2 text-indigo-400">
                        <i data-lucide="book-open" class="w-4.5 h-4.5"></i>
                        <h3 class="text-sm font-bold text-white">Ward's Assignments</h3>
                    </div>
                    <div class="space-y-4">
                        <?php if (!$ward): ?>
                            <p class="text-xs text-slate-500 py-6 text-center">No student record is linked to your account yet.</p>
                        <?php elseif (empty($ward_assignments)): ?>
                            <p class="text-xs text-slate-500 py-6 text-center">No approved assignments for your child's class yet.</p>
                        <?php else: ?>
                            <?php foreach ($ward_assignments as $a): ?>
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xs font-black text-white"><?php echo htmlspecialchars($a['title']); ?></h4>
                                            <span class="text-[10px] text-indigo-400 font-bold font-mono"><?php echo htmlspecialchars($a['subject']); ?> · Set by <?php echo htmlspecialchars($a['assigned_by_staff_name'] ?: $a['teacher_name']); ?></span>
                                        </div>
                                        <span class="text-[10px] text-rose-400 font-mono">Due: <?php echo htmlspecialchars($a['due_date']); ?></span>
                                    </div>
                                    <p class="text-[11px] text-slate-300"><?php echo nl2br(htmlspecialchars($a['description'])); ?></p>
                                    <?php if ($a['submission_status'] === 'Graded'): ?>
                                        <span class="inline-block px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[9px] font-bold">Graded: <?php echo htmlspecialchars($a['grade']); ?> / <?php echo (int)$a['max_score']; ?></span>
                                    <?php elseif ($a['submission_status'] === 'Submitted'): ?>
                                        <span class="inline-block px-2 py-0.5 bg-blue-500/10 text-blue-400 rounded text-[9px] font-bold">Submitted — awaiting grade</span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-0.5 bg-amber-500/10 text-amber-400 rounded text-[9px] font-bold">Not yet submitted</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($active_tab === 'disciplinary'): ?>
                <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                    <div class="border-b border-brandBorder pb-3 flex items-center space-x-2 text-indigo-400">
                        <i data-lucide="shield-alert" class="w-4.5 h-4.5"></i>
                        <h3 class="text-sm font-bold text-white">Ward Behavior & Disciplinary Logs</h3>
                    </div>

                    <div class="space-y-4">
                        <?php if (!$ward): ?>
                            <p class="text-xs text-slate-500 py-6 text-center">No student record is linked to your account yet.</p>
                        <?php elseif (empty($disciplinary)): ?>
                            <p class="text-xs text-slate-500 py-6 text-center">No behavior records logged for your child. Outstanding!</p>
                        <?php else: ?>
                            <?php foreach ($disciplinary as $record): ?>
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl space-y-3 font-sans">
                                    <div class="flex items-center justify-between pb-2 border-b border-brandBorder/50 text-xs">
                                        <div>
                                            <span class="<?php echo $record['incident_type']==='Merit' ? 'text-emerald-400' : 'text-rose-400'; ?> font-extrabold block"><?php echo htmlspecialchars($record['incident_type']); ?>: <?php echo htmlspecialchars($record['title']); ?></span>
                                            <span class="text-[9px] text-slate-500 block mt-0.5">Reported by: <?php echo htmlspecialchars($record['reported_by']); ?></span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold font-mono <?php echo $record['incident_type']==='Merit' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>">
                                            <?php echo ($record['points'] >= 0 ? '+' : '') . (int)$record['points']; ?> pts
                                        </span>
                                    </div>
                                    <?php if (!empty($record['description'])): ?>
                                    <div class="text-xs text-slate-300"><?php echo htmlspecialchars($record['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-slate-300">
                                        <strong class="text-white">Action Taken:</strong> <?php echo htmlspecialchars($record['action_taken']); ?>
                                    </div>
                                    <div class="text-[9px] text-slate-500 font-mono text-right">Date: <?php echo htmlspecialchars(date('d M Y', strtotime($record['recorded_at']))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 4: COMMUNICATIONS INBOX -->
            <?php if ($active_tab === 'messages'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Appointments -->
                    <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                        <div class="border-b border-brandBorder pb-3 flex items-center justify-between">
                            <div class="flex items-center space-x-2 text-indigo-400">
                                <i data-lucide="calendar-heart" class="w-4.5 h-4.5"></i>
                                <h3 class="text-sm font-bold text-white">Teacher Appointments</h3>
                            </div>
                            <button onclick="document.getElementById('newApptModal').classList.remove('hidden')" class="text-[10px] font-bold text-indigo-400">+ Request</button>
                        </div>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php if (empty($pt_appointments)): ?>
                                <p class="text-xs text-slate-500 py-6 text-center">No appointments requested yet.</p>
                            <?php endif; ?>
                            <?php foreach ($pt_appointments as $a):
                                $sc = ['Pending'=>'bg-amber-500/10 text-amber-400','Confirmed'=>'bg-emerald-500/10 text-emerald-400','Declined'=>'bg-rose-500/10 text-rose-400','Completed'=>'bg-indigo-500/10 text-indigo-400'];
                            ?>
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl text-xs space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-white"><?php echo htmlspecialchars($a['teacher_name']); ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $sc[$a['status']] ?? 'bg-slate-500/10 text-slate-400'; ?>"><?php echo htmlspecialchars($a['status']); ?></span>
                                    </div>
                                    <p class="text-slate-400"><?php echo htmlspecialchars($a['purpose']); ?></p>
                                    <p class="text-[10px] text-slate-500 font-mono"><?php echo date('d M Y', strtotime($a['meeting_date'])); ?> <?php echo htmlspecialchars($a['meeting_time']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                        <div class="border-b border-brandBorder pb-3 flex items-center space-x-2 text-indigo-400">
                            <i data-lucide="mail-open" class="w-4.5 h-4.5"></i>
                            <h3 class="text-sm font-bold text-white">Messages with Teachers</h3>
                        </div>
                        <div class="space-y-3 max-h-72 overflow-y-auto">
                            <?php if (empty($pt_messages)): ?>
                                <p class="text-xs text-slate-500 py-6 text-center">No messages yet.</p>
                            <?php endif; ?>
                            <?php foreach ($pt_messages as $m): $mine = $m['sender_uuid'] === $parent_uuid; ?>
                                <div class="max-w-[85%] <?php echo $mine ? 'ml-auto bg-indigo-600 text-white' : 'bg-brandDark text-slate-200 border border-brandBorder'; ?> rounded-xl px-3 py-2 text-xs">
                                    <span class="block font-bold text-[10px] opacity-80"><?php echo htmlspecialchars($mine ? 'You' : $m['sender_name']); ?></span>
                                    <?php echo htmlspecialchars($m['message_text']); ?>
                                    <div class="text-[9px] opacity-60 mt-1 font-mono"><?php echo date('d M, H:i', strtotime($m['sent_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" class="flex gap-2 pt-2 border-t border-brandBorder"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_send_pt_message_parent" value="1">
                            <select name="receiver_uuid" onchange="const o=this.options[this.selectedIndex]; document.getElementById('msgReceiverName').value=o.dataset.name||'';" class="bg-brandDark border border-brandBorder rounded-lg px-2 py-2 text-[10px] text-white" required>
                                <option value="">Teacher...</option>
                                <?php foreach ($school_teachers as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['user_uuid']); ?>" data-name="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="msgReceiverName" name="receiver_name">
                            <input type="text" name="message_text" required placeholder="Type a message..." class="flex-1 bg-brandDark border border-brandBorder rounded-lg px-3 py-2 text-xs text-white">
                            <button type="submit" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg"><i data-lucide="send" class="w-3.5 h-3.5"></i></button>
                        </form>
                    </div>
                </div>

                <!-- Request Appointment Modal -->
                <div id="newApptModal" class="fixed inset-0 bg-brandDark/85 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                    <div class="bg-brandCard border border-brandBorder rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-white">Request Teacher Appointment</h3>
                            <button onclick="document.getElementById('newApptModal').classList.add('hidden')" class="text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
                        </div>
                        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_request_appointment_parent" value="1">
                            <div>
                                <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Teacher *</label>
                                <select name="teacher_uuid" required class="w-full bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white">
                                    <option value="">Select teacher...</option>
                                    <?php foreach ($school_teachers as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['staff_uuid']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Date *</label>
                                    <input type="date" name="meeting_date" required class="w-full bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Time</label>
                                    <input type="text" name="meeting_time" placeholder="10:00 AM" class="w-full bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Purpose *</label>
                                <textarea name="purpose" required rows="3" class="w-full bg-brandDark border border-brandBorder rounded-xl p-3 text-xs text-white"></textarea>
                            </div>
                            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl">Send Request</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Layout Footer -->
    <footer class="p-6 text-center text-xs text-slate-600 border-t border-brandBorder mt-12 bg-brandCard/20">
        <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_brand_name); ?>. Powered by Zetaphase EduCloud.</span>
    </footer>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="fixed inset-0 bg-brandDark/85 backdrop-blur-sm z-50 <?php echo $open_pwd_modal ? 'flex' : 'hidden'; ?> items-center justify-center p-4">
        <div class="bg-brandCard border border-brandBorder rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl relative text-left">
            <button onclick="closeChangePasswordModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition-all cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
            <div class="flex items-center space-x-2 text-indigo-400">
                <i data-lucide="key-round" class="w-5 h-5"></i>
                <h3 class="text-sm font-bold text-white">Change Account Password</h3>
            </div>
            <p class="text-xs text-slate-400">Update your access credentials for safety. Ensure to save your new secret key code.</p>
            
            <?php if (!empty($change_pwd_error)): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 p-3 rounded-xl text-xs text-rose-400 flex items-center space-x-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($change_pwd_error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($change_pwd_success)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 p-3 rounded-xl text-xs text-emerald-400 flex items-center space-x-2">
                    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($change_pwd_success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_change_user_password" value="1">
                
                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">Current Password / Key</label>
                    <input type="password" name="current_password" required placeholder="••••••••" class="w-full bg-brandDark border border-brandBorder rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">New Password</label>
                    <input type="password" name="new_password" required placeholder="••••••••" class="w-full bg-brandDark border border-brandBorder rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">Confirm New Password</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••" class="w-full bg-brandDark border border-brandBorder rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div class="flex space-x-3 justify-end pt-2">
                    <button type="button" onclick="closeChangePasswordModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all font-mono cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all font-mono cursor-pointer">Apply Change</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openChangePasswordModal() {
            const modal = document.getElementById('changePasswordModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeChangePasswordModal() {
            const modal = document.getElementById('changePasswordModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }
        lucide.createIcons();
    </script>
</body>
</html>
