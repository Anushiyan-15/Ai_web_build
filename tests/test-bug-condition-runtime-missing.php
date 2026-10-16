<?php
// tests/test-bug-condition-runtime-missing.php
// Bug Condition Exploration Test: Bus Booking Sites Missing Runtime Integration
// Run: php tests/test-bug-condition-runtime-missing.php
//
// **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
// **DO NOT attempt to fix the test or the code when it fails**
// **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
// **GOAL**: Surface counterexamples that demonstrate the bug exists
//
// **Validates: Requirements 1.7, 1.8, 1.9, 2.7, 2.8, 2.9**

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', sys_get_temp_dir() . '/webbbuilder-bug-condition-' . getmypid());

require_once __DIR__ . '/../includes/AutoSiteGenerator.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
require_once __DIR__ . '/../includes/BusinessOps.php';

$fail = 0;
function ok($c, $m) { 
    global $fail; 
    echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; 
    if (!$c) {
        $fail++;
        echo "  ^ This is EXPECTED to fail on unfixed code (bug exists)\n";
    }
}

function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { 
        if ($f === '.' || $f === '..') continue; 
        $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p); 
    }
    @rmdir($d);
}

echo "=== Bug Condition Exploration Test ===\n";
echo "Testing: Bus booking sites should have runtime integration\n";
echo "Expected: This test FAILS on unfixed code (proving bug exists)\n\n";

// Clean up before test (don't pre-clean to avoid affecting slug generation)
$testSlug = 'bug-test-city-express';

// Test 1: Generate a bus booking site using AutoSiteGenerator::generate()
echo "--- Test 1: Generate Bus Booking Site ---\n";
$input = [
    'company_name' => 'City Express',
    'project_type' => 'bus booking',
    'primary_purpose' => 'online bus ticket booking',
    'target_audience' => 'travelers',
    'unique_value' => 'easy seat selection and booking',
];

try {
    $result = AutoSiteGenerator::generate($input);
    ok(isset($result['success']) && $result['success'] === true, 'Site generation completed successfully');
    ok(isset($result['slug']), 'Site slug returned');
    $generatedSlug = $result['slug'] ?? $testSlug;
    echo "Generated site slug: $generatedSlug\n";
} catch (Exception $e) {
    echo "FAIL: Site generation threw exception: " . $e->getMessage() . "\n";
    $fail++;
    exit(1);
}

// Test 2: Read generated index.html
echo "\n--- Test 2: Read Generated HTML ---\n";
$publishedRoot = defined('STORAGE_DIR') ? (dirname(STORAGE_DIR) . '/published') : (dirname(__DIR__) . '/published');
$indexPath = $publishedRoot . '/' . $generatedSlug . '/index.html';
if (!file_exists($indexPath)) {
    echo "FAIL: Generated index.html not found at: $indexPath\n";
    echo "Checking alternative paths...\n";
    // List published directory to see what was created
    $pubDir = $publishedRoot;
    if (is_dir($pubDir)) {
        $dirs = array_filter(scandir($pubDir), function($d) use ($pubDir) {
            return $d !== '.' && $d !== '..' && is_dir($pubDir . '/' . $d);
        });
        echo "Available directories in published/: " . implode(', ', $dirs) . "\n";
    }
    $fail++;
    exit(1);
}

$html = file_get_contents($indexPath);
ok(strlen($html) > 0, 'Generated HTML is not empty');
echo "HTML size: " . strlen($html) . " bytes\n";

// Test 3: Check for runtime integration script (data-wc-biz-runtime)
echo "\n--- Test 3: Check for Runtime Integration Script ---\n";
$hasRuntimeScript = strpos($html, 'data-wc-biz-runtime') !== false;
ok($hasRuntimeScript, 'Bug Condition Property 1: Generated HTML contains data-wc-biz-runtime attribute (runtime injected)');

if (!$hasRuntimeScript) {
    echo "COUNTEREXAMPLE FOUND: Missing data-wc-biz-runtime script tag\n";
    echo "This proves the bug exists - runtime integration was not injected\n";
}

// Test 4: Check for window.WCBiz definition
echo "\n--- Test 4: Check for window.WCBiz Definition ---\n";
$hasWCBiz = strpos($html, 'window.WCBiz') !== false;
ok($hasWCBiz, 'Bug Condition Property 2: Generated HTML contains window.WCBiz definition (API object defined)');

if (!$hasWCBiz) {
    echo "COUNTEREXAMPLE FOUND: window.WCBiz is not defined in generated HTML\n";
    echo "This proves the bug exists - API object missing\n";
}

// Test 5: Check for bus booking detection flag
echo "\n--- Test 5: Check for Bus Booking Detection Flag ---\n";
$hasBusFlag = strpos($html, 'IS_BUS_BOOKING=(true)') !== false || strpos($html, 'IS_BUS_BOOKING=true') !== false;
ok($hasBusFlag, 'Bug Condition Property 3: Generated HTML contains IS_BUS_BOOKING=(true) (bus detection worked)');

if (!$hasBusFlag) {
    echo "COUNTEREXAMPLE FOUND: IS_BUS_BOOKING flag not set to true\n";
    echo "This proves the bug exists - bus booking detection failed\n";
}

// Test 6: Check for backend API reference
echo "\n--- Test 6: Check for Backend API Reference ---\n";
$hasApiRef = strpos($html, 'api/business-ops.php') !== false;
ok($hasApiRef, 'Bug Condition Property 4: Generated HTML contains reference to api/business-ops.php (backend connected)');

if (!$hasApiRef) {
    echo "COUNTEREXAMPLE FOUND: No reference to api/business-ops.php found\n";
    echo "This proves the bug exists - backend API not connected\n";
}

// Test 7: Check for bus booking widget presence (should exist even on unfixed code)
echo "\n--- Test 7: Check for Bus Booking Widget Presence ---\n";
$hasWidget = strpos($html, 'data-wc-live-bus-booking') !== false || 
             strpos($html, 'bus-seat-grid') !== false ||
             strpos($html, 'id="busSeatMap"') !== false;
ok($hasWidget, 'Verification: Generated HTML contains bus booking widget HTML');

if ($hasWidget && !$hasRuntimeScript) {
    echo "CRITICAL FINDING: Widget HTML present BUT runtime integration missing\n";
    echo "This confirms the root cause: widget exists but is disconnected from backend\n";
}

// Clean up after test
echo "\n--- Cleanup ---\n";
$publishedRoot = defined('STORAGE_DIR') ? (dirname(STORAGE_DIR) . '/published') : (dirname(__DIR__) . '/published');
$publishedDir = $publishedRoot . '/' . $generatedSlug;
rmdir_r($publishedDir);
rmdir_r(bops_root() . '/collections/' . $generatedSlug);
rmdir_r(bops_root() . '/sites/' . $generatedSlug);
rmdir_r(STORAGE_DIR);

// Summary
echo "\n=== Test Summary ===\n";
if ($fail > 0) {
    echo "EXPECTED RESULT: $fail assertion(s) failed\n";
    echo "This is CORRECT - the test is SUPPOSED to fail on unfixed code\n";
    echo "The failures prove the bug exists (runtime integration missing)\n";
    echo "\nCounterexamples documented:\n";
    if (!$hasRuntimeScript) echo "- Missing data-wc-biz-runtime script tag\n";
    if (!$hasWCBiz) echo "- window.WCBiz undefined\n";
    if (!$hasBusFlag) echo "- IS_BUS_BOOKING flag not set\n";
    if (!$hasApiRef) echo "- Backend API reference missing\n";
    if ($hasWidget && !$hasRuntimeScript) {
        echo "- Widget present but disconnected (ROOT CAUSE CONFIRMED)\n";
    }
    echo "\nAfter implementing the fix, this test should PASS\n";
} else {
    echo "UNEXPECTED RESULT: All tests passed\n";
    echo "This means either:\n";
    echo "1. The bug has already been fixed\n";
    echo "2. The test logic is incorrect\n";
    echo "3. The root cause analysis was wrong\n";
}

exit($fail > 0 ? 1 : 0);
