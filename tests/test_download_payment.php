<?php
// tests/test_download_payment.php

require_once __DIR__ . '/../clarity_app/api/controllers/DownloadController.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';

// Setup SQLite DB
$testConfig = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_HOST' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'APP_KEY' => 'testkey',
    'DEBUG' => true
];

Database::getInstance()->setConfig($testConfig);
$db = Database::getInstance()->connect();

// Create Tables
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, role TEXT)");
$db->exec("CREATE TABLE projects (id TEXT PRIMARY KEY, client_email TEXT, status TEXT, package_snapshot TEXT, storage_path TEXT, title TEXT)");
$db->exec("CREATE TABLE download_tokens (id INTEGER PRIMARY KEY, project_id TEXT, token_hash TEXT, expires_at TEXT)");
$db->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, public_config TEXT)");

// Insert Settings
$db->prepare("INSERT INTO settings (public_config) VALUES (?)")->execute([json_encode(['link_timeout' => 10])]);

// Insert Data
$db->prepare("INSERT INTO users (id, email, role) VALUES (1, 'client@example.com', 'client')")->execute();
$db->prepare("INSERT INTO users (id, email, role) VALUES (2, 'admin@example.com', 'admin')")->execute();
$db->prepare("INSERT INTO users (id, email, role) VALUES (3, 'stranger@example.com', 'client')")->execute();

$unpaidProject = '11111111-1111-1111-1111-111111111111';
$db->prepare("INSERT INTO projects (id, client_email, status, package_snapshot, storage_path, title) VALUES (?, ?, ?, ?, ?, ?)")
   ->execute([$unpaidProject, 'client@example.com', 'draft', json_encode(['price_cents' => 5000]), '/tmp/storage/p1', 'Unpaid Project']);

$paidProject = '22222222-2222-2222-2222-222222222222';
$db->prepare("INSERT INTO projects (id, client_email, status, package_snapshot, storage_path, title) VALUES (?, ?, ?, ?, ?, ?)")
   ->execute([$paidProject, 'client@example.com', 'paid', json_encode(['price_cents' => 5000]), '/tmp/storage/p2', 'Paid Project']);

$freeProject = '33333333-3333-3333-3333-333333333333';
$db->prepare("INSERT INTO projects (id, client_email, status, package_snapshot, storage_path, title) VALUES (?, ?, ?, ?, ?, ?)")
   ->execute([$freeProject, 'client@example.com', 'draft', json_encode(['price_cents' => 0]), '/tmp/storage/p3', 'Free Project']);

function capture_output($callback) {
    ob_start();
    try {
        $callback();
    } catch (Exception $e) {} // Catch if needed
    return ob_get_clean();
}

$controller = new DownloadController();

function checkResult($output, $expectSuccess, $label) {
    $data = json_decode($output, true);
    $hasUrl = isset($data['url']);

    if ($expectSuccess) {
        if ($hasUrl) echo "PASS: $label\n";
        else echo "FAIL: $label (Expected URL, got: $output)\n";
    } else {
        if ($hasUrl) echo "FAIL: $label (Got URL but expected failure)\n";
        else echo "PASS: $label (Blocked: $output)\n";
    }
}

// Start fake session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$testCase = $argv[1] ?? '1';

if ($testCase === '1') {
    echo "--- Test 1: Unpaid Project by Owner (Should FAIL) ---\n";
    $_SESSION['user_id'] = 1; // Client
    $output = capture_output(function() use ($controller, $unpaidProject) {
        $controller->generateLink($unpaidProject);
    });
    // If output is empty because of exit, we might need to handle it.
    // But exit stops script. So this checkResult might not run if I don't use shutdown function or similar.
    // However, if I run this as a script, I can just check the output of the SCRIPT.
    // But since the controller calls `exit`, this script terminates inside capture_output closure?
    // Yes.
    // So the script output is whatever `echo`ed before `exit`.
    // Which is the JSON.
    // So I don't need `checkResult` here if `exit` is called.
    // I should just let it output and I will check it in bash.
    exit; // Stop here manually if controller didn't exit (it should)
}

if ($testCase === '2') {
    echo "--- Test 2: Paid Project by Owner (Should PASS) ---\n";
    $_SESSION['user_id'] = 1;
    $controller->generateLink($paidProject);
}

if ($testCase === '3') {
    echo "--- Test 3: Free Project by Owner (Should PASS) ---\n";
    $_SESSION['user_id'] = 1;
    $controller->generateLink($freeProject);
}

if ($testCase === '4') {
    echo "--- Test 4: Unpaid Project by Admin (Should PASS) ---\n";
    $_SESSION['user_id'] = 2;
    $controller->generateLink($unpaidProject);
}

if ($testCase === '5') {
    echo "--- Test 5: Paid Project by Stranger (Should FAIL) ---\n";
    $_SESSION['user_id'] = 3;
    $controller->generateLink($paidProject);
}

if ($testCase === '6') {
    echo "--- Test 6: No Session (Should FAIL) ---\n";
    unset($_SESSION['user_id']);
    $controller->generateLink($paidProject);
}
?>
