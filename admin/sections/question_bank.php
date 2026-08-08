<?php
/**
 * SECTION: Question Bank (Phase C)
 * Store questions once (objective or theory), reuse them either as CBT
 * items or as a typed, printable exam paper — covers "past question bank
 * for both CBT and printed exams, typeable for non-CBT tests".
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Question Bank' => null]);

$qb_subject = $_GET['qb_subject'] ?? '';
$qb_class   = $_GET['qb_class'] ?? '';
$questions = [];
try {
    $sql = "SELECT * FROM question_bank WHERE school_uuid=?";
    $params = [$school_uuid];
    if ($qb_subject !== '') { $sql .= " AND subject_name=?"; $params[] = $qb_subject; }
    if ($qb_class !== '')   { $sql .= " AND (class_name=? OR class_name IS NULL)"; $params[] = $qb_class; }
    $sql .= " ORDER BY subject_name, created_at DESC LIMIT 200";
    $q = $pdo->prepare($sql);
    $q->execute($params);
    $questions = $q->fetchAll();
} catch (Exception $e) {}

$printed_papers = [];
try {
    $pq = $pdo->prepare("SELECT * FROM printed_exam_papers WHERE school_uuid=? ORDER BY created_at DESC LIMIT 30");
    $pq->execute([$school_uuid]);
    $printed_papers = $pq->fetchAll();
} catch (Exception $e) {}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="book-open-check" class="w-6 h-6 text-violet-400"></i><span>Question Bank</span>
    </h1>

    <!-- Add question -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
        <h3 class="text-sm font-bold text-[var(--text-primary)] mb-3">Add a Question</h3>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3" id="qbForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_question" value="1">
            <div><label class="block text-[10px] font-bold uppercase mb-1">Subject</label><input type="text" name="subject_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Class (optional)</label>
                <select name="class_name" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">— Any —</option>
                    <?php foreach (($roster_classes ?? []) as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Type</label>
                <select name="question_type" id="qbType" onchange="document.getElementById('qbOptions').style.display=this.value==='objective'?'grid':'none'" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="objective">Objective (CBT/MCQ)</option>
                    <option value="theory">Theory (typed, printed-exam only)</option>
                </select>
            </div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Topic / Year</label><div class="flex gap-2"><input type="text" name="topic" placeholder="Topic" class="w-1/2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"><input type="text" name="year" placeholder="Year" class="w-1/2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div></div>
            <div class="md:col-span-2"><label class="block text-[10px] font-bold uppercase mb-1">Question Text</label><textarea name="question_text" required rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-xs text-[var(--text-primary)]"></textarea></div>
            <div id="qbOptions" class="md:col-span-2 grid grid-cols-2 gap-2">
                <input type="text" name="option_a" placeholder="Option A" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <input type="text" name="option_b" placeholder="Option B" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <input type="text" name="option_c" placeholder="Option C" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <input type="text" name="option_d" placeholder="Option D" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <select name="correct_option" class="col-span-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">Correct option…</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-[10px] text-[var(--text-secondary)] md:col-span-2"><input type="checkbox" name="for_printed_exam" value="1" checked> Also usable in a typed/printed exam paper</label>
            <div class="md:col-span-2"><button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold px-4 py-2 rounded-xl">Add Question</button></div>
        </form>
    </div>

    <!-- Filter + list -->
    <form method="GET" class="flex gap-3">
        <input type="hidden" name="section" value="question_bank">
        <input type="text" name="qb_subject" value="<?php echo htmlspecialchars($qb_subject); ?>" placeholder="Filter by subject" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
        <button class="px-3 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs text-[var(--text-secondary)]">Filter</button>
    </form>

    <form method="POST" id="printedPaperForm" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action_build_printed_paper" value="1">
        <div class="p-4 border-b border-[var(--border-color)] flex flex-wrap gap-3 items-end">
            <div><label class="block text-[10px] font-bold uppercase mb-1">Printed Paper Title</label><input type="text" name="paper_title" placeholder="e.g. SS2 Physics Mock Exam" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[10px] font-bold uppercase mb-1">Instructions</label><input type="text" name="instructions" placeholder="e.g. Answer all questions" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <input type="hidden" name="paper_subject" value="<?php echo htmlspecialchars($qb_subject); ?>">
            <input type="hidden" name="paper_class" value="<?php echo htmlspecialchars($qb_class); ?>">
            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl">Build Printed Paper from Checked Questions</button>
        </div>
        <div class="divide-y divide-[var(--border-color)] max-h-[500px] overflow-y-auto">
            <?php foreach ($questions as $qn): ?>
            <label class="p-3 flex items-start gap-3 cursor-pointer hover:bg-[var(--bg-tertiary)]">
                <input type="checkbox" name="question_uuids[]" value="<?php echo htmlspecialchars($qn['question_uuid']); ?>" class="mt-1">
                <div class="flex-1">
                    <p class="text-xs text-[var(--text-primary)]"><b><?php echo htmlspecialchars($qn['subject_name']); ?></b> — <?php echo htmlspecialchars($qn['question_text']); ?></p>
                    <p class="text-[9px] text-[var(--text-secondary)]"><?php echo ucfirst($qn['question_type']); ?> <?php echo $qn['topic'] ? '· ' . htmlspecialchars($qn['topic']) : ''; ?> <?php echo $qn['year'] ? '· ' . htmlspecialchars($qn['year']) : ''; ?></p>
                </div>
                <button type="button" onclick="event.preventDefault(); if(confirm('Delete this question?')){document.getElementById('delQ_<?php echo $qn['question_uuid']; ?>').submit();}" class="text-[10px] text-rose-400 font-bold">Delete</button>
            </label>
            <?php endforeach; ?>
            <?php if (empty($questions)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">No questions yet — add one above.</p><?php endif; ?>
        </div>
    </form>
    <?php foreach ($questions as $qn): ?>
    <form method="POST" id="delQ_<?php echo $qn['question_uuid']; ?>" class="hidden"><?php echo csrf_field(); ?><input type="hidden" name="action_delete_question" value="1"><input type="hidden" name="question_uuid" value="<?php echo htmlspecialchars($qn['question_uuid']); ?>"></form>
    <?php endforeach; ?>

    <!-- Printed papers -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-[var(--border-color)]"><h3 class="text-sm font-bold text-[var(--text-primary)]">Printed Exam Papers</h3></div>
        <div class="divide-y divide-[var(--border-color)]">
            <?php foreach ($printed_papers as $pp): ?>
            <div class="p-3 flex items-center justify-between">
                <span class="text-xs text-[var(--text-primary)]"><b><?php echo htmlspecialchars($pp['title']); ?></b> <span class="text-[var(--text-secondary)]">— <?php echo count(explode(',', $pp['question_uuids'])); ?> questions</span></span>
                <a href="print_exam_paper.php?paper_uuid=<?php echo urlencode($pp['paper_uuid']); ?>" target="_blank" class="text-[10px] text-violet-400 font-bold hover:underline">Print</a>
            </div>
            <?php endforeach; ?>
            <?php if (empty($printed_papers)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">No printed papers built yet.</p><?php endif; ?>
        </div>
    </div>
</div>
