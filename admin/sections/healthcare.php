<?php
/**
 * SECTION: Healthcare Records
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Healthcare' => null]);
$can_write = can_manage($active_role, $current_access);

$filter_type = safe_str($_GET['filter_type'] ?? '');
$total = 0;
try {
    $cs = $pdo->prepare("SELECT COUNT(*) FROM healthcare_records WHERE school_uuid=?" . ($filter_type ? " AND person_type=?" : ""));
    $cs->execute($filter_type ? [$school_uuid, $filter_type] : [$school_uuid]);
    $total = (int)$cs->fetchColumn();
} catch (Exception $e) {}

$pg = paginate($total, 25, 'healthcare', array_filter(['filter_type' => $filter_type]));
$records = [];
try {
    $sql  = "SELECT * FROM healthcare_records WHERE school_uuid=?" . ($filter_type ? " AND person_type=?" : "") . " ORDER BY visit_date DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}";
    $bind = $filter_type ? [$school_uuid, $filter_type] : [$school_uuid];
    $st   = $pdo->prepare($sql); $st->execute($bind);
    $records = $st->fetchAll();
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="heart-pulse" class="w-5 h-5 text-rose-500"></i> Healthcare & Medical Records
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo number_format($total); ?> clinic visit records</p>
        </div>
        <?php if ($can_write): ?>
        <button onclick="document.getElementById('addHealthModal').classList.remove('hidden')"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Log Clinic Visit
        </button>
        <?php endif; ?>
    </div>
    <form method="GET" class="flex gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="healthcare">
        <select name="filter_type" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="">All Patients</option>
            <option value="Student" <?php echo $filter_type==='Student'?'selected':''; ?>>Students Only</option>
            <option value="Staff"   <?php echo $filter_type==='Staff'  ?'selected':''; ?>>Staff Only</option>
        </select>
    </form>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                <tr>
                    <th class="p-3">Date</th><th class="p-3">Patient</th><th class="p-3">Type</th>
                    <th class="p-3">Symptoms</th><th class="p-3">Diagnosis</th>
                    <th class="p-3">Treatment</th><th class="p-3">Attending</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php if (empty($records)): ?>
                <tr><td colspan="7" class="p-8 text-center text-[var(--text-secondary)] italic">No clinic visits logged yet.</td></tr>
            <?php else: foreach ($records as $r): ?>
                <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors">
                    <td class="p-3 font-mono text-indigo-400"><?php echo htmlspecialchars($r['visit_date']); ?></td>
                    <td class="p-3 font-bold"><?php echo htmlspecialchars($r['person_name']); ?></td>
                    <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-400"><?php echo htmlspecialchars($r['person_type']); ?></span></td>
                    <td class="p-3 text-[var(--text-primary)]"><?php echo htmlspecialchars($r['symptoms']); ?></td>
                    <td class="p-3 text-rose-300 font-semibold"><?php echo htmlspecialchars($r['diagnosis']); ?></td>
                    <td class="p-3 text-emerald-300"><?php echo htmlspecialchars($r['treatment']); ?></td>
                    <td class="p-3 text-[var(--text-secondary)]"><?php echo htmlspecialchars($r['attending_staff']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <div class="px-4 pb-4"><?php render_pagination($pg); ?></div>
    </div>
</div>
<!-- ADD HEALTH MODAL -->
<div id="addHealthModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-lg w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Log Clinic Visit</h3>
            <button onclick="document.getElementById('addHealthModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_health_record" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Patient Type</label>
                    <select name="person_type" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option>Student</option><option>Staff</option>
                    </select></div>
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Visit Date</label>
                    <input type="date" name="visit_date" value="<?php echo date('Y-m-d'); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Patient UUID</label>
                    <input type="text" name="person_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Patient Name</label>
                    <input type="text" name="person_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            </div>
            <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Symptoms *</label>
                <input type="text" name="symptoms" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Diagnosis</label>
                    <input type="text" name="diagnosis" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Treatment</label>
                    <input type="text" name="treatment" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            </div>
            <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Attending Staff</label>
                <input type="text" name="attending_staff" value="School Nurse" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <button type="submit" class="w-full py-3 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl shadow-lg">Save Medical Log</button>
        </form>
    </div>
</div>
