<?php
require_once __DIR__ . '/../core/Database.php';

echo "Starting Email Queue Processor...\n";

try {
    $db = Database::getInstance()->connect();

    // Start transaction to lock rows
    $db->beginTransaction();

    // Fetch pending emails with SKIP LOCKED to prevent race conditions
    $stmt = $db->query("SELECT id, to_email, subject, body, headers FROM email_queue WHERE status = 'pending' LIMIT 50 FOR UPDATE SKIP LOCKED");
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        $db->commit();
        echo "No pending emails found.\n";
        exit;
    }

    // Mark as processing immediately so other workers don't pick them up even if they don't use SKIP LOCKED (defensive)
    // although SKIP LOCKED handles it during the transaction.
    $ids = array_column($emails, 'id');
    $inQuery = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $db->prepare("UPDATE email_queue SET status = 'processing', updated_at = NOW() WHERE id IN ($inQuery)");
    $updateStmt->execute($ids);

    // Commit the status change so other workers can proceed with other rows
    $db->commit();

    echo "Found " . count($emails) . " emails to process.\n";

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
                // Force reconnection for the child process to avoid using parent's connection
                Database::getInstance()->conn = null;
                $childDb = Database::getInstance()->connect();

                echo "Processing email ID: {$email['id']}... ";

                // Use @ to suppress warnings from mail() if sendmail is not configured
                $sent = @mail($email['to_email'], $email['subject'], $email['body'], $email['headers']);

                if ($sent) {
                    $stmt = $childDb->prepare("UPDATE email_queue SET status = 'sent', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$email['id']]);
                    echo "Sent.\n";
                } else {
                    $stmt = $childDb->prepare("UPDATE email_queue SET status = 'failed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$email['id']]);
                    echo "Failed (Check mail configuration).\n";
                }
            } catch (Exception $e) {
                echo "Error processing email {$email['id']}: " . $e->getMessage() . "\n";
            }
            // Ensure child exits
            exit(0);
        }
    }

    // Wait for all child processes to complete
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

} catch (Exception $e) {
    // Rollback if we are still in transaction (though we commit early above)
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
