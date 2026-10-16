<?php
// Manual verification script for bus booking site generation
// This generates a bus booking site that can be opened in a browser for manual testing

require_once __DIR__ . '/../includes/AutoSiteGenerator.php';

echo "=== Manual Bus Booking Site Generation ===\n\n";

$input = [
    'company_name' => 'Express Bus Lines',
    'project_type' => 'bus booking',
    'primary_purpose' => 'online bus ticket booking and seat reservation',
    'target_audience' => 'travelers and commuters',
    'unique_value' => 'real-time seat availability and instant booking confirmation',
    'must_have_features' => 'seat selection, trip search, ticket booking',
];

echo "Generating bus booking site...\n";
echo "Company: {$input['company_name']}\n";
echo "Type: {$input['project_type']}\n\n";

try {
    $result = AutoSiteGenerator::generate($input);
    
    if (isset($result['success']) && $result['success']) {
        echo "✓ Site generation successful!\n\n";
        echo "Site Details:\n";
        echo "  Slug: {$result['slug']}\n";
        
        $publishedRoot = defined('STORAGE_DIR') ? (dirname(STORAGE_DIR) . '/published') : (dirname(__DIR__) . '/published');
        $indexPath = $publishedRoot . '/' . $result['slug'] . '/index.html';
        
        echo "  Path: $indexPath\n";
        echo "  URL: http://localhost/project/webbbuilder/published/{$result['slug']}/index.html\n\n";
        
        if (file_exists($indexPath)) {
            $html = file_get_contents($indexPath);
            
            echo "Manual Verification Checklist:\n";
            echo "================================\n\n";
            
            // Check 1: window.WCBiz
            $hasWCBiz = strpos($html, 'window.WCBiz') !== false;
            echo "1. window.WCBiz defined: " . ($hasWCBiz ? "✓ YES" : "✗ NO") . "\n";
            
            // Check 2: Runtime script
            $hasRuntime = strpos($html, 'data-wc-biz-runtime') !== false;
            echo "2. data-wc-biz-runtime present: " . ($hasRuntime ? "✓ YES" : "✗ NO") . "\n";
            
            // Check 3: Bus booking flag
            $hasBusFlag = strpos($html, 'IS_BUS_BOOKING=(true)') !== false || strpos($html, 'IS_BUS_BOOKING=true') !== false;
            echo "3. IS_BUS_BOOKING=true: " . ($hasBusFlag ? "✓ YES" : "✗ NO") . "\n";
            
            // Check 4: API reference
            $hasApiRef = strpos($html, 'api/business-ops.php') !== false;
            echo "4. api/business-ops.php reference: " . ($hasApiRef ? "✓ YES" : "✗ NO") . "\n";
            
            // Check 5: Bus widget
            $hasWidget = strpos($html, 'data-wc-live-bus-booking') !== false || 
                        strpos($html, 'bus-seat-grid') !== false ||
                        strpos($html, 'id="busSeatMap"') !== false;
            echo "5. Bus booking widget: " . ($hasWidget ? "✓ YES" : "✗ NO") . "\n";
            
            echo "\n";
            echo "Browser Verification Steps:\n";
            echo "===========================\n";
            echo "1. Open: http://localhost/project/webbbuilder/published/{$result['slug']}/index.html\n";
            echo "2. Open browser console (F12)\n";
            echo "3. Type: window.WCBiz\n";
            echo "   Expected: Object with api, hold, create, cancel, pay, ticketQR, printTicket, gotoStep methods\n";
            echo "4. Type: typeof window.WCBiz.api\n";
            echo "   Expected: 'function'\n";
            echo "5. Type: window.WCBiz.api('list', {entity: 'trips'})\n";
            echo "   Expected: Promise that resolves with trips data (may be empty array initially)\n";
            
        } else {
            echo "ERROR: Generated index.html not found at: $indexPath\n";
        }
        
    } else {
        echo "✗ Site generation failed\n";
        if (isset($result['error'])) {
            echo "Error: {$result['error']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";
