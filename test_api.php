<?php
// Test 1: Gemini API key — single-model policy: gemini-3.5-flash-lite ONLY
// Key is read from env / config — never hardcode real keys in tracked files.
$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : 'YOUR_GEMINI_API_KEY');
$models = ['gemini-3.5-flash-lite'];

foreach ($models as $model) {
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['contents' => [['parts' => [['text' => 'Say OK']]]], 'generationConfig' => ['maxOutputTokens' => 20]]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    $j = json_decode($res, true);
    $text = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $apiErr = $j['error']['message'] ?? '';
    
    echo "Model: $model | HTTP: $code";
    if ($err) echo " | CURL: $err";
    if ($apiErr) echo " | API_ERR: $apiErr";
    if ($text) echo " | Response: $text";
    echo "\n";
}

// Test 2: Cart quantity - simulate product card qty
echo "\n--- Cart qty test ---\n";
echo "wc-card-inc / wc-card-dec delegated event: should work via document.addEventListener\n";
echo "Issue: The quantity +/- buttons in product cards are in the PHP-generated HTML.\n";
echo "       The JS delegation is in NOWDOC cart script which uses __PRODUCTS__ placeholder.\n";
echo "       Check if placeholder is replaced correctly.\n";
