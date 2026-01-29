<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Storage/LocalAdapter.php';

class FileController {
    private $db;
    private $storage;

    public function __construct() {
        $this->db = (new \Database())->connect();
        // Path to storage outside webroot
        $this->storage = new \Core\Storage\LocalAdapter('/home/clarity_user/storage_secure');
    }

    public function view($photoId) {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) { http_response_code(401); exit; }

        // 1. Lookup
        $stmt = $this->db->prepare("
            SELECT p.system_filename, p.thumb_path, p.mime_type, pr.client_email, u.email as user_email, u.role
            FROM photos p
            JOIN projects pr ON p.project_id = pr.id
            LEFT JOIN users u ON u.id = ?
            WHERE p.id = ?
        ");
        $stmt->execute([$userId, $photoId]);
        $photo = $stmt->fetch();

        if (!$photo) { http_response_code(404); exit; }

        // 2. Gatekeeper (IDOR Protection)
        $allowed = ($photo['role'] === 'admin') || ($photo['user_email'] === $photo['client_email']);
        if (!$allowed) { http_response_code(403); exit; }

        // 3. Serve
        $isThumb = (isset($_GET['type']) && $_GET['type'] === 'thumb');
        $path = $isThumb ? $photo['thumb_path'] : $photo['system_filename'];
        
        header("Content-Type: " . ($isThumb ? 'image/jpeg' : $photo['mime_type']));
        $this->storage->output($path);
    }
}
?>
