<?php
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Configure for SQLite Benchmark
$config = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => __DIR__ . '/bench_queue.sqlite',
    'DB_USER' => '',
    'DB_PASS' => ''
];
Database::getInstance()->setConfig($config);

// Clear log
file_put_contents(__DIR__ . '/memory_baseline.log', '');

function log_memory($pid, $role) {
    $mem = memory_get_peak_usage(true);
    $line = "$pid,$role,$mem\n";
    file_put_contents(__DIR__ . '/memory_baseline.log', $line, FILE_APPEND | LOCK_EX);
}

echo "Starting Baseline (Forking) Processor...\n";

try {
    $db = Database::getInstance()->connect();

    // Start transaction to lock rows
    $db->beginTransaction();

    // Fetch pending emails - PATCHED for SQLite (No SKIP LOCKED)
    $stmt = $db->query("SELECT id, to_email, subject, body, headers FROM email_queue WHERE status = 'pending' LIMIT 50");
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        $db->commit();
        echo "No pending emails found.\n";
        exit;
    }

    $ids = array_column($emails, 'id');
    $inQuery = implode(',', array_fill(0, count($ids), '?'));
    // PATCHED for SQLite: datetime('now')
    $updateStmt = $db->prepare("UPDATE email_queue SET status = 'processing', updated_at = datetime('now') WHERE id IN ($inQuery)");
    $updateStmt->execute($ids);

    $db->commit();

    echo "Found " . count($emails) . " emails to process.\n";
    log_memory(getmypid(), 'parent_start');

    $children = [];

    foreach ($emails as $email) {
        $pid = pcntl_fork();

        if ($pid == -1) {
            error_log("Could not fork process for email ID: {$email['id']}");
            continue;
        } elseif ($pid) {
            // Parent process
            $children[] = $pid;
        } else {
            // Child process
            try {
                Database::getInstance()->conn = null;
                $childDb = Database::getInstance()->connect();

                // Mock sending (sleep slightly to simulate work and allow concurrency overlap)
                usleep(10000);

                // PATCHED for SQLite: datetime('now')
                $stmt = $childDb->prepare("UPDATE email_queue SET status = 'sent', updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$email['id']]);

            } catch (Exception $e) {
                echo "Error processing email {$email['id']}: " . $e->getMessage() . "\n";
            }

            log_memory(getmypid(), 'child');
            exit(0);
        }
    }

    // Wait for all child processes to complete
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    log_memory(getmypid(), 'parent_end');

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
