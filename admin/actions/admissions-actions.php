<?php
/**
 * Actions: Online Admissions (admin/sections/admissions.php)
 * Split out of the old phase4-actions.php grouping.
 *
 * This already handles BOTH student AND staff applications from the same
 * public_applications table (branches on applicant_type) — if there's no
 * visible place to review staff applications, that's a missing/unlinked UI
 * page, not missing backend logic; the admissions.php section should list
 * both types (e.g. as filterable tabs) so admins can see and act on staff
 * applications too.
 *
 * BUG FIX: the catch block below used to wrap EVERY exception — including
 * the deliberate, specific ones thrown just above ("A staff member with the
 * name '...' already exists.", "A user with email '...' already exists.")
 * — through safe_error(), which always returns the same generic "Approval
 * failed. Please try again — if this keeps happening, contact support."
 * unless APP_DEBUG=1 is set. That made a genuine, actionable duplicate
 * collision look identical to an unknown server error, with no way for the
 * admin to tell why approval kept failing or what to fix. Now: exceptions
 * we deliberately threw ourselves (plain Exception, with a message already
 * safe to show) are displayed as-is; only genuine unexpected failures
 * (PDOException — a real DB/SQL problem) still go through safe_error() to
 * avoid leaking raw SQL errors to the browser.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// ── ADMISSIONS: APPROVE / REJECT ──────────────────────────────────────────────
if (isset($_POST['action_process_application']) && $active_role === 'School Admin') {
    $app_uuid = safe_str($_POST['app_uuid'] ?? '');
    $decision = safe_str($_POST['decision'] ?? 'Approved'); // Approved | Rejected
    $note     = safe_str($_POST['review_note'] ?? '');

    $aq = $pdo->prepare("SELECT * FROM public_applications WHERE app_uuid=? AND school_uuid=?");
    $aq->execute([$app_uuid, $school_uuid]);
    $app = $aq->fetch();

    if (!$app) {
        $error_msg = 'Application not found.';
    } elseif ($app['status'] !== 'Pending') {
        $error_msg = 'This application has already been processed.';
    } else {
        if ($decision === 'Rejected') {
            $pdo->prepare("UPDATE public_applications SET status='Rejected', reviewed_by=?, review_note=?, reviewed_at=NOW() WHERE app_uuid=? AND school_uuid=?")
                ->execute([$_SESSION['name'] ?? 'Admin', $note, $app_uuid, $school_uuid]);
            $success_msg = "Application from {$app['applicant_name']} rejected.";
        } else {
            // Approve: actually create the real record, not just flip a status flag
            try {
                $pdo->beginTransaction();
                $health_json = $app['healthcare_json'] ?: '{}';

                if ($app['applicant_type'] === 'student') {
                    // Extract class from applied_class_or_role
                    $class = trim($app['applied_class_or_role'] ?? '');
                    // If the string contains " - ", take the part before it (e.g. "JSS1 - Gold" → "JSS1")
                    if (strpos($class, ' - ') !== false) {
                        $class = explode(' - ', $class)[0];
                    }
                    
                    // ✅ Check for duplicate student name (case-insensitive)
                    $checkName = $pdo->prepare("SELECT student_uuid FROM students WHERE school_uuid = ? AND LOWER(name) = LOWER(?) LIMIT 1");
                    $checkName->execute([$school_uuid, $app['applicant_name']]);
                    if ($checkName->fetchColumn()) {
                        throw new Exception("A student with the name '{$app['applicant_name']}' already exists.");
                    }

                    // ✅ Check for duplicate student email
                    if (!empty($app['email'])) {
                        $checkEmail = $pdo->prepare("SELECT student_uuid FROM students WHERE school_uuid = ? AND email = ? LIMIT 1");
                        $checkEmail->execute([$school_uuid, $app['email']]);
                        if ($checkEmail->fetchColumn()) {
                            throw new Exception("A student with email '{$app['email']}' already exists.");
                        }
                    }

                    // ✅ REMOVED: Parent email duplicate check — a parent can have multiple children
                    
                    // ✅ Use COUNT instead of MAX to avoid corrupted roll numbers
                    $prefix = 'RC' . date('Y');
                    try {
                        $count = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_uuid = ? AND roll_number LIKE ?");
                        $count->execute([$school_uuid, $prefix . '-%']);
                        $nextNum = (int)$count->fetchColumn() + 1;
                    } catch(Exception $e) {
                        $nextNum = 1;
                    }
                    
                    // Fallback if count returns 0 or negative
                    if ($nextNum < 1) $nextNum = 1;
                    
                    // Cap at 999 to keep 3-digit format
                    if ($nextNum > 999) {
                        $nextNum = 999;
                    }
                    
                    $roll = $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    $admission_number = generate_admission_number($pdo, $school_uuid);
                    $std_uuid = uid('std');

                    $pdo->prepare("INSERT INTO students 
                        (student_uuid, school_uuid, admission_number, name, class, arm, roll_number, 
                         parent_name, parent_email, parent_phone, photo_path, status, healthcare_json, admission_date)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())")
                        ->execute([
                            $std_uuid, $school_uuid, $admission_number, 
                            $app['applicant_name'], $class, 'Gold', $roll,
                            $app['parent_name'] ?: $app['applicant_name'], 
                            $app['email'], 
                            $app['parent_phone'] ?: $app['phone'],
                            $app['photo_path'], 'Active', $health_json
                        ]);

                    $pdo->prepare("UPDATE public_applications SET status='Approved', reviewed_by=?, review_note=?, reviewed_at=NOW() WHERE app_uuid=? AND school_uuid=?")
                        ->execute([$_SESSION['name'] ?? 'Admin', $note, $app_uuid, $school_uuid]);

                    $pdo->commit();
                    AuditLog::write($pdo,$school_uuid,$user_uuid,'admissions.approve',$std_uuid,"Enrolled student {$app['applicant_name']} (Admission $admission_number)");
                    $success_msg = "Approved! {$app['applicant_name']} enrolled as a student — Admission No: $admission_number, Roll: $roll.";
                } else {
                    // Staff application
                    $staff_uuid = uid('stf');
                    $user_new_uuid = uid('usr');
                    $temp_password = generate_temp_password();
                    $default_pass = password_hash($temp_password, PASSWORD_DEFAULT);
                    $temp_expiry = date('Y-m-d H:i:s', strtotime('+72 hours'));

                    // ✅ Check for duplicate staff name (case-insensitive)
                    $checkName = $pdo->prepare("SELECT staff_uuid FROM staff WHERE school_uuid = ? AND LOWER(name) = LOWER(?) LIMIT 1");
                    $checkName->execute([$school_uuid, $app['applicant_name']]);
                    if ($checkName->fetchColumn()) {
                        throw new Exception("A staff member with the name '{$app['applicant_name']}' already exists.");
                    }

                    // ✅ Check for duplicate user email
                    $checkEmail = $pdo->prepare("SELECT user_uuid FROM users WHERE school_uuid = ? AND email = ? LIMIT 1");
                    $checkEmail->execute([$school_uuid, $app['email']]);
                    if ($checkEmail->fetchColumn()) {
                        throw new Exception("A user with email '{$app['email']}' already exists.");
                    }

                    $pdo->prepare("INSERT INTO users (user_uuid, school_uuid, name, email, password_hash, role, phone, must_reset_password, temp_password_expires_at) 
                        VALUES (?,?,?,?,?,?,?,1,?)")
                        ->execute([
                            $user_new_uuid, $school_uuid, 
                            $app['applicant_name'], $app['email'], 
                            $default_pass, 'Teacher', 
                            $app['phone'], $temp_expiry
                        ]);

                    $pdo->prepare("INSERT INTO staff (staff_uuid, user_uuid, school_uuid, name, email, phone, role, qualification, status, photo_path, healthcare_json, date_employed)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE())")
                        ->execute([
                            $staff_uuid, $user_new_uuid, $school_uuid, 
                            $app['applicant_name'], $app['email'], $app['phone'],
                            $app['applied_class_or_role'] ?: 'Teacher', 
                            $app['qualification'], 'Active', 
                            $app['photo_path'], $health_json
                        ]);

                    $pdo->prepare("UPDATE public_applications SET status='Approved', reviewed_by=?, review_note=?, reviewed_at=NOW() WHERE app_uuid=? AND school_uuid=?")
                        ->execute([$_SESSION['name'] ?? 'Admin', $note, $app_uuid, $school_uuid]);

                    $pdo->commit();
                    AuditLog::write($pdo,$school_uuid,$user_uuid,'admissions.approve',$staff_uuid,"Onboarded staff {$app['applicant_name']}");
                    $success_msg = "Approved! {$app['applicant_name']} onboarded as staff. Temporary password: $temp_password (they'll be asked to set a new one on first login)";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // See the file-header note: PDOException (a real DB/SQL failure)
                // still goes through safe_error() to avoid leaking raw SQL; a
                // plain Exception is one we threw ourselves above with an
                // already-safe, specific message, so show it directly instead
                // of collapsing it into a generic "try again" message.
                $error_msg = ($e instanceof PDOException)
                    ? safe_error('Approval failed', $e)
                    : $e->getMessage();
            }
        }
    }
}
