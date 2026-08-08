<?php
/**
 * Subdomain Resolver — shared by index.php, login.php, apply.php
 * Detects the current subdomain from HTTP_HOST and resolves school context.
 *
 * Sets:
 *   $ctx['type']        => 'platform' | 'school' | 'root'
 *   $ctx['subdomain']   => e.g. 'khadob'
 *   $ctx['school']      => PDO row from schools table, or null
 *   $ctx['is_platform'] => bool
 *   $ctx['is_school']   => bool
 *   $ctx['is_root']     => bool (base domain — show landing page)
 */
function resolve_subdomain(PDO $pdo): array {
    $ctx = [
        'type'        => 'root',
        'subdomain'   => '',
        'school'      => null,
        'is_platform' => false,
        'is_school'   => false,
        'is_root'     => true,
    ];

    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');

    // Strip port if present (e.g. localhost:8080)
    if (str_contains($host, ':')) {
        $host = explode(':', $host)[0];
    }

    // Root domains — show landing page normally
    $root_domains = ['zetaphase.com.ng', 'www.zetaphase.com.ng', 'localhost', '127.0.0.1'];
    if (in_array($host, $root_domains, true)) {
        return $ctx;
    }

    // Extract subdomain: everything before first dot
    $parts = explode('.', $host);
    if (count($parts) < 2) {
        return $ctx; // malformed — treat as root
    }

    $sub = $parts[0];
    $ctx['subdomain'] = $sub;

    // Platform manager subdomain
    if ($sub === 'platform') {
        $ctx['type']        = 'platform';
        $ctx['is_platform'] = true;
        $ctx['is_root']     = false;
        return $ctx;
    }

    // Try to resolve as a school subdomain
    try {
        $stmt = $pdo->prepare("SELECT * FROM schools WHERE subdomain = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$sub]);
        $school = $stmt->fetch();
        if ($school) {
            $ctx['type']      = 'school';
            $ctx['school']    = $school;
            $ctx['is_school'] = true;
            $ctx['is_root']   = false;
        }
        // Unknown subdomain — fall through to root (landing page)
    } catch (Exception $e) {}

    return $ctx;
}