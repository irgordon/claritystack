<?php

// Database Setup
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Tables
    $pdo->exec("
        CREATE TABLE auth_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            selector CHAR(24) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    echo "Seeding data... (this may take a moment)\n";
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");

    $selectors = [];
    $num_tokens = 100000;

    // Create 100,000 tokens
    for ($i = 0; $i < $num_tokens; $i++) {
        $selector = bin2hex(random_bytes(12)); // 24 chars
        if ($i % 100 === 0) {
            $selectors[] = $selector; // Save some for querying
        }
        $token_hash = bin2hex(random_bytes(32));
        $user_id = rand(1, 1000);
        $stmt->execute([$user_id, $selector, $token_hash, date('Y-m-d H:i:s', strtotime('+1 day'))]);
    }

    $pdo->commit();
    echo "Seeding complete. $num_tokens tokens.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Benchmark Function
function runBenchmark($pdo, $selectors) {
    $start = microtime(true);
    $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ?");

    // Query random selectors 1000 times
    $iterations = 1000;
    for ($i = 0; $i < $iterations; $i++) {
        $targetSelector = $selectors[array_rand($selectors)];
        $stmt->execute([$targetSelector]);
        $stmt->fetchAll();
    }

    return microtime(true) - $start;
}

// 1. Measure without Index
echo "Benchmarking without index...\n";
$durationNoIndex = runBenchmark($pdo, $selectors);
echo "Time without index: " . number_format($durationNoIndex, 4) . "s\n";

// 2. Add Index
echo "Adding index...\n";
$start = microtime(true);
$pdo->exec("CREATE INDEX idx_auth_tokens_selector ON auth_tokens(selector)");
echo "Index creation took: " . number_format(microtime(true) - $start, 4) . "s\n";

// 3. Measure with Index
echo "Benchmarking with index...\n";
$durationWithIndex = runBenchmark($pdo, $selectors);
echo "Time with index: " . number_format($durationWithIndex, 4) . "s\n";

// Results
$diff = $durationNoIndex - $durationWithIndex;
$percent = 0;
if ($durationNoIndex > 0) {
    $percent = ($diff / $durationNoIndex) * 100;
}

echo "Improvement: " . number_format($diff, 4) . "s (" . number_format($percent, 2) . "%)\n";
