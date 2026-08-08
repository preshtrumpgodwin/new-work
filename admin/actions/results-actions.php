<?php
/**
 * Results Actions — Phase 6 rebuild.
 * Batch save (dynamic assessment_configurations only), delete, all gated by 
 * write access and — for non-full-access staff — scoped to subjects they're 
 * actually assigned to teach this term.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
require_once __DIR__ . '/../lib/GradingEngine.php';

if (isset($_POST['action_save_results_batch'])) {
    $can_write_results = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('results'));
    if (!$can_write_results) { $error_msg = 'You do not have permission to enter results.'; return; }

    $class   = safe_str($_POST['res_class']   ?? '');
    $arm     = safe_str($_POST['res_arm']     ?? '');
    $subject = safe_str($_POST['res_subject'] ?? '');
    $session = safe_str($_POST['res_session'] ?? '');
    $term    = safe_str($_POST['res_term']    ?? '');

    if (!$class || !$subject || !$session || !$term) {
        $error_msg = 'Missing required fields.';
        return;
    }

    // Non-full-access staff may only save for a subject they're assigned to teach.
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_approve($active_role, feature_access('results'))) {
        $su = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
        $su->execute([$user_uuid, $school_uuid]);
        $my_staff_uuid = $su->fetchColumn() ?: '';
        $my_subjects = getTeacherSubjects($pdo, $my_staff_uuid, $school_uuid, $class, $session, $term);
        if (!in_array($subject, $my_subjects, true)) {
            $error_msg = "You are not assigned to teach $subject for $class this term.";
            return;
        }
    }

    $grading = GradingEngine::fromDB($pdo, $school_uuid);
    $saved = 0;

    // Assessment columns for this class/session/term — same shared helper
    // used by the entry page and report cards, so the keys posted by the
    // form (score_<key>[...]) are guaranteed to match what we save under.
    $assess_cols = getAssessmentColumns($pdo, $school_uuid, $session, $term, $class);
    $configs = $assess_cols['columns']; // [{key, label, max}, ...]

    if (empty($configs)) {
        $error_msg = 'No assessment configurations found for this class, session, and term.';
        return;
    }

    // ── Collect posted scores, one column at a time ─────────────────────────
    // Only a field the teacher actually typed something into counts as a
    // real score. The entry page renders a (possibly blank) input for every
    // student in the roster, so treating a blank field as "0" here used to
    // create a real results row — and make the subject show up on that
    // student's report card — for every student in the class the instant
    // the teacher saved the form once, even ones they never touched.
    $scores_by_student = []; // student_uuid => [column_key => score]
    $remarks = $_POST['remark'] ?? [];
    $max_by_key = [];
    foreach ($configs as $cfg) {
        $max_by_key[$cfg['key']] = (float)$cfg['max'];
        $field = 'score_' . $cfg['key'];
        foreach (($_POST[$field] ?? []) as $uuid => $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') continue; // left blank — not an entered score
            $uuid = safe_str($uuid);
            $score = min((float)$raw, $max_by_key[$cfg['key']]);
            $scores_by_student[$uuid][$cfg['key']] = $score;
        }
    }

    // Upsert one row per (student, assessment column). config_uuid and
    // template_uuid both carry the same stable key — the older separate
    // "assessment_configurations"/"assessment_templates" tables these
    // columns originally pointed at are no longer used; JSON config in
    // school_settings is the single source of truth (see getAssessmentColumns()).
    $upsertScore = $pdo->prepare("
        INSERT INTO result_assessment_scores (school_uuid, student_uuid, session_name, term_name, class_name, subject_name, config_uuid, template_uuid, score, max_score, entered_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE score=VALUES(score), max_score=VALUES(max_score), entered_by=VALUES(entered_by)
    ");

    $upsertResult = $pdo->prepare("
        INSERT INTO results (result_uuid, school_uuid, student_uuid, session_name, term_name, class_name, arm_name, subject_name, total_score, grade, subject_teacher_remark, entered_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE total_score=VALUES(total_score), grade=VALUES(grade), subject_teacher_remark=VALUES(subject_teacher_remark), entered_by=VALUES(entered_by)
    ");

    $save_error = null;
    try {
        foreach ($scores_by_student as $uuid => $cfg_scores) {
            if (empty($cfg_scores)) continue;
            $total_score = 0; $total_max = 0;
            foreach ($configs as $cfg) {
                $key = $cfg['key'];
                $sc = $cfg_scores[$key] ?? 0;
                $total_score += $sc;
                $total_max += $max_by_key[$key];

                $upsertScore->execute([$school_uuid, $uuid, $session, $term, $class, $subject, $key, $key, $sc, $max_by_key[$key], $_SESSION['name'] ?? 'Staff']);
            }
            $pct = $total_max > 0 ? round(($total_score / $total_max) * 100, 1) : 0;
            $grade = $grading->gradeLabel($pct);
            $rem = safe_str(substr($remarks[$uuid] ?? '', 0, 200));
            $upsertResult->execute([uid('res'), $school_uuid, $uuid, $session, $term, $class, $arm, $subject, $pct, $grade, $rem, $_SESSION['name'] ?? 'Staff']);
            $saved++;
        }
    } catch (Exception $e) {
        $save_error = safe_error('Failed to save scores', $e);
    }

    if ($save_error) {
        $error_msg = $save_error;
        return;
    }

    AuditLog::write($pdo, $school_uuid, $user_uuid, 'results.batch_save', '',
        "Saved $saved scores — $class $arm / $subject / $term $session (dynamic assessments)");
    $success_msg = "$saved score" . ($saved === 1 ? '' : 's') . " saved successfully!";
}

// Delete a single result row
if (isset($_POST['action_delete_result']) && can_manage($active_role, $current_access)) {
    $uuid = safe_str($_POST['result_uuid'] ?? '');
    $pdo->prepare("DELETE FROM results WHERE result_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'results.delete', $uuid, 'Result deleted');
    $success_msg = 'Result deleted.';
}
?>