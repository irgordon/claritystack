<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';

class ProjectController {
    private $db;
    public function __construct() { $this->db = \Database::getInstance()->connect(); }

    public function listPhotos($projectId) {
        // 1. Authentication Check
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 2. Authorization Check (IDOR Protection)
        // Verify if user is admin or the project owner
        $authStmt = $this->db->prepare("
            SELECT u.role, u.email as user_email, p.client_email
            FROM users u
            CROSS JOIN projects p
            WHERE u.id = ? AND p.id = ?
        ");
        $authStmt->execute([$userId, $projectId]);
        $access = $authStmt->fetch(PDO::FETCH_ASSOC);

        if (!$access) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }

        $isAllowed = ($access['role'] === 'admin') || ($access['user_email'] === $access['client_email']);

        if (!$isAllowed) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("SELECT id, thumb_path, original_filename, system_filename, mime_type FROM photos WHERE project_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?");
        $stmt->execute([$projectId, $limit, $offset]);
        
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enhance with signed tokens to bypass DB lookup in FileController
        $payloads = [];
        $expiry = time() + 7200; // 2 hours expiry
        foreach ($photos as $photo) {
            $payload = [
                'id' => $photo['id'],
                's' => $photo['system_filename'],
                't' => $photo['thumb_path'],
                'm' => $photo['mime_type'],
                'u' => $userId,
                'e' => $expiry
            ];
            $payloads[] = json_encode($payload);
        }

        $tokens = \Core\Security::encryptBatch($payloads);

        foreach ($photos as $index => &$photo) {
            $photo['token'] = $tokens[$index];

            // Clean up internal paths from response
            unset($photo['system_filename']);
            unset($photo['mime_type']);
        }
        unset($photo);

        $total = $this->db->prepare("SELECT COUNT(*) FROM photos WHERE project_id = ?");
        $total->execute([$projectId]);

        echo json_encode([
            'data' => $photos,
            'meta' => [
                'current_page' => $page,
                'total_pages' => ceil($total->fetchColumn() / $limit)
            ]
        ]);
    }
}
?>
