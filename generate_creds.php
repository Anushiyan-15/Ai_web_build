<?php
// Generate Admin Credentials
// This script generates a new admin account and displays the credentials
// Useful for initial setup or when credentials are lost

require_once __DIR__ . '/config/platform-admin.php';
require_once __DIR__ . '/platform-admin/roles.php';

session_start();

// Generate a new random password
$newPassword = 'WebCraft' . rand(1000, 9999) . '!';
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Create a new superadmin account
$newAccount = [
    'username' => 'superadmin',
    'name' => 'Generated Super Admin',
    'email' => 'admin@webcraft.ai',
    'role' => 'superadmin',
    'password' => $passwordHash,
    'active' => true,
    'created_at' => date('Y-m-d H:i:s')
];

// Save to admin accounts file
$accountsFile = __DIR__ . '/storage/admin_accounts.json';
$accounts = [];

if (file_exists($accountsFile)) {
    $existing = json_decode(file_get_contents($accountsFile), true);
    if (is_array($existing)) {
        // Update existing superadmin or add new one
        $accounts = $existing;
    }
}

// Remove any existing superadmin and add the new one
foreach ($accounts as $key => $acc) {
    if (strtolower($acc['username'] ?? '') === 'superadmin') {
        unset($accounts[$key]);
    }
}
$accounts['superadmin'] = $newAccount;

// Write back to file
file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Also clear any login failure locks
$lockFile = __DIR__ . '/platform-admin/storage/submissions/rl_admin_login.json';
if (file_exists($lockFile)) {
    unlink($lockFile);
}

echo "=== New Admin Credentials Generated ===\n";
echo "Username: superadmin\n";
echo "Password: $newPassword\n";
echo "\nThese credentials have been saved to storage/admin_accounts.json\n";
echo "You can now log in at: platform-admin/login.php\n";
?>