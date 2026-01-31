<?php
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Mock Config for SQLite
$config = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => __DIR__ . '/test_queue.sqlite',
    'DB_USER' => '',
    'DB_PASS' => ''
];

// Setup DB
if (file_exists($config['DB_NAME'])) unlink($config['DB_NAME']);
$pdo = new PDO("sqlite:" . $config['DB_NAME']);
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

// Insert 10 emails
$stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
for ($i = 0; $i < 10; $i++) {
    $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
}

// Configure Singleton
Database::getInstance()->setConfig($config);

// Run the script
// Capture output
ob_start();
$start = microtime(true);
include __DIR__ . '/../clarity_app/api/scripts/process_email_queue.php';
$end = microtime(true);
$output = ob_get_clean();

echo "Output:\n$output\n";
echo "Time: " . ($end - $start) . "s\n";

// Check results
$pdo = new PDO("sqlite:" . $config['DB_NAME']);
$stmt = $pdo->query("SELECT status, count(*) as cnt FROM email_queue GROUP BY status");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
