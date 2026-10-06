<?php
require __DIR__ . '/includes/TasteSkill.php';

$pass = 0; $fail = 0;
function check($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS: $name\n"; }
    else { $fail++; echo "FAIL: $name\n"; }
}

// 1. normalize
$n = taste_normalize(['vibe' => 'PLAYFUL', 'variance' => 99, 'motion' => -3, 'density' => 5]);
check('normalize vibe lowercase', $n['vibe'] === 'playful');
check('normalize clamp high', $n['variance'] === 10);
check('normalize clamp low', $n['motion'] === 0);
check('normalize passthrough', $n['density'] === 5);
check('normalize bad vibe->auto', taste_normalize(['vibe' => 'zzz'])['vibe'] === 'auto');

// 2. brief inference
$data = ['biz_type' => 'Restaurant, Cafe & Bar', 'biz_audience' => 'Food lovers',
         'design_style' => 'bold', 'design_direction' => 'playful experimental'];
$b = taste_infer_brief($data, []);
check('brief vibe playful', $b['vibe'] === 'playful');
check('brief dials playful 9/8/3', $b['variance'] === 9 && $b['motion'] === 8 && $b['density'] === 3);
check('design read mentions audience', strpos($b['design_read'], 'Food lovers') !== false);

$b2 = taste_infer_brief(['biz_type' => 'Legal', 'biz_audience' => 'Clients', 'design_style' => 'modern', 'design_direction' => ''], []);
check('brief trust vibe for legal', $b2['vibe'] === 'trust');
check('brief trust dials 3/2/5', $b2['variance'] === 3 && $b2['motion'] === 2 && $b2['density'] === 5);

$b3 = taste_infer_brief($data, ['vibe' => 'minimalist', 'variance' => 4, 'motion' => 0, 'density' => 0]);
check('explicit vibe wins', $b3['vibe'] === 'minimalist');
check('explicit variance wins, rest auto', $b3['variance'] === 4 && $b3['motion'] === 4 && $b3['density'] === 3);

// 3. prompt block
$pb = taste_prompt_block($data + ['taste' => []], 'bold', 1);
check('prompt has design read', strpos($pb, 'Design Read:') !== false);
check('prompt has dials', strpos($pb, 'DESIGN_VARIANCE=9') !== false);
check('prompt has hard bans', strpos($pb, 'Hard bans:') !== false);
check('prompt has slot note', strpos($pb, 'layout slot 1') !== false);

// 4. system addenda
check('gen addendum non-empty', strlen(taste_system_addendum()) > 100);
check('edit addendum audit-first', strpos(taste_edit_addendum(), 'audit') !== false || strpos(taste_edit_addendum(), 'SCAN') !== false);
check('snippet addendum', strpos(taste_snippet_addendum(), 'accent') !== false);

// 5. audit — clean page
$clean = '<!DOCTYPE html><html><head><style>@media(max-width:900px){.x{color:red}}</style></head>'
  . '<body><nav><a href="#services">Services</a><a href="#contact">Get started</a></nav>'
  . '<section><h1>Fresh food delivered fast</h1><p>Good.</p></section>'
  . '<section><h2>Menu</h2><img src="https://picsum.photos/seed/a/800/600" alt="Fresh dishes"></section>'
  . '<style>@media (prefers-reduced-motion: reduce){*{animation:none}}</style>'
  . '</body></html>';
$a = taste_audit_html($clean);
check('clean page passes', $a['passed'] === true && $a['score'] >= 70);

// 6. audit — slop page
$slop = '<!DOCTYPE html><html><head></head><body>'
  . '<h1>This is an extremely long hero headline that goes on and on and on forever</h1>'
  . '<p>Lorem ipsum dolor sit amet. Contact John Doe at Acme Corp to elevate your seamless journey.</p>'
  . '<a href="#">Contact us</a><a href="#x">Get in touch</a>'
  . '<img src="a.jpg"><p>Think — pause — reflect — flourish — extra.</p>'
  . '</body></html>';
$a2 = taste_audit_html($slop);
check('slop page fails', $a2['passed'] === false);
check('slop catches lorem', (bool)array_filter($a2['issues'], fn($i) => stripos($i, 'lorem') !== false));
check('slop catches dup CTA', (bool)array_filter($a2['issues'], fn($i) => stripos($i, 'duplicate contact') !== false));
check('slop catches dead link', (bool)array_filter($a2['issues'], fn($i) => stripos($i, 'dead links') !== false));
check('slop catches alt', (bool)array_filter($a2['issues'], fn($i) => stripos($i, 'alt text') !== false));
check('slop catches emdash', (bool)array_filter($a2['issues'], fn($i) => stripos($i, 'em-dash') !== false));

// 7. safety net: patches missing pieces, idempotent, never touches design
$bare = '<!DOCTYPE html><html><head><title>Taste Safety Net Probe Page</title></head><body><h1>Hi there friend</h1><p>' . str_repeat('Padding copy to pass the minimum document length guard. ', 6) . '</p></body></html>';
$patched = taste_apply_safety_net($bare);
check('safety net adds smooth scroll', strpos($patched, 'scroll-behavior:smooth') !== false);
check('safety net adds reduced-motion', strpos($patched, 'prefers-reduced-motion') !== false);
check('safety net idempotent', taste_apply_safety_net($patched) === $patched);
check('safety net keeps content', strpos($patched, '<h1>Hi there friend</h1>') !== false);
$rich = '<!DOCTYPE html><html><head><style>html{scroll-behavior:smooth}@media (prefers-reduced-motion: reduce){*{animation:none}}</style></head><body></body></html>';
check('safety net no-op when present', taste_apply_safety_net($rich) === $rich);

// 8. photo rule + repair instruction
$noimg = '<!DOCTYPE html><html><head><style>@media(max-width:900px){.x{color:red}}</style></head><body><h1>Short title here</h1><p>Text.</p><p>' . str_repeat('Padding copy to pass the minimum document length guard. ', 6) . '</p></body></html>';
$ai = taste_audit_html($noimg);
check('audit flags zero photography', (bool)array_filter($ai['issues'], fn($i) => stripos($i, 'photography') !== false));
$ri = taste_repair_instruction(['dead links (href="#")', 'no alt text']);
check('repair instruction lists issues', strpos($ri, 'dead links') !== false && strpos($ri, 'fix ONLY') !== false);

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
