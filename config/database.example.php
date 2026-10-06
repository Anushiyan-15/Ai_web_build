<?php
// ═══════════════════════════════════════════════════════════════
//  config/database.example.php — Supabase PostgreSQL Configuration (TEMPLATE)
//  Copy to config/database.php and fill in your own values.
//  config/database.php is git-ignored and never pushed.
// ═══════════════════════════════════════════════════════════════

// Primary Database Engine: 'pgsql' (Supabase)
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'pgsql');

// Supabase Project Credentials
if (!defined('SUPABASE_URL'))        define('SUPABASE_URL',        getenv('SUPABASE_URL') ?: 'https://YOUR_PROJECT.supabase.co');
if (!defined('SUPABASE_PROJECT_ID')) define('SUPABASE_PROJECT_ID', getenv('SUPABASE_PROJECT_ID') ?: 'YOUR_PROJECT');
if (!defined('SUPABASE_DB_PASS'))    define('SUPABASE_DB_PASS',    getenv('SUPABASE_DB_PASS') ?: 'YOUR_DB_PASSWORD');

// PostgreSQL Connection (Supabase Cloud)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'db.YOUR_PROJECT.supabase.co');
if (!defined('DB_PORT')) define('DB_PORT', 5432);
if (!defined('DB_NAME')) define('DB_NAME', 'postgres');
if (!defined('DB_USER')) define('DB_USER', 'postgres');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_DB_PASSWORD');
if (!defined('DB_CHAR')) define('DB_CHAR', 'utf8');

// Local MySQL Fallback (used when Supabase is unreachable)
if (!defined('LOCAL_MYSQL_HOST')) define('LOCAL_MYSQL_HOST', 'localhost');
if (!defined('LOCAL_MYSQL_NAME')) define('LOCAL_MYSQL_NAME', 'webbuilder_db');
if (!defined('LOCAL_MYSQL_USER')) define('LOCAL_MYSQL_USER', 'root');
if (!defined('LOCAL_MYSQL_PASS')) define('LOCAL_MYSQL_PASS', getenv('LOCAL_MYSQL_PASS') ?: 'YOUR_LOCAL_MYSQL_PASSWORD');

// Supabase REST API (used as alternative sync path for Supabase)
if (!defined('SUPABASE_ANON_KEY')) define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'YOUR_SUPABASE_ANON_KEY');
