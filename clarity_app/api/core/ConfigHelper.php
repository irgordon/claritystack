<?php
require_once __DIR__ . '/Database.php';

class ConfigHelper {
    private static $timeoutCache = null;

    public static function getTimeout() {
        if (self::$timeoutCache !== null) {
            return self::$timeoutCache;
        }

        $db = (new \Database())->connect();
        $stmt = $db->query("SELECT public_config FROM settings LIMIT 1");
        $row = $stmt->fetch();

        if (!$row) {
            self::$timeoutCache = 10;
            return 10;
        }
        
        $config = json_decode($row['public_config'], true);
        self::$timeoutCache = (int)($config['link_timeout'] ?? 10);

        return self::$timeoutCache;
    }
}
?>
