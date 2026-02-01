<?php

// Database Setup
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Tables
    $pdo->exec("
        CREATE TABLE projects (
            id TEXT PRIMARY KEY
        );

        CREATE TABLE photos (
            id TEXT PRIMARY KEY,
            project_id TEXT,
            created_at DATETIME,
            filename TEXT
        );
        CREATE INDEX idx_photos_project_id ON photos(project_id);
    ");

    echo "Seeding data... (this may take a moment)\n";
    $pdo->beginTransaction();

    $projects = [];
    $stmtProj = $pdo->prepare("INSERT INTO projects (id) VALUES (?)");
    $stmtPhoto = $pdo->prepare("INSERT INTO photos (id, project_id, created_at, filename) VALUES (?, ?, ?, ?)");

    // Create 50 projects
    for ($i = 0; $i < 50; $i++) {
        $projectId = "proj-" . $i;
        $projects[] = $projectId;
        $stmtProj->execute([$projectId]);

        // 2000 photos per project -> 100,000 photos total
        for ($j = 0; $j < 2000; $j++) {
            $photoId = "photo-" . $i . "-" . $j;
            $createdAt = date('Y-m-d H:i:s', time() - rand(0, 31536000));
            $stmtPhoto->execute([$photoId, $projectId, $createdAt, "img$j.jpg"]);
        }
    }

    $pdo->commit();
    echo "Seeding complete. 100,000 photos across 50 projects.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Benchmark 1: Direct DB Count (Baseline)
$start = microtime(true);
$iterations = 1000;
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM photos WHERE project_id = ?");

for ($i = 0; $i < $iterations; $i++) {
    $targetProject = $projects[array_rand($projects)];
    $stmtCount->execute([$targetProject]);
    $count = $stmtCount->fetchColumn();
}
$durationDB = microtime(true) - $start;
echo "Time for $iterations DB Counts: " . number_format($durationDB, 4) . "s\n";
echo "Average per query: " . number_format(($durationDB / $iterations) * 1000, 4) . "ms\n";


// Benchmark 2: File Cache (Simulation)
// We simulate the cost of checking a file and reading an integer from it.
$cacheDir = sys_get_temp_dir();
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $targetProject = $projects[array_rand($projects)];
    $cacheFile = $cacheDir . '/count_' . md5($targetProject);

    // Simulate cache hit 90% of the time, miss 10%
    if ($i % 10 !== 0 && file_exists($cacheFile)) {
        $count = (int)file_get_contents($cacheFile);
    } else {
        // Cache miss: Query DB + Write Cache
        $stmtCount->execute([$targetProject]);
        $count = $stmtCount->fetchColumn();

        $tempFile = tempnam($cacheDir, 'tmp_count');
        file_put_contents($tempFile, $count);
        rename($tempFile, $cacheFile);
    }
}

$durationCache = microtime(true) - $start;
echo "Time for $iterations Cached Counts (90% hit rate): " . number_format($durationCache, 4) . "s\n";
echo "Average per cached op: " . number_format(($durationCache / $iterations) * 1000, 4) . "ms\n";

// Cleanup
array_map('unlink', glob($cacheDir . '/count_*'));

$improvement = $durationDB - $durationCache;
$percent = ($improvement / $durationDB) * 100;

echo "Improvement: " . number_format($percent, 2) . "%\n";
