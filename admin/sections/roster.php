<?php
/**
 * SECTION: Student Management (Roster)
 * Enroll, edit, transfer, withdraw, graduate, bulk CSV import.
 * Parent-student linking via searchable dropdown on both sides.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Student Management' => null]);
$can_write = can_manage($active_role, $current_access);

$search      = safe_str($_GET['q']             ?? '');
$filter_cl   = safe_str($_GET['filter_class']  ?? '');
$filter_st   = safe_str($_GET['filter_status'] ?? 'Active');
$filter_arm  = safe_str($_GET['filter_arm']    ?? '');

// Arms — scoped to the currently filtered class only (arms belong to a class,
// e.g. "A" under JSS1 differs from "A" under JSS2). The rest of the arm
// dropdowns on this page load via api/get-arms.php once a class is picked.
$roster_arms = [];
if ($filter_cl) {
    try {
        $aq = $pdo->prepare("SELECT arm_name FROM academic_arms WHERE school_uuid=? AND class_name=? ORDER BY id ASC");
        $aq->execute([$school_uuid, $filter_cl]);
        $roster_arms = $aq->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch(Exception $e){ $roster_arms = []; }
}

// Count
$cq  = "SELECT COUNT(*) FROM students WHERE school_uuid=?"; $cb = [$school_uuid];
if ($search)     { $cq .= " AND name LIKE ?"; $cb[] = "%$search%"; }
if ($filter_cl)  { $cq .= " AND class=?";     $cb[] = $filter_cl; }
if ($filter_arm) { $cq .= " AND arm=?";       $cb[] = $filter_arm; }
if ($filter_st !== '') { $cq .= " AND status=?"; $cb[] = $filter_st; }
$total = 0;
try { $cs = $pdo->prepare($cq); $cs->execute($cb); $total = (int)$cs->fetchColumn(); } catch(Exception $e){}

$pg = paginate($total, 25, 'roster', array_filter(['q'=>$search,'filter_class'=>$filter_cl,'filter_status'=>$filter_st,'filter_arm'=>$filter_arm]));

$students = [];
$sq  = "SELECT s.*, p.name AS linked_parent_name FROM students s LEFT JOIN parents p ON s.parent_uuid=p.parent_uuid WHERE s.school_uuid=?";
$sb  = [$school_uuid];
if ($search)     { $sq .= " AND s.name LIKE ?"; $sb[] = "%$search%"; }
if ($filter_cl)  { $sq .= " AND s.class=?";     $sb[] = $filter_cl; }
if ($filter_arm) { $sq .= " AND s.arm=?";       $sb[] = $filter_arm; }
if ($filter_st !== '') { $sq .= " AND s.status=?"; $sb[] = $filter_st; }
$sq .= " ORDER BY s.name ASC LIMIT {$pg['limit']} OFFSET {$pg['offset']}";
try { $st2 = $pdo->prepare($sq); $st2->execute($sb); $students = $st2->fetchAll(); } catch(Exception $e){}

// All parents for searchable dropdown
$all_parents = [];
try {
    $pq = $pdo->prepare("SELECT parent_uuid, name, phone, email FROM parents WHERE school_uuid=? ORDER BY name ASC");
    $pq->execute([$school_uuid]);
    $all_parents = $pq->fetchAll();
} catch(Exception $e){}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-indigo-400"></i> Student Management
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo number_format($total); ?> students matched</p>
        </div>
        <?php if ($can_write): ?>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('promoteModal').classList.remove('hidden')"
                class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl flex items-center gap-2">
                <i data-lucide="arrow-up-circle" class="w-4 h-4 text-amber-400"></i> Promote Class
            </button>
            <button onclick="document.getElementById('csvModal').classList.remove('hidden')"
                class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl flex items-center gap-2">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400"></i> CSV Import
            </button>
            <a href="export_csv.php?type=students"
                class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl flex items-center gap-2">
                <i data-lucide="download" class="w-4 h-4 text-sky-400"></i> Export CSV
            </a>
            <button onclick="document.getElementById('addStudentModal').classList.remove('hidden')"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Enroll Student
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="roster">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name…"
            class="flex-1 min-w-36 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
        <select name="filter_class" id="filterClassSel" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="">All Classes</option>
            <?php foreach ($roster_classes as $cl): ?>
                <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $filter_cl===$cl?'selected':''; ?>><?php echo htmlspecialchars($cl); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_arm" id="filterArmSel" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]" <?php echo $filter_cl?'':'disabled'; ?>>
            <option value="">All Arms</option>
            <?php foreach ($roster_arms as $ar): ?>
                <option value="<?php echo htmlspecialchars($ar); ?>" <?php echo $filter_arm===$ar?'selected':''; ?>><?php echo htmlspecialchars($ar); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_status" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="Active"    <?php echo $filter_st==='Active'   ?'selected':''; ?>>Active</option>
            <option value="Graduated" <?php echo $filter_st==='Graduated'?'selected':''; ?>>Graduated</option>
            <option value="Withdrawn" <?php echo $filter_st==='Withdrawn'?'selected':''; ?>>Withdrawn</option>
            <option value="Suspended" <?php echo $filter_st==='Suspended'?'selected':''; ?>>Suspended</option>
            <option value=""          <?php echo $filter_st===''         ?'selected':''; ?>>All Statuses</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl">Filter</button>
        <a href="dashboard.php?section=roster" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] font-bold rounded-xl">Clear</a>
    </form>

    <!-- Table -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                <tr>
                    <th class="py-3.5 px-4">Student</th>
                    <th class="py-3.5 px-4">Class / Arm</th>
                    <th class="py-3.5 px-4">Roll No</th>
                    <th class="py-3.5 px-4">Linked Parent</th>
                    <th class="py-3.5 px-4">Health</th>
                    <th class="py-3.5 px-4">Status</th>
                    <th class="py-3.5 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php if (empty($students)): ?>
                <tr><td colspan="7" class="py-10 text-center text-[var(--text-secondary)] italic">No students found.</td></tr>
            <?php else: foreach ($students as $std):
                $hlth = json_decode($std['healthcare_json'] ?? '{}', true) ?: [];
                $sc   = ['Active'=>'bg-emerald-500/10 text-emerald-400','Graduated'=>'bg-blue-500/10 text-blue-400',
                         'Withdrawn'=>'bg-slate-500/10 text-slate-400','Suspended'=>'bg-rose-500/10 text-rose-400'];
            ?>
            <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors">
                <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($std['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars(asset_url($std['photo_path'])); ?>"
                                 class="w-9 h-9 rounded-full object-cover border border-[var(--border-color)] shrink-0"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <?php endif; ?>
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                             style="background:<?php echo htmlspecialchars($school['theme_color']??'#4F46E5'); ?>;<?php echo !empty($std['photo_path'])?'display:none;':''; ?>">
                            <?php echo strtoupper(substr($std['name'],0,2)); ?>
                        </div>
                        <div>
                            <span class="font-bold block"><?php echo htmlspecialchars($std['name']); ?></span>
                            <span class="text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($std['date_of_birth'] ?? '—'); ?></span>
                        </div>
                    </div>
                </td>
                <td class="py-3 px-4 font-semibold">
                    <?php echo htmlspecialchars($std['class']); ?>
                    <span class="text-[var(--text-secondary)]">(<?php echo htmlspecialchars($std['arm'] ?: 'Gold'); ?>)</span>
                </td>
                <td class="py-3 px-4 font-mono text-indigo-400 font-bold"><?php echo htmlspecialchars($std['roll_number']); ?></td>
                <td class="py-3 px-4">
                    <?php if (!empty($std['parent_uuid']) && !empty($std['linked_parent_name'])): ?>
                        <span class="block font-semibold text-emerald-400"><?php echo htmlspecialchars($std['linked_parent_name']); ?></span>
                        <span class="text-[10px] text-[var(--text-secondary)]">Linked</span>
                    <?php elseif (!empty($std['parent_name'])): ?>
                        <span class="block font-semibold"><?php echo htmlspecialchars($std['parent_name']); ?></span>
                        <span class="text-[10px] text-amber-400">Not in Parents DB</span>
                    <?php else: ?>
                        <span class="text-[var(--text-secondary)] italic">—</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4">
                    <span class="inline-flex items-center gap-1 bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2 py-0.5 rounded text-[10px] font-bold">
                        <?php echo htmlspecialchars($hlth['blood_group'] ?? 'O+'); ?> / <?php echo htmlspecialchars($hlth['geno'] ?? $hlth['genotype'] ?? 'AA'); ?>
                    </span>
                </td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $sc[$std['status']] ?? 'bg-amber-500/10 text-amber-400'; ?>">
                        <?php echo htmlspecialchars($std['status']); ?>
                    </span>
                </td>
                <td class="py-3 px-4 text-right space-x-1">
                    <?php if ($can_write): ?>
                        <button onclick='openEditStudentModal(<?php echo json_encode($std, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'
                            class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:text-white text-[var(--text-secondary)] rounded-lg text-[10px] font-bold">Edit</button>
                    <?php endif; ?>
                    <?php if ($active_role === 'School Admin'): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this student?')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_delete_student" value="1">
                            <input type="hidden" name="student_uuid" value="<?php echo htmlspecialchars($std['student_uuid']); ?>">
                            <button type="submit" class="px-2.5 py-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 rounded-lg text-[10px] font-bold">Delete</button>
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

<!-- ═══ ADD STUDENT MODAL ═══ -->
<div id="addStudentModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-2xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Enroll New Student</h3>
            <button onclick="document.getElementById('addStudentModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-5"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_student" value="1">

            <!-- Basic info -->
            <div>
                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">Student Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Full Name *</label>
                        <input type="text" name="student_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Class *</label>
                        <select name="student_class" id="addStdClass" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">Select class</option>
                            <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Arm</label>
                        <select name="student_arm" id="addStdArm" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]" disabled>
                            <option value="">Select a class first</option>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Gender</label>
                        <select name="gender" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">-- Select --</option><option>Male</option><option>Female</option>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Admission Date</label>
                        <input type="date" name="admission_date" value="<?php echo date('Y-m-d'); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Passport Photo</label>
                        <input type="file" name="student_photo" accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-secondary)] file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white"></div>
                </div>
            </div>

            <!-- Parent linking -->
            <div>
                <p class="text-[10px] font-bold text-pink-400 uppercase tracking-widest mb-3">Parent / Guardian</p>
                <!-- Option A: link to existing parent -->
                <div class="p-4 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl space-y-3 mb-3">
                    <p class="text-[10px] font-bold text-[var(--text-secondary)]">Option A — Link to existing parent record:</p>
                    <input type="text" id="addParentSearch" placeholder="Type parent name to filter…" oninput="filterParentSelect('addParentSearch','addParentUuidSelect')"
                        class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <select name="parent_uuid" id="addParentUuidSelect" size="4"
                        class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="">— No link / enter manually below —</option>
                        <?php foreach ($all_parents as $par): ?>
                        <option value="<?php echo htmlspecialchars($par['parent_uuid']); ?>">
                            <?php echo htmlspecialchars($par['name']); ?> — <?php echo htmlspecialchars($par['phone']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Option B: enter manually -->
                <div class="p-4 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl space-y-3">
                    <p class="text-[10px] font-bold text-[var(--text-secondary)]">Option B — Enter parent details manually (if not in DB yet):</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-[10px] text-[var(--text-secondary)] mb-1">Parent Name</label>
                            <input type="text" name="parent_name" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                        <div><label class="block text-[10px] text-[var(--text-secondary)] mb-1">Parent Email</label>
                            <input type="email" name="parent_email" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                        <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] mb-1">Parent Phone</label>
                            <input type="tel" name="parent_phone" pattern="[0-9]+" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    </div>
                </div>
            </div>

            <!-- Healthcare -->
            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-3">Healthcare</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Blood Group</label>
                        <select name="blood_group" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $bg): ?><option><?php echo $bg; ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Genotype</label>
                        <select name="genotype" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['AA','AS','SS','AC'] as $gt): ?><option><?php echo $gt; ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Known Allergies / Conditions</label>
                        <input type="text" name="allergies" placeholder="e.g. None" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Emergency Contact</label>
                        <input type="text" name="emergency_contact" placeholder="Name & Phone"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                </div>
            </div>

            <div class="text-[10px] text-[var(--text-secondary)] bg-[var(--bg-tertiary)] rounded-xl p-3 border border-[var(--border-color)]">
                Roll number is auto-generated. If a parent is selected from the list, Option B fields are ignored.
            </div>
            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Complete Enrollment</button>
        </form>
    </div>
</div>

<!-- ═══ EDIT STUDENT MODAL ═══ -->
<div id="editStudentModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-2xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Edit Student Record</h3>
            <button onclick="document.getElementById('editStudentModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-5"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_edit_student" value="1">
            <input type="hidden" name="student_uuid"  id="editStdUuid">
            <input type="hidden" name="existing_photo" id="editStdPhoto">

            <div>
                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">Student Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Full Name *</label>
                        <input type="text" name="student_name" id="editStdName" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Roll Number</label>
                        <input type="text" name="roll_number" id="editStdRoll" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Status</label>
                        <select name="student_status" id="editStdStatus" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option>Active</option><option>Graduated</option><option>Withdrawn</option><option>Suspended</option>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Class</label>
                        <select name="student_class" id="editStdClass" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Arm</label>
                        <select name="student_arm" id="editStdArm" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($roster_arms as $ar): ?><option value="<?php echo htmlspecialchars($ar); ?>"><?php echo htmlspecialchars($ar); ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Gender</label>
                        <select name="gender" id="editStdGender" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">--</option><option>Male</option><option>Female</option>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="editStdDob" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">New Photo (optional)</label>
                        <input type="file" name="student_photo" accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white"></div>
                </div>
            </div>

            <!-- Parent link -->
            <div>
                <p class="text-[10px] font-bold text-pink-400 uppercase tracking-widest mb-3">Parent / Guardian Link</p>
                <input type="text" id="editParentSearch" placeholder="Type parent name to filter…" oninput="filterParentSelect('editParentSearch','editParentUuidSelect')"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] mb-2">
                <select name="parent_uuid" id="editParentUuidSelect" size="4"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">— No link —</option>
                    <?php foreach ($all_parents as $par): ?>
                    <option value="<?php echo htmlspecialchars($par['parent_uuid']); ?>">
                        <?php echo htmlspecialchars($par['name']); ?> — <?php echo htmlspecialchars($par['phone']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-[var(--text-secondary)] mt-1">Or update manual parent name/email below if not linking to a DB record:</p>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <div><input type="text"  name="parent_name"  id="editStdPName"  placeholder="Parent name"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><input type="email" name="parent_email" id="editStdPEmail" placeholder="Parent email"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                </div>
            </div>

            <!-- Healthcare -->
            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-3">Healthcare</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Blood Group</label>
                        <select name="blood_group" id="editStdBG" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $bg): ?><option><?php echo $bg; ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Genotype</label>
                        <select name="genotype" id="editStdGeno" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['AA','AS','SS','AC'] as $gt): ?><option><?php echo $gt; ?></option><?php endforeach; ?>
                        </select></div>
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg">Save Changes</button>
        </form>
    </div>
</div>

<!-- ═══ CSV IMPORT MODAL ═══ -->
<div id="promoteModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Promote / Graduate Class</h3>
            <button onclick="document.getElementById('promoteModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" class="space-y-4" onsubmit="return confirm('This will move every active student in the selected class. Continue?')"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_promote_class" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">From Class *</label>
                <select name="from_class" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-4 text-xs">
                <label class="flex items-center gap-1.5"><input type="radio" name="promotion_mode" value="promote" checked onchange="document.getElementById('toClassWrap').classList.remove('hidden')"> Promote to another class</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="promotion_mode" value="graduate" onchange="document.getElementById('toClassWrap').classList.add('hidden')"> Graduate (→ Alumni)</label>
            </div>
            <div id="toClassWrap">
                <label class="block text-[10px] font-bold uppercase mb-1">To Class *</label>
                <select name="to_class" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                </select>
            </div>
            <p class="text-[10px] text-[var(--text-secondary)]">Promoting updates every active student's class. Graduating moves them to Alumni and marks them inactive.</p>
            <button type="submit" class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl">Run Promotion</button>
        </form>
    </div>
</div>

<div id="csvModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">CSV Bulk Import</h3>
            <button onclick="document.getElementById('csvModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <p class="text-xs text-[var(--text-secondary)]">
            CSV columns: <code class="text-indigo-400 font-mono">Name, Class, Arm, Parent Name, Parent Email, Parent Phone, Gender, DOB</code><br>
            Header row is auto-skipped. Roll numbers are auto-generated.
        </p>
        <form method="POST" enctype="multipart/form-data" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_csv_upload_students" value="1">
            <input type="file" name="csv_file" accept=".csv" required
                class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white">
            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl">Upload & Import</button>
        </form>
    </div>
</div>

<script>
// Class → Arm cascades. Arms belong to one class, so the arm dropdowns only
// populate after a class is chosen (see wireClassArm() in header.php).
document.addEventListener('DOMContentLoaded', function () {
    wireClassArm('addStdClass', 'addStdArm');

    // Filter row: submit the form fresh whenever the class filter changes so
    // the server re-renders the arm <select> scoped to that class.
    const filterClassSel = document.getElementById('filterClassSel');
    if (filterClassSel) {
        filterClassSel.addEventListener('change', function () {
            document.getElementById('filterArmSel').value = '';
            this.form.submit();
        });
    }
});

async function loadEditStdArms(selectedArm) {
    const classSel = document.getElementById('editStdClass');
    const armSel   = document.getElementById('editStdArm');
    const cls = classSel.value;
    armSel.innerHTML = '<option value="">Loading…</option>';
    if (!cls) { armSel.innerHTML = '<option value="">Select a class first</option>'; return; }
    try {
        const res = await fetch('api/get-arms.php?class_name=' + encodeURIComponent(cls), { credentials: 'same-origin' });
        const data = await res.json();
        const arms = data.arms || [];
        armSel.innerHTML = '<option value="">' + (arms.length ? 'Select arm' : 'No arms for this class') + '</option>';
        arms.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a; opt.textContent = a;
            if (a === selectedArm) opt.selected = true;
            armSel.appendChild(opt);
        });
    } catch (e) { armSel.innerHTML = '<option value="">Failed to load arms</option>'; }
}
document.addEventListener('DOMContentLoaded', function () {
    const editClassSel = document.getElementById('editStdClass');
    if (editClassSel) editClassSel.addEventListener('change', () => loadEditStdArms(''));
});

// Filter parent searchable select
function filterParentSelect(inputId, selectId) {
    const f = document.getElementById(inputId).value.toUpperCase();
    const s = document.getElementById(selectId);
    for (let i = 0; i < s.options.length; i++) {
        s.options[i].style.display = s.options[i].text.toUpperCase().includes(f) ? '' : 'none';
    }
}

function openEditStudentModal(std) {
    const h = {};
    try { Object.assign(h, JSON.parse(std.healthcare_json || '{}')); } catch(e){}

    document.getElementById('editStdUuid').value       = std.student_uuid  || '';
    document.getElementById('editStdName').value       = std.name          || '';
    document.getElementById('editStdRoll').value       = std.roll_number   || '';
    document.getElementById('editStdClass').value      = std.class         || '';
    document.getElementById('editStdGender').value     = std.gender        || '';
    document.getElementById('editStdDob').value        = std.date_of_birth || '';
    document.getElementById('editStdPName').value      = std.parent_name   || '';
    document.getElementById('editStdPEmail').value     = std.parent_email  || '';
    document.getElementById('editStdPhoto').value      = std.photo_path    || '';
    document.getElementById('editStdStatus').value     = std.status        || 'Active';
    document.getElementById('editStdBG').value         = h.blood_group     || 'O+';
    document.getElementById('editStdGeno').value       = h.geno || h.genotype || 'AA';

    // Arm depends on the class — load this student's class arms then select theirs.
    loadEditStdArms(std.arm || '');

    // Pre-select parent in dropdown if linked
    const sel = document.getElementById('editParentUuidSelect');
    if (std.parent_uuid) {
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === std.parent_uuid) {
                sel.selectedIndex = i;
                break;
            }
        }
    } else {
        sel.selectedIndex = 0;
    }

    document.getElementById('editStudentModal').classList.remove('hidden');
}
</script>
