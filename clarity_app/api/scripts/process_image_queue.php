<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/FileSecurity.php';

use Core\FileSecurity;

// Allow injecting test configuration via environment variable
if ($testDb = getenv('CLARITY_TEST_DB')) {
    Database::getInstance()->setConfig([
        'DB_DRIVER' => 'sqlite',
        'DB_NAME' => $testDb,
        'DB_USER' => '',
        'DB_PASS' => ''
    ]);
}

echo "Starting Image Queue Processor...\n";

try {
    $db = Database::getInstance()->connect();

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = ($driver === 'sqlite');

    // DB specific SQL fragments
    $skipLocked = $isSqlite ? "" : "FOR UPDATE SKIP LOCKED";
    $nowFn = $isSqlite ? "datetime('now')" : "NOW()";

    // 1. Fetch pending jobs
    $db->beginTransaction();

    $limit = 10;
    $stmt = $db->query("SELECT * FROM image_queue WHERE status = 'pending' LIMIT $limit $skipLocked");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        $db->commit();
        echo "No pending jobs found.\n";
        exit;
    }

    // 2. Mark as processing
    $ids = array_column($jobs, 'id');
    $inQuery = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $db->prepare("UPDATE image_queue SET status = 'processing', updated_at = $nowFn WHERE id IN ($inQuery)");
    $updateStmt->execute($ids);

    $db->commit();

    echo "Found " . count($jobs) . " jobs to process.\n";

    // 3. Process
    $fs = new FileSecurity();

    foreach ($jobs as $job) {
        echo "Processing Job #{$job['id']} ({$job['original_path']})... ";
        try {
            $success = $fs->processQueueJob($job['original_path'], $job['thumb_path'], $job['width']);

            $status = $success ? 'completed' : 'failed';
            $msg = $success ? "Done." : "Failed (File not found).";

            $stmt = $db->prepare("UPDATE image_queue SET status = ?, updated_at = $nowFn WHERE id = ?");
            $stmt->execute([$status, $job['id']]);

            echo "$msg\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $stmt = $db->prepare("UPDATE image_queue SET status = 'failed', updated_at = $nowFn WHERE id = ?");
            $stmt->execute([$job['id']]);
        }
    }

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
?>
