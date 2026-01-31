<?php

function current_implementation($text, $data) {
    foreach ($data as $key => $value) {
        $text = str_replace("{{" . $key . "}}", htmlspecialchars($value), $text);
    }
    return $text;
}

function proposed_implementation($text, $data) {
    $replace_pairs = [];
    foreach ($data as $key => $value) {
        $replace_pairs["{{" . $key . "}}"] = htmlspecialchars($value);
    }
    return strtr($text, $replace_pairs);
}

function run_benchmark($label, $template, $data, $iterations) {
    echo "--------------------------------------------------\n";
    echo "Benchmark: $label\n";
    echo "Iterations: $iterations\n";

    // Warmup
    current_implementation($template, $data);
    proposed_implementation($template, $data);

    // Measure Current
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        current_implementation($template, $data);
    }
    $end = microtime(true);
    $time_current = $end - $start;
    echo "Current (Loop): " . number_format($time_current, 5) . " s\n";

    // Measure Proposed
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        proposed_implementation($template, $data);
    }
    $end = microtime(true);
    $time_proposed = $end - $start;
    echo "Proposed (strtr): " . number_format($time_proposed, 5) . " s\n";

    if ($time_current > 0) {
        $improvement = (($time_current - $time_proposed) / $time_current) * 100;
        echo "Improvement: " . number_format($improvement, 2) . "%\n";
    }
}

// 1. Small data set
$template_small = "Hello {{name}},\n\nWelcome to {{company}}. Your account {{account_id}} is now active.\n\nBest regards,\n{{sender}}";
$data_small = [
    'name' => 'John Doe',
    'company' => 'Acme Corp',
    'account_id' => '123456789',
    'sender' => 'Support Team'
];
run_benchmark("Small Data (4 items)", $template_small, $data_small, 100000);

// 2. Medium data set (20 items)
$template_med = "";
$data_med = [];
for ($i = 0; $i < 20; $i++) {
    $key = "key_$i";
    $template_med .= "This is {{" . $key . "}} value. ";
    $data_med[$key] = "Value $i";
}
run_benchmark("Medium Data (20 items)", $template_med, $data_med, 50000);

// 3. Large data set (100 items)
$template_large = "";
$data_large = [];
for ($i = 0; $i < 100; $i++) {
    $key = "key_$i";
    $template_large .= "This is {{" . $key . "}} value. ";
    $data_large[$key] = "Value $i";
}
run_benchmark("Large Data (100 items)", $template_large, $data_large, 10000);
