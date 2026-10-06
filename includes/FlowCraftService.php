<?php
// ═══════════════════════════════════════════════════════════════
//  includes/FlowCraftService.php — AI-FlowCraft 28-Skill Engine
//  Full-Stack Software Development Lifecycle & Architecture System
// ═══════════════════════════════════════════════════════════════

/**
 * Returns the full catalog of all 28 AI-FlowCraft skills
 */
function flowcraft_skills_catalog(): array {
    return [
        ['n' => 1,  'phase' => 'setup',   'key' => 'requirements',  'name' => 'Requirements Discussion', 'role' => 'Requirements Analyst',           'output' => 'Requirements record + Socratic discovery'],
        ['n' => 2,  'phase' => 'setup',   'key' => 'prd',           'name' => 'PRD Generation',          'role' => 'Product Planner',                'output' => 'PRD + Acceptance Criteria {FEATURE}-AC-{NNN}'],
        ['n' => 3,  'phase' => 'setup',   'key' => 'architecture',  'name' => 'System Architecture',     'role' => 'System Architect',               'output' => 'Tech stack + system topology + module boundaries'],
        ['n' => 4,  'phase' => 'setup',   'key' => 'ia',            'name' => 'Information Architecture', 'role' => 'Information Architect',          'output' => 'Site map / route hierarchy + primary CTAs'],
        ['n' => 5,  'phase' => 'setup',   'key' => 'data-conv',     'name' => 'Data Model Convention',   'role' => 'Data Model Designer',            'output' => 'Field naming conventions (snake_case, ISO timestamps)'],
        ['n' => 6,  'phase' => 'setup',   'key' => 'interaction',   'name' => 'Interaction Design',      'role' => 'Interaction Designer',           'output' => 'Per-section interaction spec (states, ARIA, transitions)'],
        ['n' => 7,  'phase' => 'setup',   'key' => 'database',      'name' => 'Database Design',         'role' => 'Database Architect',             'output' => 'Relational ER diagram + executable MySQL DDL SQL'],
        ['n' => 8,  'phase' => 'setup',   'key' => 'api',           'name' => 'API Design',              'role' => 'API Designer',                   'output' => 'REST endpoint contracts (method, path, JSON shapes, error codes)'],
        ['n' => 9,  'phase' => 'setup',   'key' => 'design-spec',   'name' => 'Design Spec',             'role' => 'Visual Designer',                'output' => 'Design tokens (:root CSS variables, radius, shadows, typography)'],
        ['n' => 10, 'phase' => 'setup',   'key' => 'backend-tech',  'name' => 'Backend Tech Design',     'role' => 'Backend Architect',              'output' => 'Backend spec (PHP signatures, PDO queries, API controllers)'],
        ['n' => 11, 'phase' => 'setup',   'key' => 'frontend-tech', 'name' => 'Frontend Tech Design',    'role' => 'Frontend Architect',             'output' => 'Frontend component tree, state management, event wiring'],
        ['n' => 12, 'phase' => 'setup',   'key' => 'structure',     'name' => 'Project Structure',       'role' => 'Structure Engineer',             'output' => 'Directory & file layout with separation of concerns'],
        ['n' => 13, 'phase' => 'setup',   'key' => 'fe-standards',  'name' => 'Frontend Standards',      'role' => 'Frontend Standards Engineer',    'output' => 'Frontend standards (responsive minimums, WCAG AA a11y, forbidden patterns)'],
        ['n' => 14, 'phase' => 'setup',   'key' => 'be-standards',  'name' => 'Backend Standards',       'role' => 'Backend Standards Engineer',     'output' => 'Backend standards (PDO prepared statements, auth checks, CSRF, validation)'],
        ['n' => 15, 'phase' => 'setup',   'key' => 'collab',        'name' => 'Collaboration Standards', 'role' => 'Collaboration Engineer',         'output' => 'Front-back contract ownership & mock strategies'],
        ['n' => 16, 'phase' => 'setup',   'key' => 'env',           'name' => 'Environment Config',      'role' => 'Environment Config Engineer',    'output' => 'Config files, environment variables, secrets handling'],
        ['n' => 17, 'phase' => 'setup',   'key' => 'roadmap',       'name' => 'Roadmap Planning',        'role' => 'Technical Product Manager',       'output' => 'Milestones in dependency order with entry/exit criteria'],
        ['n' => 18, 'phase' => 'setup',   'key' => 'init',          'name' => 'Project Initialization',  'role' => 'Project Init Engineer',          'output' => 'Minimal runnable scaffold (entrypoints, index, router, db config)'],
        ['n' => 19, 'phase' => 'feature', 'key' => 'task-plan',     'name' => 'Task Planning',           'role' => 'Task Planning Engineer',         'output' => 'Vertical-slice tasks, each shippable with AC verification step'],
        ['n' => 20, 'phase' => 'feature', 'key' => 'implement',     'name' => 'Implementation',          'role' => 'Senior Developer (TDD)',         'output' => 'TDD code (Red failing test -> Green minimal code -> Refactor)'],
        ['n' => 21, 'phase' => 'feature', 'key' => 'stage-report',  'name' => 'Stage Report',            'role' => 'Quality Report Analyst',         'output' => 'Stage completion checklist (AC pass/fail, test runs, known gaps)'],
        ['n' => 22, 'phase' => 'test',    'key' => 'unit',          'name' => 'Unit Testing',            'role' => 'Test Engineer (Unit)',           'output' => 'Pure business logic, data transformers & calculation tests'],
        ['n' => 23, 'phase' => 'test',    'key' => 'component',     'name' => 'Component Testing',       'role' => 'Test Engineer (Component)',      'output' => 'UI component render, user interaction & state tests'],
        ['n' => 24, 'phase' => 'test',    'key' => 'integration',   'name' => 'Integration Testing',     'role' => 'Test Engineer (Integration)',    'output' => 'API + Database conformance, transaction rollback & data consistency'],
        ['n' => 25, 'phase' => 'test',    'key' => 'e2e',           'name' => 'E2E Testing',             'role' => 'Test Engineer (E2E)',            'output' => 'Full user journey flows (auth, cart checkout, admin CRUD, contact submit)'],
        ['n' => 26, 'phase' => 'test',    'key' => 'system',        'name' => 'System Testing',          'role' => 'Test Engineer (System)',         'output' => 'Security review (SQLi, XSS, CSRF), performance targets, cross-browser matrix'],
        ['n' => 27, 'phase' => 'always',  'key' => 'evolve',        'name' => 'Feature Evolution',       'role' => 'Senior Developer (Evolution)',   'output' => 'Incremental enhancement plan (<30% diff rule), zero spec drift'],
        ['n' => 28, 'phase' => 'always',  'key' => 'bugfix',        'name' => 'Bug Fix',                 'role' => 'Senior Developer (Bugfix)',      'output' => 'Reproduce -> root cause -> minimal diff fix -> regression test']
    ];
}

/**
 * AI-FlowCraft Boundary Guardrails
 */
function flowcraft_guardrails(): string {
    return <<<'GUARD'
AI-FLOWCRAFT BOUNDARY GUARDRAILS (Hard Engineering Standards):
1. Role Isolation: No implementation code during requirements/spec phase; no architecture alterations during coding/bugfix phase.
2. AC Traceability: Every requirement maps to an Acceptance Criteria ID in format: {FEATURE}-AC-{NNN} covering:
   - Happy Path (standard valid behavior)
   - Edge & Error (invalid inputs, boundary conditions, timeouts)
   - Business Rules (pricing, authorization, state transitions)
3. Zero Assumption Drift: When requirements are missing or ambiguous, document assumptions explicitly. Never invent unrequested entities.
4. Two-Pass Refinement: First pass deepens structure and correctness; second pass audits cross-document integrity and security.
5. Surgical Diff: Bug fixes and evolution must produce the smallest possible changes without modifying unrelated modules.
GUARD;
}

/**
 * Full-Stack Development Manifesto (The unified guide for Frontend, Backend, Database, and Admin)
 */
function flowcraft_fullstack_manifesto(): string {
    return <<<'MANIFESTO'
## AI-FLOWCRAFT FULL-STACK ENGINEERING PROTOCOL (Production Standards)

1. FRONTEND LAYER (Skills 4, 6, 9, 11, 13):
   - Design System: Root CSS variables (--bg, --panel, --text, --accent, --radius, --shadow).
   - Semantic HTML5: Proper <nav>, <header>, <main>, <section>, <article>, <footer> markup.
   - Accessibility (WCAG 2.1 AA): Contrast >= 4.5:1, aria-labels on icon buttons, keyboard navigable (tabindex, focus-visible).
   - Micro-Interactions: Sticky blur navbar, scroll-reveal IntersectionObserver, responsive grid (clamp, flex wrap, mobile drawer).
   - Zero Dead Controls: Every button either submits, opens a modal/drawer, or scrolls to a valid anchor ID. Never href="#".

2. BACKEND API & CONTROLLER LAYER (Skills 3, 8, 10, 14, 15):
   - PHP 8.3 Standards: Type-hinted functions, strict input validation, proper HTTP status codes (200, 201, 400, 401, 403, 404, 500).
   - Uniform JSON API Contract:
     Success: {"success": true, "data": ..., "message": "..."}
     Error:   {"success": false, "error": "...", "code": 400}
   - Input Sanitization & Security:
     * Never trust user input: strip_tags(), htmlspecialchars(..., ENT_QUOTES, 'UTF-8').
     * Validate emails via filter_var(..., FILTER_VALIDATE_EMAIL).
     * Honeypot anti-spam on public forms.
     * Password hashing via password_hash($pw, PASSWORD_DEFAULT) and password_verify().
     * Session security: session_regenerate_id(true), secure cookies, auth guard helper (requireAdmin()).

3. DATABASE & PERSISTENCE LAYER (Skills 5, 7):
   - Conventions: snake_case table and column names, plural table names (users, products, inquiries, orders).
   - Standard Primary Keys: id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY or CHAR(36) UUID.
   - Timestamps: created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.
   - Foreign Keys & Indexes: Proper INDEX on search columns (slug, email, status, foreign keys) and ON DELETE CASCADE/SET NULL where applicable.
   - PDO Prepared Statements (Absolute Rule): NEVER concatenate strings into SQL queries. Always use PDO::prepare() with named parameters (:email, :id).
   - JSON Storage Engine (Admin Mode fallback): Clean transactional load_json() and save_json() with file locks (LOCK_EX) and backup rotation.

4. CMS & ADMIN PANEL ARCHITECTURE (Skills 12, 18, 20):
   - Modular structure: dashboard, entity CRUD management, content editor, customer inbox, password/profile settings.
   - Dynamic Entities: Full CRUD operations (List with search/filter, Add with validation, Edit with pre-fill, Delete with confirmation).
   - Section 18.5 Edit-Context Guard: Special business process & requirement builders are rendered ONLY when an authenticated admin is editing a specific record (edit.php?id=X), never in read-only, list, or public views.

5. QUALITY ASSURANCE & TESTING PYRAMID (Skills 21, 22, 23, 24, 25, 26):
   - Unit Tests: Pure logic (pricing, discounts, tax, date math).
   - Integration Tests: API contracts match DB schema, transactions roll back on error.
   - E2E Tests: Visitor submits contact form -> saved in inbox -> email sent; Customer adds to cart -> views bill -> checkout.
   - Security Audit: Zero SQL injection, zero XSS, zero CSRF, rate-limited auth endpoints.
MANIFESTO;
}

/**
 * Injected block for Full-Stack generation requests (admin / database modes)
 */
function flowcraft_fullstack_prompt_block(string $mode, array $data = []): string {
    $bizName = (string)($data['biz_name'] ?? 'Website');
    $bizType = (string)($data['biz_type'] ?? 'Business');
    $entities = $data['admin_entities'] ?? [];

    $isDb = ($mode === 'database');
    $isAdmin = in_array($mode, ['admin', 'database'], true);

    if (!$isAdmin) {
        return "## AI-FLOWCRAFT FRONTEND & STATIC SPEC\n"
            . "- Mode: STATIC (Frontend HTML5/CSS3/JS only).\n"
            . "- Apply Skills 4 (IA), 6 (Interaction), 9 (Visual Tokens), 11 (Frontend Arch), 13 (Standards), 20 (TDD Senior Dev).\n"
            . "- Output clean semantic markup, self-contained interactive JavaScript, and accessible CSS.\n\n";
    }

    $out = "## AI-FLOWCRAFT FULL-STACK SPECIFICATION (Mode: " . strtoupper($mode) . ")\n"
        . "Applying Skills 1-28 for full-stack architecture of \"{$bizName}\" ({$bizType}):\n\n"
        . "1. ARCHITECTURAL TOPOLOGY (Skill 3):\n"
        . "   - Public Site: High-converting landing page with working contact form and optional shop.\n"
        . "   - Admin Dashboard: Secure management interface for \"{$bizName}\" records.\n"
        . "   - Storage Engine: " . ($isDb ? "MySQL Relational Database via PDO Prepared Statements" : "Structured JSON Datastore with transactional atomic writes") . ".\n\n"
        . "2. BACKEND & SECURITY CONTRACT (Skills 8, 10, 14):\n"
        . "   - All form handlers must be secured with method verification (POST only), input sanitization, and JSON responses.\n"
        . "   - Auth must utilize bcrypt password hashing (password_verify) and session-based role authorization.\n"
        . "   - Section 18.5 Rule: Requirement builder hooks must strictly check (role === 'admin' && mode === 'edit').\n\n";

    if ($isDb) {
        $out .= "3. DATABASE & DDL SPECIFICATION (Skills 5, 7):\n"
            . "   - Generate valid MySQL DDL schema (`schema.sql`) for business entities.\n"
            . "   - Use InnoDB, utf8mb4 charset, proper primary/foreign keys, and indexes.\n"
            . "   - Prepared statements ONLY (`\$pdo->prepare('SELECT ... WHERE id = :id')`).\n\n";
    } else {
        $out .= "3. JSON DATASTORE SPECIFICATION (Skill 5):\n"
            . "   - Entity files named `data_<entity>.json` storing arrays of records with unique string IDs.\n"
            . "   - Safe file I/O with JSON_PRETTY_PRINT and JSON_UNESCAPED_UNICODE.\n\n";
    }

    $out .= flowcraft_guardrails() . "\n\n";
    return $out;
}
