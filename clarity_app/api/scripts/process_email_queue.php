<?php
require_once __DIR__ . '/../core/Database.php';

// Allow injecting test configuration via environment variable
if ($testDb = getenv('CLARITY_TEST_DB')) {
    Database::getInstance()->setConfig([
        'DB_DRIVER' => 'sqlite',
        'DB_NAME' => $testDb,
        'DB_USER' => null,
        'DB_PASS' => null
    ]);
}

/**
 * Process a batch of emails.
 *
 * @param array $emails List of email records.
 * @param PDO $db Database connection.
 * @param string $nowFn SQL function for current time.
 */
function processEmailsBatch(array $emails, PDO $db, string $nowFn) {
    $sentIds = [];
    $failedIds = [];
    $batchSize = 10;

    $flushBatch = function() use (&$sentIds, &$failedIds, $db, $nowFn) {
        if (!empty($sentIds)) {
            $inQuery = implode(',', array_fill(0, count($sentIds), '?'));
            $stmt = $db->prepare("UPDATE email_queue SET status = 'sent', updated_at = $nowFn WHERE id IN ($inQuery)");
            $stmt->execute($sentIds);
            $sentIds = [];
        }
        if (!empty($failedIds)) {
            $inQuery = implode(',', array_fill(0, count($failedIds), '?'));
            $stmt = $db->prepare("UPDATE email_queue SET status = 'failed', updated_at = $nowFn WHERE id IN ($inQuery)");
            $stmt->execute($failedIds);
            $failedIds = [];
        }
    };

    foreach ($emails as $email) {
        try {
            echo "Processing email ID: {$email['id']}... ";

            // Use @ to suppress warnings from mail() if sendmail is not configured
            $sent = @mail($email['to_email'], $email['subject'], $email['body'], $email['headers']);

            $msg = $sent ? "Sent.\n" : "Failed (Check mail configuration).\n";
            echo $msg;

            if ($sent) {
                $sentIds[] = $email['id'];
            } else {
                $failedIds[] = $email['id'];
            }

            if (count($sentIds) + count($failedIds) >= $batchSize) {
                $flushBatch();
            }

        } catch (Exception $e) {
            echo "Error processing email {$email['id']}: " . $e->getMessage() . "\n";
        }
    }

    // Flush remaining
    $flushBatch();
}

echo "Starting Email Queue Processor...\n";

try {
    $db = Database::getInstance()->connect();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = ($driver === 'sqlite');

    // DB specific SQL fragments
    $skipLocked = $isSqlite ? "" : "FOR UPDATE SKIP LOCKED";
    $nowFn = $isSqlite ? "datetime('now')" : "NOW()";

    // Start transaction to lock rows
    $db->beginTransaction();

    // Fetch pending emails
    $limit = (int)(getenv('EMAIL_BATCH_LIMIT') ?: 50);
    $stmt = $db->query("SELECT id, to_email, subject, body, headers FROM email_queue WHERE status = 'pending' LIMIT $limit $skipLocked");
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        $db->commit();
        echo "No pending emails found.\n";
        exit;
    }

    // Mark as processing immediately
    $ids = array_column($emails, 'id');
    $inQuery = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $db->prepare("UPDATE email_queue SET status = 'processing', updated_at = $nowFn WHERE id IN ($inQuery)");
    $updateStmt->execute($ids);

    // Commit the status change so we don't hold the lock on the 'pending' status for too long
    $db->commit();

    echo "Found " . count($emails) . " emails to process.\n";

    // Parallel Processing Logic
    $maxWorkers = 5;
    $canFork = function_exists('pcntl_fork');

    // Only fork if we have enough items to justify overhead (e.g., > 2)
    if ($canFork && count($emails) > 2) {
        $workerCount = min($maxWorkers, count($emails));
        $chunkSize = ceil(count($emails) / $workerCount);
        $chunks = array_chunk($emails, $chunkSize);
        $children = [];

        echo "Forking $workerCount workers...\n";

        foreach ($chunks as $chunk) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Failed to fork worker.\n";
            } elseif ($pid) {
                // Parent
                $children[] = $pid;
            } else {
                // Child Process
                // Important: Close existing DB connection and reconnect
                // This ensures each child has its own connection
                Database::getInstance()->conn = null;
                try {
                    $childDb = Database::getInstance()->connect();
                    processEmailsBatch($chunk, $childDb, $nowFn);
                } catch (Exception $e) {
                    echo "Worker Error: " . $e->getMessage() . "\n";
                    exit(1);
                }
                exit(0);
            }
        }

        // Parent waits for all children
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

    } else {
        // Sequential Fallback
        processEmailsBatch($emails, $db, $nowFn);
    }

} catch (Exception $e) {
    // Rollback if we are still in transaction
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
