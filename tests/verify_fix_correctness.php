<?php
// tests/verify_fix_correctness.php

$repoRoot = realpath(__DIR__ . '/..');
$envPath = $repoRoot . '/clarity_app/api/config/env.php';
$envBackupPath = $envPath . '.bak';
$dbPath = $repoRoot . '/tests/temp_verify.sqlite';
$scriptPath = $repoRoot . '/clarity_app/api/scripts/process_email_queue.php';

// 1. Backup env.php
if (file_exists($envPath)) {
    copy($envPath, $envBackupPath);
}

// 2. Setup SQLite DB
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE email_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    to_email TEXT,
    subject TEXT,
    body TEXT,
    headers TEXT,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME
)");

// 3. Write temp env.php
$tempEnvContent = "<?php
return [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => '$dbPath',
    'DB_USER' => '',
    'DB_PASS' => '',
    'APP_KEY' => 'test_key',
    'DEBUG' => false
];
";
file_put_contents($envPath, $tempEnvContent);

// 4. Insert 3 emails
$stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
for ($i = 0; $i < 3; $i++) {
    $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
}

// 5. Run script
echo "Running script...\n";
$output = shell_exec("php $scriptPath");
echo "Script Output: $output\n";

// 6. Verify DB state
$stmt = $pdo->query("SELECT id, status FROM email_queue");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "DB State:\n";
foreach ($rows as $row) {
    echo "ID: {$row['id']}, Status: {$row['status']}\n";
}

// 7. Restore env.php
if (file_exists($envBackupPath)) {
    copy($envBackupPath, $envPath);
    unlink($envBackupPath);
}
if (file_exists($dbPath)) {
    unlink($dbPath);
}
