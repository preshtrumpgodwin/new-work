<?php
/**
 * SECTION: Staff Attendance (Phase E)
 * Simple daily clock-in/out per staff member, plus an admin summary view.
 * Complements the gate scanner's Check-In/Check-Out log (that one is
 * event-based; this one is a per-day roll-up admins can mark/adjust).
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Staff Attendance' => null]);

$sa_date = $_GET['sa_date'] ?? date('Y-m-d');
$sa_rows = [];
try {
    $q = $pdo->prepare("SELECT s.staff_uuid, s.name, s.role, a.clock_in, a.clock_out, a.status, a.record_uuid
        FROM staff s LEFT JOIN staff_attendance a ON a.staff_uuid = s.staff_uuid AND a.date = ? AND a.school_uuid = s.school_uuid
        WHERE s.school_uuid=? AND s.status='Active' ORDER BY s.name ASC");
    $q->execute([$sa_date, $school_uuid]);
    $sa_rows = $q->fetchAll();
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="calendar-check" class="w-6 h-6 text-amber-400"></i><span>Staff Attendance</span>
    </h1>

    <form method="GET" class="flex gap-3 items-end">
        <input type="hidden" name="section" value="staff_attendance">
        <div><label class="block text-[10px] font-bold uppercase mb-1">Date</label><input type="date" name="sa_date" value="<?php echo htmlspecialchars($sa_date); ?>" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
    </form>

    <?php if (in_array($active_role, ['School Admin','Platform Manager'])): ?>
    <form method="POST" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action_save_staff_attendance_batch" value="1">
        <input type="hidden" name="date" value="<?php echo htmlspecialchars($sa_date); ?>">
        <table class="w-full text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-[10px] uppercase">
                <tr><th class="text-left p-3">Staff</th><th class="text-left p-3">Status</th><th class="text-left p-3">Clock In</th><th class="text-left p-3">Clock Out</th></tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
                <?php foreach ($sa_rows as $r): ?>
                <tr>
                    <td class="p-3 font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($r['name']); ?> <span class="text-[var(--text-secondary)] font-normal">(<?php echo htmlspecialchars($r['role']); ?>)</span></td>
                    <td class="p-3">
                        <select name="status[<?php echo htmlspecialchars($r['staff_uuid']); ?>]" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-[10px] text-[var(--text-primary)]">
                            <?php foreach (['Present','Absent','Late','On Leave'] as $opt): ?>
                            <option <?php echo ($r['status'] ?? 'Present') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="p-3"><input type="time" name="clock_in[<?php echo htmlspecialchars($r['staff_uuid']); ?>]" value="<?php echo htmlspecialchars($r['clock_in'] ?? ''); ?>" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-[10px] text-[var(--text-primary)]"></td>
                    <td class="p-3"><input type="time" name="clock_out[<?php echo htmlspecialchars($r['staff_uuid']); ?>]" value="<?php echo htmlspecialchars($r['clock_out'] ?? ''); ?>" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-[10px] text-[var(--text-primary)]"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="p-4 border-t border-[var(--border-color)]"><button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold px-4 py-2 rounded-xl">Save Attendance for <?php echo htmlspecialchars($sa_date); ?></button></div>
    </form>
    <?php else: ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-[10px] uppercase"><tr><th class="text-left p-3">Staff</th><th class="text-left p-3">Status</th><th class="text-left p-3">Clock In</th><th class="text-left p-3">Clock Out</th></tr></thead>
            <tbody class="divide-y divide-[var(--border-color)]">
                <?php foreach ($sa_rows as $r): ?>
                <tr><td class="p-3 font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($r['name']); ?></td><td class="p-3"><?php echo htmlspecialchars($r['status'] ?? '—'); ?></td><td class="p-3"><?php echo htmlspecialchars($r['clock_in'] ?? '—'); ?></td><td class="p-3"><?php echo htmlspecialchars($r['clock_out'] ?? '—'); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
