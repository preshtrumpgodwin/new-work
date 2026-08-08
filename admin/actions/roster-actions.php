<?php
/**
 * Actions: Student Management / Roster (admin/sections/roster.php)
 * Split out of the old student-actions.php grouping (pure rename — this
 * file's actions already all belonged to one section). Kept the
 * change_student_status handler from here (not the near-duplicate that used
 * to also exist in academic-actions.php) since this version audit-logs the
 * change and looks up by student_uuid rather than trusting roll_number
 * alone; the academic-actions.php copy has been dropped.
 *
 * KNOWN PRE-EXISTING BUG, preserved as-is during this split (not silently
 * fixed mid-move — flagged for a dedicated fix): in action_add_student
 * below, the duplicate-email check reads `$email`, which is never assigned
 * anywhere in this handler (only `$parent_email` is) — so that check is
 * dead code and never actually runs. Needs `$email = safe_str($_POST['email'] ?? '')`
 * added, or removing the check if students don't have their own email field.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$upload_dir = __DIR__ . '/../uploads/photos/students/';

// ── ADD STUDENT ──────────────────────────────────────────────────────────────
if (isset($_POST['action_add_student'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Write permission required.'; return; }

    $name       = safe_str($_POST['student_name']    ?? '');
    $class      = safe_str($_POST['student_class']   ?? '');
    $arm        = safe_str($_POST['student_arm']     ?? 'Gold');
    $gender     = safe_str($_POST['gender']          ?? '');
    $dob        = safe_str($_POST['date_of_birth']   ?? '');
    $adm_date   = safe_str($_POST['admission_date']  ?? date('Y-m-d'));
    $blood      = safe_str($_POST['blood_group']     ?? 'O+');
    $geno       = safe_str($_POST['genotype']        ?? 'AA');
    $allergies  = safe_str($_POST['allergies']       ?? 'None');
    $emerg      = safe_str($_POST['emergency_contact']?? '');
    $status     = 'Active';

    // Parent: prefer UUID link, fall back to manual fields
    $parent_uuid  = safe_str($_POST['parent_uuid']  ?? '');
    $parent_name  = safe_str($_POST['parent_name']  ?? '');
    $parent_email = safe_str($_POST['parent_email'] ?? '');
    $parent_phone = safe_str($_POST['parent_phone'] ?? '');

    if ($parent_uuid) {
        // Pull name/email from parents table
        try {
            $pq = $pdo->prepare("SELECT name, email, phone FROM parents WHERE parent_uuid=? AND school_uuid=? LIMIT 1");
            $pq->execute([$parent_uuid, $school_uuid]);
            $prow = $pq->fetch();
            if ($prow) { $parent_name = $prow['name']; $parent_email = $prow['email']; $parent_phone = $prow['phone']; }
        } catch(Exception $e){}
    }

    if (empty($name) || empty($class)) { $error_msg = 'Name and class are required.'; return; }

    // ✅ Check for duplicate student name (case-insensitive)
    $checkName = $pdo->prepare("SELECT student_uuid FROM students WHERE school_uuid = ? AND LOWER(name) = LOWER(?) LIMIT 1");
    $checkName->execute([$school_uuid, $name]);
    if ($checkName->fetchColumn()) {
        $error_msg = "A student with the name '$name' already exists.";
        return;
    }

    // ✅ Check for duplicate student email (if provided)
    if (!empty($email)) {
        $checkEmail = $pdo->prepare("SELECT student_uuid FROM students WHERE school_uuid = ? AND email = ? LIMIT 1");
        $checkEmail->execute([$school_uuid, $email]);
        if ($checkEmail->fetchColumn()) {
            $error_msg = "A student with email '$email' already exists.";
            return;
        }
    }

    // ✅ Roll number generation using COUNT instead of MAX
    $prefix = 'RC' . date('Y');
    $nextNum = 1;
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

    $photo_error = null;
    $photo        = handle_image_upload('student_photo', $upload_dir, 'std_', '', 5_242_880, $photo_error);
    $health_json  = json_encode(['blood_group'=>$blood,'geno'=>$geno,'genotype'=>$geno,'allergies'=>$allergies,'emergency_contact'=>$emerg]);

    try {
        $std_uuid = uid('std');
        $pdo->prepare("INSERT INTO students
            (student_uuid,school_uuid,admission_number,name,class,arm,roll_number,parent_name,parent_email,parent_phone,
             parent_uuid,photo_path,status,healthcare_json,date_of_birth,gender,admission_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$std_uuid,$school_uuid,$admission_number,$name,$class,$arm,$roll,
                       $parent_name,$parent_email,$parent_phone,
                       $parent_uuid ?: null,$photo,$status,$health_json,$dob,$gender,$adm_date]);

        // Update parent's linked_student_uuids if a parent was linked
        if ($parent_uuid) {
            try {
                $lq = $pdo->prepare("SELECT linked_student_uuids FROM parents WHERE parent_uuid=? AND school_uuid=?");
                $lq->execute([$parent_uuid, $school_uuid]);
                $existing_links = safe_str($lq->fetchColumn() ?? '');
                $uuids_arr = array_filter(array_map('trim', explode(',', $existing_links)));
                if (!in_array($std_uuid, $uuids_arr)) {
                    $uuids_arr[] = $std_uuid;
                    $pdo->prepare("UPDATE parents SET linked_student_uuids=? WHERE parent_uuid=? AND school_uuid=?")
                        ->execute([implode(',', $uuids_arr), $parent_uuid, $school_uuid]);
                }
            } catch(Exception $e){}
        }

        AuditLog::write($pdo, $school_uuid, $user_uuid, 'student.create', $std_uuid, "Enrolled $name — $roll");
        $success_msg = "Student enrolled! Admission No: $admission_number, Roll No: $roll";
        if (!empty($photo_error)) {
            $success_msg .= ' ⚠ ' . $photo_error;
        }
    } catch (PDOException $e) {
        $error_msg = safe_error('Enrollment failed', $e);
    }
}

// ── EDIT STUDENT ─────────────────────────────────────────────────────────────
if (isset($_POST['action_edit_student'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Write permission required.'; return; }

    $std_uuid     = safe_str($_POST['student_uuid']   ?? '');
    $name         = safe_str($_POST['student_name']   ?? '');
    $class        = safe_str($_POST['student_class']  ?? '');
    $arm          = safe_str($_POST['student_arm']    ?? 'Gold');
    $roll         = safe_str($_POST['roll_number']    ?? '');
    $gender       = safe_str($_POST['gender']         ?? '');
    $dob          = safe_str($_POST['date_of_birth']  ?? '');
    $status       = safe_str($_POST['student_status'] ?? 'Active');
    $blood        = safe_str($_POST['blood_group']    ?? 'O+');
    $geno         = safe_str($_POST['genotype']       ?? 'AA');
    $parent_uuid  = safe_str($_POST['parent_uuid']    ?? '');
    $parent_name  = safe_str($_POST['parent_name']    ?? '');
    $parent_email = safe_str($_POST['parent_email']   ?? '');
    $photo_error = null;
    $photo        = handle_image_upload('student_photo', $upload_dir, 'std_', safe_str($_POST['existing_photo'] ?? ''), 5_242_880, $photo_error);
    $health_json  = json_encode(['blood_group'=>$blood,'geno'=>$geno,'genotype'=>$geno,
        'allergies'=>safe_str($_POST['allergies']??'None'),
        'emergency_contact'=>safe_str($_POST['emergency_contact']??'')]);

    // If linking to parent record, pull their name
    if ($parent_uuid) {
        try {
            $pq = $pdo->prepare("SELECT name, email FROM parents WHERE parent_uuid=? AND school_uuid=? LIMIT 1");
            $pq->execute([$parent_uuid, $school_uuid]);
            $prow = $pq->fetch();
            if ($prow) { $parent_name = $prow['name']; $parent_email = $prow['email']; }
        } catch(Exception $e){}
    }

    try {
        $pdo->prepare("UPDATE students SET name=?,class=?,arm=?,roll_number=?,parent_name=?,parent_email=?,
            photo_path=?,healthcare_json=?,date_of_birth=?,gender=?,status=?,parent_uuid=?
            WHERE student_uuid=? AND school_uuid=?")
            ->execute([$name,$class,$arm,$roll,$parent_name,$parent_email,
                       $photo,$health_json,$dob,$gender,$status,
                       $parent_uuid ?: null,$std_uuid,$school_uuid]);

        AuditLog::write($pdo,$school_uuid,$user_uuid,'student.update',$std_uuid,"Updated $name");
        $success_msg = 'Student record updated!';
        if (!empty($photo_error)) {
            $success_msg .= ' ⚠ ' . $photo_error;
        }
    } catch (PDOException $e) { $error_msg = safe_error('Update failed', $e); }
}

// ── DELETE STUDENT ────────────────────────────────────────────────────────────
if (isset($_POST['action_delete_student'])) {
    if ($active_role !== 'School Admin') { $error_msg = 'Only School Admin can delete.'; return; }
    $std_uuid = safe_str($_POST['student_uuid'] ?? '');
    try {
        $pdo->prepare("DELETE FROM students WHERE student_uuid=? AND school_uuid=?")->execute([$std_uuid,$school_uuid]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'student.delete',$std_uuid,'Deleted');
        $success_msg = 'Student deleted.';
    } catch (Exception $e) { $error_msg = 'Deletion failed.'; }
}

// ── INDIVIDUAL STATUS CHANGE (withdraw, suspend, reactivate, graduate) ────────
if (isset($_POST['action_change_student_status'])) {
    if ($active_role !== 'School Admin') { $error_msg = 'Only School Admin can change status.'; return; }
    $roll       = safe_str($_POST['roll_number'] ?? '');
    $new_status = safe_str($_POST['new_status']  ?? 'Withdrawn');
    $reason     = safe_str($_POST['status_reason'] ?? '');
    $allowed    = ['Withdrawn','Suspended','Active','Graduated'];
    if (!in_array($new_status, $allowed)) { $error_msg = 'Invalid status.'; return; }
    try {
        $sq = $pdo->prepare("SELECT student_uuid, name FROM students WHERE school_uuid=? AND roll_number=? LIMIT 1");
        $sq->execute([$school_uuid, $roll]);
        $row = $sq->fetch();
        if (!$row) { $error_msg = "Roll number $roll not found."; return; }
        $pdo->prepare("UPDATE students SET status=? WHERE student_uuid=? AND school_uuid=?")
            ->execute([$new_status, $row['student_uuid'], $school_uuid]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'student.status_change',$row['student_uuid'],
            "Status → $new_status" . ($reason ? " | Reason: $reason" : ''));
        $success_msg = htmlspecialchars($row['name']) . " status changed to $new_status.";
    } catch(Exception $e){ $error_msg = safe_error('Status change failed', $e); }
}

// ── CSV BULK IMPORT ───────────────────────────────────────────────────────────
if (isset($_POST['action_csv_upload_students'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Write permission required.'; return; }
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'No CSV file uploaded.'; return;
    }

    $lines   = file($_FILES['csv_file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $count   = 0; $skipped = 0;
    
    // Get the next roll number using COUNT
    $prefix = 'RC' . date('Y');
    $nextNum = 1;
    try {
        $countExisting = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_uuid = ? AND roll_number LIKE ?");
        $countExisting->execute([$school_uuid, $prefix . '-%']);
        $nextNum = (int)$countExisting->fetchColumn() + 1;
    } catch(Exception $e){ 
        $nextNum = 1;
    }

    // Fallback if count returns 0 or negative
    if ($nextNum < 1) $nextNum = 1;

    $ins = $pdo->prepare("INSERT IGNORE INTO students
        (student_uuid,school_uuid,admission_number,name,class,arm,roll_number,parent_name,parent_email,parent_phone,status,gender,date_of_birth)
        VALUES (?,?,?,?,?,?,?,?,?,?,'Active',?,?)");

    foreach ($lines as $idx => $line) {
        $r = str_getcsv($line);
        $sname = trim($r[0] ?? '');
        if (!$sname || strtolower($sname) === 'name') continue; // skip empty/header
        
        // Roll number generation for each student
        $roll = $prefix . '-' . str_pad($nextNum++, 3, '0', STR_PAD_LEFT);
        $admission_number = generate_admission_number($pdo, $school_uuid);
        
        try {
            $ins->execute([uid('std'),$school_uuid,$admission_number,
                $sname,                     // Name
                trim($r[1] ?? 'JSS1'),      // Class
                trim($r[2] ?? 'Gold'),      // Arm
                $roll,
                trim($r[3] ?? ''),          // Parent name
                trim($r[4] ?? ''),          // Parent email
                trim($r[5] ?? ''),          // Parent phone
                trim($r[6] ?? ''),          // Gender
                trim($r[7] ?? ''),          // DOB
            ]);
            $count++;
        } catch(PDOException $e){ $skipped++; }
    }

    AuditLog::write($pdo,$school_uuid,$user_uuid,'student.csv_import','', "Imported $count, skipped $skipped");
    $success_msg = "Import complete: $count enrolled, $skipped skipped (duplicates).";
}
