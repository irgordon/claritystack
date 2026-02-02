<?php
require_once __DIR__ . '/../clarity_app/api/core/SmtpClient.php';

$logFile = __DIR__ . '/mock_smtp.log';
if (file_exists($logFile)) {
    unlink($logFile);
}

// Ensure server is ready? We'll rely on the caller to ensure server is running.

$client = new SmtpClient('127.0.0.1', 2525, 'user', 'pass');

try {
    echo "Attempting connection...\n";
    $client->connect();
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 1. Send failing email
echo "Sending failing email...\n";
try {
    $client->send('me@example.com', 'fail@example.com', 'Test 1', 'Body 1');
    echo "Unexpected success for failing email\n";
} catch (Exception $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}

// 2. Send successful email (reusing connection)
echo "Sending successful email...\n";
try {
    $client->send('me@example.com', 'success@example.com', 'Test 2', 'Body 2');
    echo "Second email sent successfully\n";
} catch (Exception $e) {
    echo "Second email failed: " . $e->getMessage() . "\n";
}

$client->quit();

// 3. Verify RSET in log
$log = file_get_contents($logFile);
if (strpos($log, 'RSET') !== false) {
    echo "SUCCESS: RSET command found in logs.\n";
} else {
    echo "FAILURE: RSET command NOT found in logs.\n";
}
