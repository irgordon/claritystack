<?php
require_once __DIR__ . '/../core/Database.php';

class ProjectController {
    private $db;
    public function __construct() { $this->db = (new \Database())->connect(); }

    public function listPhotos($projectId) {
        // [Add Auth Check Here]
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("SELECT id, thumb_path FROM photos WHERE project_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?");
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
