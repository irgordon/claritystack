<?php
require_once __DIR__ . '/../clarity_app/api/core/CacheService.php';
use Core\CacheService;

// Database Setup (Same as original benchmark)
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

        // 2000 photos per project
        for ($j = 0; $j < 2000; $j++) {
            $photoId = "photo-" . $i . "-" . $j;
            $createdAt = date('Y-m-d H:i:s', time() - rand(0, 31536000));
            $stmtPhoto->execute([$photoId, $projectId, $createdAt, "img$j.jpg"]);
        }
    }

    $pdo->commit();
    echo "Seeding complete.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM photos WHERE project_id = ?");

// Benchmark: CacheService
$start = microtime(true);
$iterations = 1000;

CacheService::flush('counts');

for ($i = 0; $i < $iterations; $i++) {
    $targetProject = $projects[array_rand($projects)];
    $cacheKey = md5($targetProject);

    // Simulate 90% hit rate?
    // Actually, let's just run random access.
    // With 50 projects and 1000 iterations, we will hit cache often.

    // In original benchmark, they forced misses 10% of time.
    // Here we just let CacheService handle it.
    // To be fair with original benchmark, we should invalidate/miss sometimes.
    // But real world usage is natural hits.

    // Let's force expire for some to simulate misses?
    if ($i % 10 === 0) {
        CacheService::delete('counts', $cacheKey);
    }

    $count = CacheService::remember('counts', $cacheKey, 60, function() use ($stmtCount, $targetProject) {
        $stmtCount->execute([$targetProject]);
        return (int)$stmtCount->fetchColumn();
    });
}

$duration = microtime(true) - $start;
echo "Time for $iterations CacheService Ops (w/ forced 10% miss): " . number_format($duration, 4) . "s\n";
echo "Average per op: " . number_format(($duration / $iterations) * 1000, 4) . "ms\n";
