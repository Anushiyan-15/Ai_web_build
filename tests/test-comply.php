<?php
// tests/test-comply.php — run: php tests/test-comply.php
// Covers: missing required sections injected, present sections untouched,
// forms wired with purpose inference, wired forms untouched, edge safety.
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Comply.php';
require_once __DIR__ . '/../includes/BizIntelligence/Completeness.php';
require_once __DIR__ . '/../includes/BizIntelligence/Verify.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

$bus = ['biz_name' => 'City Express', 'biz_type' => 'Bus booking',
    'biz_services' => 'Chennai-Bangalore trips', 'sections' => ['hero', 'contact']];
$spec = biz_repair_spec(biz_build_spec($bus, 'Bus booking with seats, QR tickets, counter pay.'));

// Fixture mimics the reported flagship: hero+contact only, bare booking form.
$bare = '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width"><title>City Express — Bus Booking</title></head>'
    . '<body><header id="hero"><nav><a href="#book">Book</a><a href="#contact">Contact</a></nav><h1>City Express</h1></header>'
    . '<main><section id="book"><form><input name="trip" required><input name="date" type="date" required><button>Search seats</button></form></section></main>'
    . '<footer id="contact"><p>Call us</p></footer></body></html>'
    . str_repeat('<!-- pad -->', 60);

$v0 = biz_verify_project($spec, ['htmls' => [$bare]]);
ok($v0['summary']['failed'] > 0, 'bare flagship fails verification (' . $v0['summary']['failed'] . ' failed: ' . implode(',', array_column($v0['failed'], 't')) . ')');

$r = biz_ensure_html_compliance($bare, $spec);
ok(!empty($r['fixes']), 'fixes recorded (' . implode(' | ', $r['fixes']) . ')');
ok(stripos($r['html'], 'id="offerings"') !== false, 'missing #offerings injected');
ok(stripos($r['html'], 'data-wc-form="booking"') !== false, 'bare form wired as booking (seat/date inference)');
ok(stripos($r['html'], 'City Express') !== false, 'business content preserved');

$v1 = biz_verify_project($spec, ['htmls' => [$r['html']]]);
$stillFailed = array_column($v1['failed'], 't');
ok(!in_array('section:offerings', $stillFailed, true), 'section:offerings passes after comply');
ok(!in_array('forms', $stillFailed, true), 'forms check passes after comply');
ok($v1['summary']['failed'] === 0, 'zero failures after comply (was ' . $v0['summary']['failed'] . ')');

// Idempotency: already-compliant HTML untouched (byte-identical, no fixes).
$r2 = biz_ensure_html_compliance($r['html'], $spec);
ok(empty($r2['fixes']) && $r2['html'] === $r['html'], 'compliant HTML untouched on re-run');

// Wired forms never rewritten.
$wired = str_replace('<form>', '<form data-wc-form="contact">', $bare);
$r3 = biz_ensure_html_compliance($wired, $spec);
ok(substr_count($r3['html'], 'data-wc-form') === substr_count($wired, 'data-wc-form'), 'pre-wired form untouched');

// Newsletter inference.
$nl = '<!DOCTYPE html><html><head><title>t</title></head><body>' . str_repeat('<p>x</p>', 40)
    . '<form><input type="email" name="email" required><button>Subscribe</button></form></body></html>'
    . str_repeat('<!-- pad -->', 60);
$r4 = biz_ensure_html_compliance($nl, $spec);
ok(stripos($r4['html'], 'data-wc-form="newsletter"') !== false, 'newsletter purpose inferred');

// Edge safety: tiny/empty HTML returned as-is, never corrupted.
$re = biz_ensure_html_compliance('<html><body>hi</body></html>', $spec);
ok($re['html'] === '<html><body>hi</body></html>' && empty($re['fixes']), 'short HTML safe');
$re2 = biz_ensure_html_compliance('', $spec);
ok($re2['html'] === '' && empty($re2['fixes']), 'empty HTML safe');

echo $fail === 0 ? "\nALL COMPLY TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
