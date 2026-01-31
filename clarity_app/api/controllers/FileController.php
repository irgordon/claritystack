<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/Storage/StorageFactory.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/Logger.php';

use Core\Storage\StorageFactory;
use Core\Logger;

class FileController {
    private $db;
    private $storage;

    public function __construct() {
        $this->db = \Database::getInstance()->connect();

        // Dynamic Storage Configuration
        $config = \ConfigHelper::getStorageConfig();
        $this->storage = StorageFactory::create($config);
    }

    public function view($photoId) {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) { http_response_code(401); exit; }

        // Performance Optimization: Release session lock immediately to allow parallel downloads
        session_write_close();

        $photo = null;
        $isTokenVerified = false;

        // 1. Optimization: Try Token (Signed URL)
        if (isset($_GET['token'])) {
            try {
                $payload = json_decode(\Core\Security::decrypt($_GET['token']), true);
                if ($payload &&
                    $payload['u'] == $userId &&
                    $payload['id'] == $photoId &&
                    $payload['e'] > time()) {

                    $photo = [
                        'system_filename' => $payload['s'],
                        'thumb_path' => $payload['t'],
                        'mime_type' => $payload['m']
                    ];
                    $isTokenVerified = true;
                }
            } catch (Exception $e) {
                // Invalid token, proceed to DB
            }
        }

        // 2. Lookup (Fallback)
        if (!$photo) {
            $stmt = $this->db->prepare("
                SELECT p.system_filename, p.thumb_path, p.mime_type, pr.client_email, u.email as user_email, u.role
                FROM photos p
                JOIN projects pr ON p.project_id = pr.id
                LEFT JOIN users u ON u.id = ?
                WHERE p.id = ?
            ");
            $stmt->execute([$userId, $photoId]);
            $photo = $stmt->fetch();
        }

        if (!$photo) { http_response_code(404); exit; }

        // 3. Gatekeeper (IDOR Protection)
        if (!$isTokenVerified) {
            $allowed = ($photo['role'] === 'admin') || ($photo['user_email'] === $photo['client_email']);
            if (!$allowed) { http_response_code(403); exit; }
        }

        // 4. Serve
        $isThumb = (isset($_GET['type']) && $_GET['type'] === 'thumb');
        $path = $isThumb ? $photo['thumb_path'] : $photo['system_filename'];
        
        header("Content-Type: " . ($isThumb ? 'image/jpeg' : $photo['mime_type']));
        header('Cache-Control: max-age=86400');

        // Audit Storage Access (Sampled 1% to reduce I/O)
        if (mt_rand(1, 100) === 1) {
            Logger::info("Storage Access: {$photoId}", [
                'category' => 'storage',
                'file_id' => $photoId,
                'user_id' => $userId,
                'is_thumb' => $isThumb
            ]);
        }

        $this->storage->output($path);
    }
}
?>
