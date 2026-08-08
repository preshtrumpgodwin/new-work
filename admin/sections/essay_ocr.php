<?php
/**
 * SECTION: AI Essay & OCR — AI provider configuration + essay marking tool
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'AI Essay & OCR' => null]);
$is_admin = $active_role === 'School Admin';

$students = [];
try {
    $st = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $st->execute([$school_uuid]);
    $students = $st->fetchAll();
} catch (Exception $e) {}

$evaluations = [];
try {
    $ev = $pdo->prepare("SELECT * FROM essay_evaluations WHERE school_uuid=? ORDER BY evaluated_at DESC LIMIT 20");
    $ev->execute([$school_uuid]);
    $evaluations = $ev->fetchAll();
} catch (Exception $e) {}

$has_key = !empty($school_settings['ai_api_key'] ?? '');
?>
<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <i data-lucide="sparkles" class="w-5 h-5 text-violet-400"></i> AI Essay & OCR
        </h1>
        <p class="text-xs text-[var(--text-secondary)] mt-1">AI-assisted essay marking, plus provider configuration.</p>
    </div>

    <?php if ($is_admin): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
        <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="settings-2" class="w-4 h-4 text-violet-400"></i> AI Configuration</h3>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_save_ai_config" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Provider</label>
                    <select name="ai_provider" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="openai" <?php echo ($school_settings['ai_provider']??'')==='openai'?'selected':''; ?>>OpenAI</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Model</label>
                    <input type="text" name="ai_model" value="<?php echo htmlspecialchars($school_settings['ai_model'] ?? 'gpt-4o-mini'); ?>" placeholder="gpt-4o-mini" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">API Key</label>
                <input type="password" name="ai_api_key" value="<?php echo htmlspecialchars($school_settings['ai_api_key'] ?? ''); ?>" placeholder="sk-..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Essay Marking Prompt (optional override)</label>
                <textarea name="ai_essay_prompt" rows="2" placeholder="Default: strict examiner marking against the guide, JSON response." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($school_settings['ai_essay_prompt'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="px-4 py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold text-xs rounded-xl">Save Configuration</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!$has_key): ?>
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-400 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span><?php echo $is_admin ? 'Add an API key above to enable AI essay marking.' : 'AI essay marking is not configured yet — ask your School Admin to add an API key.'; ?></span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Mark an Essay</h3>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_evaluate_essay" value="1">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Student</label>
                    <select name="student_uuid" onchange="const o=this.options[this.selectedIndex]; document.getElementById('essayStudentName').value = o.dataset.name || '';" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="">Select student...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['student_uuid']); ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['class']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="essayStudentName" name="student_name">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Assignment Title *</label>
                        <input type="text" name="assignment_title" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Max Score</label>
                        <input type="number" name="max_score" value="100" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Marking Guide</label>
                    <textarea name="marking_guide" rows="3" placeholder="Key points the essay should cover..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Essay Text *</label>
                    <textarea name="essay_text" required rows="6" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
                </div>
                <button type="submit" <?php echo !$has_key?'disabled':''; ?> class="w-full py-3 bg-violet-600 hover:bg-violet-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-xs rounded-xl flex items-center justify-center gap-2">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> Evaluate with AI
                </button>
            </form>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
            <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]"><h3 class="text-xs font-bold uppercase">Recent Evaluations</h3></div>
            <div class="max-h-[500px] overflow-y-auto divide-y divide-[var(--border-color)]">
                <?php if (empty($evaluations)): ?>
                    <p class="text-xs text-[var(--text-secondary)] p-6 text-center">No essays evaluated yet.</p>
                <?php endif; ?>
                <?php foreach ($evaluations as $e): ?>
                <div class="p-4 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($e['assignment_title']); ?></span>
                        <span class="font-mono font-bold text-violet-400"><?php echo (float)$e['score']; ?>/<?php echo (int)$e['max_score']; ?></span>
                    </div>
                    <p class="text-[var(--text-secondary)]"><?php echo htmlspecialchars($e['student_name']); ?> · Grammar: <?php echo htmlspecialchars($e['grammar_rating']); ?> · Coherence: <?php echo htmlspecialchars($e['coherence_rating']); ?></p>
                    <p class="text-[var(--text-secondary)] italic"><?php echo htmlspecialchars($e['feedback_comments']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
