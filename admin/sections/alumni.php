<?php
/**
 * SECTION: Alumni Network
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Alumni Network' => null]);
$can_write = can_manage($active_role, $current_access);

$alumni = [];
try {
    $st = $pdo->prepare("SELECT * FROM alumni WHERE school_uuid=? ORDER BY graduation_year DESC, name ASC");
    $st->execute([$school_uuid]);
    $alumni = $st->fetchAll();
} catch (Exception $e) {}

$grad_candidates = [];
try {
    $gc = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $gc->execute([$school_uuid]);
    $grad_candidates = $gc->fetchAll();
} catch (Exception $e) {}

$years = array_unique(array_column($alumni, 'graduation_year'));
rsort($years);

$testimonial_templates = [];
try {
    $ttq = $pdo->prepare("SELECT * FROM testimonial_templates WHERE school_uuid=? ORDER BY is_default DESC, name ASC");
    $ttq->execute([$school_uuid]);
    $testimonial_templates = $ttq->fetchAll();
} catch (Exception $e) {}
?>
<script>
function applyTestimonialTemplate(selectEl, targetTextareaId, alumniData) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.dataset.body) return;
    let text = opt.dataset.body;
    for (const [k, v] of Object.entries(alumniData)) {
        text = text.split('{{' + k + '}}').join(v || '');
    }
    document.getElementById(targetTextareaId).value = text;
}
</script>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="graduation-cap" class="w-5 h-5 text-teal-400"></i> Alumni Network
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo count($alumni); ?> alumni across <?php echo count($years); ?> graduating class(es)</p>
        </div>
        <?php if ($can_write): ?>
        <button onclick="document.getElementById('addAlumniModal').classList.remove('hidden')"
            class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Alumni
        </button>
        <?php endif; ?>
    </div>

    <!-- Configurable Testimonial Templates -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-3">
        <button type="button" onclick="document.getElementById('testimonialTplPanel').classList.toggle('hidden')" class="flex items-center justify-between w-full text-left">
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="quote" class="w-4 h-4 text-teal-400"></i> Testimonial Templates</h3>
            <span class="text-[10px] text-[var(--text-secondary)]"><?php echo count($testimonial_templates); ?> saved · click to manage</span>
        </button>
        <div id="testimonialTplPanel" class="hidden space-y-4 pt-2 border-t border-[var(--border-color)]">
            <p class="text-[10px] text-[var(--text-secondary)]">Tokens: <code>{{name}}</code>, <code>{{final_class}}</code>, <code>{{graduation_year}}</code>, <code>{{character_conduct}}</code>, <code>{{school_name}}</code></p>
            <?php if ($can_write): ?>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_testimonial_template" value="1">
                <input type="text" name="name" required placeholder="Template name" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <textarea name="body_html" required rows="3" placeholder="{{name}} was an exemplary student at {{school_name}}, graduating in {{graduation_year}} with a conduct rating of {{character_conduct}}." class="md:col-span-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
                <label class="flex items-center gap-2 text-[10px] text-[var(--text-secondary)]"><input type="checkbox" name="is_default" value="1"> Set as default</label>
                <button type="submit" class="md:col-start-3 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold px-4 py-2 rounded-xl">Save Template</button>
            </form>
            <?php endif; ?>
            <div class="space-y-2">
                <?php foreach ($testimonial_templates as $tt): ?>
                <div class="flex items-center justify-between bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2">
                    <span class="text-xs text-[var(--text-primary)]"><b><?php echo htmlspecialchars($tt['name']); ?></b><?php if ($tt['is_default']): ?> <span class="text-[9px] bg-teal-600 text-white px-2 py-0.5 rounded-full font-bold ml-1">DEFAULT</span><?php endif; ?> — <span class="text-[var(--text-secondary)]"><?php echo htmlspecialchars(mb_strimwidth($tt['body_html'],0,70,'…')); ?></span></span>
                    <?php if ($can_write): ?>
                    <form method="POST"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_delete_testimonial_template" value="1">
                        <input type="hidden" name="template_uuid" value="<?php echo htmlspecialchars($tt['template_uuid']); ?>">
                        <button type="submit" onclick="return confirm('Delete this template?')" class="text-[10px] text-rose-400 font-bold">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($testimonial_templates)): ?><p class="text-xs italic text-[var(--text-secondary)]">No templates yet — add one above.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($alumni)): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-12 flex flex-col items-center justify-center gap-3 text-center">
            <i data-lucide="graduation-cap" class="w-10 h-10 text-[var(--text-secondary)]"></i>
            <p class="text-xs text-[var(--text-secondary)]">No alumni archived yet.</p>
        </div>
    <?php else: ?>
    <?php foreach ($years as $yr): ?>
    <div class="space-y-3">
        <h2 class="text-xs font-bold uppercase text-[var(--text-secondary)] tracking-wider">Class of <?php echo (int)$yr; ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach (array_filter($alumni, fn($a) => $a['graduation_year'] == $yr) as $a): ?>
            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-teal-600 flex items-center justify-center text-white text-sm font-bold shrink-0"><?php echo strtoupper(substr($a['name'],0,2)); ?></div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] truncate"><?php echo htmlspecialchars($a['name']); ?></h3>
                        <p class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($a['final_class']); ?> · GPA <?php echo number_format($a['cumulative_gpa'],2); ?></p>
                    </div>
                </div>
                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-500/10 text-teal-400"><?php echo htmlspecialchars($a['character_conduct']); ?></span>
                <?php if (!empty($a['testimonial_text'])): ?>
                    <p class="text-xs text-[var(--text-secondary)] italic">"<?php echo htmlspecialchars($a['testimonial_text']); ?>"</p>
                <?php endif; ?>
                <?php if ($can_write): ?>
                <div class="flex gap-2 pt-2 border-t border-[var(--border-color)]">
                    <button onclick="document.getElementById('editAlumni-<?php echo $a['alumni_uuid']; ?>').classList.remove('hidden')" class="text-[10px] font-bold text-indigo-400">Edit</button>
                    <form method="POST" onsubmit="return confirm('Remove this alumni record?')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_delete_alumni" value="1">
                        <input type="hidden" name="alumni_uuid" value="<?php echo htmlspecialchars($a['alumni_uuid']); ?>">
                        <button type="submit" class="text-[10px] font-bold text-rose-400">Delete</button>
                    </form>
                </div>
                <div id="editAlumni-<?php echo $a['alumni_uuid']; ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Edit <?php echo htmlspecialchars($a['name']); ?></h3>
                            <button onclick="document.getElementById('editAlumni-<?php echo $a['alumni_uuid']; ?>').classList.add('hidden')" class="text-[var(--text-secondary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
                        </div>
                        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_edit_alumni" value="1">
                            <input type="hidden" name="alumni_uuid" value="<?php echo htmlspecialchars($a['alumni_uuid']); ?>">
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-[10px] font-bold uppercase mb-1">Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($a['name']); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs"></div>
                                <div><label class="block text-[10px] font-bold uppercase mb-1">Grad Year</label><input type="number" name="graduation_year" value="<?php echo $a['graduation_year']; ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-[10px] font-bold uppercase mb-1">Final Class</label><input type="text" name="final_class" value="<?php echo htmlspecialchars($a['final_class']); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs"></div>
                                <div><label class="block text-[10px] font-bold uppercase mb-1">GPA</label><input type="number" step="0.01" name="cumulative_gpa" value="<?php echo $a['cumulative_gpa']; ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs"></div>
                            </div>
                            <div><label class="block text-[10px] font-bold uppercase mb-1">Character/Conduct</label><input type="text" name="character_conduct" value="<?php echo htmlspecialchars($a['character_conduct']); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs"></div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase mb-1">Testimonial</label>
                                <?php if (!empty($testimonial_templates)): ?>
                                <select onchange="applyTestimonialTemplate(this,'testimonial-<?php echo $a['alumni_uuid']; ?>',{name:<?php echo json_encode($a['name']); ?>,final_class:<?php echo json_encode($a['final_class']); ?>,graduation_year:<?php echo json_encode((string)$a['graduation_year']); ?>,character_conduct:<?php echo json_encode($a['character_conduct']); ?>,school_name:<?php echo json_encode($school['name'] ?? ''); ?>})" class="w-full mb-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[10px] text-[var(--text-primary)]">
                                    <option value="">— Use a template —</option>
                                    <?php foreach ($testimonial_templates as $tt): ?>
                                    <option value="<?php echo htmlspecialchars($tt['template_uuid']); ?>" data-body="<?php echo htmlspecialchars($tt['body_html']); ?>"><?php echo htmlspecialchars($tt['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                                <textarea id="testimonial-<?php echo $a['alumni_uuid']; ?>" name="testimonial_text" rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs"><?php echo htmlspecialchars($a['testimonial_text']); ?></textarea>
                            </div>
                            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl">Save Changes</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Alumni Modal -->
<div id="addAlumniModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Add Alumni</h3>
            <button onclick="document.getElementById('addAlumniModal').classList.add('hidden')" class="text-[var(--text-secondary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_alumni" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Archive from Student Roster (optional)</label>
                <select name="student_uuid" onchange="const o=this.options[this.selectedIndex]; if(o.dataset.name){document.getElementById('almName').value=o.dataset.name; document.getElementById('almClass').value=o.dataset.class;}" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">— Enter manually instead —</option>
                    <?php foreach ($grad_candidates as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['student_uuid']); ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>" data-class="<?php echo htmlspecialchars($s['class']); ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['class']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Name *</label>
                    <input type="text" id="almName" name="name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Graduation Year *</label>
                    <input type="number" name="graduation_year" value="<?php echo date('Y'); ?>" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Final Class</label>
                    <input type="text" id="almClass" name="final_class" value="SSS3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Cumulative GPA</label>
                    <input type="number" step="0.01" name="cumulative_gpa" value="3.85" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Character/Conduct</label>
                <input type="text" name="character_conduct" value="Exemplary & Outstanding" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Testimonial</label>
                <?php if (!empty($testimonial_templates)): ?>
                <select onchange="applyTestimonialTemplate(this,'newAlumniTestimonial',{name:document.getElementById('almName').value,final_class:document.getElementById('almClass').value,graduation_year:document.querySelector('[name=graduation_year]').value,character_conduct:document.querySelector('[name=character_conduct]').value,school_name:<?php echo json_encode($school['name'] ?? ''); ?>})" class="w-full mb-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[10px] text-[var(--text-primary)]">
                    <option value="">— Use a template —</option>
                    <?php foreach ($testimonial_templates as $tt): ?>
                    <option value="<?php echo htmlspecialchars($tt['template_uuid']); ?>" data-body="<?php echo htmlspecialchars($tt['body_html']); ?>"><?php echo htmlspecialchars($tt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <textarea id="newAlumniTestimonial" name="testimonial_text" rows="3" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs rounded-xl">Add to Alumni Network</button>
        </form>
    </div>
</div>
