<?php
// tests/benchmark_n_plus_1.php

function setup_db($dbFile, $count = 50) {
    if (file_exists($dbFile)) unlink($dbFile);
    try {
        $pdo = new PDO("sqlite:$dbFile");
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
        $stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
        $pdo->beginTransaction();
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
        }
        $pdo->commit();
        echo "DB Setup Complete with $count emails.\n";
    } catch (Exception $e) {
        die("DB Setup Failed: " . $e->getMessage() . "\n");
    }
}

$originalFile = __DIR__ . '/../clarity_app/api/scripts/process_email_queue.php';
$dbFile = __DIR__ . '/bench_n_plus_1.sqlite';

$count = 50;
echo "--- Running Benchmark (N+1 Writes) with $count emails ---\n";
setup_db($dbFile, $count);

putenv("CLARITY_TEST_DB=$dbFile");
putenv("EMAIL_BATCH_LIMIT=$count");

$start = microtime(true);
system("php " . escapeshellarg($originalFile) . " > /dev/null 2>&1");
$end = microtime(true);
echo "Time: " . ($end - $start) . "s\n";

if (file_exists($dbFile)) unlink($dbFile);
