<?php
/**
 * Breadcrumb component.
 *
 * Usage — call render_breadcrumb() from any section file:
 *
 *   render_breadcrumb([
 *       'Dashboard' => 'dashboard.php?section=overview',
 *       'Student Management' => 'dashboard.php?section=roster',
 *       'Enroll Student' => null,   // null = current page (no link)
 *   ]);
 *
 * The last item with a null URL is rendered as the active crumb.
 */

function render_breadcrumb(array $crumbs): void {
    if (empty($crumbs)) return;
    echo '<nav class="flex items-center space-x-1 text-[10px] text-[var(--text-secondary)] mb-4 font-mono">';
    $total = count($crumbs);
    $i     = 0;
    foreach ($crumbs as $label => $url) {
        $i++;
        $isLast = ($i === $total);
        if ($url && !$isLast) {
            echo '<a href="' . htmlspecialchars($url) . '" class="hover:text-[var(--text-primary)] transition-colors">'
               . htmlspecialchars($label) . '</a>';
            echo '<span class="text-[var(--border-color)] select-none mx-1">/</span>';
        } else {
            echo '<span class="text-[var(--text-primary)] font-bold">' . htmlspecialchars($label) . '</span>';
        }
    }
    echo '</nav>';
}
