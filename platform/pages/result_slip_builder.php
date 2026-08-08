<?php
/**
 * PLATFORM PAGE: Result Slip Templates (Phase C)
 * Platform Manager builds base result-slip layouts on a true A4 canvas —
 * free drag positioning, resize, per-field font/color/alignment styling,
 * and an optional page background image. Schools then pick one of these
 * (or a school-custom variant, built the same way from
 * admin/sections/result_slip_templates.php) as their active result slip.
 */
$base_templates = $pdo->query("SELECT * FROM result_slip_templates WHERE school_uuid IS NULL ORDER BY name ASC")->fetchAll();

$AVAILABLE_BLOCKS = [
    'school_logo'       => 'School Logo',
    'school_name'       => 'School Name & Address',
    'student_photo'     => 'Student Photo',
    'student_name'      => 'Student Name',
    'admission_no'      => 'Admission Number',
    'class_arm'         => 'Class & Arm',
    'session_term'      => 'Session & Term',
    'subjects_table'     => 'Subjects & Scores Table',
    'total_average'      => 'Total & Average',
    'position'           => 'Class Position',
    'attendance_summary'  => 'Attendance Summary',
    'affective_domain'    => 'Affective Domain Ratings',
    'psychomotor_domain'  => 'Psychomotor Domain Ratings',
    'class_teacher_remark' => 'Class Teacher Remark',
    'principal_remark'   => 'Principal / Head Remark',
    'next_term_begins'   => 'Next Term Resumption Date',
    'signature_line'     => 'Signature Line',
];

// If editing an existing base template, preload its layout/background so the
// canvas opens with the saved design instead of a blank sheet.
$edit_uuid = trim($_GET['edit'] ?? '');
$edit_name = '';
$edit_layout_json = [];
$edit_bg_url = null;
if ($edit_uuid !== '') {
    $eq = $pdo->prepare("SELECT * FROM result_slip_templates WHERE template_uuid=? AND school_uuid IS NULL");
    $eq->execute([$edit_uuid]);
    $et = $eq->fetch();
    if ($et) {
        $edit_name = $et['name'];
        $decoded = json_decode($et['layout_json'], true);
        $edit_layout_json = $decoded ?: [];
        if (!empty($decoded['page']['background_image'])) {
            $edit_bg_url = asset_url($decoded['page']['background_image']);
        }
    }
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Result Slip Templates — Base Layouts</h1>
    </div>
    <p class="text-xs text-[var(--text-secondary)]">Drag field blocks onto the A4 sheet, then drag to reposition and use the corner handle to resize. Click a field to change its font, size, color, alignment and layer order from the inspector on the right.</p>

    <form method="POST" id="rstForm" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action_save_platform_result_slip_template" value="1">
        <input type="hidden" name="layout_json" id="rstLayoutJson" value="[]">
        <input type="hidden" name="template_uuid" value="<?php echo htmlspecialchars($edit_uuid); ?>">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4">
            <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_name); ?>" placeholder="Template name, e.g. Standard Secondary" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] w-64">
            <div class="flex items-center gap-3">
                <label class="text-[10px] font-bold uppercase text-[var(--text-secondary)]">Page Background</label>
                <input type="file" name="background_image" id="rstBgFile" accept="image/*" class="text-[10px] text-[var(--text-secondary)]">
                <button type="button" id="rstBgRemove" class="px-2 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-rose-400">Remove</button>
                <input type="hidden" name="remove_background_image" id="rstBgRemoveFlag" value="0">
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl"><?php echo $edit_uuid ? 'Save Changes' : 'Save Base Template'; ?></button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Available blocks -->
            <div class="lg:col-span-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4">
                <h3 class="text-xs font-bold text-[var(--text-secondary)] uppercase mb-3">Fields</h3>
                <div id="rstAvailable" class="space-y-2">
                    <?php foreach ($AVAILABLE_BLOCKS as $key => $label): ?>
                    <div draggable="true" data-key="<?php echo $key; ?>" data-label="<?php echo htmlspecialchars($label); ?>"
                         class="rst-block bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] cursor-move flex items-center gap-2">
                        <i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-[var(--text-secondary)]"></i><?php echo htmlspecialchars($label); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-[10px] text-[var(--text-secondary)] mt-3 italic">Faded fields are already on the sheet.</p>
            </div>

            <!-- A4 canvas -->
             <style>
            .custom-size {
                width: 210mm;
                height: 297mm;
            }
            </style>

            <div class="lg:col-span-7 custom-size bg-[var(--bg-secondary)] border-2 border-dashed border-indigo-500/40 rounded-2xl p-4 mt-16 overflow-auto">
                <h3 class="text-xs font-bold text-[var(--text-secondary)] uppercase mb-3">A4 Portrait Sheet <span class="normal-case font-normal">(210 × 297mm — actual print proportions)</span></h3>
                <div id="rstCanvas"></div>
            </div>

            <!-- Inspector -->
            <div class="lg:col-span-5 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4">
                <h3 class="text-xs font-bold text-[var(--text-secondary)] uppercase mb-3">Field Inspector</h3>
                <div id="rstInspector"></div>
            </div>
        </div>
    </form>

    <!-- Existing base templates -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-[var(--border-color)]"><h3 class="text-sm font-bold text-[var(--text-primary)]">Existing Base Templates</h3></div>
        <div class="divide-y divide-[var(--border-color)]">
            <?php foreach ($base_templates as $bt): $decoded = json_decode($bt['layout_json'], true); $count = isset($decoded['elements']) ? count($decoded['elements']) : (is_array($decoded) ? count($decoded) : 0); ?>
            <div class="p-3 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($bt['name']); ?></span>
                    <span class="text-[10px] text-[var(--text-secondary)] ml-2"><?php echo $count; ?> fields</span>
                </div>
                <div class="flex items-center gap-3">
                    <a href="../admin/preview_result_slip_template.php?template_uuid=<?php echo urlencode($bt['template_uuid']); ?>" target="_blank" rel="noopener" class="text-[10px] text-emerald-400 font-bold">Preview</a>
                    <a href="index.php?page=result_slip_builder&edit=<?php echo urlencode($bt['template_uuid']); ?>" class="text-[10px] text-indigo-400 font-bold">Edit</a>
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_delete_platform_result_slip_template" value="1"><input type="hidden" name="template_uuid" value="<?php echo htmlspecialchars($bt['template_uuid']); ?>"><button class="text-[10px] text-rose-400 font-bold" onclick="return confirm('Delete this base template?')">Delete</button></form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($base_templates)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">No base templates yet — build one above.</p><?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/result-slip-canvas.js"></script>
<script>
(function () {
    // Sample data so the canvas shows what each field will actually look
    // like on a printed slip, instead of just its name in a plain box.
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
        initialLayout: <?php echo json_encode($edit_layout_json ?? []); ?>,
        uploadedBgUrl: <?php echo json_encode($edit_bg_url ?? null); ?>,
    });

    document.getElementById('rstBgRemove').addEventListener('click', function () {
        document.getElementById('rstBgRemoveFlag').value = '1';
    });
    document.getElementById('rstForm').addEventListener('submit', function () {
        editor.serialize();
    });
    if (window.lucide) lucide.createIcons();
})();
</script>
