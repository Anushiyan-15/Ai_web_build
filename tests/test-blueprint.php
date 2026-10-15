<?php
// tests/test-blueprint.php — run: php tests/test-blueprint.php
// Covers: interview, classification, completeness honesty, blueprint
// validation, capability details, verification, backward compatibility.
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Interview.php';
require_once __DIR__ . '/../includes/BizIntelligence/Completeness.php';
require_once __DIR__ . '/../includes/BizIntelligence/Capabilities.php';
require_once __DIR__ . '/../includes/BizIntelligence/Verify.php';
require_once __DIR__ . '/../includes/BizIntelligence/Complexity.php';
require_once __DIR__ . '/../includes/BizIntelligence/Router.php';
require_once __DIR__ . '/../includes/BusinessSpec.php';
$fail = 0;
function ok($c,$m){global $fail;echo ($c?"PASS":"FAIL").": $m\n";if(!$c)$fail++;}

$bus = ['biz_name' => 'City Express', 'biz_type' => 'Bus booking',
    'biz_tagline' => 'Daily buses', 'biz_services' => 'Chennai-Bangalore trips',
    'sections' => ['hero','contact']];
$spec = biz_repair_spec(biz_build_spec($bus, 'Bus booking with seats, QR tickets, counter pay, cancellations.'));

// 1. Interview: context-specific, capped, critical-first, no repeats
$qs = biz_interview_next($bus, $spec, [], [], 4);
ok(count($qs) > 0 && count($qs) <= 4, 'interview returns 1-4 questions');
$crit = array_filter($qs, fn($q) => !empty($q['critical']));
ok(!empty($crit), 'critical questions surface first');
$topics = array_column(array_map(fn($q) => ['t' => $q['key']], $qs), 't');
$qs2 = biz_interview_next($bus, $spec, [$qs[0]['key'] => 'x'], [], 4);
foreach ($qs2 as $q) ok($q['key'] !== $qs[0]['key'], 'answered topic never repeats (' . $q['key'] . ')');
// skip marks topic done
$qs3 = biz_interview_next($bus, $spec, [], [$qs[0]['key'] => true], 4);
foreach ($qs3 as $q) ok($q['key'] !== $qs[0]['key'], 'skipped topic never repeats');
// unknown business still gets generic (non-empty-or-done honest)
$weird = biz_repair_spec(biz_build_spec(['biz_name' => 'X'], 'Goat yoga at sunset.'));
$qw = biz_interview_next(['biz_name' => 'X'], $weird, [], [], 4);
ok(is_array($qw), 'unfamiliar business handled without crash');

// 2. Classification: 5 classes, verdicts, recommendation never confirmed
$classes = array_unique(array_column($spec['requirements'], 'class'));
ok(!empty(array_intersect($classes, ['confirmed','recommended','inferred','needs_confirmation'])), 'requirements classified (' . implode(',', $classes) . ')');
$rid = $spec['requirements'][0]['id'] ?? '';
ok($rid !== '' && biz_requirement_verdict($spec, $rid, 'accepted'), 'accept verdict applies');
ok(biz_requirement_verdict($spec, $rid, 'bogus') === false, 'bad verdict rejected');
ok(biz_requirement_verdict($spec, 'NOPE-AC-999', 'accepted') === false, 'unknown id rejected');
[$vok] = biz_validate_spec($spec);
ok($vok, 'spec still validates after verdicts');
// recommendation cannot pose as confirmed
$spec2 = biz_blank_spec(['business_name' => 'T', 'description' => 'd']);
biz_add_requirement($spec2, 'T', 'Try AI chatbot', 'functional', 'should', 'recommended', 0.5, 'nice to have', [], 'chat replies');
[$vok2, $e2] = biz_validate_spec($spec2);
ok($vok2, 'recommended (non-confirmed) validates honestly');

// 3. Completeness honesty: partial info must NOT score 100
$thin = biz_repair_spec(biz_build_spec(['biz_name' => ''], ''));
$c0 = biz_completeness($thin, []);
ok($c0['score'] < 100, 'thin spec scores ' . $c0['score'] . ' (not 100)');
ok(!empty($c0['blocking']), 'thin spec has blocking gaps');
ok($c0['can_proceed'] === false, 'thin spec blocks generation');
$full = biz_completeness($spec, ['payments_methods' => 'both', 'payments_refunds' => 'partial', 'accounts_needed' => 'yes']);
ok($full['score'] >= $c0['score'], 'answers improve score (' . $full['score'] . ' vs ' . $c0['score'] . ')');

// 4. Interview apply: unknown keys ignored, gates recorded, destructive-safe
$before = count($spec['capabilities']);
[$vb] = biz_validate_spec($spec);
$r = biz_interview_apply($spec, ['payments_methods' => 'none', 'hacker_key' => 'x', 'empty_key' => '']);
ok(!isset($r['applied']['hacker_key']), 'unknown answer keys ignored');
ok(!in_array('payments', $spec['capabilities'] ?? [], true), 'payments removed per explicit answer');
ok(count($spec['entities']) > 0, 'entities preserved (non-destructive)');
[$va] = biz_validate_spec($spec);
ok($va, 'spec validates after answers');

// 5. Capability details registry
$d = biz_capability_detail('booking');
ok(is_array($d) && isset($d['prerequisites'], $d['workflow'], $d['admin'], $d['data'], $d['integrations']), 'booking detail complete');
ok(biz_capability_detail('nope') === null, 'unknown capability returns null');
ok(in_array('booking', biz_capability_ids()) && in_array('hospitality', biz_capability_ids()), 'capability ids extensible');

// 6. Verification on fixture HTML
$html = '<!DOCTYPE html><html lang="en"><head><title>City Express — Bus Booking</title><meta name="viewport" content="width=device-width"><style>' . str_repeat('.c{color:#111;margin:0;padding:0;}', 30) . '</style></head>'
    . '<body><header id="hero"><nav><a href="#book">Book</a><a href="#contact">Contact</a></nav><h1>City Express Bus Booking</h1><p>' . str_repeat('Daily comfortable buses. ', 10) . '</p></header>'
    . '<main><section id="book"><form data-wc-form="booking"><input name="name" required><input name="email" type="email" required></form></section>'
    . '<section id="contact"></section></main><footer></footer></body></html>';
$rep = biz_verify_project($spec, ['htmls' => [$html]]);
ok($rep['summary']['passed'] > 0, 'verification passes real checks (' . $rep['summary']['passed'] . ')');
ok(isset($rep['summary']['failed'], $rep['summary']['untested'], $rep['summary']['missing_config']), 'report has all four buckets');
$bad = biz_verify_project($spec, ['htmls' => ['<html><body>hi</body></html>']]);
ok($bad['summary']['failed'] > 0, 'broken build fails checks honestly');

// 7. Backward compatibility: legacy interfaces intact
ok(function_exists('biz_build_spec') && function_exists('biz_decide_route') && function_exists('business_spec_analyze'), 'legacy entry points intact');
ok(strpos(file_get_contents(__DIR__ . '/../builder.php'), 'collectWizardSnapshot') !== false, 'collectWizardSnapshot kept');
ok(strpos(file_get_contents(__DIR__ . '/../builder.php'), 'generateRouted') !== false, 'GenerationRouter path kept');
ok(strpos(file_get_contents(__DIR__ . '/../assets/js/generation-router.js'), 'options.spec') !== false, 'router accepts approved spec');
// blueprint session keys additive (no credential storage)
$b = file_get_contents(__DIR__ . '/../builder.php');
ok(strpos($b, 'window.__blueprint') !== false, 'blueprint session state wired');
ok(strpos($b, 'verifyReport') !== false, 'verify report persisted');

echo $fail === 0 ? "\nALL BLUEPRINT TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
