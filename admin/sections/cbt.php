<?php
/**
 * SECTION: cbt — extracted from dashboard.php (Phase 1 refactor)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Cbt' => null]);
?>
<!-- SECTION: CBT -->
                <?php if ($section === 'cbt'): ?>
                    <?php
                    $cbtTests = [];
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM cbt_tests WHERE school_uuid = ? ORDER BY id DESC");
                        $stmt->execute([$school_uuid]);
                        $cbtTests = $stmt->fetchAll();
                    } catch (Exception $e) {}
                    $activeTestUuid = $_GET['test'] ?? ($cbtTests[0]['test_uuid'] ?? null);
                    $activeTestQuestions = [];
                    $activeTest = null;
                    if ($activeTestUuid) {
                        foreach ($cbtTests as $ct) { if ($ct['test_uuid'] === $activeTestUuid) { $activeTest = $ct; break; } }
                        if ($activeTest) {
                            try {
                                $qStmt = $pdo->prepare("SELECT * FROM cbt_questions WHERE test_uuid = ? AND school_uuid = ? ORDER BY id ASC");
                                $qStmt->execute([$activeTestUuid, $school_uuid]);
                                $activeTestQuestions = $qStmt->fetchAll();
                            } catch (Exception $e) {}
                        }
                    }
                    ?>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
                            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                                <i data-lucide="laptop" class="w-6 h-6 text-purple-400"></i>
                                <span>CBT Quizzes</span>
                            </h1>
                            <button onclick="document.getElementById('addCbtModal').classList.remove('hidden')" class="px-4 py-2 bg-purple-600 text-white font-bold text-xs rounded-xl">Create Test</button>
                        </div>

                        <!-- Performance Analytics (Phase C) -->
                        <?php
                        $an_rows = [];
                        try {
                            $aq = $pdo->prepare("SELECT subject_name, AVG(total_score) avg_score, COUNT(*) n FROM results WHERE school_uuid=? GROUP BY subject_name ORDER BY avg_score DESC LIMIT 15");
                            $aq->execute([$school_uuid]);
                            $an_rows = $aq->fetchAll();
                        } catch (Exception $e) {}
                        ?>
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
                            <h3 class="text-sm font-bold text-[var(--text-primary)] mb-1">Performance Analytics</h3>
                            <p class="text-[10px] text-[var(--text-secondary)] mb-4">Average result score by subject, across all results on file.</p>
                            <?php if ($an_rows): ?>
                            <canvas id="perfAnalyticsChart" height="90"></canvas>
                            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
                            <script>
                            new Chart(document.getElementById('perfAnalyticsChart'), {
                                type: 'bar',
                                data: {
                                    labels: <?php echo json_encode(array_column($an_rows, 'subject_name')); ?>,
                                    datasets: [{ label: 'Average Score', data: <?php echo json_encode(array_map(fn($r) => round((float)$r['avg_score'],1), $an_rows)); ?>, backgroundColor: 'rgba(139,92,246,0.6)' }]
                                },
                                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
                            });
                            </script>
                            <?php else: ?>
                            <p class="text-xs italic text-[var(--text-secondary)]">No results on file yet.</p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php if (empty($cbtTests)): ?>
                                <p class="text-xs text-[var(--text-secondary)] col-span-full">No tests yet — click "Create Test" to add one.</p>
                            <?php endif; ?>
                            <?php foreach ($cbtTests as $ct): ?>
                                <a href="dashboard.php?section=cbt&test=<?php echo urlencode($ct['test_uuid']); ?>" class="block bg-[var(--bg-secondary)] border <?php echo ($ct['test_uuid'] === $activeTestUuid) ? 'border-purple-500' : 'border-[var(--border-color)]'; ?> rounded-2xl p-4 space-y-2 hover:border-purple-500 transition-all">
                                    <span class="text-[10px] font-bold text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded"><?php echo htmlspecialchars($ct['subject']); ?></span>
                                    <h3 class="text-sm font-bold"><?php echo htmlspecialchars($ct['title']); ?></h3>
                                    <p class="text-xs text-[var(--text-secondary)]"><?php echo htmlspecialchars($ct['class_name']); ?> | <?php echo (int)$ct['duration_minutes']; ?> mins</p>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $ct['status']==='Approved' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'; ?>"><?php echo htmlspecialchars($ct['status']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($activeTest): ?>
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Question Bank — <?php echo htmlspecialchars($activeTest['title']); ?> (<?php echo count($activeTestQuestions); ?> questions)</h3>
                                <?php if ($activeTest['status'] !== 'Approved' && $active_role === 'School Admin'): ?>
                                    <form method="POST"><?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_approve_cbt_test" value="1">
                                        <input type="hidden" name="test_uuid" value="<?php echo htmlspecialchars($activeTestUuid); ?>">
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white font-bold text-[10px] rounded-lg">Approve Test</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="grid grid-cols-1 gap-3 bg-[var(--bg-tertiary)] p-4 rounded-xl border border-[var(--border-color)]"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_add_cbt_question" value="1">
                                <input type="hidden" name="test_uuid" value="<?php echo htmlspecialchars($activeTestUuid); ?>">
                                <textarea name="question_text" required placeholder="Question text" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></textarea>
                                <div class="grid grid-cols-2 gap-3">
                                    <input type="text" name="option_a" required placeholder="Option A" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="option_b" required placeholder="Option B" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="option_c" required placeholder="Option C" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="option_d" required placeholder="Option D" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                </div>
                                <div class="flex items-center gap-3">
                                    <label class="text-[10px] text-[var(--text-secondary)] font-bold">Correct Option</label>
                                    <select name="correct_option" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                    </select>
                                    <button type="submit" class="ml-auto px-4 py-2 bg-purple-600 text-white font-bold text-xs rounded-xl">Add Question</button>
                                </div>
                            </form>
                            <div class="space-y-2">
                                <?php foreach ($activeTestQuestions as $i => $q): ?>
                                    <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs space-y-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="font-bold"><?php echo ($i+1) . '. ' . htmlspecialchars($q['question_text']); ?></span>
                                            <form method="POST" onsubmit="return confirm('Remove this question?');"><?php echo csrf_field(); ?>
                                                <input type="hidden" name="action_delete_cbt_question" value="1">
                                                <input type="hidden" name="question_uuid" value="<?php echo htmlspecialchars($q['question_uuid']); ?>">
                                                <button type="submit" class="text-rose-400 hover:text-rose-300 shrink-0"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                            </form>
                                        </div>
                                        <p class="text-[var(--text-secondary)]">A) <?php echo htmlspecialchars($q['option_a']); ?> &nbsp; B) <?php echo htmlspecialchars($q['option_b']); ?> &nbsp; C) <?php echo htmlspecialchars($q['option_c']); ?> &nbsp; D) <?php echo htmlspecialchars($q['option_d']); ?></p>
                                        <span class="text-emerald-400 font-bold">Correct: <?php echo htmlspecialchars($q['correct_option']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Create Test Modal -->
                    <div id="addCbtModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-bold text-[var(--text-primary)]">Create CBT Test</h3>
                                <button onclick="document.getElementById('addCbtModal').classList.add('hidden')" class="text-[var(--text-secondary)]">✕</button>
                            </div>
                            <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_add_cbt_test" value="1">
                                <input type="text" name="cbt_title" required placeholder="Test title" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <input type="text" name="cbt_subject" required placeholder="Subject" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <select name="cbt_class" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <?php foreach ($roster_classes as $cl): ?>
                                        <option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="cbt_duration" value="30" min="1" placeholder="Duration (minutes)" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                <button type="submit" class="w-full py-3 bg-purple-600 text-white font-bold text-xs rounded-xl">Create Test</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
