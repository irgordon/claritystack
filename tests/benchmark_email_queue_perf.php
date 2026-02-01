<?php
$dbFile = __DIR__ . '/bench_queue_perf.sqlite';
$targetScript = __DIR__ . '/../clarity_app/api/scripts/process_email_queue.php';
$tempScript = __DIR__ . '/temp_process_email_queue.php';

// 1. Setup Database
if (file_exists($dbFile)) unlink($dbFile);
$pdo = new PDO("sqlite:$dbFile");
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
$count = 50;
for ($i = 0; $i < $count; $i++) {
    $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
}
echo "Initialized DB with $count emails.\n";

// 2. Create Temp Script with simulated latency
$originalCode = file_get_contents($targetScript);
// Replace @mail call with simulated latency
// Pattern to find: $sent = @mail($email['to_email'], $email['subject'], $email['body'], $email['headers']);
$modifiedCode = str_replace(
    '$sent = @mail($email[\'to_email\'], $email[\'subject\'], $email[\'body\'], $email[\'headers\']);',
    'usleep(100000); $sent = true; // Simulated 100ms latency',
    $originalCode
);

// We also need to make sure the script uses the correct autoloader path if we move it
// The original script is in clarity_app/api/scripts/
// Our temp script is in tests/
// Original: require_once __DIR__ . '/../core/Database.php';
// New path relative to tests/: ../clarity_app/api/core/Database.php
$modifiedCode = str_replace(
    "require_once __DIR__ . '/../core/Database.php';",
    "require_once __DIR__ . '/../clarity_app/api/core/Database.php';",
    $modifiedCode
);

file_put_contents($tempScript, $modifiedCode);

// 3. Run Benchmark
echo "Running benchmark (Sequential processing of 50 emails, 100ms each)...\n";
$start = microtime(true);

putenv("CLARITY_TEST_DB=$dbFile");
putenv("EMAIL_BATCH_LIMIT=100");
passthru("php $tempScript");

$end = microtime(true);
$duration = $end - $start;

echo "\nTotal Time: " . number_format($duration, 4) . " seconds\n";
echo "Average Time per Email: " . number_format($duration / $count, 4) . " seconds\n";

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
if (file_exists($tempScript)) unlink($tempScript);
?>
