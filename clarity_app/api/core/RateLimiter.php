<?php
namespace Core;

use PDO;
use Exception;

class RateLimiter {
    private static $pdo = null;

    private static function getPdo() {
        if (self::$pdo === null) {
            try {
                $file = sys_get_temp_dir() . '/global_ratelimit.sqlite';
                // Check if file is missing or empty (handling race where PDO creates 0-byte file)
                $initialize = !file_exists($file) || @filesize($file) === 0;

                self::$pdo = new PDO("sqlite:$file");
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                if ($initialize) {
                    // create table if not exists
                    self::$pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                        ip TEXT,
                        timestamp INTEGER
                    )");

                    // Indexes for performance
                    // Index for counting attempts by IP in a time window
                    self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ip_timestamp ON rate_limits(ip, timestamp)");

                    // Index for cleanup query (DELETE WHERE timestamp <= ?)
                    self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_timestamp ON rate_limits(timestamp)");

                    // Enable WAL mode for better concurrency performance
                    self::$pdo->exec("PRAGMA journal_mode = WAL;");
                }

                self::$pdo->exec("PRAGMA synchronous = NORMAL;");
            } catch (Exception $e) {
                // If DB fails, fail open to avoid blocking users
                error_log("RateLimiter SQLite error: " . $e->getMessage());
                return null;
            }
        }
        return self::$pdo;
    }

    public static function check($ip, $limit = 5, $seconds = 60) {
        $pdo = self::getPdo();
        if (!$pdo) {
            return true; // Fail open
        }

        $now = time();
        $cutoff = $now - $seconds;

        try {
            // Probabilistic cleanup (1 in 50 chance)
            if (mt_rand(1, 50) === 1) {
                $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE timestamp <= ?");
                $stmt->execute([$cutoff]);
            }

            // Count attempts in window
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND timestamp > ?");
            $stmt->execute([$ip, $cutoff]);
            $count = (int)$stmt->fetchColumn();

            if ($count >= $limit) {
                return false;
            }

            // Record new attempt
            $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, timestamp) VALUES (?, ?)");
            $stmt->execute([$ip, $now]);

            return true;
        } catch (Exception $e) {
            // If something goes wrong during query, fail open
            return true;
        }
    }
}
