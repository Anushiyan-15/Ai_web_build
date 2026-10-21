<?php
// includes/AutoSiteGenerator.php — Full End-to-End Dynamic Website & Admin Generator
// Automatically creates a category-tailored dynamic website (like bus booking,
// restaurant ordering, salon appointments, ecommerce, hotel reservations, etc.)
// with working frontend, backend API, live database collections, and Admin Panel.

require_once __DIR__ . '/BusinessRegistry.php';
require_once __DIR__ . '/BusinessAnalyzer.php';
require_once __DIR__ . '/BusinessManifest.php';
require_once __DIR__ . '/AdminDashboardBuilder.php';
require_once __DIR__ . '/AdminPanelGenerator.php';
require_once __DIR__ . '/PublishedAdminKit.php';
require_once __DIR__ . '/SiteApiBridge.php';
require_once __DIR__ . '/TasteSkill.php';
require_once __DIR__ . '/BusinessRuntime.php';
require_once __DIR__ . '/BusBookingWidget.php';

if (!class_exists('AutoSiteGenerator')) {
class AutoSiteGenerator {

    /**
     * Generate and publish a complete dynamic website and admin panel for any business.
     */
    public static function generate(array $input): array {
        $bizName = trim((string)($input['company_name'] ?? $input['biz_name'] ?? 'Smart Business'));
        if ($bizName === '') $bizName = 'Smart Business';

        $clientName  = trim((string)($input['client_name'] ?? 'Business Owner'));
        $clientEmail = trim((string)($input['client_email'] ?? 'admin@example.com'));
        $clientPhone = trim((string)($input['client_phone'] ?? ''));
        $projectType = trim((string)($input['project_type'] ?? ''));
        $purpose     = trim((string)($input['primary_purpose'] ?? ''));
        $features    = trim((string)($input['must_have_features'] ?? ''));
        $audience    = trim((string)($input['target_audience'] ?? ''));
        $uvp         = trim((string)($input['uvp'] ?? ''));

        // 1. Detect Category
        $combinedText = $bizName . ' ' . $projectType . ' ' . $purpose . ' ' . $features . ' ' . $uvp;
        $category = business_registry_detect($combinedText);
        $categories = business_registry_categories();
        $catMeta = $categories[$category] ?? $categories['generic'];

        // 2. Generate Slug (underscores preserved: storage/API layers treat
        // "_" as significant; stripping it once split "__bustest2" into two
        // stores whose bookings could never meet).
        $baseSlug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', trim($bizName)));
        $baseSlug = trim($baseSlug, '-');
        if (strlen($baseSlug) < 3) $baseSlug = 'business-' . substr(md5(uniqid()), 0, 5);
        
        $publishedRoot = defined('STORAGE_DIR') ? (dirname(STORAGE_DIR) . '/published') : (dirname(__DIR__) . '/published');
        $slug = $baseSlug;
        $counter = 1;
        while (is_dir($publishedRoot . '/' . $slug)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $orderId = 'ord_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
        $targetDir = $publishedRoot . '/' . $slug;
        @mkdir($targetDir, 0755, true);

        // 3. Admin Credentials
        $adminUsername = !empty($input['admin_username']) ? trim($input['admin_username']) : 'admin';
        $adminPassword = !empty($input['admin_password']) ? trim($input['admin_password']) : ('Pass_' . substr(md5(uniqid()), 0, 8));

        // 4. Build Business Manifest
        $analysisInput = [
            'biz_name'    => $bizName,
            'biz_type'    => $catMeta['label'] ?? $category,
            'services'    => $features ?: ($purpose ?: 'Standard services and customer bookings'),
            'products'    => $features,
            'description' => $purpose . ' ' . $uvp,
        ];
        $analysis = business_analyzer_analyze($analysisInput);
        $manifest = business_manifest_build($analysis, $slug, $orderId);

        // Construct business spec for runtime injection
        $businessSpec = [
            'category' => $category,
            'slug' => $slug,
            'profile' => [
                'business_name' => $bizName,
                'domain' => $category,
                'description' => $purpose . ' ' . $uvp,
            ],
            'business' => [
                'category' => $category,
                'name' => $bizName,
            ],
            'entities' => $manifest['entities'] ?? [],
            'capabilities' => $manifest['capabilities'] ?? [],
        ];

        // 5. Build Category-Specific Dynamic Frontend HTML
        $siteHtml = self::buildDynamicWebsiteHtml($category, $bizName, $catMeta, $input, $slug);

        // Inject Business Runtime & Safety Net
        if (function_exists('business_runtime_inject')) {
            $siteHtml = business_runtime_inject($siteHtml, $businessSpec, $slug);
        }
        if (function_exists('taste_apply_safety_net')) {
            $siteHtml = taste_apply_safety_net($siteHtml);
        }
        @file_put_contents($targetDir . '/index.html', $siteHtml);
        if ($category === 'bus' && !copy(__DIR__ . '/templates/bus-customer-portal.php', $targetDir . '/customer.php')) {
            throw new RuntimeException('Unable to create the generated bus customer portal.');
        }
        if ($category === 'bus') {
            @copy(__DIR__ . '/templates/bus-ticket-verify.php', $targetDir . '/verify.php');
        }

        // 6. Setup Admin Directory & Files
        $adminDir = $targetDir . '/admin';
        @mkdir($adminDir, 0755, true);

        $passHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $order = [
            'order_id'             => $orderId,
            'slug'                 => $slug,
            'site_name'            => $bizName,
            'biz_type'             => $category,
            'admin_username'       => $adminUsername,
            'admin_password'       => $adminPassword,
            'admin_password_plain' => $adminPassword,
            'admin_password_hash'  => $passHash,
            'admin_email'          => $clientEmail,
            'contact_email'        => $clientEmail,
            'gen_mode'             => 'admin',
            'created_at'           => date('Y-m-d H:i:s'),
            'status'               => 'active',
        ];

        // Install CMS Admin via Manifest
        AdminDashboardBuilder::installFromManifest($adminDir, $order, $manifest);

        // Set Password Hash in .auth.json
        $authData = [
            'username'   => $adminUsername,
            'hash'       => password_hash($adminPassword, PASSWORD_BCRYPT),
            'email'      => $clientEmail,
            'created_at' => date('Y-m-d H:i:s')
        ];
        @file_put_contents($adminDir . '/.auth.json', json_encode($authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Install Admin Kit & Public Handlers
        $manifestEntities = AdminDashboardBuilder::manifestEntitiesForGenerator($manifest);
        $order['admin_entities'] = $manifestEntities;
        
        if (function_exists('pak_install')) {
            pak_install($adminDir, $order);
        }
        if (function_exists('pak_public_handlers')) {
            pak_public_handlers($targetDir, $order, $manifestEntities);
        }
        if (function_exists('writeSiteApiBridge')) {
            writeSiteApiBridge($targetDir);
        }

        // 7. Seed Initial Dynamic Catalog Data (Items, Trips, Services, etc.)
        self::seedCatalogData($category, $bizName, $adminDir, $slug);

        // 8. Storage & Metadata Persistence
        $sitesStorageDir = (defined('STORAGE_DIR') ? STORAGE_DIR : (dirname(__DIR__) . '/storage')) . '/sites/' . $slug;
        @mkdir($sitesStorageDir, 0755, true);
        @file_put_contents($sitesStorageDir . '/business.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $secFile = $sitesStorageDir . '/node_secret';
        if (!file_exists($secFile)) @file_put_contents($secFile, bin2hex(random_bytes(32)));

        // Write meta.json
        $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder';
        $liveUrl = $siteUrl . '/published/' . $slug . '/index.html';
        $adminUrl = $siteUrl . '/published/' . $slug . '/admin/login.php';

        $meta = [
            'order_id'       => $orderId,
            'site_name'      => $bizName,
            'slug'           => $slug,
            'category'       => $category,
            'category_label' => $catMeta['label'] ?? ucfirst($category),
            'client_name'    => $clientName,
            'client_email'   => $clientEmail,
            'published_at'   => date('Y-m-d H:i:s'),
            'admin_username' => $adminUsername,
            'live_url'       => $liveUrl,
            'admin_url'      => $adminUrl,
            'gen_mode'       => 'admin',
            'status'         => 'active',
        ];
        @file_put_contents($targetDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Save order to DB if available
        $orderRecord = array_merge($order, [
            'live_url'     => $liveUrl,
            'admin_url'    => $adminUrl,
            'published_at' => date('Y-m-d H:i:s'),
            'meta'         => $meta
        ]);
        if (function_exists('saveOrderToDatabase')) {
            try { saveOrderToDatabase($orderRecord); } catch (\Throwable $e) {}
        }
        if (function_exists('saveOrder')) {
            try { saveOrder($orderRecord); } catch (\Throwable $e) {}
        }

        return [
            'success'        => true,
            'order_id'       => $orderId,
            'slug'           => $slug,
            'site_name'      => $bizName,
            'category'       => $category,
            'category_label' => $catMeta['label'] ?? ucfirst($category),
            'live_url'       => $liveUrl,
            'admin_url'      => $adminUrl,
            'admin_username' => $adminUsername,
            'admin_password' => $adminPassword,
            'entities'       => $catMeta['entities'] ?? [],
        ];
    }

    /**
     * Seeds realistic items (trips, menu items, rooms, stylists, products)
     * so the live website and admin panel immediately show working dynamic content.
     */
    private static function seedCatalogData(string $cat, string $bizName, string $adminDir, string $slug): void {
        $storageDir = (defined('STORAGE_DIR') ? STORAGE_DIR : (dirname(__DIR__) . '/storage')) . '/collections/' . $slug;
        @mkdir($storageDir, 0755, true);

        $now = date('Y-m-d H:i:s');
        $seeds = [];

        switch ($cat) {
            case 'bus':
                $seeds['trips'] = [
                    ['id' => 'trip_101', 'route' => 'Chennai → Bangalore Express', 'date' => date('Y-m-d', strtotime('+1 day')), 'departure' => '08:00 AM', 'price' => 850, 'total_seats' => 40, 'available_seats' => 34, 'created_at' => $now],
                    ['id' => 'trip_102', 'route' => 'Chennai → Bangalore AC Sleeper', 'date' => date('Y-m-d', strtotime('+1 day')), 'departure' => '10:30 PM', 'price' => 1100, 'total_seats' => 36, 'available_seats' => 30, 'created_at' => $now],
                    ['id' => 'trip_103', 'route' => 'Bangalore → Chennai Superfast', 'date' => date('Y-m-d', strtotime('+2 days')), 'departure' => '06:15 AM', 'price' => 850, 'total_seats' => 40, 'available_seats' => 38, 'created_at' => $now],
                ];
                $seeds['routes'] = [
                    ['id' => 'rt_1', 'origin' => 'Chennai', 'destination' => 'Bangalore', 'distance' => 350, 'fare' => 850, 'created_at' => $now],
                    ['id' => 'rt_2', 'origin' => 'Bangalore', 'destination' => 'Chennai', 'distance' => 350, 'fare' => 850, 'created_at' => $now],
                ];
                $seeds['buses'] = [
                    ['id' => 'bus_1', 'plate' => 'TN-01-AB-1234', 'capacity' => 40, 'layout' => '2x2', 'status' => 'active', 'created_at' => $now],
                    ['id' => 'bus_2', 'plate' => 'KA-01-CD-5678', 'capacity' => 36, 'layout' => '2x1', 'status' => 'active', 'created_at' => $now],
                ];
                $seeds['bookings'] = [];
                $seeds['payments'] = [];
                $seeds['customers'] = [];
                break;

            case 'restaurant':
                $seeds['menu_items'] = [
                    ['id' => 'm_1', 'name' => 'Signature Chef Special Steak', 'category' => 'Mains', 'price' => 28, 'description' => 'Prime cut grilled to perfection with herb butter and seasonal greens.', 'created_at' => $now],
                    ['id' => 'm_2', 'name' => 'Truffle & Wild Mushroom Pasta', 'category' => 'Mains', 'price' => 22, 'description' => 'Handmade fettuccine with shaved parmesan and rich cream sauce.', 'created_at' => $now],
                    ['id' => 'm_3', 'name' => 'Artisanal Woodfired Margherita', 'category' => 'Pizza', 'price' => 16, 'description' => 'San Marzano tomatoes, buffalo mozzarella, fresh basil.', 'created_at' => $now],
                    ['id' => 'm_4', 'name' => 'Decadent Molten Lava Cake', 'category' => 'Desserts', 'price' => 10, 'description' => 'Warm dark chocolate center served with Madagascar vanilla gelato.', 'created_at' => $now],
                ];
                break;

            case 'salon':
                $seeds['services'] = [
                    ['id' => 'srv_1', 'name' => 'Deluxe Hair Cut & Styling', 'category' => 'Hair', 'price' => 45, 'duration' => '45 min', 'created_at' => $now],
                    ['id' => 'srv_2', 'name' => 'Balayage & Color Gloss', 'category' => 'Color', 'price' => 120, 'duration' => '90 min', 'created_at' => $now],
                    ['id' => 'srv_3', 'name' => 'Hydra-Infusion Facial Therapy', 'category' => 'Skincare', 'price' => 75, 'duration' => '60 min', 'created_at' => $now],
                ];
                break;

            case 'hotel':
                $seeds['rooms'] = [
                    ['id' => 'rm_1', 'room_type' => 'Deluxe Ocean View King', 'capacity' => 2, 'price_per_night' => 140, 'amenities' => 'King Bed, Balcony, Wi-Fi, Breakfast', 'created_at' => $now],
                    ['id' => 'rm_2', 'room_type' => 'Executive Garden Suite', 'capacity' => 4, 'price_per_night' => 220, 'amenities' => 'Living Room, Jacuzzi, Mini-Bar, Pool Access', 'created_at' => $now],
                ];
                break;

            case 'ecommerce':
                $seeds['products'] = [
                    ['id' => 'p_1', 'title' => 'Signature Classic Series Item', 'category' => 'Featured', 'price' => 49.99, 'stock' => 50, 'description' => 'Premium quality crafted for daily performance.', 'created_at' => $now],
                    ['id' => 'p_2', 'title' => 'Pro Edition Dynamic Accessory', 'category' => 'New', 'price' => 89.99, 'stock' => 30, 'description' => 'Engineered with top-tier materials and sleek design.', 'created_at' => $now],
                ];
                break;

            case 'construction':
                $seeds['projects'] = [
                    ['id' => 'proj_1', 'title' => 'Modern Commercial Tower', 'category' => 'Commercial', 'status' => 'completed', 'value' => 2500000, 'location' => 'Downtown Business District', 'year' => date('Y') - 1, 'description' => 'A 12-storey mixed-use development delivering premium office and retail space.', 'created_at' => $now],
                    ['id' => 'proj_2', 'title' => 'Luxury Residential Complex', 'category' => 'Residential', 'status' => 'completed', 'value' => 1800000, 'location' => 'Uptown Heights', 'year' => date('Y') - 1, 'description' => '48 premium apartments with rooftop garden, gym and underground parking.', 'created_at' => $now],
                    ['id' => 'proj_3', 'title' => 'Infrastructure Bridge Overhaul', 'category' => 'Civil', 'status' => 'in_progress', 'value' => 900000, 'location' => 'City North Corridor', 'year' => date('Y'), 'description' => 'Structural rehabilitation and widening of a major arterial bridge.', 'created_at' => $now],
                ];
                $seeds['services'] = [
                    ['id' => 'svc_1', 'name' => 'Structural Engineering & Design', 'category' => 'Engineering', 'description' => 'Full structural analysis, design and certification for all building types.', 'created_at' => $now],
                    ['id' => 'svc_2', 'name' => 'General Contracting & Project Management', 'category' => 'Construction', 'description' => 'End-to-end project delivery from groundbreaking to handover.', 'created_at' => $now],
                    ['id' => 'svc_3', 'name' => 'Renovation & Fit-Out Works', 'category' => 'Renovation', 'description' => 'Interior and exterior renovation with minimal operational disruption.', 'created_at' => $now],
                ];
                $seeds['inquiries'] = [];
                break;

            case 'portfolio':
                $seeds['projects'] = [
                    ['id' => 'port_1', 'title' => 'Brand Identity — Apex Fintech', 'category' => 'Branding', 'year' => date('Y') - 1, 'client' => 'Apex Financial Group', 'description' => 'Complete visual identity including logo system, typography, colour palette and brand guidelines.', 'created_at' => $now],
                    ['id' => 'port_2', 'title' => 'Product UI Design — HealthTrack App', 'category' => 'UI/UX', 'year' => date('Y'), 'client' => 'HealthTrack Inc', 'description' => 'End-to-end mobile UX research, wireframes, prototypes and high-fidelity UI for iOS and Android.', 'created_at' => $now],
                    ['id' => 'port_3', 'title' => 'Motion Campaign — SkyRoute Airlines', 'category' => 'Motion', 'year' => date('Y'), 'client' => 'SkyRoute Airlines', 'description' => 'Animated promotional series for social media and in-flight entertainment channels.', 'created_at' => $now],
                ];
                $seeds['services'] = [
                    ['id' => 'sv_1', 'name' => 'Brand Identity Design', 'category' => 'Branding', 'starting_price' => 1500, 'turnaround' => '2–3 weeks', 'description' => 'Logo, colour palette, typography and full brand guidelines.', 'created_at' => $now],
                    ['id' => 'sv_2', 'name' => 'UI/UX Design & Prototyping', 'category' => 'Digital', 'starting_price' => 2500, 'turnaround' => '3–5 weeks', 'description' => 'Research-driven wireframes, interactive prototypes and pixel-perfect UI delivery.', 'created_at' => $now],
                    ['id' => 'sv_3', 'name' => 'Motion Graphics & Video', 'category' => 'Motion', 'starting_price' => 1200, 'turnaround' => '1–2 weeks', 'description' => 'Animated explainers, social reels and broadcast-quality motion graphics.', 'created_at' => $now],
                ];
                $seeds['inquiries'] = [];
                break;

            default:
                $seeds['services'] = [
                    ['id' => 's_1', 'name' => 'Comprehensive Service Package', 'category' => 'Core', 'price' => 99, 'description' => 'Complete end-to-end service with guaranteed satisfaction.', 'created_at' => $now],
                    ['id' => 's_2', 'name' => 'Premium Consultation & Delivery', 'category' => 'Pro', 'price' => 180, 'description' => 'Dedicated priority support and expedited execution.', 'created_at' => $now],
                ];
                break;
        }

        // Write each seed to both admin/data_<entity>.json and collections/<slug>/<entity>.json
        foreach ($seeds as $entity => $rows) {
            $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            @file_put_contents($adminDir . '/data_' . $entity . '.json', $json, LOCK_EX);
            @file_put_contents($storageDir . '/' . $entity . '.json', $json, LOCK_EX);
        }
    }

    /**
     * Builds responsive HTML template with interactive components for the detected business.
     */
    private static function buildDynamicWebsiteHtml(string $cat, string $bizName, array $catMeta, array $input, string $slug): string {
        $tagline = !empty($input['uvp']) ? htmlspecialchars($input['uvp']) : 'Premier quality and dedicated service for our valued clients.';
        $desc = !empty($input['primary_purpose']) ? htmlspecialchars($input['primary_purpose']) : 'Discover seamless booking, transparent pricing, and instant confirmations.';
        $phone = !empty($input['client_phone']) ? htmlspecialchars($input['client_phone']) : '+1 (555) 234-5678';
        $email = !empty($input['client_email']) ? htmlspecialchars($input['client_email']) : 'hello@' . $slug . '.com';

        // Render business-specific dynamic section
        $dynamicSection = self::renderDynamicWidget($cat, $bizName, $slug);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$bizName} — Official Website</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --bg: #0b0f19;
            --surface: #131b2e;
            --surface-hover: #1c2742;
            --border: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        
        /* Navbar */
        nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(11, 15, 25, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 18px 0;
        }
        .nav-inner { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 20px; font-weight: 800; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .nav-links { display: flex; gap: 28px; align-items: center; }
        .nav-links a { color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: #fff; }
        .btn-portal { background: #1e293b; color: #f8fafc; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid #334155; }
        .btn-portal:hover { background: #334155; }

        /* Hero */
        .hero { padding: 90px 0 60px; text-align: center; position: relative; }
        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.25);
            color: #60a5fa; padding: 6px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 600; margin-bottom: 24px;
        }
        .hero h1 { font-size: clamp(34px, 5vw, 56px); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 20px; color: #fff; line-height: 1.15; }
        .hero p { font-size: 18px; color: var(--text-muted); max-width: 680px; margin: 0 auto 36px; }
        
        /* Interactive dynamic widget container */
        .widget-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px;
            margin: 40px auto;
            max-width: 900px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            text-align: left;
        }

        /* Features / Stats */
        .features { padding: 70px 0; border-top: 1px solid var(--border); }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 28px; }
        .card h3 { font-size: 18px; font-weight: 700; margin-bottom: 10px; color: #fff; }
        .card p { font-size: 14px; color: var(--text-muted); }

        /* Form elements */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; }
        .form-input, .form-select {
            width: 100%; padding: 12px 16px; background: #0b0f19;
            border: 1px solid var(--border); border-radius: 10px;
            color: #fff; font-size: 14px; font-family: inherit;
        }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); }
        .bus-trip-list { display: grid; gap: 12px; margin-top: 18px; }
        .bus-trip-option { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; padding: 16px; border: 1px solid var(--border); border-radius: 12px; background: #0b0f19; }
        .bus-trip-option h3 { margin: 0 0 4px; font-size: 15px; }
        .bus-trip-option p { margin: 0; color: var(--text-muted); font-size: 13px; }
        .bus-seat-grid { display: grid; grid-template-columns: repeat(4, minmax(42px, 56px)); gap: 9px; margin: 18px 0; }
        .bus-seat { min-height: 42px; border: 1px solid #475569; border-radius: 9px; background: #0b0f19; color: #e2e8f0; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
        .bus-seat:hover:not(:disabled), .bus-seat[aria-pressed="true"] { border-color: #10b981; background: rgba(16,185,129,.18); color: #6ee7b7; }
        .bus-seat:disabled { border-color: #334155; background: #263247; color: #64748b; cursor: not-allowed; text-decoration: line-through; }
        .bus-status { min-height: 24px; margin-top: 12px; color: var(--text-muted); font-size: 13px; }
        .bus-status[data-state="error"] { color: #fca5a5; }
        .bus-status[data-state="success"] { color: #6ee7b7; }
        @media(max-width:600px) { .bus-trip-option { grid-template-columns: 1fr; } }
        .btn-submit {
            display: inline-flex; justify-content: center; align-items: center; width: 100%;
            background: var(--primary); color: #fff; padding: 14px 24px;
            border-radius: 10px; font-size: 15px; font-weight: 700; border: none; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: var(--primary-hover); }

        /* Notification / Toast */
        .toast { display: none; padding: 16px; border-radius: 10px; margin-top: 18px; font-size: 14px; }
        .toast-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; }

        /* Footer */
        footer { padding: 50px 0 30px; border-top: 1px solid var(--border); text-align: center; color: var(--text-muted); font-size: 14px; }
        footer a { color: #60a5fa; text-decoration: none; }
    </style>
</head>
<body>

    <nav>
        <div class="container nav-inner">
            <a href="#" class="logo">
                <span>✨</span> {$bizName}
            </a>
            <div class="nav-links">
                <a href="#services">Services</a>
                <a href="#booking">Book Online</a>
                <a href="#contact">Contact</a>
                <a href="customer.php" class="btn-portal">👤 My Account</a>
                <a href="admin/login.php" class="btn-portal">🔐 Staff Admin</a>
            </div>
        </div>
    </nav>

    <header class="hero container">
        <div class="badge">✨ Verified Live Operations & Booking Engine</div>
        <h1>{$bizName}</h1>
        <p>{$tagline}</p>
        
        {$dynamicSection}
    </header>

    <section id="services" class="features container">
        <div class="grid-3">
            <div class="card">
                <h3>⚡ Real-Time Instant Confirmations</h3>
                <p>All bookings, orders, and inquiries are instantly processed and recorded in our live management system.</p>
            </div>
            <div class="card">
                <h3>🛡️ Verified & Secure</h3>
                <p>Enterprise dual-backend architecture ensures your reservations are always safe and accessible.</p>
            </div>
            <div class="card">
                <h3>📱 Dedicated Customer Support</h3>
                <p>Reach us directly at {$phone} or email us at {$email} for special requests or assistance.</p>
            </div>
        </div>
    </section>

    <section id="contact" class="container" style="padding: 50px 0; max-width: 650px;">
        <div class="card">
            <h3 style="font-size: 22px; margin-bottom: 8px;">Direct Customer Inquiry</h3>
            <p style="margin-bottom: 24px;">Have questions? Send a direct message to our support team.</p>
            <form id="generalInquiryForm" data-wc-form="inquiries">
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="name" class="form-input" required placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-input" required placeholder="john@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-input" placeholder="+1 (555) 000-0000">
                </div>
                <div class="form-group">
                    <label>Message / Request</label>
                    <textarea name="message" class="form-input" rows="3" required placeholder="How can we assist you?"></textarea>
                </div>
                <button type="submit" class="btn-submit">Send Message</button>
                <div id="inquiryToast" class="toast toast-success">Thank you! Your message has been received. Our team will contact you shortly.</div>
            </form>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> {$bizName}. All rights reserved.</p>
            <p style="margin-top: 8px; font-size: 13px;">Powered by WebCraft Dynamic Dual-Backend Platform • <a href="admin/login.php">Admin Login</a></p>
        </div>
    </footer>

    <script>
    document.getElementById('generalInquiryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var f = e.target;
        var btn = f.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Sending...';
        
        var payload = {
            entity: 'inquiries',
            record: {
                name: f.name.value,
                email: f.email.value,
                phone: f.phone.value,
                message: f.message.value,
                status: 'unread',
                submitted_at: new Date().toISOString()
            }
        };

        // Post to site API bridge
        fetch('api/business-ops.php?action=create&entity=inquiries&slug={$slug}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('inquiryToast').style.display = 'block';
            f.reset();
            btn.disabled = false;
            btn.textContent = 'Send Message';
        })
        .catch(function() {
            document.getElementById('inquiryToast').style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Send Message';
        });
    });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Renders specific dynamic widgets based on business type (Bus, Restaurant, Salon, etc.)
     */
    private static function renderDynamicWidget(string $cat, string $bizName, string $slug): string {
        if ($cat === 'bus') return bus_booking_widget_html($slug);
        switch ($cat) {
            case 'bus':
                return <<<HTML
<div id="booking" class="widget-wrap">
    <div style="margin-bottom:22px; border-bottom:1px solid var(--border); padding-bottom:16px;">
        <h2 style="font-size:22px; font-weight:700; color:#fff;">Find a trip</h2>
        <p style="margin-top:5px; color:var(--text-muted); font-size:14px;">Search live schedules, choose available seats, then confirm your booking.</p>
    </form>
    </div>
    <form id="busTripSearch" data-wc-search="trips" data-wc-custom>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px;">
            <div class="form-group"><label for="busOrigin">From</label><input id="busOrigin" name="origin" class="form-input" required placeholder="Departure city"></div>
            <div class="form-group"><label for="busDestination">To</label><input id="busDestination" name="destination" class="form-input" required placeholder="Arrival city"></div>
            <div class="form-group"><label for="busDate">Travel date</label><input id="busDate" name="date" type="date" class="form-input" required></div>
        </div>
        <button type="submit" class="btn-submit" id="busSearchButton">Search trips</button>
    </form>
    <div id="busTripResults" class="bus-trip-list" aria-live="polite"></div>
    <section id="busSeatStep" hidden style="margin-top:26px; border-top:1px solid var(--border); padding-top:22px;">
        <h3 id="busSelectedTripTitle" style="font-size:18px; margin-bottom:4px;"></h3>
        <p id="busSelectedTripMeta" style="color:var(--text-muted); font-size:13px;"></p>
        <p style="margin-top:18px; font-size:13px; color:var(--text-muted);">Choose available seats. Selected seats are held for 15 minutes.</p>
        <div id="busSeatGrid" class="bus-seat-grid" role="group" aria-label="Available bus seats"></div>
        <button type="button" class="btn-submit" id="busHoldButton">Hold selected seats</button>
        <p id="busHoldStatus" class="bus-status" role="status" aria-live="polite"></p>
        <form id="busBookingForm" data-wc-custom hidden style="margin-top:20px;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
                <div class="form-group"><label for="busPassenger">Passenger name</label><input id="busPassenger" name="passenger" class="form-input" autocomplete="name" required maxlength="120"></div>
                <div class="form-group"><label for="busPhone">Mobile number</label><input id="busPhone" name="phone" type="tel" class="form-input" autocomplete="tel" required maxlength="30"></div>
                <div class="form-group"><label for="busEmail">Email (optional)</label><input id="busEmail" name="email" type="email" class="form-input" autocomplete="email" maxlength="160"></div>
            </div>
            <button type="submit" class="btn-submit" id="busBookButton">Confirm booking</button>
        </form>
        <p id="busBookingStatus" class="bus-status" role="status" aria-live="polite"></p>
    </section>
    <section style="margin-top:30px; border-top:1px solid var(--border); padding-top:22px;">
        <h3 style="font-size:17px; margin-bottom:8px;">Already booked?</h3>
        <p style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">Look up a ticket using the phone number and booking reference.</p>
        <div data-wc-ticket-lookup="bookings"></div>
    </section>
</div>
<script>
(function(){
    var searchForm=document.getElementById('busTripSearch'),results=document.getElementById('busTripResults');
    var seatStep=document.getElementById('busSeatStep'),seatGrid=document.getElementById('busSeatGrid');
    var holdStatus=document.getElementById('busHoldStatus'),bookingStatus=document.getElementById('busBookingStatus');
    var bookingForm=document.getElementById('busBookingForm'),holdButton=document.getElementById('busHoldButton');
    var bookButton=document.getElementById('busBookButton'),selectedTrip=null,selectedSeats=[],currentHold=null;
    function status(el,message,state){el.textContent=message;el.setAttribute('data-state',state||'');}
    function api(action,payload){
        if(!window.WCBiz||typeof window.WCBiz.api!=='function')return Promise.reject(new Error('Booking service is not ready. Refresh and try again.'));
        return window.WCBiz.api(action,payload);
    }
    function releaseHold(){
        if(!currentHold)return Promise.resolve();
        var old=currentHold;currentHold=null;
        if(window.__WC_HOLD__)window.__WC_HOLD__=null;
        return api('release_hold',{entity:'bookings',ref:old.ref}).then(function(r){
            if(!r||!r.success){currentHold=old;throw new Error((r&&r.error)||'Could not release the previous seat hold.');}
        });
    }
    function renderTrip(trip){
        var card=document.createElement('article'),details=document.createElement('div');
        card.className='bus-trip-option';
        var title=document.createElement('h3');
        title.textContent=trip.route||((trip.origin||'')+' to '+(trip.destination||''));
        var meta=document.createElement('p');
        meta.textContent=[trip.date,trip.departure||trip.departure_time,trip.bus||trip.bus_type,
            trip.price==null?'':'Fare: '+trip.price].filter(Boolean).join(' · ');
        var choose=document.createElement('button');
        choose.type='button';choose.className='btn-submit bus-select-trip';choose.textContent='Choose seats';
        choose.disabled=!(parseInt(trip.total_seats||trip.capacity||'0',10)>0);
        choose.addEventListener('click',function(){showSeats(trip);});
        details.appendChild(title);details.appendChild(meta);card.appendChild(details);card.appendChild(choose);
        return card;
    }
    function showSeats(trip){
        releaseHold().then(function(){
            selectedTrip=trip;selectedSeats=[];bookingForm.hidden=true;bookingStatus.textContent='';holdStatus.textContent='';
            document.getElementById('busSelectedTripTitle').textContent=trip.route||((trip.origin||'')+' to '+(trip.destination||''));
            document.getElementById('busSelectedTripMeta').textContent=[trip.date,trip.departure||trip.departure_time,
                trip.price==null?'':'Fare: '+trip.price].filter(Boolean).join(' · ');
            seatStep.hidden=false;holdButton.hidden=false;seatGrid.textContent='Loading seat availability…';
            if(window.WCBiz)window.WCBiz.activeTrip=trip;
            api('availability',{entity:'bookings',trip_id:trip.id}).then(function(r){
                if(!r||!r.success||!r.data)throw new Error((r&&r.error)||'Could not load seats for this trip.');
                var capacity=parseInt(r.data.total_seats||trip.total_seats||trip.capacity||'0',10);
                if(!Number.isFinite(capacity)||capacity<1)throw new Error('This trip has no seat layout yet.');
                var columns=['A','B','C','D'],unavailable=r.data.reserved_seats||[];
                seatGrid.textContent='';
                for(var i=0;i<capacity;i++){
                    var name=columns[i%4]+(Math.floor(i/4)+1),seat=document.createElement('button');
                    seat.type='button';seat.className='bus-seat';seat.textContent=name;seat.setAttribute('data-seat',name);
                    seat.setAttribute('aria-pressed','false');seat.disabled=unavailable.indexOf(name)!==-1;
                    seat.setAttribute('aria-label','Seat '+name+(seat.disabled?', unavailable':', available'));
                    seat.addEventListener('click',function(){
                        var idx=selectedSeats.indexOf(name);
                        if(idx===-1)selectedSeats.push(name);else selectedSeats.splice(idx,1);
                        seat.setAttribute('aria-pressed',idx===-1?'true':'false');
                    });
                    seatGrid.appendChild(seat);
                }
                seatStep.scrollIntoView({behavior:'smooth',block:'start'});
            }).catch(function(error){seatGrid.textContent='';status(holdStatus,error.message,'error');});
        }).catch(function(error){status(holdStatus,error.message,'error');});
    }
    searchForm.addEventListener('submit',function(event){
        event.preventDefault();if(!searchForm.reportValidity())return;
        var button=document.getElementById('busSearchButton');
        button.disabled=true;button.textContent='Searching…';results.textContent='Loading available trips…';seatStep.hidden=true;
        selectedTrip=null;selectedSeats=[];
        releaseHold().then(function(){
            return api('list',{entity:'trips',origin:document.getElementById('busOrigin').value.trim(),
                destination:document.getElementById('busDestination').value.trim(),date:document.getElementById('busDate').value});
        }).then(function(r){
            button.disabled=false;button.textContent='Search trips';
            if(!r||!r.success||!Array.isArray(r.data))throw new Error((r&&r.error)||'Could not search trips. Please try again.');
            results.textContent='';
            if(!r.data.length){results.textContent='No trips match those details. Try another date or route.';return;}
            r.data.forEach(function(trip){results.appendChild(renderTrip(trip));});
        }).catch(function(error){
            button.disabled=false;button.textContent='Search trips';results.textContent='';
            status(bookingStatus,error.message||'Could not search trips. Please try again.','error');
        });
    });
    holdButton.addEventListener('click',function(){
        if(!selectedTrip||!selectedSeats.length){status(holdStatus,'Choose at least one available seat first.','error');return;}
        holdButton.disabled=true;holdButton.textContent='Holding seats…';
        window.WCBiz.hold('bookings',{trip_id:selectedTrip.id,seats:selectedSeats}).then(function(r){
            if(!r||!r.success||!r.data)throw new Error((r&&r.error)||'Could not hold these seats.');
            currentHold=r.data;bookingForm.hidden=false;holdButton.hidden=true;
            seatGrid.querySelectorAll('.bus-seat[aria-pressed="true"]').forEach(function(button){button.disabled=true;});
            status(holdStatus,'Seats held for 15 minutes. Complete passenger details to confirm.','success');
            bookingForm.scrollIntoView({behavior:'smooth',block:'start'});
        }).catch(function(error){status(holdStatus,error.message||'Could not hold seats. Refresh availability and try again.','error');})
        .finally(function(){holdButton.disabled=false;holdButton.textContent='Hold selected seats';});
    });
    bookingForm.addEventListener('submit',function(event){
        event.preventDefault();
        if(!currentHold||!selectedTrip||!selectedSeats.length){status(bookingStatus,'Hold your seats before confirming the booking.','error');return;}
        if(!bookingForm.reportValidity())return;
        bookButton.disabled=true;bookButton.textContent='Confirming…';
        var record={trip_id:selectedTrip.id,seats:selectedSeats.slice(),hold_ref:currentHold.ref,
            passenger:document.getElementById('busPassenger').value.trim(),phone:document.getElementById('busPhone').value.trim(),
            email:document.getElementById('busEmail').value.trim()};
        var key='bus_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,10);
        window.WCBiz.create('bookings',record,{idempotency_key:key}).then(function(r){
            if(!r||!r.success||!r.data||!r.data.ref)throw new Error((r&&r.error)||'Booking was not confirmed. Please try again.');
            currentHold=null;if(window.__WC_HOLD__)window.__WC_HOLD__=null;
            var saved=r.data;
            status(bookingStatus,'Booking saved. Reference: '+saved.ref+' · Status: '+(saved.status||'awaiting_payment')+
                ' · Payment: '+(saved.payment_status||'unpaid'),'success');
            bookingForm.reset();bookingForm.hidden=true;holdButton.hidden=false;holdStatus.textContent='';
            selectedSeats.forEach(function(name){var seat=seatGrid.querySelector('[data-seat="'+name+'"]');if(seat)seat.disabled=true;});
            selectedSeats=[];
        }).catch(function(error){
            status(bookingStatus,error.message||'Booking failed. Held seats remain reserved until the hold expires.','error');
        }).finally(function(){bookButton.disabled=false;bookButton.textContent='Confirm booking';});
    });
    var today=new Date();today.setMinutes(today.getMinutes()-today.getTimezoneOffset());
    var dateField=document.getElementById('busDate');dateField.min=today.toISOString().slice(0,10);dateField.value=dateField.min;
})();
</script>
HTML;

            case 'restaurant':
                return <<<HTML
<div id="booking" class="widget-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:14px;">
        <h2 style="font-size:20px; font-weight:700; color:#fff;">🍽️ Table Reservation & Online Food Order</h2>
        <span style="font-size:12px; background:rgba(234,179,8,0.15); color:#facc15; padding:4px 10px; border-radius:999px; font-weight:600;">Kitchen Live</span>
    </div>
    <form id="restBookingForm">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Order Type</label>
                <select name="type" class="form-select">
                    <option value="table">Dine-In Table Reservation</option>
                    <option value="takeout">Takeout / Delivery Order</option>
                </select>
            </div>
            <div class="form-group">
                <label>Guest / Party Size</label>
                <select name="party_size" class="form-select">
                    <option value="2">2 Guests</option>
                    <option value="4" selected>4 Guests</option>
                    <option value="6">6 Guests</option>
                    <option value="8+">8+ Guests</option>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Date & Desired Time</label>
                <input type="text" name="booking_time" class="form-input" required placeholder="Tonight, 7:30 PM">
            </div>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="tel" name="phone" class="form-input" required placeholder="+1 555-0143">
            </div>
        </div>
        <div class="form-group">
            <label>Name & Special Requests</label>
            <input type="text" name="guest_name" class="form-input" required placeholder="Your Name (e.g. Window table, birthday)">
        </div>
        <button type="submit" class="btn-submit" id="restBookBtn">🛎️ Reserve Table Now</button>
        <div id="restSuccessToast" class="toast toast-success">
            <strong>🎉 Table Reserved!</strong> Your reservation has been sent to the head host. Confirmation SMS on the way.
        </div>
    </form>
</div>
<script>
document.getElementById('restBookingForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var f = e.target;
    var btn = document.getElementById('restBookBtn');
    btn.disabled = true; btn.textContent = 'Reserving...';
    var payload = {
        entity: 'reservations',
        record: {
            guest_name: f.guest_name.value,
            phone: f.phone.value,
            party_size: f.party_size.value,
            booking_time: f.booking_time.value,
            status: 'confirmed',
            created_at: new Date().toISOString()
        }
    };
    fetch('api/business-ops.php?action=create&entity=reservations&slug={$slug}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(function(){
        document.getElementById('restSuccessToast').style.display = 'block';
        f.reset();
        btn.disabled = false; btn.textContent = '🛎️ Reserve Table Now';
    }).catch(function(){
        document.getElementById('restSuccessToast').style.display = 'block';
        btn.disabled = false; btn.textContent = '🛎️ Reserve Table Now';
    });
});
</script>
HTML;

            case 'salon':
                return <<<HTML
<div id="booking" class="widget-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:14px;">
        <h2 style="font-size:20px; font-weight:700; color:#fff;">✂️ Book Salon & Spa Appointment</h2>
        <span style="font-size:12px; background:rgba(236,72,153,0.15); color:#f472b6; padding:4px 10px; border-radius:999px; font-weight:600;">Select Stylist</span>
    </div>
    <form id="salBookingForm">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Select Treatment / Service</label>
                <select name="service" class="form-select">
                    <option value="Deluxe Hair Cut & Styling ($45)">Deluxe Hair Cut & Styling ($45)</option>
                    <option value="Balayage & Color Gloss ($120)">Balayage & Color Gloss ($120)</option>
                    <option value="Hydra-Infusion Facial Therapy ($75)">Hydra-Infusion Facial Therapy ($75)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Preferred Stylist</label>
                <select name="stylist" class="form-select">
                    <option value="Any Available Expert">Any Available Expert</option>
                    <option value="Master Stylist Sarah">Master Stylist Sarah</option>
                    <option value="Color Specialist David">Color Specialist David</option>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Client Name</label>
                <input type="text" name="customer" class="form-input" required placeholder="Your Name">
            </div>
            <div class="form-group">
                <label>Date & Time Slot</label>
                <input type="text" name="timeslot" class="form-input" required placeholder="Tomorrow at 2:00 PM">
            </div>
        </div>
        <button type="submit" class="btn-submit" id="salBookBtn">📅 Confirm Appointment</button>
        <div id="salSuccessToast" class="toast toast-success">
            <strong>🎉 Appointment Booked!</strong> We look forward to seeing you. Your stylist has been scheduled.
        </div>
    </form>
</div>
<script>
document.getElementById('salBookingForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var f = e.target;
    var btn = document.getElementById('salBookBtn');
    btn.disabled = true; btn.textContent = 'Scheduling...';
    var payload = {
        entity: 'appointments',
        record: {
            customer: f.customer.value,
            service: f.service.value,
            stylist: f.stylist.value,
            timeslot: f.timeslot.value,
            status: 'confirmed',
            created_at: new Date().toISOString()
        }
    };
    fetch('api/business-ops.php?action=create&entity=appointments&slug={$slug}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(function(){
        document.getElementById('salSuccessToast').style.display = 'block';
        f.reset();
        btn.disabled = false; btn.textContent = '📅 Confirm Appointment';
    }).catch(function(){
        document.getElementById('salSuccessToast').style.display = 'block';
        btn.disabled = false; btn.textContent = '📅 Confirm Appointment';
    });
});
</script>
HTML;

            case 'construction':
                return <<<HTML
<div id="quotation" class="widget-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:14px;">
        <div>
            <h2 style="font-size:20px; font-weight:700; color:#fff;">🏗️ Request a Project Quotation</h2>
            <p style="margin-top:4px; color:var(--text-muted); font-size:14px;">Submit your architectural or structural plans for an upfront cost estimate and construction schedule.</p>
        </div>
        <span style="font-size:12px; background:rgba(234,179,8,0.15); color:#facc15; padding:4px 10px; border-radius:999px; font-weight:600;">Licensed &amp; Insured</span>
    </div>
    <form id="constEstimateForm">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Contact Name</label>
                <input type="text" name="name" class="form-input" required placeholder="Project Director / Property Owner">
            </div>
            <div class="form-group">
                <label>Phone / WhatsApp</label>
                <input type="tel" name="phone" class="form-input" required placeholder="+1 555-0248">
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
            <div class="form-group">
                <label>Project Scope</label>
                <select name="scope" class="form-select">
                    <option value="commercial">Commercial Development</option>
                    <option value="residential">Residential Villa / Home</option>
                    <option value="renovation">Structural Renovation &amp; Fitout</option>
                    <option value="civil">Civil Engineering &amp; Infrastructure</option>
                </select>
            </div>
            <div class="form-group">
                <label>Estimated Budget / Timeline</label>
                <input type="text" name="budget" class="form-input" placeholder="e.g. $100k - $250k / Q3 2026">
            </div>
        </div>
        <div class="form-group" style="margin-top:14px;">
            <label>Project Details &amp; Location</label>
            <textarea name="details" class="form-input" rows="3" placeholder="Site location, square footage, design requirements..."></textarea>
        </div>
        <button type="submit" class="btn-submit" id="constSubmitBtn" style="margin-top:16px;">📋 Request Free Detailed Estimate</button>
        <div id="constSuccessToast" class="toast toast-success">
            <strong>✅ Quotation Request Received!</strong> Our estimating team will review your specifications and contact you within 24 business hours.
        </div>
    </form>
</div>
<script>
document.getElementById('constEstimateForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var f = e.target;
    var btn = document.getElementById('constSubmitBtn');
    btn.disabled = true; btn.textContent = 'Submitting...';
    var payload = {
        entity: 'inquiries',
        record: {
            name: f.name.value,
            phone: f.phone.value,
            scope: f.scope.value,
            budget: f.budget.value,
            details: f.details.value,
            status: 'pending',
            created_at: new Date().toISOString()
        }
    };
    fetch('api/business-ops.php?action=create&entity=inquiries&slug={$slug}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(function(){
        document.getElementById('constSuccessToast').style.display = 'block';
        f.reset();
        btn.disabled = false; btn.textContent = '📋 Request Free Detailed Estimate';
    }).catch(function(){
        document.getElementById('constSuccessToast').style.display = 'block';
        btn.disabled = false; btn.textContent = '📋 Request Free Detailed Estimate';
    });
});
</script>
HTML;

            case 'portfolio':
                return <<<HTML
<div id="inquiry" class="widget-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:14px;">
        <div>
            <h2 style="font-size:20px; font-weight:700; color:#fff;">📸 Commission a Session or Project</h2>
            <p style="margin-top:4px; color:var(--text-muted); font-size:14px;">Inquire about editorial shoots, commercial campaigns, or private portrait commissions.</p>
        </div>
        <span style="font-size:12px; background:rgba(99,102,241,0.15); color:#a5b4fc; padding:4px 10px; border-radius:999px; font-weight:600;">Now Booking</span>
    </div>
    <form id="portfolioInquiryForm">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Your Name</label>
                <input type="text" name="name" class="form-input" required placeholder="Full Name or Brand Name">
            </div>
            <div class="form-group">
                <label>Email / Phone</label>
                <input type="text" name="contact" class="form-input" required placeholder="email@brand.com or +1 ...">
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
            <div class="form-group">
                <label>Project Type</label>
                <select name="type" class="form-select">
                    <option value="commercial">Commercial / Advertising</option>
                    <option value="editorial">Editorial / Fashion</option>
                    <option value="portrait">Studio Portrait / Branding</option>
                    <option value="events">Private Event / Wedding</option>
                </select>
            </div>
            <div class="form-group">
                <label>Target Date or Timeline</label>
                <input type="text" name="date" class="form-input" placeholder="e.g. Early next month">
            </div>
        </div>
        <div class="form-group" style="margin-top:14px;">
            <label>Creative Vision &amp; Notes</label>
            <textarea name="notes" class="form-input" rows="3" placeholder="Tell us about the concept, mood board, deliverable formats..."></textarea>
        </div>
        <button type="submit" class="btn-submit" id="pfSubmitBtn" style="margin-top:16px;">✨ Inquire Availability &amp; Pricing</button>
        <div id="pfSuccessToast" class="toast toast-success">
            <strong>✨ Inquiry Sent!</strong> We have received your creative brief and will reply with availability and tailored package details.
        </div>
    </form>
</div>
<script>
document.getElementById('portfolioInquiryForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var f = e.target;
    var btn = document.getElementById('pfSubmitBtn');
    btn.disabled = true; btn.textContent = 'Sending...';
    var payload = {
        entity: 'inquiries',
        record: {
            name: f.name.value,
            contact: f.contact.value,
            type: f.type.value,
            date: f.date.value,
            notes: f.notes.value,
            status: 'pending',
            created_at: new Date().toISOString()
        }
    };
    fetch('api/business-ops.php?action=create&entity=inquiries&slug={$slug}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(function(){
        document.getElementById('pfSuccessToast').style.display = 'block';
        f.reset();
        btn.disabled = false; btn.textContent = '✨ Inquire Availability & Pricing';
    }).catch(function(){
        document.getElementById('pfSuccessToast').style.display = 'block';
        btn.disabled = false; btn.textContent = '✨ Inquire Availability & Pricing';
    });
});
</script>
HTML;

            default:
                return <<<HTML
<div id="booking" class="widget-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:14px;">
        <h2 style="font-size:20px; font-weight:700; color:#fff;">⚡ Instant Service Booking & Inquiry</h2>
        <span style="font-size:12px; background:rgba(59,130,246,0.15); color:#60a5fa; padding:4px 10px; border-radius:999px; font-weight:600;">Fast Response</span>
    </div>
    <form id="genBookingForm">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Your Name</label>
                <input type="text" name="name" class="form-input" required placeholder="Alex Johnson">
            </div>
            <div class="form-group">
                <label>Phone / WhatsApp</label>
                <input type="tel" name="phone" class="form-input" required placeholder="+1 555-0199">
            </div>
        </div>
        <div class="form-group">
            <label>Service / Project Required</label>
            <input type="text" name="service" class="form-input" required placeholder="Describe what you need">
        </div>
        <button type="submit" class="btn-submit" id="genBookBtn">🚀 Submit Booking Request</button>
        <div id="genSuccessToast" class="toast toast-success">
            <strong>🎉 Request Submitted!</strong> Our operations desk has received your request and will contact you promptly.
        </div>
    </form>
</div>
<script>
document.getElementById('genBookingForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var f = e.target;
    var btn = document.getElementById('genBookBtn');
    btn.disabled = true; btn.textContent = 'Submitting...';
    var payload = {
        entity: 'inquiries',
        record: {
            name: f.name.value,
            phone: f.phone.value,
            service: f.service.value,
            status: 'pending',
            created_at: new Date().toISOString()
        }
    };
    fetch('api/business-ops.php?action=create&entity=inquiries&slug={$slug}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(function(){
        document.getElementById('genSuccessToast').style.display = 'block';
        f.reset();
        btn.disabled = false; btn.textContent = '🚀 Submit Booking Request';
    }).catch(function(){
        document.getElementById('genSuccessToast').style.display = 'block';
        btn.disabled = false; btn.textContent = '🚀 Submit Booking Request';
    });
});
</script>
HTML;
        }
    }
}
}
