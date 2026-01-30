<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';

use Core\Security;

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
            echo json_encode(['status' => 'success', 'message' => 'Storage configuration updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save settings to database.']);
        }
    }
}
?>
