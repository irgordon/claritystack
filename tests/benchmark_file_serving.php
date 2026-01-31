<?php
require_once __DIR__ . '/../clarity_app/api/core/Security.php';

// Setup SQLite DB for N+1 simulation
$dbFile = sys_get_temp_dir() . '/bench_file_serving.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

$pdo = new PDO("sqlite:$dbFile");
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, role TEXT)");
$pdo->exec("CREATE TABLE projects (id INTEGER PRIMARY KEY, client_email TEXT)");
$pdo->exec("CREATE TABLE photos (id INTEGER PRIMARY KEY, project_id INTEGER, system_filename TEXT, thumb_path TEXT, mime_type TEXT)");

$pdo->exec("INSERT INTO users (id, email, role) VALUES (1, 'user@example.com', 'client')");
$pdo->exec("INSERT INTO projects (id, client_email) VALUES (1, 'user@example.com')");

$stmt = $pdo->prepare("INSERT INTO photos (id, project_id, system_filename, thumb_path, mime_type) VALUES (?, 1, ?, ?, 'image/jpeg')");
for ($i=1; $i<=100; $i++) {
    $stmt->execute([$i, "file_$i.jpg", "thumb_$i.jpg"]);
}

$iterations = 5000;

// Benchmark 1: N+1 DB Queries
echo "Benchmarking DB Queries...\n";
$start = microtime(true);
$sql = "
    SELECT p.system_filename, p.thumb_path, p.mime_type, pr.client_email, u.email as user_email, u.role
    FROM photos p
    JOIN projects pr ON p.project_id = pr.id
    LEFT JOIN users u ON u.id = ?
    WHERE p.id = ?
";
$stmt = $pdo->prepare($sql);
for ($i=0; $i<$iterations; $i++) {
    $photoId = ($i % 100) + 1;
    $stmt->execute([1, $photoId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    // Gatekeeper logic
    $allowed = ($res['role'] === 'admin') || ($res['user_email'] === $res['client_email']);
}
$end = microtime(true);
$dbTime = $end - $start;
echo "DB Queries ($iterations): " . number_format($dbTime, 4) . "s\n";

// Benchmark 2: Token Decryption
echo "Benchmarking Token Decryption...\n";
// Create a token
$payload = [
    'id' => 1,
    's' => 'file_1.jpg',
    't' => 'thumb_1.jpg',
    'm' => 'image/jpeg',
    'u' => 1,
    'e' => time() + 3600
];
// Ensure Security class works
try {
    $token = \Core\Security::encrypt(json_encode($payload));
} catch (Exception $e) {
    die("Security Error: " . $e->getMessage());
}

$start = microtime(true);
for ($i=0; $i<$iterations; $i++) {
    $data = json_decode(\Core\Security::decrypt($token), true);
    // Validation logic
    $valid = $data && $data['u'] == 1 && $data['e'] > time();
}
$end = microtime(true);
$tokenTime = $end - $start;
echo "Token Decryption ($iterations): " . number_format($tokenTime, 4) . "s\n";

echo "Improvement: " . number_format(($dbTime / $tokenTime), 2) . "x\n";

if (file_exists($dbFile)) unlink($dbFile);
