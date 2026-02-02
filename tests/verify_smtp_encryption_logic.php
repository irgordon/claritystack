<?php
// tests/verify_smtp_encryption_logic.php

require_once __DIR__ . '/../clarity_app/api/core/SmtpClient.php';

// Start Mock Server in Background
$serverLog = __DIR__ . '/mock_smtp.log';
@unlink($serverLog);
$pid = exec("php " . __DIR__ . "/mock_smtp_server.php > /dev/null 2>&1 & echo $!");
sleep(1); // Wait for server to start

function cleanup($pid) {
    echo "Stopping Mock Server (PID $pid)...\n";
    posix_kill($pid, SIGTERM);
}

register_shutdown_function(function() use ($pid) {
    cleanup($pid);
});

echo "Testing SMTP Encryption Logic...\n";

// Test 1: Explicit 'none' - Should connect via TCP and NOT issue STARTTLS
try {
    echo "Test 1: Encryption = 'none' -> ";
    $client = new SmtpClient('127.0.0.1', 2525, null, null, 5, 'none');
    $client->connect();

    // Check logs for STARTTLS
    $logs = file_get_contents($serverLog);
    if (stripos($logs, 'STARTTLS') !== false) {
        echo "FAILED (STARTTLS was issued unexpectedly)\n";
        exit(1);
    }
    echo "PASSED (Connected, no STARTTLS)\n";
    $client->quit();
} catch (Exception $e) {
    echo "FAILED (" . $e->getMessage() . ")\n";
    exit(1);
}

// Reset Log
file_put_contents($serverLog, "");

// Test 2: Explicit 'tls' - Should connect via TCP and ISSUE STARTTLS
// Note: Our mock server doesn't actually support TLS handshake, so stream_socket_enable_crypto will fail.
// This confirms we tried to enable it.
try {
    echo "Test 2: Encryption = 'tls' -> ";
    $client = new SmtpClient('127.0.0.1', 2525, null, null, 5, 'tls');
    $client->connect();
    echo "FAILED (Should have failed TLS negotiation)\n";
    exit(1);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'TLS Negotiation Failed') !== false || strpos($e->getMessage(), 'crypto') !== false) {
        // Verify STARTTLS was sent
        $logs = file_get_contents($serverLog);
        if (stripos($logs, 'STARTTLS') !== false) {
            echo "PASSED (Attempted STARTTLS as expected)\n";
        } else {
             echo "FAILED (Exception matched but STARTTLS command not found in logs)\n";
             exit(1);
        }
    } else {
        echo "FAILED (Unexpected Exception: " . $e->getMessage() . ")\n";
        exit(1);
    }
}

// Test 3: Explicit 'ssl' - Should try SSL immediately
// Connecting SSL to a plain TCP port usually results in a handshake failure or connection reset immediately.
try {
    echo "Test 3: Encryption = 'ssl' -> ";
    // Use a different constructor to force SSL
    $client = new SmtpClient('127.0.0.1', 2525, null, null, 5, 'ssl');
    $client->connect();
    echo "FAILED (Should have failed SSL handshake)\n";
    exit(1);
} catch (Exception $e) {
    // Exact error message depends on OpenSSL version and OS, but it generally implies handshake failure
    // or the server closing connection because it received garbage (Client Hello).
    echo "PASSED (Connection failed as expected: " . $e->getMessage() . ")\n";
}

// Test 4: Auto (null) on port 2525 - Should act like 'none' (or 'tls' if we updated auto logic, but current plan is simple auto)
// Current Logic: 465=SSL, 587=TLS, others=TCP
try {
    echo "Test 4: Auto on port 2525 -> ";
    file_put_contents($serverLog, "");
    $client = new SmtpClient('127.0.0.1', 2525, null, null, 5, null);
    $client->connect();

    $logs = file_get_contents($serverLog);
    if (stripos($logs, 'STARTTLS') !== false) {
        echo "FAILED (Auto on 2525 should not trigger STARTTLS)\n";
        exit(1);
    }
    echo "PASSED\n";
    $client->quit();
} catch (Exception $e) {
    echo "FAILED (" . $e->getMessage() . ")\n";
    exit(1);
}

echo "\nAll Tests Passed.\n";
