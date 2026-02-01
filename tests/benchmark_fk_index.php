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
            project_id TEXT, -- Foreign Key
            filename TEXT
        );
    ");

    echo "Seeding data... (this may take a moment)\n";
    $pdo->beginTransaction();

    $projects = [];

    $stmtProj = $pdo->prepare("INSERT INTO projects (id) VALUES (?)");
    $stmtPhoto = $pdo->prepare("INSERT INTO photos (id, project_id, filename) VALUES (?, ?, ?)");

    // Create 100 projects
    for ($i = 0; $i < 100; $i++) {
        $projectId = "proj-" . $i;
        $projects[] = $projectId;
        $stmtProj->execute([$projectId]);

        // Create 1000 photos per project -> 100,000 photos total
        for ($j = 0; $j < 1000; $j++) {
            $photoId = "photo-" . $i . "-" . $j;
            $stmtPhoto->execute([$photoId, $projectId, "img$j.jpg"]);
        }
    }

    $pdo->commit();
    echo "Seeding complete. 100,000 photos across 100 projects.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Benchmark Function
function runBenchmark($pdo, $projects) {
    $start = microtime(true);
    $stmt = $pdo->prepare("SELECT * FROM photos WHERE project_id = ?");

    // Query photos for random projects 1000 times
    $iterations = 1000;
    for ($i = 0; $i < $iterations; $i++) {
        $targetProject = $projects[array_rand($projects)];
        $stmt->execute([$targetProject]);
        $stmt->fetchAll();
    }

    return microtime(true) - $start;
}

// 1. Measure without Index
echo "Benchmarking without index...\n";
$durationNoIndex = runBenchmark($pdo, $projects);
echo "Time without index: " . number_format($durationNoIndex, 4) . "s\n";

// 2. Add Index
echo "Adding index...\n";
$start = microtime(true);
$pdo->exec("CREATE INDEX idx_photos_project_id ON photos(project_id)");
echo "Index creation took: " . number_format(microtime(true) - $start, 4) . "s\n";

// 3. Measure with Index
echo "Benchmarking with index...\n";
$durationWithIndex = runBenchmark($pdo, $projects);
echo "Time with index: " . number_format($durationWithIndex, 4) . "s\n";

// Results
$diff = $durationNoIndex - $durationWithIndex;
$percent = 0;
if ($durationNoIndex > 0) {
    $percent = ($diff / $durationNoIndex) * 100;
}

echo "Improvement: " . number_format($diff, 4) . "s (" . number_format($percent, 2) . "%)\n";
