<?php
namespace Core;

class Logger {
    private static $logFile = __DIR__ . '/../../logs/clarity.log';

    public static function log($level, $message, $context = []) {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];

        // atomic write not strictly necessary for logs, but FILE_APPEND is atomic enough for lines usually
        file_put_contents(self::$logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }

    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }

    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }

    public static function critical($message, $context = []) {
        self::log('CRITICAL', $message, $context);
    }
}
?>
