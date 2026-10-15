<?php
// api/biz-intelligence.php — Universal Business Requirements Intelligence endpoint
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Diff.php';
require_once __DIR__ . '/../includes/BizIntelligence/Interview.php';
require_once __DIR__ . '/../includes/BizIntelligence/Completeness.php';
require_once __DIR__ . '/../includes/BizIntelligence/Comply.php';
require_once __DIR__ . '/../includes/BizIntelligence/Gapscan.php';
require_once __DIR__ . '/../includes/BizIntelligence/Verify.php';
require_once __DIR__ . '/../includes/BizIntelligence/Complexity.php';

function biz_blueprint(array $wizard, string $desc, array $answers, array $skipped): array {
    $spec = biz_repair_spec(biz_build_spec($wizard, $desc));
    if (!empty($answers)) {
        biz_interview_apply($spec, $answers);
        $spec = biz_repair_spec($spec);
    }
    [$ok, $errs] = biz_validate_spec($spec);
    if (!$ok) return ['ok' => false, 'issues' => $errs];
    $questions = biz_interview_next($wizard, $spec, $answers, $skipped);
    $comp = biz_completeness($spec, $answers);
    // Architecture recommendation (simplest suitable; existing modes respected).
    $cx = biz_assess_complexity($spec);
    $needsDb = !empty($spec['backend']['transactions']) || in_array('booking', $spec['capabilities'] ?? [], true)
        || in_array('orders', $spec['capabilities'] ?? [], true) || count($spec['entities'] ?? []) > 2;
    $needsAdmin = !empty($spec['entities']);
    $arch = !$needsAdmin ? 'static' : ($needsDb ? 'database' : 'admin');
    $why = !$needsAdmin ? 'Informational site: no server-side data operations required.'
        : ($needsDb ? 'Persistent records/relationships/transactions require the database-backed mode.'
        : 'Secure management with persistent functionality; relational depth does not require full database mode.');
    return ['ok' => true, 'spec' => $spec, 'questions' => $questions,
        'interview_done' => empty($questions), 'completeness' => $comp,
        'complexity' => $cx, 'architecture' => ['mode' => $arch, 'why' => $why]];
}

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) $req = $_POST;
$action = $req['action'] ?? 'analyze';

try {
    if ($action === 'analyze') {
        $wizard = is_array($req['data'] ?? null) ? $req['data'] : $req;
        $desc = (string)($req['description'] ?? $wizard['description'] ?? '');
        $spec = biz_build_spec($wizard, $desc);
        $spec = biz_repair_spec($spec);
        [$ok, $errs] = biz_validate_spec($spec);
        if (!$ok) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'spec invalid', 'issues' => $errs]); exit; }
        echo json_encode(['success' => true, 'spec' => $spec,
            'summary' => ($spec['profile']['business_name'] ?? 'Business') . ' | ' . ($spec['profile']['domain'] ?? '') . ' | caps: ' . implode(',', $spec['capabilities'] ?? [])]);
        exit;
    }
    if ($action === 'validate') {
        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        [$ok, $errs] = biz_validate_spec($spec);
        echo json_encode(['success' => $ok, 'issues' => $errs]);
        exit;
    }
    if ($action === 'diff') {
        $d = biz_diff_specs(is_array($req['old'] ?? null) ? $req['old'] : [], is_array($req['new'] ?? null) ? $req['new'] : []);
        echo json_encode(['success' => true, 'diff' => $d]);
        exit;
    }
    if ($action === 'interview') {
        $wizard = is_array($req['data'] ?? null) ? $req['data'] : [];
        $desc = (string)($req['description'] ?? $wizard['description'] ?? '');
        $answers = is_array($req['answers'] ?? null) ? $req['answers'] : [];
        $skipped = is_array($req['skipped'] ?? null) ? $req['skipped'] : [];
        $spec = biz_repair_spec(biz_build_spec($wizard, $desc));
        $qs = biz_interview_next($wizard, $spec, $answers, $skipped, isset($req['max']) ? max(1, min(6, (int)$req['max'])) : 4);
        echo json_encode(['success' => true, 'questions' => $qs, 'done' => empty($qs)]);
        exit;
    }
    if ($action === 'answer') {
        // Apply interview answers -> rebuilt spec + completeness (additive).
        $wizard = is_array($req['data'] ?? null) ? $req['data'] : [];
        $desc = (string)($req['description'] ?? $wizard['description'] ?? '');
        $answers = is_array($req['answers'] ?? null) ? $req['answers'] : [];
        $skipped = is_array($req['skipped'] ?? null) ? $req['skipped'] : [];
        $b = biz_blueprint($wizard, $desc, $answers, $skipped);
        if (!$b['ok']) { http_response_code(422); echo json_encode(['success' => false, 'issues' => $b['issues']]); exit; }
        echo json_encode(['success' => true, 'spec' => $b['spec'], 'questions' => $b['questions'],
            'done' => $b['interview_done'], 'completeness' => $b['completeness'], 'architecture' => $b['architecture']]);
        exit;
    }
    if ($action === 'blueprint') {
        $wizard = is_array($req['data'] ?? null) ? $req['data'] : [];
        $desc = (string)($req['description'] ?? $wizard['description'] ?? '');
        $answers = is_array($req['answers'] ?? null) ? $req['answers'] : [];
        $skipped = is_array($req['skipped'] ?? null) ? $req['skipped'] : [];
        $b = biz_blueprint($wizard, $desc, $answers, $skipped);
        if (!$b['ok']) { http_response_code(422); echo json_encode(['success' => false, 'issues' => $b['issues']]); exit; }
        echo json_encode(['success' => true, 'spec' => $b['spec'], 'questions' => $b['questions'],
            'done' => $b['interview_done'], 'completeness' => $b['completeness'],
            'complexity' => $b['complexity'], 'architecture' => $b['architecture']]);
        exit;
    }
    if ($action === 'verdict') {
        // Customer accept/edit/reject (+reorder) for a requirement id.
        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        $ok = biz_requirement_verdict($spec, (string)($req['id'] ?? ''),
            (string)($req['verdict'] ?? ''),
            isset($req['text']) ? (string)$req['text'] : null,
            isset($req['order']) ? (int)$req['order'] : null);
        if (!$ok) { echo json_encode(['success' => false, 'error' => 'Unknown requirement or verdict']); exit; }
        [$vok, $errs] = biz_validate_spec($spec);
        echo json_encode(['success' => $vok, 'spec' => $spec, 'issues' => $errs]);
        exit;
    }
    if ($action === 'comply') {
        // Deterministic structural patch BEFORE verify: required sections
        // injected, forms wired. Never rewrites existing markup; every fix listed.
        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        $htmls = is_array($req['htmls'] ?? null) ? array_slice(array_map('strval', $req['htmls']), 0, 3) : [];
        $out = []; $fixes = [];
        foreach ($htmls as $h) {
            $r = biz_ensure_html_compliance(substr($h, 0, 200000), $spec);
            $out[] = $r['html'];
            foreach ($r['fixes'] as $f) $fixes[] = $f;
        }
        echo json_encode(['success' => true, 'htmls' => $out, 'fixes' => array_values(array_unique($fixes))]);
        exit;
    }
    if ($action === 'gapscan') {
        // Reviewable missing-function report: spec vs actual HTML.
        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        $html = substr((string)($req['html'] ?? ''), 0, 200000);
        $g = biz_gapscan($spec, $html);
        echo json_encode(['success' => true, 'findings' => $g['findings'], 'auto_fixable' => $g['auto_fixable']]);
        exit;
    }
    if ($action === 'gapfix') {
        // Deterministic fixes only (forms/sections/runtime). Content gaps
        // stay reported — never faked.
        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        $html = substr((string)($req['html'] ?? ''), 0, 200000);
        $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? 'site')) ?: 'site';
        $r = biz_gapfix($spec, $html, substr($slug, 0, 60));
        echo json_encode(['success' => true, 'html' => $r['html'], 'applied' => $r['applied'], 'remaining' => $r['remaining']]);
        exit;
    }
    if ($action === 'verify') {        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        // admin_dir is server-side only: confine to published/ + storage/.
        $adir = '';
        if (isset($req['admin_dir']) && trim((string)$req['admin_dir']) !== '') {
            $rp = realpath((string)$req['admin_dir']);
            $okRoots = [realpath(__DIR__ . '/../published'), realpath(__DIR__ . '/../storage')];
            if ($rp !== false) {
                foreach ($okRoots as $root) {
                    if ($root !== false && strpos($rp, $root) === 0) { $adir = $rp; break; }
                }
            }
        }
        $rep = biz_verify_project($spec, [
            'htmls' => is_array($req['htmls'] ?? null) ? array_map(fn($h) => substr((string)$h, 0, 200000), array_slice($req['htmls'], 0, 3)) : [],
            'admin_dir' => $adir,
            'admin_files' => is_array($req['admin_files'] ?? null) ? array_slice(array_map('strval', $req['admin_files']), 0, 60) : null,
        ]);
        echo json_encode(['success' => true, 'report' => $rep]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
