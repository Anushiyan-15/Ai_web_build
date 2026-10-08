<?php
// ═══════════════════════════════════════════════════════════════
//  api/reviews.php — Public review feed (approved only, batched)
//
//  GET ?action=list&limit=5&offset=0  → next batch of approved reviews
//  GET ?action=summary                → {average, count} of approved
//  No auth required. Nothing unapproved ever leaves this endpoint.
// ═══════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once dirname(__DIR__) . '/includes/reviews.php';

$action = trim($_GET['action'] ?? 'list');

if ($action === 'summary') {
    $s = reviews_rating_summary();
    echo json_encode([
        'success' => true,
        'average' => (float)$s['average'],
        'count'   => (int)$s['count'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Default: batched list, newest first.
$limit  = max(1, min(20, (int)($_GET['limit'] ?? REVIEW_PAGE_SIZE)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$rows  = reviews_public($limit, $offset);
$total = reviews_public_count();

$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id'      => (int)($r['id'] ?? 0),
        'name'    => (string)($r['customer_name'] ?? 'Customer'),
        'role'    => (string)($r['customer_role'] ?? ''),
        'rating'  => max(1, min(5, (int)($r['rating'] ?? 5))),
        'title'   => (string)($r['review_title'] ?? ''),
        'message' => (string)($r['review_message'] ?? ''),
        'date'    => !empty($r['created_at']) ? date('M Y', strtotime($r['created_at'])) : '',
        'avatar'  => review_avatar_url($r['customer_email'] ?? ''),
        'html'    => review_card_html($r),
    ];
}

echo json_encode([
    'success'  => true,
    'items'    => $items,
    'total'    => $total,
    'offset'   => $offset,
    'limit'    => $limit,
    'has_more' => ($offset + count($items)) < $total,
], JSON_UNESCAPED_UNICODE);
