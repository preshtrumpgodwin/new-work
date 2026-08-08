<?php
/**
 * SECTION: Report Cards
 * Full per-student report card view with all scores, domains, comments, print.
 */
render_breadcrumb([
    'Dashboard'    => 'dashboard.php?section=overview',
    'Report Cards' => null,
]);
require_once __DIR__ . '/../lib/GradingEngine.php';
$grading   = GradingEngine::fromDB($pdo, $school_uuid);
$can_write = can_manage($active_role, $current_access);
$is_full_view = in_array($active_role, ['School Admin','Platform Manager']) || can_approve($active_role, $current_access ?? 'hide');

// A Class Teacher (i.e. not a full-access viewer, but holding a
// class_teacher_assignments row) is auto-granted write access to Report
// Cards — but only for their own class/arm, not the whole school. Resolve
// their assigned class(es)/arm(s) here so the class picker, student list,
// and single-card view below can all be scoped to it.
$my_class_scopes = [];
if (!$is_full_view) {
    $su = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
    $su->execute([$user_uuid, $school_uuid]);
    $my_staff_uuid = $su->fetchColumn() ?: '';
    $my_class_scopes = getClassTeacherClasses($pdo, $my_staff_uuid, $school_uuid);
}
$is_scoped_class_teacher = !$is_full_view && !empty($my_class_scopes);
$my_classes = $is_scoped_class_teacher ? array_values(array_unique(array_column($my_class_scopes, 'class_name'))) : null;
$my_arms_by_class = [];
foreach ($my_class_scopes as $sc) { $my_arms_by_class[$sc['class_name']][] = $sc['arm_name']; }

// The class picker only ever offers classes this viewer is allowed to see.
$visible_roster_classes = $is_scoped_class_teacher ? $my_classes : $roster_classes;

$rc_class   = safe_str($_GET['rc_class']   ?? ($visible_roster_classes[0] ?? 'JSS1'));
$rc_session = safe_str($_GET['rc_session'] ?? ($school_settings['current_session'] ?? ''));
$rc_term    = safe_str($_GET['rc_term']    ?? ($school_settings['current_term']    ?? ''));
$view_uuid  = safe_str($_GET['view']       ?? '');

// A scoped class teacher can't force their way into another class by
// editing the URL — fall back to their own (first) assigned class instead.
if ($is_scoped_class_teacher && !in_array($rc_class, $my_classes, true)) {
    $rc_class = $my_classes[0];
}

// Sessions / terms
$sessions = []; $terms = [];
try {
    $sq = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid=? ORDER BY id DESC");
    $sq->execute([$school_uuid]); $sessions = $sq->fetchAll(PDO::FETCH_COLUMN);
    $tq = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid=? ORDER BY id ASC");
    $tq->execute([$school_uuid]); $terms = $tq->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e){}
if (empty($sessions)) $sessions = ['2025/2026'];
if (empty($terms))    $terms    = ['First Term','Second Term','Third Term'];
if (!$rc_session) $rc_session = $sessions[0];
if (!$rc_term)    $rc_term    = $terms[0];

// Students — for the current session, use the students table directly.
// For a past session, source from student_class_history so a student who
// has since been promoted (e.g. now in SS3) still shows up under the class
// they were actually in that session (e.g. JSS1), instead of disappearing
// the moment they're promoted. A scoped class teacher additionally only
// sees students in the arm(s) they're actually the class teacher of.
$rc_students = [];
$is_current_session = ($rc_session === ($school_settings['current_session'] ?? ''));
$my_arms_for_class = $is_scoped_class_teacher ? ($my_arms_by_class[$rc_class] ?? []) : [];
try {
    if ($is_current_session) {
        $sql = "SELECT * FROM students WHERE school_uuid=? AND class=? AND status='Active'";
        $params = [$school_uuid, $rc_class];
        if ($is_scoped_class_teacher) {
            $arm_in = implode(',', array_fill(0, count($my_arms_for_class), '?'));
            $sql .= " AND arm IN ($arm_in)";
            $params = array_merge($params, $my_arms_for_class);
        }
        $sql .= " ORDER BY name ASC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
    } else {
        $sql = "
            SELECT s.* FROM students s
            JOIN student_class_history h ON h.student_uuid = s.student_uuid AND h.school_uuid = s.school_uuid
            WHERE s.school_uuid=? AND h.session_name=? AND h.class_name=?
        ";
        $params = [$school_uuid, $rc_session, $rc_class];
        if ($is_scoped_class_teacher) {
            $arm_in = implode(',', array_fill(0, count($my_arms_for_class), '?'));
            $sql .= " AND s.arm IN ($arm_in)";
            $params = array_merge($params, $my_arms_for_class);
        }
        $sql .= " ORDER BY s.name ASC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }
    $rc_students = $st->fetchAll();
} catch(Exception $e){}

// ── Single report card view ────────────────────────────────────────────────
if ($view_uuid) {
    $student = null;
    try {
        $s2 = $pdo->prepare("SELECT * FROM students WHERE student_uuid=? AND school_uuid=? LIMIT 1");
        $s2->execute([$view_uuid, $school_uuid]);
        $student = $s2->fetch();
    } catch(Exception $e){}

    // A scoped class teacher can't view a student outside their own
    // class/arm just by putting a different student_uuid in the URL —
    // enforce the same restriction used to build $rc_students above.
    if ($student && $is_scoped_class_teacher) {
        $allowed_arms = $my_arms_by_class[$student['class'] ?? ''] ?? [];
        if (!in_array($student['arm'] ?? '', $allowed_arms, true)) {
            $student = null;
        }
    }

    $subjects_results = [];
    try {
        $rs = $pdo->prepare("SELECT * FROM results WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? ORDER BY subject_name ASC");
        $rs->execute([$school_uuid, $view_uuid, $rc_session, $rc_term]);
        $subjects_results = $rs->fetchAll();
    } catch(Exception $e){}

    $domain_ratings = ['Affective' => [], 'Psychomotor' => []];
    try {
        $dr = $pdo->prepare("SELECT domain_type, trait_name, rating FROM student_domain_ratings WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=?");
        $dr->execute([$school_uuid, $view_uuid, $rc_session, $rc_term]);
        foreach ($dr->fetchAll() as $d) $domain_ratings[$d['domain_type']][$d['trait_name']] = $d['rating'];
    } catch(Exception $e){}

    $saved_card = null;
    try {
        $rc2 = $pdo->prepare("SELECT * FROM report_cards WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? LIMIT 1");
        $rc2->execute([$school_uuid, $view_uuid, $rc_session, $rc_term]);
        $saved_card = $rc2->fetch() ?: null;
    } catch(Exception $e){}

    $totals   = array_column($subjects_results, 'total_score');
    $grand    = array_sum($totals);
    $avg      = count($totals) ? round($grand / count($totals), 1) : 0;
    $gpa      = $grading->gpa($totals);
    $hlth     = json_decode($student['healthcare_json'] ?? '{}', true) ?: [];

    // Assessment columns for this session/term, live from Settings →
    // Assessment Configuration — no hardcoded CA1/CA2/Exam fallback.
    $assess_cols = getAssessmentColumns($pdo, $school_uuid, $rc_session, $rc_term, $student['class'] ?? $rc_class);
    $dynamic_scores = $assess_cols['dynamic'] ? getStudentDynamicScores($pdo, $school_uuid, $view_uuid, $rc_session, $rc_term) : [];

    // Attendance for this term
    $att_present = 0; $att_total = 0;
    try {
        $atq = $pdo->prepare("SELECT COUNT(*) as days, SUM(status='Present') as present FROM attendance_records WHERE school_uuid=? AND student_uuid=?");
        $atq->execute([$school_uuid, $view_uuid]);
        $atr = $atq->fetch();
        $att_total = (int)$atr['days']; $att_present = (int)$atr['present'];
    } catch(Exception $e){}

    // Class position
    $class_pos = 0; $class_size = count($rc_students);
    try {
        $all_totals = [];
        $uuids_in_class = array_column($rc_students, 'student_uuid');
        if ($uuids_in_class) {
            $in = implode(',', array_fill(0, count($uuids_in_class), '?'));
            $pq = $pdo->prepare("SELECT student_uuid, SUM(total_score) as grand FROM results WHERE school_uuid=? AND session_name=? AND term_name=? AND class_name=? AND student_uuid IN ($in) GROUP BY student_uuid");
            $pq->execute(array_merge([$school_uuid, $rc_session, $rc_term, $rc_class], $uuids_in_class));
            foreach ($pq->fetchAll() as $pr) $all_totals[$pr['student_uuid']] = (float)$pr['grand'];
        }
        $positions = $grading->positions($all_totals);
        $class_pos = $positions[$view_uuid] ?? 0;
    } catch(Exception $e){}

    $affective_traits    = ['Punctuality','Neatness','Cooperation','Respect','Attentiveness','Leadership'];
    $psychomotor_traits  = ['Handwriting','Sports','Drawing','Craft','Music','Public Speaking'];
    $rating_labels = [1=>'Poor',2=>'Fair',3=>'Good',4=>'Very Good',5=>'Excellent'];

    if ($student):
?>
<!-- ── PRINT REPORT CARD ──────────────────────────────────────────────── -->
<style>
  @media print {
    body > div > div > div:first-child,
    body > div > div > div.flex-1 > header,
    .no-print { display: none !important; }
    body { background: white !important; color: black !important; font-size: 11px; }
    .print-card { box-shadow: none !important; border: 1px solid #ccc !important; }
  }
</style>

<div class="no-print flex items-center justify-between mb-4">
    <a href="dashboard.php?section=report_cards&rc_class=<?php echo urlencode($rc_class); ?>&rc_session=<?php echo urlencode($rc_session); ?>&rc_term=<?php echo urlencode($rc_term); ?>"
       class="text-xs text-[var(--text-secondary)] hover:text-white flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to class list
    </a>
    <div class="flex items-center gap-2">
        <?php if ($can_write && ($saved_card['status'] ?? '') !== 'Approved'): ?>
        <form method="POST" class="inline"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_approve_report_card" value="1">
            <input type="hidden" name="student_uuid" value="<?php echo htmlspecialchars($view_uuid); ?>">
            <input type="hidden" name="rc_session"   value="<?php echo htmlspecialchars($rc_session); ?>">
            <input type="hidden" name="rc_term"      value="<?php echo htmlspecialchars($rc_term); ?>">
            <button type="submit" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl">
                <i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>Approve
            </button>
        </form>
        <?php endif; ?>
        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl flex items-center gap-2">
            <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
        </button>
    </div>
</div>

<div class="print-card bg-white text-gray-900 rounded-2xl border border-gray-200 overflow-hidden shadow-2xl max-w-3xl mx-auto">

    <!-- School header -->
    <?php if ($report_card_config['show_letterhead']): ?>
    <div class="bg-gradient-to-r from-[<?php echo htmlspecialchars($school['theme_color']??'#4F46E5'); ?>] to-[<?php echo htmlspecialchars($school['theme_color']??'#4F46E5'); ?>]/80 text-white p-6 text-center">
        <div class="flex items-center justify-center gap-4">
            <?php if ($report_card_config['show_logo'] && !empty($school['logo_path'])): ?>
            <img src="<?php echo htmlspecialchars($school['logo_path']); ?>" class="w-16 h-16 object-contain rounded-xl bg-white/10 p-1">
            <?php endif; ?>
            <div>
                <h1 class="text-xl font-black tracking-wide"><?php echo htmlspecialchars($school['name'] ?? 'SCHOOL NAME'); ?></h1>
                <p class="text-xs opacity-80 mt-0.5"><?php echo htmlspecialchars($school_settings['address'] ?? ''); ?></p>
                <p class="text-[10px] opacity-70 mt-0.5 italic"><?php echo htmlspecialchars($school_settings['motto'] ?? ''); ?></p>
            </div>
        </div>
        <div class="mt-3 text-xs font-bold tracking-widest uppercase opacity-90">
            Academic Report — <?php echo htmlspecialchars($rc_term); ?>, <?php echo htmlspecialchars($rc_session); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="p-6 space-y-5">

        <!-- Bio row -->
        <div class="flex items-start justify-between gap-4 pb-4 border-b border-gray-200">
            <div class="flex items-start gap-4">
                <?php if ($report_card_config['show_photo'] && !empty($student['photo_path'])): ?>
                <img src="<?php echo htmlspecialchars(asset_url($student['photo_path'])); ?>" class="w-20 h-20 rounded-xl object-cover border-2 border-gray-200">
                <?php endif; ?>
                <div class="space-y-1 text-xs">
                    <div class="text-lg font-black text-gray-900"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div><span class="font-bold text-gray-500">Admission No:</span> <span class="font-mono font-bold"><?php echo htmlspecialchars($student['roll_number']); ?></span></div>
                    <div><span class="font-bold text-gray-500">Class:</span> <?php echo htmlspecialchars($student['class']); ?> <?php echo htmlspecialchars($student['arm']??''); ?></div>
                    <div><span class="font-bold text-gray-500">Gender:</span> <?php echo htmlspecialchars($student['gender']??'—'); ?></div>
                    <?php if ($report_card_config['show_healthcare']): ?>
                    <div><span class="font-bold text-gray-500">Blood Group:</span> <?php echo htmlspecialchars($hlth['blood_group']??'—'); ?> | <span class="font-bold text-gray-500">Genotype:</span> <?php echo htmlspecialchars($hlth['geno']??'—'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right text-xs space-y-1">
                <?php if ($report_card_config['show_position'] && $class_pos > 0): ?>
                <div class="text-2xl font-black text-indigo-600"><?php echo GradingEngine::ordinal($class_pos); ?></div>
                <div class="text-gray-500">of <?php echo $class_size; ?> students</div>
                <?php endif; ?>
                <div class="mt-2 px-3 py-1 bg-gray-100 rounded-xl font-mono font-bold text-gray-800">GPA: <?php echo $gpa; ?></div>
                <?php if ($report_card_config['show_attendance']): ?>
                <div class="text-gray-500">Attendance: <strong><?php echo $att_present; ?>/<?php echo $att_total; ?></strong></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Academic results table -->
        <div>
            <h3 class="text-xs font-black text-gray-700 uppercase tracking-widest mb-2">Academic Performance</h3>
            <?php if (!$assess_cols['configured']): ?>
            <p class="text-[10px] text-amber-600 italic mb-2">No assessment configuration found for <?php echo htmlspecialchars($rc_term); ?>, <?php echo htmlspecialchars($rc_session); ?> — scores below show Subject/Total/Grade only. Set one up in Settings → Assessment Configuration.</p>
            <?php endif; ?>
            <table class="w-full text-xs border border-gray-200 rounded-xl overflow-hidden">
                <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] font-mono">
                    <tr>
                        <th class="py-2 px-3 text-left">Subject</th>
                        <?php foreach ($assess_cols['columns'] as $col): ?>
                        <th class="py-2 px-3 text-center"><?php echo htmlspecialchars($col['label']); ?><?php echo $col['max'] !== null ? ' /' . (int)$col['max'] : ''; ?></th>
                        <?php endforeach; ?>
                        <th class="py-2 px-3 text-center font-black">Total</th>
                        <th class="py-2 px-3 text-center">Grade</th>
                        <th class="py-2 px-3 text-left">Remark</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($subjects_results)): ?>
                    <tr><td colspan="<?php echo 3 + count($assess_cols['columns']); ?>" class="py-6 text-center text-gray-400 italic">No results entered yet.</td></tr>
                <?php else: foreach ($subjects_results as $r):
                    $g = $grading->grade((float)$r['total_score']);
                    $gcol = $g['grade'][0]==='A'?'text-green-700':($g['grade'][0]==='F'?'text-red-600':'text-amber-700');
                ?>
                    <tr>
                        <td class="py-2 px-3 font-bold"><?php echo htmlspecialchars($r['subject_name']); ?></td>
                        <?php foreach ($assess_cols['columns'] as $col): ?>
                        <td class="py-2 px-3 text-center font-mono"><?php
                            if ($assess_cols['dynamic']) {
                                echo (float)($dynamic_scores[$r['subject_name']][$col['key']] ?? 0);
                            } else {
                                echo (float)($r[$col['key']] ?? 0);
                            }
                        ?></td>
                        <?php endforeach; ?>
                        <td class="py-2 px-3 text-center font-black text-gray-900"><?php echo (float)$r['total_score']; ?></td>
                        <td class="py-2 px-3 text-center font-black <?php echo $gcol; ?>"><?php echo htmlspecialchars($r['grade']); ?></td>
                        <td class="py-2 px-3 text-gray-500 italic text-[10px]"><?php echo htmlspecialchars($r['subject_teacher_remark'] ?: $g['remark']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($subjects_results)): ?>
                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                    <tr>
                        <td class="py-2 px-3 uppercase text-[10px] text-gray-600">Total / Average</td>
                        <td colspan="<?php echo count($assess_cols['columns']); ?>"></td>
                        <td class="py-2 px-3 text-center text-gray-900"><?php echo $grand; ?></td>
                        <td class="py-2 px-3 text-center text-indigo-700"><?php echo $avg; ?>%</td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Affective & Psychomotor domains -->
        <div class="grid grid-cols-2 gap-4">
            <?php foreach (['Affective' => $affective_traits, 'Psychomotor' => $psychomotor_traits] as $dtype => $traits): ?>
            <div>
                <h3 class="text-[10px] font-black text-gray-600 uppercase tracking-widest mb-2"><?php echo $dtype; ?> Domain</h3>
                <table class="w-full text-[10px] border border-gray-200 rounded-xl overflow-hidden">
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach ($traits as $trait):
                        $r = (int)($domain_ratings[$dtype][$trait] ?? 3);
                    ?>
                    <tr>
                        <td class="py-1.5 px-2 font-semibold text-gray-700"><?php echo $trait; ?></td>
                        <td class="py-1.5 px-2 text-right">
                            <span class="font-bold <?php echo $r>=4?'text-green-700':($r<=2?'text-red-600':'text-amber-700'); ?>">
                                <?php echo $rating_labels[$r] ?? 'Good'; ?>
                            </span>
                        </td>
                        <td class="py-1.5 px-2 text-gray-400">
                            <?php for($i=1;$i<=5;$i++) echo $i<=$r ? '●' : '○'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Comments -->
        <?php if ($report_card_config['show_teacher_comment'] || $report_card_config['show_principal_comment']): ?>
        <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-200">
            <?php if ($report_card_config['show_teacher_comment']): ?>
            <div>
                <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">Class Teacher's Comment</div>
                <?php if ($can_write): ?>
                <form method="POST"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_save_report_comment" value="1">
                    <input type="hidden" name="student_uuid"  value="<?php echo htmlspecialchars($view_uuid); ?>">
                    <input type="hidden" name="rc_session"    value="<?php echo htmlspecialchars($rc_session); ?>">
                    <input type="hidden" name="rc_term"       value="<?php echo htmlspecialchars($rc_term); ?>">
                    <input type="hidden" name="comment_type"  value="teacher">
                    <textarea name="comment_text" rows="2" class="w-full text-xs border border-gray-200 rounded-xl p-2 text-gray-800 bg-gray-50 focus:border-indigo-400 focus:outline-none"
                        placeholder="Enter class teacher's remark…"><?php echo htmlspecialchars($saved_card['teacher_comment'] ?? ''); ?></textarea>
                    <button type="submit" class="mt-1 px-3 py-1 bg-indigo-600 text-white text-[10px] font-bold rounded-lg">Save</button>
                </form>
                <?php else: ?>
                <p class="text-xs text-gray-600 italic border border-gray-200 rounded-xl p-2 min-h-[3rem]">
                    <?php echo htmlspecialchars($saved_card['teacher_comment'] ?? '—'); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($report_card_config['show_principal_comment'] && $active_role === 'School Admin'): ?>
            <div>
                <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">Principal's Comment</div>
                <form method="POST"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_save_report_comment" value="1">
                    <input type="hidden" name="student_uuid" value="<?php echo htmlspecialchars($view_uuid); ?>">
                    <input type="hidden" name="rc_session"   value="<?php echo htmlspecialchars($rc_session); ?>">
                    <input type="hidden" name="rc_term"      value="<?php echo htmlspecialchars($rc_term); ?>">
                    <input type="hidden" name="comment_type" value="principal">
                    <textarea name="comment_text" rows="2" class="w-full text-xs border border-gray-200 rounded-xl p-2 text-gray-800 bg-gray-50 focus:border-indigo-400 focus:outline-none"
                        placeholder="Enter principal's remark…"><?php echo htmlspecialchars($saved_card['principal_comment'] ?? ''); ?></textarea>
                    <button type="submit" class="mt-1 px-3 py-1 bg-emerald-600 text-white text-[10px] font-bold rounded-lg">Save</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Signature line -->
        <?php if ($report_card_config['show_signature']): ?>
        <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-200 text-center text-[10px] text-gray-500">
            <div><div class="border-t border-gray-400 mt-8 pt-1">Class Teacher</div></div>
            <div><div class="border-t border-gray-400 mt-8 pt-1">Principal / Head Teacher</div></div>
            <div><div class="border-t border-gray-400 mt-8 pt-1">Date Issued</div></div>
        </div>
        <?php endif; ?>

        <!-- Status badge -->
        <div class="text-right">
            <span class="text-[9px] font-mono text-gray-400">
                Status:
                <strong class="<?php echo ($saved_card['status']??'') === 'Approved' ? 'text-green-600' : 'text-amber-600'; ?>">
                    <?php echo htmlspecialchars($saved_card['status'] ?? 'Pending Approval'); ?>
                </strong>
                &nbsp;·&nbsp; <?php echo htmlspecialchars($student['student_uuid']); ?>
            </span>
        </div>
    </div>
</div>

<?php else: ?>
<div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-6 text-xs text-rose-400">Student not found.</div>
<?php endif;
    return; // don't render class list below when viewing single card
}
// ── End single card view ──────────────────────────────────────────────────
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="award" class="w-5 h-5 text-amber-400"></i> Report Cards
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo count($rc_students); ?> students in <?php echo htmlspecialchars($rc_class); ?></p>
        </div>
        <a href="dashboard.php?section=broadsheet&filter_class=<?php echo urlencode($rc_class); ?>&filter_term=<?php echo urlencode($rc_term); ?>&filter_sess=<?php echo urlencode($rc_session); ?>"
           class="px-4 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] text-xs font-bold rounded-xl hover:text-white flex items-center gap-2">
            <i data-lucide="table-2" class="w-4 h-4 text-indigo-400"></i> Class Broadsheet
        </a>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="report_cards">
        <select name="rc_class" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <?php foreach ($visible_roster_classes as $cl): ?>
                <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $rc_class===$cl?'selected':''; ?>><?php echo htmlspecialchars($cl); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="rc_term" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <?php foreach ($terms as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $rc_term===$t?'selected':''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="rc_session" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <?php foreach ($sessions as $s): ?>
                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $rc_session===$s?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($rc_students)): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
        No active students in <?php echo htmlspecialchars($rc_class); ?>.
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($rc_students as $rs):
            $rc_link = "dashboard.php?section=report_cards&rc_class=" . urlencode($rc_class)
                . "&rc_session=" . urlencode($rc_session)
                . "&rc_term=" . urlencode($rc_term)
                . "&view=" . urlencode($rs['student_uuid']);
            $card_status = 'Pending';
            try {
                $cs = $pdo->prepare("SELECT status FROM report_cards WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? LIMIT 1");
                $cs->execute([$school_uuid, $rs['student_uuid'], $rc_session, $rc_term]);
                $card_status = $cs->fetchColumn() ?: 'Pending';
            } catch(Exception $e){}
        ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] hover:border-indigo-500/40 rounded-2xl p-5 space-y-3 transition-all group">
            <div class="flex items-center gap-3">
                <?php if (!empty($rs['photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars(asset_url($rs['photo_path'])); ?>" class="w-10 h-10 rounded-full object-cover border border-[var(--border-color)]">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-black"
                         style="background:<?php echo htmlspecialchars($school['theme_color']??'#4F46E5'); ?>">
                        <?php echo strtoupper(substr($rs['name'],0,2)); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="font-bold text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($rs['name']); ?></div>
                    <div class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($rs['roll_number']); ?></div>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold
                    <?php echo $card_status==='Approved'?'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20':'bg-amber-500/10 text-amber-400 border border-amber-500/20'; ?>">
                    <?php echo htmlspecialchars($card_status); ?>
                </span>
                <a href="<?php echo htmlspecialchars($rc_link); ?>"
                   class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-bold rounded-lg flex items-center gap-1">
                    <i data-lucide="eye" class="w-3 h-3"></i> View / Print
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
