<?php
/**
 * Actions: Sessions & Classes (admin/sections/primary_ops.php)
 * Split out of the old academic-actions.php grouping. The promote_class
 * handler here is a merged version — see the note above that block.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// ── SESSIONS ─────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_session']) && $active_role === 'School Admin') {
    $name = safe_str($_POST['session_name'] ?? '');
    if ($name) {
        $pdo->prepare("INSERT INTO academic_sessions (school_uuid,session_name,is_current) VALUES (?,?,0)")->execute([$school_uuid,$name]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'session.create','', "Added session $name");
        $success_msg = "Session $name added!";
    }
}
if (isset($_POST['action_set_current_session'])) {
    $name = safe_str($_POST['session_name'] ?? '');
    $pdo->prepare("UPDATE academic_sessions SET is_current=0 WHERE school_uuid=?")->execute([$school_uuid]);
    $pdo->prepare("UPDATE academic_sessions SET is_current=1 WHERE school_uuid=? AND session_name=?")->execute([$school_uuid,$name]);
    $pdo->prepare("UPDATE school_settings SET current_session=? WHERE school_uuid=?")->execute([$name,$school_uuid]);
    $success_msg = "Current session: $name";
}
if (isset($_POST['action_delete_session']) && $active_role === 'School Admin') {
    $pdo->prepare("DELETE FROM academic_sessions WHERE id=? AND school_uuid=?")->execute([safe_int($_POST['session_id']??0),$school_uuid]);
    $success_msg = 'Session removed.';
}

// ── TERMS ─────────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_term']) && $active_role === 'School Admin') {
    $name = safe_str($_POST['term_name'] ?? '');
    if ($name) {
        $pdo->prepare("INSERT INTO academic_terms (school_uuid,term_name,is_current) VALUES (?,?,0)")->execute([$school_uuid,$name]);
        $success_msg = "Term $name added!";
    }
}
if (isset($_POST['action_set_current_term'])) {
    $name = safe_str($_POST['term_name'] ?? '');
    $pdo->prepare("UPDATE academic_terms SET is_current=0 WHERE school_uuid=?")->execute([$school_uuid]);
    $pdo->prepare("UPDATE academic_terms SET is_current=1 WHERE school_uuid=? AND term_name=?")->execute([$school_uuid,$name]);
    $pdo->prepare("UPDATE school_settings SET current_term=? WHERE school_uuid=?")->execute([$name,$school_uuid]);
    $success_msg = "Current term: $name";
}
if (isset($_POST['action_delete_term']) && $active_role === 'School Admin') {
    $pdo->prepare("DELETE FROM academic_terms WHERE id=? AND school_uuid=?")->execute([safe_int($_POST['term_id']??0),$school_uuid]);
    $success_msg = 'Term removed.';
}

// ── TERM OPEN/CLOSE ─────────────────────────────────────────────────────────
if (isset($_POST['action_open_term']) && $active_role === 'School Admin') {
    $term_id = safe_int($_POST['term_id'] ?? 0);
    $pdo->prepare("UPDATE academic_terms SET is_open=1 WHERE id=? AND school_uuid=?")->execute([$term_id, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'term.open', (string)$term_id, 'Term opened for attendance/operations');
    $success_msg = 'Term opened.';
}
if (isset($_POST['action_close_term']) && $active_role === 'School Admin') {
    $term_id = safe_int($_POST['term_id'] ?? 0);
    $pdo->prepare("UPDATE academic_terms SET is_open=0 WHERE id=? AND school_uuid=?")->execute([$term_id, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'term.close', (string)$term_id, 'Term closed');
    $success_msg = 'Term closed. Attendance will not be marked while it is closed.';
}

// ── CLASSES ───────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_class']) && $active_role === 'School Admin') {
    $cn = safe_str($_POST['class_name'] ?? '');
    if ($cn) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM academic_classes WHERE school_uuid=? AND class_name=?");
        $chk->execute([$school_uuid,$cn]);
        if ($chk->fetchColumn() > 0) { $error_msg = "Class $cn already exists."; }
        else {
            $pdo->prepare("INSERT INTO academic_classes (school_uuid,class_name) VALUES (?,?)")->execute([$school_uuid,$cn]);
            // Guide the admin to add an arm right away — a class with no arm
            // is an orphan class (nothing can be scoped/attended/graded under it).
            $success_msg = "Class $cn added! Now add at least one arm for it below (e.g. \"$cn A\") so students can be assigned to it.";
        }
    }
}
if (isset($_POST['action_delete_class']) && $active_role === 'School Admin') {
    $pdo->prepare("DELETE FROM academic_classes WHERE id=? AND school_uuid=?")->execute([safe_int($_POST['class_id']??0),$school_uuid]);
    $success_msg = 'Class removed.';
}

// ── ARMS ──────────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_arm']) && $active_role === 'School Admin') {
    $an = safe_str($_POST['arm_name'] ?? '');
    $ac = safe_str($_POST['class_name'] ?? '');
    if (!$ac) { $error_msg = "Choose a class before adding an arm."; }
    elseif ($an) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM academic_arms WHERE school_uuid=? AND class_name=? AND arm_name=?");
        $chk->execute([$school_uuid,$ac,$an]);
        if ($chk->fetchColumn() > 0) { $error_msg = "Arm $an already exists for $ac."; }
        else {
            $pdo->prepare("INSERT INTO academic_arms (school_uuid,class_name,arm_name) VALUES (?,?,?)")->execute([$school_uuid,$ac,$an]);
            $success_msg = "Arm $ac $an added!";
        }
    }
}
if (isset($_POST['action_delete_arm']) && $active_role === 'School Admin') {
    $pdo->prepare("DELETE FROM academic_arms WHERE id=? AND school_uuid=?")->execute([safe_int($_POST['arm_id']??0),$school_uuid]);
    $success_msg = 'Arm removed.';
}

// ── SCHOOL CALENDAR (public holidays / non-school days) ─────────────────────
if (isset($_POST['action_add_calendar_day']) && $active_role === 'School Admin') {
    $date  = safe_str($_POST['calendar_date'] ?? '');
    $type  = safe_str($_POST['calendar_type'] ?? 'holiday');
    $title = safe_str($_POST['calendar_title'] ?? '');
    if ($date === '') {
        $error_msg = 'A date is required.';
    } else {
        $is_holiday = $type === 'holiday' ? 1 : 0;
        $is_school_day = $type === 'school_day' ? 1 : ($type === 'not_school_day' ? 0 : 0);
        try {
            $pdo->prepare("
                INSERT INTO school_calendar_days (school_uuid, calendar_date, is_school_day, is_public_holiday, title)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE is_school_day=VALUES(is_school_day), is_public_holiday=VALUES(is_public_holiday), title=VALUES(title)
            ")->execute([$school_uuid, $date, $is_school_day, $is_holiday, $title ?: null]);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'calendar.set', $date, "Calendar day set: $type" . ($title ? " ($title)" : ''));
            $success_msg = 'Calendar updated.';
        } catch (Exception $e) { $error_msg = safe_error('Error', $e); }
    }
}
if (isset($_POST['action_delete_calendar_day']) && $active_role === 'School Admin') {
    $cid = safe_int($_POST['calendar_id'] ?? 0);
    $pdo->prepare("DELETE FROM school_calendar_days WHERE id=? AND school_uuid=?")->execute([$cid, $school_uuid]);
    $success_msg = 'Calendar entry removed.';
}

// ── TEACHER ASSIGNMENTS (Class Teacher) ──────────────────────────────────────
if (isset($_POST['action_assign_class_teacher']) && $active_role === 'School Admin') {
    $staff_uuid   = safe_str($_POST['staff_uuid']   ?? '');
    $class_name   = safe_str($_POST['class_name']   ?? '');
    $arm_name     = safe_str($_POST['arm_name']     ?? '');
    $session_name = safe_str($_POST['session_name'] ?? ($school_settings['current_session'] ?? ''));
    $term_name    = safe_str($_POST['term_name']    ?? ($school_settings['current_term']    ?? ''));

    if (!$staff_uuid || !$class_name || !$session_name || !$term_name) {
        $error_msg = 'Staff, class, session, and term are all required.';
    } else {
        try {
            // Check for duplicate using unique key combination
            $check = $pdo->prepare("
                SELECT id FROM class_teacher_assignments 
                WHERE school_uuid = ? AND staff_uuid = ? AND class_name = ? 
                  AND arm_name = ? AND session_name = ? AND term_name = ?
            ");
            $check->execute([$school_uuid, $staff_uuid, $class_name, $arm_name, $session_name, $term_name]);
            
            if ($check->fetch()) {
                $error_msg = 'This class teacher assignment already exists.';
            } else {
                $pdo->prepare("
                    INSERT INTO class_teacher_assignments 
                    (school_uuid, staff_uuid, class_name, arm_name, session_name, term_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ")->execute([$school_uuid, $staff_uuid, $class_name, $arm_name, $session_name, $term_name]);
                
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'teacher.assign_class', $staff_uuid, 
                    "Assigned class teacher: $class_name $arm_name — $term_name $session_name");
                $success_msg = 'Class teacher assigned! They now have write access to Attendance and Report Cards for this class.';
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_msg = 'This class teacher assignment already exists.';
            } else {
                $error_msg = safe_error('Error', $e);
            }
        }
    }
}

if (isset($_POST['action_remove_class_teacher']) && $active_role === 'School Admin') {
    $assignment_id = safe_int($_POST['assignment_id'] ?? 0);
    if ($assignment_id) {
        try {
            // Verify ownership before deleting
            $check = $pdo->prepare("SELECT id FROM class_teacher_assignments WHERE id = ? AND school_uuid = ?");
            $check->execute([$assignment_id, $school_uuid]);
            
            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM class_teacher_assignments WHERE id = ? AND school_uuid = ?")
                    ->execute([$assignment_id, $school_uuid]);
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'teacher.remove_class', (string)$assignment_id, 'Class teacher assignment removed');
                $success_msg = 'Class teacher assignment removed.';
            }
        } catch (Exception $e) {
            $error_msg = safe_error('Error', $e);
        }
    }
}

// ── TEACHER ASSIGNMENTS (Subject Teacher) ────────────────────────────────────
if (isset($_POST['action_assign_subject_teacher']) && $active_role === 'School Admin') {
    $staff_uuid       = safe_str($_POST['staff_uuid']       ?? '');
    $subject_name     = safe_str($_POST['subject_name']     ?? '');
    $class_name       = safe_str($_POST['class_name']       ?? '');
    $arm_name         = safe_str($_POST['arm_name']         ?? '') ?: null; // NULL = all arms
    $periods_per_week = safe_int($_POST['periods_per_week'] ?? 5);
    $session_name     = safe_str($_POST['session_name']     ?? ($school_settings['current_session'] ?? ''));
    $term_name        = safe_str($_POST['term_name']        ?? ($school_settings['current_term']    ?? ''));

    if (!$staff_uuid || !$subject_name || !$class_name || !$session_name || !$term_name) {
        $error_msg = 'Staff, subject, class, session, and term are all required.';
    } else {
        try {
            // Check for duplicate using unique key combination (staff, subject, class, arm, session, term)
            $check = $pdo->prepare("
                SELECT id FROM staff_subject_assignments 
                WHERE school_uuid = ? AND staff_uuid = ? AND subject_name = ? 
                  AND class_name = ? AND (arm_name <=> ?) AND session_name = ? AND term_name = ?
            ");
            $check->execute([$school_uuid, $staff_uuid, $subject_name, $class_name, $arm_name, $session_name, $term_name]);
            
            if ($check->fetch()) {
                $error_msg = 'This subject teacher assignment already exists.';
            } else {
                $pdo->prepare("
                    INSERT INTO staff_subject_assignments 
                    (school_uuid, staff_uuid, subject_name, class_name, arm_name, periods_per_week, session_name, term_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([$school_uuid, $staff_uuid, $subject_name, $class_name, $arm_name, $periods_per_week, $session_name, $term_name]);
                
                // Get staff name for audit log
                $staff_name = $pdo->prepare("SELECT name FROM staff WHERE staff_uuid = ? AND school_uuid = ?");
                $staff_name->execute([$staff_uuid, $school_uuid]);
                $staff_name = $staff_name->fetchColumn() ?: $staff_uuid;
                
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'teacher.assign_subject', $staff_uuid, 
                    "Assigned subject teacher: $staff_name → $subject_name ($class_name " . ($arm_name ?: 'all arms') . ") — $term_name $session_name");
                $success_msg = 'Subject teacher assigned! They now have write access to Results and the Broadsheet for this subject/class.';
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_msg = 'This subject teacher assignment already exists.';
            } else {
                $error_msg = safe_error('Error', $e);
            }
        }
    }
}

if (isset($_POST['action_remove_subject_teacher']) && $active_role === 'School Admin') {
    $assignment_id = safe_int($_POST['assignment_id'] ?? 0);
    if ($assignment_id) {
        try {
            // Verify ownership and get details for audit log
            $check = $pdo->prepare("
                SELECT ssa.id, s.name AS staff_name, ssa.subject_name, ssa.class_name, ssa.arm_name
                FROM staff_subject_assignments ssa
                JOIN staff s ON s.staff_uuid = ssa.staff_uuid
                WHERE ssa.id = ? AND ssa.school_uuid = ?
            ");
            $check->execute([$assignment_id, $school_uuid]);
            $assignment = $check->fetch();
            
            if ($assignment) {
                $pdo->prepare("DELETE FROM staff_subject_assignments WHERE id = ? AND school_uuid = ?")
                    ->execute([$assignment_id, $school_uuid]);
                
                $details = $assignment['staff_name'] . ' from ' . $assignment['subject_name'] . 
                          ' (' . $assignment['class_name'] . ' ' . ($assignment['arm_name'] ?: 'all arms') . ')';
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'teacher.remove_subject', (string)$assignment_id, 
                    "Removed subject teacher: $details");
                $success_msg = 'Subject teacher assignment removed.';
            } else {
                $error_msg = 'Assignment not found.';
            }
        } catch (Exception $e) {
            $error_msg = safe_error('Error', $e);
        }
    }
}

// ── SUBJECTS CATALOG ─────────────────────────────────────────────────────────
if (isset($_POST['action_add_subject']) && $active_role === 'School Admin') {
    $code = safe_str($_POST['subject_code'] ?? '');
    $name = safe_str($_POST['subject_name'] ?? '');
    $dept = safe_str($_POST['department_name'] ?? 'General');
    if (!$name) {
        $error_msg = 'Subject name is required.';
    } else {
        try {
            $pdo->prepare("INSERT INTO academic_subjects (school_uuid, subject_code, subject_name, department_name) VALUES (?, ?, ?, ?)")
                ->execute([$school_uuid, $code ?: strtoupper(substr($name,0,4)), $name, $dept]);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'subject.add', '', "Added subject: $name");
            $success_msg = "Subject '$name' added!";
        } catch (Exception $e) { $error_msg = safe_error('Error', $e); }
    }
}
if (isset($_POST['action_delete_subject']) && $active_role === 'School Admin') {
    $id = safe_int($_POST['subject_id'] ?? 0);
    $pdo->prepare("DELETE FROM academic_subjects WHERE id=? AND school_uuid=?")->execute([$id, $school_uuid]);
    $success_msg = 'Subject removed.';
}

// ── PROMOTION ────────────────────────────────────────────────────────────────
// MERGED handler: this action name (action_promote_class) previously had TWO
// separate, conflicting handlers — the old academic-actions.php version
// (promote-only, but with a promotion_log guard against double-promoting a
// class in the same session, and student_class_history tracking) and the old
// phase4-actions.php version (supported both promote AND graduate modes via
// promotion_mode, wrote to alumni on graduate, but had NO double-promotion
// guard and didn't track history). Both used to run on every submission
// since neither file exited after handling. This version keeps BOTH modes
// from phase4's version and adds back the promotion_log guard + history
// tracking from academic-actions.php's version, for the promote path only
// (graduating already has its own guard: it only touches Active students,
// so re-running it against an already-graduated class is a no-op).
if (isset($_POST['action_promote_class']) && $active_role === 'School Admin') {
    $from_class = safe_str($_POST['from_class'] ?? '');
    $to_class   = safe_str($_POST['to_class']   ?? '');
    $mode       = safe_str($_POST['promotion_mode'] ?? 'promote'); // promote | graduate
    $current_session = $school_settings['current_session'] ?? '';

    if (!$from_class) {
        $error_msg = 'Select a class to promote.';
    } elseif ($mode === 'promote' && !$to_class) {
        $error_msg = 'Select the destination class.';
    } elseif ($mode === 'promote' && !$current_session) {
        $error_msg = 'No current session is set — cannot promote.';
    } elseif ($mode === 'promote' && (function() use ($pdo, $school_uuid, $current_session, $from_class) {
        $chk = $pdo->prepare("SELECT id FROM promotion_log WHERE school_uuid=? AND session_name=? AND from_class=? LIMIT 1");
        $chk->execute([$school_uuid, $current_session, $from_class]);
        return (bool)$chk->fetch();
    })()) {
        $error_msg = "$from_class has already been promoted this session ($current_session). Promotion can only run once per class per session.";
    } else {
        $sq = $pdo->prepare("SELECT student_uuid, name, arm FROM students WHERE school_uuid=? AND class=? AND status='Active'");
        $sq->execute([$school_uuid, $from_class]);
        $students_in_class = $sq->fetchAll();

        if (empty($students_in_class)) {
            $success_msg = "No active students found in $from_class.";
        } elseif ($mode === 'graduate') {
            foreach ($students_in_class as $s) {
                $pdo->prepare("UPDATE students SET status='Graduated' WHERE student_uuid=? AND school_uuid=?")->execute([$s['student_uuid'], $school_uuid]);
                $pdo->prepare("INSERT INTO alumni (alumni_uuid,school_uuid,student_uuid,name,graduation_year,final_class,archived_date) VALUES (?,?,?,?,?,?,CURDATE())
                    ON DUPLICATE KEY UPDATE name=VALUES(name)")
                    ->execute([uid('alm'), $school_uuid, $s['student_uuid'], $s['name'], (int)date('Y'), $from_class]);
            }
            AuditLog::write($pdo,$school_uuid,$user_uuid,'class.graduate','',"Graduated " . count($students_in_class) . " students from $from_class");
            $success_msg = "Graduated " . count($students_in_class) . " students from $from_class!";
        } else {
            // Promote: history tracking + promotion_log guard, then move students.
            $histIns = $pdo->prepare("
                INSERT INTO student_class_history (school_uuid, student_uuid, session_name, class_name, arm_name, event_type)
                VALUES (?, ?, ?, ?, ?, 'Promotion')
                ON DUPLICATE KEY UPDATE class_name = VALUES(class_name), arm_name = VALUES(arm_name)
            ");
            foreach ($students_in_class as $m) {
                $histIns->execute([$school_uuid, $m['student_uuid'], $current_session, $from_class, $m['arm']]);
            }

            $stmt = $pdo->prepare("UPDATE students SET class=? WHERE school_uuid=? AND class=? AND status='Active'");
            $stmt->execute([$to_class, $school_uuid, $from_class]);
            $n = $stmt->rowCount();

            $pdo->prepare("
                INSERT INTO promotion_log (school_uuid, session_name, from_class, to_class, promoted_count, promoted_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$school_uuid, $current_session, $from_class, $to_class, $n, $_SESSION['name'] ?? $active_role]);

            AuditLog::write($pdo,$school_uuid,$user_uuid,'class.promote','',"Promoted $n students from $from_class to $to_class ($current_session)");
            $success_msg = "Promoted $n students from $from_class to $to_class! This class cannot be promoted again this session.";
        }
    }
}

// ── GRADUATION (whole-class graduation, independent of the promote flow) ────
if (isset($_POST['action_graduate_class']) && $active_role === 'School Admin') {
    $grad = safe_str($_POST['graduate_class'] ?? '');
    $current_session = $school_settings['current_session'] ?? '';

    $students_leaving = $pdo->prepare("SELECT student_uuid, arm FROM students WHERE school_uuid=? AND class=? AND status='Active'");
    $students_leaving->execute([$school_uuid, $grad]);
    $leaving = $students_leaving->fetchAll();
    if ($current_session) {
        $histIns = $pdo->prepare("
            INSERT INTO student_class_history (school_uuid, student_uuid, session_name, class_name, arm_name, event_type)
            VALUES (?, ?, ?, ?, ?, 'Graduation')
            ON DUPLICATE KEY UPDATE class_name = VALUES(class_name), arm_name = VALUES(arm_name), event_type='Graduation'
        ");
        foreach ($leaving as $m) {
            $histIns->execute([$school_uuid, $m['student_uuid'], $current_session, $grad, $m['arm']]);
        }
    }

    $stmt = $pdo->prepare("UPDATE students SET status='Graduated' WHERE school_uuid=? AND class=? AND status='Active'");
    $stmt->execute([$school_uuid,$grad]);
    $n = $stmt->rowCount();
    AuditLog::write($pdo,$school_uuid,$user_uuid,'class.graduate','',"Graduated $n from $grad");
    $success_msg = "Graduated $n students from $grad!";
}
