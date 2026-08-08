<?php
/**
 * SECTION: OMR Sheets & Marking
 * Sheets → Answer Keys → Mark (real per-question comparison) → Print
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'OMR' => null]);
$tab = safe_str($_GET['tab'] ?? 'sheets');

$omrSheets = [];
try {
    $st = $pdo->prepare("SELECT * FROM omr_sheets WHERE school_uuid=? ORDER BY created_at DESC");
    $st->execute([$school_uuid]);
    $omrSheets = $st->fetchAll();
} catch (Exception $e) {}

$omrStds = [];
try {
    $ss = $pdo->prepare("SELECT student_uuid, name, roll_number, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $ss->execute([$school_uuid]);
    $omrStds = $ss->fetchAll();
} catch (Exception $e) {}

$omrEvals = [];
try {
    $ev = $pdo->prepare("SELECT * FROM omr_evaluations WHERE school_uuid=? ORDER BY evaluated_at DESC LIMIT 30");
    $ev->execute([$school_uuid]);
    $omrEvals = $ev->fetchAll();
} catch (Exception $e) {}

$sel_sheet_uuid = safe_str($_GET['sheet_uuid'] ?? '');
$sel_sheet = null;
$existing_keys = [];
foreach ($omrSheets as $sh) { if ($sh['sheet_uuid'] === $sel_sheet_uuid) { $sel_sheet = $sh; break; } }
if (!$sel_sheet && !empty($omrSheets)) $sel_sheet = $omrSheets[0];
if ($sel_sheet) {
    $kq = $pdo->prepare("SELECT question_number, correct_option FROM omr_answer_keys WHERE school_uuid=? AND sheet_uuid=? ORDER BY question_number ASC");
    $kq->execute([$school_uuid, $sel_sheet['sheet_uuid']]);
    foreach ($kq->fetchAll() as $r) { $existing_keys[(int)$r['question_number']] = $r['correct_option']; }
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)] flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                <i data-lucide="scan-line" class="w-6 h-6 text-cyan-400"></i>
                <span>OMR Sheets & Marking</span>
            </h1>
            <p class="text-xs text-[var(--text-secondary)]">Create sheets, set answer keys, mark scripts, print bubble sheets.</p>
        </div>
        <div class="flex bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl p-1 text-xs">
            <?php foreach (['sheets'=>'Sheets','keys'=>'Answer Keys','print'=>'Generate & Print','scan'=>'Scan & Auto-Mark','mark'=>'Manual Mark','history'=>'History'] as $tk=>$tl): ?>
            <a href="dashboard.php?section=omr&tab=<?php echo $tk; ?><?php echo $sel_sheet ? '&sheet_uuid='.$sel_sheet['sheet_uuid'] : ''; ?>"
               class="px-3 py-1.5 rounded-lg font-bold <?php echo $tab===$tk ? 'bg-cyan-600 text-white' : 'text-[var(--text-secondary)]'; ?>"><?php echo $tl; ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($tab === 'sheets'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">New OMR Sheet</h3>
                <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_create_omr_sheet" value="1">
                    <div><label class="block text-[10px] font-bold uppercase mb-1">Exam Title *</label>
                        <input type="text" name="exam_title" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] font-bold uppercase mb-1">Class *</label>
                        <select name="class_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($roster_classes as $rc): ?><option value="<?php echo htmlspecialchars($rc); ?>"><?php echo htmlspecialchars($rc); ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] font-bold uppercase mb-1">Total Questions</label>
                        <input type="number" name="total_questions" value="20" min="1" max="100" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs rounded-xl">Create Sheet</button>
                </form>
            </div>
            <div class="lg:col-span-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Exam</th><th class="p-3">Class</th><th class="p-3">Qs</th><th class="p-3">Keys Set</th><th class="p-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-[var(--border-color)]">
                        <?php if (empty($omrSheets)): ?><tr><td colspan="5" class="p-6 text-center italic text-[var(--text-secondary)]">No sheets created yet.</td></tr><?php endif; ?>
                        <?php foreach ($omrSheets as $sh):
                            $kc = $pdo->prepare("SELECT COUNT(*) FROM omr_answer_keys WHERE sheet_uuid=?"); $kc->execute([$sh['sheet_uuid']]); $kcount = (int)$kc->fetchColumn();
                        ?>
                        <tr>
                            <td class="p-3 font-bold"><?php echo htmlspecialchars($sh['exam_title']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($sh['class_name']); ?></td>
                            <td class="p-3 font-mono"><?php echo (int)$sh['total_questions']; ?></td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $kcount >= $sh['total_questions'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'; ?>"><?php echo $kcount; ?>/<?php echo (int)$sh['total_questions']; ?></span></td>
                            <td class="p-3 text-right"><a href="dashboard.php?section=omr&tab=keys&sheet_uuid=<?php echo $sh['sheet_uuid']; ?>" class="text-[10px] font-bold text-indigo-400">Set Keys →</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'keys'): ?>
        <?php if (!$sel_sheet): ?>
            <p class="text-xs text-[var(--text-secondary)] p-6 text-center">Create a sheet first.</p>
        <?php else: ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($sel_sheet['exam_title']); ?> — Answer Key (<?php echo (int)$sel_sheet['total_questions']; ?> questions)</h3>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_answer_key" value="1">
                <input type="hidden" name="sheet_uuid" value="<?php echo htmlspecialchars($sel_sheet['sheet_uuid']); ?>">
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                    <?php for ($q = 1; $q <= (int)$sel_sheet['total_questions']; $q++): ?>
                    <div class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-center">
                        <span class="text-[9px] font-bold text-[var(--text-secondary)] block mb-1">Q<?php echo $q; ?></span>
                        <select name="key[<?php echo $q; ?>]" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg text-xs text-center py-1">
                            <?php foreach (['A','B','C','D'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo (($existing_keys[$q] ?? '')===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs rounded-xl">Save Answer Key</button>
            </form>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'mark'): ?>
        <?php if (!$sel_sheet || empty($existing_keys)): ?>
            <p class="text-xs text-[var(--text-secondary)] p-6 text-center">Select a sheet with a saved answer key first.</p>
        <?php else: ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Mark Script — <?php echo htmlspecialchars($sel_sheet['exam_title']); ?></h3>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_omr_evaluation" value="1">
                <input type="hidden" name="sheet_uuid" value="<?php echo htmlspecialchars($sel_sheet['sheet_uuid']); ?>">
                <input type="hidden" name="exam_title" value="<?php echo htmlspecialchars($sel_sheet['exam_title']); ?>">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Student</label>
                    <select name="student_uuid" onchange="document.getElementById('omrStudentName').value=this.options[this.selectedIndex].text" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($omrStds as $st2): ?><option value="<?php echo $st2['student_uuid']; ?>"><?php echo htmlspecialchars($st2['name']); ?> (<?php echo $st2['class']; ?>)</option><?php endforeach; ?>
                    </select>
                    <input type="hidden" name="student_name" id="omrStudentName" value="<?php echo !empty($omrStds) ? htmlspecialchars($omrStds[0]['name']) : ''; ?>">
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                    <?php foreach ($existing_keys as $qn => $correct): ?>
                    <div class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-2 text-center">
                        <span class="text-[9px] font-bold text-[var(--text-secondary)] block mb-1">Q<?php echo $qn; ?></span>
                        <select name="answer[<?php echo $qn; ?>]" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg text-xs text-center py-1">
                            <option value="">—</option>
                            <?php foreach (['A','B','C','D'] as $opt): ?><option value="<?php echo $opt; ?>"><?php echo $opt; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl">Grade & Save</button>
            </form>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'print'): ?>
        <?php if (!$sel_sheet): ?>
            <p class="text-xs text-[var(--text-secondary)] p-6 text-center">Create a sheet first.</p>
        <?php else:
            $stripq = $pdo->prepare("SELECT * FROM omr_sheet_students WHERE sheet_uuid=? ORDER BY student_name ASC");
            $stripq->execute([$sel_sheet['sheet_uuid']]);
            $strips = $stripq->fetchAll();
        ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($sel_sheet['exam_title']); ?> — <?php echo htmlspecialchars($sel_sheet['class_name']); ?></h3>
            <p class="text-xs text-[var(--text-secondary)]">Step 1: generate a printable strip for every active student in this class (each gets a unique hidden ID code baked in). Step 2: print — 3 strips per A4, cut along the dashed lines and hand out.</p>
            <div class="flex items-center gap-3 flex-wrap">
                <form method="POST"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_generate_omr_strips" value="1">
                    <input type="hidden" name="sheet_uuid" value="<?php echo htmlspecialchars($sel_sheet['sheet_uuid']); ?>">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl"><i data-lucide="users" class="w-4 h-4 inline mr-1"></i> Generate Strips for <?php echo htmlspecialchars($sel_sheet['class_name']); ?></button>
                </form>
                <?php if (!empty($strips)): ?>
                <a href="print_omr_strips.php?sheet_uuid=<?php echo urlencode($sel_sheet['sheet_uuid']); ?>" target="_blank" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs rounded-xl"><i data-lucide="printer" class="w-4 h-4 inline mr-1"></i> Print <?php echo count($strips); ?> Strip(s)</a>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-2">Student</th><th class="p-2">Roll</th><th class="p-2">Strip ID</th><th class="p-2">Scanned?</th></tr></thead>
                    <tbody class="divide-y divide-[var(--border-color)]">
                        <?php if (empty($strips)): ?><tr><td colspan="4" class="p-4 text-center italic text-[var(--text-secondary)]">No strips generated yet.</td></tr><?php endif; ?>
                        <?php foreach ($strips as $st): ?>
                        <tr>
                            <td class="p-2 font-semibold"><?php echo htmlspecialchars($st['student_name']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($st['roll_number']); ?></td>
                            <td class="p-2 font-mono text-cyan-400"><?php echo htmlspecialchars($st['serial_code']); ?></td>
                            <td class="p-2"><?php echo $st['scanned_at'] ? '<span class="text-emerald-400 font-bold">✓ '.htmlspecialchars($st['scanned_at']).'</span>' : '<span class="text-[var(--text-secondary)] italic">Not yet</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'scan'): ?>
        <?php if (!$sel_sheet || empty($existing_keys)): ?>
            <p class="text-xs text-[var(--text-secondary)] p-6 text-center">Select a sheet with a saved answer key first (and generate + print strips under "Generate & Print").</p>
        <?php else: ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Scan & Auto-Mark — <?php echo htmlspecialchars($sel_sheet['exam_title']); ?></h3>
            <p class="text-xs text-[var(--text-secondary)]">Upload photos or scans of the cut strips (one student per photo, or select several at once). The system reads each strip's hidden ID and bubbled answers automatically and scores it against the saved answer key.</p>
            <form method="POST" enctype="multipart/form-data" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_upload_omr_scan" value="1">
                <input type="hidden" name="sheet_uuid" value="<?php echo htmlspecialchars($sel_sheet['sheet_uuid']); ?>">
                <input type="file" name="scan_files[]" accept="image/jpeg,image/png" multiple required class="w-full text-xs text-[var(--text-primary)]">
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl"><i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> Upload & Auto-Mark</button>
            </form>

            <?php if (!empty($omr_scan_results ?? [])): ?>
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-2">File</th><th class="p-2">Result</th><th class="p-2">Score</th><th class="p-2">Confidence</th></tr></thead>
                    <tbody class="divide-y divide-[var(--border-color)]">
                        <?php foreach ($omr_scan_results as $r): ?>
                        <tr>
                            <td class="p-2 font-mono text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($r['file']); ?></td>
                            <?php if ($r['ok']): ?>
                            <td class="p-2 font-semibold text-emerald-400"><?php echo htmlspecialchars($r['student_name']); ?></td>
                            <td class="p-2 font-mono"><?php echo $r['correct']; ?>/<?php echo $r['total']; ?> (<?php echo $r['pct']; ?>%)</td>
                            <td class="p-2">
                                <?php if ($r['confidence'] === 'high' && empty($r['flagged'])): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400">High</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400">Review<?php echo !empty($r['flagged']) ? ' — Q' . implode(', Q', $r['flagged']) : ''; ?></span>
                                <?php endif; ?>
                            </td>
                            <?php else: ?>
                            <td class="p-2 text-rose-400" colspan="3"><?php echo htmlspecialchars($r['error']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-[10px] text-[var(--text-secondary)] mt-2">"Review" or failed rows: double-check that scan in "Manual Mark" or "History" and correct by hand if needed — auto-marking flags anything it isn't confident about rather than guessing silently.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'history'): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Student</th><th class="p-3">Exam</th><th class="p-3">Score</th><th class="p-3">Date</th></tr></thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php if (empty($omrEvals)): ?><tr><td colspan="4" class="p-6 text-center italic text-[var(--text-secondary)]">No evaluations yet.</td></tr><?php endif; ?>
                    <?php foreach ($omrEvals as $ev): ?>
                    <tr>
                        <td class="p-3 font-bold"><?php echo htmlspecialchars($ev['student_name']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($ev['exam_title']); ?></td>
                        <td class="p-3 font-mono text-cyan-400 font-bold"><?php echo $ev['correct_count']; ?>/<?php echo $ev['total_questions']; ?> (<?php echo $ev['percentage_score']; ?>%)</td>
                        <td class="p-3 text-[var(--text-secondary)]"><?php echo $ev['evaluated_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
