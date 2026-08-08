<?php
/**
 * Actions: Consultations — Parent-Teacher appointments + messages
 * (admin/sections/consultations.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// NOTE: staff/admin log these on behalf of a parent (selected from the Parent
// Records list) since the parent portal doesn't yet have its own scheduling UI.
if (isset($_POST['action_request_appointment'])) {
    $parent_uuid  = safe_str($_POST['parent_uuid']  ?? '');
    $teacher_uuid = safe_str($_POST['teacher_uuid'] ?? '');
    $student_name = safe_str($_POST['student_name'] ?? '');
    $mdate = safe_str($_POST['meeting_date'] ?? '');
    $mtime = safe_str($_POST['meeting_time'] ?? '');
    $purpose = safe_str($_POST['purpose'] ?? '');

    $pn = $pdo->prepare("SELECT name FROM parents WHERE parent_uuid=? AND school_uuid=?");
    $pn->execute([$parent_uuid,$school_uuid]);
    $parent_name = $pn->fetchColumn();

    $tn = $pdo->prepare("SELECT name FROM staff WHERE staff_uuid=? AND school_uuid=?");
    $tn->execute([$teacher_uuid,$school_uuid]);
    $teacher_name = $tn->fetchColumn();

    if ($parent_uuid && $parent_name && $teacher_uuid && $teacher_name && $mdate && $purpose) {
        $uuid = uid('apt');
        $pdo->prepare("INSERT INTO parent_teacher_appointments (appointment_uuid,school_uuid,parent_uuid,parent_name,teacher_uuid,teacher_name,student_name,meeting_date,meeting_time,purpose,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,'Pending')")
            ->execute([$uuid,$school_uuid,$parent_uuid,$parent_name,$teacher_uuid,$teacher_name,$student_name,$mdate,$mtime,$purpose]);
        $tuser = $pdo->prepare("SELECT user_uuid FROM staff WHERE staff_uuid=? AND school_uuid=?");
        $tuser->execute([$teacher_uuid,$school_uuid]);
        if ($tu = $tuser->fetchColumn()) {
            Notify::user($pdo,$school_uuid,$tu,'New appointment logged',"Meeting with $parent_name on $mdate re: $student_name",'info','dashboard.php?section=consultations');
        }
        AuditLog::write($pdo,$school_uuid,$user_uuid,'appointment.create',$uuid,"$parent_name with $teacher_name on $mdate");
        $success_msg = 'Appointment logged!';
    } else { $error_msg = 'Parent, teacher, date and purpose are required.'; }
}
if (isset($_POST['action_respond_appointment'])) {
    $apt_uuid = safe_str($_POST['appointment_uuid'] ?? '');
    $decision = safe_str($_POST['decision'] ?? 'Confirmed');
    $pdo->prepare("UPDATE parent_teacher_appointments SET status=? WHERE appointment_uuid=? AND school_uuid=?")
        ->execute([$decision,$apt_uuid,$school_uuid]);
    $ap = $pdo->prepare("SELECT parent_uuid, teacher_name, meeting_date FROM parent_teacher_appointments WHERE appointment_uuid=? AND school_uuid=?");
    $ap->execute([$apt_uuid,$school_uuid]);
    if ($row = $ap->fetch()) {
        Notify::user($pdo,$school_uuid,$row['parent_uuid'],"Appointment $decision", "{$row['teacher_name']} $decision your meeting on {$row['meeting_date']}", $decision==='Confirmed'?'success':'warning','dashboard.php?section=consultations');
    }
    $success_msg = "Appointment $decision.";
}
if (isset($_POST['action_send_pt_message'])) {
    $receiver_uuid = safe_str($_POST['receiver_uuid'] ?? '');
    $receiver_name = safe_str($_POST['receiver_name'] ?? '');
    $text = safe_str($_POST['message_text'] ?? '');
    if ($receiver_uuid && $text) {
        $uuid = uid('ptm');
        $pdo->prepare("INSERT INTO parent_teacher_messages (message_uuid,school_uuid,sender_uuid,sender_name,sender_role,receiver_uuid,receiver_name,message_text)
            VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$user_uuid,$_SESSION['name']??'User',$active_role,$receiver_uuid,$receiver_name,$text]);
        Notify::user($pdo,$school_uuid,$receiver_uuid,"New message from " . ($_SESSION['name']??'User'), mb_substr($text,0,140), 'info', 'dashboard.php?section=consultations');
        $success_msg = 'Message sent!';
    } else { $error_msg = 'Select a recipient and enter a message.'; }
}
