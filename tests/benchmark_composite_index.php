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

        // Create 2000 photos per project -> 100,000 photos total
        // Randomize created_at to make sorting work harder
        for ($j = 0; $j < 2000; $j++) {
            $photoId = "photo-" . $i . "-" . $j;
            // Random date within last year
            $timestamp = time() - rand(0, 31536000);
            $createdAt = date('Y-m-d H:i:s', $timestamp);
            $stmtPhoto->execute([$photoId, $projectId, $createdAt, "img$j.jpg"]);
        }
    }

    $pdo->commit();
    echo "Seeding complete. 100,000 photos across 50 projects.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Benchmark Function
function runBenchmark($pdo, $projects) {
    $start = microtime(true);
    // This query matches ProjectController::listPhotos
    $stmt = $pdo->prepare("SELECT id, filename FROM photos WHERE project_id = ? ORDER BY created_at ASC LIMIT 50");

    // Query photos for random projects 500 times
    $iterations = 500;
    for ($i = 0; $i < $iterations; $i++) {
        $targetProject = $projects[array_rand($projects)];
        $stmt->execute([$targetProject]);
        $stmt->fetchAll();
    }

    return microtime(true) - $start;
}

// 1. Measure with Simple Index (Current State)
echo "Creating simple index (project_id)...\n";
$pdo->exec("CREATE INDEX idx_photos_project_id ON photos(project_id)");

echo "Benchmarking with simple index...\n";
$durationSimple = runBenchmark($pdo, $projects);
echo "Time with simple index: " . number_format($durationSimple, 4) . "s\n";

// 2. Measure with Composite Index (Proposed State)
echo "Replacing with composite index (project_id, created_at)...\n";
$pdo->exec("DROP INDEX idx_photos_project_id");
$pdo->exec("CREATE INDEX idx_photos_project_id ON photos(project_id, created_at)");

echo "Benchmarking with composite index...\n";
$durationComposite = runBenchmark($pdo, $projects);
echo "Time with composite index: " . number_format($durationComposite, 4) . "s\n";

// Results
$diff = $durationSimple - $durationComposite;
$percent = 0;
if ($durationSimple > 0) {
    $percent = ($diff / $durationSimple) * 100;
}

echo "Improvement: " . number_format($diff, 4) . "s (" . number_format($percent, 2) . "%)\n";
