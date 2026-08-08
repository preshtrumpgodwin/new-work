<?php
/**
 * SECTION: Notice Board
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Notice Board' => null]);
$can_write = can_manage($active_role, $current_access);

$notices = [];
try {
    $st = $pdo->prepare("SELECT * FROM school_notices_calendar WHERE school_uuid=? ORDER BY COALESCE(event_date, created_at) DESC, created_at DESC");
    $st->execute([$school_uuid]);
    $notices = $st->fetchAll();
} catch (Exception $e) {}

$cat_colors = [
    'Announcement' => 'indigo', 'Event' => 'emerald', 'Holiday' => 'amber',
    'Exam' => 'rose', 'Meeting' => 'cyan', 'Emergency' => 'red', 'Activity' => 'teal',
];
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="megaphone" class="w-5 h-5 text-rose-400"></i> Notice Board
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo count($notices); ?> notice(s) posted</p>
        </div>
        <?php if ($can_write): ?>
        <button onclick="document.getElementById('addNoticeModal').classList.remove('hidden')"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Post Notice
        </button>
        <?php endif; ?>
    </div>

    <?php if (empty($notices)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-12 flex flex-col items-center justify-center gap-3 text-center">
            <i data-lucide="megaphone" class="w-10 h-10 text-[var(--text-secondary)]"></i>
            <p class="text-xs text-[var(--text-secondary)]">No notices posted yet.</p>
        </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($notices as $n): $c = $cat_colors[$n['category']] ?? 'indigo'; ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-3 flex flex-col">
            <div class="flex items-start justify-between">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-<?php echo $c; ?>-500/10 text-<?php echo $c; ?>-400 border border-<?php echo $c; ?>-500/20"><?php echo htmlspecialchars($n['category']); ?></span>
                <?php if ($can_write): ?>
                <form method="POST" onsubmit="return confirm('Delete this notice?')"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_delete_notice" value="1">
                    <input type="hidden" name="notice_uuid" value="<?php echo htmlspecialchars($n['notice_uuid']); ?>">
                    <button type="submit" class="text-[var(--text-secondary)] hover:text-rose-400"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                </form>
                <?php endif; ?>
            </div>
            <h3 class="text-sm font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($n['title']); ?></h3>
            <p class="text-xs text-[var(--text-secondary)] flex-1 whitespace-pre-line"><?php echo htmlspecialchars($n['content']); ?></p>
            <div class="flex items-center justify-between text-[10px] text-[var(--text-secondary)] font-mono pt-2 border-t border-[var(--border-color)]">
                <span class="flex items-center gap-1"><i data-lucide="users" class="w-3 h-3"></i> <?php echo htmlspecialchars($n['target_audience']); ?></span>
                <?php if (!empty($n['event_date'])): ?>
                    <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i> <?php echo date('d M Y', strtotime($n['event_date'])); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($n['sent_sms_alert']): ?>
                <span class="text-[10px] text-emerald-400 flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> SMS alert sent</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Notice Modal -->
<div id="addNoticeModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="megaphone" class="w-4 h-4 text-rose-400"></i> Post Notice</h3>
            <button onclick="document.getElementById('addNoticeModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_notice" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Title *</label>
                <input type="text" name="title" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Category</label>
                    <select name="category" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach (array_keys($cat_colors) as $cat): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Event Date</label>
                    <input type="date" name="event_date" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Target Audience</label>
                <select name="target_audience" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="All">All (Staff + Parents)</option>
                    <option value="Staff">Staff Only</option>
                    <option value="Parents">Parents Only</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Content *</label>
                <textarea name="content" required rows="4" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
            </div>
            <label class="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
                <input type="checkbox" name="sent_sms_alert" value="1" class="rounded">
                Also send as an SMS alert to the target audience
            </label>
            <button type="submit" class="w-full py-3 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl">Post Notice</button>
        </form>
    </div>
</div>
