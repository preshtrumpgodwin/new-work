<?php
require_once __DIR__ . '/env-loader.php';
/**
 * Centralized security bootstrap.
 *
 * Every entry point that starts a session should call secure_session_start()
 * instead of calling session_start() directly. This is the single place that
 * controls:
 *   1. Session cookie flags (HttpOnly, SameSite, Secure)
 *   2. HTTP security response headers (X-Frame-Options, CSP, etc.)
 *
 * ── Local testing ──────────────────────────────────────────────────────────
 * Locally you're almost certainly running over plain http:// (XAMPP/MAMP,
 * php -S, etc.), not https://. Browsers silently DROP any cookie marked
 * `Secure` when it's not sent over HTTPS — so if we always set `secure=true`,
 * login will appear to "not work" locally (the session cookie just never
 * gets stored) with no obvious error.
 *
 * To test locally over plain HTTP, set an environment variable before
 * starting your dev server:
 *
 *     APP_ENV=local
 *
 * e.g. in XAMPP, add to your Apache vhost or a `.env`-loading snippet:
 *     putenv('APP_ENV=local');
 * or on the CLI:
 *     APP_ENV=local php -S localhost:8000
 *
 * When APP_ENV=local:
 *   - The `Secure` cookie flag is NOT set (so cookies work over http://)
 *   - Strict-Transport-Security header is NOT sent (it would force https
 *     redirects in the browser for your whole local domain otherwise)
 *   - Everything else (HttpOnly, SameSite, CSP, X-Frame-Options, etc.)
 *     still applies, so local testing stays close to production behavior.
 *
 * Do NOT set APP_ENV=local in production. Nothing in this file will refuse
 * to run if you forget — that's a deploy-checklist item, see
 * config/deploy-check.php.
 */

function app_is_local(): bool {
    $env = getenv('APP_ENV');
    return $env === 'local' || $env === 'development' || $env === 'dev';
}

/** Send the app's standard security headers. Safe to call more than once. */
function apply_security_headers(): void {
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Content-Security-Policy: deliberately permissive on script/style-src
    // because this app relies on inline <script>/<style> blocks and onclick=
    // handlers throughout (not built with nonces). This still meaningfully
    // blocks: framing by other sites, arbitrary <object>/<embed>, form
    // submission to third-party origins, and loading scripts from any
    // domain other than the ones explicitly listed below.
    // Tightening this to remove 'unsafe-inline' is a good follow-up project
    // (would require adding nonces to every inline <script> tag).
    $csp = "default-src 'self'; "
         . "img-src 'self' data: https://api.qrserver.com; "
         . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com data:; "
         . "connect-src 'self'; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "form-action 'self';";
    header("Content-Security-Policy: $csp");

    // Only send HSTS when we're not in local dev AND the request actually
    // arrived over HTTPS — sending it over plain http locally (or behind a
    // proxy that terminates TLS) can lock a browser into https-only for the
    // domain for the max-age duration, which is painful to undo locally.
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!app_is_local() && $is_https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Drop-in replacement for session_start(). Sets hardened cookie params
 * first (cookie params must be set BEFORE the session starts), then starts
 * the session, then applies the standard security headers.
 */
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => !app_is_local(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    apply_security_headers();
}
