<?php
namespace Core;

class CacheService {
    private static $baseDir = null;
    private static $memoryCache = [];
    private static $metrics = ['hits' => 0, 'misses' => 0, 'writes' => 0];

    public static function init() {
        if (self::$baseDir === null) {
            // Scope cache by application path to prevent collisions
            self::$baseDir = sys_get_temp_dir() . '/clarity_cache_' . md5(__DIR__);
            if (!is_dir(self::$baseDir)) {
                @mkdir(self::$baseDir, 0700, true);
            }
        }
    }

    /**
     * Get a value from cache. If missing or expired, execute callback and cache result.
     *
     * @param string $namespace  Cache namespace (folder name)
     * @param string $key        Cache key
     * @param int|null $ttl      Time to live in seconds (null for infinite/managed by key)
     * @param callable $callback Function to generate value if miss
     * @return mixed
     */
    public static function remember($namespace, $key, $ttl, callable $callback) {
        $val = self::get($namespace, $key);
        if ($val !== null) {
            self::$metrics['hits']++;
            return $val;
        }

        self::$metrics['misses']++;
        $val = $callback();
        self::set($namespace, $key, $val, $ttl);
        return $val;
    }

    /**
     * Get a value from cache if it exists and hasn't expired.
     */
    public static function get($namespace, $key) {
        self::init();

        $memKey = "$namespace:$key";

        // L1: Memory Cache
        if (array_key_exists($memKey, self::$memoryCache)) {
            $cached = self::$memoryCache[$memKey];
            if ($cached['expires'] === 0 || $cached['expires'] > time()) {
                return $cached['payload'];
            }
            // Expired in memory
            unset(self::$memoryCache[$memKey]);
        }

        $file = self::getFilePath($namespace, $key);

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                // Use JSON for security
                $data = json_decode($content, true);

                if ($data && is_array($data) && array_key_exists('expires', $data) && array_key_exists('payload', $data)) {
                    // Check Expiry (if not 0/null)
                    if ($data['expires'] === 0 || $data['expires'] > time()) {
                        self::$memoryCache[$memKey] = $data; // Store full data in memory
                        return $data['payload'];
                    } else {
                         // Expired
                         @unlink($file);
                    }
                }
            }
        }

        return null;
    }

    public static function set($namespace, $key, $value, $ttl = null) {
        self::init();
        self::$metrics['writes']++;

        $dir = self::$baseDir . '/' . $namespace;
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $file = self::getFilePath($namespace, $key);

        $expiry = ($ttl === null || $ttl === 0) ? 0 : (time() + $ttl);

        $data = [
            'expires' => $expiry,
            'payload' => $value
        ];

        // Use JSON for security
        $content = json_encode($data);

        // Atomic Write
        $tempFile = tempnam($dir, 'tmp_');
        if ($tempFile !== false) {
            chmod($tempFile, 0600); // Secure permissions
            if (file_put_contents($tempFile, $content) !== false) {
                @rename($tempFile, $file);

                // Update L1
                self::$memoryCache["$namespace:$key"] = $data;
                return true;
            } else {
                @unlink($tempFile);
            }
        }
        return false;
    }

    public static function delete($namespace, $key) {
        self::init();
        $file = self::getFilePath($namespace, $key);
        if (file_exists($file)) {
            @unlink($file);
        }
        unset(self::$memoryCache["$namespace:$key"]);
    }

    public static function flush($namespace = null) {
        self::init();
        if ($namespace) {
            $dir = self::$baseDir . '/' . $namespace;
            self::recursiveDelete($dir);
            // Clear memory for this namespace
            foreach (self::$memoryCache as $k => $v) {
                if (strpos($k, "$namespace:") === 0) {
                    unset(self::$memoryCache[$k]);
                }
            }
        } else {
            self::recursiveDelete(self::$baseDir);
            self::$memoryCache = [];
        }
    }

    public static function getMetrics() {
        return self::$metrics;
    }

    private static function getFilePath($namespace, $key) {
        // Sanitize key (hash it to ensure safe filename)
        $safeKey = md5($key);
        return self::$baseDir . '/' . $namespace . '/' . $safeKey . '.cache';
    }

    private static function recursiveDelete($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
