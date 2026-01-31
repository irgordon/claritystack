<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';

use Core\Security;

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
            $stmt = $db->query("SELECT public_config, private_config FROM settings LIMIT 1");
            $row = $stmt->fetch();

            self::$cache = [
                'public' => [],
                'private' => []
            ];

            if ($row) {
                if (isset($row['public_config'])) {
                    self::$cache['public'] = json_decode($row['public_config'], true) ?? [];
                }
                if (isset($row['private_config'])) {
                    self::$cache['private'] = json_decode($row['private_config'], true) ?? [];
                }
            }
        } catch (Exception $e) {
            // Fallback to empty array if DB fails so the app doesn't crash completely
            error_log("ConfigHelper Error: " . $e->getMessage());
            self::$cache = ['public' => [], 'private' => []];
        }
    }

    /**
     * Returns the configured link timeout, defaulting to 10 minutes.
     */
    public static function getTimeout() {
        self::load();
        return (int)(self::$cache['public']['link_timeout'] ?? 10);
    }

    /**
     * Generic getter for other public configuration values.
     * Usage: ConfigHelper::get('primary_color', '#000000');
     */
    public static function get($key, $default = null) {
        self::load();
        return self::$cache['public'][$key] ?? $default;
    }

    /**
     * Returns the full storage configuration (Private + Public)
     * with keys mapped to uppercase for StorageFactory.
     */
    public static function getStorageConfig() {
        self::load();

        $config = [];

        // Merge public and decrypted private settings
        foreach (self::$cache['public'] as $k => $v) {
            $config[strtoupper($k)] = $v;
        }

        foreach (self::$cache['private'] as $k => $v) {
            if (!empty($v)) {
                $config[strtoupper($k)] = Security::decrypt($v);
            }
        }

        return $config;
    }
}
?>
