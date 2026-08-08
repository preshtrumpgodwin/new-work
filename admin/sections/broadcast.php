<?php
/**
 * SECTION: broadcast — extracted from dashboard.php (Phase 1 refactor)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Broadcast' => null]);
?>
<!-- SECTION: BROADCAST WITH DEBTORS/NON-DEBTORS -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <?php if ($section === 'broadcast'): ?>
                    <?php
                    $bcs = [];
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM broadcast_messages WHERE school_uuid = ? ORDER BY sent_at DESC");
                        $stmt->execute([$school_uuid]);
                        $bcs = $stmt->fetchAll();
                    } catch (Exception $e) {}

                    // Phase B — Automated Message Templates (birthday + triggers)
                    $notif_templates = [];
                    try {
                        $ntq = $pdo->prepare("SELECT * FROM notification_templates WHERE school_uuid=? ORDER BY category, audience, trigger_key, slot_index");
                        $ntq->execute([$school_uuid]);
                        foreach ($ntq->fetchAll() as $r) {
                            $key = $r['category'] === 'birthday' ? "birthday_{$r['audience']}_{$r['slot_index']}" : "trigger_{$r['trigger_key']}";
                            $notif_templates[$key] = $r;
                        }
                    } catch (Exception $e) {}
                    ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                                <i data-lucide="send" class="w-6 h-6 text-cyan-400"></i>
                                <span>SMS & Broadcast Centre</span>
                            </h1>
                            <p class="text-xs text-[var(--text-secondary)]">Send messages to parents, staff, debtors, and non-debtors.</p>
                        </div>
                        <?php if (empty($school_settings['sms_api_key'] ?? '')): ?>
                        <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-400 flex items-center gap-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                            <span>No SMS gateway API key configured — messages will be logged but not actually delivered. <a href="dashboard.php?section=settings" class="underline font-bold">Configure it in Settings</a>.</span>
                        </div>
                        <?php endif; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Compose Broadcast</h3>
                                <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_send_broadcast" value="1">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase mb-1">Channel</label>
                                            <select name="channel" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                                <option value="SMS">SMS</option>
                                                <option value="WhatsApp">WhatsApp</option>
                                                <option value="SMS & WhatsApp">Both</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase mb-1">Recipients</label>
                                            <select name="recipient_group" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                                <option value="All Parents">All Parents</option>
                                                <option value="All Staff">All Staff</option>
                                                <option value="Debtors">Debtors / Unpaid Fees</option>
                                                <option value="Non-Debtors">Non-Debtors / Paid Fees</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase mb-1">Message *</label>
                                        <textarea name="message_text" required rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
                                    </div>
                                    <button type="submit" class="w-full py-3 bg-cyan-600 text-white font-bold text-xs rounded-xl">Send Broadcast</button>
                                </form>
                            </div>
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">One-Click Triggers</h3>
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_send_absenteeism_alerts" value="1"><button type="submit" class="w-full p-4 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-left hover:border-rose-500/40 transition-all"><span class="text-xs font-bold">📢 Daily Absenteeism Alert</span><span class="text-[10px] text-[var(--text-secondary)] block">Notify parents of absent students today.</span></button></form>
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_send_fee_reminders" value="1"><button type="submit" class="w-full p-4 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-left hover:border-emerald-500/40 transition-all"><span class="text-xs font-bold">💰 Fee Balance Reminder</span><span class="text-[10px] text-[var(--text-secondary)] block">Send payment reminders to debtor parents.</span></button></form>
                            </div>
                        </div>
                        <!-- Automated Message Templates (Phase B — Notification Engine) -->
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-3">
                            <button type="button" onclick="document.getElementById('notifTplPanel').classList.toggle('hidden')" class="flex items-center justify-between w-full text-left">
                                <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="bell-ring" class="w-4 h-4 text-cyan-400"></i> Automated Message Templates</h3>
                                <span class="text-[10px] text-[var(--text-secondary)]">Birthdays · Exam Results · Activities — click to manage</span>
                            </button>
                            <div id="notifTplPanel" class="hidden space-y-6 pt-3 border-t border-[var(--border-color)]">

                                <!-- Trigger templates -->
                                <div class="space-y-3">
                                    <h4 class="text-xs font-bold uppercase text-[var(--text-secondary)]">Auto-Triggered SMS</h4>
                                    <?php foreach (['exam_result' => ['label' => 'Exam Result Published', 'tokens' => 'student_name, class, parent_name, session, term, school_name'],
                                                     'activity'    => ['label' => 'Activity Posted (Notice Board)', 'tokens' => 'activity_title, school_name']] as $tk => $meta):
                                        $existing = $notif_templates["trigger_$tk"] ?? null; ?>
                                    <form method="POST" class="border border-[var(--border-color)] rounded-xl p-4 space-y-2">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_save_trigger_template" value="1">
                                        <input type="hidden" name="trigger_key" value="<?php echo $tk; ?>">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-[var(--text-primary)]"><?php echo $meta['label']; ?></span>
                                            <label class="flex items-center gap-1.5 text-[10px] text-[var(--text-secondary)]"><input type="checkbox" name="is_active" value="1" <?php echo (!empty($existing['is_active'])) ? 'checked' : ''; ?>> Active (auto-sends on trigger)</label>
                                        </div>
                                        <p class="text-[9px] text-[var(--text-secondary)]">Tokens: <?php echo htmlspecialchars($meta['tokens']); ?></p>
                                        <textarea name="body" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-xs text-[var(--text-primary)]" placeholder="Message body…"><?php echo htmlspecialchars($existing['body'] ?? ''); ?></textarea>
                                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg">Save</button>
                                    </form>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Birthday templates: 10 slots per audience -->
                                <?php foreach (['student' => 'Students', 'staff' => 'Staff', 'parent' => 'Parents'] as $aud => $audLabel): ?>
                                <div class="space-y-2">
                                    <button type="button" onclick="document.getElementById('bdaySlots-<?php echo $aud; ?>').classList.toggle('hidden')" class="flex items-center justify-between w-full text-left">
                                        <h4 class="text-xs font-bold uppercase text-[var(--text-secondary)]">Birthday Templates — <?php echo $audLabel; ?> (10 slots)</h4>
                                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-[var(--text-secondary)]"></i>
                                    </button>
                                    <div id="bdaySlots-<?php echo $aud; ?>" class="hidden grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <?php for ($slot = 1; $slot <= 10; $slot++):
                                            $existing = $notif_templates["birthday_{$aud}_{$slot}"] ?? null; ?>
                                        <form method="POST" class="border border-[var(--border-color)] rounded-xl p-3 space-y-2">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action_save_birthday_template" value="1">
                                            <input type="hidden" name="audience" value="<?php echo $aud; ?>">
                                            <input type="hidden" name="slot_index" value="<?php echo $slot; ?>">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-bold text-[var(--text-secondary)]">Slot <?php echo $slot; ?></span>
                                                <label class="flex items-center gap-1 text-[9px] text-[var(--text-secondary)]"><input type="checkbox" name="is_active" value="1" <?php echo (!empty($existing['is_active'])) ? 'checked' : ''; ?>> Use this one</label>
                                            </div>
                                            <input type="text" name="title" value="<?php echo htmlspecialchars($existing['title'] ?? "Slot $slot"); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-[10px] text-[var(--text-primary)]" placeholder="Title">
                                            <textarea name="body" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg p-2 text-[10px] text-[var(--text-primary)]" placeholder="e.g. Happy Birthday {{student_name}}! 🎉 — {{school_name}}"><?php echo htmlspecialchars($existing['body'] ?? ''); ?></textarea>
                                            <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white text-[9px] font-bold px-2.5 py-1 rounded-lg">Save Slot</button>
                                        </form>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Broadcast Logs -->
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
                            <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]"><h3 class="text-xs font-bold uppercase">Broadcast History</h3></div>
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Channel</th><th class="p-3">Group</th><th class="p-3">Message</th><th class="p-3">Recipients</th><th class="p-3">Status</th><th class="p-3">Time</th></tr></thead>
                                <tbody class="divide-y divide-[var(--border-color)]">
                                    <?php foreach ($bcs as $b): ?>
                                        <tr><td class="p-3 font-bold text-cyan-400"><?php echo htmlspecialchars($b['channel']); ?></td><td class="p-3"><?php echo htmlspecialchars($b['recipient_group']); ?></td><td class="p-3 truncate max-w-xs"><?php echo htmlspecialchars($b['message_text']); ?></td><td class="p-3 font-mono text-indigo-400"><?php echo $b['recipient_count']; ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $b['status']==='Sent' ? 'bg-emerald-500/10 text-emerald-400' : ($b['status']==='No Recipients' ? 'bg-slate-500/10 text-slate-400' : 'bg-rose-500/10 text-rose-400'); ?>" title="<?php echo htmlspecialchars($b['gateway_response'] ?? ''); ?>"><?php echo htmlspecialchars($b['status']); ?></span>
                                        </td>
                                        <td class="p-3 text-[var(--text-secondary)]"><?php echo $b['sent_at']; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════ -->
