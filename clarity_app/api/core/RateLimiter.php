<?php
namespace Core;

use PDO;
use Exception;
use PDOException;

class RateLimiter {
    private static $pdo = null;

    private static function getPdo() {
        if (self::$pdo === null) {
            try {
                $file = sys_get_temp_dir() . '/global_ratelimit.sqlite';

                // Optimized: Removed filesystem checks and conditional schema initialization
                self::$pdo = new PDO("sqlite:$file", null, null, [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                self::$pdo->exec("PRAGMA synchronous = NORMAL;");
            } catch (Exception $e) {
                // If DB fails, fail open to avoid blocking users
                error_log("RateLimiter SQLite error: " . $e->getMessage());
                return null;
            }
        }
        return self::$pdo;
    }

    private static function initialize(PDO $pdo) {
        // create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            ip TEXT,
            timestamp INTEGER
        )");

        // Indexes for performance
        // Index for counting attempts by IP in a time window
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ip_timestamp ON rate_limits(ip, timestamp)");

        // Index for cleanup query (DELETE WHERE timestamp <= ?)
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_timestamp ON rate_limits(timestamp)");

        // Enable WAL mode for better concurrency performance
        $pdo->exec("PRAGMA journal_mode = WAL;");
    }

    private static function attemptCheck(PDO $pdo, $ip, $limit, $cutoff, $now) {
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
    }

    public static function check($ip, $limit = 5, $seconds = 60) {
        $pdo = self::getPdo();
        if (!$pdo) {
            return true; // Fail open
        }

        $now = time();
        $cutoff = $now - $seconds;

        try {
            return self::attemptCheck($pdo, $ip, $limit, $cutoff, $now);
        } catch (PDOException $e) {
            // Lazy initialization: only create table if it doesn't exist
            if (strpos($e->getMessage(), 'no such table') !== false) {
                try {
                    self::initialize($pdo);
                    return self::attemptCheck($pdo, $ip, $limit, $cutoff, $now);
                } catch (Exception $ex) {
                    // If initialization or retry fails, fail open
                    return true;
                }
            }
            // Other DB errors
            return true;
        } catch (Exception $e) {
            // If something goes wrong during query, fail open
            return true;
        }
    }
}
