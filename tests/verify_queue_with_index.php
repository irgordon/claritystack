<?php
$dbFile = __DIR__ . '/verify_queue_index.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Create Table and Index
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
$pdo->exec("CREATE INDEX idx_email_queue_status ON email_queue(status)");

// 2. Insert Test Data
$stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
for ($i = 0; $i < 10; $i++) {
    $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: admin@example.com"]);
}

// 3. Simulate Queue Processing (Select pending, then Update)
echo "Selecting pending emails...\n";
$stmt = $pdo->query("SELECT id FROM email_queue WHERE status = 'pending' LIMIT 5");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($ids) . " pending emails.\n";

if (count($ids) > 0) {
    $inQuery = implode(',', $ids);
    echo "Updating status to 'processing' for IDs: $inQuery\n";
    $pdo->exec("UPDATE email_queue SET status = 'processing' WHERE id IN ($inQuery)");

    // Simulate sending
    echo "Updating status to 'sent'...\n";
    foreach ($ids as $id) {
        $pdo->exec("UPDATE email_queue SET status = 'sent' WHERE id = $id");
    }
}

// 4. Verify Final State
$stmt = $pdo->query("SELECT status, count(*) as cnt FROM email_queue GROUP BY status");
$results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "Final Counts:\n";
print_r($results);

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);

if ($results['sent'] == 5 && $results['pending'] == 5) {
    echo "Verification SUCCESS\n";
} else {
    echo "Verification FAILED\n";
    exit(1);
}
?>
