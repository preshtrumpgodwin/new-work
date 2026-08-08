<?php
/**
 * SECTION: Broadsheet — Phase 6 rebuild.
 *
 * Previously queried a table that never existed (`student_results` with
 * columns `term`/`session`) — every load silently caught the exception and
 * showed "No results entered yet" no matter what was actually in the
 * database. Fixed to query the real `results` table.
 *
 * Visibility: full access (or School Admin/Platform Manager) sees every
 * subject. write/read access only sees the subjects that staff member is
 * assigned to teach (staff_subject_assignments) for the current session/term.
 */
render_breadcrumb([
    'Dashboard'    => 'dashboard.php?section=overview',
    'Report Cards' => 'dashboard.php?section=report_cards',
    'Broadsheet'   => null,
]);

// Hard server-side gate — the sidebar link is already hidden for anyone
// without real Broadsheet access, but that's just UI; enforce it here too
// so navigating straight to ?section=broadsheet can't bypass it. Holding
// Results or Report Cards access (including via the class/subject teacher
// auto-grants) does NOT imply Broadsheet access — it's granted separately.
$broadsheet_access = feature_access('broadsheet');
if ($broadsheet_access === 'hide') {
?>
<div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-6 text-xs text-rose-400">
    You don't have access to the Class Broadsheet. If you believe this is a mistake, ask your School Admin to grant it under Roles &amp; Permissions.
</div>
<?php
    return;
}

$can_write = can_manage($active_role, $current_access);
$is_full_view = in_array($active_role, ['School Admin','Platform Manager']) || can_approve($active_role, $current_access ?? 'hide');

$filter_class = safe_str($_GET['filter_class'] ?? (($roster_classes[0] ?? 'JSS1')));
$filter_term  = safe_str($_GET['filter_term']  ?? ($school_settings['current_term']    ?? ''));
$filter_sess  = safe_str($_GET['filter_sess']  ?? ($school_settings['current_session'] ?? ''));

// Subjects this viewer is allowed to see, if not full-access.
$allowed_subjects = null; // null = no restriction (full access)
if (!$is_full_view) {
    $su = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
    $su->execute([$user_uuid, $school_uuid]);
    $my_staff_uuid = $su->fetchColumn() ?: '';
    $allowed_subjects = getTeacherSubjects($pdo, $my_staff_uuid, $school_uuid, $filter_class, $filter_sess, $filter_term);
}

// Fetch all students in the chosen class
$students_in_class = [];
try {
    $st = $pdo->prepare("SELECT student_uuid, name FROM students WHERE school_uuid=? AND class=? AND status='Active' ORDER BY name ASC");
    $st->execute([$school_uuid, $filter_class]);
    $students_in_class = $st->fetchAll();
} catch (Exception $e) {}

// Distinct subjects that have results for this class/term/session (real table + columns)
$subjects_done = [];
try {
    $sj = $pdo->prepare("SELECT DISTINCT subject_name FROM results WHERE school_uuid=? AND class_name=? AND term_name=? AND session_name=? ORDER BY subject_name ASC");
    $sj->execute([$school_uuid, $filter_class, $filter_term, $filter_sess]);
    $subjects_done = $sj->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Apply subject visibility scoping for non-full-access viewers
$visible_subjects = $allowed_subjects === null ? $subjects_done : array_values(array_intersect($subjects_done, $allowed_subjects));

// Index results: student_uuid → subject → total_score (real table + columns)
$results_index = [];
if (!empty($subjects_done) && !empty($students_in_class)) {
    try {
        $in = implode(',', array_fill(0, count($students_in_class), '?'));
        $uuids = array_column($students_in_class, 'student_uuid');
        $rs = $pdo->prepare("SELECT student_uuid, subject_name, total_score FROM results
            WHERE school_uuid=? AND class_name=? AND term_name=? AND session_name=? AND student_uuid IN ($in)");
        $rs->execute(array_merge([$school_uuid, $filter_class, $filter_term, $filter_sess], $uuids));
        foreach ($rs->fetchAll() as $r) {
            $results_index[$r['student_uuid']][$r['subject_name']] = (float)$r['total_score'];
        }
    } catch (Exception $e) {}
}

// Totals & positions are always computed across ALL subjects (not just the
// viewer's visible subset) so class rank stays accurate regardless of who's
// looking — only which subject *columns* are shown is restricted.
$student_totals = [];
foreach ($students_in_class as $std) {
    $uuid  = $std['student_uuid'];
    $total = 0;
    foreach ($subjects_done as $subj) {
        $total += $results_index[$uuid][$subj] ?? 0;
    }
    $student_totals[$uuid] = $total;
}
arsort($student_totals);
$positions = [];
$rank = 1; $prev_total = null; $count = 0;
foreach ($student_totals as $uuid => $tot) {
    if ($tot !== $prev_total) {
        $rank = $count + 1;
    }
    $positions[$uuid] = $rank;
    $prev_total = $tot;
    $count++;
}

// Session/term lists
$sessions = []; $terms = [];
try {
    $sq = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid=? ORDER BY id DESC");
    $sq->execute([$school_uuid]); $sessions = $sq->fetchAll(PDO::FETCH_COLUMN);
    $tq = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid=? ORDER BY id ASC");
    $tq->execute([$school_uuid]); $terms = $tq->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="table-2" class="w-5 h-5 text-indigo-400"></i> Class Broadsheet
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">
                <?php echo htmlspecialchars($filter_class); ?> &nbsp;·&nbsp;
                <?php echo htmlspecialchars($filter_term); ?> &nbsp;·&nbsp;
                <?php echo htmlspecialchars($filter_sess); ?>
                <?php if (!$is_full_view): ?>
                    <span class="ml-2 px-2 py-0.5 bg-amber-500/10 text-amber-400 rounded text-[10px] font-bold">Showing only your assigned subject(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <button onclick="window.print()"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl flex items-center gap-2">
            <i data-lucide="printer" class="w-4 h-4"></i> Print Broadsheet
        </button>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="broadsheet">
        <select name="filter_class" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <?php foreach ($roster_classes as $cl): ?>
                <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $filter_class===$cl?'selected':''; ?>><?php echo htmlspecialchars($cl); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_term" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <?php foreach ($terms as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filter_term===$t?'selected':''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_sess" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <?php foreach ($sessions as $s): ?>
                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $filter_sess===$s?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl">Load</button>
    </form>

    <!-- Broadsheet table -->
    <?php if (empty($students_in_class)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
            No active students found for <?php echo htmlspecialchars($filter_class); ?>.
        </div>
    <?php elseif (empty($subjects_done)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
            No results entered yet for <?php echo htmlspecialchars($filter_class); ?> — <?php echo htmlspecialchars($filter_term); ?>.
        </div>
    <?php elseif (empty($visible_subjects)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-10 text-center text-[var(--text-secondary)] text-xs italic">
            Results exist for this class, but none of them are for a subject you're assigned to teach this term.
        </div>
    <?php else: ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-[10px]">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono tracking-wider border-b border-[var(--border-color)]">
                <tr>
                    <th class="p-3 sticky left-0 bg-[var(--bg-tertiary)] z-10 min-w-[160px]">Student</th>
                    <?php foreach ($visible_subjects as $subj): ?>
                        <th class="p-3 text-center whitespace-nowrap">
                            <?php echo htmlspecialchars($subj); ?>
                            <?php if ($can_write): ?>
                            <a href="dashboard.php?section=results&res_class=<?php echo urlencode($filter_class); ?>&res_subject=<?php echo urlencode($subj); ?>&res_term=<?php echo urlencode($filter_term); ?>&res_session=<?php echo urlencode($filter_sess); ?>" class="block text-indigo-400 normal-case font-normal text-[9px] mt-0.5">Enter/Edit →</a>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <?php if (!$is_full_view && count($visible_subjects) < count($subjects_done)): ?>
                        <th class="p-3 text-center whitespace-nowrap text-[var(--text-secondary)] italic">+<?php echo count($subjects_done) - count($visible_subjects); ?> other subject(s)</th>
                    <?php endif; ?>
                    <th class="p-3 text-center font-black text-indigo-400">Total</th>
                    <th class="p-3 text-center font-black text-amber-400">Avg %</th>
                    <th class="p-3 text-center font-black text-emerald-400">Position</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php
            // Re-sort students by position for display
            $sorted_students = $students_in_class;
            usort($sorted_students, fn($a,$b) => ($positions[$a['student_uuid']]??99) - ($positions[$b['student_uuid']]??99));
            foreach ($sorted_students as $idx => $std):
                $uuid  = $std['student_uuid'];
                $total = $student_totals[$uuid] ?? 0;
                $avg   = count($subjects_done) > 0 ? round($total / count($subjects_done), 1) : 0;
                $pos   = $positions[$uuid] ?? '-';
                $rowBg = $idx % 2 === 0 ? '' : 'bg-[var(--bg-tertiary)]/30';
            ?>
            <tr class="hover:bg-indigo-500/5 transition-colors <?php echo $rowBg; ?>">
                <td class="p-3 font-bold sticky left-0 bg-[var(--bg-secondary)] z-10 border-r border-[var(--border-color)]">
                    <?php echo htmlspecialchars($std['name']); ?>
                </td>
                <?php foreach ($visible_subjects as $subj):
                    $score = $results_index[$uuid][$subj] ?? null;
                    $color = 'text-[var(--text-secondary)]';
                    if ($score !== null) {
                        if ($score >= 70) $color = 'text-emerald-400 font-bold';
                        elseif ($score >= 50) $color = 'text-amber-400';
                        else $color = 'text-rose-400';
                    }
                ?>
                <td class="p-3 text-center <?php echo $color; ?>">
                    <?php echo $score !== null ? $score : '<span class="text-[var(--border-color)]">—</span>'; ?>
                </td>
                <?php endforeach; ?>
                <?php if (!$is_full_view && count($visible_subjects) < count($subjects_done)): ?>
                    <td class="p-3 text-center text-[var(--text-secondary)]">—</td>
                <?php endif; ?>
                <td class="p-3 text-center font-black text-indigo-400"><?php echo $total; ?></td>
                <td class="p-3 text-center font-bold <?php echo $avg>=70?'text-emerald-400':($avg>=50?'text-amber-400':'text-rose-400'); ?>">
                    <?php echo $avg; ?>%
                </td>
                <td class="p-3 text-center">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[10px] font-black
                        <?php echo $pos<=3?'bg-amber-500/20 text-amber-400 border border-amber-500/30':'bg-[var(--bg-tertiary)] text-[var(--text-secondary)]'; ?>">
                        <?php echo $pos; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-[var(--bg-tertiary)] border-t-2 border-indigo-500/20 text-[10px] font-mono font-bold text-[var(--text-secondary)]">
                <tr>
                    <td class="p-3 sticky left-0 bg-[var(--bg-tertiary)]">Class Average</td>
                    <?php foreach ($visible_subjects as $subj):
                        $scores = array_filter(array_column($results_index, $subj));
                        $cavg   = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
                    ?>
                    <td class="p-3 text-center text-indigo-400"><?php echo $cavg; ?></td>
                    <?php endforeach; ?>
                    <?php if (!$is_full_view && count($visible_subjects) < count($subjects_done)): ?><td class="p-3 text-center">—</td><?php endif; ?>
                    <td class="p-3 text-center">—</td>
                    <td class="p-3 text-center">—</td>
                    <td class="p-3 text-center">—</td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    @media print {
        .no-print, nav, header, aside { display: none !important; }
        body { background: white !important; color: black !important; }
        table { font-size: 9px; }
    }
</style>
