<?php
// tests/test-customer-portal-support.php — run: php tests/test-customer-portal-support.php
// Customer portal support tickets must live in storage/submissions with the
// exact shape the platform-admin inbox already reads, enforce ownership, and
// survive create/reply/close/reopen cycles. Uses an isolated temp storage dir.
if (php_sapi_name() !== 'cli') exit('CLI only');

$tmp = sys_get_temp_dir() . '/wc_portal_support_' . bin2hex(random_bytes(4));
@mkdir($tmp, 0777, true);
define('STORAGE_DIR', $tmp);
require_once __DIR__ . '/../includes/CustomerSupport.php';

$fail = 0;
$pass = 0;
function ok($cond, $label) {
    global $fail, $pass;
    if ($cond) { $pass++; echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label\n"; }
}

$email = 'Support.Test@Example.com';
$created = support_ticket_create($email, 'Test User', 'Cannot edit my homepage', 'I need help changing the hero text on my homepage.', 'WBL-2026-ABC123');
ok(!empty($created['success']), 'create succeeds');
$id = $created['ticket']['id'] ?? '';
ok(support_ticket_id_valid($id), 'ticket id format');
ok($id !== '' && is_file($tmp . '/submissions/' . $id . '.json'), 'ticket file exists');

$dup = support_ticket_create($email, 'Test User', 'Cannot edit my homepage', 'I need help changing the hero text on my homepage.', 'WBL-2026-ABC123');
ok(!empty($dup['success']) && !empty($dup['duplicate']) && ($dup['ticket']['id'] ?? '') === $id, 'duplicate submission suppressed');

$list = support_tickets_for_email('support.test@example.com');
ok(count($list) === 1 && $list[0]['id'] === $id, 'list by email is case-insensitive');
ok(count(support_tickets_for_email('other@example.com')) === 0, 'other email sees no tickets');

$reply = support_ticket_reply($id, 'support.test@example.com', 'Test User', 'Adding that the issue happens on mobile too.');
ok(!empty($reply['success']), 'owner reply succeeds');
$after = support_read_ticket($id);
ok(count($after['replies']) === 1 && $after['replies'][0]['role'] === 'customer', 'reply persisted with role');

$bad = support_ticket_reply($id, 'attacker@example.com', 'Attacker', 'Give me access please.');
ok(empty($bad['success']), 'non-owner reply rejected');
ok(support_read_ticket('../../config') === null, 'path traversal rejected');

$closed = support_ticket_set_status($id, 'support.test@example.com', 'closed');
ok(!empty($closed['success']), 'owner close succeeds');
ok((support_read_ticket($id)['status'] ?? '') === 'closed', 'status persisted closed');
ok(empty(support_ticket_reply($id, 'support.test@example.com', 'Test User', 'Trying to reply after close.')['success']), 'reply to closed rejected');
ok(!empty(support_ticket_set_status($id, 'support.test@example.com', 'open')['success']), 'owner reopen succeeds');

$stored = json_decode((string)file_get_contents($tmp . '/submissions/' . $id . '.json'), true);
ok(isset($stored['id'], $stored['name'], $stored['email'], $stored['subject'], $stored['message'], $stored['status'], $stored['created_at'], $stored['replies']), 'platform-admin compatible keys');
ok(($stored['source'] ?? '') === 'customer_portal' && ($stored['order_id'] ?? '') === 'WBL-2026-ABC123', 'portal metadata preserved');

ok(empty(support_ticket_create('not-an-email', 'X', 'Hi', 'This message is long enough.')['success']), 'invalid email rejected');
ok(empty(support_ticket_create($email, 'X', 'Hi', 'short')['success']), 'short message rejected');

foreach (glob($tmp . '/submissions/*') ?: [] as $f) @unlink($f);
@rmdir($tmp . '/submissions');
@rmdir($tmp);

echo $fail === 0 ? "ALL SUPPORT TESTS PASSED ($pass checks)\n" : "$fail TEST(S) FAILED\n";
exit($fail === 0 ? 0 : 1);
