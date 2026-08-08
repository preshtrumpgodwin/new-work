<?php
/**
 * SECTION: Staff & HR Directory
 * Full CRUD — name, email, phone, role, department, qualification, TRCN,
 * salary, gender, DOB, address, date employed, photo, healthcare, permissions.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Staff & HR' => null]);
$can_write = ($active_role === 'School Admin');

$search     = safe_str($_GET['q']           ?? '');
$filter_role= safe_str($_GET['filter_role'] ?? '');
$total      = 0;

try {
    $cq   = "SELECT COUNT(*) FROM staff WHERE school_uuid=?";
    $cb   = [$school_uuid];
    if ($search)      { $cq .= " AND (name LIKE ? OR email LIKE ?)"; $cb[] = "%$search%"; $cb[] = "%$search%"; }
    if ($filter_role) { $cq .= " AND role=?"; $cb[] = $filter_role; }
    $cs   = $pdo->prepare($cq); $cs->execute($cb);
    $total= (int)$cs->fetchColumn();
} catch (Exception $e) {}

$pg       = paginate($total, 20, 'hr', array_filter(['q'=>$search,'filter_role'=>$filter_role]));
$staffList= [];
try {
    $sq  = "SELECT * FROM staff WHERE school_uuid=?";
    $sb  = [$school_uuid];
    if ($search)      { $sq .= " AND (name LIKE ? OR email LIKE ?)"; $sb[] = "%$search%"; $sb[] = "%$search%"; }
    if ($filter_role) { $sq .= " AND role=?"; $sb[] = $filter_role; }
    $sq .= " ORDER BY name ASC LIMIT {$pg['limit']} OFFSET {$pg['offset']}";
    $st  = $pdo->prepare($sq); $st->execute($sb);
    $staffList = $st->fetchAll();
} catch (Exception $e) {}

$roles_list = ['Teacher','Non-Teaching','Nurse','Bursar','Librarian','Security','Cleaner','IT Officer','Accountant','Vice Principal','Principal','Other'];
$dept_list  = ['Academics','Administration','Sciences','Arts','Commercial','Technical','Health','Library','Security','ICT','Maintenance'];
$f_keys     = ['attendance'=>'Attendance','timetable'=>'Timetable','cbt'=>'CBT','academics'=>'Report Cards',
                'healthcare'=>'Healthcare','disciplinary'=>'Disciplinary','library'=>'Library',
                'hostel'=>'Hostel','transport'=>'Transport','finance'=>'Finance'];

$tab = safe_str($_GET['tab'] ?? 'directory');
$all_staff_min = [];
try {
    $asm = $pdo->prepare("SELECT staff_uuid, name, salary FROM staff WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $asm->execute([$school_uuid]);
    $all_staff_min = $asm->fetchAll();
} catch (Exception $e) {}

$leave_requests = [];
try {
    $lq = $pdo->prepare("SELECT * FROM staff_leave_requests WHERE school_uuid=? ORDER BY created_at DESC LIMIT 100");
    $lq->execute([$school_uuid]);
    $leave_requests = $lq->fetchAll();
} catch (Exception $e) {}

$appraisals = [];
try {
    $aq = $pdo->prepare("SELECT * FROM staff_appraisals WHERE school_uuid=? ORDER BY created_at DESC LIMIT 100");
    $aq->execute([$school_uuid]);
    $appraisals = $aq->fetchAll();
} catch (Exception $e) {}

$payslips = [];
try {
    $pq = $pdo->prepare("SELECT * FROM staff_payslips WHERE school_uuid=? ORDER BY created_at DESC LIMIT 100");
    $pq->execute([$school_uuid]);
    $payslips = $pq->fetchAll();
} catch (Exception $e) {}

// Letters tab — editable Letter of Employment templates + issued letters
$letter_templates = [];
$issued_letters    = [];
try {
    $ltq = $pdo->prepare("SELECT * FROM hr_employment_letter_templates WHERE school_uuid=? ORDER BY is_default DESC, title ASC");
    $ltq->execute([$school_uuid]);
    $letter_templates = $ltq->fetchAll();
    $liq = $pdo->prepare("SELECT l.*, s.name AS staff_name FROM hr_employment_letters_issued l LEFT JOIN staff s ON s.staff_uuid=l.staff_uuid WHERE l.school_uuid=? ORDER BY l.issued_at DESC LIMIT 50");
    $liq->execute([$school_uuid]);
    $issued_letters = $liq->fetchAll();
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)] flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="briefcase" class="w-5 h-5 text-purple-400"></i> Staff & HR
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo number_format($total); ?> staff records</p>
        </div>
        <div class="flex bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl p-1 text-xs">
            <?php foreach (['directory'=>'Directory','leave'=>'Leave','payroll'=>'Payroll','appraisals'=>'Appraisals','letters'=>'Letters'] as $tk=>$tl): ?>
            <a href="dashboard.php?section=hr&tab=<?php echo $tk; ?>" class="px-3 py-1.5 rounded-lg font-bold <?php echo $tab===$tk ? 'bg-purple-600 text-white' : 'text-[var(--text-secondary)]'; ?>"><?php echo $tl; ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($tab === 'directory'): ?>
    <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-end gap-4">
        <a href="export_csv.php?type=staff" class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs font-bold rounded-xl flex items-center gap-2"><i data-lucide="download" class="w-4 h-4 text-sky-400"></i> Export CSV</a>
        <?php if ($can_write): ?>
        <button onclick="document.getElementById('addStaffModal').classList.remove('hidden')"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add Staff Member
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="hr"><input type="hidden" name="tab" value="directory">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name or email…"
            class="flex-1 min-w-40 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
        <select name="filter_role" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="">All Roles</option>
            <?php foreach ($roles_list as $r): ?>
                <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $filter_role===$r?'selected':''; ?>><?php echo htmlspecialchars($r); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl">Search</button>
        <a href="dashboard.php?section=hr" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] font-bold rounded-xl">Clear</a>
    </form>

    <!-- Table -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                <tr>
                    <th class="p-3.5">Staff Member</th>
                    <th class="p-3.5">Role / Dept</th>
                    <th class="p-3.5">Qualification</th>
                    <th class="p-3.5">Salary</th>
                    <th class="p-3.5">Status</th>
                    <th class="p-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php if (empty($staffList)): ?>
                <tr><td colspan="6" class="p-8 text-center text-[var(--text-secondary)] italic">No staff records yet.</td></tr>
            <?php else: foreach ($staffList as $stf):
                $sh = json_decode($stf['healthcare_json'] ?? '{}', true) ?: [];
            ?>
                <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors">
                    <td class="p-3.5">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($stf['photo_path'])): ?>
                                <img src="<?php echo htmlspecialchars(asset_url($stf['photo_path'])); ?>"
                                     class="w-9 h-9 rounded-full object-cover border border-[var(--border-color)] shrink-0"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <?php endif; ?>
                            <div class="w-9 h-9 rounded-full bg-purple-600 flex items-center justify-center text-white text-xs font-bold shrink-0"
                                 style="<?php echo !empty($stf['photo_path'])?'display:none;':''; ?>">
                                <?php echo strtoupper(substr($stf['name'],0,2)); ?>
                            </div>
                            <div>
                                <span class="font-bold block"><?php echo htmlspecialchars($stf['name']); ?></span>
                                <span class="text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($stf['email']); ?></span>
                                <?php if (!empty($stf['phone'])): ?>
                                <span class="text-[10px] text-[var(--text-secondary)] block font-mono"><?php echo htmlspecialchars($stf['phone']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="p-3.5">
                        <span class="block font-semibold"><?php echo htmlspecialchars($stf['role']); ?></span>
                        <span class="text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($stf['department'] ?? '—'); ?></span>
                    </td>
                    <td class="p-3.5">
                        <span class="block"><?php echo htmlspecialchars($stf['qualification'] ?: '—'); ?></span>
                        <?php if (!empty($stf['trcn_number'])): ?>
                        <span class="text-[10px] text-indigo-400 font-mono">TRCN: <?php echo htmlspecialchars($stf['trcn_number']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3.5 font-mono font-bold text-emerald-400">₦<?php echo number_format((float)$stf['salary']); ?></td>
                    <td class="p-3.5">
                        <?php $sc2 = ['Active'=>'bg-emerald-500/10 text-emerald-400','Inactive'=>'bg-slate-500/10 text-slate-400','Suspended'=>'bg-rose-500/10 text-rose-400']; ?>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $sc2[$stf['status']] ?? 'bg-amber-500/10 text-amber-400'; ?>">
                            <?php echo htmlspecialchars($stf['status']); ?>
                        </span>
                    </td>
                    <td class="p-3.5 text-right space-x-1">
                        <?php if ($can_write): ?>
                        <button onclick='openEditStaffModal(<?php echo json_encode($stf, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'
                            class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:text-white text-[var(--text-secondary)] rounded-lg text-[10px] font-bold">Edit</button>
                        <button onclick="openPermModal('<?php echo $stf['staff_uuid']; ?>','<?php echo htmlspecialchars(addslashes($stf['name'])); ?>')"
                            class="px-2.5 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 rounded-lg text-[10px] font-bold">Perms</button>
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
    <?php endif; ?>

    <?php if ($tab === 'leave'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Request Leave</h3>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_request_leave" value="1">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Staff Member *</label>
                    <select name="staff_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($all_staff_min as $s): ?><option value="<?php echo htmlspecialchars($s['staff_uuid']); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Leave Type</label>
                    <select name="leave_type" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option>Annual</option><option>Sick</option><option>Maternity</option><option>Paternity</option><option>Compassionate</option><option>Unpaid</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-[10px] font-bold uppercase mb-1">Start *</label><input type="date" name="start_date" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] font-bold uppercase mb-1">End *</label><input type="date" name="end_date" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                </div>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Reason</label><textarea name="reason" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea></div>
                <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl">Submit Request</button>
            </form>
        </div>
        <div class="lg:col-span-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Staff</th><th class="p-3">Type</th><th class="p-3">Dates</th><th class="p-3">Status</th><?php if ($can_write): ?><th class="p-3 text-right">Action</th><?php endif; ?></tr></thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php if (empty($leave_requests)): ?><tr><td colspan="5" class="p-6 text-center italic text-[var(--text-secondary)]">No leave requests yet.</td></tr><?php endif; ?>
                    <?php foreach ($leave_requests as $l): $sc=['Pending'=>'amber','Approved'=>'emerald','Declined'=>'rose'][$l['status']] ?? 'slate'; ?>
                    <tr>
                        <td class="p-3 font-bold"><?php echo htmlspecialchars($l['staff_name']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($l['leave_type']); ?></td>
                        <td class="p-3 font-mono text-[10px]"><?php echo date('d M', strtotime($l['start_date'])); ?> – <?php echo date('d M Y', strtotime($l['end_date'])); ?></td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-<?php echo $sc; ?>-500/10 text-<?php echo $sc; ?>-400"><?php echo htmlspecialchars($l['status']); ?></span></td>
                        <?php if ($can_write): ?>
                        <td class="p-3 text-right">
                            <?php if ($l['status']==='Pending'): ?>
                            <div class="flex gap-2 justify-end">
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_review_leave" value="1"><input type="hidden" name="leave_uuid" value="<?php echo $l['leave_uuid']; ?>"><input type="hidden" name="decision" value="Approved"><button class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold">Approve</button></form>
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_review_leave" value="1"><input type="hidden" name="leave_uuid" value="<?php echo $l['leave_uuid']; ?>"><input type="hidden" name="decision" value="Declined"><button class="px-2 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-[10px] font-bold">Decline</button></form>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'payroll'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <?php if ($can_write): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Generate Payslip</h3>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_generate_payslip" value="1">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Staff Member *</label>
                    <select name="staff_uuid" id="payslipStaffSel" required onchange="const o=this.options[this.selectedIndex]; document.getElementById('payslipBasic').value = o.dataset.salary || 0;" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($all_staff_min as $s): ?><option value="<?php echo htmlspecialchars($s['staff_uuid']); ?>" data-salary="<?php echo (float)($s['salary'] ?? 0); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Pay Period *</label>
                    <input type="text" name="pay_period" required placeholder="e.g. July 2026" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div><label class="block text-[9px] font-bold uppercase mb-1">Basic (₦)</label><input type="number" step="0.01" id="payslipBasic" name="basic_salary" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-2 py-2 text-xs text-[var(--text-primary)]" value="<?php echo !empty($all_staff_min) ? (float)($all_staff_min[0]['salary'] ?? 0) : 0; ?>"></div>
                    <div><label class="block text-[9px] font-bold uppercase mb-1">Allowances</label><input type="number" step="0.01" name="allowances" value="0" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-2 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[9px] font-bold uppercase mb-1">Deductions</label><input type="number" step="0.01" name="deductions" value="0" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-2 py-2 text-xs text-[var(--text-primary)]"></div>
                </div>
                <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl">Generate Payslip</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="<?php echo $can_write ? 'lg:col-span-2' : 'lg:col-span-3'; ?> bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
            <div class="p-3 text-[10px] text-[var(--text-secondary)] border-b border-[var(--border-color)]">Salary disbursement requires approval by a full-access staff member or the school admin before it can be marked as paid out.</div>
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Staff</th><th class="p-3">Period</th><th class="p-3 text-right">Net Pay</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php if (empty($payslips)): ?><tr><td colspan="5" class="p-6 text-center italic text-[var(--text-secondary)]">No payslips generated yet.</td></tr><?php endif; ?>
                    <?php $can_approve_payslips = in_array($active_role, ['School Admin','Platform Manager']) || can_approve($active_role, $current_access ?? 'hide'); ?>
                    <?php foreach ($payslips as $p): ?>
                    <tr>
                        <td class="p-3 font-bold"><?php echo htmlspecialchars($p['staff_name']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($p['pay_period']); ?></td>
                        <td class="p-3 text-right font-mono text-emerald-400 font-bold">₦<?php echo number_format($p['net_pay'],2); ?></td>
                        <td class="p-3">
                            <?php if (!empty($p['disbursed_at'])): ?>
                                <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[10px] font-bold">Disbursed</span>
                            <?php elseif (($p['approval_status'] ?? 'Pending') === 'Approved'): ?>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded text-[10px] font-bold">Approved</span>
                            <?php elseif (($p['approval_status'] ?? 'Pending') === 'Rejected'): ?>
                                <span class="px-2 py-0.5 bg-rose-500/10 text-rose-400 rounded text-[10px] font-bold" title="<?php echo htmlspecialchars($p['rejection_reason'] ?? ''); ?>">Rejected</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 rounded text-[10px] font-bold">Pending Approval</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-right space-x-1 whitespace-nowrap">
                            <button onclick="printPayslip('<?php echo $p['payslip_uuid']; ?>')" class="text-indigo-400 inline-block align-middle"><i data-lucide="printer" class="w-3.5 h-3.5"></i></button>
                            <?php if ($can_approve_payslips && empty($p['disbursed_at'])): ?>
                                <?php if (($p['approval_status'] ?? 'Pending') !== 'Approved'): ?>
                                <form method="POST" class="inline"><?php echo csrf_field(); ?><input type="hidden" name="action_approve_payslip" value="1"><input type="hidden" name="payslip_uuid" value="<?php echo $p['payslip_uuid']; ?>"><button type="submit" class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded text-[10px] font-bold">Approve</button></form>
                                <?php endif; ?>
                                <?php if (($p['approval_status'] ?? 'Pending') === 'Pending'): ?>
                                <form method="POST" class="inline"><?php echo csrf_field(); ?><input type="hidden" name="action_reject_payslip" value="1"><input type="hidden" name="payslip_uuid" value="<?php echo $p['payslip_uuid']; ?>"><button type="submit" class="px-2 py-0.5 bg-rose-500/10 text-rose-400 rounded text-[10px] font-bold">Reject</button></form>
                                <?php endif; ?>
                                <?php if (($p['approval_status'] ?? 'Pending') === 'Approved'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Confirm salary has been disbursed?')"><?php echo csrf_field(); ?><input type="hidden" name="action_mark_payslip_disbursed" value="1"><input type="hidden" name="payslip_uuid" value="<?php echo $p['payslip_uuid']; ?>"><button type="submit" class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[10px] font-bold">Mark Disbursed</button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <div id="payslipPrint-<?php echo $p['payslip_uuid']; ?>" class="hidden print-payslip bg-white text-black p-8 max-w-md">
                        <h3 class="text-center font-black text-lg border-b-2 border-black pb-2 mb-4"><?php echo htmlspecialchars($school['name'] ?? 'School'); ?> — Payslip</h3>
                        <p class="text-xs">Staff: <strong><?php echo htmlspecialchars($p['staff_name']); ?></strong></p>
                        <p class="text-xs">Period: <strong><?php echo htmlspecialchars($p['pay_period']); ?></strong></p>
                        <table class="w-full text-xs mt-4">
                            <tr><td class="py-1">Basic Salary</td><td class="text-right">₦<?php echo number_format($p['basic_salary'],2); ?></td></tr>
                            <tr><td class="py-1">Allowances</td><td class="text-right">+₦<?php echo number_format($p['allowances'],2); ?></td></tr>
                            <tr><td class="py-1">Deductions</td><td class="text-right">-₦<?php echo number_format($p['deductions'],2); ?></td></tr>
                            <tr class="border-t-2 border-black font-bold"><td class="py-2">NET PAY</td><td class="text-right py-2">₦<?php echo number_format($p['net_pay'],2); ?></td></tr>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    function printPayslip(uuid) {
        const el = document.getElementById('payslipPrint-' + uuid);
        const w = window.open('', '_blank');
        w.document.write('<html><body>' + el.innerHTML + '</body></html>');
        w.document.close(); w.print();
    }
    </script>
    <?php endif; ?>

    <?php if ($tab === 'appraisals'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <?php if ($can_write): ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">New Appraisal</h3>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_add_appraisal" value="1">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Staff Member *</label>
                    <select name="staff_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($all_staff_min as $s): ?><option value="<?php echo htmlspecialchars($s['staff_uuid']); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Period *</label><input type="text" name="period_label" required placeholder="e.g. First Term 2025/2026" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                <?php foreach (['punctuality_rating'=>'Punctuality','subject_mastery_rating'=>'Subject Mastery','classroom_management_rating'=>'Classroom Management','teamwork_rating'=>'Teamwork'] as $fk=>$fl): ?>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1"><?php echo $fl; ?></label>
                    <select name="<?php echo $fk; ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php for ($r=1;$r<=5;$r++): ?><option value="<?php echo $r; ?>" <?php echo $r===3?'selected':''; ?>><?php echo $r; ?> <?php echo str_repeat('★',$r); ?></option><?php endfor; ?>
                    </select>
                </div>
                <?php endforeach; ?>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Comment</label><textarea name="overall_comment" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3 text-xs text-[var(--text-primary)]"></textarea></div>
                <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl">Save Appraisal</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="<?php echo $can_write ? 'lg:col-span-2' : 'lg:col-span-3'; ?> bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden divide-y divide-[var(--border-color)]">
            <?php if (empty($appraisals)): ?><p class="p-6 text-center italic text-xs text-[var(--text-secondary)]">No appraisals recorded yet.</p><?php endif; ?>
            <?php foreach ($appraisals as $ap):
                $avg = round(($ap['punctuality_rating']+$ap['subject_mastery_rating']+$ap['classroom_management_rating']+$ap['teamwork_rating'])/4, 1);
            ?>
            <div class="p-4 text-xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($ap['staff_name']); ?></span>
                    <span class="font-mono font-bold text-purple-400"><?php echo $avg; ?>/5 avg</span>
                </div>
                <p class="text-[var(--text-secondary)]"><?php echo htmlspecialchars($ap['period_label']); ?></p>
                <?php if (!empty($ap['overall_comment'])): ?><p class="text-[var(--text-secondary)] italic">"<?php echo htmlspecialchars($ap['overall_comment']); ?>"</p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'letters'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Template editor -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Letter of Employment Templates</h3>
            <p class="text-[10px] text-[var(--text-secondary)]">Use tokens: <code>{{staff_name}}</code>, <code>{{role}}</code>, <code>{{department}}</code>, <code>{{date_employed}}</code>, <code>{{salary}}</code>, <code>{{school_name}}</code>, <code>{{today}}</code></p>

            <?php if ($can_write): ?>
            <form method="POST" class="space-y-3 border border-[var(--border-color)] rounded-xl p-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="section" value="hr"><input type="hidden" name="tab" value="letters">
                <input type="hidden" name="action_save_letter_template" value="1">
                <input type="hidden" name="template_uuid" id="letterTemplateUuid" value="">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Template Title</label>
                    <input type="text" name="title" id="letterTemplateTitle" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]" placeholder="Letter of Employment">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Letter Body</label>
                    <textarea name="body_html" id="letterTemplateBody" rows="10" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">Dear {{staff_name}},

We are pleased to confirm your employment with {{school_name}} as {{role}} in the {{department}} department, effective {{date_employed}}.

Your monthly remuneration is {{salary}}.

We look forward to your valuable contribution.

Sincerely,
Management</textarea>
                </div>
                <label class="flex items-center gap-2 text-xs text-[var(--text-secondary)]"><input type="checkbox" name="is_default" value="1"> Set as default template</label>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-xl">Save Template</button>
            </form>
            <?php endif; ?>

            <div class="space-y-2">
                <?php if (empty($letter_templates)): ?>
                    <p class="text-xs italic text-[var(--text-secondary)]">No templates yet — create one above.</p>
                <?php endif; ?>
                <?php foreach ($letter_templates as $lt): ?>
                <div class="flex items-center justify-between bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2">
                    <div>
                        <span class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($lt['title']); ?></span>
                        <?php if ($lt['is_default']): ?><span class="ml-2 text-[9px] bg-indigo-600 text-white px-2 py-0.5 rounded-full font-bold">DEFAULT</span><?php endif; ?>
                    </div>
                    <?php if ($can_write): ?>
                    <button type="button" onclick='document.getElementById("letterTemplateUuid").value=<?php echo json_encode($lt['template_uuid']); ?>;document.getElementById("letterTemplateTitle").value=<?php echo json_encode($lt['title']); ?>;document.getElementById("letterTemplateBody").value=<?php echo json_encode($lt['body_html']); ?>;window.scrollTo(0,0);' class="text-[10px] text-indigo-400 hover:underline font-bold">Edit</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Issue a letter -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Issue Letter to Staff</h3>
            <?php if ($can_write): ?>
            <form method="POST" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="section" value="hr"><input type="hidden" name="tab" value="letters">
                <input type="hidden" name="action_issue_employment_letter" value="1">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Staff Member</label>
                    <select name="staff_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="">Select staff…</option>
                        <?php foreach ($all_staff_min as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['staff_uuid']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Template</label>
                    <select name="template_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($letter_templates as $lt): ?>
                        <option value="<?php echo htmlspecialchars($lt['template_uuid']); ?>" <?php echo $lt['is_default'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($lt['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-xl">Generate & Issue</button>
            </form>
            <?php endif; ?>

            <h4 class="text-xs font-bold text-[var(--text-secondary)] uppercase pt-2 border-t border-[var(--border-color)]">Recently Issued</h4>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php if (empty($issued_letters)): ?><p class="text-xs italic text-[var(--text-secondary)]">None issued yet.</p><?php endif; ?>
                <?php foreach ($issued_letters as $il): ?>
                <div class="flex items-center justify-between bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2">
                    <div>
                        <span class="text-xs font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($il['staff_name'] ?? 'Unknown'); ?></span>
                        <span class="block text-[10px] text-[var(--text-secondary)]"><?php echo date('M j, Y', strtotime($il['issued_at'])); ?></span>
                    </div>
                    <a href="print_employment_letter.php?letter_uuid=<?php echo urlencode($il['letter_uuid']); ?>" target="_blank" class="text-[10px] text-indigo-400 hover:underline font-bold">Print</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ═══ ADD STAFF MODAL ═══ -->
<div id="addStaffModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-2xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Add New Staff Member</h3>
            <button onclick="document.getElementById('addStaffModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-5"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_staff" value="1">

            <!-- Personal details -->
            <div>
                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">Personal Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Full Name *</label>
                        <input type="text" name="staff_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Gender</label>
                        <select name="gender" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">-- Select --</option>
                            <option>Male</option><option>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Email *</label>
                        <input type="email" name="staff_email" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Phone</label>
                        <input type="tel" name="staff_phone" pattern="[0-9]+" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Residential Address</label>
                        <input type="text" name="address" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Passport Photo</label>
                        <input type="file" name="staff_photo" accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-secondary)] file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-600 file:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Emergency Contact</label>
                        <input type="text" name="emergency_contact" placeholder="Name & Phone"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                </div>
            </div>

            <!-- Employment details -->
            <div>
                <p class="text-[10px] font-bold text-purple-400 uppercase tracking-widest mb-3">Employment Details</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Role / Position *</label>
                        <select name="staff_role" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($roles_list as $r): ?>
                                <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Department</label>
                        <select name="staff_dept" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($dept_list as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Date Employed</label>
                        <input type="date" name="date_employed" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Monthly Salary (₦)</label>
                        <input type="number" name="staff_salary" value="120000" min="0" step="500"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                    </div>
                </div>
            </div>

            <!-- Qualifications -->
            <div>
                <p class="text-[10px] font-bold text-amber-400 uppercase tracking-widest mb-3">Qualifications & Certification</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Highest Qualification</label>
                        <select name="staff_qual" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">-- Select --</option>
                            <option>FSLC</option><option>WAEC/NECO</option><option>OND</option><option>NCE</option>
                            <option>HND</option><option>B.Ed</option><option>B.Sc</option><option>B.A</option>
                            <option>PGDE</option><option>M.Ed</option><option>M.Sc</option><option>M.A</option>
                            <option>Ph.D</option><option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Course / Field of Study</label>
                        <input type="text" name="qual_course" placeholder="e.g. Education / Mathematics"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">TRCN Number</label>
                        <input type="text" name="trcn_number" placeholder="Optional — teachers only"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Other Certifications</label>
                        <input type="text" name="other_certifications" placeholder="e.g. PMP, ICAN, CIPM"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                </div>
            </div>

            <!-- Healthcare -->
            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-3">Healthcare Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Blood Group</label>
                        <select name="blood_group" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $bg): ?>
                                <option><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Genotype</label>
                        <select name="genotype" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['AA','AS','SS','AC'] as $gt): ?>
                                <option><?php echo $gt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Known Allergies / Medical Conditions</label>
                        <input type="text" name="allergies" placeholder="e.g. None"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    </div>
                </div>
            </div>

            <div class="text-[10px] text-[var(--text-secondary)] bg-[var(--bg-tertiary)] rounded-xl p-3 border border-[var(--border-color)]">
                A random temporary password is generated for each new staff member and shown once after enrollment — they'll be required to set their own password on first login.
            </div>
            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg">
                Save & Enroll Staff Member
            </button>
        </form>
    </div>
</div>

<!-- ═══ EDIT STAFF MODAL ═══ -->
<div id="editStaffModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-2xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Edit Staff Record</h3>
            <button onclick="document.getElementById('editStaffModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-5"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_edit_staff" value="1">
            <input type="hidden" name="staff_uuid"     id="editStfUuid">
            <input type="hidden" name="existing_photo" id="editStfPhoto">

            <div>
                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">Personal Information</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Full Name *</label>
                        <input type="text" name="staff_name" id="editStfName" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Gender</label>
                        <select name="gender" id="editStfGender" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">-- Select --</option><option>Male</option><option>Female</option>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Email *</label>
                        <input type="email" name="staff_email" id="editStfEmail" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Phone</label>
                        <input type="tel" name="staff_phone" id="editStfPhone" pattern="[0-9]+" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="editStfDob" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Status</label>
                        <select name="staff_status" id="editStfStatus" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option>Active</option><option>Inactive</option><option>Suspended</option>
                        </select></div>
                    <div class="col-span-2"><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Address</label>
                        <input type="text" name="address" id="editStfAddr" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">New Photo (optional)</label>
                        <input type="file" name="staff_photo" accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-600 file:text-white"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Emergency Contact</label>
                        <input type="text" name="emergency_contact" id="editStfEmerg" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                </div>
            </div>

            <div>
                <p class="text-[10px] font-bold text-purple-400 uppercase tracking-widest mb-3">Employment Details</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Role</label>
                        <select name="staff_role" id="editStfRole" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($roles_list as $r): ?><option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Department</label>
                        <select name="staff_dept" id="editStfDept" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach ($dept_list as $d): ?><option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Date Employed</label>
                        <input type="date" name="date_employed" id="editStfEmpDate" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Monthly Salary (₦)</label>
                        <input type="number" name="staff_salary" id="editStfSal" min="0" step="500" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                </div>
            </div>

            <div>
                <p class="text-[10px] font-bold text-amber-400 uppercase tracking-widest mb-3">Qualifications</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Highest Qualification</label>
                        <select name="staff_qual" id="editStfQual" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <option value="">-- Select --</option>
                            <?php foreach (['FSLC','WAEC/NECO','OND','NCE','HND','B.Ed','B.Sc','B.A','PGDE','M.Ed','M.Sc','M.A','Ph.D','Other'] as $q): ?>
                                <option value="<?php echo $q; ?>"><?php echo $q; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">TRCN Number</label>
                        <input type="text" name="trcn_number" id="editStfTrcn" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                </div>
            </div>

            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-3">Healthcare</p>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Blood Group</label>
                        <select name="blood_group" id="editStfBG" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $bg): ?><option><?php echo $bg; ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Genotype</label>
                        <select name="genotype" id="editStfGeno" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                            <?php foreach (['AA','AS','SS','AC'] as $gt): ?><option><?php echo $gt; ?></option><?php endforeach; ?>
                        </select></div>
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg">Save Changes</button>
        </form>
    </div>
</div>

<!-- ═══ PERMISSIONS MODAL ═══ -->
<div id="permModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Permissions: <span id="permStaffName" class="text-indigo-400"></span></h3>
            <button onclick="document.getElementById('permModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_update_staff_permissions" value="1">
            <input type="hidden" name="staff_uuid" id="permStaffUuid">
            <?php foreach ($f_keys as $fk => $label): ?>
            <div class="flex items-center justify-between p-2.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                <span class="font-bold"><?php echo $label; ?></span>
                <select name="perm_access[<?php echo $fk; ?>]" class="bg-[var(--bg-secondary)] border border-[var(--border-color)] text-xs text-[var(--text-primary)] rounded-lg px-2 py-1">
                    <option value="manage">Manage</option>
                    <option value="view">View Only</option>
                    <option value="none">No Access</option>
                </select>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl">Save Permissions</button>
        </form>
    </div>
</div>

<script>
function openEditStaffModal(s) {
    const h = JSON.parse(s.healthcare_json || '{}');
    document.getElementById('editStfUuid').value    = s.staff_uuid    || '';
    document.getElementById('editStfName').value    = s.name          || '';
    document.getElementById('editStfEmail').value   = s.email         || '';
    document.getElementById('editStfPhone').value   = s.phone         || '';
    document.getElementById('editStfGender').value  = s.gender        || '';
    document.getElementById('editStfDob').value     = s.date_of_birth || '';
    document.getElementById('editStfAddr').value    = s.address       || '';
    document.getElementById('editStfStatus').value  = s.status        || 'Active';
    document.getElementById('editStfRole').value    = s.role          || 'Teacher';
    document.getElementById('editStfDept').value    = s.department    || 'Academics';
    document.getElementById('editStfEmpDate').value = s.date_employed || '';
    document.getElementById('editStfSal').value     = s.salary        || 120000;
    document.getElementById('editStfQual').value    = s.qualification || '';
    document.getElementById('editStfTrcn').value    = s.trcn_number   || '';
    document.getElementById('editStfBG').value      = h.blood_group   || 'O+';
    document.getElementById('editStfGeno').value    = h.genotype      || 'AA';
    document.getElementById('editStfEmerg').value   = h.emergency_contact || '';
    document.getElementById('editStfPhoto').value   = s.photo_path    || '';
    document.getElementById('editStaffModal').classList.remove('hidden');
}
function openPermModal(uuid, name) {
    document.getElementById('permStaffUuid').textContent = '';
    document.getElementById('permStaffUuid').value = uuid;
    document.getElementById('permStaffName').textContent = name;
    document.getElementById('permModal').classList.remove('hidden');
}
</script>
