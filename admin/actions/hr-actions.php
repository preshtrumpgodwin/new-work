<?php
/**
 * Staff / HR Actions — full fields including qualification, TRCN, dept, DOB, address
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$upload_dir = __DIR__ . '/../uploads/photos/staff/';

// ── ADD STAFF ─────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_staff'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager'])) { $error_msg = 'Only the school admin can add staff.'; return; }
    if ($active_role !== 'School Admin') { $error_msg = 'Only School Admin can add staff.'; return; }

    $s_name   = safe_str($_POST['staff_name']       ?? '');
    $s_email  = safe_str($_POST['staff_email']      ?? '');
    $s_phone  = safe_str($_POST['staff_phone']      ?? '');
    $s_role   = safe_str($_POST['staff_role']       ?? 'Teacher');
    $s_dept   = safe_str($_POST['staff_dept']       ?? 'Academics');
    $s_sal    = max(0, (float)($_POST['staff_salary'] ?? 120000));
    $s_dob    = safe_str($_POST['date_of_birth']    ?? '');
    $s_gen    = safe_str($_POST['gender']           ?? '');
    $s_addr   = safe_str($_POST['address']          ?? '');
    $s_emp    = safe_str($_POST['date_employed']    ?? date('Y-m-d'));
    $s_qual   = safe_str($_POST['staff_qual']       ?? '');
    $s_course = safe_str($_POST['qual_course']      ?? '');
    $s_trcn   = safe_str($_POST['trcn_number']      ?? '');
    $s_certs  = safe_str($_POST['other_certifications'] ?? '');
    $blood    = safe_str($_POST['blood_group']      ?? 'O+');
    $geno     = safe_str($_POST['genotype']         ?? 'AA');
    $allergies= safe_str($_POST['allergies']        ?? 'None');
    $emerg    = safe_str($_POST['emergency_contact']?? '');
    $photo_error = null;
    $photo    = handle_image_upload('staff_photo', $upload_dir, 'stf_', '', 5_242_880, $photo_error);

    // Build full qualification string
    $full_qual = trim("$s_qual" . ($s_course ? " — $s_course" : ''));
    $health_json = json_encode(['blood_group'=>$blood,'geno'=>$geno,'genotype'=>$geno,
        'allergies'=>$allergies,'emergency_contact'=>$emerg,'other_certifications'=>$s_certs]);

    if (empty($s_name) || empty($s_email)) { $error_msg = 'Name and email are required.'; return; }

    try {
        // Create user account
        $u_uuid = uid('usr-stf');
        $temp_password = generate_temp_password();
        $hash   = password_hash($temp_password, PASSWORD_BCRYPT);
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+72 hours'));
        $pdo->prepare("INSERT INTO users (user_uuid,school_uuid,name,email,password_hash,role,phone,photo_path,department,must_reset_password,temp_password_expires_at)
            VALUES (?,?,?,?,?,?,?,?,?,1,?)")
            ->execute([$u_uuid,$school_uuid,$s_name,$s_email,$hash,$s_role,$s_phone,$photo,$s_dept,$temp_expiry]);

        // Create staff record
        $stf_uuid = uid('stf');
        $pdo->prepare("INSERT INTO staff
            (staff_uuid,user_uuid,school_uuid,name,email,phone,role,qualification,salary,status,
             photo_path,healthcare_json,date_of_birth,gender,address,date_employed)
            VALUES (?,?,?,?,?,?,?,?,?,'Active',?,?,?,?,?,?)")
            ->execute([$stf_uuid,$u_uuid,$school_uuid,$s_name,$s_email,$s_phone,
                       $s_role,$full_qual,$s_sal,$photo,$health_json,$s_dob,$s_gen,$s_addr,$s_emp]);

        // TRCN — store in staff table if column exists, else skip gracefully
        try { $pdo->prepare("UPDATE staff SET trcn_number=?,department=? WHERE staff_uuid=?")->execute([$s_trcn,$s_dept,$stf_uuid]); } catch(Exception $e){}

        // Default permissions
        foreach (['attendance'=>'view','timetable'=>'view','cbt'=>'view','academics'=>'view',
                  'healthcare'=>'view','disciplinary'=>'none','library'=>'view',
                  'hostel'=>'none','transport'=>'none','finance'=>'none'] as $fk => $lvl) {
            try {
                $pdo->prepare("INSERT INTO staff_feature_permissions (school_uuid,staff_uuid,feature_key,access_level,is_enabled) VALUES (?,?,?,?,1)")
                    ->execute([$school_uuid,$stf_uuid,$fk,$lvl]);
            } catch(Exception $e){}
        }

        AuditLog::write($pdo,$school_uuid,$user_uuid,'staff.create',$stf_uuid,"Enrolled $s_name (temp password issued, delivered via email)");

        // Deliver the temp password via email rather than exposing it in the URL/flash message.
        $mail_body = "<p>Hello {$s_name},</p>"
            . "<p>An account has been created for you on " . htmlspecialchars($school_settings['school_name'] ?? 'the school portal') . ".</p>"
            . "<p><strong>Temporary password:</strong> " . htmlspecialchars($temp_password) . "</p>"
            . "<p>You will be asked to set a new password on first login. This temporary password expires in 72 hours.</p>";
        $mail_result = Mailer::send($school_settings, $s_email, $s_name, 'Your staff account has been created', $mail_body);

        if ($mail_result['success']) {
            $success_msg = "Staff member $s_name enrolled! Their temporary password has been emailed to $s_email.";
        } else {
            // Email failed — do not leak the password into the URL. Direct the admin to a secure lookup instead.
            $success_msg = "Staff member $s_name enrolled, but the welcome email could not be sent ({$mail_result['response']}). "
                . "Ask a Platform/School Admin to resend the credentials from the Staff record.";
        }
        if (!empty($photo_error)) {
            $success_msg .= ' ⚠ ' . $photo_error;
        }
    } catch (PDOException $e) { $error_msg = safe_error('Staff enrollment failed', $e); }
}

// ── EDIT STAFF ────────────────────────────────────────────────────────────────
if (isset($_POST['action_edit_staff'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager'])) { $error_msg = 'Only the school admin can edit staff records.'; return; }
    if ($active_role !== 'School Admin') { $error_msg = 'Only School Admin can edit staff.'; return; }

    $stf_uuid = safe_str($_POST['staff_uuid']    ?? '');
    $s_name   = safe_str($_POST['staff_name']    ?? '');
    $s_email  = safe_str($_POST['staff_email']   ?? '');
    $s_phone  = safe_str($_POST['staff_phone']   ?? '');
    $s_role   = safe_str($_POST['staff_role']    ?? 'Teacher');
    $s_dept   = safe_str($_POST['staff_dept']    ?? 'Academics');
    $s_sal    = max(0, (float)($_POST['staff_salary'] ?? 120000));
    $s_dob    = safe_str($_POST['date_of_birth'] ?? '');
    $s_gen    = safe_str($_POST['gender']        ?? '');
    $s_addr   = safe_str($_POST['address']       ?? '');
    $s_emp    = safe_str($_POST['date_employed'] ?? '');
    $s_qual   = safe_str($_POST['staff_qual']    ?? '');
    $s_trcn   = safe_str($_POST['trcn_number']   ?? '');
    $s_status = safe_str($_POST['staff_status']  ?? 'Active');
    $blood    = safe_str($_POST['blood_group']   ?? 'O+');
    $geno     = safe_str($_POST['genotype']      ?? 'AA');
    $emerg    = safe_str($_POST['emergency_contact'] ?? '');
    $photo_error = null;
    $photo    = handle_image_upload('staff_photo', $upload_dir, 'stf_', safe_str($_POST['existing_photo'] ?? ''), 5_242_880, $photo_error);
    $health_json = json_encode(['blood_group'=>$blood,'geno'=>$geno,'genotype'=>$geno,
        'allergies'=>safe_str($_POST['allergies']??'None'),'emergency_contact'=>$emerg]);

    try {
        $pdo->prepare("UPDATE staff SET name=?,email=?,phone=?,role=?,qualification=?,salary=?,status=?,
            photo_path=?,healthcare_json=?,date_of_birth=?,gender=?,address=?,date_employed=?
            WHERE staff_uuid=? AND school_uuid=?")
            ->execute([$s_name,$s_email,$s_phone,$s_role,$s_qual,$s_sal,$s_status,
                       $photo,$health_json,$s_dob,$s_gen,$s_addr,$s_emp,$stf_uuid,$school_uuid]);
        try { $pdo->prepare("UPDATE staff SET trcn_number=?,department=? WHERE staff_uuid=?")->execute([$s_trcn,$s_dept,$stf_uuid]); } catch(Exception $e){}
        AuditLog::write($pdo,$school_uuid,$user_uuid,'staff.update',$stf_uuid,"Updated $s_name");
        $success_msg = 'Staff record updated!';
        if (!empty($photo_error)) {
            $success_msg .= ' ⚠ ' . $photo_error;
        }
    } catch (PDOException $e) { $error_msg = safe_error('Update failed', $e); }
}

// ── PERMISSIONS ───────────────────────────────────────────────────────────────
if (isset($_POST['action_update_staff_permissions']) && $active_role === 'School Admin') {
    $stf_uuid = safe_str($_POST['staff_uuid'] ?? '');
    $perms    = $_POST['perm_access'] ?? [];
    foreach ($perms as $fk => $al) {
        $fk = preg_replace('/[^a-z_]/', '', $fk);
        $al = in_array($al, ['manage','view','none']) ? $al : 'view';
        try {
            $pdo->prepare("INSERT INTO staff_feature_permissions (school_uuid,staff_uuid,feature_key,access_level,is_enabled)
                VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level),is_enabled=VALUES(is_enabled)")
                ->execute([$school_uuid,$stf_uuid,$fk,$al,($al==='none'?0:1)]);
        } catch(Exception $e){}
    }
    AuditLog::write($pdo,$school_uuid,$user_uuid,'staff.permissions',$stf_uuid,'Permissions updated');
    $success_msg = 'Permissions updated!';
}

// ── STAFF LEAVE (moved from phase4-actions.php) ────────────────────────────────
if (isset($_POST['action_request_leave'])) {
    $staff_uuid = safe_str($_POST['staff_uuid'] ?? '');
    $sn = $pdo->prepare("SELECT name, user_uuid FROM staff WHERE staff_uuid=? AND school_uuid=?");
    $sn->execute([$staff_uuid, $school_uuid]);
    $staff = $sn->fetch();
    $start = safe_str($_POST['start_date'] ?? '');
    $end   = safe_str($_POST['end_date']   ?? '');
    if ($staff_uuid && $staff && $start && $end) {
        $uuid = uid('lv');
        $pdo->prepare("INSERT INTO staff_leave_requests (leave_uuid,school_uuid,staff_uuid,staff_name,leave_type,start_date,end_date,reason,status)
            VALUES (?,?,?,?,?,?,?,?,'Pending')")
            ->execute([$uuid,$school_uuid,$staff_uuid,$staff['name'],safe_str($_POST['leave_type']??'Annual'),$start,$end,safe_str($_POST['reason']??'')]);
        Notify::role($pdo,$school_uuid,'School Admin','Leave request submitted',"{$staff['name']} requested $start to $end",'info','dashboard.php?section=hr&tab=leave');
        AuditLog::write($pdo,$school_uuid,$user_uuid,'hr.leave.request',$uuid,"{$staff['name']} — $start to $end");
        $success_msg = 'Leave request submitted!';
    } else { $error_msg = 'Staff, start, and end dates are required.'; }
}
if (isset($_POST['action_review_leave']) && $active_role === 'School Admin') {
    $leave_uuid = safe_str($_POST['leave_uuid'] ?? '');
    $decision = safe_str($_POST['decision'] ?? 'Approved');
    $pdo->prepare("UPDATE staff_leave_requests SET status=?, reviewed_by=? WHERE leave_uuid=? AND school_uuid=?")
        ->execute([$decision, $_SESSION['name']??'Admin', $leave_uuid, $school_uuid]);
    $lq = $pdo->prepare("SELECT sl.staff_name, s.user_uuid FROM staff_leave_requests sl LEFT JOIN staff s ON s.staff_uuid=sl.staff_uuid WHERE sl.leave_uuid=? AND sl.school_uuid=?");
    $lq->execute([$leave_uuid, $school_uuid]);
    if ($row = $lq->fetch()) {
        if (!empty($row['user_uuid'])) Notify::user($pdo,$school_uuid,$row['user_uuid'],"Leave request $decision","Your leave request was $decision",$decision==='Approved'?'success':'warning','dashboard.php?section=hr&tab=leave');
    }
    $success_msg = "Leave request $decision.";
}

// ── PAYROLL / PAYSLIPS (moved from phase4-actions.php) ─────────────────────────
if (isset($_POST['action_generate_payslip']) && $active_role === 'School Admin') {
    $staff_uuid = safe_str($_POST['staff_uuid'] ?? '');
    $period     = safe_str($_POST['pay_period'] ?? '');
    $basic      = max(0, (float)($_POST['basic_salary'] ?? 0));
    $allow      = max(0, (float)($_POST['allowances']   ?? 0));
    $deduct     = max(0, (float)($_POST['deductions']   ?? 0));
    $sn = $pdo->prepare("SELECT name, user_uuid FROM staff WHERE staff_uuid=? AND school_uuid=?");
    $sn->execute([$staff_uuid, $school_uuid]);
    $staff = $sn->fetch();

    if (!$staff_uuid || !$staff || !$period || $basic <= 0) {
        $error_msg = 'Staff, pay period, and basic salary are required.';
    } else {
        $net = $basic + $allow - $deduct;
        try {
            $uuid = uid('pay');
            $pdo->prepare("INSERT INTO staff_payslips (payslip_uuid,school_uuid,staff_uuid,staff_name,pay_period,basic_salary,allowances,deductions,net_pay,generated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$uuid,$school_uuid,$staff_uuid,$staff['name'],$period,$basic,$allow,$deduct,$net,$_SESSION['name']??'Admin']);
            if (!empty($staff['user_uuid'])) Notify::user($pdo,$school_uuid,$staff['user_uuid'],"Payslip ready — $period","Net pay: ₦".number_format($net,2),'success','dashboard.php?section=hr&tab=payroll');
            AuditLog::write($pdo,$school_uuid,$user_uuid,'hr.payslip.generate',$uuid,"{$staff['name']} — $period — ₦$net");
            $success_msg = "Payslip generated for {$staff['name']} — ₦" . number_format($net,2);
        } catch (PDOException $e) {
            $error_msg = str_contains($e->getMessage(), 'uniq_payslip')
                ? 'A payslip for this staff member and period already exists.'
                : safe_error('Failed to generate payslip', $e);
        }
    }
}

// ── STAFF APPRAISALS (moved from phase4-actions.php) ────────────────────────────
if (isset($_POST['action_add_appraisal']) && $active_role === 'School Admin') {
    $staff_uuid = safe_str($_POST['staff_uuid']   ?? '');
    $period     = safe_str($_POST['period_label'] ?? '');
    $sn = $pdo->prepare("SELECT name FROM staff WHERE staff_uuid=? AND school_uuid=?");
    $sn->execute([$staff_uuid, $school_uuid]);
    $staff_name = $sn->fetchColumn();

    if ($staff_uuid && $staff_name && $period) {
        $uuid = uid('apr');
        $clamp = fn($v) => max(1, min(5, safe_int($v, 3)));
        $pdo->prepare("INSERT INTO staff_appraisals (appraisal_uuid,school_uuid,staff_uuid,staff_name,period_label,punctuality_rating,subject_mastery_rating,classroom_management_rating,teamwork_rating,overall_comment,appraised_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$staff_uuid,$staff_name,$period,
                $clamp($_POST['punctuality_rating']??3), $clamp($_POST['subject_mastery_rating']??3),
                $clamp($_POST['classroom_management_rating']??3), $clamp($_POST['teamwork_rating']??3),
                safe_str($_POST['overall_comment']??''), $_SESSION['name']??'Admin']);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'hr.appraisal.create',$uuid,"$staff_name — $period");
        $success_msg = "Appraisal saved for $staff_name!";
    } else { $error_msg = 'Staff member and period are required.'; }
}

// ── HR: EDITABLE LETTER OF EMPLOYMENT TEMPLATES + ISSUING (moved from phase4-actions.php) ──
if (isset($_POST['action_save_letter_template']) && $active_role === 'School Admin') {
    $tpl_uuid = safe_str($_POST['template_uuid'] ?? '');
    $title    = safe_str($_POST['title'] ?? 'Letter of Employment');
    $body     = trim((string)($_POST['body_html'] ?? ''));
    $is_def   = isset($_POST['is_default']) ? 1 : 0;

    if ($body === '') {
        $error_msg = 'Letter body cannot be empty.';
    } else {
        try {
            if ($is_def) {
                $pdo->prepare("UPDATE hr_employment_letter_templates SET is_default=0 WHERE school_uuid=?")->execute([$school_uuid]);
            }
            if ($tpl_uuid) {
                $pdo->prepare("UPDATE hr_employment_letter_templates SET title=?, body_html=?, is_default=?, updated_by=? WHERE template_uuid=? AND school_uuid=?")
                    ->execute([$title, $body, $is_def, $_SESSION['name'] ?? 'Admin', $tpl_uuid, $school_uuid]);
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'hr.letter_template.update', $tpl_uuid, $title);
            } else {
                $tpl_uuid = uid('let');
                $pdo->prepare("INSERT INTO hr_employment_letter_templates (template_uuid,school_uuid,title,body_html,is_default,updated_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$tpl_uuid, $school_uuid, $title, $body, $is_def, $_SESSION['name'] ?? 'Admin']);
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'hr.letter_template.create', $tpl_uuid, $title);
            }
            $success_msg = "Letter template \"$title\" saved!";
        } catch (Exception $e) {
            $error_msg = safe_error('Failed to save letter template', $e);
        }
    }
}

if (isset($_POST['action_issue_employment_letter']) && $active_role === 'School Admin') {
    $staff_uuid = safe_str($_POST['staff_uuid'] ?? '');
    $tpl_uuid   = safe_str($_POST['template_uuid'] ?? '');

    $ss = $pdo->prepare("SELECT * FROM staff WHERE staff_uuid=? AND school_uuid=?");
    $ss->execute([$staff_uuid, $school_uuid]);
    $staff = $ss->fetch();

    $ts = $pdo->prepare("SELECT * FROM hr_employment_letter_templates WHERE template_uuid=? AND school_uuid=?");
    $ts->execute([$tpl_uuid, $school_uuid]);
    $tpl = $ts->fetch();

    if (!$staff || !$tpl) {
        $error_msg = 'Select a valid staff member and template.';
    } else {
        $tokens = [
            '{{staff_name}}'    => $staff['name'] ?? '',
            '{{role}}'          => $staff['role'] ?? '',
            '{{department}}'    => $staff['department'] ?? '',
            '{{date_employed}}' => !empty($staff['date_employed']) ? date('F j, Y', strtotime($staff['date_employed'])) : '—',
            '{{salary}}'        => isset($staff['salary']) ? '₦' . number_format((float)$staff['salary'], 2) : '—',
            '{{school_name}}'   => $school['name'] ?? 'the school',
            '{{today}}'         => date('F j, Y'),
        ];
        $rendered = nl2br(htmlspecialchars(strtr($tpl['body_html'], $tokens)));
        $letter_uuid = uid('elt');
        $pdo->prepare("INSERT INTO hr_employment_letters_issued (letter_uuid,school_uuid,staff_uuid,template_uuid,rendered_html,issued_by) VALUES (?,?,?,?,?,?)")
            ->execute([$letter_uuid, $school_uuid, $staff_uuid, $tpl_uuid, $rendered, $_SESSION['name'] ?? 'Admin']);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'hr.letter.issue', $letter_uuid, "Issued to {$staff['name']}");
        $success_msg = "Letter of Employment issued to {$staff['name']}!";
    }
}
