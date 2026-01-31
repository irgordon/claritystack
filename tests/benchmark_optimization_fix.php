<?php
// tests/benchmark_optimization_fix.php

$repoRoot = realpath(__DIR__ . '/..');
$envPath = $repoRoot . '/clarity_app/api/config/env.php';
$envBackupPath = $envPath . '.bak';
$dbPath = $repoRoot . '/tests/temp_bench.sqlite';
$scriptPath = $repoRoot . '/clarity_app/api/scripts/process_email_queue.php';

// 1. Backup env.php
if (file_exists($envPath)) {
    copy($envPath, $envBackupPath);
}

// 2. Setup SQLite DB
if (file_exists($dbPath)) {
    unlink($dbPath);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
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
} catch (Exception $e) {
    die("Failed to create SQLite DB: " . $e->getMessage() . "\n");
}

// 3. Write temp env.php
$tempEnvContent = "<?php
return [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => '$dbPath',
    'DB_USER' => '',
    'DB_PASS' => '',
    'APP_KEY' => 'test_key',
    'DEBUG' => false
];
";
file_put_contents($envPath, $tempEnvContent);

// 4. Run Benchmark
$iterations = 20;
$batchSize = 50;
$totalTime = 0;

echo "Starting benchmark ($iterations iterations of $batchSize emails)...\n";

for ($i = 0; $i < $iterations; $i++) {
    // Reset DB state
    $pdo->exec("DELETE FROM email_queue");
    $pdo->exec("VACUUM"); // Keep it clean

    // Insert pending emails
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
    for ($j = 0; $j < $batchSize; $j++) {
        $stmt->execute(["test$j@example.com", "Subject $j", "Body $j", "From: me@example.com"]);
    }
    $pdo->commit();

    // Run script
    $start = microtime(true);
    // Use exec to run the script. We ignore output to avoid clutter, but we capture stderr just in case.
    $output = shell_exec("php $scriptPath 2>&1");
    $end = microtime(true);

    $duration = $end - $start;
    $totalTime += $duration;

    // Check if it actually worked (optional, but good for sanity)
    // $count = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sent' OR status = 'failed'")->fetchColumn();
    // if ($count != $batchSize) {
    //    echo "Warning: Iteration $i processed $count emails instead of $batchSize.\n";
    //    echo "Output: $output\n";
    // }

    // Progress dot
    echo ".";
}
echo "\n";

// 5. Restore env.php
if (file_exists($envBackupPath)) {
    copy($envBackupPath, $envPath);
    unlink($envBackupPath);
}
if (file_exists($dbPath)) {
    unlink($dbPath);
}

// 6. Report
$avgTime = $totalTime / $iterations;
echo "Total Time: " . number_format($totalTime, 4) . "s\n";
echo "Average Time per Batch: " . number_format($avgTime, 4) . "s\n";
echo "Average Time per Email: " . number_format(($totalTime / ($iterations * $batchSize)) * 1000, 4) . "ms\n";
