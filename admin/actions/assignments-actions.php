<?php
/**
 * Assignment Actions — Phase 5
 *
 * Access model:
 *   - Any staff with write/full on Assignments (or School Admin/Platform
 *     Manager) can create an assignment and grade submissions.
 *   - Only staff with FULL access (or School Admin/Platform Manager) can
 *     approve/reject an assignment — this is what makes it visible to
 *     parents/students. A confirmed parent-teacher meeting can also serve
 *     as the approval event: the approver picks the meeting instead of
 *     typing a note, and it's recorded as the justification.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$can_write_assignments = in_array($active_role, ['School Admin', 'Platform Manager']) || can_manage($active_role, feature_access('assignments'));
$can_approve_assignments = in_array($active_role, ['School Admin', 'Platform Manager']) || can_approve($active_role, feature_access('assignments'));

// ── CREATE ───────────────────────────────────────────────────────────────────
if (isset($_POST['action_create_assignment'])) {
    if (!$can_write_assignments) { $error_msg = 'You do not have permission to create assignments.'; return; }

    $title    = safe_str($_POST['title']       ?? '');
    $subject  = safe_str($_POST['subject']     ?? '');
    $class    = safe_str($_POST['class_name']  ?? '');
    $desc     = safe_str($_POST['description'] ?? '');
    $due      = safe_str($_POST['due_date']    ?? '');
    $max      = safe_int($_POST['max_score']   ?? 100);
    $attach   = safe_str($_POST['attachment_url'] ?? '');
    $staff_name = $_SESSION['name'] ?? $active_role;

    if (!$title || !$subject || !$class || !$desc || !$due) {
        $error_msg = 'Title, subject, class, description, and due date are all required.';
    } else {
        $uuid = uid('asg');
        $staff_uuid_lookup = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
        $staff_uuid_lookup->execute([$user_uuid, $school_uuid]);
        $staff_uuid = $staff_uuid_lookup->fetchColumn() ?: null;

        $pdo->prepare("
            INSERT INTO assignments (assignment_uuid, school_uuid, title, subject, class_name, teacher_name, assigned_by_staff_uuid, assigned_by_staff_name, session_name, term_name, description, due_date, max_score, attachment_url, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ")->execute([
            $uuid, $school_uuid, $title, $subject, $class, $staff_name, $staff_uuid, $staff_name,
            $school_settings['current_session'] ?? null, $school_settings['current_term'] ?? null,
            $desc, $due, $max, $attach ?: null,
        ]);

        AuditLog::write($pdo, $school_uuid, $user_uuid, 'assignment.create', $uuid, "Created \"$title\" for $class — awaiting approval");
        Notify::role($pdo, $school_uuid, 'School Admin', 'Assignment awaiting approval', "\"$title\" ($class) was submitted by $staff_name", 'info', 'dashboard.php?section=assignments');
        $success_msg = "Assignment created and sent for approval. It will not be visible to parents/students until approved.";
    }
}

// ── APPROVE (direct, or via a confirmed parent meeting) ──────────────────────
if (isset($_POST['action_approve_assignment'])) {
    if (!$can_approve_assignments) { 
        $error_msg = 'Only full-access staff or the school admin can approve assignments.'; 
        return; 
    }

    $assignment_uuid = safe_str($_POST['assignment_uuid'] ?? '');
    if (empty($assignment_uuid)) {
        $error_msg = 'Assignment UUID is required.';
        return;
    }

    $appointment_uuid = safe_str($_POST['approval_appointment_uuid'] ?? '');
    $approver_name = $_SESSION['name'] ?? $active_role;

    $note = 'Direct approval';
    $linked_appointment = null;

    if ($appointment_uuid !== '') {
        // A confirmed parent-teacher meeting stands in for a direct approval.
        $ap = $pdo->prepare("SELECT * FROM parent_teacher_appointments WHERE appointment_uuid=? AND school_uuid=? AND status='Confirmed' LIMIT 1");
        $ap->execute([$appointment_uuid, $school_uuid]);
        $appt = $ap->fetch();
        if (!$appt) {
            $error_msg = 'That meeting is not a confirmed appointment — pick another, or approve directly.';
            return;
        }
        $note = "Approved via parent meeting with {$appt['parent_name']} on {$appt['meeting_date']} (re: {$appt['student_name']})";
        $linked_appointment = $appointment_uuid;
    }

    $update = $pdo->prepare("
        UPDATE assignments SET 
            approval_status='Approved', 
            approved_by=?, 
            approved_at=NOW(), 
            approval_note=?, 
            linked_appointment_uuid=?, 
            rejection_reason=NULL
        WHERE assignment_uuid=? AND school_uuid=?
    ");
    $update->execute([$approver_name, $note, $linked_appointment, $assignment_uuid, $school_uuid]);

    if ($update->rowCount() > 0) {
        $aq = $pdo->prepare("SELECT title, assigned_by_staff_uuid FROM assignments WHERE assignment_uuid=? AND school_uuid=?");
        $aq->execute([$assignment_uuid, $school_uuid]);
        if ($a = $aq->fetch()) {
            if ($a['assigned_by_staff_uuid']) {
                $su = $pdo->prepare("SELECT user_uuid FROM staff WHERE staff_uuid=? AND school_uuid=?");
                $su->execute([$a['assigned_by_staff_uuid'], $school_uuid]);
                if ($tu = $su->fetchColumn()) {
                    Notify::user($pdo, $school_uuid, $tu, 'Assignment approved', "\"{$a['title']}\" is now visible to parents/students. $note", 'success', 'dashboard.php?section=assignments');
                }
            }
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'assignment.approve', $assignment_uuid, "Approved \"{$a['title']}\" — $note");
        }
        $success_msg = 'Assignment approved and now visible to parents/students.';
    } else {
        $error_msg = 'Failed to approve assignment. Please check if the assignment exists.';
    }
}

// ── REJECT ────────────────────────────────────────────────────────────────────
if (isset($_POST['action_reject_assignment'])) {
    if (!$can_approve_assignments) { 
        $error_msg = 'Only full-access staff or the school admin can reject assignments.'; 
        return; 
    }

    $assignment_uuid = safe_str($_POST['assignment_uuid'] ?? '');
    $reason = safe_str($_POST['rejection_reason'] ?? '');
    $approver_name = $_SESSION['name'] ?? $active_role;

    if (empty($assignment_uuid)) {
        $error_msg = 'Assignment UUID is required.';
        return;
    }

    // First check if the assignment exists and is pending
    $check = $pdo->prepare("SELECT title, assigned_by_staff_uuid, approval_status FROM assignments WHERE assignment_uuid=? AND school_uuid=?");
    $check->execute([$assignment_uuid, $school_uuid]);
    $assignment = $check->fetch();

    if (!$assignment) {
        $error_msg = 'Assignment not found.';
        return;
    }

    if ($assignment['approval_status'] !== 'Pending') {
        $error_msg = 'This assignment has already been ' . strtolower($assignment['approval_status']) . '.';
        return;
    }

    $update = $pdo->prepare("
        UPDATE assignments SET 
            approval_status='Rejected', 
            approved_by=?, 
            approved_at=NOW(), 
            rejection_reason=?,
            approval_note=NULL,
            linked_appointment_uuid=NULL
        WHERE assignment_uuid=? AND school_uuid=?
    ");
    $update->execute([$approver_name, $reason, $assignment_uuid, $school_uuid]);

    if ($update->rowCount() > 0) {
        if ($assignment['assigned_by_staff_uuid']) {
            $su = $pdo->prepare("SELECT user_uuid FROM staff WHERE staff_uuid=? AND school_uuid=?");
            $su->execute([$assignment['assigned_by_staff_uuid'], $school_uuid]);
            if ($tu = $su->fetchColumn()) {
                Notify::user($pdo, $school_uuid, $tu, 'Assignment rejected', 
                    "\"{$assignment['title']}\" was rejected." . ($reason ? " Reason: $reason" : ''), 
                    'warning', 
                    'dashboard.php?section=assignments'
                );
            }
        }
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'assignment.reject', $assignment_uuid, 
            "Rejected \"{$assignment['title']}\"" . ($reason ? " — $reason" : '')
        );
        $success_msg = 'Assignment rejected successfully.';
    } else {
        $error_msg = 'Failed to reject assignment. Please try again.';
    }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if (isset($_POST['action_delete_assignment'])) {
    if (!in_array($active_role, ['School Admin', 'Platform Manager']) && !can_approve($active_role, feature_access('assignments'))) {
        $error_msg = 'You do not have permission to delete assignments.';
        return;
    }
    $assignment_uuid = safe_str($_POST['assignment_uuid'] ?? '');
    
    if (empty($assignment_uuid)) {
        $error_msg = 'Assignment UUID is required.';
        return;
    }

    try {
        $pdo->beginTransaction();
        
        // First delete submissions
        $pdo->prepare("DELETE FROM assignment_submissions WHERE assignment_uuid=? AND school_uuid=?")->execute([$assignment_uuid, $school_uuid]);
        
        // Then delete the assignment
        $delete = $pdo->prepare("DELETE FROM assignments WHERE assignment_uuid=? AND school_uuid=?");
        $delete->execute([$assignment_uuid, $school_uuid]);
        
        if ($delete->rowCount() > 0) {
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'assignment.delete', $assignment_uuid, 'Assignment deleted');
            $success_msg = 'Assignment deleted successfully.';
        } else {
            $error_msg = 'Assignment not found.';
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Failed to delete assignment: ' . $e->getMessage();
    }
}

// ── GRADE A SUBMISSION ────────────────────────────────────────────────────────
if (isset($_POST['action_grade_submission'])) {
    if (!$can_write_assignments) { 
        $error_msg = 'You do not have permission to grade submissions.'; 
        return; 
    }

    $submission_uuid = safe_str($_POST['submission_uuid'] ?? '');
    $score = (float)($_POST['grade_score'] ?? 0);
    $feedback = safe_str($_POST['teacher_feedback'] ?? '');

    if (empty($submission_uuid)) {
        $error_msg = 'Submission UUID is required.';
        return;
    }

    $sq = $pdo->prepare("SELECT student_uuid, assignment_uuid FROM assignment_submissions WHERE submission_uuid=? AND school_uuid=?");
    $sq->execute([$submission_uuid, $school_uuid]);
    $sub = $sq->fetch();

    if (!$sub) {
        $error_msg = 'Submission not found.';
        return;
    }

    $update = $pdo->prepare("
        UPDATE assignment_submissions SET 
            grade_score=?, 
            teacher_feedback=?, 
            status='Graded'
        WHERE submission_uuid=? AND school_uuid=?
    ");
    $update->execute([$score, $feedback, $submission_uuid, $school_uuid]);

    if ($update->rowCount() > 0) {
        if ($sub) {
            $stu = $pdo->prepare("SELECT parent_uuid FROM students WHERE student_uuid=? AND school_uuid=?");
            $stu->execute([$sub['student_uuid'], $school_uuid]);
            if ($parent_uuid = $stu->fetchColumn()) {
                $pu = $pdo->prepare("
                    SELECT u.user_uuid FROM parents p
                    JOIN users u ON u.email = p.email AND u.school_uuid = p.school_uuid
                    WHERE p.parent_uuid=? AND p.school_uuid=?
                ");
                $pu->execute([$parent_uuid, $school_uuid]);
                if ($pun = $pu->fetchColumn()) {
                    Notify::user($pdo, $school_uuid, $pun, 'Assignment graded', "Your child's assignment was graded: $score", 'success', 'parent-portal.php?tab=assignments');
                }
            }
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'assignment.grade', $submission_uuid, "Graded submission — $score");
        }
        $success_msg = 'Submission graded successfully.';
    } else {
        $error_msg = 'Failed to grade submission.';
    }
}