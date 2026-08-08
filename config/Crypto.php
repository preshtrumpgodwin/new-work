<?php
require_once __DIR__ . '/env-loader.php';
/**
 * Crypto — small at-rest encryption helper (SEC-5 fix).
 *
 * Used for sensitive columns that must live in the DB (e.g.
 * school_payment_settings.paystack_secret_key) so a DB compromise alone
 * doesn't hand over every tenant's live payment gateway secret.
 *
 * The encryption key MUST come from the environment / server config, never
 * from the database itself — otherwise a DB dump defeats the encryption.
 * Set it via an environment variable:
 *
 *     APP_ENCRYPTION_KEY=<64 hex chars, e.g. output of bin2hex(random_bytes(32))>
 *
 * On cPanel: Setup Environment Variables in the app's PHP config, or set it
 * in a .env-style include loaded before this file. If unset, a per-install
 * fallback file `config/.encryption_key` is auto-generated and used instead
 * (still outside any web-servable path such as /uploads) — this keeps
 * upgrades from breaking on hosts where env vars are inconvenient to set,
 * while remaining local to the server, not the DB.
 */
class Crypto
{
    private const CIPHER = 'aes-256-cbc';

    private static function key(): string
    {
        $envKey = getenv('APP_ENCRYPTION_KEY');
        if ($envKey && strlen($envKey) >= 32) {
            return substr(hash('sha256', $envKey, true), 0, 32);
        }

        // Fallback: a generated key file stored next to config/, never in the DB.
        $keyFile = __DIR__ . '/.encryption_key';
        if (!file_exists($keyFile)) {
            $generated = bin2hex(random_bytes(32));
            // Restrict permissions where the filesystem supports it (best-effort on shared hosting).
            file_put_contents($keyFile, $generated);
            @chmod($keyFile, 0600);
        }
        $fileKey = trim((string) file_get_contents($keyFile));
        return substr(hash('sha256', $fileKey, true), 0, 32);
    }

    /** Encrypt a plaintext value for storage. Returns base64("iv:ciphertext"), or '' for empty input. */
    public static function encrypt(?string $plaintext): string
    {
        if ($plaintext === null || $plaintext === '') return '';
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) return '';
        return 'enc:' . base64_encode($iv . $ciphertext);
    }

    /** Decrypt a value previously produced by encrypt(). Returns '' on failure/empty input. */
    public static function decrypt(?string $stored): string
    {
        if ($stored === null || $stored === '') return '';
        if (!str_starts_with($stored, 'enc:')) {
            // Not encrypted — legacy plaintext row from before this fix. Return as-is
            // so existing installs keep working; it will be re-encrypted on next save.
            return $stored;
        }
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) < 17) return '';
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        return $plaintext === false ? '' : $plaintext;
    }
}
