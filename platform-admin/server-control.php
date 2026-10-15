<?php
/**
 * platform-admin/server-control.php
 * Real-time Node.js Server Management & Process Control (ON / OFF / Restart)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('deployments'); // or admin access

$rootDir = realpath(dirname(__DIR__));
$serverJs = $rootDir . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'server.js';
$storageDir = (defined('STORAGE_DIR') ? STORAGE_DIR : $rootDir . DIRECTORY_SEPARATOR . 'storage');
$pidFile = $storageDir . DIRECTORY_SEPARATOR . 'node.pid';
$logFile = $storageDir . DIRECTORY_SEPARATOR . 'node.log';
$errFile = $storageDir . DIRECTORY_SEPARATOR . 'node.err.log';

$nodeExe = 'C:\\Program Files\\nodejs\\node.exe';
if (!file_exists($nodeExe)) {
    $where = trim((string)shell_exec('where node 2>nul'));
    if ($where !== '') {
        $nodeExe = explode("\n", str_replace("\r", "", $where))[0];
    }
}

// ──────────────────────────────────────────────
// Helper Functions for Server Management
// ──────────────────────────────────────────────

function check_node_health(int $timeoutMs = 900): ?array {
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => $timeoutMs / 1000,
            'ignore_errors' => true,
        ]
    ]);
    $res = @file_get_contents('http://localhost:3100/node-api/health', false, $ctx);
    if ($res) {
        $json = json_decode($res, true);
        if (is_array($json) && !empty($json['success']) && isset($json['data'])) {
            return $json['data'];
        }
    }
    return null;
}

function start_node_server(string $nodeExe, string $serverJs, string $rootDir, string $pidFile, string $logFile, string $errFile): array {
    $health = check_node_health(500);
    if ($health) {
        return ['ok' => true, 'msg' => 'Node.js server is already running on port 3100.', 'already_running' => true];
    }

    if (!is_dir(dirname($pidFile))) @mkdir(dirname($pidFile), 0755, true);

    $psCmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "$env:PORT=3100; $env:WB_PID_FILE=\'' . addslashes($pidFile) . '\'; Start-Process -FilePath \'' . addslashes($nodeExe) . '\' -ArgumentList \'' . addslashes($serverJs) . '\' -WorkingDirectory \'' . addslashes($rootDir) . '\' -RedirectStandardOutput \'' . addslashes($logFile) . '\' -RedirectStandardError \'' . addslashes($errFile) . '\' -WindowStyle Hidden"';
    
    // Execute non-blocking via popen
    $p = popen($psCmd, 'r');
    if (is_resource($p)) pclose($p);

    // Wait and verify
    for ($i = 0; $i < 6; $i++) {
        usleep(400000);
        $health = check_node_health(500);
        if ($health) {
            log_admin_action('node_server_start', 'server', 'Started Node.js server (PID: ' . ($health['pid'] ?? 'active') . ')');
            return ['ok' => true, 'msg' => 'Node.js server started successfully on port 3100!'];
        }
    }

    return ['ok' => false, 'msg' => 'Node.js process launched but health check timed out. Check server logs below.'];
}

function stop_node_server(string $pidFile): array {
    $stopped = false;
    
    // 1. Try graceful stop endpoint
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode(['token' => '']),
            'timeout' => 1.0,
            'ignore_errors' => true,
        ]
    ]);
    @file_get_contents('http://localhost:3100/node-api/control/stop', false, $ctx);
    usleep(300000);

    // 2. Kill PID if file exists
    if (file_exists($pidFile)) {
        $data = json_decode(@file_get_contents($pidFile), true);
        $pid = (int)($data['pid'] ?? 0);
        if ($pid > 0) {
            exec('taskkill /F /PID ' . $pid . ' 2>&1', $out, $code);
            $stopped = true;
        }
        @unlink($pidFile);
    }

    // 3. Fallback: Find and kill process listening on port 3100 if still active
    $portCheck = shell_exec('netstat -ano | findstr :3100 2>nul');
    if ($portCheck) {
        $lines = explode("\n", trim($portCheck));
        foreach ($lines as $line) {
            if (preg_match('/LISTENING\s+(\d+)/i', $line, $m)) {
                $p = (int)$m[1];
                if ($p > 0) {
                    exec('taskkill /F /PID ' . $p . ' 2>&1');
                    $stopped = true;
                }
            }
        }
    }

    usleep(300000);
    $health = check_node_health(400);
    if (!$health) {
        log_admin_action('node_server_stop', 'server', 'Stopped Node.js server');
        return ['ok' => true, 'msg' => 'Node.js server has been stopped.'];
    }

    return ['ok' => false, 'msg' => 'Server could not be terminated.'];
}

// ──────────────────────────────────────────────
// Handle Control Actions
// ──────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

if ($action === 'start') {
    $res = start_node_server($nodeExe, $serverJs, $rootDir, $pidFile, $logFile, $errFile);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }
    header('Location: server-control.php?msg=' . urlencode($res['msg']) . '&type=' . ($res['ok'] ? 'success' : 'error'));
    exit;
}

if ($action === 'stop') {
    $res = stop_node_server($pidFile);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }
    header('Location: server-control.php?msg=' . urlencode($res['msg']) . '&type=' . ($res['ok'] ? 'success' : 'error'));
    exit;
}

if ($action === 'restart') {
    stop_node_server($pidFile);
    usleep(500000);
    $res = start_node_server($nodeExe, $serverJs, $rootDir, $pidFile, $logFile, $errFile);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }
    header('Location: server-control.php?msg=' . urlencode($res['ok'] ? 'Server restarted successfully.' : $res['msg']) . '&type=' . ($res['ok'] ? 'success' : 'error'));
    exit;
}

if ($action === 'clear_logs') {
    @file_put_contents($logFile, '');
    @file_put_contents($errFile, '');
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'msg' => 'Logs cleared.']);
        exit;
    }
    header('Location: server-control.php?msg=Logs+cleared+successfully&type=success');
    exit;
}

if ($action === 'status_json') {
    header('Content-Type: application/json');
    $h = check_node_health(700);
    $pidInfo = file_exists($pidFile) ? json_decode(@file_get_contents($pidFile), true) : null;
    echo json_encode([
        'running' => $h !== null,
        'health' => $h,
        'pid_info' => $pidInfo,
        'time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Read current status
$health = check_node_health(800);
$isRunning = ($health !== null);
$pidInfo = file_exists($pidFile) ? json_decode(@file_get_contents($pidFile), true) : null;

// Read recent logs
$logs = '';
if (file_exists($logFile)) {
    $lines = file($logFile);
    if ($lines) {
        $recent = array_slice($lines, -60);
        $logs = implode("", $recent);
    }
}
$errLogs = '';
if (file_exists($errFile)) {
    $elines = file($errFile);
    if ($elines) {
        $recentE = array_slice($elines, -40);
        $errLogs = implode("", $recentE);
    }
}

$page_title = 'Node.js Server Control';
render_head($page_title);
?>
<div style="display:flex;min-height:100vh;background:#0a0d14;color:#e2e8f0;font-family:system-ui,-apple-system,sans-serif;">
    <?php render_sidebar('server_control'); ?>
    
    <main style="margin-left:260px;flex:1;padding:32px 40px;max-width:1200px;">
        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;">
            <div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                    <h1 style="font-size:24px;font-weight:700;margin:0;color:#f8fafc;">⚡ Node.js Server Control</h1>
                    <span id="statusBadge" style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;<?= $isRunning ? 'background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);' : 'background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);' ?>">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $isRunning ? '#22c55e' : '#ef4444' ?>;box-shadow:0 0 8px <?= $isRunning ? '#22c55e' : '#ef4444' ?>;"></span>
                        <?= $isRunning ? 'ACTIVE (Port 3100)' : 'STOPPED' ?>
                    </span>
                </div>
                <p style="color:#94a3b8;font-size:14px;margin:0;">
                    Manage the high-performance Node.js operations engine that powers dynamic booking, data queries, and AI features across all customer websites.
                </p>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="http://localhost:3100/node-api/health" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:#1e293b;color:#cbd5e1;padding:8px 14px;border-radius:8px;font-size:13px;text-decoration:none;border:1px solid #334155;transition:0.2s;" title="Direct test endpoint">
                    🔍 Test API Health
                </a>
            </div>
        </div>

        <?php if (!empty($_GET['msg'])): ?>
            <div style="padding:14px 18px;border-radius:10px;margin-bottom:24px;font-size:14px;display:flex;align-items:center;gap:10px;<?= ($_GET['type'] ?? '') === 'error' ? 'background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;' : 'background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.3);color:#86efac;' ?>">
                <span><?= ($_GET['type'] ?? '') === 'error' ? '⚠️' : '✅' ?></span>
                <span><?= htmlspecialchars($_GET['msg']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Primary Control Console Card -->
        <div style="background:#111622;border:1px solid #1e293b;border-radius:16px;padding:28px;margin-bottom:32px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
                <div>
                    <h2 style="font-size:18px;font-weight:600;margin:0 0 6px 0;color:#f1f5f9;">Server Power Switch</h2>
                    <p style="font-size:13px;color:#94a3b8;margin:0;">
                        Dual-engine architecture: If turned off, sites will automatically fall back to the native PHP data bridge without downtime.
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div style="display:flex;gap:12px;align-items:center;">
                    <!-- ON Button -->
                    <button id="btnStart" onclick="runServerAction('start')" <?= $isRunning ? 'disabled' : '' ?> style="display:inline-flex;align-items:center;gap:8px;background:<?= $isRunning ? '#1e293b' : 'linear-gradient(135deg,#16a34a,#22c55e)' ?>;color:<?= $isRunning ? '#64748b' : '#ffffff' ?>;padding:12px 24px;border-radius:10px;font-weight:600;font-size:14px;border:none;cursor:<?= $isRunning ? 'not-allowed' : 'pointer' ?>;box-shadow:<?= $isRunning ? 'none' : '0 4px 14px rgba(34,197,94,0.35)' ?>;transition:all 0.2s;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Turn Server ON
                    </button>

                    <!-- OFF Button -->
                    <button id="btnStop" onclick="runServerAction('stop')" <?= !$isRunning ? 'disabled' : '' ?> style="display:inline-flex;align-items:center;gap:8px;background:<?= !$isRunning ? '#1e293b' : 'linear-gradient(135deg,#dc2626,#ef4444)' ?>;color:<?= !$isRunning ? '#64748b' : '#ffffff' ?>;padding:12px 24px;border-radius:10px;font-weight:600;font-size:14px;border:none;cursor:<?= !$isRunning ? 'not-allowed' : 'pointer' ?>;box-shadow:<?= !$isRunning ? 'none' : '0 4px 14px rgba(239,68,68,0.35)' ?>;transition:all 0.2s;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12"></rect></svg>
                        Turn Server OFF
                    </button>

                    <!-- Restart Button -->
                    <button id="btnRestart" onclick="runServerAction('restart')" style="display:inline-flex;align-items:center;gap:8px;background:#1e293b;color:#f8fafc;padding:12px 18px;border-radius:10px;font-weight:500;font-size:14px;border:1px solid #334155;cursor:pointer;transition:all 0.2s;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        Restart
                    </button>
                </div>
            </div>

            <!-- Real-time metrics grid -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:28px;padding-top:24px;border-top:1px solid #1e293b;">
                <div style="background:#0d111a;padding:16px;border-radius:10px;border:1px solid #1e293b;">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Status</div>
                    <div id="statStatus" style="font-size:18px;font-weight:700;color:<?= $isRunning ? '#4ade80' : '#f87171' ?>;">
                        <?= $isRunning ? 'Online & Listening' : 'Inactive / Offline' ?>
                    </div>
                </div>

                <div style="background:#0d111a;padding:16px;border-radius:10px;border:1px solid #1e293b;">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Port / Network</div>
                    <div style="font-size:18px;font-weight:700;color:#f8fafc;">
                        :3100 <span style="font-size:12px;color:#94a3b8;font-weight:400;">(TCP)</span>
                    </div>
                </div>

                <div style="background:#0d111a;padding:16px;border-radius:10px;border:1px solid #1e293b;">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Process ID (PID)</div>
                    <div id="statPid" style="font-size:18px;font-weight:700;color:#f8fafc;">
                        <?= $isRunning ? htmlspecialchars($pidInfo['pid'] ?? 'Active') : '—' ?>
                    </div>
                </div>

                <div style="background:#0d111a;padding:16px;border-radius:10px;border:1px solid #1e293b;">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Uptime</div>
                    <div id="statUptime" style="font-size:18px;font-weight:700;color:#f8fafc;">
                        <?= $isRunning ? gmdate('H:i:s', (int)($health['uptime'] ?? 0)) : '00:00:00' ?>
                    </div>
                </div>

                <div style="background:#0d111a;padding:16px;border-radius:10px;border:1px solid #1e293b;">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Active Sites</div>
                    <div id="statSites" style="font-size:18px;font-weight:700;color:#60a5fa;">
                        <?= $isRunning ? (int)($health['sites'] ?? 0) : '—' ?>
                    </div>
                </div>

                <div style="background:#0d111a;padding:16px;border-radius:10px;border:1px solid #1e293b;">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Node Engine</div>
                    <div style="font-size:18px;font-weight:700;color:#a78bfa;">
                        <?= $isRunning ? htmlspecialchars($health['node'] ?? 'v22.x') : 'v22.19.0' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Information & Fallback Architecture -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
            <div style="background:#111622;border:1px solid #1e293b;border-radius:14px;padding:22px;">
                <h3 style="font-size:15px;font-weight:600;color:#f8fafc;margin:0 0 10px 0;display:flex;align-items:center;gap:8px;">
                    <span>🛡️</span> Dual Backend Resilience
                </h3>
                <p style="font-size:13px;line-height:1.6;color:#94a3b8;margin:0;">
                    WebCraft client sites are equipped with twin-channel API runtime.
                    When the Node.js server is <strong>ON</strong>, requests use high-speed in-memory JSON synchronization.
                    If the Node.js server is turned <strong>OFF</strong>, client sites seamlessly shift to the integrated PHP JSON storage bridge without losing bookings or inquiries.
                </p>
            </div>

            <div style="background:#111622;border:1px solid #1e293b;border-radius:14px;padding:22px;">
                <h3 style="font-size:15px;font-weight:600;color:#f8fafc;margin:0 0 10px 0;display:flex;align-items:center;gap:8px;">
                    <span>📁</span> Process & Config Paths
                </h3>
                <div style="font-size:12px;color:#94a3b8;font-family:monospace;display:flex;flex-direction:column;gap:6px;">
                    <div><strong>Script:</strong> <span style="color:#cbd5e1;"><?= htmlspecialchars($serverJs) ?></span></div>
                    <div><strong>Node Binary:</strong> <span style="color:#cbd5e1;"><?= htmlspecialchars($nodeExe) ?></span></div>
                    <div><strong>PID Storage:</strong> <span style="color:#cbd5e1;"><?= htmlspecialchars($pidFile) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Live Server Logs Console -->
        <div style="background:#111622;border:1px solid #1e293b;border-radius:16px;overflow:hidden;">
            <div style="padding:16px 20px;background:#0d111a;border-bottom:1px solid #1e293b;display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-weight:600;font-size:14px;color:#f1f5f9;">Terminal Output & Server Log</span>
                    <span style="font-size:11px;background:#1e293b;color:#94a3b8;padding:2px 8px;border-radius:4px;">node.log</span>
                </div>
                <div style="display:flex;gap:10px;">
                    <button onclick="location.reload()" style="background:#1e293b;border:1px solid #334155;color:#cbd5e1;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;">
                        🔄 Refresh
                    </button>
                    <a href="server-control.php?action=clear_logs" style="background:#1e293b;border:1px solid #334155;color:#f87171;padding:6px 12px;border-radius:6px;font-size:12px;text-decoration:none;cursor:pointer;">
                        🗑️ Clear Logs
                    </a>
                </div>
            </div>
            
            <pre id="logConsole" style="margin:0;padding:20px;background:#06080d;color:#10b981;font-family:'Courier New',Courier,monospace;font-size:13px;line-height:1.5;max-height:360px;overflow-y:auto;white-space:pre-wrap;"><?= !empty($logs) ? htmlspecialchars($logs) : "No log output recorded yet.\n" ?><?php if (!empty($errLogs)): ?><span style="color:#f87171;"><?= "\n--- Errors ---\n" . htmlspecialchars($errLogs) ?></span><?php endif; ?></pre>
        </div>
    </main>
</div>

<script>
function runServerAction(action) {
    var btnStart = document.getElementById('btnStart');
    var btnStop = document.getElementById('btnStop');
    var btnRestart = document.getElementById('btnRestart');
    
    btnStart.disabled = true;
    btnStop.disabled = true;
    btnRestart.disabled = true;
    btnStart.style.opacity = '0.5';
    btnStop.style.opacity = '0.5';
    btnRestart.style.opacity = '0.5';

    fetch('server-control.php?action=' + action + '&ajax=1', {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        setTimeout(function() {
            location.href = 'server-control.php?msg=' + encodeURIComponent(data.msg || 'Action completed') + '&type=' + (data.ok ? 'success' : 'error');
        }, 600);
    })
    .catch(function(err) {
        // Fallback to standard HTTP submit if fetch is interrupted by process switch
        setTimeout(function() {
            location.href = 'server-control.php?action=' + action;
        }, 500);
    });
}

// Background auto-refresh status every 4 seconds
setInterval(function() {
    fetch('server-control.php?action=status_json')
    .then(function(r) { return r.json(); })
    .then(function(res) {
        var isUp = res.running;
        var badge = document.getElementById('statusBadge');
        var statStatus = document.getElementById('statStatus');
        var statPid = document.getElementById('statPid');
        var statUptime = document.getElementById('statUptime');
        var statSites = document.getElementById('statSites');
        var btnStart = document.getElementById('btnStart');
        var btnStop = document.getElementById('btnStop');

        if (isUp) {
            badge.style.background = 'rgba(34,197,94,0.15)';
            badge.style.color = '#4ade80';
            badge.style.border = '1px solid rgba(34,197,94,0.3)';
            badge.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 8px #22c55e;"></span> ACTIVE (Port 3100)';
            
            statStatus.textContent = 'Online & Listening';
            statStatus.style.color = '#4ade80';
            statPid.textContent = (res.pid_info && res.pid_info.pid) ? res.pid_info.pid : 'Active';
            
            var s = res.health ? res.health.uptime : 0;
            var hrs = Math.floor(s / 3600);
            var mins = Math.floor((s % 3600) / 60);
            var secs = s % 60;
            statUptime.textContent = (hrs < 10 ? '0' : '') + hrs + ':' + (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
            statSites.textContent = res.health ? res.health.sites : '0';

            btnStart.disabled = true;
            btnStart.style.opacity = '0.5';
            btnStart.style.cursor = 'not-allowed';
            btnStop.disabled = false;
            btnStop.style.opacity = '1';
            btnStop.style.cursor = 'pointer';
        } else {
            badge.style.background = 'rgba(239,68,68,0.15)';
            badge.style.color = '#f87171';
            badge.style.border = '1px solid rgba(239,68,68,0.3)';
            badge.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 8px #ef4444;"></span> STOPPED';

            statStatus.textContent = 'Inactive / Offline';
            statStatus.style.color = '#f87171';
            statPid.textContent = '—';
            statUptime.textContent = '00:00:00';
            statSites.textContent = '—';

            btnStart.disabled = false;
            btnStart.style.opacity = '1';
            btnStart.style.cursor = 'pointer';
            btnStop.disabled = true;
            btnStop.style.opacity = '0.5';
            btnStop.style.cursor = 'not-allowed';
        }
    })
    .catch(function(){});
}, 4000);
</script>
</body>
</html>
