<?php
/**
 * SECTION: Results Entry
 * Full score entry — dynamic assessments per subject, live total+grade, save to DB.
 */
render_breadcrumb([
    'Dashboard' => 'dashboard.php?section=overview',
    'Results Entry' => null,
]);

require_once __DIR__ . '/../lib/GradingEngine.php';
$grading  = GradingEngine::fromDB($pdo, $school_uuid);
$can_write = can_manage($active_role, $current_access);
$is_full_view = in_array($active_role, ['School Admin','Platform Manager']) || can_approve($active_role, $current_access ?? 'hide');

// ── Filters ────────────────────────────────────────────────────────────────
$res_class   = safe_str($_GET['res_class']   ?? '');
$res_arm     = safe_str($_GET['res_arm']     ?? '');
$res_subject = safe_str($_GET['res_subject'] ?? '');
$res_session = $school_settings['current_session'] ?? '';
$res_term    = $school_settings['current_term'] ?? '';

// ── Subject list from DB only (no fallback) ──────────────────────────────
$subject_list = [];
try {
    $sj = $pdo->prepare("SELECT subject_name FROM academic_subjects WHERE school_uuid=? ORDER BY subject_name ASC");
    $sj->execute([$school_uuid]);
    $subject_list = $sj->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {}

// ── Arms ── scoped to the selected class (arms belong to one class) ────────
$arms = [];
if (!empty($res_class)) {
    try {
        $aq = $pdo->prepare("SELECT arm_name FROM academic_arms WHERE school_uuid=? AND class_name=? ORDER BY id ASC");
        $aq->execute([$school_uuid, $res_class]); $arms = $aq->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e){}
}

// ── Non-full-access staff only get to enter results for subjects they're ──
// actually assigned to teach (staff_subject_assignments) this session/term.
$my_subjects = null; // null = no restriction (full access)
if (!$is_full_view && !empty($res_class) && !empty($res_session) && !empty($res_term)) {
    $su = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
    $su->execute([$user_uuid, $school_uuid]);
    $my_staff_uuid = $su->fetchColumn() ?: '';
    $my_subjects = getTeacherSubjects($pdo, $my_staff_uuid, $school_uuid, $res_class, $res_session, $res_term);
    $subject_list = array_values(array_intersect($subject_list, $my_subjects)) ?: $my_subjects;
    if (empty($subject_list)) $can_write = false; // nothing this teacher can enter for this class
}

// ── Set default values if not selected ────────────────────────────────────
if (empty($res_subject) && !empty($subject_list)) $res_subject = $subject_list[0];

// A non-full-access staff member can't force their way into a subject they
// aren't assigned to by editing the URL — falls back to read-only display.
if ($my_subjects !== null && !in_array($res_subject, $my_subjects, true)) $can_write = false;
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="file-spreadsheet" class="w-5 h-5 text-amber-400"></i> Results Entry
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">
                <?php if ($res_class && $res_arm && $res_subject && $res_term && $res_session): ?>
                    <?php echo htmlspecialchars($res_class); ?> <?php echo htmlspecialchars($res_arm); ?>
                    &nbsp;·&nbsp; <?php echo htmlspecialchars($res_subject); ?>
                    &nbsp;·&nbsp; <?php echo htmlspecialchars($res_term); ?>, <?php echo htmlspecialchars($res_session); ?>
                <?php else: ?>
                    Select filters below to enter results
                <?php endif; ?>
            </p>
        </div>
        <?php if ($res_class && $res_arm && $res_subject && $res_term && $res_session): ?>
        <div class="flex items-center gap-2">
            <a href="dashboard.php?section=broadsheet&filter_class=<?php echo urlencode($res_class); ?>&filter_term=<?php echo urlencode($res_term); ?>&filter_sess=<?php echo urlencode($res_session); ?>"
               class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] text-xs text-[var(--text-secondary)] font-bold rounded-xl hover:text-white flex items-center gap-2">
                <i data-lucide="table-2" class="w-4 h-4"></i> Broadsheet
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filters - Always Visible (Class, Arm, Subject only) -->
    <form method="GET" id="filterForm" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="results">
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Class</label>
            <select name="res_class" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                <option value="">— Select —</option>
                <?php foreach ($roster_classes as $cl): ?>
                    <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $res_class===$cl?'selected':''; ?>><?php echo htmlspecialchars($cl); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Arm</label>
            <select name="res_arm" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                <option value="">— Select —</option>
                <?php if (!empty($arms)): ?>
                    <?php foreach ($arms as $ar): ?>
                        <option value="<?php echo htmlspecialchars($ar); ?>" <?php echo $res_arm===$ar?'selected':''; ?>><?php echo htmlspecialchars($ar); ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled>Select a class first</option>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Subject</label>
            <select name="res_subject" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                <option value="">— Select —</option>
                <?php if (!empty($subject_list)): ?>
                    <?php foreach ($subject_list as $sb): ?>
                        <option value="<?php echo htmlspecialchars($sb); ?>" <?php echo $res_subject===$sb?'selected':''; ?>><?php echo htmlspecialchars($sb); ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled>No subjects available</option>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Term</label>
            <div class="bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-3 py-2 text-xs text-emerald-400 font-mono">
                <?php echo htmlspecialchars($res_term ?: '—'); ?>
            </div>
        </div>
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Session</label>
            <div class="bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-3 py-2 text-xs text-emerald-400 font-mono">
                <?php echo htmlspecialchars($res_session ?: '—'); ?>
            </div>
        </div>
    </form>

    <?php
    // ── Check for missing data and show messages ──────────────────────────
    $has_error = false;
    $error_messages = [];

    if (empty($subject_list)) {
        $error_messages[] = 'No subjects configured. Please add subjects in <a href="dashboard.php?section=settings" class="text-indigo-400 underline">Settings → Subjects Catalog</a>.';
        $has_error = true;
    }

    if (empty($res_session) || empty($res_term)) {
        $error_messages[] = 'No current session or term set. Please set current session and term in <a href="dashboard.php?section=academic" class="text-indigo-400 underline">Academic Operations</a>.';
        $has_error = true;
    }

    if (empty($roster_classes)) {
        $error_messages[] = 'No classes configured. Please add classes in <a href="dashboard.php?section=academic" class="text-indigo-400 underline">Academic Operations</a>.';
        $has_error = true;
    }

    $has_assessment_templates = false;
    try {
        $tplChk = $pdo->prepare("SELECT assessment_templates_json FROM school_settings WHERE school_uuid = ?");
        $tplChk->execute([$school_uuid]);
        $tplRaw = json_decode((string)$tplChk->fetchColumn(), true);
        $has_assessment_templates = !empty($tplRaw);
    } catch (Exception $e) {}
    if (!$has_assessment_templates) {
        $error_messages[] = 'No assessment templates configured. Please add assessment templates in <a href="dashboard.php?section=settings" class="text-indigo-400 underline">Settings → Assessment Configuration</a>.';
        $has_error = true;
    }

    if (!$has_error && empty($res_class)) {
        $error_messages[] = 'Please select a class from the filters above.';
        $has_error = true;
    }

    if (!$has_error && !empty($res_class) && empty($arms)) {
        $error_messages[] = 'No arms configured for ' . htmlspecialchars($res_class) . '. Please add arms in <a href="dashboard.php?section=academic" class="text-indigo-400 underline">Academic Operations</a>.';
        $has_error = true;
    }

    if (!$has_error && !empty($res_class) && !empty($arms) && empty($res_arm)) {
        $error_messages[] = 'Please select an arm from the filters above.';
        $has_error = true;
    }

    // Display error messages
    if ($has_error):
    ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
        <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
        <?php foreach ($error_messages as $msg): ?>
            <p class="mb-2"><?php echo $msg; ?></p>
        <?php endforeach; ?>
    </div>
    <?php
        return; // Stop here if there are errors
    endif;

    // ── If no subject selected, show message ──────────────────────────────
    if (empty($res_subject)):
    ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
        <i data-lucide="book-open" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
        <p class="font-bold text-base text-[var(--text-primary)]">No subject selected</p>
        <p>Please select a subject from the filters above.</p>
    </div>
    <?php
        return;
    endif;

    // ── Assessment columns for this class/session/term — same shared helper
    // used by report cards, broadsheet, and print slips, so every screen
    // always agrees on what's configured and what each score belongs to. ──
    $assess_cols = getAssessmentColumns($pdo, $school_uuid, $res_session, $res_term, $res_class);
    $dynamic_configs = $assess_cols['columns']; // [{key, label, max}, ...] — key is the stable id used everywhere
    $use_dynamic = $assess_cols['configured'];

    // If no configurations, show message
    if (!$use_dynamic):
    ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
        <i data-lucide="settings" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
        <p class="font-bold text-base text-[var(--text-primary)]">No assessment configurations</p>
        <p>No assessments configured for <?php echo htmlspecialchars($res_class); ?> in <?php echo htmlspecialchars($res_term); ?>, <?php echo htmlspecialchars($res_session); ?>.</p>
        <p>Configure assessments in <a href="dashboard.php?section=settings" class="text-indigo-400 underline">Settings → Assessment Configuration</a>.</p>
    </div>
    <?php
        return;
    endif;

    // ── Students in class ─────────────────────────────────────────────────────
    $res_students = [];
    try {
        $rs_sql = "SELECT student_uuid, name, roll_number FROM students WHERE school_uuid=? AND class=? AND status='Active'";
        $rs_params = [$school_uuid, $res_class];
        if ($res_arm !== '') { $rs_sql .= " AND arm=?"; $rs_params[] = $res_arm; }
        $rs_sql .= " ORDER BY name ASC";
        $st = $pdo->prepare($rs_sql);
        $st->execute($rs_params);
        $res_students = $st->fetchAll();
    } catch(Exception $e){}

    // If no students, show message
    if (empty($res_students)):
    ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
        <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
        <p class="font-bold text-base text-[var(--text-primary)]">No active students</p>
        <p>No active students in <?php echo htmlspecialchars($res_class); ?> <?php echo htmlspecialchars($res_arm); ?>.</p>
        <p>Enroll students first via <a href="dashboard.php?section=roster" class="text-indigo-400 underline">Student Management</a>.</p>
    </div>
    <?php
        return;
    endif;

    // ── Existing scores keyed by student_uuid ─────────────────────────────────
    $existing_dynamic_scores = [];
    $existing_scores = [];
    if (!empty($res_students)) {
        try {
            $uuids = array_column($res_students, 'student_uuid');
            $in = implode(',', array_fill(0, count($uuids), '?'));
            
            // Get existing results
            $ex = $pdo->prepare("SELECT student_uuid, total_score, grade, subject_teacher_remark
                FROM results
                WHERE school_uuid=? AND class_name=? AND arm_name=? AND session_name=?
                AND term_name=? AND subject_name=? AND student_uuid IN ($in)");
            $ex->execute(array_merge([$school_uuid,$res_class,$res_arm,$res_session,$res_term,$res_subject], $uuids));
            foreach ($ex->fetchAll() as $row) {
                $existing_scores[$row['student_uuid']] = $row;
            }
            
            // Get dynamic assessment scores using template_uuid
            try {
                $ds = $pdo->prepare("SELECT student_uuid, template_uuid, score FROM result_assessment_scores
                    WHERE school_uuid=? AND session_name=? AND term_name=? AND subject_name=? AND student_uuid IN ($in)");
                $ds->execute(array_merge([$school_uuid, $res_session, $res_term, $res_subject], $uuids));
                foreach ($ds->fetchAll() as $row) {
                    if (!isset($existing_dynamic_scores[$row['student_uuid']])) {
                        $existing_dynamic_scores[$row['student_uuid']] = [];
                    }
                    $existing_dynamic_scores[$row['student_uuid']][$row['template_uuid']] = (float)$row['score'];
                }
            } catch (Exception $e) {}
        } catch(Exception $e){}
    }

    // ── Class average for this subject ────────────────────────────────────────
    $class_avg = 0;
    if (!empty($existing_scores)) {
        $totals = array_column(array_values($existing_scores), 'total_score');
        $class_avg = count($totals) ? round(array_sum($totals)/count($totals), 1) : 0;
    }

    // ── Grade scale for legend ────────────────────────────────────────────────
    $scale = $grading->scale();
    ?>

    <!-- Stats row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <?php
        $saved_count   = count($existing_scores);
        $above_avg     = count(array_filter($existing_scores, fn($r) => $r['total_score'] >= $class_avg));
        $failing_count = count(array_filter($existing_scores, fn($r) => $r['total_score'] < 40));
        ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4 text-center">
            <div class="text-lg font-black text-indigo-400"><?php echo count($res_students); ?></div>
            <div class="text-[10px] text-[var(--text-secondary)] mt-0.5">Students</div>
        </div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4 text-center">
            <div class="text-lg font-black text-emerald-400"><?php echo $saved_count; ?></div>
            <div class="text-[10px] text-[var(--text-secondary)] mt-0.5">Scores Saved</div>
        </div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4 text-center">
            <div class="text-lg font-black text-amber-400"><?php echo $class_avg; ?>%</div>
            <div class="text-[10px] text-[var(--text-secondary)] mt-0.5">Class Average</div>
        </div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4 text-center">
            <div class="text-lg font-black text-rose-400"><?php echo $failing_count; ?></div>
            <div class="text-[10px] text-[var(--text-secondary)] mt-0.5">Below 40</div>
        </div>
    </div>

    <!-- Score entry table -->
    <form method="POST" id="resultsForm"><?php echo csrf_field(); ?>
        <input type="hidden" name="action_save_results_batch" value="1">
        <input type="hidden" name="res_class"   value="<?php echo htmlspecialchars($res_class); ?>">
        <input type="hidden" name="res_arm"     value="<?php echo htmlspecialchars($res_arm); ?>">
        <input type="hidden" name="res_subject" value="<?php echo htmlspecialchars($res_subject); ?>">
        <input type="hidden" name="res_session" value="<?php echo htmlspecialchars($res_session); ?>">
        <input type="hidden" name="res_term"    value="<?php echo htmlspecialchars($res_term); ?>">

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
            <!-- Column header with limits -->
            <div class="px-5 py-3 border-b border-[var(--border-color)] bg-[var(--bg-tertiary)] flex items-center justify-between">
                <span class="text-[10px] font-mono font-bold text-[var(--text-secondary)] uppercase tracking-widest">
                    <?php echo htmlspecialchars($res_subject); ?> &nbsp;—&nbsp;
                    <?php foreach ($dynamic_configs as $i => $cfg): ?>
                        <?php echo $i>0?' · ':''; ?>
                        <?php echo htmlspecialchars($cfg['label']); ?> /<?php echo (int)$cfg['max']; ?>
                    <?php endforeach; ?>
                    &nbsp;·&nbsp; Total /<?php 
                        $total_max = 0;
                        foreach ($dynamic_configs as $cfg) {
                            $total_max += (float)$cfg['max'];
                        }
                        echo $total_max;
                    ?>
                </span>
                <?php if ($can_write): ?>
                <button type="submit"
                    class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-black rounded-xl shadow-md flex items-center gap-1.5">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i> Save All Scores
                </button>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-xs" id="resultsTable">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                    <tr>
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4">Roll</th>
                        <?php foreach ($dynamic_configs as $cfg): ?>
                        <th class="py-3 px-4 text-center"><?php echo htmlspecialchars($cfg['label']); ?><span class="opacity-50">/<?php echo (int)$cfg['max']; ?></span></th>
                        <?php endforeach; ?>
                        <th class="py-3 px-4 text-center font-black text-indigo-400">Total</th>
                        <th class="py-3 px-4 text-center">Grade</th>
                        <th class="py-3 px-4 text-center">Remark</th>
                        <th class="py-3 px-4">Teacher Comment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                <?php foreach ($res_students as $std):
                    $uuid = $std['student_uuid'];
                    $cfg_scores = $existing_dynamic_scores[$uuid] ?? [];
                    $tot = 0; $tot_max = 0;
                    foreach ($dynamic_configs as $cfg) {
                        $tot += $cfg_scores[$cfg['key']] ?? 0;
                        $tot_max += (float)$cfg['max'];
                    }
                    $pct = $tot_max > 0 ? round(($tot / $tot_max) * 100, 1) : 0;
                    $g = $grading->grade($pct);
                    $ex = $existing_scores[$uuid] ?? null;
                    $teacherRemark = htmlspecialchars($ex['subject_teacher_remark'] ?? '');
                ?>
                <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors result-row">
                    <td class="py-3 px-4 font-bold"><?php echo htmlspecialchars($std['name']); ?></td>
                    <td class="py-3 px-4 font-mono text-indigo-400 text-[10px]"><?php echo htmlspecialchars($std['roll_number']); ?></td>

                    <?php foreach ($dynamic_configs as $cfg):
                        $cur = $cfg_scores[$cfg['key']] ?? 0;
                    ?>
                    <td class="py-3 px-4 text-center">
                        <?php if ($can_write): ?>
                        <input type="number" name="score_<?php echo htmlspecialchars($cfg['key']); ?>[<?php echo $uuid; ?>]"
                               value="<?php echo $cur > 0 ? $cur : ''; ?>"
                               min="0" max="<?php echo (float)$cfg['max']; ?>" step="0.5"
                               class="score-input dyn-input w-16 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-center text-[var(--text-primary)] focus:border-indigo-500 focus:outline-none"
                               placeholder="0">
                        <?php else: ?><span class="font-mono"><?php echo $cur; ?></span><?php endif; ?>
                    </td>
                    <?php endforeach; ?>

                    <!-- Live total -->
                    <td class="py-3 px-4 text-center">
                        <span class="total-cell font-black text-lg <?php echo $pct>=70?'text-emerald-400':($pct>=50?'text-amber-400':'text-rose-400'); ?>">
                            <?php echo $pct > 0 ? $pct : '—'; ?>
                        </span>
                    </td>

                    <!-- Grade -->
                    <td class="py-3 px-4 text-center">
                        <span class="grade-cell font-black text-sm <?php echo $g['grade'][0]==='A'?'text-emerald-400':($g['grade'][0]==='F'?'text-rose-400':'text-amber-400'); ?>">
                            <?php echo $pct > 0 ? htmlspecialchars($g['grade']) : '—'; ?>
                        </span>
                    </td>

                    <!-- Remark -->
                    <td class="py-3 px-4 text-center">
                        <span class="remark-cell text-[10px] text-[var(--text-secondary)]">
                            <?php echo $pct > 0 ? htmlspecialchars($g['remark']) : '—'; ?>
                        </span>
                    </td>

                    <!-- Teacher remark -->
                    <td class="py-3 px-4">
                        <?php if ($can_write): ?>
                        <input type="text" name="remark[<?php echo $uuid; ?>]"
                               value="<?php echo $teacherRemark; ?>"
                               maxlength="200"
                               class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)] focus:border-indigo-500 focus:outline-none"
                               placeholder="Optional comment…">
                        <?php else: ?><span class="text-[var(--text-secondary)] italic text-[10px]"><?php echo $teacherRemark ?: '—'; ?></span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if ($can_write): ?>
            <div class="px-5 py-4 border-t border-[var(--border-color)] flex justify-end">
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black rounded-xl shadow-md flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Save All Scores
                </button>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Grade scale legend -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5">
        <h3 class="text-[10px] font-bold text-[var(--text-secondary)] uppercase font-mono tracking-widest mb-3">Grading Scale</h3>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($scale as $band): ?>
            <span class="px-3 py-1.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-[10px] font-mono font-bold text-[var(--text-primary)]">
                <span class="text-indigo-400"><?php echo $band['grade']; ?></span>
                &nbsp;<?php echo $band['min']; ?>–<?php echo $band['max']; ?>
                &nbsp;<span class="text-[var(--text-secondary)]"><?php echo $band['remark']; ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// ── Live total + grade calculation ──────────────────────────────────────
const gradeScale = <?php echo json_encode($scale); ?>;

function scoreToGrade(total) {
    for (const band of gradeScale) {
        if (total >= band.min && total <= band.max)
            return { grade: band.grade, remark: band.remark };
    }
    return { grade: 'F9', remark: 'Fail' };
}

function gradeColor(grade) {
    if (grade.startsWith('A')) return 'text-emerald-400';
    if (grade.startsWith('F')) return 'text-rose-400';
    return 'text-amber-400';
}

function totalColor(total) {
    if (total >= 70) return 'text-emerald-400';
    if (total >= 50) return 'text-amber-400';
    return 'text-rose-400';
}

document.querySelectorAll('.result-row').forEach(row => {
    const inputs = row.querySelectorAll('.score-input');
    const totalCell = row.querySelector('.total-cell');
    const gradeCell = row.querySelector('.grade-cell');
    const remarkCell = row.querySelector('.remark-cell');

    function recalc() {
        let total = 0;
        let totalMax = 0;
        
        inputs.forEach(input => {
            const max = parseFloat(input.max);
            const val = Math.min(parseFloat(input.value || 0), max);
            total += val;
            totalMax += max;
        });

        const percentage = totalMax > 0 ? (total / totalMax) * 100 : 0;
        const g = scoreToGrade(percentage);

        if (totalCell) {
            totalCell.textContent = percentage > 0 ? percentage.toFixed(1).replace('.0','') : '—';
            totalCell.className = `total-cell font-black text-lg ${percentage > 0 ? totalColor(percentage) : 'text-[var(--text-secondary)]'}`;
        }
        if (gradeCell) {
            gradeCell.textContent = percentage > 0 ? g.grade : '—';
            gradeCell.className = `grade-cell font-black text-sm ${percentage > 0 ? gradeColor(g.grade) : 'text-[var(--text-secondary)]'}`;
        }
        if (remarkCell) {
            remarkCell.textContent = percentage > 0 ? g.remark : '—';
        }
    }

    inputs.forEach(inp => {
        if (inp) inp.addEventListener('input', recalc);
    });
});

// Tab key moves to next score input
document.getElementById('resultsTable')?.addEventListener('keydown', e => {
    if (e.key === 'Tab') return; // let browser handle Tab between inputs naturally
    if (e.key === 'Enter') {
        e.preventDefault();
        const inputs = [...document.querySelectorAll('.score-input')];
        const idx = inputs.indexOf(document.activeElement);
        if (idx >= 0 && idx < inputs.length - 1) inputs[idx + 1].focus();
    }
});
</script>