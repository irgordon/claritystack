<?php
$iterations = 500000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $t = time() + 7200;
}
$end = microtime(true);
echo "time() x $iterations: " . ($end - $start) . " seconds\n";

$start = microtime(true);
$t_base = time() + 7200;
for ($i = 0; $i < $iterations; $i++) {
    $t = $t_base;
}
$end = microtime(true);
echo "cached time x $iterations: " . ($end - $start) . " seconds\n";
