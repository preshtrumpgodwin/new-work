<?php
/**
 * SECTION: Transcript Generator (Phase C)
 * Pulls every result row on file for a student across all sessions/terms
 * and lays it out as a print-ready academic transcript.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Transcript Generator' => null]);

$tr_class = $_GET['tr_class'] ?? '';
$tr_student = $_GET['tr_student'] ?? '';

$students = [];
if ($tr_class !== '') {
    $sq = $pdo->prepare("SELECT student_uuid, name FROM students WHERE school_uuid=? AND class=? ORDER BY name ASC");
    $sq->execute([$school_uuid, $tr_class]);
    $students = $sq->fetchAll();
}

$rows = [];
$student_info = null;
if ($tr_student !== '') {
    $rq = $pdo->prepare("SELECT * FROM results WHERE school_uuid=? AND student_uuid=? ORDER BY session_name, term_name, subject_name");
    $rq->execute([$school_uuid, $tr_student]);
    $rows = $rq->fetchAll();
    $siq = $pdo->prepare("SELECT * FROM students WHERE student_uuid=? AND school_uuid=?");
    $siq->execute([$tr_student, $school_uuid]);
    $student_info = $siq->fetch();
}

// Group by session > term
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['session_name']][$r['term_name']][] = $r;
}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="file-text" class="w-6 h-6 text-sky-400"></i><span>Transcript Generator</span>
    </h1>

    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="section" value="transcripts">
        <div>
            <label class="block text-[10px] font-bold uppercase mb-1">Class</label>
            <select name="tr_class" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <option value="">— Select Class —</option>
                <?php foreach (($roster_classes ?? []) as $cl): ?>
                <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $tr_class === $cl ? 'selected' : ''; ?>><?php echo htmlspecialchars($cl); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($students): ?>
        <div>
            <label class="block text-[10px] font-bold uppercase mb-1">Student</label>
            <select name="tr_student" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <option value="">— Select Student —</option>
                <?php foreach ($students as $st): ?>
                <option value="<?php echo htmlspecialchars($st['student_uuid']); ?>" <?php echo $tr_student === $st['student_uuid'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($student_info): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($student_info['name']); ?> — Full Academic Transcript</h3>
            <a href="print_transcript.php?student_uuid=<?php echo urlencode($tr_student); ?>" target="_blank" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl">Print Transcript</a>
        </div>
        <?php if (empty($grouped)): ?>
        <p class="text-xs italic text-[var(--text-secondary)]">No results on file for this student yet.</p>
        <?php endif; ?>
        <?php foreach ($grouped as $session => $terms): ?>
        <div class="border border-[var(--border-color)] rounded-xl p-4">
            <h4 class="text-xs font-bold text-[var(--text-secondary)] uppercase mb-2"><?php echo htmlspecialchars($session); ?></h4>
            <?php foreach ($terms as $term => $subjects): ?>
            <p class="text-[11px] font-bold text-[var(--text-primary)] mt-2"><?php echo htmlspecialchars($term); ?></p>
            <table class="w-full text-xs mt-1">
                <thead class="text-[var(--text-secondary)] text-[10px] uppercase"><tr><th class="text-left py-1">Subject</th><th class="text-left py-1">Total</th><th class="text-left py-1">Grade</th></tr></thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                    <tr><td class="py-1 text-[var(--text-primary)]"><?php echo htmlspecialchars($s['subject_name']); ?></td><td class="py-1 text-[var(--text-secondary)]"><?php echo htmlspecialchars($s['total_score']); ?></td><td class="py-1 text-[var(--text-secondary)]"><?php echo htmlspecialchars($s['grade'] ?? '—'); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
