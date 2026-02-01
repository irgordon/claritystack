<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/CacheService.php';

use Core\Security;
use Core\CacheService;

class ConfigHelper {
    private static $storageCache = null;

    /**
     * Loads the configuration from cache or database.
     */
    private static function load() {
        return CacheService::remember('config', 'settings', 0, function() {
            try {
                $db = \Database::getInstance()->connect();
                $stmt = $db->query("SELECT business_name, public_config, private_config, updated_at FROM settings LIMIT 1");
                $row = $stmt->fetch();

                $cache = [
                    'public' => [],
                    'private' => [],
                    'business_name' => '',
                    'updated_at' => '0'
                ];

                if ($row) {
                    if (isset($row['public_config'])) {
                        $cache['public'] = json_decode($row['public_config'], true) ?? [];
                    }
                    if (isset($row['private_config'])) {
                        $cache['private'] = json_decode($row['private_config'], true) ?? [];
                    }
                    if (isset($row['business_name'])) {
                        $cache['business_name'] = $row['business_name'];
                    }
                    if (isset($row['updated_at'])) {
                        $cache['updated_at'] = $row['updated_at'];
                    }
                }
                return $cache;

            } catch (Exception $e) {
                // Fallback to empty array if DB fails so the app doesn't crash completely
                error_log("ConfigHelper Error: " . $e->getMessage());
                return ['public' => [], 'private' => [], 'business_name' => '', 'updated_at' => '0'];
            }
        });
    }

    public static function clearCache() {
        CacheService::delete('config', 'settings');
        self::$storageCache = null;
    }

    /**
     * Returns the configured link timeout, defaulting to 10 minutes.
     */
    public static function getTimeout() {
        $data = self::load();
        return (int)($data['public']['link_timeout'] ?? 10);
    }

    /**
     * Generic getter for other public configuration values.
     * Usage: ConfigHelper::get('primary_color', '#000000');
     */
    public static function get($key, $default = null) {
        $data = self::load();
        return $data['public'][$key] ?? $default;
    }

    /**
     * Returns the business name.
     */
    public static function getBusinessName() {
        $data = self::load();
        return $data['business_name'] ?? '';
    }

    /**
     * Returns the settings updated_at timestamp.
     */
    public static function getUpdatedAt() {
        $data = self::load();
        return $data['updated_at'] ?? '0';
    }

    /**
     * Returns the entire public config array.
     */
    public static function getPublicConfig() {
        $data = self::load();
        return $data['public'] ?? [];
    }

    /**
     * Returns the full storage configuration (Private + Public)
     * with keys mapped to uppercase for StorageFactory.
     */
    public static function getStorageConfig() {
        if (self::$storageCache !== null) {
            return self::$storageCache;
        }

        $data = self::load();

        $config = [];

        // Merge public and decrypted private settings
        foreach ($data['public'] as $k => $v) {
            $config[strtoupper($k)] = $v;
        }

        foreach ($data['private'] as $k => $v) {
            if (!empty($v)) {
                $config[strtoupper($k)] = Security::decrypt($v);
            }
        }

        self::$storageCache = $config;
        return $config;
    }
}
?>
