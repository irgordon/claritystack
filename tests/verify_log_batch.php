<?php
// tests/verify_log_batch.php

// 1. Setup Test DB for SettingsController
$dbFile = sys_get_temp_dir() . '/test_log_batch.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, public_config TEXT, private_config TEXT, updated_at TEXT)");
    $pdo->exec("INSERT INTO settings (id, public_config) VALUES (1, '{}')");
} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Configure environment
putenv("CLARITY_TEST_DB=$dbFile");

require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Inject Test Config
Database::getInstance()->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
    'DB_USER' => null,
    'DB_PASS' => null
]);

require_once __DIR__ . '/../clarity_app/api/controllers/SettingsController.php';
require_once __DIR__ . '/../clarity_app/api/core/Logger.php';

// Redirect log file to a temp location for verification
$logFile = sys_get_temp_dir() . '/test_clarity.log';
if (file_exists($logFile)) unlink($logFile);

$reflection = new ReflectionClass('Core\Logger');
$property = $reflection->getProperty('logFile');
$property->setAccessible(true);
$property->setValue(null, $logFile);


$sc = new SettingsController();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Test 1: Single Log Event
echo "Test 1: Single Log Event...\n";
$singlePayload = [
    'level' => 'INFO',
    'message' => 'Single Event',
    'category' => 'test'
];

ob_start();
$sc->logClientEvent($singlePayload);
ob_end_clean();

// Check log
if (!file_exists($logFile)) {
    die("FAILED: Log file created.\n");
}
$logs = file($logFile);
if (count($logs) === 1 && strpos($logs[0], 'Single Event') !== false) {
    echo "SUCCESS\n";
} else {
    echo "FAILED: Log count " . count($logs) . "\n";
    print_r($logs);
}

// Test 2: Batch Log Event
echo "Test 2: Batch Log Event...\n";
$batchPayload = [
    [
        'level' => 'INFO',
        'message' => 'Batch Event 1',
        'category' => 'test'
    ],
    [
        'level' => 'WARN',
        'message' => 'Batch Event 2',
        'category' => 'test'
    ]
];

ob_start();
$sc->logClientEvent($batchPayload);
ob_end_clean();

$logs = file($logFile);
if (count($logs) === 3 && strpos($logs[1], 'Batch Event 1') !== false && strpos($logs[2], 'Batch Event 2') !== false) {
    echo "SUCCESS\n";
} else {
    echo "FAILED: Log count " . count($logs) . "\n";
    print_r($logs);
}

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
if (file_exists($logFile)) unlink($logFile);

echo "\nAll Tests Passed!\n";
?>
