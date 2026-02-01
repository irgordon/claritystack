<?php
require_once __DIR__ . '/../clarity_app/api/core/Security.php';

// Mock data
$photos = [];
for ($i = 0; $i < 50; $i++) {
    $photos[] = [
        'id' => $i,
        'system_filename' => "photo_{$i}.jpg",
        'thumb_path' => "thumbs/photo_{$i}.jpg",
        'mime_type' => 'image/jpeg',
    ];
}
$userId = 123;

$start = microtime(true);

// Loop 10000 times (50 * 10000 = 500,000 encryptions)
for ($j = 0; $j < 10000; $j++) {
    foreach ($photos as $photo) {
        $payload = [
            'id' => $photo['id'],
            's' => $photo['system_filename'],
            't' => $photo['thumb_path'],
            'm' => $photo['mime_type'],
            'u' => $userId,
            'e' => time() + 7200
        ];
        $token = \Core\Security::encrypt(json_encode($payload));
    }
}

$end = microtime(true);
echo "Time: " . ($end - $start) . " seconds\n";
