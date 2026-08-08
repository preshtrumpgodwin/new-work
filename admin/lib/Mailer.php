<?php
/**
 * Mailer — minimal dependency-free SMTP client (raw socket, STARTTLS aware).
 * No Composer/PHPMailer required. Falls back to PHP's built-in mail() if
 * no SMTP host is configured, so schools without SMTP still get a best-effort
 * send via the server's local MTA when available.
 */
class Mailer
{
    /**
     * @return array{success: bool, response: string}
     */
    public static function send(array $school_settings, string $to_email, string $to_name, string $subject, string $body_html): array
    {
        $host = trim($school_settings['smtp_host'] ?? '');
        $from_email = $school_settings['smtp_from_email'] ?: ($school_settings['email'] ?? 'no-reply@zetaphase.com.ng');
        $from_name  = $school_settings['smtp_from_name']  ?: ($school_settings['school_name'] ?? 'School');

        if ($host === '') {
            return self::sendViaPhpMail($from_email, $from_name, $to_email, $subject, $body_html);
        }

        try {
            return self::sendViaSmtp($school_settings, $from_email, $from_name, $to_email, $to_name, $subject, $body_html);
        } catch (Exception $e) {
            return ['success' => false, 'response' => 'SMTP error: ' . $e->getMessage()];
        }
    }

    private static function sendViaPhpMail(string $from_email, string $from_name, string $to, string $subject, string $body_html): array
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$from_name} <{$from_email}>\r\n";
        $ok = @mail($to, $subject, $body_html, $headers);
        return ['success' => $ok, 'response' => $ok ? 'Sent via server mail() — no SMTP host configured in Settings.' : 'mail() failed — no SMTP host configured and local MTA unavailable.'];
    }

    private static function sendViaSmtp(array $s, string $from_email, string $from_name, string $to, string $to_name, string $subject, string $body_html): array
    {
        $host       = $s['smtp_host'];
        $port       = (int)($s['smtp_port'] ?: 587);
        $user       = $s['smtp_username'] ?? '';
        $pass       = $s['smtp_password'] ?? '';
        $encryption = $s['smtp_encryption'] ?? 'tls';

        $transport = ($encryption === 'ssl') ? "ssl://$host" : $host;
        $fp = @stream_socket_client("$transport:$port", $errno, $errstr, 15);
        if (!$fp) throw new Exception("Could not connect to $host:$port ($errstr)");

        $read = function () use ($fp) {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };
        $write = function (string $cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

        $read();
        $write("EHLO zetaphase.com.ng"); $resp = $read();

        if ($encryption === 'tls') {
            $write("STARTTLS"); $read();
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('STARTTLS negotiation failed');
            }
            $write("EHLO zetaphase.com.ng"); $read();
        }

        if ($user !== '') {
            $write("AUTH LOGIN"); $read();
            $write(base64_encode($user)); $read();
            $write(base64_encode($pass)); $auth_resp = $read();
            if (!str_starts_with(trim($auth_resp), '235')) {
                fclose($fp);
                throw new Exception('SMTP auth failed: ' . trim($auth_resp));
            }
        }

        $write("MAIL FROM:<$from_email>"); $read();
        $write("RCPT TO:<$to>"); $rcpt_resp = $read();
        if (!preg_match('/^25/', trim($rcpt_resp))) {
            fclose($fp);
            throw new Exception('Recipient rejected: ' . trim($rcpt_resp));
        }
        $write("DATA"); $read();

        $headers  = "From: {$from_name} <{$from_email}>\r\n";
        $headers .= "To: {$to_name} <{$to}>\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message = $headers . "\r\n" . str_replace("\n.", "\n..", $body_html) . "\r\n.";
        $write($message);
        $final = $read();
        $write("QUIT");
        fclose($fp);

        $ok = preg_match('/^250/', trim($final));
        return ['success' => (bool)$ok, 'response' => trim($final) ?: 'Sent'];
    }
}
