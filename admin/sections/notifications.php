<?php
/**
 * SECTION: In-App Notifications
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Notifications' => null]);

$notifs = Notify::listFor($pdo, $school_uuid, $user_uuid, $active_role, 100);
$type_colors = ['info' => 'indigo', 'success' => 'emerald', 'warning' => 'amber', 'alert' => 'rose'];
$type_icons  = ['info' => 'info', 'success' => 'check-circle', 'warning' => 'alert-triangle', 'alert' => 'bell-ring'];
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="bell" class="w-5 h-5 text-indigo-400"></i> Notifications
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo count($notifs); ?> notification(s)</p>
        </div>
        <form method="POST"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_mark_all_notifications_read" value="1">
            <button type="submit" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)] text-xs font-bold rounded-xl">Mark all as read</button>
        </form>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-12 flex flex-col items-center justify-center gap-3 text-center">
            <i data-lucide="bell-off" class="w-10 h-10 text-[var(--text-secondary)]"></i>
            <p class="text-xs text-[var(--text-secondary)]">You're all caught up — no notifications.</p>
        </div>
    <?php else: ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl divide-y divide-[var(--border-color)] overflow-hidden">
        <?php foreach ($notifs as $n): $tc = $type_colors[$n['type']] ?? 'indigo'; $ti = $type_icons[$n['type']] ?? 'info'; ?>
        <div class="p-4 flex items-start gap-3 <?php echo $n['is_read'] ? 'opacity-60' : ''; ?>">
            <div class="w-8 h-8 rounded-lg bg-<?php echo $tc; ?>-500/10 flex items-center justify-center shrink-0">
                <i data-lucide="<?php echo $ti; ?>" class="w-4 h-4 text-<?php echo $tc; ?>-400"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($n['title']); ?></span>
                    <span class="text-[10px] text-[var(--text-secondary)] font-mono shrink-0"><?php echo date('d M, H:i', strtotime($n['created_at'])); ?></span>
                </div>
                <p class="text-xs text-[var(--text-secondary)] mt-0.5"><?php echo htmlspecialchars($n['message']); ?></p>
                <div class="flex items-center gap-3 mt-2">
                    <?php if (!empty($n['link'])): ?>
                        <a href="<?php echo htmlspecialchars($n['link']); ?>" class="text-[10px] font-bold text-indigo-400 hover:underline">View →</a>
                    <?php endif; ?>
                    <?php if (!$n['is_read']): ?>
                    <form method="POST"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_mark_notification_read" value="1">
                        <input type="hidden" name="notification_uuid" value="<?php echo htmlspecialchars($n['notification_uuid']); ?>">
                        <button type="submit" class="text-[10px] font-bold text-[var(--text-secondary)] hover:text-[var(--text-primary)]">Mark as read</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
