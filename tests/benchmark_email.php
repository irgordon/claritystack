<?php
// tests/benchmark_email.php

// Require EmailService (which requires Database.php)
require_once __DIR__ . '/../clarity_app/api/core/EmailService.php';

echo "Setting up Benchmark Environment...\n";

// Configure Database for Testing (SQLite Memory)
$testConfig = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_HOST' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'APP_KEY' => 'test_key',
    'DEBUG' => true
];

// Inject configuration to avoid modifying env.php
Database::getInstance()->setConfig($testConfig);

// Initialize Database
try {
    $db = Database::getInstance()->connect();
} catch (Exception $e) {
    echo "DB Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create Tables
$db->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT)");
$db->exec("CREATE TABLE email_templates (id INTEGER PRIMARY KEY, key_name TEXT, subject TEXT, body_content TEXT)");
$db->exec("CREATE TABLE email_queue (id INTEGER PRIMARY KEY, to_email TEXT, subject TEXT, body TEXT, headers TEXT, status TEXT)");

// Populate Data
$stmt = $db->prepare("INSERT INTO settings (business_name, public_config) VALUES (?, ?)");
$stmt->execute([
    'MyBusiness',
    json_encode(['primary_color' => '#ff0000', 'logo_url' => 'http://example.com/logo.png', 'no_reply_email' => 'noreply@example.com'])
]);

$stmt = $db->prepare("INSERT INTO email_templates (key_name, subject, body_content) VALUES (?, ?, ?)");
$stmt->execute([
    'welcome',
    'Welcome {{name}}!',
    '<p>Hello {{name}}, welcome to our service.</p>'
]);

// Benchmark Function
function benchmark($iterations = 100) {
    // Clear queue
    $db = Database::getInstance()->connect();
    $db->exec("DELETE FROM email_queue");

    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        EmailService::send('user@example.com', 'welcome', ['name' => "User $i"]);
    }
    $end = microtime(true);
    $duration = $end - $start;
    $avg = ($duration / $iterations) * 1000;

    // Count queue items
    $count = $db->query("SELECT COUNT(*) FROM email_queue")->fetchColumn();

    echo "Processed $iterations emails in " . number_format($duration, 4) . "s\n";
    echo "Average: " . number_format($avg, 4) . "ms per email\n";
    echo "Queue Size: $count\n";
}

echo "Running Benchmark...\n";
$iterations = isset($argv[1]) ? (int)$argv[1] : 100;
benchmark($iterations);
