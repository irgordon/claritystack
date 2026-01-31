<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ConfigHelper.php';

class EmailService {
    private static $templateCache = [];

    public static function send($toEmail, $templateKey, $data = []) {
        $db = \Database::getInstance()->connect();

        // 1. Fetch Template
        if (isset(self::$templateCache[$templateKey])) {
            $template = self::$templateCache[$templateKey];
        } else {
            $stmt = $db->prepare("SELECT subject, body_content FROM email_templates WHERE key_name = ?");
            $stmt->execute([$templateKey]);
            $template = $stmt->fetch();
            if ($template) {
                self::$templateCache[$templateKey] = $template;
            }
        }

        if (!$template) {
            error_log("Email Error: Template '$templateKey' not found.");
            return false;
        }

        // 2. Fetch Branding
        $config = ConfigHelper::getPublicConfig();
        $businessName = ConfigHelper::getBusinessName();
        
        $brandColor = $config['primary_color'] ?? '#3b82f6';
        $logoUrl = $config['logo_url'] ?? '';
        $fromEmail = $config['no_reply_email'] ?? 'no-reply@' . $_SERVER['HTTP_HOST'];

        // 3. Merge Data
        $subject = self::merge($template['subject'], $data);
        $rawBody = self::merge($template['body_content'], $data);

        // 4. Wrap HTML
        $html = self::wrapHtml($rawBody, $businessName, $logoUrl, $brandColor);

        // 5. Queue Email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$businessName} <{$fromEmail}>" . "\r\n";

        try {
            $stmt = $db->prepare("INSERT INTO email_queue (to_email, subject, body, headers, status) VALUES (?, ?, ?, ?, 'pending')");
            return $stmt->execute([$toEmail, $subject, $html, $headers]);
        } catch (Exception $e) {
            error_log("Email Queue Error: " . $e->getMessage());
            return false;
        }
    }

    private static function merge($text, $data) {
        foreach ($data as $key => $value) {
            $text = str_replace("{{" . $key . "}}", htmlspecialchars($value), $text);
        }
        return $text;
    }

    private static function wrapHtml($content, $name, $logo, $color) {
        $logoImg = $logo ? "<img src='$logo' alt='$name' style='max-height: 50px;'>" : "<h1>$name</h1>";
        return "
        <!DOCTYPE html>
        <html>
        <body style='font-family: sans-serif; background-color: #f3f4f6; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: $color; padding: 20px; text-align: center; color: white;'>$logoImg</div>
                <div style='padding: 30px; line-height: 1.6; color: #333;'>$content</div>
                <div style='background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280;'>
                    &copy; " . date('Y') . " $name.
                </div>
            </div>
        </body>
        </html>";
    }
}
?>
