<?php
require_once __DIR__ . '/../core/Database.php';

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
    $stmt = $db->query("SELECT id, to_email, subject, body, headers FROM email_queue WHERE status = 'pending' LIMIT 50 $skipLocked");
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

    // Process sequentially (Optimization: Removed forking to save memory)
    foreach ($emails as $email) {
        try {
            echo "Processing email ID: {$email['id']}... ";

            // Use @ to suppress warnings from mail() if sendmail is not configured
            $sent = @mail($email['to_email'], $email['subject'], $email['body'], $email['headers']);

            $status = $sent ? 'sent' : 'failed';
            $msg = $sent ? "Sent.\n" : "Failed (Check mail configuration).\n";

            $stmt = $db->prepare("UPDATE email_queue SET status = ?, updated_at = $nowFn WHERE id = ?");
            $stmt->execute([$status, $email['id']]);
            echo $msg;

        } catch (Exception $e) {
            echo "Error processing email {$email['id']}: " . $e->getMessage() . "\n";
        }
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
