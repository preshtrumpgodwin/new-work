<?php
/**
 * SECTION: Annual Report Generator (Phase D)
 * Aggregates enrollment, results, attendance, and finance stats for a
 * chosen session into a printable school annual report.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Annual Report' => null]);

$ar_session = $_GET['ar_session'] ?? ($current_session ?? '');
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="bar-chart-3" class="w-6 h-6 text-orange-400"></i><span>Annual Report Generator</span>
    </h1>

    <form method="GET" class="flex gap-3 items-end">
        <input type="hidden" name="section" value="annual_report">
        <div>
            <label class="block text-[10px] font-bold uppercase mb-1">Session</label>
            <input type="text" name="ar_session" value="<?php echo htmlspecialchars($ar_session); ?>" placeholder="e.g. 2025/2026" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
        </div>
        <button class="px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold rounded-xl">Generate</button>
        <?php if ($ar_session !== ''): ?>
        <a href="print_annual_report.php?session=<?php echo urlencode($ar_session); ?>" target="_blank" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)] text-xs font-bold rounded-xl">Print Report</a>
        <?php endif; ?>
    </form>

    <?php if ($ar_session !== ''):
        $c = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_uuid=? AND status='Active'"); $c->execute([$school_uuid]); $total_students = (int)$c->fetchColumn();
        $c = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE school_uuid=? AND status='Active'"); $c->execute([$school_uuid]); $total_staff = (int)$c->fetchColumn();
        $c = $pdo->prepare("SELECT COUNT(DISTINCT student_uuid) FROM alumni WHERE school_uuid=? AND graduation_year=?"); $c->execute([$school_uuid, substr($ar_session,0,4)]); $graduates = (int)$c->fetchColumn();
        $c = $pdo->prepare("SELECT AVG(total_score) FROM results WHERE school_uuid=? AND session_name=?"); $c->execute([$school_uuid, $ar_session]); $avg_score = round((float)$c->fetchColumn(), 1);
        $c = $pdo->prepare("SELECT COUNT(*) FROM results WHERE school_uuid=? AND session_name=?"); $c->execute([$school_uuid, $ar_session]); $result_rows = (int)$c->fetchColumn();
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4"><p class="text-[10px] uppercase text-[var(--text-secondary)]">Active Students</p><p class="text-2xl font-bold text-[var(--text-primary)]"><?php echo $total_students; ?></p></div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4"><p class="text-[10px] uppercase text-[var(--text-secondary)]">Active Staff</p><p class="text-2xl font-bold text-[var(--text-primary)]"><?php echo $total_staff; ?></p></div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4"><p class="text-[10px] uppercase text-[var(--text-secondary)]">Graduates (<?php echo htmlspecialchars(substr($ar_session,0,4)); ?>)</p><p class="text-2xl font-bold text-[var(--text-primary)]"><?php echo $graduates; ?></p></div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4"><p class="text-[10px] uppercase text-[var(--text-secondary)]">Avg. Result Score</p><p class="text-2xl font-bold text-[var(--text-primary)]"><?php echo $avg_score ?: '—'; ?></p></div>
    </div>
    <p class="text-[10px] text-[var(--text-secondary)]"><?php echo $result_rows; ?> result entries on file for <?php echo htmlspecialchars($ar_session); ?>.</p>
    <?php endif; ?>
</div>
