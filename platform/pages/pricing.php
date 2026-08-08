<?php
$packages = $pdo->query("SELECT * FROM pricing_packages ORDER BY monthly_price ASC")->fetchAll();
$catalog = $pdo->query("SELECT * FROM platform_feature_catalog ORDER BY sort_order ASC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Pricing & Packages</h1>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($packages as $pkg): 
            $features = json_decode($pkg['features_json'], true) ?: [];
        ?>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_update_pricing" value="1">
                <input type="hidden" name="tier_name" value="<?php echo htmlspecialchars($pkg['tier_name']); ?>">
                <h3 class="text-sm font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($pkg['tier_name']); ?></h3>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Monthly (₦)</label>
                    <input type="number" step="0.01" name="monthly_price" value="<?php echo $pkg['monthly_price']; ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono" required>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Yearly (₦)</label>
                    <input type="number" step="0.01" name="yearly_price" value="<?php echo $pkg['yearly_price']; ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono" required>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Max Students</label>
                    <input type="number" name="max_students" value="<?php echo $pkg['max_students']; ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono" required>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"><?php echo htmlspecialchars($pkg['description']); ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-2">Features</label>
                    <div class="space-y-1 max-h-48 overflow-y-auto pr-1 bg-[var(--bg-tertiary)] rounded-xl p-3 border border-[var(--border-color)]">
                        <?php foreach ($catalog as $cat): 
                            $checked = in_array($cat['feature_label'], $features, true);
                        ?>
                        <label class="flex items-center gap-2 text-[11px] text-[var(--text-primary)] cursor-pointer">
                            <input type="checkbox" name="package_features[]" value="<?php echo htmlspecialchars($cat['feature_label']); ?>" <?php echo $checked ? 'checked' : ''; ?> class="w-3.5 h-3.5 accent-indigo-500">
                            <?php echo htmlspecialchars($cat['feature_label']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold">Save</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>