<?php

// Database Setup
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Tables
    $pdo->exec("
        CREATE TABLE download_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token_hash CHAR(64) NOT NULL,
            project_id TEXT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    echo "Seeding data... (this may take a moment)\n";
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO download_tokens (token_hash, project_id, expires_at) VALUES (?, ?, ?)");

    $tokens = [];
    $num_tokens = 100000;

    // Create 100,000 tokens
    for ($i = 0; $i < $num_tokens; $i++) {
        $token = bin2hex(random_bytes(32));
        if ($i % 100 === 0) {
            $tokens[] = $token; // Save some tokens for querying
        }
        $projectId = "proj-" . rand(1, 1000);
        $stmt->execute([$token, $projectId, date('Y-m-d H:i:s', strtotime('+1 day'))]);
    }

    $pdo->commit();
    echo "Seeding complete. $num_tokens tokens.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Benchmark Function
function runBenchmark($pdo, $tokens) {
    $start = microtime(true);
    $stmt = $pdo->prepare("SELECT * FROM download_tokens WHERE token_hash = ?");

    // Query random tokens 1000 times
    $iterations = 1000;
    for ($i = 0; $i < $iterations; $i++) {
        $targetToken = $tokens[array_rand($tokens)];
        $stmt->execute([$targetToken]);
        $stmt->fetchAll();
    }

    return microtime(true) - $start;
}

// 1. Measure without Index
echo "Benchmarking without index...\n";
$durationNoIndex = runBenchmark($pdo, $tokens);
echo "Time without index: " . number_format($durationNoIndex, 4) . "s\n";

// 2. Add Index
echo "Adding index...\n";
$start = microtime(true);
$pdo->exec("CREATE INDEX idx_download_tokens_hash ON download_tokens(token_hash)");
echo "Index creation took: " . number_format(microtime(true) - $start, 4) . "s\n";

// 3. Measure with Index
echo "Benchmarking with index...\n";
$durationWithIndex = runBenchmark($pdo, $tokens);
echo "Time with index: " . number_format($durationWithIndex, 4) . "s\n";

// Results
$diff = $durationNoIndex - $durationWithIndex;
$percent = 0;
if ($durationNoIndex > 0) {
    $percent = ($diff / $durationNoIndex) * 100;
}

echo "Improvement: " . number_format($diff, 4) . "s (" . number_format($percent, 2) . "%)\n";
