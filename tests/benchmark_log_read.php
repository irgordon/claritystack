<?php

// tests/benchmark_log_read.php

function generate_large_log($filename, $lines = 100000) {
    $fp = fopen($filename, 'w');
    for ($i = 0; $i < $lines; $i++) {
        $data = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'INFO',
            'message' => "This is log line number $i",
            'context' => ['user_id' => rand(1, 1000)]
        ]);
        fwrite($fp, $data . "\n");
    }
    fclose($fp);
}

function old_method($logFile) {
    $start_mem = memory_get_usage();
    $start_time = microtime(true);

    if (!file_exists($logFile)) {
        return [];
    }

    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    $logs = [];
    foreach ($lastLines as $line) {
        $decoded = json_decode($line, true);
        if ($decoded) {
            $logs[] = $decoded;
        }
    }
    $result = array_reverse($logs);

    $end_time = microtime(true);
    $peak_mem = memory_get_peak_usage();

    return [
        'count' => count($result),
        'time' => ($end_time - $start_time) * 1000, // ms
        'memory' => ($peak_mem - $start_mem) / 1024 / 1024 // MB
    ];
}

function new_method($logFile) {
    $start_mem = memory_get_usage();
    $start_time = microtime(true);

    if (!file_exists($logFile)) {
        return [];
    }

    $fp = fopen($logFile, 'r');
    if (!$fp) return [];

    // Re-implementing a robust `tail` logic for the benchmark
    $logs = [];
    $chunkSize = 1024;
    $pos = filesize($logFile);
    $buffer = '';
    $lineCount = 0;
    $maxLines = 50;

    while ($pos > 0 && $lineCount < $maxLines) {
        $seek = max(0, $pos - $chunkSize);
        fseek($fp, $seek);
        $readLen = $pos - $seek;
        $chunk = fread($fp, $readLen);
        $buffer = $chunk . $buffer;
        $pos = $seek;

        // Count newlines
        $lines = explode("\n", $buffer);
        $lineCount = count($lines);

        // If we have enough lines (excluding the first potentially partial one)
        if ($lineCount > $maxLines + 1 || ($pos == 0 && $lineCount >= $maxLines)) {
             break;
        }
    }

    // Process the buffer
    $lines = explode("\n", $buffer);

    // Remove empty last line if file ends with newline
    if (end($lines) === "") {
        array_pop($lines);
    }

    // Take last 50
    $lastLines = array_slice($lines, -$maxLines);

    foreach ($lastLines as $line) {
        if (trim($line) === '') continue;
        $decoded = json_decode($line, true);
        if ($decoded) {
            $logs[] = $decoded;
        }
    }

    $result = array_reverse($logs);

    fclose($fp);

    $end_time = microtime(true);
    $peak_mem = memory_get_peak_usage();

    return [
        'count' => count($result),
        'time' => ($end_time - $start_time) * 1000, // ms
        'memory' => ($peak_mem - $start_mem) / 1024 / 1024 // MB
    ];
}

// Main Execution
$tempFile = sys_get_temp_dir() . '/large_test.log';
echo "Generating 100k log lines (~10-15MB)...\n";
generate_large_log($tempFile, 100000);

echo "Running New Method...\n";
// Force GC
gc_collect_cycles();
$new = new_method($tempFile);
echo "New Method: Time: {$new['time']} ms, Memory: {$new['memory']} MB\n";

echo "Running Old Method...\n";
// Force GC
gc_collect_cycles();
$old = old_method($tempFile);
echo "Old Method: Time: {$old['time']} ms, Memory: {$old['memory']} MB\n";

if ($old['count'] !== $new['count']) {
    echo "WARNING: Count mismatch! Old: {$old['count']}, New: {$new['count']}\n";
} else {
    echo "Counts match: {$old['count']}\n";
}

// Clean up
unlink($tempFile);
