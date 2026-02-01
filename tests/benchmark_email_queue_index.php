<?php
$dbFile = __DIR__ . '/bench_queue_index.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Create Table (without index)
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

echo "Populating database with 100,000 records (1% pending)...\n";
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, ?)");

for ($i = 0; $i < 100000; $i++) {
    $status = ($i % 100 == 0) ? 'pending' : 'sent';
    $stmt->execute(["user$i@example.com", "Subject $i", "Body $i", "From: admin@example.com", $status]);
}
$pdo->commit();

// 2. Measure Baseline (No Index)
echo "Benchmarking SELECT without index...\n";
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $stmt = $pdo->query("SELECT id FROM email_queue WHERE status = 'pending'");
    $stmt->fetchAll();
}
$end = microtime(true);
$baselineTime = $end - $start;
echo "Time without index: " . number_format($baselineTime, 4) . "s\n";

// 3. Add Index
echo "Adding index on status...\n";
$start = microtime(true);
$pdo->exec("CREATE INDEX idx_email_queue_status ON email_queue(status)");
$end = microtime(true);
echo "Index creation time: " . number_format($end - $start, 4) . "s\n";

// 4. Measure Optimized (With Index)
echo "Benchmarking SELECT with index...\n";
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $stmt = $pdo->query("SELECT id FROM email_queue WHERE status = 'pending'");
    $stmt->fetchAll();
}
$end = microtime(true);
$optimizedTime = $end - $start;
echo "Time with index: " . number_format($optimizedTime, 4) . "s\n";

// 5. Calculate Improvement
if ($optimizedTime > 0) {
    $improvement = $baselineTime / $optimizedTime;
    echo "Speedup: " . number_format($improvement, 2) . "x\n";
}

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
?>
