<?php
/**
 * SECTION: Email Centre
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Email Centre' => null]);

$emails = [];
try {
    $st = $pdo->prepare("SELECT * FROM email_log WHERE school_uuid=? ORDER BY sent_at DESC LIMIT 50");
    $st->execute([$school_uuid]);
    $emails = $st->fetchAll();
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <i data-lucide="mail" class="w-5 h-5 text-blue-400"></i> Email Centre
        </h1>
        <p class="text-xs text-[var(--text-secondary)] mt-1">Send email announcements to parents, staff, or a custom address.</p>
    </div>

    <?php if (empty($school_settings['smtp_host'] ?? '')): ?>
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-400 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span>No SMTP host configured — the system will try the server's default mail() function, which may not be reliable. <a href="dashboard.php?section=settings" class="underline font-bold">Configure SMTP in Settings</a> for guaranteed delivery.</span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Compose Email</h3>
            <form method="POST" class="space-y-4" id="emailForm"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_send_email" value="1">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Recipients</label>
                    <select name="recipient_group" id="recipientGroup" onchange="document.getElementById('customEmailWrap').classList.toggle('hidden', this.value !== 'Custom')" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="All Parents">All Parents</option>
                        <option value="All Staff">All Staff</option>
                        <option value="Custom">Custom Email Address</option>
                    </select>
                </div>
                <div id="customEmailWrap" class="hidden">
                    <label class="block text-[10px] font-bold uppercase mb-1">Custom Email</label>
                    <input type="email" name="custom_email" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Subject *</label>
                    <input type="text" name="subject" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Message *</label>
                    <textarea name="body_html" required rows="6" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl">Send Email</button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
            <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]"><h3 class="text-xs font-bold uppercase">Send History</h3></div>
            <div class="max-h-[420px] overflow-y-auto divide-y divide-[var(--border-color)]">
                <?php if (empty($emails)): ?>
                    <p class="text-xs text-[var(--text-secondary)] p-6 text-center">No emails sent yet.</p>
                <?php endif; ?>
                <?php foreach ($emails as $e): ?>
                <div class="p-4 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($e['subject']); ?></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $e['status']==='Sent' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>"><?php echo htmlspecialchars($e['status']); ?></span>
                    </div>
                    <p class="text-[var(--text-secondary)]"><?php echo htmlspecialchars($e['recipient_group']); ?> · <?php echo (int)$e['recipient_count']; ?> recipient(s)</p>
                    <p class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo $e['sent_at']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
