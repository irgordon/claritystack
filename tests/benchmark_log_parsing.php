<?php

// benchmark_log_parsing.php

$logFile = sys_get_temp_dir() . '/benchmark_clarity.log';
$targetSize = 10 * 1024 * 1024; // 10MB
$maxLines = 5000;

echo "Generating log file (~" . ($targetSize / 1024 / 1024) . " MB)...\n";

$fp = fopen($logFile, 'w');
$generatedBytes = 0;
$i = 0;
while ($generatedBytes < $targetSize) {
    $data = [
        'level' => 'INFO',
        'message' => "Log entry number $i",
        'context' => ['user_id' => rand(1, 1000), 'ip' => '127.0.0.1'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    $line = json_encode($data) . "\n";
    fwrite($fp, $line);
    $generatedBytes += strlen($line);
    $i++;
}
fclose($fp);

echo "Log file generated with $i lines.\n";

function originalParse($logFile, $maxLines) {
    $fp = fopen($logFile, 'r');
    if (!$fp) return [];

    $chunkSize = 4096;
    $pos = filesize($logFile);
    $buffer = '';
    $lineCount = 0;

    while ($pos > 0 && $lineCount < $maxLines) {
        $seek = max(0, $pos - $chunkSize);
        fseek($fp, $seek);
        $readLen = $pos - $seek;
        $chunk = fread($fp, $readLen);
        $buffer = $chunk . $buffer;
        $pos = $seek;

        // The Inefficient Part
        $lines = explode("\n", $buffer);
        $lineCount = count($lines);

        if ($lineCount > $maxLines + 1 || ($pos == 0 && $lineCount >= $maxLines)) {
             break;
        }
    }
    fclose($fp);

    $lines = explode("\n", $buffer);
    if (end($lines) === "") {
        array_pop($lines);
    }
    return array_slice($lines, -$maxLines);
}

function optimizedParse($logFile, $maxLines) {
    $fp = fopen($logFile, 'r');
    if (!$fp) return [];

    $chunkSize = 4096;
    $pos = filesize($logFile);
    $buffer = '';
    $lineCount = 0;

    while ($pos > 0 && $lineCount < $maxLines) {
        $seek = max(0, $pos - $chunkSize);
        fseek($fp, $seek);
        $readLen = $pos - $seek;
        $chunk = fread($fp, $readLen);
        $buffer = $chunk . $buffer;
        $pos = $seek;

        // Optimization: Use substr_count instead of explode
        // We add 1 because explode creates n+1 elements for n separators usually,
        // but here we are counting lines.
        // If buffer ends with \n, explode gives empty string at end.
        // Let's mimic the logic: explode("\n", "a\nb") -> 2 elements. substr_count("a\nb", "\n") -> 1.
        // So lineCount roughly equals substr_count + 1.

        $lineCount = substr_count($buffer, "\n");
        if ($buffer !== '' && substr($buffer, -1) !== "\n") {
             $lineCount++;
        }
        // Actually, the original logic does:
        // $lines = explode("\n", $buffer); $lineCount = count($lines);
        // If buffer is "foo\nbar\n", explode -> ["foo", "bar", ""], count = 3.
        // substr_count = 2.

        // Let's exact match the count logic for loop termination condition
        // explode count = substr_count($buffer, "\n") + 1
        $currentLines = substr_count($buffer, "\n") + 1;

        if ($currentLines > $maxLines + 1 || ($pos == 0 && $currentLines >= $maxLines)) {
             break;
        }
    }
    fclose($fp);

    $lines = explode("\n", $buffer);
    if (end($lines) === "") {
        array_pop($lines);
    }
    return array_slice($lines, -$maxLines);
}

// Verification
echo "Verifying Correctness...\n";
$originalResult = originalParse($logFile, $maxLines);
$optimizedResult = optimizedParse($logFile, $maxLines);

if ($originalResult === $optimizedResult) {
    echo "✅ Logic Verified: Results match exactly.\n";
} else {
    echo "❌ Verification Failed: Results differ!\n";
    echo "Original Count: " . count($originalResult) . "\n";
    echo "Optimized Count: " . count($optimizedResult) . "\n";
    exit(1);
}

// Warmup
originalParse($logFile, 5);

echo "Benchmarking Original...\n";
$start = microtime(true);
for ($j = 0; $j < 20; $j++) {
    originalParse($logFile, $maxLines);
}
$timeOriginal = microtime(true) - $start;
echo "Original Time: " . number_format($timeOriginal, 4) . "s\n";

echo "Benchmarking Optimized...\n";
$start = microtime(true);
for ($j = 0; $j < 20; $j++) {
    optimizedParse($logFile, $maxLines);
}
$timeOptimized = microtime(true) - $start;
echo "Optimized Time: " . number_format($timeOptimized, 4) . "s\n";

if ($timeOriginal > 0) {
    $improvement = (($timeOriginal - $timeOptimized) / $timeOriginal) * 100;
    echo "Improvement: " . number_format($improvement, 2) . "%\n";
}

unlink($logFile);
