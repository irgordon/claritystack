<?php

// Simple Benchmark Script for Prepared Statements Optimization

function getDbConnection() {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE email_queue (id INTEGER PRIMARY KEY, status TEXT, updated_at TEXT)");
    return $pdo;
}

function seedData($pdo, $count = 1000) {
    $pdo->exec("DELETE FROM email_queue");
    $stmt = $pdo->prepare("INSERT INTO email_queue (status) VALUES ('pending')");
    $pdo->beginTransaction();
    for ($i = 0; $i < $count; $i++) {
        $stmt->execute();
    }
    $pdo->commit();
}

function testInefficient($pdo, $count) {
    $start = microtime(true);
    $pdo->beginTransaction();
    for ($i = 1; $i <= $count; $i++) {
        // Simulating the N+1 issue: Prepare inside loop
        $stmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$i]);
    }
    $pdo->commit();
    return microtime(true) - $start;
}

function testOptimized($pdo, $count) {
    $start = microtime(true);
    $pdo->beginTransaction();
    // Optimization: Prepare outside loop
    $stmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', updated_at = datetime('now') WHERE id = ?");
    for ($i = 1; $i <= $count; $i++) {
        $stmt->execute([$i]);
    }
    $pdo->commit();
    return microtime(true) - $start;
}

$pdo = getDbConnection();
$count = 5000; // 5000 iterations to make it noticeable

echo "Benchmarking N+1 Prepare Statement Issue ($count iterations)...\n";

seedData($pdo, $count);
$timeInefficient = testInefficient($pdo, $count);
echo "Inefficient (Prepare inside loop): " . number_format($timeInefficient, 4) . " seconds\n";

seedData($pdo, $count);
$timeOptimized = testOptimized($pdo, $count);
echo "Optimized   (Prepare outside loop): " . number_format($timeOptimized, 4) . " seconds\n";

$improvement = $timeInefficient - $timeOptimized;
$percent = ($improvement / $timeInefficient) * 100;

echo "Improvement: " . number_format($improvement, 4) . " seconds (" . number_format($percent, 2) . "%)\n";
