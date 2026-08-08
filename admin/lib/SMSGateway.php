<?php
/**
 * SMSGateway — sends a text message through whichever provider a school has
 * configured in school_settings (sms_provider / sms_api_key / sms_sender_id).
 * Falls back to a "simulated" send (still logged) if no key is configured,
 * so schools that haven't set up a gateway yet still see something happen
 * instead of a silent no-op.
 */
class SMSGateway
{
    /**
     * @return array{success: bool, provider: string, response: string}
     */
    public static function send(array $school_settings, string $to_phone, string $message): array
    {
        $provider = $school_settings['sms_provider'] ?? 'termii';
        $api_key  = trim($school_settings['sms_api_key'] ?? '');
        $sender   = $school_settings['sms_sender_id'] ?? 'School';

        if ($api_key === '') {
            return ['success' => false, 'provider' => $provider, 'response' => 'No SMS API key configured in Settings — message logged only, not actually sent.'];
        }

        try {
            switch ($provider) {
                case 'termii':
                    return self::sendTermii($api_key, $sender, $to_phone, $message);
                case 'bulksmsnigeria':
                    $username = $school_settings['sms_at_username'] ?? '';
                    return self::sendBulkSmsNigeria($api_key, $username, $sender, $to_phone, $message);
                default:
                    return ['success' => false, 'provider' => $provider, 'response' => "Unknown provider '$provider'."];
            }
        } catch (Exception $e) {
            return ['success' => false, 'provider' => $provider, 'response' => 'Gateway error: ' . $e->getMessage()];
        }
    }

    private static function sendTermii(string $api_key, string $sender, string $to, string $message): array
    {
        $payload = json_encode([
            'to' => $to, 'from' => $sender, 'sms' => $message,
            'type' => 'plain', 'channel' => 'generic', 'api_key' => $api_key,
        ]);
        $ch = curl_init('https://api.ng.termii.com/api/sms/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) return ['success' => false, 'provider' => 'termii', 'response' => "cURL error: $err"];
        $ok = $code >= 200 && $code < 300;
        return ['success' => $ok, 'provider' => 'termii', 'response' => $resp ?: "HTTP $code"];
    }

    private static function sendBulkSmsNigeria(string $api_key, string $username, string $sender, string $to, string $message): array
    {
        $params = http_build_query([
            'api_token' => $api_key, 'from' => $sender, 'to' => $to,
            'body' => $message, 'dnd' => 2, 'gateway' => 'direct-refund',
        ]);
        $ch = curl_init('https://www.bulksmsnigeria.com/api/v1/sms/create?' . $params);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) return ['success' => false, 'provider' => 'bulksmsnigeria', 'response' => "cURL error: $err"];
        $ok = $code >= 200 && $code < 300;
        return ['success' => $ok, 'provider' => 'bulksmsnigeria', 'response' => $resp ?: "HTTP $code"];
    }
}
