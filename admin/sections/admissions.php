<?php
/**
 * SECTION: Online Admissions
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Admissions' => null]);

$apps = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM public_applications WHERE school_uuid = ? ORDER BY (status='Pending') DESC, created_at DESC");
    $stmt->execute([$school_uuid]);
    $apps = $stmt->fetchAll();
} catch (Exception $e) {}

$status_colors = ['Pending' => 'amber', 'Approved' => 'emerald', 'Rejected' => 'rose'];
?>
<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Online Admissions</h1>
        <p class="text-xs text-[var(--text-secondary)]">Review student & staff applications from the public portal. Approving creates the real student/staff record.</p>
    </div>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                <tr>
                    <th class="p-3.5">Applicant</th><th class="p-3.5">Type</th><th class="p-3.5">Target</th>
                    <th class="p-3.5">Healthcare</th><th class="p-3.5">Status</th><th class="p-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
                <?php if (empty($apps)): ?>
                    <tr><td colspan="6" class="p-6 text-center text-[var(--text-secondary)] italic">No applications yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($apps as $a): ?>
                        <?php $ah = json_decode($a['healthcare_json'] ?? '{}', true) ?: []; ?>
                        <tr>
                            <td class="p-3.5"><span class="font-bold block"><?php echo htmlspecialchars($a['applicant_name']); ?></span><span class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($a['email']); ?></span></td>
                            <td class="p-3.5"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-400"><?php echo strtoupper($a['applicant_type']); ?></span></td>
                            <td class="p-3.5"><?php echo htmlspecialchars($a['applied_class_or_role']); ?></td>
                            <td class="p-3.5"><span class="text-[10px] text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded font-mono"><?php echo htmlspecialchars(($ah['blood_group']??'—').' | '.($ah['genotype']??'—')); ?></span></td>
                            <td class="p-3.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-<?php echo $status_colors[$a['status']] ?? 'slate'; ?>-500/10 text-<?php echo $status_colors[$a['status']] ?? 'slate'; ?>-400"><?php echo htmlspecialchars($a['status']); ?></span>
                                <?php if (!empty($a['review_note'])): ?><span class="block text-[9px] text-[var(--text-secondary)] mt-1 italic">"<?php echo htmlspecialchars($a['review_note']); ?>"</span><?php endif; ?>
                            </td>
                            <td class="p-3.5 text-right">
                                <?php if ($a['status'] === 'Pending'): ?>
                                    <button onclick="document.getElementById('reviewModal-<?php echo $a['app_uuid']; ?>').classList.remove('hidden')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold">Review</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($a['status'] === 'Pending'): ?>
                        <tr id="reviewModal-<?php echo $a['app_uuid']; ?>" class="hidden">
                            <td colspan="6" class="p-4 bg-[var(--bg-tertiary)]">
                                <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_process_application" value="1">
                                    <input type="hidden" name="app_uuid" value="<?php echo htmlspecialchars($a['app_uuid']); ?>">
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase mb-1">Review Note (optional)</label>
                                        <textarea name="review_note" rows="2" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl p-2 text-xs"></textarea>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" name="decision" value="Approved" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] rounded-lg">✓ Approve & Enroll</button>
                                        <button type="submit" name="decision" value="Rejected" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold text-[10px] rounded-lg">✕ Reject</button>
                                        <button type="button" onclick="document.getElementById('reviewModal-<?php echo $a['app_uuid']; ?>').classList.add('hidden')" class="px-4 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] text-[10px] rounded-lg">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
