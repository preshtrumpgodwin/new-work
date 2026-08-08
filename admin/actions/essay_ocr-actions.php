<?php
/**
 * Actions: Essay OCR / AI Evaluation + its AI Configuration
 * (admin/sections/essay_ocr.php)
 * Split out of the old phase3-actions.php grouping — kept these two
 * together since the original file grouped them under one "AI
 * Configuration + Essay Evaluation" heading and the config only exists to
 * drive the evaluator.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_save_ai_config']) && $active_role === 'School Admin') {
    $provider = safe_str($_POST['ai_provider'] ?? 'openai');
    $api_key  = safe_str($_POST['ai_api_key']  ?? '');
    $model    = safe_str($_POST['ai_model']    ?? '');
    $ep       = safe_str($_POST['ai_essay_prompt']  ?? '');
    $lp       = safe_str($_POST['ai_lesson_prompt'] ?? '');
    $exists = $pdo->prepare("SELECT id FROM school_settings WHERE school_uuid=?");
    $exists->execute([$school_uuid]);
    if ($exists->fetchColumn()) {
        $pdo->prepare("UPDATE school_settings SET ai_provider=?, ai_api_key=?, ai_model=?, ai_essay_prompt=?, ai_lesson_prompt=? WHERE school_uuid=?")
            ->execute([$provider,$api_key,$model,$ep,$lp,$school_uuid]);
    } else {
        $pdo->prepare("INSERT INTO school_settings (school_uuid,school_name,ai_provider,ai_api_key,ai_model,ai_essay_prompt,ai_lesson_prompt) VALUES (?,?,?,?,?,?,?)")
            ->execute([$school_uuid,$school['name']??'School',$provider,$api_key,$model,$ep,$lp]);
    }
    AuditLog::write($pdo,$school_uuid,$user_uuid,'settings.ai_config',$school_uuid,"Updated AI config ($provider)");
    $success_msg = 'AI configuration saved!';
}
if (isset($_POST['action_evaluate_essay'])) {
    $s_uuid  = safe_str($_POST['student_uuid']    ?? '');
    $s_name  = safe_str($_POST['student_name']    ?? '');
    $title   = safe_str($_POST['assignment_title'] ?? '');
    $essay   = safe_str($_POST['essay_text']       ?? '');
    $guide   = safe_str($_POST['marking_guide']    ?? '');
    $max     = safe_int($_POST['max_score']        ?? 100);

    if (!$title || !$essay) {
        $error_msg = 'Assignment title and essay text are required.';
    } else {
        $api_key = trim($school_settings['ai_api_key'] ?? '');
        $provider = $school_settings['ai_provider'] ?? 'openai';
        $result = null;
        if ($api_key === '') {
            $error_msg = 'No AI provider configured — set an API key under AI Configuration first.';
        } else {
            $result = evaluate_essay_with_ai($provider, $api_key, $school_settings['ai_model'] ?? '', $essay, $guide, $max, $school_settings['ai_essay_prompt'] ?? '');
        }
        if ($result) {
            $uuid = uid('ess');
            $pdo->prepare("INSERT INTO essay_evaluations (evaluation_uuid,school_uuid,student_uuid,student_name,assignment_title,essay_text,marking_guide,score,max_score,grammar_rating,coherence_rating,feedback_comments)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$uuid,$school_uuid,$s_uuid,$s_name,$title,$essay,$guide,$result['score'],$max,$result['grammar'],$result['coherence'],$result['feedback']]);
            AuditLog::write($pdo,$school_uuid,$user_uuid,'essay.evaluate',$uuid,"Evaluated '$title' for $s_name — {$result['score']}/$max");
            $success_msg = "Essay evaluated: {$result['score']}/$max — {$result['feedback']}";
        }
    }
}

/**
 * Minimal AI call for essay marking. Supports OpenAI-compatible chat
 * completion APIs (OpenAI, and any provider exposing the same schema).
 * Returns a parsed [score, grammar, coherence, feedback] array or null on failure.
 */
function evaluate_essay_with_ai(string $provider, string $api_key, string $model, string $essay, string $guide, int $max_score, string $custom_prompt): ?array {
    global $error_msg;
    $model = $model ?: 'gpt-4o-mini';
    $prompt = $custom_prompt ?: "You are a strict but fair examiner. Mark the following essay out of {$max_score} against the marking guide provided. Respond ONLY with JSON: {\"score\": number, \"grammar\": string, \"coherence\": string, \"feedback\": string}.";
    $endpoint = match ($provider) {
        'openai' => 'https://api.openai.com/v1/chat/completions',
        default  => 'https://api.openai.com/v1/chat/completions',
    };
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => "MARKING GUIDE:\n$guide\n\nESSAY:\n$essay"],
        ],
        'temperature' => 0.3,
    ]);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $api_key"],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || !$resp) { $error_msg = 'AI request failed: ' . ($err ?: 'no response'); return null; }
    $data = json_decode($resp, true);
    $content = $data['choices'][0]['message']['content'] ?? null;
    if (!$content) { $error_msg = 'AI response error: ' . substr($resp, 0, 200); return null; }
    $parsed = json_decode(trim(str_replace(['```json','```'], '', $content)), true);
    if (!is_array($parsed)) { $error_msg = 'Could not parse AI response.'; return null; }
    return [
        'score'     => min($max_score, max(0, (float)($parsed['score'] ?? 0))),
        'grammar'   => safe_str($parsed['grammar']   ?? 'Good'),
        'coherence' => safe_str($parsed['coherence'] ?? 'Good'),
        'feedback'  => safe_str($parsed['feedback']  ?? 'No feedback returned.'),
    ];
}
