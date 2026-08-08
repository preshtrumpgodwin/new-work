<?php
/**
 * SECTION: Consultations (Parent-Teacher appointments + direct messages)
 * Staff/Admin-facing: appointments are logged on behalf of a parent since
 * the parent portal doesn't have its own scheduling entry point yet.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Consultations' => null]);
$is_admin = in_array($active_role, ['School Admin','Platform Manager'], true);

$my_staff_uuid = null;
if (!$is_admin) {
    $ms = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=?");
    $ms->execute([$user_uuid, $school_uuid]);
    $my_staff_uuid = $ms->fetchColumn() ?: null;
}

$appointments = [];
try {
    $sql = "SELECT * FROM parent_teacher_appointments WHERE school_uuid=?";
    $params = [$school_uuid];
    if (!$is_admin && $my_staff_uuid) { $sql .= " AND teacher_uuid=?"; $params[] = $my_staff_uuid; }
    $sql .= " ORDER BY meeting_date DESC, id DESC";
    $st = $pdo->prepare($sql); $st->execute($params);
    $appointments = $st->fetchAll();
} catch (Exception $e) {}

$parents_list = $pdo->prepare("SELECT parent_uuid, name FROM parents WHERE school_uuid=? ORDER BY name ASC");
$parents_list->execute([$school_uuid]);
$parents_list = $parents_list->fetchAll();

$teachers_list = $pdo->prepare("SELECT staff_uuid, name FROM staff WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
$teachers_list->execute([$school_uuid]);
$teachers_list = $teachers_list->fetchAll();

// Messages: thread with a selected parent
$thread_parent = safe_str($_GET['parent_uuid'] ?? '');
$messages = [];
if ($thread_parent) {
    $mst = $pdo->prepare("SELECT * FROM parent_teacher_messages WHERE school_uuid=? AND ((sender_uuid=? AND receiver_uuid=?) OR (sender_uuid=? AND receiver_uuid=?)) ORDER BY sent_at ASC");
    $mst->execute([$school_uuid, $user_uuid, $thread_parent, $thread_parent, $user_uuid]);
    $messages = $mst->fetchAll();
}
$status_colors = ['Pending' => 'amber', 'Confirmed' => 'emerald', 'Declined' => 'rose', 'Completed' => 'indigo'];
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="calendar-heart" class="w-5 h-5 text-pink-400"></i> Consultations
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">Parent-teacher appointments & direct messages</p>
        </div>
        <button onclick="document.getElementById('logApptModal').classList.remove('hidden')"
            class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i> Log Appointment
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Appointments -->
        <div class="lg:col-span-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
            <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]"><h3 class="text-xs font-bold uppercase">Appointments</h3></div>
            <div class="divide-y divide-[var(--border-color)] max-h-[500px] overflow-y-auto">
                <?php if (empty($appointments)): ?>
                    <p class="text-xs text-[var(--text-secondary)] p-6 text-center">No appointments logged yet.</p>
                <?php endif; ?>
                <?php foreach ($appointments as $a): $sc = $status_colors[$a['status']] ?? 'slate'; ?>
                <div class="p-4 text-xs space-y-2">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($a['parent_name']); ?> ↔ <?php echo htmlspecialchars($a['teacher_name']); ?></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-<?php echo $sc; ?>-500/10 text-<?php echo $sc; ?>-400"><?php echo htmlspecialchars($a['status']); ?></span>
                    </div>
                    <p class="text-[var(--text-secondary)]">Re: <?php echo htmlspecialchars($a['student_name'] ?: '—'); ?> · <?php echo htmlspecialchars($a['purpose']); ?></p>
                    <p class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo date('d M Y', strtotime($a['meeting_date'])); ?> <?php echo htmlspecialchars($a['meeting_time']); ?></p>
                    <?php if ($a['status'] === 'Pending'): ?>
                    <div class="flex gap-2 pt-1">
                        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_respond_appointment" value="1"><input type="hidden" name="appointment_uuid" value="<?php echo htmlspecialchars($a['appointment_uuid']); ?>"><input type="hidden" name="decision" value="Confirmed"><button class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold text-[10px]">Confirm</button></form>
                        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_respond_appointment" value="1"><input type="hidden" name="appointment_uuid" value="<?php echo htmlspecialchars($a['appointment_uuid']); ?>"><input type="hidden" name="decision" value="Declined"><button class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-lg font-bold text-[10px]">Decline</button></form>
                    </div>
                    <?php elseif ($a['status'] === 'Confirmed'): ?>
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_respond_appointment" value="1"><input type="hidden" name="appointment_uuid" value="<?php echo htmlspecialchars($a['appointment_uuid']); ?>"><input type="hidden" name="decision" value="Completed"><button class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold text-[10px]">Mark Completed</button></form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Messages -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden flex flex-col">
            <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]">
                <h3 class="text-xs font-bold uppercase mb-2">Messages</h3>
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="section" value="consultations">
                    <select name="parent_uuid" onchange="this.form.submit()" class="flex-1 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-[10px] text-[var(--text-primary)]">
                        <option value="">Select a parent...</option>
                        <?php foreach ($parents_list as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['parent_uuid']); ?>" <?php echo $thread_parent===$p['parent_uuid']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php if ($thread_parent): ?>
            <div class="flex-1 max-h-[340px] overflow-y-auto p-3 space-y-2">
                <?php if (empty($messages)): ?><p class="text-[10px] text-[var(--text-secondary)] text-center py-6">No messages yet — say hello!</p><?php endif; ?>
                <?php foreach ($messages as $m): $mine = $m['sender_uuid'] === $user_uuid; ?>
                <div class="max-w-[85%] <?php echo $mine ? 'ml-auto bg-pink-600 text-white' : 'bg-[var(--bg-tertiary)] text-[var(--text-primary)]'; ?> rounded-xl px-3 py-2 text-xs">
                    <?php echo htmlspecialchars($m['message_text']); ?>
                    <div class="text-[9px] opacity-70 mt-1 font-mono"><?php echo date('d M, H:i', strtotime($m['sent_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" class="p-3 border-t border-[var(--border-color)] flex gap-2"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_send_pt_message" value="1">
                <input type="hidden" name="receiver_uuid" value="<?php echo htmlspecialchars($thread_parent); ?>">
                <input type="hidden" name="receiver_name" value="<?php echo htmlspecialchars($parents_list ? (array_values(array_filter($parents_list, fn($p)=>$p['parent_uuid']===$thread_parent))[0]['name'] ?? '') : ''); ?>">
                <input type="text" name="message_text" required placeholder="Type a message..." class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)]">
                <button type="submit" class="px-3 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-lg"><i data-lucide="send" class="w-3.5 h-3.5"></i></button>
            </form>
            <?php else: ?>
            <p class="text-[10px] text-[var(--text-secondary)] text-center py-10">Select a parent to view or start a conversation.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Log Appointment Modal -->
<div id="logApptModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Log Appointment</h3>
            <button onclick="document.getElementById('logApptModal').classList.add('hidden')" class="text-[var(--text-secondary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_request_appointment" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Parent *</label>
                <select name="parent_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">Select parent...</option>
                    <?php foreach ($parents_list as $p): ?><option value="<?php echo htmlspecialchars($p['parent_uuid']); ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Teacher *</label>
                <select name="teacher_uuid" required <?php echo (!$is_admin && $my_staff_uuid) ? 'disabled' : ''; ?> class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <?php foreach ($teachers_list as $t): ?>
                    <option value="<?php echo htmlspecialchars($t['staff_uuid']); ?>" <?php echo (!$is_admin && $t['staff_uuid']===$my_staff_uuid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$is_admin && $my_staff_uuid): ?><input type="hidden" name="teacher_uuid" value="<?php echo htmlspecialchars($my_staff_uuid); ?>"><?php endif; ?>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Student Name</label>
                <input type="text" name="student_name" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Date *</label>
                    <input type="date" name="meeting_date" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Time</label>
                    <input type="text" name="meeting_time" placeholder="10:00 AM" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Purpose *</label>
                <textarea name="purpose" required rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-pink-600 hover:bg-pink-500 text-white font-bold text-xs rounded-xl">Log Appointment</button>
        </form>
    </div>
</div>
