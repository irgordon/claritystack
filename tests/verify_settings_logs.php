<?php
// tests/verify_settings_logs.php

// Mock session
$_SESSION['user_id'] = 1;

require_once __DIR__ . '/../clarity_app/api/controllers/SettingsController.php';

// Setup DB
$db = Database::getInstance();
$db->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_USER' => '',
    'DB_PASS' => ''
]);
$pdo = $db->connect();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT)");
$pdo->exec("INSERT INTO users (id, role) VALUES (1, 'admin')");

// Helper to swap log file
$logPath = __DIR__ . '/../clarity_app/logs/clarity.log';
$backupPath = $logPath . '.bak';

// Ensure log directory exists
$logDir = dirname($logPath);
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

if (file_exists($logPath)) {
    rename($logPath, $backupPath);
}

function restore_log() {
    global $logPath, $backupPath;
    if (file_exists($logPath)) unlink($logPath);
    if (file_exists($backupPath)) rename($backupPath, $logPath);
}
register_shutdown_function('restore_log');

echo "Starting verification tests...\n";

// Test 1: 100 lines (Should return last 50 reversed)
$fp = fopen($logPath, 'w');
for ($i = 0; $i < 100; $i++) {
    fwrite($fp, json_encode(['id' => $i, 'msg' => "Line $i"]) . "\n");
}
fclose($fp);

ob_start();
$controller = new SettingsController();
$controller->getLogs();
$output = ob_get_clean();

$logs = json_decode($output, true);
if (!is_array($logs)) {
    echo "FAIL: Output is not JSON array: $output\n";
    exit(1);
}
if (count($logs) !== 50) {
    echo "FAIL: Expected 50 logs, got " . count($logs) . "\n";
    exit(1);
}
// Expected: Newest first.
// Newest is id=99.
if ($logs[0]['id'] !== 99) {
    echo "FAIL: Expected first log to be id 99, got " . $logs[0]['id'] . "\n";
    exit(1);
}
if ($logs[49]['id'] !== 50) {
    echo "FAIL: Expected last log to be id 50, got " . $logs[49]['id'] . "\n";
    exit(1);
}

echo "Test 1 (100 lines) PASS\n";

// Test 2: 10 lines (Should return all 10 reversed)
$fp = fopen($logPath, 'w');
for ($i = 0; $i < 10; $i++) {
    fwrite($fp, json_encode(['id' => $i, 'msg' => "Line $i"]) . "\n");
}
fclose($fp);

ob_start();
$controller->getLogs();
$output = ob_get_clean();
$logs = json_decode($output, true);

if (count($logs) !== 10) {
    echo "FAIL: Expected 10 logs, got " . count($logs) . "\n";
    exit(1);
}
if ($logs[0]['id'] !== 9) {
    echo "FAIL: Expected first log to be id 9, got " . $logs[0]['id'] . "\n";
    exit(1);
}

echo "Test 2 (10 lines) PASS\n";

// Test 3: Empty file
file_put_contents($logPath, "");
ob_start();
$controller->getLogs();
$output = ob_get_clean();
$logs = json_decode($output, true);
if (count($logs) !== 0) {
    echo "FAIL: Expected 0 logs, got " . count($logs) . "\n";
    exit(1);
}
echo "Test 3 (Empty) PASS\n";

// Test 4: File with random newlines at end
$fp = fopen($logPath, 'w');
fwrite($fp, json_encode(['id' => 1]) . "\n");
fwrite($fp, json_encode(['id' => 2]) . "\n\n\n");
fclose($fp);

ob_start();
$controller->getLogs();
$output = ob_get_clean();
$logs = json_decode($output, true);

if (count($logs) !== 2) {
    echo "FAIL: Test 4 Expected 2 logs, got " . count($logs) . "\n";
    // Check what we got
    print_r($logs);
    exit(1);
}
if ($logs[0]['id'] !== 2) {
    echo "FAIL: Test 4 Expected id 2 first.\n";
    exit(1);
}
echo "Test 4 (Extra newlines) PASS\n";

echo "All tests passed.\n";
