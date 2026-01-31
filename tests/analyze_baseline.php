<?php
$lines = file(__DIR__ . '/memory_baseline.log');
$parent_peak = 0;
$children_peak_sum = 0;
$child_count = 0;

foreach ($lines as $line) {
    list($pid, $role, $mem) = explode(',', trim($line));
    $mem = (int)$mem;

    if (strpos($role, 'parent') !== false) {
        if ($mem > $parent_peak) $parent_peak = $mem;
    } elseif ($role === 'child') {
        $children_peak_sum += $mem;
        $child_count++;
    }
}

$total = $parent_peak + $children_peak_sum;
echo "Parent Peak: " . number_format($parent_peak / 1024 / 1024, 2) . " MB\n";
echo "Child Processes: $child_count\n";
echo "Children Sum: " . number_format($children_peak_sum / 1024 / 1024, 2) . " MB\n";
echo "Total Footprint: " . number_format($total / 1024 / 1024, 2) . " MB\n";
?>
