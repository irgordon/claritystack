<?php
// tests/verify_smtp_encryption_logic.php

require_once __DIR__ . '/../clarity_app/api/core/SmtpClient.php';

// Start Mock Server in Background
$serverLog = __DIR__ . '/mock_smtp.log';
@unlink($serverLog);
$pid = exec("php " . __DIR__ . "/mock_smtp_server.php > /dev/null 2>&1 & echo $!");

// Start Second Mock Server (No TLS)
$serverLogNoTls = __DIR__ . '/mock_smtp_2526.log';
@unlink($serverLogNoTls);
$pidNoTls = exec("php " . __DIR__ . "/mock_smtp_server.php 2526 --no-tls > /dev/null 2>&1 & echo $!");

sleep(1); // Wait for servers to start

function cleanup($pid, $pidNoTls) {
    echo "Stopping Mock Servers (PIDs $pid, $pidNoTls)...\n";
    posix_kill($pid, SIGTERM);
    posix_kill($pidNoTls, SIGTERM);
}

register_shutdown_function(function() use ($pid, $pidNoTls) {
    cleanup($pid, $pidNoTls);
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
try {
    echo "Test 3: Encryption = 'ssl' -> ";
    $client = new SmtpClient('127.0.0.1', 2525, null, null, 5, 'ssl');
    $client->connect();
    echo "FAILED (Should have failed SSL handshake)\n";
    exit(1);
} catch (Exception $e) {
    echo "PASSED (Connection failed as expected: " . $e->getMessage() . ")\n";
}

// Test 4: Auto (null) on port 2525 - Should attempt STARTTLS because server advertises it
try {
    echo "Test 4: Auto on port 2525 (Advertised) -> ";
    file_put_contents($serverLog, "");
    $client = new SmtpClient('127.0.0.1', 2525, null, null, 5, null);
    $client->connect();

    // Should fail negotiation
    echo "FAILED (Should have failed TLS negotiation)\n";
    exit(1);
} catch (Exception $e) {
     if (strpos($e->getMessage(), 'TLS Negotiation Failed') !== false || strpos($e->getMessage(), 'crypto') !== false) {
        $logs = file_get_contents($serverLog);
        if (stripos($logs, 'STARTTLS') !== false) {
            echo "PASSED (Attempted STARTTLS because advertised)\n";
        } else {
            echo "FAILED (Exception matched but STARTTLS command not found in logs)\n";
            exit(1);
        }
    } else {
        echo "FAILED (Unexpected Exception: " . $e->getMessage() . ")\n";
        exit(1);
    }
}

// Test 5: Auto (null) on port 2526 - Should NOT attempt STARTTLS because server does NOT advertise it
try {
    echo "Test 5: Auto on port 2526 (Not Advertised) -> ";
    $client = new SmtpClient('127.0.0.1', 2526, null, null, 5, null);
    $client->connect();

    $logs = file_get_contents($serverLogNoTls);
    if (stripos($logs, 'STARTTLS') !== false) {
        echo "FAILED (STARTTLS was issued but not advertised)\n";
        exit(1);
    }
    echo "PASSED (Connected, no STARTTLS attempt)\n";
    $client->quit();
} catch (Exception $e) {
    echo "FAILED (" . $e->getMessage() . ")\n";
    exit(1);
}

echo "\nAll Tests Passed.\n";
