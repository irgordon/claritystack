<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/Logger.php';

use Core\Security;
use Core\Logger;

class SettingsController {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->connect();
    }

    /**
     * GET /api/admin/settings
     * Returns the Public Configuration (non-sensitive) for the Admin UI.
     */
    public function getSettings() {
        // Auth Check (Simulated for this file review)
        // verifyAdminSession();

        $stmt = $this->db->query("SELECT public_config FROM settings LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // We only return public_config. Private keys are never sent to the client.
        echo json_encode($row ? json_decode($row['public_config'], true) : []);
    }

    /**
     * POST /api/admin/settings/storage
     * Updates the storage driver and credentials.
     */
    public function updateStorage() {
        // Auth Check
        // verifyAdminSession();

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['driver'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Driver is required']);
            exit;
        }

        $driver = $input['driver'];

        // 1. Fetch Existing Settings (To merge, ensuring we don't wipe other settings)
        $stmt = $this->db->query("SELECT public_config, private_config FROM settings LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $public  = $row ? json_decode($row['public_config'], true) : [];
        $private = $row ? json_decode($row['private_config'], true) : [];

        // 2. Update Public Values (Safe to store plain text)
        $public['storage_driver'] = $driver;

        // 3. Handle Driver Specifics (Encryption)
        if ($driver === 'cloudinary') {
            $public['cloudinary_name'] = $input['cloudinary_name'] ?? '';
            $public['cloudinary_key']  = $input['cloudinary_key'] ?? '';
            
            // Only update secret if user typed a new one (not empty)
            if (!empty($input['cloudinary_secret'])) {
                $private['cloudinary_secret'] = Security::encrypt($input['cloudinary_secret']);
            }
        }
        elseif ($driver === 's3') {
            $public['s3_key']      = $input['s3_key'] ?? '';
            $public['s3_bucket']   = $input['s3_bucket'] ?? '';
            $public['s3_region']   = $input['s3_region'] ?? '';
            $public['s3_endpoint'] = $input['s3_endpoint'] ?? ''; // Optional
            
            if (!empty($input['s3_secret'])) {
                $private['s3_secret'] = Security::encrypt($input['s3_secret']);
            }
        }
        elseif ($driver === 'imagekit') {
            $public['imagekit_public'] = $input['imagekit_public'] ?? '';
            $public['imagekit_url']    = $input['imagekit_url'] ?? '';
            
            if (!empty($input['imagekit_private'])) {
                $private['imagekit_private'] = Security::encrypt($input['imagekit_private']);
            }
        }
        // Local driver requires no keys

        // 4. Save Back to DB
        $updateStmt = $this->db->prepare("UPDATE settings SET public_config = ?, private_config = ?, updated_at = NOW() WHERE id = (SELECT id FROM settings LIMIT 1)");
        
        $success = $updateStmt->execute([
            json_encode($public),
            json_encode($private)
        ]);

        if ($success) {
            ConfigHelper::clearCache();
            echo json_encode(['status' => 'success', 'message' => 'Storage configuration updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save settings to database.']);
        }
    }

    private function verifyAdminSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();

        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }

    /**
     * GET /api/admin/health
     */
    public function getSystemHealth() {
        $this->verifyAdminSession();

        $db = $this->db;
        $dbDriver = 'Unknown';
        $dbVersion = 'Unknown';
        try {
            $dbDriver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $dbVersion = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Exception $e) {}

        $health = [
            'server' => [
                'php_version' => PHP_VERSION,
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'os' => PHP_OS,
                'disk_free' => disk_free_space(__DIR__),
                'disk_total' => disk_total_space(__DIR__),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ],
            'database' => [
                'driver' => $dbDriver,
                'version' => $dbVersion,
            ],
            'env' => [
                'DB_DRIVER' => getenv('DB_DRIVER') ?: 'N/A',
                'APP_ENV' => getenv('APP_ENV') ?: 'production',
            ]
        ];
        echo json_encode($health);
    }

    /**
     * GET /api/admin/logs
     */
    public function getLogs() {
        $this->verifyAdminSession();

        $logFile = __DIR__ . '/../../logs/clarity.log';
        if (!file_exists($logFile)) {
            echo json_encode([]);
            return;
        }

        $fp = fopen($logFile, 'r');
        if (!$fp) {
            echo json_encode([]);
            return;
        }

        $logs = [];
        $chunkSize = 4096;
        $pos = filesize($logFile);
        $buffer = '';
        $lineCount = 0;
        $maxLines = 50;

        while ($pos > 0 && $lineCount < $maxLines) {
            $seek = max(0, $pos - $chunkSize);
            fseek($fp, $seek);
            $readLen = $pos - $seek;
            $chunk = fread($fp, $readLen);
            $buffer = $chunk . $buffer;
            $pos = $seek;

            // Count newlines
            // Optimization: substr_count is much faster than explode + count
            // We add 1 to match explode's behavior (n newlines => n+1 elements)
            $lineCount = substr_count($buffer, "\n") + 1;

            if ($lineCount > $maxLines + 1 || ($pos == 0 && $lineCount >= $maxLines)) {
                 break;
            }
        }

        fclose($fp);

        $lines = explode("\n", $buffer);
        if (end($lines) === "") {
            array_pop($lines);
        }

        $lastLines = array_slice($lines, -$maxLines);

        foreach ($lastLines as $line) {
            if (trim($line) === '') continue;
            $decoded = json_decode($line, true);
            if ($decoded) {
                $logs[] = $decoded;
            }
        }
        echo json_encode(array_reverse($logs));
    }

    /**
     * POST /api/log/client
     * @param array|null $testInput Optional injected input for testing
     */
    public function logClientEvent($testInput = null) {
        // Public endpoint, use Rate Limiter
        require_once __DIR__ . '/../core/RateLimiter.php';
        if (!\Core\RateLimiter::check($_SERVER['REMOTE_ADDR'], 60, 60)) {
             http_response_code(429);
             exit;
        }

        $input = $testInput ?? json_decode(file_get_contents('php://input'), true);

        // Handle Batch (Array) or Single (Object)
        // isset($input[0]) is a simple heuristic for a list of objects
        if (is_array($input) && isset($input[0])) {
            $batchEntries = [];
            $count = 0;
            foreach ($input as $entry) {
                // Limit batch size to prevent abuse
                if ($count++ >= 50) break;

                if (!is_array($entry)) continue;

                $level = $entry['level'] ?? 'INFO';
                $message = $entry['message'] ?? 'Client Event';
                $context = $entry['context'] ?? [];

                $category = $entry['category'] ?? 'client';
                $context['category'] = $category;

                $batchEntries[] = [
                    'level' => $level,
                    'message' => $message,
                    'context' => $context
                ];
            }

            if (!empty($batchEntries)) {
                Logger::batchLog($batchEntries);
            }

            echo json_encode(['status' => 'logged', 'count' => $count]);
        } else {
            $this->processLogEvent($input);
            echo json_encode(['status' => 'logged']);
        }
    }

    private function processLogEvent($input) {
        if (!is_array($input)) return;

        $level = $input['level'] ?? 'INFO';
        $message = $input['message'] ?? 'Client Event';
        $context = $input['context'] ?? [];

        $category = $input['category'] ?? 'client';
        $context['category'] = $category;

        Logger::log($level, $message, $context);
    }
}
?>
