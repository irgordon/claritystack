<?php
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Connect to DB
try {
    $db = Database::getInstance()->connect();
} catch (Exception $e) {
    die("DB Connection failed: " . $e->getMessage() . "\n");
}

// Clear queue
$db->exec("DELETE FROM email_queue");

// Insert 5 test emails
$stmt = $db->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
for ($i = 0; $i < 5; $i++) {
    $stmt->execute(["test$i@example.com", "Subject $i", "Body $i", "From: me@example.com"]);
}

echo "Inserted 5 emails.\n";

$start = microtime(true);

// Run the processor script
// We use system() to run it in a separate process so we can measure the full execution time including startup
system("php " . __DIR__ . "/../clarity_app/api/scripts/process_email_queue.php");

$end = microtime(true);
echo "Time taken: " . ($end - $start) . " seconds\n";
