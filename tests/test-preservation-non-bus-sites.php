<?php
// tests/test-preservation-non-bus-sites.php
// Preservation Property Test: Non-Bus Site Generation Unchanged
// Run: php tests/test-preservation-non-bus-sites.php
//
// **IMPORTANT**: This test follows observation-first methodology
// **GOAL**: Observe behavior on UNFIXED code for non-buggy inputs (non-bus sites)
// **EXPECTED**: Tests PASS on unfixed code (confirms baseline behavior to preserve)
// **NOTE**: These same tests must still PASS after implementing the fix (no regressions)
//
// **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12**

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', sys_get_temp_dir() . '/webbbuilder-preservation-' . getmypid());

require_once __DIR__ . '/../includes/AutoSiteGenerator.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
require_once __DIR__ . '/../includes/BusinessOps.php';

$fail = 0;
$pass = 0;
function ok($c, $m) { 
    global $fail, $pass; 
    echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; 
    if (!$c) {
        $fail++;
    } else {
        $pass++;
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

echo "=== Preservation Property Test ===\n";
echo "Testing: Non-bus sites should NOT have bus-specific features\n";
echo "Expected: This test PASSES on unfixed code (baseline behavior to preserve)\n\n";

// Define test cases: non-bus business types
$testCases = [
    [
        'name' => 'Restaurant Site',
        'input' => [
            'company_name' => 'Tasty Bites',
            'project_type' => 'restaurant',
            'primary_purpose' => 'online food ordering',
            'target_audience' => 'food lovers',
            'unique_value' => 'fresh ingredients and quick delivery',
        ],
    ],
    [
        'name' => 'Hotel Site',
        'input' => [
            'company_name' => 'Grand Hotel',
            'project_type' => 'hotel',
            'primary_purpose' => 'room reservations',
            'target_audience' => 'travelers',
            'unique_value' => 'luxury accommodations and spa',
        ],
    ],
    [
        'name' => 'Salon Site',
        'input' => [
            'company_name' => 'Style Salon',
            'project_type' => 'beauty salon',
            'primary_purpose' => 'appointment booking',
            'target_audience' => 'beauty enthusiasts',
            'unique_value' => 'expert stylists and quality service',
        ],
    ],
];

$generatedSlugs = [];

foreach ($testCases as $idx => $testCase) {
    $testNum = $idx + 1;
    echo "\n========================================\n";
    echo "Test Case {$testNum}: {$testCase['name']}\n";
    echo "========================================\n";
    
    // Step 1: Generate the site
    echo "\n--- Step 1: Generate Site ---\n";
    try {
        $result = AutoSiteGenerator::generate($testCase['input']);
        ok(isset($result['success']) && $result['success'] === true, "{$testCase['name']}: Site generation completed successfully");
        ok(isset($result['slug']), "{$testCase['name']}: Site slug returned");
        $generatedSlug = $result['slug'] ?? "test-{$testNum}";
        $generatedSlugs[] = $generatedSlug;
        echo "Generated site slug: $generatedSlug\n";
    } catch (Exception $e) {
        echo "FAIL: {$testCase['name']}: Site generation threw exception: " . $e->getMessage() . "\n";
        $fail++;
        continue; // Skip to next test case
    }
    
    // Step 2: Read generated index.html
    echo "\n--- Step 2: Read Generated HTML ---\n";
    $publishedRoot = defined('STORAGE_DIR') ? (dirname(STORAGE_DIR) . '/published') : (dirname(__DIR__) . '/published');
    $indexPath = $publishedRoot . '/' . $generatedSlug . '/index.html';
    if (!file_exists($indexPath)) {
        echo "FAIL: {$testCase['name']}: Generated index.html not found at: $indexPath\n";
        $fail++;
        continue;
    }
    
    $html = file_get_contents($indexPath);
    ok(strlen($html) > 0, "{$testCase['name']}: Generated HTML is not empty");
    echo "HTML size: " . strlen($html) . " bytes\n";
    
    // Step 3: Preservation Property 1 - No bus booking widget attribute
    echo "\n--- Step 3: Check for ABSENCE of Bus Booking Widget ---\n";
    $hasLiveBusBooking = strpos($html, 'data-wc-live-bus-booking') !== false;
    ok(!$hasLiveBusBooking, "{$testCase['name']}: Preservation Property 1: Generated HTML does NOT contain data-wc-live-bus-booking attribute");
    
    if ($hasLiveBusBooking) {
        echo "REGRESSION: data-wc-live-bus-booking found in non-bus site\n";
    }
    
    // Step 4: Preservation Property 2 - No IS_BUS_BOOKING flag set to true
    echo "\n--- Step 4: Check for ABSENCE of Bus Booking Flag ---\n";
    $hasBusFlag = strpos($html, 'IS_BUS_BOOKING=(true)') !== false || strpos($html, 'IS_BUS_BOOKING=true') !== false;
    ok(!$hasBusFlag, "{$testCase['name']}: Preservation Property 2: Generated HTML does NOT contain IS_BUS_BOOKING=(true) in any scripts");
    
    if ($hasBusFlag) {
        echo "REGRESSION: IS_BUS_BOOKING flag found in non-bus site\n";
    }
    
    // Step 5: Preservation Property 3 - No bus-specific HTML elements (functional, not just CSS)
    echo "\n--- Step 5: Check for ABSENCE of Bus-Specific Functional HTML ---\n";
    // Check for actual bus booking widget elements (not just CSS class names)
    $hasBusSeatMap = strpos($html, 'id="busSeatMap"') !== false || strpos($html, 'id="busSeatGrid"') !== false;
    $hasBusTripSearch = strpos($html, 'id="busTripSearch"') !== false || strpos($html, 'id="busSearchForm"') !== false;
    $hasBusBookingSteps = strpos($html, 'data-wc-step="bus-') !== false;
    $hasBusHoldButton = strpos($html, 'id="busHoldButton"') !== false;
    $hasBusSpecificHtml = $hasBusSeatMap || $hasBusTripSearch || $hasBusBookingSteps || $hasBusHoldButton;
    ok(!$hasBusSpecificHtml, "{$testCase['name']}: Preservation Property 3: Generated HTML does NOT contain bus-specific functional elements");
    
    if ($hasBusSpecificHtml) {
        echo "REGRESSION: Bus-specific functional HTML found in non-bus site\n";
        if ($hasBusSeatMap) echo "  - Found: Bus seat map element\n";
        if ($hasBusTripSearch) echo "  - Found: Bus trip search form\n";
        if ($hasBusBookingSteps) echo "  - Found: Bus booking workflow steps\n";
        if ($hasBusHoldButton) echo "  - Found: Bus seat hold button\n";
    }
    
    // Note: CSS class names like .bus-seat-grid may appear in stylesheets for all sites
    // but that's acceptable - we're checking for actual functional bus booking elements
    
    // Step 6: Record presence/absence of generic form runtime features
    echo "\n--- Step 6: Check for Generic Form Runtime Features ---\n";
    $hasGenericRuntime = strpos($html, 'data-wc-biz-runtime') !== false;
    $hasWCBiz = strpos($html, 'window.WCBiz') !== false;
    $hasGenericForms = strpos($html, 'data-wc-form') !== false;
    
    echo "Generic runtime features observed:\n";
    echo "  - data-wc-biz-runtime: " . ($hasGenericRuntime ? 'PRESENT' : 'ABSENT') . "\n";
    echo "  - window.WCBiz: " . ($hasWCBiz ? 'PRESENT' : 'ABSENT') . "\n";
    echo "  - data-wc-form: " . ($hasGenericForms ? 'PRESENT' : 'ABSENT') . "\n";
    echo "Note: These features are acceptable for non-bus sites if they provide generic functionality\n";
    
    // Step 7: Verify admin panel was generated
    echo "\n--- Step 7: Check Admin Panel Generation ---\n";
    $adminPath = $publishedRoot . '/' . $generatedSlug . '/admin/index.php';
    $hasAdminPanel = file_exists($adminPath);
    ok($hasAdminPanel, "{$testCase['name']}: Preservation Property 4: Admin panel generated correctly");
    
    if (!$hasAdminPanel) {
        echo "REGRESSION: Admin panel missing for non-bus site\n";
    }
    
    // Step 8: Verify entity seeding (check for entities in manifest or admin files)
    echo "\n--- Step 8: Check Entity Seeding ---\n";
    $manifestPath = $publishedRoot . '/' . $generatedSlug . '/admin/manifest.json';
    $hasManifest = file_exists($manifestPath);
    ok($hasManifest, "{$testCase['name']}: Preservation Property 5: Manifest file exists (entity seeding worked)");
    
    if ($hasManifest) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $hasEntities = isset($manifest['entities']) && is_array($manifest['entities']);
        ok($hasEntities, "{$testCase['name']}: Preservation Property 6: Manifest contains entities array");
        if ($hasEntities) {
            echo "Entities found: " . count($manifest['entities']) . "\n";
        }
    }
    
    echo "\n{$testCase['name']}: Completed\n";
}

// Clean up after all tests
echo "\n========================================\n";
echo "Cleanup\n";
echo "========================================\n";

$publishedRoot = defined('STORAGE_DIR') ? (dirname(STORAGE_DIR) . '/published') : (dirname(__DIR__) . '/published');
foreach ($generatedSlugs as $slug) {
    $publishedDir = $publishedRoot . '/' . $slug;
    if (is_dir($publishedDir)) {
        rmdir_r($publishedDir);
        echo "Removed: $publishedDir\n";
    }
    
    // Clean up business ops data
    $bopsCollectionDir = bops_root() . '/collections/' . $slug;
    if (is_dir($bopsCollectionDir)) {
        rmdir_r($bopsCollectionDir);
        echo "Removed: $bopsCollectionDir\n";
    }
    
    $bopsSitesDir = bops_root() . '/sites/' . $slug;
    if (is_dir($bopsSitesDir)) {
        rmdir_r($bopsSitesDir);
        echo "Removed: $bopsSitesDir\n";
    }
}

if (is_dir(STORAGE_DIR)) {
    rmdir_r(STORAGE_DIR);
    echo "Removed: " . STORAGE_DIR . "\n";
}

// Summary
echo "\n========================================\n";
echo "Test Summary\n";
echo "========================================\n";
echo "Total assertions: " . ($pass + $fail) . "\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";

if ($fail === 0) {
    echo "\nEXPECTED RESULT: All preservation tests passed\n";
    echo "This confirms baseline behavior for non-bus sites\n";
    echo "These same tests must still pass after implementing the fix\n";
    echo "This ensures no regressions are introduced\n";
} else {
    echo "\nUNEXPECTED RESULT: $fail assertion(s) failed\n";
    echo "This means either:\n";
    echo "1. Non-bus sites already have bus-specific features (regression already exists)\n";
    echo "2. The test logic is incorrect\n";
    echo "3. The baseline behavior needs to be re-analyzed\n";
}

exit($fail > 0 ? 1 : 0);
