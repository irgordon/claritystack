<?php
namespace Core;

class RateLimiter {
    public static function check($ip, $limit = 5, $seconds = 60) {
        $file = sys_get_temp_dir() . '/ratelimit_' . md5($ip);
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        
        // Remove old attempts
        $data = array_filter($data, function($timestamp) use ($seconds) {
            return $timestamp > (time() - $seconds);
        });

        if (count($data) >= $limit) {
            return false; // Limit Exceeded
        }

        $data[] = time();
        file_put_contents($file, json_encode($data));
        return true;
    }
}
