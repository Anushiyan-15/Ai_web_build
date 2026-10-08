<?php
// ═══════════════════════════════════════════════════════════════
//  includes/reviews.php — Customer Review & Rating System
//
//  Storage: Supabase/Postgres (primary) → local MySQL (fallback) →
//  storage/reviews.json (file fallback), same conventions as db.php.
//  Ownership is keyed on customer_email (canonical identity app-wide).
//  Only status='approved' rows are ever exposed publicly.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/db.php';

if (!defined('REVIEW_STATUS_PENDING'))  define('REVIEW_STATUS_PENDING', 'pending');
if (!defined('REVIEW_STATUS_APPROVED')) define('REVIEW_STATUS_APPROVED', 'approved');
if (!defined('REVIEW_STATUS_REJECTED')) define('REVIEW_STATUS_REJECTED', 'rejected');
if (!defined('REVIEW_MIN_MESSAGE'))     define('REVIEW_MIN_MESSAGE', 10);
if (!defined('REVIEW_MAX_MESSAGE'))     define('REVIEW_MAX_MESSAGE', 2000);
if (!defined('REVIEW_MAX_TITLE'))       define('REVIEW_MAX_TITLE', 120);
if (!defined('REVIEW_PAGE_SIZE'))       define('REVIEW_PAGE_SIZE', 5);

function review_valid_statuses(): array {
    return [REVIEW_STATUS_PENDING, REVIEW_STATUS_APPROVED, REVIEW_STATUS_REJECTED];
}

/* ── Storage bootstrap ─────────────────────────────────────── */

function reviews_storage_file(): string {
    $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return rtrim($dir, '/\\') . '/reviews.json';
}

function reviews_table_ddl(string $driver): string {
    if ($driver === 'pgsql') {
        return "CREATE TABLE IF NOT EXISTS reviews (
            id BIGSERIAL PRIMARY KEY,
            customer_id VARCHAR(64) NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_role VARCHAR(120) NOT NULL DEFAULT '',
            rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review_title VARCHAR(255) NOT NULL DEFAULT '',
            review_message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
            updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
        );
        CREATE INDEX IF NOT EXISTS idx_reviews_email ON reviews(customer_email);
        CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status);";
    }
    return "CREATE TABLE IF NOT EXISTS `reviews` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `customer_id` VARCHAR(64) NULL,
      `customer_email` VARCHAR(255) NOT NULL,
      `customer_name` VARCHAR(255) NOT NULL DEFAULT '',
      `customer_role` VARCHAR(120) NOT NULL DEFAULT '',
      `rating` TINYINT NOT NULL,
      `review_title` VARCHAR(255) NOT NULL DEFAULT '',
      `review_message` TEXT NOT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
      `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_reviews_email` (`customer_email`),
      INDEX `idx_reviews_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
}

/**
 * Ensure the reviews table exists (idempotent). Seeds the 3 legacy
 * landing-page testimonials as approved so the section is never empty.
 */
function reviews_ensure_table(): bool {
    $db = getDb();
    if (!$db) return false;
    try {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            // pgsql PDO::exec cannot run multi-statement strings reliably —
            // split on the statement boundary.
            $db->exec("CREATE TABLE IF NOT EXISTS reviews (
                id BIGSERIAL PRIMARY KEY,
                customer_id VARCHAR(64) NULL,
                customer_email VARCHAR(255) NOT NULL,
                customer_name VARCHAR(255) NOT NULL DEFAULT '',
                customer_role VARCHAR(120) NOT NULL DEFAULT '',
                rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                review_title VARCHAR(255) NOT NULL DEFAULT '',
                review_message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
                updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
            )");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_reviews_email ON reviews(customer_email)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status)");
        } else {
            $db->exec(reviews_table_ddl('mysql'));
        }
        // Seed legacy testimonials once (keeps landing populated).
        $n = (int)$db->query("SELECT COUNT(*) AS c FROM reviews")->fetch()['c'];
        if ($n === 0) {
            $seed = [
                ['seed-sarah@webcraft.ai', 'Sarah M.', 'Restaurant Owner', 5, 'Live in 2 minutes', 'Generated my entire restaurant website in under 2 minutes. The code was clean and I only had to change the phone number!'],
                ['seed-james@webcraft.ai', 'James K.', 'SaaS Founder', 5, 'Brilliant AI', 'I used this to build a landing page for my startup. The AI even added animations I didn\'t ask for. Absolutely brilliant.'],
                ['seed-priya@webcraft.ai', 'Priya R.', 'Freelance Designer', 5, 'Saved me 8 hours', 'My client needed a portfolio site urgently. I used WebCraft AI to generate the base and customized it live. Saved me 8 hours.'],
            ];
            $ins = $db->prepare("INSERT INTO reviews (customer_id, customer_email, customer_name, customer_role, rating, review_title, review_message, status) VALUES ('seed', :email, :name, :role, :rating, :title, :msg, 'approved')");
            foreach ($seed as $s) {
                $ins->execute([':email' => $s[0], ':name' => $s[1], ':role' => $s[2], ':rating' => $s[3], ':title' => $s[4], ':msg' => $s[5]]);
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log('reviews_ensure_table error: ' . $e->getMessage());
        return false;
    }
}

/* ── File fallback (same row shape) ────────────────────────── */

function reviews_load_file(): array {
    $f = reviews_storage_file();
    if (!is_file($f)) return [];
    try {
        $a = json_decode(@file_get_contents($f), true);
        return is_array($a) ? array_values($a) : [];
    } catch (Throwable $e) { return []; }
}

function reviews_save_file(array $rows): void {
    @file_put_contents(reviews_storage_file(), json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function reviews_seed_file_rows(): array {
    $now = date('Y-m-d H:i:s');
    return [
        ['id' => 1, 'customer_id' => 'seed', 'customer_email' => 'seed-sarah@webcraft.ai', 'customer_name' => 'Sarah M.', 'customer_role' => 'Restaurant Owner', 'rating' => 5, 'review_title' => 'Live in 2 minutes', 'review_message' => 'Generated my entire restaurant website in under 2 minutes. The code was clean and I only had to change the phone number!', 'status' => 'approved', 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'customer_id' => 'seed', 'customer_email' => 'seed-james@webcraft.ai', 'customer_name' => 'James K.', 'customer_role' => 'SaaS Founder', 'rating' => 5, 'review_title' => 'Brilliant AI', 'review_message' => 'I used this to build a landing page for my startup. The AI even added animations I didn\'t ask for. Absolutely brilliant.', 'status' => 'approved', 'created_at' => $now, 'updated_at' => $now],
        ['id' => 3, 'customer_id' => 'seed', 'customer_email' => 'seed-priya@webcraft.ai', 'customer_name' => 'Priya R.', 'customer_role' => 'Freelance Designer', 'rating' => 5, 'review_title' => 'Saved me 8 hours', 'review_message' => 'My client needed a portfolio site urgently. I used WebCraft AI to generate the base and customized it live. Saved me 8 hours.', 'status' => 'approved', 'created_at' => $now, 'updated_at' => $now],
    ];
}

/* ── Validation & sanitizing ───────────────────────────────── */

function review_validate($rating, $title, $message): string {
    $r = (int)$rating;
    if ($r < 1 || $r > 5) return 'Please select a star rating between 1 and 5.';
    $msg = trim(strip_tags((string)$message));
    if (mb_strlen($msg) < REVIEW_MIN_MESSAGE) return 'Please write at least ' . REVIEW_MIN_MESSAGE . ' characters for your review.';
    if (mb_strlen($msg) > REVIEW_MAX_MESSAGE) return 'Review is too long (max ' . REVIEW_MAX_MESSAGE . ' characters).';
    if (mb_strlen(trim((string)$title)) > REVIEW_MAX_TITLE) return 'Title is too long (max ' . REVIEW_MAX_TITLE . ' characters).';
    return '';
}

function review_clean_text(string $s, int $max): string {
    $s = trim(strip_tags($s));
    $s = preg_replace('/\s+/', ' ', $s);
    return mb_substr($s, 0, $max);
}

/* ── Customer writes (ownership enforced in SQL) ───────────── */

function review_create(string $email, string $name, $rating, string $title, string $message, string $role = '', $customerId = null): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'Please sign in first.'];
    $err = review_validate($rating, $title, $message);
    if ($err !== '') return ['success' => false, 'error' => $err];

    $name    = review_clean_text($name, 120);
    $title   = review_clean_text($title, REVIEW_MAX_TITLE);
    $message = review_clean_text($message, REVIEW_MAX_MESSAGE);
    $role    = review_clean_text($role, 120);
    if ($name === '') $name = 'Customer';
    $now = date('Y-m-d H:i:s');

    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            // Block exact-duplicate submissions from the same account.
            $dup = $db->prepare("SELECT id FROM reviews WHERE customer_email = :email AND review_message = :msg LIMIT 1");
            $dup->execute([':email' => $email, ':msg' => $message]);
            if ($dup->fetch()) return ['success' => false, 'error' => 'You have already submitted this review.'];
            $stmt = $db->prepare("INSERT INTO reviews (customer_id, customer_email, customer_name, customer_role, rating, review_title, review_message, status, created_at, updated_at) VALUES (:cid, :email, :name, :role, :rating, :title, :msg, 'pending', NOW(), NOW())");
            $stmt->execute([':cid' => $customerId, ':email' => $email, ':name' => $name, ':role' => $role, ':rating' => (int)$rating, ':title' => $title, ':msg' => $message]);
            return ['success' => true, 'id' => $db->lastInsertId()];
        } catch (Throwable $e) {
            error_log('review_create DB error: ' . $e->getMessage());
        }
    }
    // File fallback
    $rows = reviews_load_file();
    if (!$rows) $rows = reviews_seed_file_rows();
    foreach ($rows as $r) {
        if (strtolower($r['customer_email'] ?? '') === $email && trim($r['review_message'] ?? '') === $message) {
            return ['success' => false, 'error' => 'You have already submitted this review.'];
        }
    }
    $id = 1;
    foreach ($rows as $r) $id = max($id, (int)($r['id'] ?? 0) + 1);
    $rows[] = ['id' => $id, 'customer_id' => $customerId, 'customer_email' => $email, 'customer_name' => $name, 'customer_role' => $role, 'rating' => (int)$rating, 'review_title' => $title, 'review_message' => $message, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now];
    reviews_save_file($rows);
    return ['success' => true, 'id' => $id];
}

function reviews_for_customer(string $email): array {
    $email = strtolower(trim($email));
    if (!$email) return [];
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $stmt = $db->prepare("SELECT * FROM reviews WHERE customer_email = :email ORDER BY created_at DESC, id DESC");
            $stmt->execute([':email' => $email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('reviews_for_customer DB error: ' . $e->getMessage());
        }
    }
    $out = [];
    foreach (reviews_load_file() as $r) {
        if (strtolower($r['customer_email'] ?? '') === $email) $out[] = $r;
    }
    usort($out, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? '') ?: ((int)($b['id'] ?? 0) - (int)($a['id'] ?? 0)));
    return $out;
}

function review_update_customer($id, string $email, $rating, string $title, string $message, string $role = ''): array {
    $email = strtolower(trim($email));
    if (!$email) return ['success' => false, 'error' => 'Please sign in first.'];
    $err = review_validate($rating, $title, $message);
    if ($err !== '') return ['success' => false, 'error' => $err];
    $title   = review_clean_text($title, REVIEW_MAX_TITLE);
    $message = review_clean_text($message, REVIEW_MAX_MESSAGE);
    $role    = review_clean_text($role, 120);

    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            // Ownership enforced in WHERE — another customer's row never matches.
            $stmt = $db->prepare("UPDATE reviews SET rating = :rating, review_title = :title, review_message = :msg, customer_role = :role, status = 'pending', updated_at = NOW() WHERE id = :id AND customer_email = :email");
            $stmt->execute([':rating' => (int)$rating, ':title' => $title, ':msg' => $message, ':role' => $role, ':id' => (int)$id, ':email' => $email]);
            if ($stmt->rowCount() === 0) return ['success' => false, 'error' => 'Review not found or not yours.'];
            return ['success' => true];
        } catch (Throwable $e) {
            error_log('review_update_customer DB error: ' . $e->getMessage());
        }
    }
    $rows = reviews_load_file();
    foreach ($rows as &$r) {
        if ((int)($r['id'] ?? 0) === (int)$id && strtolower($r['customer_email'] ?? '') === $email) {
            $r['rating'] = (int)$rating; $r['review_title'] = $title;
            $r['review_message'] = $message; $r['customer_role'] = $role;
            $r['status'] = 'pending'; $r['updated_at'] = date('Y-m-d H:i:s');
            reviews_save_file($rows);
            return ['success' => true];
        }
    }
    return ['success' => false, 'error' => 'Review not found or not yours.'];
}

function review_delete_customer($id, string $email): array {
    $email = strtolower(trim($email));
    if (!$email) return ['success' => false, 'error' => 'Please sign in first.'];
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = :id AND customer_email = :email");
            $stmt->execute([':id' => (int)$id, ':email' => $email]);
            if ($stmt->rowCount() === 0) return ['success' => false, 'error' => 'Review not found or not yours.'];
            return ['success' => true];
        } catch (Throwable $e) {
            error_log('review_delete_customer DB error: ' . $e->getMessage());
        }
    }
    $rows = reviews_load_file();
    $kept = [];
    $found = false;
    foreach ($rows as $r) {
        if ((int)($r['id'] ?? 0) === (int)$id && strtolower($r['customer_email'] ?? '') === $email) { $found = true; continue; }
        $kept[] = $r;
    }
    if (!$found) return ['success' => false, 'error' => 'Review not found or not yours.'];
    reviews_save_file($kept);
    return ['success' => true];
}

/* ── Public reads (approved only, newest first) ────────────── */

function reviews_public(int $limit = 5, int $offset = 0): array {
    $limit  = max(1, min(20, $limit));
    $offset = max(0, $offset);
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lim = (int)$limit; $off = (int)$offset;
            // LIMIT/OFFSET are ints validated above — safe to inline (PDO forbids binding them on some drivers).
            $stmt = $db->query("SELECT id, customer_email, customer_name, customer_role, rating, review_title, review_message, created_at FROM reviews WHERE status = 'approved' ORDER BY created_at DESC, id DESC LIMIT $lim OFFSET $off");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('reviews_public DB error: ' . $e->getMessage());
        }
    }
    $rows = array_values(array_filter(reviews_load_file(), fn($r) => ($r['status'] ?? '') === 'approved'));
    usort($rows, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? '') ?: ((int)($b['id'] ?? 0) - (int)($a['id'] ?? 0)));
    return array_slice($rows, $offset, $limit);
}

function reviews_public_count(): int {
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            return (int)$db->query("SELECT COUNT(*) AS c FROM reviews WHERE status = 'approved'")->fetch()['c'];
        } catch (Throwable $e) {
            error_log('reviews_public_count DB error: ' . $e->getMessage());
        }
    }
    $n = 0;
    foreach (reviews_load_file() as $r) if (($r['status'] ?? '') === 'approved') $n++;
    return $n;
}

function reviews_rating_summary(): array {
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $row = $db->query("SELECT COUNT(*) AS c, AVG(rating) AS a FROM reviews WHERE status = 'approved'")->fetch();
            $c = (int)($row['c'] ?? 0);
            return ['count' => $c, 'average' => $c ? round((float)$row['a'], 1) : 0.0];
        } catch (Throwable $e) {
            error_log('reviews_rating_summary DB error: ' . $e->getMessage());
        }
    }
    $rows = array_filter(reviews_load_file(), fn($r) => ($r['status'] ?? '') === 'approved');
    $c = count($rows);
    if (!$c) return ['count' => 0, 'average' => 0.0];
    $sum = 0;
    foreach ($rows as $r) $sum += (int)($r['rating'] ?? 0);
    return ['count' => $c, 'average' => round($sum / $c, 1)];
}

/* ── Admin (no ownership filter — platform admins only) ────── */

function reviews_admin_list(string $search = '', string $status = '', $rating = 0, int $limit = 100, int $offset = 0): array {
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $where = [];
            $args = [];
            if ($search !== '') { $where[] = "(customer_name ILIKE :q1 OR customer_email ILIKE :q2 OR review_title ILIKE :q3 OR review_message ILIKE :q4)"; $args[':q1'] = $args[':q2'] = $args[':q3'] = $args[':q4'] = '%' . $search . '%'; }
            if ($status !== '' && in_array($status, review_valid_statuses(), true)) { $where[] = "status = :status"; $args[':status'] = $status; }
            $rating = (int)$rating;
            if ($rating >= 1 && $rating <= 5) { $where[] = "rating = :rating"; $args[':rating'] = $rating; }
            // NOTE:ILIKE is Postgres-only; MySQL path uses LIKE (case-insensitive by collation).
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                $where = array_map(fn($w) => str_replace('ILIKE', 'LIKE', $w), $where);
            }
            $sql = "SELECT * FROM reviews" . ($where ? " WHERE " . implode(' AND ', $where) : "") . " ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(500, (int)$limit)) . " OFFSET " . max(0, (int)$offset);
            $stmt = $db->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('reviews_admin_list DB error: ' . $e->getMessage());
        }
    }
    $rows = reviews_load_file();
    if ($search !== '') {
        $q = strtolower($search);
        $rows = array_values(array_filter($rows, fn($r) => strpos(strtolower(($r['customer_name'] ?? '') . ' ' . ($r['customer_email'] ?? '') . ' ' . ($r['review_title'] ?? '') . ' ' . ($r['review_message'] ?? '')), $q) !== false));
    }
    if ($status !== '' && in_array($status, review_valid_statuses(), true)) $rows = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === $status));
    if ((int)$rating >= 1 && (int)$rating <= 5) $rows = array_values(array_filter($rows, fn($r) => (int)($r['rating'] ?? 0) === (int)$rating));
    usort($rows, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? '') ?: ((int)($b['id'] ?? 0) - (int)($a['id'] ?? 0)));
    return array_slice($rows, max(0, (int)$offset), max(1, min(500, (int)$limit)));
}

function reviews_admin_count(string $search = '', string $status = ''): int {
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $where = [];
            $args = [];
            if ($search !== '') { $where[] = "(customer_name ILIKE :q1 OR customer_email ILIKE :q2 OR review_title ILIKE :q3 OR review_message ILIKE :q4)"; $args[':q1'] = $args[':q2'] = $args[':q3'] = $args[':q4'] = '%' . $search . '%'; }
            if ($status !== '' && in_array($status, review_valid_statuses(), true)) { $where[] = "status = :status"; $args[':status'] = $status; }
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                $where = array_map(fn($w) => str_replace('ILIKE', 'LIKE', $w), $where);
            }
            $stmt = $db->prepare("SELECT COUNT(*) AS c FROM reviews" . ($where ? " WHERE " . implode(' AND ', $where) : ""));
            $stmt->execute($args);
            return (int)$stmt->fetch()['c'];
        } catch (Throwable $e) {
            error_log('reviews_admin_count DB error: ' . $e->getMessage());
        }
    }
    return count(reviews_admin_list($search, $status, 0, 500, 0));
}

function review_admin_set_status($id, string $status): array {
    if (!in_array($status, review_valid_statuses(), true)) return ['success' => false, 'error' => 'Invalid status.'];
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $stmt = $db->prepare("UPDATE reviews SET status = :status, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => (int)$id]);
            if ($stmt->rowCount() === 0) return ['success' => false, 'error' => 'Review not found.'];
            return ['success' => true];
        } catch (Throwable $e) {
            error_log('review_admin_set_status DB error: ' . $e->getMessage());
        }
    }
    $rows = reviews_load_file();
    foreach ($rows as &$r) {
        if ((int)($r['id'] ?? 0) === (int)$id) {
            $r['status'] = $status; $r['updated_at'] = date('Y-m-d H:i:s');
            reviews_save_file($rows);
            return ['success' => true];
        }
    }
    return ['success' => false, 'error' => 'Review not found.'];
}

function review_admin_update($id, $rating, string $title, string $message, string $role = ''): array {
    $err = review_validate($rating, $title, $message);
    if ($err !== '') return ['success' => false, 'error' => $err];
    $title   = review_clean_text($title, REVIEW_MAX_TITLE);
    $message = review_clean_text($message, REVIEW_MAX_MESSAGE);
    $role    = review_clean_text($role, 120);
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $stmt = $db->prepare("UPDATE reviews SET rating = :rating, review_title = :title, review_message = :msg, customer_role = :role, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':rating' => (int)$rating, ':title' => $title, ':msg' => $message, ':role' => $role, ':id' => (int)$id]);
            if ($stmt->rowCount() === 0) return ['success' => false, 'error' => 'Review not found.'];
            return ['success' => true];
        } catch (Throwable $e) {
            error_log('review_admin_update DB error: ' . $e->getMessage());
        }
    }
    $rows = reviews_load_file();
    foreach ($rows as &$r) {
        if ((int)($r['id'] ?? 0) === (int)$id) {
            $r['rating'] = (int)$rating; $r['review_title'] = $title;
            $r['review_message'] = $message; $r['customer_role'] = $role;
            $r['updated_at'] = date('Y-m-d H:i:s');
            reviews_save_file($rows);
            return ['success' => true];
        }
    }
    return ['success' => false, 'error' => 'Review not found.'];
}

function review_admin_delete($id): array {
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = :id");
            $stmt->execute([':id' => (int)$id]);
            if ($stmt->rowCount() === 0) return ['success' => false, 'error' => 'Review not found.'];
            return ['success' => true];
        } catch (Throwable $e) {
            error_log('review_admin_delete DB error: ' . $e->getMessage());
        }
    }
    $rows = reviews_load_file();
    $kept = [];
    $found = false;
    foreach ($rows as $r) {
        if ((int)($r['id'] ?? 0) === (int)$id) { $found = true; continue; }
        $kept[] = $r;
    }
    if (!$found) return ['success' => false, 'error' => 'Review not found.'];
    reviews_save_file($kept);
    return ['success' => true];
}

function reviews_status_counts(): array {
    $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
    $db = getDb();
    if ($db && reviews_ensure_table()) {
        try {
            foreach ($db->query("SELECT status, COUNT(*) AS c FROM reviews GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $s = $row['status'] ?? '';
                if (isset($out[$s])) $out[$s] = (int)$row['c'];
            }
            $out['total'] = $out['pending'] + $out['approved'] + $out['rejected'];
            return $out;
        } catch (Throwable $e) {
            error_log('reviews_status_counts DB error: ' . $e->getMessage());
        }
    }
    foreach (reviews_load_file() as $r) {
        $s = $r['status'] ?? '';
        if (isset($out[$s])) $out[$s]++;
    }
    $out['total'] = $out['pending'] + $out['approved'] + $out['rejected'];
    return $out;
}

/* ── Presentation helpers (shared card renderer) ───────────── */

function review_avatar_color(string $seed): string {
    $palette = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'];
    $h = 0;
    foreach (str_split(strtolower(trim($seed))) as $ch) $h = ($h * 31 + ord($ch)) % 997;
    return $palette[$h % count($palette)];
}

/**
 * Profile photo URL for a reviewer ('' when none).
 * Result cached per request — landing renders up to N cards at once.
 */
function review_avatar_url(string $email): string {
    static $cache = [];
    $email = strtolower(trim($email));
    if ($email === '') return '';
    if (array_key_exists($email, $cache)) return $cache[$email];
    $url = '';
    try {
        $row = findCustomerByEmail($email);
        if ($row) $url = customerAvatarUrl($row);
    } catch (Throwable $e) {}
    if ($url === '') {
        // No customer row (legacy order-password accounts) but a photo file
        // may still exist — check the avatars dir directly.
        try {
            $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/avatars';
            foreach (glob($dir . '/' . md5($email) . '.*') ?: [] as $f) {
                if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) { $url = 'file'; break; }
            }
        } catch (Throwable $e) {}
    }
    // Host-relative URL: works on localhost, 127.0.0.1, LAN IP or domain —
    // an absolute SITE_URL host would break the image on alternate hosts.
    if ($url !== '') {
        $prefix = '';
        if (defined('SITE_URL')) {
            try { $prefix = rtrim((string)parse_url(SITE_URL, PHP_URL_PATH), '/'); } catch (Throwable $e) {}
        }
        $url = $prefix . '/avatar.php?u=' . md5($email);
    }
    $cache[$email] = $url;
    return $url;
}

/**
 * Does this account have a verifiable password? (delete-gate)
 * Customers row hash OR legacy order password counts. Social/seed
 * accounts with no password return false → session auth suffices.
 */
function review_delete_needs_password(string $email): bool {
    $email = strtolower(trim($email));
    if ($email === '') return false;
    try {
        $row = findCustomerByEmail($email);
        if ($row && !empty($row['password_hash'])) return true;
    } catch (Throwable $e) {}
    try {
        foreach (getCustomerOrdersFromDatabase($email) as $o) {
            if (!empty($o['admin_password_hash']) || !empty($o['admin_password_plain'])) return true;
        }
    } catch (Throwable $e) {}
    return false;
}

/**
 * Verify the account password before a destructive action.
 * Returns true when verified OR when no password is set (nothing to check).
 * Never accepts master/backdoor passwords — only the account's own.
 */
function review_verify_delete_password(string $email, string $password): bool {
    $email = strtolower(trim($email));
    if ($email === '') return false;
    $hasPw = false;
    try {
        $row = findCustomerByEmail($email);
        if ($row && !empty($row['password_hash'])) {
            $hasPw = true;
            if ($password !== '' && password_verify($password, $row['password_hash'])) return true;
        }
    } catch (Throwable $e) {}
    if (!$hasPw) {
        try {
            foreach (getCustomerOrdersFromDatabase($email) as $o) {
                $hash = $o['admin_password_hash'] ?? '';
                $plain = $o['admin_password_plain'] ?? '';
                if ($hash !== '' || $plain !== '') $hasPw = true;
                if ($password !== '' && (($hash !== '' && password_verify($password, $hash)) || ($plain !== '' && $plain === $password))) return true;
            }
        } catch (Throwable $e) {}
    }
    // No password anywhere → nothing to verify, session login suffices.
    return !$hasPw;
}

function review_stars_html($rating, string $cls = 'testi-stars'): string {
    $r = max(0, min(5, (int)$rating));
    return '<div class="' . $cls . '" aria-label="' . $r . ' out of 5 stars">' . str_repeat('★', $r) . str_repeat('☆', 5 - $r) . '</div>';
}

/** Average-rating visual: gold stars clipped to the exact average. */
function review_avg_stars_html(float $avg): string {
    $pct = max(0, min(100, ($avg / 5) * 100));
    return '<span class="avg-stars" aria-label="' . htmlspecialchars(number_format($avg, 1)) . ' out of 5">'
        . '<span class="avg-stars-bg">★★★★★</span>'
        . '<span class="avg-stars-fg" style="width:' . round($pct, 1) . '%;">★★★★★</span>'
        . '</span>';
}

function review_card_html(array $r): string {
    $name  = trim($r['customer_name'] ?? 'Customer') ?: 'Customer';
    $role  = trim($r['customer_role'] ?? '');
    $title = trim($r['review_title'] ?? '');
    $msg   = trim($r['review_message'] ?? '');
    $date  = '';
    if (!empty($r['created_at'])) {
        try { $date = date('M Y', strtotime($r['created_at'])); } catch (Throwable $e) { $date = ''; }
    }
    $initial = strtoupper(mb_substr($name, 0, 1));
    $color = review_avatar_color($r['customer_email'] ?? $name);
    // Real profile photo when the customer uploaded one, layered over the
    // initials — if the file ever goes missing the img removes itself and
    // the initials show through, exactly as before.
    $avatarUrl = review_avatar_url($r['customer_email'] ?? '');
    $avatarHtml = '<div class="testi-avatar" style="background:' . $color . '">' . htmlspecialchars($initial)
        . ($avatarUrl !== '' ? '<img class="testi-avatar-img" src="' . htmlspecialchars($avatarUrl) . '" alt="" loading="lazy" onerror="this.remove()">' : '')
        . '</div>';
    return '<div class="testi-card">'
        . review_stars_html($r['rating'] ?? 5)
        . ($title !== '' ? '<div class="testi-title">' . htmlspecialchars($title) . '</div>' : '')
        . '<p class="testi-text">&ldquo;' . htmlspecialchars($msg) . '&rdquo;</p>'
        . '<div class="testi-author">'
        . $avatarHtml
        . '<div><div class="testi-name">' . htmlspecialchars($name) . '</div>'
        . ($role !== '' ? '<div class="testi-role">' . htmlspecialchars($role) . '</div>' : '')
        . ($date !== '' ? '<div class="testi-date">' . htmlspecialchars($date) . '</div>' : '')
        . '</div></div></div>';
}
