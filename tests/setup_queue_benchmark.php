<?php
$dbFile = __DIR__ . '/bench_queue.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create schema
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

    // Insert test data
    $stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
    for ($i = 0; $i < 5; $i++) {
        $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
    }

    echo "Database set up at $dbFile with 5 pending emails.\n";
} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage() . "\n");
}
