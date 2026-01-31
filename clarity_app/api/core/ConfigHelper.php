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

        $cacheFile = self::getCacheFile();
        if (file_exists($cacheFile)) {
            $data = include $cacheFile;
            if (is_array($data)) {
                self::$cache = $data;
                return;
            }
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

            // Write to file cache (Atomic)
            $tempFile = tempnam(sys_get_temp_dir(), 'clarity_tmp');
            if ($tempFile) {
                chmod($tempFile, 0600); // Secure before writing content
                $content = "<?php return " . var_export(self::$cache, true) . ";";
                if (file_put_contents($tempFile, $content) !== false) {
                    rename($tempFile, $cacheFile);
                    // Invalidate OPcache to ensure the new file is read immediately
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($cacheFile, true);
                    }
                } else {
                    @unlink($tempFile);
                }
            }

        } catch (Exception $e) {
            // Fallback to empty array if DB fails so the app doesn't crash completely
            error_log("ConfigHelper Error: " . $e->getMessage());
            self::$cache = ['public' => [], 'private' => []];
        }
    }

    private static function getCacheFile() {
        // Use a unique hash based on the directory to prevent collisions in shared environments
        return sys_get_temp_dir() . '/clarity_config_' . md5(__DIR__) . '.php';
    }

    public static function clearCache() {
        $file = self::getCacheFile();
        if (file_exists($file)) {
            unlink($file);
        }
        self::$cache = null;
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
