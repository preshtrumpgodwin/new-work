<?php
/**
 * SECTION: Student Career Advisory (Phase D)
 * A counselor/teacher records recommended career paths, strengths, and
 * notes per student — visible to the student/parent on their portal.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Career Advisory' => null]);

$ca_class = $_GET['ca_class'] ?? '';
$ca_students = [];
if ($ca_class !== '') {
    try {
        $q = $pdo->prepare("SELECT s.*, n.note_uuid, n.recommended_paths, n.strengths, n.counselor_notes
            FROM students s LEFT JOIN career_advisory_notes n ON n.student_uuid = s.student_uuid
            WHERE s.school_uuid=? AND s.class=? AND s.status='Active' ORDER BY s.name ASC");
        $q->execute([$school_uuid, $ca_class]);
        $ca_students = $q->fetchAll();
    } catch (Exception $e) {}
}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="compass" class="w-6 h-6 text-teal-400"></i><span>Student Career Advisory</span>
    </h1>

    <form method="GET" class="flex gap-3">
        <input type="hidden" name="section" value="career_advisory">
        <select name="ca_class" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            <option value="">— Select Class —</option>
            <?php foreach (($roster_classes ?? []) as $cl): ?>
            <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $ca_class === $cl ? 'selected' : ''; ?>><?php echo htmlspecialchars($cl); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($ca_class !== ''): ?>
    <div class="space-y-4">
        <?php foreach ($ca_students as $st): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5">
            <h3 class="text-sm font-bold text-[var(--text-primary)] mb-3"><?php echo htmlspecialchars($st['name']); ?></h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_career_advisory" value="1">
                <input type="hidden" name="student_uuid" value="<?php echo htmlspecialchars($st['student_uuid']); ?>">
                <div><label class="block text-[10px] font-bold uppercase mb-1">Recommended Paths</label><textarea name="recommended_paths" rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-xs text-[var(--text-primary)]" placeholder="e.g. Engineering, Medicine, Computer Science"><?php echo htmlspecialchars($st['recommended_paths'] ?? ''); ?></textarea></div>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Strengths</label><textarea name="strengths" rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-xs text-[var(--text-primary)]" placeholder="e.g. Strong in Mathematics & Physics"><?php echo htmlspecialchars($st['strengths'] ?? ''); ?></textarea></div>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Counselor Notes</label><textarea name="counselor_notes" rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($st['counselor_notes'] ?? ''); ?></textarea></div>
                <div class="md:col-span-3"><button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white text-[10px] font-bold px-4 py-1.5 rounded-lg">Save</button></div>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($ca_students)): ?><p class="text-xs italic text-[var(--text-secondary)]">No active students in this class.</p><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
