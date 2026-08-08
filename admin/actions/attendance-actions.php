<?php
/**
 * Actions: Attendance Log (admin/sections/attendance.php)
 * Split out of the old misc-actions.php / academic-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// ── ONE-CLICK: DAILY ABSENTEEISM ALERT ────────────────────────────────────────
if (isset($_POST['action_send_absenteeism_alerts']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    $today = date('Y-m-d');
    $rows = $pdo->prepare("SELECT s.name AS student_name, p.phone FROM attendance_records a
        JOIN students s ON s.student_uuid = a.student_uuid
        LEFT JOIN parents p ON p.parent_uuid = s.parent_uuid
        WHERE a.school_uuid=? AND a.date=? AND a.status='Absent' AND p.phone IS NOT NULL AND p.phone != ''");
    $rows->execute([$school_uuid,$today]);
    $absentees = $rows->fetchAll();
    $sent = 0; $last_response = '';
    foreach ($absentees as $a) {
        $msg = "Dear Parent, your ward {$a['student_name']} was marked ABSENT today ($today). Please contact the school office if this is in error.";
        $r = SMSGateway::send($school_settings, $a['phone'], $msg);
        $last_response = $r['response'];
        if ($r['success']) $sent++;
    }
    $rcount = count($absentees);
    $uuid = uid('bc');
    $pdo->prepare("INSERT INTO broadcast_messages (broadcast_uuid,school_uuid,channel,recipient_group,message_text,recipient_count,status,sent_by,gateway_response) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$uuid,$school_uuid,'SMS','Absentee Parents (Today)',"Daily absenteeism alert for $today",$rcount,$rcount===0?'No Recipients':($sent>0?'Sent':'Failed'),$_SESSION['name']??'Admin',$last_response]);
    if ($rcount === 0) { $success_msg = 'No absent students recorded today — nothing to send.'; }
    elseif ($sent === 0) { $error_msg = "Alert not delivered — $last_response"; }
    else { $success_msg = "Absenteeism alert sent to $sent of $rcount parent(s)."; }
}

// ── MARK / OVERRIDE ATTENDANCE (moved from academic-actions.php) ─────────────
if (isset($_POST['action_mark_attendance'])) {
    $can_mark = in_array($active_role, ['School Admin', 'Platform Manager']) || can_manage($active_role, feature_access('attendance'));
    $att_date = safe_str($_POST['attendance_date'] ?? '');
    $rule = $att_date !== '' ? attendanceMarkable($pdo, $school_uuid, $att_date) : ['allowed' => false, 'reason' => 'No date supplied.'];

    if (!$can_mark) {
        $error_msg = 'You do not have permission to mark attendance.';
    } elseif (!$rule['allowed']) {
        $error_msg = 'Attendance cannot be marked for this date: ' . $rule['reason'];
    } else {
        $student_uuids = $_POST['student_uuid'] ?? [];
        $statuses      = $_POST['status'] ?? [];
        try {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_records (school_uuid, student_uuid, date, status, auto_marked, marked_by)
                VALUES (?, ?, ?, ?, 0, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), auto_marked = 0, marked_by = VALUES(marked_by)
            ");
            $n = 0;
            foreach ($student_uuids as $i => $sid) {
                $sid = safe_str($sid);
                $status = in_array($statuses[$i] ?? '', ['Present','Absent','Late','Excused']) ? $statuses[$i] : 'Present';
                if ($sid === '') continue;
                $stmt->execute([$school_uuid, $sid, $att_date, $status, $user_uuid]);
                $n++;
            }
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'attendance.mark', $att_date, "Attendance marked for $n students on $att_date");
            $success_msg = "Attendance saved for $n students.";
        } catch (Exception $e) { $error_msg = safe_error('Error', $e); }
    }
}
