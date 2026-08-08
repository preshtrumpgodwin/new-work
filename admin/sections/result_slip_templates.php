<?php
/**
 * SECTION: Result Slip Templates (Phase C — school admin side)
 * School admin picks one of the platform's base templates as their active
 * result slip, or duplicates one and edits it on a true A4 canvas — free
 * positioning, resizing, font/color/alignment styling, background image —
 * to make a school-specific custom template.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Result Slip Templates' => null]);

// Hard server-side gate, matching the sidebar link's visibility — choosing
// the school's result slip template is a School Admin decision, and the
// save/select actions in phase5-actions.php already require it, so someone
// navigating straight to this URL shouldn't land on a builder they can't
// actually use.
if (!in_array($active_role, ['School Admin', 'Platform Manager'], true)) {
?>
<div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-6 text-xs text-rose-400">
    Only a School Admin can choose or build result slip templates.
</div>
<?php
    return;
}

$AVAILABLE_BLOCKS = [
    'school_logo' => 'School Logo', 'school_name' => 'School Name & Address',
    'student_photo' => 'Student Photo', 'student_name' => 'Student Name',
    'admission_no' => 'Admission Number', 'class_arm' => 'Class & Arm',
    'session_term' => 'Session & Term', 'subjects_table' => 'Subjects & Scores Table',
    'total_average' => 'Total & Average', 'position' => 'Class Position',
    'attendance_summary' => 'Attendance Summary', 'affective_domain' => 'Affective Domain Ratings',
    'psychomotor_domain' => 'Psychomotor Domain Ratings', 'class_teacher_remark' => 'Class Teacher Remark',
    'principal_remark' => 'Principal / Head Remark', 'next_term_begins' => 'Next Term Resumption Date',
    'signature_line' => 'Signature Line',
];

$platform_templates = $pdo->query("SELECT * FROM result_slip_templates WHERE school_uuid IS NULL ORDER BY name ASC")->fetchAll();
$school_templates = $pdo->prepare("SELECT * FROM result_slip_templates WHERE school_uuid = ? ORDER BY updated_at DESC");
$school_templates->execute([$school_uuid]);
$school_templates = $school_templates->fetchAll();
$active_uuid = $school['active_result_slip_template_uuid'] ?? '';

function rst_field_count(array $row): int {
    $decoded = json_decode($row['layout_json'], true);
    if (isset($decoded['elements'])) return count($decoded['elements']);
    return is_array($decoded) ? count($decoded) : 0;
}

$edit_uuid = $_GET['edit'] ?? '';
$edit_layout_json = [];
$edit_bg_url = null;
$edit_name = '';
if ($edit_uuid !== '') {
    $eq = $pdo->prepare("SELECT * FROM result_slip_templates WHERE template_uuid=? AND (school_uuid=? OR school_uuid IS NULL)");
    $eq->execute([$edit_uuid, $school_uuid]);
    $et = $eq->fetch();
    if ($et) {
        $decoded = json_decode($et['layout_json'], true) ?: [];
        $edit_layout_json = $decoded;
        $edit_name = $et['name'] . ' (Copy)';
        if (!empty($decoded['page']['background_image'])) {
            $edit_bg_url = asset_url($decoded['page']['background_image']);
        }
    }
}
?>
<div class="space-y-6">
    <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="layout-template" class="w-6 h-6 text-indigo-400"></i><span>Result Slip Templates</span>
    </h1>

    <!-- Platform base templates — pick one -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-[var(--border-color)]"><h3 class="text-sm font-bold text-[var(--text-primary)]">Platform Templates</h3></div>
        <div class="divide-y divide-[var(--border-color)]">
            <?php foreach ($platform_templates as $pt): ?>
            <div class="p-3 flex items-center justify-between">
                <div><span class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($pt['name']); ?></span> <span class="text-[10px] text-[var(--text-secondary)]">(<?php echo rst_field_count($pt); ?> fields)</span></div>
                <div class="flex gap-2">
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_select_result_slip_template" value="1"><input type="hidden" name="template_uuid" value="<?php echo htmlspecialchars($pt['template_uuid']); ?>">
                        <button class="px-3 py-1 text-[10px] font-bold rounded-lg <?php echo $active_uuid === $pt['template_uuid'] ? 'bg-emerald-600 text-white' : 'bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]'; ?>"><?php echo $active_uuid === $pt['template_uuid'] ? 'Active' : 'Use This'; ?></button>
                    </form>
                    <a href="preview_result_slip_template.php?template_uuid=<?php echo urlencode($pt['template_uuid']); ?>" target="_blank" class="px-3 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]">Preview</a>
                    <a href="?section=result_slip_templates&edit=<?php echo urlencode($pt['template_uuid']); ?>#rstBuilder" class="px-3 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]">Duplicate & Customize</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($platform_templates)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">The platform hasn't published any base templates yet.</p><?php endif; ?>
        </div>
    </div>

    <!-- School custom templates -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-[var(--border-color)]"><h3 class="text-sm font-bold text-[var(--text-primary)]">Our Custom Templates</h3></div>
        <div class="divide-y divide-[var(--border-color)]">
            <?php foreach ($school_templates as $st): ?>
            <div class="p-3 flex items-center justify-between">
                <div><span class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($st['name']); ?></span> <span class="text-[10px] text-[var(--text-secondary)]">(<?php echo rst_field_count($st); ?> fields)</span></div>
                <div class="flex gap-2">
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_select_result_slip_template" value="1"><input type="hidden" name="template_uuid" value="<?php echo htmlspecialchars($st['template_uuid']); ?>">
                        <button class="px-3 py-1 text-[10px] font-bold rounded-lg <?php echo $active_uuid === $st['template_uuid'] ? 'bg-emerald-600 text-white' : 'bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]'; ?>"><?php echo $active_uuid === $st['template_uuid'] ? 'Active' : 'Use This'; ?></button>
                    </form>
                    <a href="preview_result_slip_template.php?template_uuid=<?php echo urlencode($st['template_uuid']); ?>" target="_blank" class="px-3 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]">Preview</a>
                    <a href="?section=result_slip_templates&edit=<?php echo urlencode($st['template_uuid']); ?>#rstBuilder" class="px-3 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]">Edit Copy</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($school_templates)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">No custom templates yet — duplicate a platform template above to start one.</p><?php endif; ?>
        </div>
    </div>

    <!-- Builder — same A4 canvas as the platform builder -->
    <div id="rstBuilder" class="bg-[var(--bg-secondary)] border-2 border-dashed border-indigo-500/40 rounded-2xl p-4">
        <h3 class="text-sm font-bold text-[var(--text-primary)] mb-1"><?php echo $edit_uuid ? 'Customize (then save as a new template)' : 'Build a New Custom Template'; ?></h3>
        <p class="text-xs text-[var(--text-secondary)] mb-3">Drag fields onto the A4 sheet, drag to reposition, use the corner handle to resize, and style each field from the inspector.</p>
        <form method="POST" id="rstForm" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_save_school_result_slip_template" value="1">
            <input type="hidden" name="layout_json" id="rstLayoutJson" value="[]">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_name); ?>" placeholder="Template name" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] w-64">
                <div class="flex items-center gap-3">
                    <label class="text-[10px] font-bold uppercase text-[var(--text-secondary)]">Page Background</label>
                    <input type="file" name="background_image" id="rstBgFile" accept="image/*" class="text-[10px] text-[var(--text-secondary)]">
                    <button type="button" id="rstBgRemove" class="px-2 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-rose-400">Remove</button>
                    <input type="hidden" name="remove_background_image" id="rstBgRemoveFlag" value="0">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl">Save as New Template & Set Active</button>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-2">
                    <h4 class="text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-2">Fields</h4>
                    <div id="rstAvailable" class="space-y-2">
                        <?php foreach ($AVAILABLE_BLOCKS as $key => $label): ?>
                        <div draggable="true" data-key="<?php echo $key; ?>" data-label="<?php echo htmlspecialchars($label); ?>" class="rst-block bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] cursor-move flex items-center gap-2">
                            <i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-[var(--text-secondary)]"></i><?php echo htmlspecialchars($label); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="lg:col-span-7 overflow-auto">
                    <h4 class="text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-2">A4 Portrait Sheet <span class="normal-case font-normal">(210 × 297mm)</span></h4>
                    <div id="rstCanvas"></div>
                </div>
                <div class="lg:col-span-3">
                    <h4 class="text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-2">Field Inspector</h4>
                    <div id="rstInspector"></div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/result-slip-canvas.js"></script>
<script>
(function() {
    const SAMPLE = {
        school_logo: '<div style="text-align:center;"><div style="display:inline-block;width:100%;height:100%;border-radius:6px;background:#e5e7eb;color:#9ca3af;font-size:8px;display:flex;align-items:center;justify-content:center;">LOGO</div></div>',
        school_name: '<div><div style="font-weight:bold;">Bright Future Academy</div><div style="font-size:0.8em;color:#666;">Result Slip</div></div>',
        student_photo: '<div style="width:100%;height:100%;border:1px solid #ccc;border-radius:6px;background:#f3f4f6;color:#9ca3af;font-size:8px;display:flex;align-items:center;justify-content:center;">PHOTO</div>',
        student_name: '<div><b>Name:</b> Adaeze Okafor</div>',
        admission_no: '<div><b>Admission No:</b> STU-2026-0142</div>',
        class_arm: '<div><b>Class:</b> SS2 Science</div>',
        session_term: '<div><b>Session/Term:</b> 2025/2026 First Term</div>',
        subjects_table: '<table style="width:100%;height:100%;border-collapse:collapse;font-size:0.85em;"><thead><tr><th style="border:1px solid #ccc;padding:1px 3px;">Subject</th><th style="border:1px solid #ccc;padding:1px 3px;">CA</th><th style="border:1px solid #ccc;padding:1px 3px;">Exam</th><th style="border:1px solid #ccc;padding:1px 3px;">Total</th><th style="border:1px solid #ccc;padding:1px 3px;">Grade</th></tr></thead><tbody><tr><td style="border:1px solid #ccc;padding:1px 3px;">Mathematics</td><td style="border:1px solid #ccc;padding:1px 3px;">18</td><td style="border:1px solid #ccc;padding:1px 3px;">55</td><td style="border:1px solid #ccc;padding:1px 3px;">73</td><td style="border:1px solid #ccc;padding:1px 3px;">B2</td></tr></tbody></table>',
        total_average: '<div><b>Total/Average:</b> 139 / 69.5</div>',
        position: '<div><b>Position:</b> 3rd of 32</div>',
        attendance_summary: '<div><b>Attendance:</b> 58/60</div>',
        affective_domain: '<div><b>Affective:</b> Punctuality — Excellent</div>',
        psychomotor_domain: '<div><b>Psychomotor:</b> Sports — Good</div>',
        class_teacher_remark: '<div><b>Class Teacher:</b> A consistent, hardworking student.</div>',
        principal_remark: '<div><b>Principal:</b> Keep up the good work.</div>',
        next_term_begins: '<div><b>Next Term Begins:</b> January 12, 2026</div>',
        signature_line: '<div style="border-top:1px solid #333;padding-top:2px;font-size:0.75em;">Authorized Signature</div>',
    };

    const editor = ResultSlipCanvas.createCanvasEditor({
        availableBlocksEl: document.getElementById('rstAvailable'),
        canvasWrapEl: document.getElementById('rstCanvas'),
        inspectorEl: document.getElementById('rstInspector'),
        jsonField: document.getElementById('rstLayoutJson'),
        bgFileInput: document.getElementById('rstBgFile'),
        bgRemoveBtn: document.getElementById('rstBgRemove'),
        sampleHtml: SAMPLE,
        initialLayout: <?php echo json_encode($edit_layout_json); ?>,
        uploadedBgUrl: <?php echo json_encode($edit_bg_url); ?>,
    });

    document.getElementById('rstBgRemove').addEventListener('click', function() {
        document.getElementById('rstBgRemoveFlag').value = '1';
    });
    document.getElementById('rstForm').addEventListener('submit', function() {
        editor.serialize();
    });
    if (window.lucide) lucide.createIcons();
})();
</script>
