<?php
/**
 * get-plans.php  — Public AJAX endpoint: returns active subscription plans
 * Called by publish.php JS to load dynamic pricing
 * GET /get-plans.php  → JSON
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$plansFile = __DIR__ . '/storage/plans.json';

$defaultPlans = [
    'starter' => [
        'id' => 'starter', 'label' => 'Starter', 'price' => 9.00,
        'period' => 'month', 'popular' => false,
        'features' => [
            ['text' => '1 Website',      'included' => true],
            ['text' => '5 GB Bandwidth', 'included' => true],
            ['text' => 'Basic Support',  'included' => true],
            ['text' => 'SSL Included',   'included' => true],
            ['text' => 'Admin Panel',    'included' => false],
            ['text' => 'Priority Support','included' => false],
        ],
    ],
    'pro' => [
        'id' => 'pro', 'label' => 'Pro', 'price' => 19.00,
        'period' => 'month', 'popular' => true,
        'features' => [
            ['text' => '3 Websites',       'included' => true],
            ['text' => '50 GB Bandwidth',  'included' => true],
            ['text' => 'Priority Support', 'included' => true],
            ['text' => 'SSL + Backup',     'included' => true],
            ['text' => 'Admin Panel',      'included' => true],
            ['text' => 'AI Features',      'included' => false],
        ],
    ],
    'business' => [
        'id' => 'business', 'label' => 'Business', 'price' => 49.00,
        'period' => 'month', 'popular' => false,
        'features' => [
            ['text' => 'Unlimited Websites', 'included' => true],
            ['text' => '500 GB Bandwidth',   'included' => true],
            ['text' => '24/7 Support',        'included' => true],
            ['text' => 'SSL + Daily Backup',  'included' => true],
            ['text' => 'Admin Panel',         'included' => true],
            ['text' => 'AI Features',         'included' => true],
        ],
    ],
];

$plans = file_exists($plansFile)
    ? (json_decode(file_get_contents($plansFile), true) ?: $defaultPlans)
    : $defaultPlans;

echo json_encode(['success' => true, 'plans' => array_values($plans)]);
