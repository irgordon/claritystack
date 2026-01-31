<?php
// Function to setup DB
function setup_db($dbFile) {
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
        for ($i = 0; $i < 5; $i++) {
            $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
        }
        echo "DB Setup Complete.\n";
    } catch (Exception $e) {
        die("DB Setup Failed: " . $e->getMessage() . "\n");
    }
}

$originalFile = __DIR__ . '/../clarity_app/api/scripts/process_email_queue.php';
$dbFile = __DIR__ . '/bench_queue.sqlite';

// --- Run Baseline ---
echo "--- Running Baseline ---\n";
setup_db($dbFile);

putenv("CLARITY_TEST_DB=$dbFile");

// process_email_queue.php handles SQLite compatibility natively when CLARITY_TEST_DB is set.
// It automatically adjusts SQL for SQLite (no 'FOR UPDATE SKIP LOCKED', uses 'datetime('now')').

$start = microtime(true);
system("php " . escapeshellarg($originalFile));
$end = microtime(true);
echo "Baseline Time: " . ($end - $start) . "s\n";

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
