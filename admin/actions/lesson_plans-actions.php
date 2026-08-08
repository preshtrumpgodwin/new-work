<?php
/**
 * Actions: Lesson Plans & Schemes (admin/sections/lesson_plans.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_lesson_plan'])) {
    $subj  = safe_str($_POST['subject_name']          ?? '');
    $class = safe_str($_POST['class_name']             ?? '');
    $topic = safe_str($_POST['topic']                  ?? '');
    $week  = safe_int($_POST['week_number']            ?? 1);
    $obj   = safe_str($_POST['behavioral_objectives']  ?? '');
    $notes = safe_str($_POST['lesson_notes']            ?? '');
    $exer  = safe_str($_POST['exercises']               ?? '');
    $hw    = safe_str($_POST['homework']                ?? '');
    $tname = $_SESSION['name'] ?? 'Teacher';
    if ($subj && $class && $topic && $obj && $notes) {
        $uuid = uid('lp');
        $pdo->prepare("INSERT INTO lesson_plans (plan_uuid,school_uuid,teacher_uuid,teacher_name,subject_name,class_name,topic,week_number,behavioral_objectives,lesson_notes,exercises,homework,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Pending Review')")
            ->execute([$uuid,$school_uuid,$user_uuid,$tname,$subj,$class,$topic,$week,$obj,$notes,$exer,$hw]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'lesson_plan.create',$uuid,"Submitted plan: $topic ($subj, $class)");
        Notify::role($pdo,$school_uuid,'School Admin',"Lesson plan submitted",("$tname submitted \"$topic\" for $subj / $class"),'info','dashboard.php?section=lesson_plans');
        $success_msg = "Lesson plan '$topic' submitted for review!";
    } else { $error_msg = 'Subject, class, topic, objectives and notes are required.'; }
}
if (isset($_POST['action_review_lesson_plan']) && in_array($active_role, ['School Admin','Platform Manager'], true)) {
    $plan_uuid = safe_str($_POST['plan_uuid']    ?? '');
    $decision  = safe_str($_POST['decision']     ?? 'Approved'); // Approved | Rejected
    $feedback  = safe_str($_POST['reviewer_feedback'] ?? '');
    $pdo->prepare("UPDATE lesson_plans SET status=?, reviewer_feedback=?, reviewed_by=?, reviewed_at=NOW() WHERE plan_uuid=? AND school_uuid=?")
        ->execute([$decision,$feedback,$_SESSION['name']??'Admin',$plan_uuid,$school_uuid]);
    $tp = $pdo->prepare("SELECT teacher_uuid, topic FROM lesson_plans WHERE plan_uuid=? AND school_uuid=?");
    $tp->execute([$plan_uuid,$school_uuid]);
    if ($row = $tp->fetch()) {
        if (!empty($row['teacher_uuid'])) {
            Notify::user($pdo,$school_uuid,$row['teacher_uuid'],"Lesson plan $decision", "\"{$row['topic']}\" was $decision" . ($feedback ? ": $feedback" : ''), $decision === 'Approved' ? 'success' : 'warning', 'dashboard.php?section=lesson_plans');
        }
    }
    $success_msg = "Lesson plan $decision.";
}
if (isset($_POST['action_delete_lesson_plan'])) {
    $pdo->prepare("DELETE FROM lesson_plans WHERE plan_uuid=? AND school_uuid=?")->execute([safe_str($_POST['plan_uuid']??''),$school_uuid]);
    $success_msg = 'Lesson plan deleted.';
}
