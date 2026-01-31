<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/EmailService.php';
require_once __DIR__ . '/../core/Logger.php';

use Core\Logger;
use Core\EmailService;

// 1. Log Rotation
$logFile = __DIR__ . '/../../logs/clarity.log';
$maxSize = 5 * 1024 * 1024; // 5MB

if (file_exists($logFile) && filesize($logFile) > $maxSize) {
    for ($i = 4; $i >= 1; $i--) {
        $old = $logFile . '.' . $i;
        $new = $logFile . '.' . ($i + 1);
        if (file_exists($old)) {
            rename($old, $new);
        }
    }
    if (file_exists($logFile . '.1')) {
        rename($logFile . '.1', $logFile . '.2');
    }

    rename($logFile, $logFile . '.1');
    // Create new empty file
    touch($logFile);
    Logger::info('Log rotation performed.');
}

// 2. Weekly Report
$lastReportFile = sys_get_temp_dir() . '/last_weekly_report';
$lastReport = file_exists($lastReportFile) ? (int)file_get_contents($lastReportFile) : 0;

if (time() - $lastReport > (7 * 86400)) {
    // Run Weekly Check
    try {
        $db = \Database::getInstance()->connect();

        // Check/Create Template
        $stmt = $db->prepare("SELECT id FROM email_templates WHERE key_name = ?");
        $stmt->execute(['weekly_report']);
        if (!$stmt->fetch()) {
            $subject = "Weekly Clarity Health Report";
            $body = "<h2>Weekly Health Report</h2><p>Hello Admin,</p><p>This is your frictionless weekly update.</p><p><strong>Critical/High Errors:</strong> {{error_count}}</p><p>System is operational. {{quote}}</p>";
            $db->prepare("INSERT INTO email_templates (key_name, subject, body_content) VALUES (?, ?, ?)")
               ->execute(['weekly_report', $subject, $body]);
        }

        // Scan Logs (Simplified: just check current log file for now)
        $errorCount = 0;
        if (file_exists($logFile)) {
            $handle = fopen($logFile, "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (strpos($line, '"level":"ERROR"') !== false || strpos($line, '"level":"CRITICAL"') !== false) {
                        $errorCount++;
                    }
                }
                fclose($handle);
            }
        }

        if ($errorCount >= 0) { // Send anyway for health check? "Professional, frictionless" might imply only when needed, but prompt says "on critical or high errors".
            // Prompt: "weekly... emails... on critical or high errors".
            if ($errorCount > 0) {
                 // Find Admin
                $adminStmt = $db->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
                $admin = $adminStmt->fetch();

                if ($admin) {
                    EmailService::send($admin['email'], 'weekly_report', [
                        'error_count' => $errorCount,
                        'quote' => '"The best way to predict the future is to create it." - A. Lincoln'
                    ]);
                    Logger::info("Weekly report sent to " . $admin['email']);
                }
            }
        } else {
            Logger::info("Weekly report skipped (no errors).");
        }

        // Update timestamp
        file_put_contents($lastReportFile, time());
    } catch (Exception $e) {
        Logger::error("Maintenance Script Error: " . $e->getMessage());
    }
}
?>
