<?php
/**
 * SECTION: Parent & Guardian Records
 * Full CRUD with searchable student-linking dropdown on both add and edit.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Parent Records' => null]);
$can_write = can_manage($active_role, $current_access);

$search = safe_str($_GET['q'] ?? '');
$total  = 0;
try {
    $cs = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_uuid=?" . ($search ? " AND name LIKE ?" : ""));
    $cs->execute($search ? [$school_uuid,"%$search%"] : [$school_uuid]);
    $total = (int)$cs->fetchColumn();
} catch(Exception $e){}

$pg      = paginate($total, 25, 'parents', array_filter(['q'=>$search]));
$parents = [];
try {
    $sql  = "SELECT * FROM parents WHERE school_uuid=?" . ($search?" AND name LIKE ?":'') . " ORDER BY name ASC LIMIT {$pg['limit']} OFFSET {$pg['offset']}";
    $bind = $search ? [$school_uuid,"%$search%"] : [$school_uuid];
    $st   = $pdo->prepare($sql); $st->execute($bind);
    $parents = $st->fetchAll();
} catch(Exception $e){}

// ── All students for searchable dropdown (EXCLUDE already-linked students) ──
$all_students = [];
try {
    // Get all students
    $sq = $pdo->prepare("SELECT student_uuid, name, class, arm, roll_number FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $sq->execute([$school_uuid]);
    $all_students = $sq->fetchAll();
    
    // Get all already-linked student UUIDs (from parents.linked_student_uuids)
    $linked_uuids = [];
    $lq = $pdo->prepare("SELECT linked_student_uuids FROM parents WHERE school_uuid=? AND linked_student_uuids IS NOT NULL AND linked_student_uuids != ''");
    $lq->execute([$school_uuid]);
    while ($row = $lq->fetch()) {
        $parts = array_filter(array_map('trim', explode(',', $row['linked_student_uuids'])));
        $linked_uuids = array_merge($linked_uuids, $parts);
    }
    $linked_uuids = array_unique($linked_uuids);
    
    // Filter out already-linked students
    $all_students = array_filter($all_students, function($stu) use ($linked_uuids) {
        return !in_array($stu['student_uuid'], $linked_uuids);
    });
} catch(Exception $e){}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="heart-handshake" class="w-5 h-5 text-pink-400"></i> Parent & Guardian Records
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo number_format($total); ?> parent records</p>
        </div>
        <?php if ($can_write): ?>
        <a href="export_csv.php?type=parents" class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl flex items-center gap-2"><i data-lucide="download" class="w-4 h-4 text-sky-400"></i> Export CSV</a>
        <button onclick="document.getElementById('addParentModal').classList.remove('hidden')"
            class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add Parent
        </button>
        <?php endif; ?>
    </div>

    <!-- Filter -->
    <form method="GET" class="flex gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="parents">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name…"
            class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl">Search</button>
        <a href="dashboard.php?section=parents" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] font-bold rounded-xl">Clear</a>
    </form>

    <!-- Table -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                <tr>
                    <th class="p-3.5">Parent</th>
                    <th class="p-3.5">Contact</th>
                    <th class="p-3.5">Occupation</th>
                    <th class="p-3.5">Linked Children</th>
                    <th class="p-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php if (empty($parents)): ?>
                <tr><td colspan="5" class="p-8 text-center text-[var(--text-secondary)] italic">No parent records yet.</td></tr>
            <?php else: foreach ($parents as $prt):
                // Resolve linked student names
                $linked_names = [];
                if (!empty($prt['linked_student_uuids'])) {
                    $luuids = array_filter(array_map('trim', explode(',', $prt['linked_student_uuids'])));
                    foreach ($luuids as $lu) {
                        foreach ($all_students as $astu) {
                            if ($astu['student_uuid'] === $lu) {
                                $linked_names[] = $astu['name'] . ' (' . $astu['class'] . ')';
                                break;
                            }
                        }
                    }
                }
                // Also check students.parent_uuid pointing here
                try {
                    $lq = $pdo->prepare("SELECT name, class FROM students WHERE school_uuid=? AND parent_uuid=?");
                    $lq->execute([$school_uuid, $prt['parent_uuid']]);
                    foreach ($lq->fetchAll() as $lr) {
                        $entry = $lr['name'] . ' (' . $lr['class'] . ')';
                        if (!in_array($entry, $linked_names)) $linked_names[] = $entry;
                    }
                } catch(Exception $e){}
            ?>
            <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors">
                <td class="p-3.5">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($prt['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars(asset_url($prt['photo_path'])); ?>" class="w-9 h-9 rounded-full object-cover border border-[var(--border-color)] shrink-0"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <?php endif; ?>
                        <div class="w-9 h-9 rounded-full bg-pink-600 flex items-center justify-center text-white text-xs font-bold shrink-0"
                             style="<?php echo !empty($prt['photo_path'])?'display:none;':''; ?>">
                            <?php echo strtoupper(substr($prt['name'],0,2)); ?>
                        </div>
                        <div>
                            <span class="font-bold block"><?php echo htmlspecialchars($prt['name']); ?></span>
                            <span class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($prt['parent_uuid']); ?></span>
                        </div>
                    </div>
                </td>
                <td class="p-3.5">
                    <span class="block"><?php echo htmlspecialchars($prt['email']); ?></span>
                    <span class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($prt['phone']); ?></span>
                </td>
                <td class="p-3.5 text-[var(--text-secondary)]"><?php echo htmlspecialchars($prt['occupation'] ?? '—'); ?></td>
                <td class="p-3.5">
                    <?php if (empty($linked_names)): ?>
                        <span class="text-[10px] text-amber-400 italic">No children linked</span>
                    <?php else: ?>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($linked_names as $ln): ?>
                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded text-[10px] font-bold"><?php echo htmlspecialchars($ln); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="p-3.5 text-right space-x-1">
                    <?php if ($can_write): ?>
                    <button onclick='openEditParentModal(<?php echo json_encode($prt, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'
                        class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:text-white text-[var(--text-secondary)] rounded-lg text-[10px] font-bold">Edit</button>
                    <?php endif; ?>
                    <?php if ($active_role === 'School Admin'): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this parent record?')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_delete_parent" value="1">
                        <input type="hidden" name="parent_uuid" value="<?php echo htmlspecialchars($prt['parent_uuid']); ?>">
                        <button type="submit" class="px-2.5 py-1 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-lg text-[10px] font-bold">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <div class="px-4 pb-4"><?php render_pagination($pg); ?></div>
    </div>
</div>

<!-- ═══ ADD PARENT MODAL ═══ -->
<div id="addParentModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Add Parent / Guardian</h3>
            <button onclick="document.getElementById('addParentModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-5"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_parent" value="1">

            <div>
                <p class="text-[10px] font-bold text-pink-400 uppercase tracking-widest mb-3">Personal Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Full Name *</label>
                        <input type="text" name="parent_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Phone *</label>
                        <input type="tel" name="parent_phone" required pattern="[0-9]+" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Email *</label>
                        <input type="email" name="parent_email" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Occupation</label>
                        <input type="text" name="occupation" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Employer</label>
                        <input type="text" name="employer" placeholder="Optional"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Home Address</label>
                        <input type="text" name="address" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Passport Photo</label>
                        <input type="file" name="parent_photo" accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-pink-600 file:text-white"></div>
                </div>
            </div>

            <!-- Student linking -->
            <div>
                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">Link Children (Students)</p>
                <p class="text-[10px] text-[var(--text-secondary)] mb-2">Search and select one or more students. Hold Ctrl/Cmd to select multiple. Students already linked to another parent are not shown.</p>
                <input type="text" id="addStuSearch" placeholder="Type student name to filter…" oninput="filterStudentSelect('addStuSearch','addStuSelect')"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] mb-2">
                <select name="linked_student_uuids[]" id="addStuSelect" multiple size="5"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <?php foreach ($all_students as $stu): ?>
                    <option value="<?php echo htmlspecialchars($stu['student_uuid']); ?>">
                        <?php echo htmlspecialchars($stu['name']); ?> — <?php echo htmlspecialchars($stu['class']); ?> <?php echo htmlspecialchars($stu['arm']); ?> (<?php echo htmlspecialchars($stu['roll_number']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($all_students)): ?>
                <p class="text-[10px] text-amber-400 italic mt-1">No available students to link — all active students are already linked to a parent.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full py-3 bg-pink-600 hover:bg-pink-500 text-white font-bold text-xs rounded-xl shadow-lg">Save Parent Record</button>
        </form>
    </div>
</div>

<!-- ═══ EDIT PARENT MODAL ═══ -->
<div id="editParentModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Edit Parent Record</h3>
            <button onclick="document.getElementById('editParentModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-5"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_edit_parent" value="1">
            <input type="hidden" name="parent_uuid"    id="editPrtUuid">
            <input type="hidden" name="existing_photo" id="editPrtPhoto">

            <div>
                <p class="text-[10px] font-bold text-pink-400 uppercase tracking-widest mb-3">Personal Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Full Name *</label>
                        <input type="text" name="parent_name" id="editPrtName" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Phone *</label>
                        <input type="tel" name="parent_phone" id="editPrtPhone" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Email</label>
                        <input type="email" name="parent_email" id="editPrtEmail" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Occupation</label>
                        <input type="text" name="occupation" id="editPrtOcc" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Employer</label>
                        <input type="text" name="employer" id="editPrtEmp" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Address</label>
                        <input type="text" name="address" id="editPrtAddr" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">New Photo (optional)</label>
                        <input type="file" name="parent_photo" accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-pink-600 file:text-white"></div>
                </div>
            </div>

            <!-- Student linking -->
            <div>
                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">Linked Children</p>
                <input type="text" id="editStuSearch" placeholder="Type student name to filter…" oninput="filterStudentSelect('editStuSearch','editStuSelect')"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] mb-2">
                <select name="linked_student_uuids[]" id="editStuSelect" multiple size="5"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <?php foreach ($all_students as $stu): ?>
                    <option value="<?php echo htmlspecialchars($stu['student_uuid']); ?>">
                        <?php echo htmlspecialchars($stu['name']); ?> — <?php echo htmlspecialchars($stu['class']); ?> <?php echo htmlspecialchars($stu['arm']); ?> (<?php echo htmlspecialchars($stu['roll_number']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-[var(--text-secondary)] mt-1">Hold Ctrl/Cmd to select or deselect multiple students.</p>
            </div>

            <button type="submit" class="w-full py-3 bg-pink-600 hover:bg-pink-500 text-white font-bold text-xs rounded-xl shadow-lg">Save Changes</button>
        </form>
    </div>
</div>

<script>
function filterStudentSelect(inputId, selectId) {
    const f = document.getElementById(inputId).value.toUpperCase();
    const s = document.getElementById(selectId);
    for (let i = 0; i < s.options.length; i++) {
        s.options[i].style.display = s.options[i].text.toUpperCase().includes(f) ? '' : 'none';
    }
}

function openEditParentModal(p) {
    document.getElementById('editPrtUuid').value  = p.parent_uuid || '';
    document.getElementById('editPrtName').value  = p.name        || '';
    document.getElementById('editPrtPhone').value = p.phone       || '';
    document.getElementById('editPrtEmail').value = p.email       || '';
    document.getElementById('editPrtOcc').value   = p.occupation  || '';
    document.getElementById('editPrtEmp').value   = p.employer    || '';
    document.getElementById('editPrtAddr').value  = p.address     || '';
    document.getElementById('editPrtPhoto').value = p.photo_path  || '';

    // Pre-select linked students
    const linked = (p.linked_student_uuids || '').split(',').map(s => s.trim()).filter(Boolean);
    const sel    = document.getElementById('editStuSelect');
    for (let i = 0; i < sel.options.length; i++) {
        sel.options[i].selected = linked.includes(sel.options[i].value);
    }

    document.getElementById('editParentModal').classList.remove('hidden');
}
</script>