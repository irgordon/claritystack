<?php
require_once __DIR__ . '/Database.php';

class ConfigHelper {
    public static function getTimeout() {
        $db = (new \Database())->connect();
        $stmt = $db->query("SELECT public_config FROM settings LIMIT 1");
        $row = $stmt->fetch();
        if (!$row) return 10;
        
        $config = json_decode($row['public_config'], true);
        return (int)($config['link_timeout'] ?? 10);
    }
}
?>
