<?php
/**
 * SECTION: disciplinary — extracted from dashboard.php (Phase 1 refactor)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Disciplinary' => null]);
?>
<!-- SECTION: DISCIPLINARY (separated from news) -->
                <?php if ($section === 'disciplinary'): ?>
                    <?php
                    $behaviorLogs = [];
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM student_behavior_records WHERE school_uuid = ? ORDER BY recorded_at DESC");
                        $stmt->execute([$school_uuid]);
                        $behaviorLogs = $stmt->fetchAll();
                    } catch (Exception $e) {}
                    $can_log_incident = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, $current_access ?? 'hide');
                    $disc_students = [];
                    try {
                        $dq = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
                        $dq->execute([$school_uuid]);
                        $disc_students = $dq->fetchAll();
                    } catch (Exception $e) {}
                    ?>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
                            <div>
                                <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                                    <i data-lucide="shield-alert" class="w-6 h-6 text-rose-400"></i>
                                    <span>Disciplinary Records</span>
                                </h1>
                                <p class="text-xs text-[var(--text-secondary)]">Merit/demerit tracker. News & notices are in a separate section.</p>
                            </div>
                            <?php if ($can_log_incident): ?>
                            <button onclick="document.getElementById('newBehaviorModal').classList.remove('hidden')" class="px-4 py-2 bg-rose-600 text-white font-bold text-xs rounded-xl">Log Incident</button>
                            <?php endif; ?>
                        </div>

                        <?php if ($can_log_incident): ?>
                        <div id="newBehaviorModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 max-w-lg w-full space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-[var(--text-primary)]">Log Disciplinary Incident</h3>
                                    <button onclick="document.getElementById('newBehaviorModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white">✕</button>
                                </div>
                                <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_behavior_record" value="1">
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase mb-1">Student</label>
                                        <select name="student_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                            <?php foreach ($disc_students as $ds): ?>
                                            <option value="<?php echo htmlspecialchars($ds['student_uuid']); ?>"><?php echo htmlspecialchars($ds['name'].' — '.$ds['class']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase mb-1">Type</label>
                                            <select name="incident_type" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                                <option value="Demerit">Demerit</option>
                                                <option value="Merit">Merit</option>
                                                <option value="Commendation">Commendation</option>
                                                <option value="Warning">Warning</option>
                                                <option value="Suspension">Suspension</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase mb-1">Points</label>
                                            <input type="number" name="points" value="5" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase mb-1">Title</label>
                                        <input type="text" name="title" required placeholder="e.g. Fighting in class" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase mb-1">Action Taken</label>
                                        <input type="text" name="action_taken" placeholder="e.g. Notice Sent to Parent" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase mb-1">Notes (optional)</label>
                                        <textarea name="notes" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></textarea>
                                    </div>
                                    <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl">Save Record</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                                    <tr><th class="p-3">Student</th><th class="p-3">Type</th><th class="p-3">Title</th><th class="p-3">Points</th><th class="p-3">Action</th><th class="p-3">Date</th><?php if ($can_log_incident): ?><th class="p-3"></th><?php endif; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--border-color)]">
                                    <?php if (empty($behaviorLogs)): ?>
                                        <tr><td colspan="7" class="p-6 text-center text-[var(--text-secondary)] italic">No records yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($behaviorLogs as $bl): ?>
                                            <tr>
                                                <td class="p-3 font-bold"><?php echo htmlspecialchars($bl['student_name']); ?></td>
                                                <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo in_array($bl['incident_type'],['Merit','Commendation']) ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>"><?php echo htmlspecialchars($bl['incident_type']); ?></span></td>
                                                <td class="p-3"><?php echo htmlspecialchars($bl['title']); ?></td>
                                                <td class="p-3 font-mono text-amber-400 font-bold"><?php echo $bl['points']; ?> pts</td>
                                                <td class="p-3 text-[var(--text-secondary)]"><?php echo htmlspecialchars($bl['action_taken']); ?></td>
                                                <td class="p-3 text-[var(--text-secondary)] font-mono"><?php echo date('M d', strtotime($bl['recorded_at'])); ?></td>
                                                <?php if ($can_log_incident): ?>
                                                <td class="p-3 text-right">
                                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this record?')"><?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action_delete_behavior_record" value="1">
                                                        <input type="hidden" name="record_uuid" value="<?php echo htmlspecialchars($bl['record_uuid']); ?>">
                                                        <button type="submit" class="text-rose-400 hover:text-rose-300 text-[10px]">✕</button>
                                                    </form>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
