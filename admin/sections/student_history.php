<?php
/**
 * SECTION: Student History Timeline (Phase E)
 * Pulls a student's results, attendance summary, disciplinary records, and
 * career advisory notes into one chronological view.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Student History Timeline' => null]);

$sh_class = $_GET['sh_class'] ?? '';
$sh_student = $_GET['sh_student'] ?? '';

$students = [];
if ($sh_class !== '') {
    $sq = $pdo->prepare("SELECT student_uuid, name FROM students WHERE school_uuid=? AND class=? ORDER BY name ASC");
    $sq->execute([$school_uuid, $sh_class]);
    $students = $sq->fetchAll();
}

$timeline = [];
$student_info = null;
if ($sh_student !== '') {
    $siq = $pdo->prepare("SELECT * FROM students WHERE student_uuid=? AND school_uuid=?");
    $siq->execute([$sh_student, $school_uuid]);
    $student_info = $siq->fetch();

    if ($student_info) {
        if (!empty($student_info['date_enrolled'] ?? $student_info['created_at'] ?? null)) {
            $timeline[] = ['date' => $student_info['date_enrolled'] ?? $student_info['created_at'], 'type' => 'Enrollment', 'icon' => 'log-in', 'color' => 'emerald', 'text' => 'Enrolled at the school' . (!empty($student_info['class']) ? ' — ' . $student_info['class'] : '')];
        }

        try {
            $rq = $pdo->prepare("SELECT session_name, term_name, subject_name, total_score, grade, created_at FROM results WHERE school_uuid=? AND student_uuid=? ORDER BY created_at DESC LIMIT 100");
            $rq->execute([$school_uuid, $sh_student]);
            $bySessionTerm = [];
            foreach ($rq->fetchAll() as $r) $bySessionTerm[$r['session_name'] . '|' . $r['term_name']][] = $r;
            foreach ($bySessionTerm as $key => $rows) {
                [$sess, $term] = explode('|', $key);
                $avg = round(array_sum(array_column($rows, 'total_score')) / count($rows), 1);
                $timeline[] = ['date' => $rows[0]['created_at'], 'type' => 'Results', 'icon' => 'bar-chart-2', 'color' => 'sky', 'text' => "$sess $term — " . count($rows) . " subjects, average $avg"];
            }
        } catch (Exception $e) {}

        try {
            $bq = $pdo->prepare("SELECT * FROM student_behavior_records WHERE school_uuid=? AND student_uuid=? ORDER BY recorded_at DESC LIMIT 50");
            $bq->execute([$school_uuid, $sh_student]);
            foreach ($bq->fetchAll() as $b) {
                $timeline[] = ['date' => $b['recorded_at'], 'type' => 'Disciplinary', 'icon' => 'alert-triangle', 'color' => 'rose', 'text' => htmlspecialchars($b['title']) . (!empty($b['action_taken']) ? ' — ' . htmlspecialchars($b['action_taken']) : '')];
            }
        } catch (Exception $e) {}

        try {
            $cq = $pdo->prepare("SELECT * FROM career_advisory_notes WHERE school_uuid=? AND student_uuid=?");
            $cq->execute([$school_uuid, $sh_student]);
            $ca = $cq->fetch();
            if ($ca) $timeline[] = ['date' => $ca['updated_at'], 'type' => 'Career Advisory', 'icon' => 'compass', 'color' => 'teal', 'text' => 'Advisory note updated' . (!empty($ca['recommended_paths']) ? ' — ' . htmlspecialchars(mb_strimwidth($ca['recommended_paths'],0,80,'…')) : '')];
        } catch (Exception $e) {}

        try {
            $aq = $pdo->prepare("SELECT status, COUNT(*) n FROM attendance_records WHERE school_uuid=? AND student_uuid=? GROUP BY status");
            $aq->execute([$school_uuid, $sh_student]);
            $att = $aq->fetchAll();
            if ($att) {
                $summary = implode(', ', array_map(fn($a) => "{$a['n']} {$a['status']}", $att));
                $timeline[] = ['date' => date('Y-m-d'), 'type' => 'Attendance', 'icon' => 'calendar-check', 'color' => 'amber', 'text' => "All-time attendance: $summary"];
            }
        } catch (Exception $e) {}

        usort($timeline, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
    }
}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="history" class="w-6 h-6 text-fuchsia-400"></i><span>Student History Timeline</span>
    </h1>

    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="section" value="student_history">
        <div>
            <label class="block text-[10px] font-bold uppercase mb-1">Class</label>
            <select name="sh_class" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <option value="">— Select Class —</option>
                <?php foreach (($roster_classes ?? []) as $cl): ?>
                <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $sh_class === $cl ? 'selected' : ''; ?>><?php echo htmlspecialchars($cl); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($students): ?>
        <div>
            <label class="block text-[10px] font-bold uppercase mb-1">Student</label>
            <select name="sh_student" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <option value="">— Select Student —</option>
                <?php foreach ($students as $st): ?>
                <option value="<?php echo htmlspecialchars($st['student_uuid']); ?>" <?php echo $sh_student === $st['student_uuid'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($student_info): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
        <h3 class="text-sm font-bold text-[var(--text-primary)] mb-4"><?php echo htmlspecialchars($student_info['name']); ?> — Full History</h3>
        <?php if (empty($timeline)): ?>
        <p class="text-xs italic text-[var(--text-secondary)]">No history recorded yet.</p>
        <?php else: ?>
        <div class="space-y-3 border-l-2 border-[var(--border-color)] pl-4">
            <?php foreach ($timeline as $ev): ?>
            <div class="relative">
                <span class="absolute -left-[22px] top-1 w-3 h-3 rounded-full bg-<?php echo $ev['color']; ?>-500"></span>
                <p class="text-[10px] text-[var(--text-secondary)]"><?php echo date('M j, Y', strtotime($ev['date'])); ?> · <span class="font-bold text-<?php echo $ev['color']; ?>-400"><?php echo htmlspecialchars($ev['type']); ?></span></p>
                <p class="text-xs text-[var(--text-primary)]"><?php echo $ev['text']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
