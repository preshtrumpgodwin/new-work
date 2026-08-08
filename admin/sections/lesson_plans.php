<?php
/**
 * SECTION: Lesson Plans & Schemes
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Lesson Plans' => null]);
$is_admin = in_array($active_role, ['School Admin','Platform Manager'], true);

$plans = [];
try {
    $sql = "SELECT * FROM lesson_plans WHERE school_uuid=?";
    $params = [$school_uuid];
    if (!$is_admin) { $sql .= " AND teacher_uuid=?"; $params[] = $user_uuid; }
    $sql .= " ORDER BY created_at DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $plans = $st->fetchAll();
} catch (Exception $e) {}

$status_colors = ['Pending Review' => 'amber', 'Approved' => 'emerald', 'Rejected' => 'rose'];
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="book-marked" class="w-5 h-5 text-purple-400"></i> Lesson Plans & Schemes
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo count($plans); ?> plan(s) <?php echo $is_admin ? 'submitted school-wide' : 'submitted by you'; ?></p>
        </div>
        <button onclick="document.getElementById('addPlanModal').classList.remove('hidden')"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> New Lesson Plan
        </button>
    </div>

    <?php if (empty($plans)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-12 flex flex-col items-center justify-center gap-3 text-center">
            <i data-lucide="book-marked" class="w-10 h-10 text-[var(--text-secondary)]"></i>
            <p class="text-xs text-[var(--text-secondary)]">No lesson plans submitted yet.</p>
        </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($plans as $p): $sc = $status_colors[$p['status']] ?? 'slate'; ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-3">
            <div class="flex items-start justify-between flex-wrap gap-2">
                <div>
                    <h3 class="text-sm font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($p['topic']); ?></h3>
                    <p class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($p['subject_name']); ?> · <?php echo htmlspecialchars($p['class_name']); ?> · Week <?php echo (int)$p['week_number']; ?> · <?php echo htmlspecialchars($p['teacher_name']); ?></p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-<?php echo $sc; ?>-500/10 text-<?php echo $sc; ?>-400 border border-<?php echo $sc; ?>-500/20"><?php echo htmlspecialchars($p['status']); ?></span>
            </div>
            <details class="text-xs text-[var(--text-secondary)]">
                <summary class="cursor-pointer font-bold text-[var(--text-primary)]">View full plan</summary>
                <div class="mt-2 space-y-2">
                    <p><span class="font-bold text-[var(--text-primary)]">Objectives:</span> <?php echo nl2br(htmlspecialchars($p['behavioral_objectives'])); ?></p>
                    <p><span class="font-bold text-[var(--text-primary)]">Lesson Notes:</span> <?php echo nl2br(htmlspecialchars($p['lesson_notes'])); ?></p>
                    <?php if ($p['exercises']): ?><p><span class="font-bold text-[var(--text-primary)]">Evaluation/Class Activity:</span> <?php echo nl2br(htmlspecialchars($p['exercises'])); ?></p><?php endif; ?>
                    <?php if ($p['homework']): ?><p><span class="font-bold text-[var(--text-primary)]">Homework:</span> <?php echo nl2br(htmlspecialchars($p['homework'])); ?></p><?php endif; ?>
                </div>
            </details>
            <?php if (!empty($p['reviewer_feedback'])): ?>
                <div class="bg-[var(--bg-tertiary)] rounded-xl p-3 text-xs text-[var(--text-secondary)]">
                    <span class="font-bold text-[var(--text-primary)]">Reviewer feedback (<?php echo htmlspecialchars($p['reviewed_by'] ?? ''); ?>):</span> <?php echo htmlspecialchars($p['reviewer_feedback']); ?>
                </div>
            <?php endif; ?>
            <div class="flex items-center gap-2 pt-2 border-t border-[var(--border-color)]">
                <?php if ($is_admin && $p['status'] === 'Pending Review'): ?>
                    <button onclick="document.getElementById('reviewModal-<?php echo $p['plan_uuid']; ?>').classList.remove('hidden')" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-bold rounded-lg">Review</button>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this lesson plan?')"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_delete_lesson_plan" value="1">
                    <input type="hidden" name="plan_uuid" value="<?php echo htmlspecialchars($p['plan_uuid']); ?>">
                    <button type="submit" class="px-3 py-1.5 bg-rose-500/10 text-rose-400 text-[10px] font-bold rounded-lg border border-rose-500/20">Delete</button>
                </form>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <div id="reviewModal-<?php echo $p['plan_uuid']; ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-[var(--text-primary)]">Review: <?php echo htmlspecialchars($p['topic']); ?></h3>
                    <button onclick="document.getElementById('reviewModal-<?php echo $p['plan_uuid']; ?>').classList.add('hidden')" class="text-[var(--text-secondary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_review_lesson_plan" value="1">
                    <input type="hidden" name="plan_uuid" value="<?php echo htmlspecialchars($p['plan_uuid']); ?>">
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Feedback</label>
                        <textarea name="reviewer_feedback" rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" name="decision" value="Approved" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl">Approve</button>
                        <button type="submit" name="decision" value="Rejected" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl">Reject</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- New Lesson Plan Modal -->
<div id="addPlanModal" class="fixed flex inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="book-marked" class="w-4 h-4 text-purple-400"></i> New Lesson Plan</h3>
            <button onclick="document.getElementById('addPlanModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_lesson_plan" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Subject *</label>
                    <input type="text" name="subject_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Class *</label>
                    <select name="class_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($roster_classes as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Topic *</label>
                    <input type="text" name="topic" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Week #</label>
                    <input type="number" name="week_number" min="1" value="1" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Behavioral Objectives *</label>
                <textarea name="behavioral_objectives" required rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]" placeholder="By the end of this lesson, students should be able to..."></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Lesson Notes *</label>
                <textarea name="lesson_notes" required rows="4" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Evaluation/Class Activity</label>
                <textarea name="exercises" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]" placeholder="Describe the evaluation or class activity for this lesson"></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Homework</label>
                <textarea name="homework" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl">Submit for Review</button>
        </form>
    </div>
</div>