<?php
$schools = $pdo->query("SELECT * FROM schools ORDER BY id DESC")->fetchAll();
$catalog = $pdo->query("SELECT * FROM platform_feature_catalog ORDER BY sort_order ASC")->fetchAll();
$feature_map = [];
foreach ($pdo->query("SELECT school_uuid, feature_key, is_enabled FROM school_feature_access") as $row) {
    $feature_map[$row['school_uuid']][$row['feature_key']] = (int)$row['is_enabled'];
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">School Tenants</h1>
    </div>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                    <tr>
                        <th class="px-3.5 py-3">School</th>
                        <th class="px-3.5 py-3">Subdomain</th>
                        <th class="px-3.5 py-3">Plan</th>
                        <th class="px-3.5 py-3">Fee</th>
                        <th class="px-3.5 py-3">Status</th>
                        <th class="px-3.5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php foreach ($schools as $sch): 
                        $featureData = $feature_map[$sch['school_uuid']] ?? [];
                        $safeName = htmlspecialchars($sch['name'], ENT_QUOTES, 'UTF-8');
                        $safeSubdomain = htmlspecialchars($sch['subdomain'], ENT_QUOTES, 'UTF-8');
                        $safePlan = htmlspecialchars($sch['plan'], ENT_QUOTES, 'UTF-8');
                        $safeStatus = htmlspecialchars($sch['status'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="hover:bg-[var(--bg-tertiary)]/50">
                        <td class="px-3.5 py-3 font-bold text-[var(--text-primary)]"><?php echo $safeName; ?></td>
                        <td class="px-3.5 py-3 font-mono text-indigo-400"><?php echo $safeSubdomain; ?>.zetaphase.com.ng</td>
                        <td class="px-3.5 py-3">
                            <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded font-mono font-bold"><?php echo $safePlan; ?></span>
                        </td>
                        <td class="px-3.5 py-3 font-mono text-emerald-400 font-bold">₦<?php echo number_format($sch['monthly_fee'],2); ?></td>
                        <td class="px-3.5 py-3">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $sch['status']==='Active'?'bg-emerald-500/10 text-emerald-400':'bg-rose-500/10 text-rose-400'; ?>">
                                <?php echo $safeStatus; ?>
                            </span>
                        </td>
                        <td class="px-3.5 py-3">
                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                <a href="settings.php?school_uuid=<?php echo urlencode($sch['school_uuid']); ?>" 
                                   class="px-2.5 py-1 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap">Settings</a>

                                <?php if (!empty($sch['active_result_slip_template_uuid'])): ?>
                                <a href="../admin/preview_result_slip_template.php?template_uuid=<?php echo urlencode($sch['active_result_slip_template_uuid']); ?>&school_uuid=<?php echo urlencode($sch['school_uuid']); ?>" target="_blank" rel="noopener"
                                   class="px-2.5 py-1 bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap">Report Card</a>
                                <?php endif; ?>
                                
                                <button 
                                    data-uuid="<?php echo $sch['school_uuid']; ?>"
                                    data-name="<?php echo $safeName; ?>"
                                    data-features='<?php echo json_encode($featureData); ?>'
                                    onclick="openFeatureModal(this)"
                                    class="px-2.5 py-1 bg-purple-600/10 hover:bg-purple-600/20 text-purple-400 border border-purple-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap">Features</button>
                                
                                <button onclick="openBillingModal('<?php echo $sch['school_uuid']; ?>', '<?php echo $safeName; ?>', '<?php echo $sch['monthly_fee']; ?>', '<?php echo $safePlan; ?>', '<?php echo htmlspecialchars($sch['billing_cycle'], ENT_QUOTES); ?>')" 
                                        class="px-2.5 py-1 bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap">Billing</button>
                                
                                <button onclick="openResetModal('<?php echo htmlspecialchars($sch['admin_email'], ENT_QUOTES); ?>', '<?php echo $safeName; ?>')" 
                                        class="px-2.5 py-1 bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 border border-amber-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap">Reset</button>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this school permanently?')"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_delete_school" value="1">
                                    <input type="hidden" name="school_uuid" value="<?php echo $sch['school_uuid']; ?>">
                                    <button class="px-2.5 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-[10px] font-bold whitespace-nowrap">Delete</button>
                                </form>
                                
                                <?php if ($sch['status'] === 'Suspended'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Reactivate this school?')"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_toggle_suspend" value="1">
                                    <input type="hidden" name="school_uuid" value="<?php echo $sch['school_uuid']; ?>">
                                    <input type="hidden" name="new_status" value="Active">
                                    <button class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold whitespace-nowrap">Activate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Suspend this school?')"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_toggle_suspend" value="1">
                                    <input type="hidden" name="school_uuid" value="<?php echo $sch['school_uuid']; ?>">
                                    <input type="hidden" name="new_status" value="Suspended">
                                    <button class="px-2.5 py-1 bg-rose-600/10 hover:bg-rose-600/20 text-rose-400 border border-rose-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap">Suspend</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Feature Modal -->
<div id="featureModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-lg w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto relative">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Features: <span id="featSchoolName" class="text-indigo-400"></span></h3>
            <button onclick="closeModal('featureModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xl">✕</button>
        </div>
        <form method="POST" class="space-y-3" id="featureForm"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_update_school_features" value="1">
            <input type="hidden" id="featSchoolUuid" name="school_uuid">
            <?php foreach ($catalog as $cat): ?>
            <div class="flex items-center justify-between p-2.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($cat['feature_label']); ?></span>
                <input type="checkbox" name="school_feature_rights[<?php echo htmlspecialchars($cat['feature_key']); ?>]"
                       class="w-4 h-4 accent-indigo-500 feat-checkbox" 
                       <?php echo ($cat['is_core'] ?? 0) ? 'checked disabled' : ''; ?>>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition">Save Features</button>
        </form>
    </div>
</div>

<!-- Billing Modal -->
<div id="billingModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4 relative">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Adjust Billing</h3>
            <button onclick="closeModal('billingModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xl">✕</button>
        </div>
        <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_manual_billing_adjust" value="1">
            <input type="hidden" id="billSchoolUuid" name="school_uuid">
            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">School</label>
                <input type="text" id="billSchoolName" disabled class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-bold">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Fee (₦)</label>
                    <input type="number" step="0.01" id="billFee" name="monthly_fee" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Plan</label>
                    <select id="billPlan" name="plan" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option>Basic</option>
                        <option>Standard</option>
                        <option>Pro</option>
                        <option>Custom</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Billing Cycle</label>
                <select id="billCycle" name="billing_cycle" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option>Monthly</option>
                    <option>Yearly</option>
                    <option>Quarterly</option>
                    <option>One-Time</option>
                </select>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">Save Billing</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4 relative">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Reset Admin Password</h3>
            <button onclick="closeModal('resetModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xl">✕</button>
        </div>
        <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_reset_admin_password" value="1">
            <input type="hidden" id="resetEmail" name="admin_email">
            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">School</label>
                <input type="text" id="resetSchoolName" disabled class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-bold">
            </div>
            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">New Password</label>
                <input type="password" name="new_password" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-2 text-xs text-[var(--text-primary)] font-mono">
            </div>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition">Update Password</button>
        </form>
    </div>
</div>

<script>
/**
 * Complete Modal Management System
 */
const ModalManager = {
    // Track all open modals
    openModals: [],
    
    /**
     * Open a modal
     * @param {string} modalId - The ID of the modal to open
     */
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Close all modals first
        this.closeAll();
        
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        this.openModals.push(modalId);
        
        // Recreate icons if lucide is available
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    },
    
    /**
     * Close a specific modal
     * @param {string} modalId - The ID of the modal to close
     */
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.classList.add('hidden');
        modal.style.display = '';
        this.openModals = this.openModals.filter(id => id !== modalId);
    },
    
    /**
     * Close all open modals
     */
    closeAll() {
        this.openModals.forEach(id => {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = '';
            }
        });
        this.openModals = [];
    },
    
    /**
     * Setup outside click listeners for multiple modals
     * @param {string|array} modalIds - Single modal ID or array of modal IDs
     */
    setupOutsideClick(modalIds) {
        const ids = Array.isArray(modalIds) ? modalIds : [modalIds];
        
        ids.forEach(id => {
            const modal = document.getElementById(id);
            if (!modal) return;
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.close(id);
                }
            });
        });
    },
    
    /**
     * Setup Escape key listener for all modals
     */
    setupEscapeKey() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (this.openModals.length > 0) {
                    // Close the most recently opened modal first (LIFO)
                    const lastModal = this.openModals[this.openModals.length - 1];
                    this.close(lastModal);
                }
            }
        });
    },
    
    /**
     * Initialize the modal system
     * @param {string|array} modalIds - Modal IDs to manage
     */
    init(modalIds) {
        this.setupOutsideClick(modalIds);
        this.setupEscapeKey();
        console.log('✅ Modal system initialized for:', modalIds);
    }
};

// ── Feature Modal ──────────────────────────────────────────────────────────
function openFeatureModal(button) {
    try {
        const uuid = button.dataset.uuid;
        const name = button.dataset.name;
        const savedState = JSON.parse(button.dataset.features);
        
        document.getElementById('featSchoolUuid').value = uuid;
        document.getElementById('featSchoolName').textContent = name;
        
        const hasSaved = savedState && Object.keys(savedState).length > 0;
        document.querySelectorAll('.feat-checkbox').forEach(box => {
            if (box.disabled) return;
            const key = box.name.match(/\[(.*?)\]/)?.[1];
            if (key) {
                box.checked = hasSaved ? !!savedState[key] : true;
            }
        });
        
        ModalManager.open('featureModal');
    } catch (error) {
        console.error('Error opening feature modal:', error);
        alert('Error loading features. Please try again.');
    }
}

// ── Billing Modal ──────────────────────────────────────────────────────────
function openBillingModal(uuid, name, fee, plan, cycle) {
    try {
        document.getElementById('billSchoolUuid').value = uuid;
        document.getElementById('billSchoolName').value = name;
        document.getElementById('billFee').value = fee;
        document.getElementById('billPlan').value = plan;
        document.getElementById('billCycle').value = cycle;
        ModalManager.open('billingModal');
    } catch (error) {
        console.error('Error opening billing modal:', error);
        alert('Error loading billing data. Please try again.');
    }
}

// ── Reset Password Modal ──────────────────────────────────────────────────
function openResetModal(email, name) {
    try {
        document.getElementById('resetEmail').value = email;
        document.getElementById('resetSchoolName').value = name;
        ModalManager.open('resetModal');
    } catch (error) {
        console.error('Error opening reset modal:', error);
        alert('Error loading reset data. Please try again.');
    }
}

// ── Close Modal (for inline onclick) ──────────────────────────────────────
function closeModal(modalId) {
    ModalManager.close(modalId);
}

// ── Close all modals ──────────────────────────────────────────────────────
function closeAllModals() {
    ModalManager.closeAll();
}

// ── Initialize Modal System ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all modals
    ModalManager.init(['featureModal', 'billingModal', 'resetModal']);
    
    // Debug
    console.log('✅ Tenant management page loaded successfully');
    console.log('📊 Total schools:', <?php echo count($schools); ?>);
});

// ── Legacy support for old modal functions (if needed) ───────────────────
function openModal(modalId) {
    ModalManager.open(modalId);
}

// ── Additional utility functions ──────────────────────────────────────────

/**
 * Toggle modal visibility
 * @param {string} modalId - The ID of the modal to toggle
 */
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    if (modal.classList.contains('hidden')) {
        ModalManager.open(modalId);
    } else {
        ModalManager.close(modalId);
    }
}

/**
 * Check if a modal is open
 * @param {string} modalId - The ID of the modal to check
 * @returns {boolean}
 */
function isModalOpen(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return false;
    return !modal.classList.contains('hidden');
}

/**
 * Get all open modals
 * @returns {array}
 */
function getOpenModals() {
    return ModalManager.openModals;
}

/**
 * Prevent scroll on body when modal is open
 * @param {boolean} prevent - Whether to prevent scroll
 */
function preventBodyScroll(prevent) {
    if (prevent) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// ── Override the open method to handle body scroll ──────────────────────
const originalOpen = ModalManager.open.bind(ModalManager);
ModalManager.open = function(modalId) {
    originalOpen(modalId);
    if (this.openModals.length > 0) {
        preventBodyScroll(true);
    }
};

const originalClose = ModalManager.close.bind(ModalManager);
ModalManager.close = function(modalId) {
    originalClose(modalId);
    if (this.openModals.length === 0) {
        preventBodyScroll(false);
    }
};

const originalCloseAll = ModalManager.closeAll.bind(ModalManager);
ModalManager.closeAll = function() {
    originalCloseAll();
    preventBodyScroll(false);
};

// ── Keyboard shortcuts ────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    // Ctrl + M to close all modals
    if (e.ctrlKey && e.key === 'm') {
        e.preventDefault();
        ModalManager.closeAll();
    }
});
</script>