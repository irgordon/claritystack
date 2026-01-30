<?php
require_once __DIR__ . '/Database.php';

class ConfigHelper {
    // Static cache to persist settings in memory for the duration of the request
    private static $cache = null;

    /**
     * Lazy-loads the configuration from the database.
     */
    private static function load() {
        // If already loaded, do nothing (Memoization)
        if (self::$cache !== null) {
            return;
        }

        try {
            $db = \Database::getInstance()->connect();
            $stmt = $db->query("SELECT public_config FROM settings LIMIT 1");
            $row = $stmt->fetch();

            if ($row && isset($row['public_config'])) {
                self::$cache = json_decode($row['public_config'], true);
            } else {
                self::$cache = []; // Default to empty if no settings exist
            }
        } catch (Exception $e) {
            // Fallback to empty array if DB fails so the app doesn't crash completely
            error_log("ConfigHelper Error: " . $e->getMessage());
            self::$cache = [];
        }
    }

    /**
     * Returns the configured link timeout, defaulting to 10 minutes.
     */
    public static function getTimeout() {
        self::load();
        return (int)(self::$cache['link_timeout'] ?? 10);
    }

    /**
     * Generic getter for other public configuration values.
     * Usage: ConfigHelper::get('primary_color', '#000000');
     */
    public static function get($key, $default = null) {
        self::load();
        return self::$cache[$key] ?? $default;
    }
}
?>
