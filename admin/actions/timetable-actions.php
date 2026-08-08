<?php
/**
 * admin/actions/timetable-grid-actions.php
 *
 * Whole-school timetable grid: all classes/arms together, per day, with a
 * shared set of period (time-slot) columns. Replaces the old per-class
 * timetable.php + phase5-actions.php generator/template flow.
 *
 * Actions handled here:
 *   action_add_timetable_period    — add a new time-slot column (school-wide)
 *   action_delete_timetable_period — remove a column (and the slots in it)
 *   action_toggle_timetable_day    — show/hide a day of the week on the grid
 *   action_auto_fill_timetable     — auto-fill every class/arm × active day × period
 *   action_check_timetable_clashes — scan and flag teacher double-bookings
 *   action_override_timetable_clash — accept a flagged clash for one slot
 *   action_publish_timetable       — snapshot-publish the current timetable
 *   action_clear_timetable_all     — wipe every slot for the school (fresh start)
 *
 * (action_add_timetable_slot / action_delete_timetable_slot no longer exist —
 * grid cells are edited inline via admin/api/update-timetable-slot.php.)
 */

$__perm_ok = in_array($active_role, ['School Admin', 'Platform Manager']) || can_manage($active_role, feature_access('timetable'));

$__tt_session = $school_settings['current_session'] ?? '';
$__tt_term    = $school_settings['current_term'] ?? '';

// ── ADD PERIOD COLUMN ───────────────────────────────────────────────────────
if (isset($_POST['action_add_timetable_period'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }
    $label = trim(safe_str($_POST['period_label'] ?? ''));
    if ($label === '') {
        $error_msg = 'Enter a time slot label, e.g. "8:00 - 8:40".';
    } else {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM timetable_periods WHERE school_uuid=? AND label=?");
        $dup->execute([$school_uuid, $label]);
        if ($dup->fetchColumn() > 0) {
            $error_msg = "A column called \"$label\" already exists.";
        } else {
            $ordq = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM timetable_periods WHERE school_uuid=?");
            $ordq->execute([$school_uuid]);
            $nextOrd = (int)$ordq->fetchColumn() + 1;
            $puuid = uid('ttp');
            $pdo->prepare("INSERT INTO timetable_periods (period_uuid, school_uuid, label, sort_order) VALUES (?,?,?,?)")
                ->execute([$puuid, $school_uuid, $label, $nextOrd]);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable_period.add', $puuid, $label);
            $success_msg = "Column \"$label\" added.";
        }
    }
}

// ── DELETE PERIOD COLUMN ────────────────────────────────────────────────────
if (isset($_POST['action_delete_timetable_period'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }
    $puuid = safe_str($_POST['period_uuid'] ?? '');
    $pq = $pdo->prepare("SELECT label FROM timetable_periods WHERE period_uuid=? AND school_uuid=?");
    $pq->execute([$puuid, $school_uuid]);
    $label = $pq->fetchColumn();
    if ($label !== false) {
        $pdo->prepare("DELETE FROM timetable_periods WHERE period_uuid=? AND school_uuid=?")->execute([$puuid, $school_uuid]);
        // Cascade: remove the slots that lived in that column so the grid
        // doesn't hold orphaned rows for a column that no longer exists.
        $pdo->prepare("DELETE FROM timetables WHERE school_uuid=? AND period_time=?")->execute([$school_uuid, $label]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable_period.delete', $puuid, $label);
        $success_msg = "Column \"$label\" removed.";
    }
}

// ── TOGGLE DAY ON/OFF ────────────────────────────────────────────────────────
if (isset($_POST['action_toggle_timetable_day'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }
    $day = safe_str($_POST['day_name'] ?? '');
    $valid_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    if (!in_array($day, $valid_days)) {
        $error_msg = 'Unrecognized day.';
    } else {
        $order = array_search($day, $valid_days);
        $existing = $pdo->prepare("SELECT is_active FROM timetable_days WHERE school_uuid=? AND day_name=?");
        $existing->execute([$school_uuid, $day]);
        $cur = $existing->fetchColumn();
        if ($cur === false) {
            // Wasn't seeded yet — first toggle turns it off (since defaults are active).
            $pdo->prepare("INSERT INTO timetable_days (school_uuid, day_name, is_active, sort_order) VALUES (?,?,0,?)")
                ->execute([$school_uuid, $day, $order]);
            $success_msg = "$day removed from the timetable.";
        } else {
            $new_state = $cur ? 0 : 1;
            $pdo->prepare("UPDATE timetable_days SET is_active=? WHERE school_uuid=? AND day_name=?")
                ->execute([$new_state, $school_uuid, $day]);
            $success_msg = $new_state ? "$day added back to the timetable." : "$day removed from the timetable.";
        }
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable_day.toggle', '', $day);
    }
}

// ── AUTO-FILL EVERY CLASS/ARM ───────────────────────────────────────────────
if (isset($_POST['action_auto_fill_timetable'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }

    // Active days for the grid (seed defaults if none configured yet).
    $days_q = $pdo->prepare("SELECT day_name FROM timetable_days WHERE school_uuid=? AND is_active=1 ORDER BY sort_order ASC");
    $days_q->execute([$school_uuid]);
    $active_days = $days_q->fetchAll(PDO::FETCH_COLUMN);
    if (empty($active_days)) {
        $active_days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
    }

    $periods_q = $pdo->prepare("SELECT label FROM timetable_periods WHERE school_uuid=? ORDER BY sort_order ASC");
    $periods_q->execute([$school_uuid]);
    $periods = $periods_q->fetchAll(PDO::FETCH_COLUMN);

    if (empty($periods)) {
        $error_msg = 'Add at least one time-slot column before auto-filling.';
    } else {
        // Build the full list of class/arm rows the same way the grid does.
        $rows_q = $pdo->prepare("SELECT class_name, arm_name FROM academic_arms WHERE school_uuid=? ORDER BY class_name, arm_name");
        $rows_q->execute([$school_uuid]);
        $arm_rows = $rows_q->fetchAll();

        $classes_with_arms = [];
        foreach ($arm_rows as $ar) { $classes_with_arms[$ar['class_name']] = true; }
        $class_arm_pairs = [];
        foreach ($arm_rows as $ar) { $class_arm_pairs[] = [$ar['class_name'], $ar['arm_name']]; }
        foreach ($roster_classes as $cn) {
            if (empty($classes_with_arms[$cn])) { $class_arm_pairs[] = [$cn, '']; }
        }

        $filled = 0; $skipped_no_assignment = 0;
        $pdo->beginTransaction();
        try {
            foreach ($class_arm_pairs as [$class_name, $arm_name]) {
                // Same fetch + fallback logic used everywhere else: exact
                // arm match, else arm-less assignments, else every
                // assignment on record for the class.
                $saq = $pdo->prepare("
                    SELECT ssa.subject_name AS subject, s.name AS teacher_name,
                           ssa.periods_per_week, ssa.arm_name
                    FROM staff_subject_assignments ssa
                    JOIN staff s ON s.staff_uuid = ssa.staff_uuid
                    WHERE ssa.school_uuid=? AND ssa.class_name=? AND ssa.session_name=? AND ssa.term_name=?
                    ORDER BY ssa.subject_name ASC
                ");
                $saq->execute([$school_uuid, $class_name, $__tt_session, $__tt_term]);
                $all_assignments = $saq->fetchAll();

                $assignments = [];
                foreach ($all_assignments as $a) {
                    if ($a['arm_name'] === $arm_name) $assignments[] = $a;
                    elseif (empty($a['arm_name'])) $assignments[] = $a;
                }
                // Only fall back to "every assignment on record for the
                // class" when NONE of them specify an arm at all (the
                // school isn't tracking this class per-arm). If other arms
                // of this class DO have their own arm-specific assignments,
                // an arm with no match of its own must NOT inherit them —
                // otherwise a teacher assigned to "SS3 Science" twice a week
                // also gets auto-filled into "SS3 Arts" periods.
                $any_arm_specified = false;
                foreach ($all_assignments as $a) { if (!empty($a['arm_name'])) { $any_arm_specified = true; break; } }
                if (empty($assignments) && !$any_arm_specified) $assignments = $all_assignments;
                if (empty($assignments)) { $skipped_no_assignment++; continue; }

                // Build a flat queue of (subject, teacher) repeated periods_per_week times.
                $queue = [];
                foreach ($assignments as $a) {
                    $n = max(1, (int)($a['periods_per_week'] ?: 1));
                    for ($i = 0; $i < $n; $i++) {
                        $queue[] = [$a['subject'], $a['teacher_name']];
                    }
                }

                // Wipe this class/arm's existing grid slots and rebuild.
                $pdo->prepare("DELETE FROM timetables WHERE school_uuid=? AND class_name=? AND arm_name=?")
                    ->execute([$school_uuid, $class_name, $arm_name]);

                $qi = 0;
                foreach ($active_days as $day) {
                    foreach ($periods as $period) {
                        if (isset($queue[$qi])) {
                            [$subj, $teach] = $queue[$qi];
                            $qi++;
                        } else {
                            $subj = '— Free Period —';
                            $teach = 'Unassigned';
                        }
                        $tuuid = uid('tt');
                        $pdo->prepare("INSERT INTO timetables (timetable_uuid, school_uuid, class_name, arm_name, day_of_week, period_time, subject, teacher_name) VALUES (?,?,?,?,?,?,?,?)")
                            ->execute([$tuuid, $school_uuid, $class_name, $arm_name, $day, $period, $subj, $teach]);
                        $filled++;
                    }
                }
            }
            $pdo->commit();
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable.auto_fill_all', '', "$filled slots across " . count($class_arm_pairs) . ' class/arm rows');
            $success_msg = "Auto-fill complete — $filled slots written across " . count($class_arm_pairs) . ' class/arm rows.'
                . ($skipped_no_assignment ? " $skipped_no_assignment row(s) had no subject-teacher assignments and were left untouched." : '')
                . ' Run "Check Clashes" next to catch any double-booked teachers.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = 'Auto-fill failed: ' . safe_error('Error', $e);
        }
    }
}

// ── CHECK CLASHES ────────────────────────────────────────────────────────────
if (isset($_POST['action_check_timetable_clashes'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }

    // Reset all flags first.
    $pdo->prepare("UPDATE timetables SET has_clash=0 WHERE school_uuid=?")->execute([$school_uuid]);

    // A clash = the same teacher assigned to more than one class/arm at the
    // same day + period (free/unassigned periods don't count).
    $cq = $pdo->prepare("
        SELECT day_of_week, period_time, teacher_name, COUNT(*) AS n
        FROM timetables
        WHERE school_uuid=? AND teacher_name IS NOT NULL AND teacher_name NOT IN ('', 'Unassigned')
        GROUP BY day_of_week, period_time, teacher_name
        HAVING COUNT(*) > 1
    ");
    $cq->execute([$school_uuid]);
    $clash_groups = $cq->fetchAll();

    $flagged = 0;
    foreach ($clash_groups as $g) {
        $upd = $pdo->prepare("UPDATE timetables SET has_clash=1 WHERE school_uuid=? AND day_of_week=? AND period_time=? AND teacher_name=?");
        $upd->execute([$school_uuid, $g['day_of_week'], $g['period_time'], $g['teacher_name']]);
        $flagged += (int)$g['n'];
    }

    AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable.check_clashes', '', count($clash_groups) . ' clash group(s), ' . $flagged . ' slot(s)');
    $success_msg = empty($clash_groups)
        ? 'No clashes found — every teacher is only booked in one place per period.'
        : count($clash_groups) . ' clash(es) found, ' . $flagged . ' slot(s) flagged in red below. Reassign the teacher/subject, or override to accept it.';
}

// ── OVERRIDE A FLAGGED CLASH ────────────────────────────────────────────────
if (isset($_POST['action_override_timetable_clash'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }
    $tuuid = safe_str($_POST['timetable_uuid'] ?? '');
    $pdo->prepare("UPDATE timetables SET clash_overridden=1 WHERE timetable_uuid=? AND school_uuid=?")->execute([$tuuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable.override_clash', $tuuid, '');
    $success_msg = 'Clash overridden for that slot — it will still show flagged, but is marked as accepted.';
}

// ── PUBLISH ──────────────────────────────────────────────────────────────────
if (isset($_POST['action_publish_timetable'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to publish the timetable.'; return; }

    $unresolved = $pdo->prepare("SELECT COUNT(*) FROM timetables WHERE school_uuid=? AND has_clash=1 AND clash_overridden=0");
    $unresolved->execute([$school_uuid]);
    $unresolvedCount = (int)$unresolved->fetchColumn();

    if ($unresolvedCount > 0 && empty($_POST['confirm_publish_with_clashes'])) {
        $error_msg = "$unresolvedCount slot(s) still have unresolved clashes. Override them or fix the assignment, then publish again — or tick \"publish anyway\" to override all of them at once.";
    } else {
        if ($unresolvedCount > 0) {
            $pdo->prepare("UPDATE timetables SET clash_overridden=1 WHERE school_uuid=? AND has_clash=1")->execute([$school_uuid]);
        }
        $pdo->prepare("INSERT INTO timetable_publications (school_uuid, session_name, term_name, published_by) VALUES (?,?,?,?)")
            ->execute([$school_uuid, $__tt_session, $__tt_term, $_SESSION['name'] ?? 'Admin']);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable.publish', '', "$__tt_session $__tt_term");
        $success_msg = 'Timetable published! It is now visible to teachers and students.';
    }
}

// ── CLEAR EVERYTHING ─────────────────────────────────────────────────────────
if (isset($_POST['action_clear_timetable_all'])) {
    if (!$__perm_ok) { $error_msg = 'You do not have permission to edit the timetable.'; return; }
    $pdo->prepare("DELETE FROM timetables WHERE school_uuid=?")->execute([$school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'timetable.clear_all', '', '');
    $success_msg = 'Timetable cleared for every class. Auto-fill or build it back up manually.';
}
