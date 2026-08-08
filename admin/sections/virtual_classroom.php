<?php
/**
 * SECTION: Virtual Classroom (Phase D)
 * Teachers/admins post a Zoom/Google Meet link for a class + subject;
 * students/parents see it on their portal. No embedded video — just a
 * scheduled link, which covers the "paste Zoom/Meet links" requirement
 * without depending on either provider's API.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Virtual Classroom' => null]);

$vc_list = [];
try {
    $vq = $pdo->prepare("SELECT * FROM virtual_classes WHERE school_uuid=? ORDER BY scheduled_at DESC LIMIT 100");
    $vq->execute([$school_uuid]);
    $vc_list = $vq->fetchAll();
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="video" class="w-6 h-6 text-indigo-400"></i><span>Virtual Classroom</span>
    </h1>

    <?php if ($can_write ?? in_array($active_role, ['School Admin','Teacher','Platform Manager'])): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
        <h3 class="text-sm font-bold text-[var(--text-primary)] mb-3">Schedule a Class</h3>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_virtual_class" value="1">
            <div><label class="block text-[10px] font-bold uppercase mb-1">Title</label><input type="text" name="title" required placeholder="e.g. SS2 Physics — Waves" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Meeting Link</label><input type="url" name="meeting_link" required placeholder="https://zoom.us/j/... or https://meet.google.com/..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Class</label>
                <select name="class_name" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">— Any / All —</option>
                    <?php foreach (($roster_classes ?? []) as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Subject</label><input type="text" name="subject_name" placeholder="e.g. Physics" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Date & Time</label><input type="datetime-local" name="scheduled_at" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Platform</label>
                <select name="platform" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option>Zoom</option><option>Google Meet</option><option>Other</option>
                </select>
            </div>
            <div class="md:col-span-2"><button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-xl">Schedule</button></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-[var(--border-color)]"><h3 class="text-sm font-bold text-[var(--text-primary)]">Scheduled Classes</h3></div>
        <div class="divide-y divide-[var(--border-color)]">
            <?php foreach ($vc_list as $vc): ?>
            <div class="p-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($vc['title']); ?></p>
                    <p class="text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($vc['class_name'] ?: 'All classes'); ?> <?php echo $vc['subject_name'] ? '· ' . htmlspecialchars($vc['subject_name']) : ''; ?> <?php echo $vc['scheduled_at'] ? '· ' . date('M j, Y g:ia', strtotime($vc['scheduled_at'])) : ''; ?> · <?php echo htmlspecialchars($vc['platform']); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars($vc['meeting_link']); ?>" target="_blank" rel="noopener" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold rounded-lg shrink-0">Join Link</a>
            </div>
            <?php endforeach; ?>
            <?php if (empty($vc_list)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">No classes scheduled yet.</p><?php endif; ?>
        </div>
    </div>
</div>
