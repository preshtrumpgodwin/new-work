<?php
// Use 'audit_page' for log pagination; 'page' is reserved for app routing
$audit_page = isset($_GET['audit_page']) ? max(1, (int)$_GET['audit_page']) : 1;
$per_page = 20;
$offset = ($audit_page - 1) * $per_page;

// Capture the current app page (section) to preserve it in links
$current_app_page = $_GET['page'] ?? 'audit'; // default to 'audit' if not set

// Real server-side search + action filter (was client-side-only before,
// which only ever searched the 20 rows already on the current page).
$q = trim($_GET['q'] ?? '');
$action_type = trim($_GET['action_type'] ?? '');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(user_email LIKE ? OR action LIKE ? OR description LIKE ? OR ip_address LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($action_type !== '') {
    $where[] = "action LIKE ?";
    $params[] = '%' . $action_type . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$logs = $pdo->prepare("SELECT * FROM audit_logs $whereSql ORDER BY id DESC LIMIT ? OFFSET ?");
$i = 1;
foreach ($params as $p) { $logs->bindValue($i++, $p); }
$logs->bindValue($i++, $per_page, PDO::PARAM_INT);
$logs->bindValue($i++, $offset, PDO::PARAM_INT);
$logs->execute();
$logs = $logs->fetchAll();
$total_pages = max(1, ceil($total / $per_page));

// Preserve q/action_type in pagination links
$qs = http_build_query(array_filter(['page' => $current_app_page, 'q' => $q, 'action_type' => $action_type]));
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Audit Logs</h1>
        <span class="text-xs text-[var(--text-secondary)] font-mono"><?php echo number_format($total); ?> records<?php echo ($q !== '' || $action_type !== '') ? ' (filtered)' : ''; ?></span>
    </div>
    
    <!-- Filter/Search Bar — real server-side search across every record, not just the current page -->
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl">
        <input type="hidden" name="page" value="<?php echo htmlspecialchars($current_app_page); ?>">
        <div class="flex-1 min-w-40">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search user, action, description, IP..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
        </div>
        <select name="action_type" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            <option value="">All Actions</option>
            <?php foreach (['login', 'logout', 'create', 'update', 'delete', 'approve', 'reject'] as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php echo $action_type === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition">Filter</button>
        <?php if ($q !== '' || $action_type !== ''): ?>
            <a href="?page=<?php echo urlencode($current_app_page); ?>" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] font-bold rounded-xl text-xs transition">Reset</a>
        <?php endif; ?>
    </form>
    
    <!-- Audit Logs Table -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                    <tr>
                        <th class="px-3.5 py-3">#</th>
                        <th class="px-3.5 py-3">Timestamp</th>
                        <th class="px-3.5 py-3">User</th>
                        <th class="px-3.5 py-3">Action</th>
                        <th class="px-3.5 py-3">Description</th>
                        <th class="px-3.5 py-3">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-3.5 py-8 text-center text-[var(--text-secondary)] italic">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-[var(--text-secondary)] opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span><?php echo ($q !== '' || $action_type !== '') ? 'No audit logs match that search' : 'No audit logs found'; ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $index => $log): 
                            $logId = htmlspecialchars($log['id'], ENT_QUOTES, 'UTF-8');
                            $createdAt = htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8');
                            $userEmail = htmlspecialchars($log['user_email'], ENT_QUOTES, 'UTF-8');
                            $action = htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8');
                            $details = htmlspecialchars($log['description'] ?? '', ENT_QUOTES, 'UTF-8');
                            $ipAddress = htmlspecialchars($log['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8');
                            
                            // Color code actions
                            $actionColor = 'text-[var(--text-primary)]';
                            $actionBg = 'bg-[var(--bg-tertiary)]';
                            if (strpos(strtolower($action), 'login') !== false) {
                                $actionColor = 'text-emerald-400';
                                $actionBg = 'bg-emerald-500/10';
                            } elseif (strpos(strtolower($action), 'logout') !== false) {
                                $actionColor = 'text-amber-400';
                                $actionBg = 'bg-amber-500/10';
                            } elseif (strpos(strtolower($action), 'delete') !== false || strpos(strtolower($action), 'reject') !== false) {
                                $actionColor = 'text-rose-400';
                                $actionBg = 'bg-rose-500/10';
                            } elseif (strpos(strtolower($action), 'create') !== false || strpos(strtolower($action), 'approve') !== false) {
                                $actionColor = 'text-indigo-400';
                                $actionBg = 'bg-indigo-500/10';
                            }
                        ?>
                        <tr class="hover:bg-[var(--bg-tertiary)]/30 transition">
                            <td class="px-3.5 py-3 text-[var(--text-secondary)] font-mono text-[10px]"><?php echo $logId; ?></td>
                            <td class="px-3.5 py-3 text-[var(--text-secondary)] font-mono whitespace-nowrap"><?php echo $createdAt; ?></td>
                            <td class="px-3.5 py-3 text-indigo-400 font-mono font-bold"><?php echo $userEmail; ?></td>
                            <td class="px-3.5 py-3">
                                <span class="px-2 py-0.5 rounded <?php echo $actionBg . ' ' . $actionColor; ?> font-mono text-[10px] font-bold">
                                    <?php echo $action; ?>
                                </span>
                            </td>
                            <td class="px-3.5 py-3 text-[var(--text-secondary)] max-w-xs truncate" title="<?php echo $details; ?>"><?php echo $details !== '' ? $details : '—'; ?></td>
                            <td class="px-3.5 py-3 text-[var(--text-secondary)] font-mono text-[10px] whitespace-nowrap"><?php echo $ipAddress; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[var(--text-secondary)] pt-2">
        <div class="text-[var(--text-secondary)] font-mono">
            Showing <?php echo min(($audit_page-1)*$per_page+1, $total); ?> – <?php echo min($audit_page*$per_page, $total); ?> of <?php echo number_format($total); ?>
        </div>
        <div class="flex items-center gap-1 flex-wrap">
            <?php if ($audit_page > 1): ?>
                <a href="?<?php echo $qs; ?>&audit_page=<?php echo $audit_page-1; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">←</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i === $audit_page): ?>
                    <span class="px-2.5 py-1 bg-indigo-600 text-white rounded-lg font-bold min-w-[28px] text-center"><?php echo $i; ?></span>
                <?php elseif ($i === 1 || $i === $total_pages || abs($i - $audit_page) <= 2): ?>
                    <a href="?<?php echo $qs; ?>&audit_page=<?php echo $i; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition min-w-[28px] text-center"><?php echo $i; ?></a>
                <?php elseif ($i === 2 || $i === $total_pages - 1): ?>
                    <span class="px-1 text-[var(--text-secondary)]">…</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($audit_page < $total_pages): ?>
                <a href="?<?php echo $qs; ?>&audit_page=<?php echo $audit_page+1; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">→</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>