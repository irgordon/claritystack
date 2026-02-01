<?php
// ... existing content ...

$iterations = 50000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    random_bytes(24);
}
$end = microtime(true);
echo "random_bytes(24) x $iterations: " . ($end - $start) . " seconds\n";

$start = microtime(true);
$bytes = random_bytes(24 * $iterations);
for ($i = 0; $i < $iterations; $i++) {
    substr($bytes, $i * 24, 24);
}
$end = microtime(true);
echo "random_bytes(batch) + substr x $iterations: " . ($end - $start) . " seconds\n";
