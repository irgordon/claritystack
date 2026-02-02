<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/SmtpClient.php';

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
 * @param array $smtpConfig Optional SMTP configuration.
 */
function processEmailsBatch(array $emails, PDO $db, string $nowFn, array $smtpConfig = []) {
    $sentIds = [];
    $failedIds = [];
    $batchSize = 10;
    $smtpClient = null;

    // Initialize SMTP Client if configured
    if (!empty($smtpConfig['SMTP_HOST']) && !empty($smtpConfig['SMTP_PORT'])) {
        try {
            echo "Connecting to SMTP server {$smtpConfig['SMTP_HOST']}:{$smtpConfig['SMTP_PORT']}...\n";
            $smtpClient = new SmtpClient(
                $smtpConfig['SMTP_HOST'],
                $smtpConfig['SMTP_PORT'],
                $smtpConfig['SMTP_USER'] ?? null,
                $smtpConfig['SMTP_PASS'] ?? null,
                30,
                $smtpConfig['SMTP_ENCRYPTION'] ?? null
            );
            $smtpClient->connect();
            echo "SMTP Connected.\n";
        } catch (Exception $e) {
            echo "SMTP Connection Error: " . $e->getMessage() . " - Falling back to mail()\n";
            $smtpClient = null;
        }
    }

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

            $sent = false;

            if ($smtpClient) {
                try {
                    // Extract 'From' address from headers if possible
                    $from = '';
                    if (preg_match('/From:\s*([^<\r\n]*<([^>\r\n]+)>|([^\r\n]+))/', $email['headers'], $matches)) {
                        $from = !empty($matches[2]) ? $matches[2] : trim($matches[3]);
                    }
                    if (empty($from)) {
                        $from = 'noreply@' . gethostname();
                    }

                    $smtpClient->send($from, $email['to_email'], $email['subject'], $email['body'], $email['headers']);
                    $sent = true;
                } catch (Exception $e) {
                    echo "SMTP Error: " . $e->getMessage() . " - Retrying with mail()... ";
                    // If SMTP fails mid-batch, we could try to reconnect, but for now fallback to mail()
                }
            }

            if (!$sent) {
                // Use @ to suppress warnings from mail() if sendmail is not configured
                $sent = @mail($email['to_email'], $email['subject'], $email['body'], $email['headers']);
            }

            $msg = $sent ? "Sent.\n" : "Failed.\n";
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

    // Close SMTP connection
    if ($smtpClient) {
        $smtpClient->quit();
    }

    // Flush remaining
    $flushBatch();
}

echo "Starting Email Queue Processor...\n";

try {
    $db = Database::getInstance()->connect();

    // Load Configuration
    $storageConfig = ConfigHelper::getStorageConfig();
    $smtpConfig = [
        'SMTP_HOST' => $storageConfig['SMTP_HOST'] ?? null,
        'SMTP_PORT' => $storageConfig['SMTP_PORT'] ?? null,
        'SMTP_USER' => $storageConfig['SMTP_USER'] ?? null,
        'SMTP_PASS' => $storageConfig['SMTP_PASS'] ?? null,
        'SMTP_ENCRYPTION' => $storageConfig['SMTP_ENCRYPTION'] ?? null,
    ];

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
                    processEmailsBatch($chunk, $childDb, $nowFn, $smtpConfig);
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
        processEmailsBatch($emails, $db, $nowFn, $smtpConfig);
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
