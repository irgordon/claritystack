<?php
namespace Core;

class Logger {
    private static $logFile = null;

    private static function getLogFile() {
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../../logs/clarity.log';
            $dir = dirname(self::$logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return self::$logFile;
    }

    public static function log($level, $message, $context = []) {
        $file = self::getLogFile();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];

        // Use LOCK_EX to prevent interleaving
        file_put_contents($file, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
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

    public static function batchLog(array $entries) {
        $file = self::getLogFile();

        $buffer = '';
        foreach ($entries as $entry) {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => strtoupper($entry['level'] ?? 'INFO'),
                'message' => $entry['message'] ?? '',
                'context' => $entry['context'] ?? []
            ];
            $buffer .= json_encode($logEntry) . PHP_EOL;
        }

        if ($buffer !== '') {
            file_put_contents($file, $buffer, FILE_APPEND | LOCK_EX);
        }
    }
}
