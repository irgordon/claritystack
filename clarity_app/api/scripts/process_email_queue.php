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

    // Prepare statements once outside the loop
    $sentStmt = $db->prepare("UPDATE email_queue SET status = 'sent', updated_at = NOW() WHERE id = ?");
    $failedStmt = $db->prepare("UPDATE email_queue SET status = 'failed', updated_at = NOW() WHERE id = ?");

    foreach ($emails as $email) {
        echo "Processing email ID: {$email['id']}... ";

        // Use @ to suppress warnings from mail() if sendmail is not configured
        $sent = @mail($email['to_email'], $email['subject'], $email['body'], $email['headers']);

        if ($sent) {
            $sentStmt->execute([$email['id']]);
            echo "Sent.\n";
        } else {
            $failedStmt->execute([$email['id']]);
            echo "Failed (Check mail configuration).\n";
        }
    }

} catch (Exception $e) {
    // Rollback if we are still in transaction (though we commit early above)
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
