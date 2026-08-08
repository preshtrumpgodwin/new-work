<?php
/**
 * SECTION: Audit Log (School Admin)
 *
 * Read-only view of everything AuditLog::write() has recorded for this
 * school — who changed what, and when. Useful for disputes over grade
 * changes, fee edits, permission changes, etc.
 *
 * Restricted to School Admin (and Platform Manager, who can see any school).
 * This section is intentionally NOT in $sectionFeatureMap in dashboard.php,
 * so it can't be turned into a "hide/view/manage" per-staff feature toggle —
 * audit visibility for a school should only ever belong to that school's
 * admin, not be delegable to arbitrary staff.
 */
if (!in_array($active_role, ['School Admin', 'Platform Manager'], true)) {
    header('Location: dashboard.php?section=overview');
    exit;
}

render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Audit Log' => null]);

$filter_action = trim($_GET['action_filter'] ?? '');
$filter_user   = trim($_GET['user_filter'] ?? '');
$filter_from   = trim($_GET['from'] ?? '');
$filter_to     = trim($_GET['to'] ?? '');

$where  = "WHERE school_uuid = ?";
$params = [$school_uuid];

if ($filter_action !== '') {
    $where .= " AND action LIKE ?";
    $params[] = '%' . $filter_action . '%';
}
if ($filter_user !== '') {
    $where .= " AND user_uuid = ?";
    $params[] = $filter_user;
}
if ($filter_from !== '') {
    $where .= " AND created_at >= ?";
    $params[] = $filter_from . ' 00:00:00';
}
if ($filter_to !== '') {
    $where .= " AND created_at <= ?";
    $params[] = $filter_to . ' 23:59:59';
}

$logs = [];
$total_rows = 0;
$staff_directory = [];
try {
    // audit_logs is created lazily by AuditLog::write() on first use, so it
    // may not exist yet on a brand-new school with zero mutating actions.
    $exists = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetch();
    if ($exists) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $where");
        $countStmt->execute($params);
        $total_rows = (int)$countStmt->fetchColumn();

        $pg = paginate($total_rows, 40, 'audit_log', array_filter([
            'action_filter' => $filter_action,
            'user_filter'   => $filter_user,
            'from'          => $filter_from,
            'to'            => $filter_to,
        ]));

        $stmt = $pdo->prepare("SELECT * FROM audit_logs $where ORDER BY created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Resolve actor names in one query rather than one lookup per row.
        $userUuids = array_unique(array_filter(array_column($logs, 'user_uuid')));
        if (!empty($userUuids)) {
            $placeholders = implode(',', array_fill(0, count($userUuids), '?'));
            $nameStmt = $pdo->prepare("SELECT user_uuid, name FROM users WHERE user_uuid IN ($placeholders)");
            $nameStmt->execute(array_values($userUuids));
            foreach ($nameStmt->fetchAll() as $u) {
                $staff_directory[$u['user_uuid']] = $u['name'];
            }
        }
    } else {
        $pg = paginate(0, 40, 'audit_log');
    }
} catch (Exception $e) {
    $pg = paginate(0, 40, 'audit_log');
}

// Distinct action list for the filter dropdown.
$action_options = [];
try {
    if ($exists ?? false) {
        $aStmt = $pdo->prepare("SELECT DISTINCT action FROM audit_logs WHERE school_uuid = ? ORDER BY action ASC");
        $aStmt->execute([$school_uuid]);
        $action_options = $aStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)] flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="shield-check" class="w-5 h-5 text-cyan-400"></i> Audit Log
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo number_format($total_rows); ?> recorded action(s) for this school</p>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 items-end bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4">
        <input type="hidden" name="section" value="audit_log">
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Action contains</label>
            <input type="text" name="action_filter" value="<?php echo htmlspecialchars($filter_action); ?>" list="actionOptions"
                placeholder="e.g. finance.receipt"
                class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            <datalist id="actionOptions">
                <?php foreach ($action_options as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($filter_from); ?>"
                class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
        </div>
        <div>
            <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($filter_to); ?>"
                class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
        </div>
        <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl">Filter</button>
        <?php if ($filter_action !== '' || $filter_user !== '' || $filter_from !== '' || $filter_to !== ''): ?>
            <a href="dashboard.php?section=audit_log" class="px-4 py-2 bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl">Clear</a>
        <?php endif; ?>
    </form>

    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase text-[10px] font-bold">
                    <tr>
                        <th class="p-3 text-left">When</th>
                        <th class="p-3 text-left">Actor</th>
                        <th class="p-3 text-left">Action</th>
                        <th class="p-3 text-left">Description</th>
                        <th class="p-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="p-6 text-center text-[var(--text-secondary)]">No audit records match this filter yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                    <tr class="border-t border-[var(--border-color)] hover:bg-[var(--bg-tertiary)]">
                        <td class="p-3 whitespace-nowrap text-[var(--text-secondary)]"><?php echo htmlspecialchars($log['created_at']); ?></td>
                        <td class="p-3 whitespace-nowrap"><?php echo htmlspecialchars($staff_directory[$log['user_uuid']] ?? 'System'); ?></td>
                        <td class="p-3 font-mono text-cyan-400 whitespace-nowrap"><?php echo htmlspecialchars($log['action']); ?></td>
                        <td class="p-3 text-[var(--text-primary)]"><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                        <td class="p-3 font-mono text-[var(--text-secondary)] whitespace-nowrap"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3">
            <?php render_pagination($pg); ?>
        </div>
    </div>
</div>
