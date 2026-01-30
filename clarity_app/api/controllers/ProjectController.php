<?php
require_once __DIR__ . '/../core/Database.php';

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
            FROM users u, projects p
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

        $stmt = $this->db->prepare("SELECT id, thumb_path, original_filename FROM photos WHERE project_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?");
        $stmt->execute([$projectId, $limit, $offset]);
        
        $total = $this->db->prepare("SELECT COUNT(*) FROM photos WHERE project_id = ?");
        $total->execute([$projectId]);

        echo json_encode([
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'current_page' => $page,
                'total_pages' => ceil($total->fetchColumn() / $limit)
            ]
        ]);
    }
}
?>
