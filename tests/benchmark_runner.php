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
$baselineFile = __DIR__ . '/temp_worker_baseline.php';
$dbFile = __DIR__ . '/bench_queue.sqlite';

// --- Run Baseline ---
echo "--- Running Baseline ---\n";
setup_db($dbFile);

// Read the current file (which I already added sleep to in previous step)
$content = file_get_contents($originalFile);

// Fix require path
$content = str_replace("require_once __DIR__ . '/../core/Database.php';",
    "require_once __DIR__ . '/../clarity_app/api/core/Database.php';\nDatabase::getInstance()->setConfig(['DB_DRIVER'=>'sqlite','DB_NAME'=>'$dbFile']);",
    $content);

// Fix SQL for SQLite
$content = str_replace("FOR UPDATE SKIP LOCKED", "", $content);
$content = str_replace("NOW()", "datetime('now')", $content);

file_put_contents($baselineFile, $content);

$start = microtime(true);
system("php $baselineFile");
$end = microtime(true);
echo "Baseline Time: " . ($end - $start) . "s\n";

// Cleanup
if (file_exists($baselineFile)) unlink($baselineFile);
if (file_exists($dbFile)) unlink($dbFile);
