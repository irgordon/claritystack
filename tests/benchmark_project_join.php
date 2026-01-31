<?php

// Database Setup
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create Tables (Simplified for SQLite)
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            role TEXT,
            email TEXT
        );

        CREATE TABLE projects (
            id TEXT PRIMARY KEY,
            client_email TEXT
        );
    ");

    // Seed Data
    $pdo->beginTransaction();
    $stmUser = $pdo->prepare("INSERT INTO users (role, email) VALUES (?, ?)");
    $stmProject = $pdo->prepare("INSERT INTO projects (id, client_email) VALUES (?, ?)");

    $users = [];
    $projects = [];

    for ($i = 0; $i < 1000; $i++) {
        $role = ($i % 10 === 0) ? 'admin' : 'client';
        $email = "user{$i}@example.com";
        $stmUser->execute([$role, $email]);
        $users[] = $pdo->lastInsertId();

        $projectId = "proj-" . $i; // Simulating UUID
        $clientEmail = "user{$i}@example.com"; // Some projects match users
        $stmProject->execute([$projectId, $clientEmail]);
        $projects[] = $projectId;
    }
    $pdo->commit();

    echo "Seeded 1000 users and 1000 projects.\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Queries
$implicitQuery = "
    SELECT u.role, u.email as user_email, p.client_email
    FROM users u, projects p
    WHERE u.id = ? AND p.id = ?
";

$explicitQuery = "
    SELECT u.role, u.email as user_email, p.client_email
    FROM users u
    CROSS JOIN projects p
    WHERE u.id = ? AND p.id = ?
";

// Warmup
$targetUser = $users[500];
$targetProject = $projects[500];

$stmImplicit = $pdo->prepare($implicitQuery);
$stmImplicit->execute([$targetUser, $targetProject]);
$stmImplicit->fetchAll();

$stmExplicit = $pdo->prepare($explicitQuery);
$stmExplicit->execute([$targetUser, $targetProject]);
$stmExplicit->fetchAll();

// Benchmark Configuration
$iterations = 50000;
$testPairs = [];
for($i=0; $i<$iterations; $i++) {
    $testPairs[] = [
        $users[rand(0, 999)],
        $projects[rand(0, 999)]
    ];
}

echo "Running benchmark with $iterations iterations...\n";

// Measure Implicit
$start = microtime(true);
$stm = $pdo->prepare($implicitQuery);
foreach ($testPairs as $pair) {
    $stm->execute($pair);
    $result = $stm->fetch();
}
$durationImplicit = microtime(true) - $start;
echo "Implicit Join Duration: " . number_format($durationImplicit, 4) . "s\n";

// Measure Explicit
$start = microtime(true);
$stm = $pdo->prepare($explicitQuery);
foreach ($testPairs as $pair) {
    $stm->execute($pair);
    $result = $stm->fetch();
}
$durationExplicit = microtime(true) - $start;
echo "Explicit Join Duration: " . number_format($durationExplicit, 4) . "s\n";

// Results
$diff = $durationImplicit - $durationExplicit;
$percent = ($diff / $durationImplicit) * 100;

echo "Difference: " . number_format($diff, 4) . "s (" . number_format($percent, 2) . "%)\n";
if ($diff > 0) {
    echo "Explicit Join was faster.\n";
} else {
    echo "Implicit Join was faster.\n";
}
