<?php
/**
 * SECTION: settings — Phase 2 rebuild.
 *
 * Branding, theme, and all integration keys (SMTP/SMS/WhatsApp/payment/AI)
 * are configured exclusively by the Platform Manager (see
 * platform/settings.php). The school admin can view them here but cannot
 * edit them. The school admin manages Assessment Configurations for their
 * own school — which assessments (CA1, CA2, Exam, Project, etc.) apply per
 * session/term/class, since assessments are no longer hard-coded to
 * ca1_max/ca2_max/exam_max — plus Condition of Service and School Policy,
 * which moved here from the Platform Manager.
 *
 * Every section below is its own <form> with its own submit button —
 * saving one section never touches the others.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Settings' => null]);

// Display flash messages if they exist
if (isset($_SESSION['flash_success'])) {
    echo '<div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4 mb-6 text-xs text-emerald-400 font-bold">' . htmlspecialchars($_SESSION['flash_success']) . '</div>';
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    echo '<div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 mb-6 text-xs text-rose-400 font-bold">' . htmlspecialchars($_SESSION['flash_error']) . '</div>';
    unset($_SESSION['flash_error']);
}
?>
<!-- SECTION: SETTINGS & CONFIGURATION -->
<!-- ═══════════════════════════════════════════════════════ -->
<?php if ($section === 'settings' && $active_role === 'School Admin'): ?>
    <?php
    try {
        $pdo->prepare("UPDATE subscription_reminders SET is_read=1 WHERE school_uuid=? AND is_read=0")->execute([$school_uuid]);
    } catch (Exception $e) {}
    
    $billingInvoices  = (function() use ($pdo, $school_uuid) { 
        $__st = $pdo->prepare("SELECT * FROM school_invoices WHERE school_uuid = ? ORDER BY id DESC LIMIT 10"); 
        $__st->execute([$school_uuid]); 
        return $__st->fetchAll(); 
    })();
    
    $billingReminders = (function() use ($pdo, $school_uuid) { 
        $__st = $pdo->prepare("SELECT * FROM subscription_reminders WHERE school_uuid = ? ORDER BY id DESC LIMIT 10"); 
        $__st->execute([$school_uuid]); 
        return $__st->fetchAll(); 
    })();

    // Sessions/terms/classes for the config-assignment form (per-section convention)
    $sessions = []; 
    $terms = [];
    try {
        $sq = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid=? ORDER BY id DESC");
        $sq->execute([$school_uuid]); 
        $sessions = $sq->fetchAll(PDO::FETCH_COLUMN);
        
        $tq = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid=? ORDER BY id ASC");
        $tq->execute([$school_uuid]); 
        $terms = $tq->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
    
    if (empty($sessions)) $sessions = [$school_settings['current_session'] ?? '2025/2026'];
    if (empty($terms))    $terms    = ['First Term','Second Term','Third Term'];

    // Get assessment templates from JSON in school_settings
    $assess_templates = [];
    try {
        $tpl_json = $pdo->prepare("SELECT assessment_templates_json FROM school_settings WHERE school_uuid = ?");
        $tpl_json->execute([$school_uuid]);
        $row = $tpl_json->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row['assessment_templates_json'])) {
            $assess_templates = json_decode($row['assessment_templates_json'], true);
            if (!is_array($assess_templates)) {
                $assess_templates = [];
            }
        } else {
            $assess_templates = [];
        }
    } catch (Exception $e) {
        $assess_templates = [];
        error_log("Error fetching assessment templates: " . $e->getMessage());
    }

    // Get assessment configurations from JSON in school_settings
    $assess_configs = [];
    try {
        $cfg_json = $pdo->prepare("SELECT assessment_configurations_json FROM school_settings WHERE school_uuid = ?");
        $cfg_json->execute([$school_uuid]);
        $row = $cfg_json->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row['assessment_configurations_json'])) {
            $assess_configs = json_decode($row['assessment_configurations_json'], true);
            if (!is_array($assess_configs)) {
                $assess_configs = [];
            }
        } else {
            $assess_configs = [];
        }
    } catch (Exception $e) {
        $assess_configs = [];
        error_log("Error fetching assessment configurations: " . $e->getMessage());
    }

    // Grading scale for this school only
    $grading_json = $school_settings['grading_json'] ?? '';
    $grade_scale = json_decode($grading_json, true);
    if (!is_array($grade_scale) || empty($grade_scale)) {
        $grade_scale = [
            ['min' => 75, 'max' => 100, 'grade' => 'A1', 'remark' => 'Distinction', 'points' => 4.0],
            ['min' => 70, 'max' => 74,  'grade' => 'B2', 'remark' => 'Excellent',   'points' => 3.7],
            ['min' => 65, 'max' => 69,  'grade' => 'B3', 'remark' => 'Very Good',   'points' => 3.3],
            ['min' => 60, 'max' => 64,  'grade' => 'C4', 'remark' => 'Credit',      'points' => 3.0],
            ['min' => 55, 'max' => 59,  'grade' => 'C5', 'remark' => 'Credit',      'points' => 2.7],
            ['min' => 50, 'max' => 54,  'grade' => 'C6', 'remark' => 'Credit',      'points' => 2.3],
            ['min' => 45, 'max' => 49,  'grade' => 'D7', 'remark' => 'Pass',        'points' => 2.0],
            ['min' => 40, 'max' => 44,  'grade' => 'E8', 'remark' => 'Pass',        'points' => 1.0],
            ['min' => 0,  'max' => 39,  'grade' => 'F9', 'remark' => 'Fail',        'points' => 0.0],
        ];
    }

    // Fetch Condition of Service and School Policy from the schools table
    $condition_of_service = '';
    $school_policy = '';
    try {
        $stmt = $pdo->prepare("SELECT condition_of_service_text, school_policy_text FROM schools WHERE school_uuid = ?");
        $stmt->execute([$school_uuid]);
        $school_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($school_data) {
            $condition_of_service = $school_data['condition_of_service_text'] ?? '';
            $school_policy = $school_data['school_policy_text'] ?? '';
        }
    } catch (Exception $e) {
        error_log("Error fetching policies: " . $e->getMessage());
    }

    // Get current session and term from school settings
    $current_session = $school_settings['current_session'] ?? '—';
    $current_term = $school_settings['current_term'] ?? '—';
    
    // Debug: Check what we have
    // echo '<pre>'; print_r($assess_templates); echo '</pre>';
    // echo '<pre>'; print_r($assess_configs); echo '</pre>';
    ?>
    
    <div class="space-y-6">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">School Settings</h1>

        <!-- Platform-managed profile (read-only) -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 space-y-6">
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-indigo-400 flex-1">Profile, Branding & Policies <span class="text-[10px] font-normal text-[var(--text-secondary)] normal-case">— managed by your platform manager</span></h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div><span class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">School Name</span><span class="text-[var(--text-primary)]"><?php echo htmlspecialchars($school['name'] ?? '—'); ?></span></div>
                <div><span class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Motto</span><span class="text-[var(--text-primary)]"><?php echo htmlspecialchars($school_settings['motto'] ?? '—'); ?></span></div>
                <div><span class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Theme</span><span class="text-[var(--text-primary)]"><?php echo htmlspecialchars(ucfirst($school['theme_mode'] ?? 'dark')); ?> · <?php echo htmlspecialchars($school['theme_color'] ?? '#4F46E5'); ?></span></div>
                <div><span class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Current Session / Term</span><span class="text-[var(--text-primary)]"><?php echo htmlspecialchars(($school_settings['current_session'] ?? '—') . ' · ' . ($school_settings['current_term'] ?? '—')); ?></span></div>
            </div>
            <p class="text-[10px] text-[var(--text-secondary)] italic">To change branding, SMTP/SMS/WhatsApp, payment gateways, or AI configuration, please contact your platform manager — these are no longer editable from the school admin portal. Condition of Service and School Policy are managed below.</p>
        </div>

        <!-- Assessment Configuration (school-admin managed) -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 space-y-6">
            <div>
                <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-cyan-400">Assessment Configuration</h3>
                <p class="text-xs text-[var(--text-secondary)] mt-2">Define which assessments (CA, Exam, Project, Mock, etc.) apply to each session, term, and class, and their max scores. Results and report cards use this instead of a fixed CA1/CA2/Exam split.</p>
            </div>

            <!-- Templates (JSON-based) -->
            <form method="POST" class="bg-[var(--bg-tertiary)] p-4 rounded-xl border border-[var(--border-color)] space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_assessment_templates" value="1">
                <h4 class="text-xs font-bold text-[var(--text-primary)]">Assessment Templates</h4>
                <p class="text-[10px] text-[var(--text-secondary)]">The types of assessments your school uses.</p>
                <div id="templateRows" class="space-y-2">
                    <?php if (!empty($assess_templates)): ?>
                        <?php foreach ($assess_templates as $index => $template): ?>
                        <div class="template-row flex gap-2 items-center p-2 bg-[var(--bg-primary)] rounded-lg border border-[var(--border-color)]">
                            <input type="text" name="template_name[]" value="<?php echo htmlspecialchars($template['name'] ?? ''); ?>" placeholder="Assessment name" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]" required>
                            <input type="text" name="template_desc[]" value="<?php echo htmlspecialchars($template['description'] ?? ''); ?>" placeholder="Description (optional)" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">
                            <button type="button" onclick="this.closest('.template-row').remove()" class="text-rose-400 hover:text-rose-300 text-[10px] px-2" title="Remove from form">✕</button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="template-row flex gap-2 items-center p-2 bg-[var(--bg-primary)] rounded-lg border border-[var(--border-color)]">
                            <input type="text" name="template_name[]" value="" placeholder="Assessment name" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]" required>
                            <input type="text" name="template_desc[]" value="" placeholder="Description (optional)" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">
                            <button type="button" onclick="this.closest('.template-row').remove()" class="text-rose-400 hover:text-rose-300 text-[10px] px-2">✕</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addAssessmentTemplateRow()" class="px-3 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/20 rounded-lg text-[10px] font-bold hover:bg-cyan-600/30">+ Add Template</button>
                <div><button type="submit" class="px-6 py-2.5 bg-cyan-600 text-white font-bold text-xs rounded-xl">Save Templates</button></div>
            </form>

            <!-- Configurations (JSON-based) -->
            <form method="POST" class="bg-[var(--bg-tertiary)] p-4 rounded-xl border border-[var(--border-color)] space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_assessment_config" value="1">
                <h4 class="text-xs font-bold text-[var(--text-primary)]">Assign an Assessment to a Term/Class</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Session</label>
                        <div class="w-full bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-2 py-1.5 text-xs text-emerald-400 font-mono">
                            <?php echo htmlspecialchars($current_session); ?>
                        </div>
                        <input type="hidden" name="config_session" value="<?php echo htmlspecialchars($current_session); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Term</label>
                        <div class="w-full bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-2 py-1.5 text-xs text-emerald-400 font-mono">
                            <?php echo htmlspecialchars($current_term); ?>
                        </div>
                        <input type="hidden" name="config_term" value="<?php echo htmlspecialchars($current_term); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Class (blank = all)</label>
                        <select name="config_class" class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                            <option value="">All Classes</option>
                            <?php foreach (($roster_classes ?? []) as $cn): ?><option value="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Assessment</label>
                        <select name="config_template" class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]" required>
                            <option value="">— Select —</option>
                            <?php if (!empty($assess_templates)): ?>
                                <?php foreach ($assess_templates as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No templates defined yet</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Order</label>
                        <input type="number" name="config_order" value="0" class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1">Max Score</label>
                        <input type="number" step="0.01" name="config_max_score" value="100" class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    </div>
                    <div class="flex items-end pb-1.5">
                        <label class="flex items-center gap-2 text-[10px] font-bold uppercase"><input type="checkbox" name="config_required" checked class="w-3.5 h-3.5 accent-cyan-600"> Required</label>
                    </div>
                </div>
                <div><button type="submit" class="px-6 py-2.5 bg-cyan-600 text-white font-bold text-xs rounded-xl">Add / Update Configuration</button></div>
            </form>

            <!-- Configurations Table -->
            <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                        <tr><th class="p-2">Session</th><th class="p-2">Term</th><th class="p-2">Class</th><th class="p-2">Order</th><th class="p-2">Assessment</th><th class="p-2">Max</th><th class="p-2 text-center">Required</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border-color)]">
                        <?php if (!empty($assess_configs)): ?>
                            <?php foreach ($assess_configs as $index => $cfg): ?>
                            <tr>
                                <td class="p-2 font-mono"><?php echo htmlspecialchars($cfg['session_name']); ?></td>
                                <td class="p-2 font-mono"><?php echo htmlspecialchars($cfg['term_name']); ?></td>
                                <td class="p-2 font-mono"><?php echo htmlspecialchars($cfg['class_name'] ?? 'All Classes'); ?></td>
                                <td class="p-2 text-center"><?php echo (int)$cfg['assessment_order']; ?></td>
                                <td class="p-2 font-bold"><?php echo htmlspecialchars($cfg['template_name']); ?></td>
                                <td class="p-2 font-mono"><?php echo (float)$cfg['max_score']; ?></td>
                                <td class="p-2 text-center"><?php echo $cfg['is_required'] ? '✅' : '⬜'; ?></td>
                                <td class="p-2 text-right">
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this configuration?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_delete_assessment_config" value="1">
                                        <input type="hidden" name="config_session" value="<?php echo htmlspecialchars($cfg['session_name']); ?>">
                                        <input type="hidden" name="config_term" value="<?php echo htmlspecialchars($cfg['term_name']); ?>">
                                        <input type="hidden" name="config_class" value="<?php echo htmlspecialchars($cfg['class_name'] ?? ''); ?>">
                                        <input type="hidden" name="config_template" value="<?php echo htmlspecialchars($cfg['template_name']); ?>">
                                        <button type="submit" class="px-2 py-0.5 bg-rose-500/10 text-rose-400 rounded text-[10px] font-bold">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="p-4 text-center text-[var(--text-secondary)] italic">No configurations yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Billing Notices (read-only, informational) -->
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-amber-400">Platform Billing</h3>
            <?php if (!empty($billingReminders)): ?>
                <div class="space-y-2">
                    <?php foreach ($billingReminders as $rem): ?>
                        <div class="flex items-start gap-3 p-3 rounded-xl border <?php echo $rem['is_read'] ? 'bg-[var(--bg-tertiary)] border-[var(--border-color)]' : 'bg-amber-500/5 border-amber-500/20'; ?>">
                            <i data-lucide="bell" class="w-4 h-4 mt-0.5 <?php echo $rem['is_read'] ? 'text-[var(--text-secondary)]' : 'text-amber-400'; ?>"></i>
                            <div>
                                <p class="text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($rem['message']); ?></p>
                                <span class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($rem['date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($billingInvoices)): ?>
                <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Invoice</th><th class="p-3">Plan</th><th class="p-3">Amount</th><th class="p-3">Due</th><th class="p-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-[var(--border-color)]">
                            <?php foreach ($billingInvoices as $inv): ?>
                                <tr>
                                    <td class="p-3 font-mono font-bold"><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($inv['plan']); ?></td>
                                    <td class="p-3 font-mono text-emerald-400 font-bold">₦<?php echo number_format($inv['amount'], 2); ?></td>
                                    <td class="p-3 font-mono"><?php echo htmlspecialchars($inv['due_date']); ?></td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $inv['status']==='Paid' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>"><?php echo htmlspecialchars($inv['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grading Scale (school-admin managed) -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 space-y-4 mt-6">
            <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-yellow-400">Grading Scale Configuration</h3>
            <p class="text-xs text-[var(--text-secondary)]">Define grade boundaries, letter grades, and remarks. The grading engine uses these settings for all result calculations.</p>
            <form method="POST" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_grading_scale" value="1">
                <div id="gradeScaleRows" class="space-y-2">
                    <?php foreach ($grade_scale as $index => $band): ?>
                    <div class="grade-row grid grid-cols-5 gap-2 items-center p-2 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border-color)]">
                        <input type="number" name="grade_min[<?php echo $index; ?>]" value="<?php echo $band['min']; ?>" placeholder="Min" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]" required>
                        <input type="number" name="grade_max[<?php echo $index; ?>]" value="<?php echo $band['max']; ?>" placeholder="Max" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]" required>
                        <input type="text" name="grade_letter[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($band['grade']); ?>" placeholder="Grade" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)] font-mono" required>
                        <input type="text" name="grade_remark[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($band['remark']); ?>" placeholder="Remark" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]" required>
                        <input type="number" step="0.1" name="grade_points[<?php echo $index; ?>]" value="<?php echo $band['points']; ?>" placeholder="Points" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)] font-mono" required>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="addGradeRow()" class="px-3 py-1.5 bg-yellow-600/20 text-yellow-400 border border-yellow-500/20 rounded-lg text-[10px] font-bold hover:bg-yellow-600/30">+ Add Grade Band</button>
                <div><button type="submit" class="px-6 py-2.5 bg-yellow-600 text-white font-bold text-xs rounded-xl">Save Grading Scale</button></div>
            </form>
        </div>

        <!-- Policies (school-admin managed) -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 space-y-4 mt-6">
            <h3 class="text-sm font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-2 text-rose-400">Policies</h3>
            
            <!-- Condition of Service -->
            <form method="POST" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_condition_of_service" value="1">
                <label class="block text-[10px] font-bold uppercase mb-1">Condition of Service</label>
                <textarea name="condition_of_service_text" rows="4" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)] font-mono"><?php echo htmlspecialchars($condition_of_service); ?></textarea>
                <button type="submit" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl">Save Condition of Service</button>
            </form>
            
            <!-- School Policy -->
            <form method="POST" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_school_policy" value="1">
                <label class="block text-[10px] font-bold uppercase mb-1">School Policy</label>
                <textarea name="school_policy_text" rows="4" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)] font-mono"><?php echo htmlspecialchars($school_policy); ?></textarea>
                <button type="submit" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl">Save School Policy</button>
            </form>
        </div>
    </div>
    
    <script>
    function addAssessmentTemplateRow() {
        const wrap = document.createElement('div');
        wrap.className = 'template-row flex gap-2 items-center p-2 bg-[var(--bg-primary)] rounded-lg border border-[var(--border-color)]';
        wrap.innerHTML = '<input type="text" name="template_name[]" placeholder="Assessment name" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]" required>' +
            '<input type="text" name="template_desc[]" placeholder="Description (optional)" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">' +
            '<button type="button" onclick="this.closest(\'.template-row\').remove()" class="text-rose-400 hover:text-rose-300 text-[10px] px-2">✕</button>';
        document.getElementById('templateRows').appendChild(wrap);
    }

    let gradeRowCount = <?php echo count($grade_scale); ?>;
    function addGradeRow() {
        const container = document.getElementById('gradeScaleRows');
        const row = document.createElement('div');
        row.className = 'grade-row grid grid-cols-5 gap-2 items-center p-2 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border-color)]';
        row.innerHTML = '<input type="number" name="grade_min[' + gradeRowCount + ']" placeholder="Min" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]" required>' +
            '<input type="number" name="grade_max[' + gradeRowCount + ']" placeholder="Max" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]" required>' +
            '<input type="text" name="grade_letter[' + gradeRowCount + ']" placeholder="Grade" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)] font-mono" required>' +
            '<input type="text" name="grade_remark[' + gradeRowCount + ']" placeholder="Remark" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]" required>' +
            '<input type="number" step="0.1" name="grade_points[' + gradeRowCount + ']" placeholder="Points" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)] font-mono" required>';
        container.appendChild(row);
        gradeRowCount++;
    }
    </script>
<?php endif; ?>