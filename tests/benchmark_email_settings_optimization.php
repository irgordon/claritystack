<?php
// tests/benchmark_email_settings_optimization.php

require_once __DIR__ . '/../clarity_app/api/core/EmailService.php';
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';

// Setup Mock DB
$testConfig = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'APP_KEY' => 'test_key',
    'DEBUG' => true
];
Database::getInstance()->setConfig($testConfig);
$db = Database::getInstance()->connect();

// Create Tables
$db->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT, private_config TEXT, updated_at TEXT)");
$db->exec("CREATE TABLE email_templates (id INTEGER PRIMARY KEY, key_name TEXT, subject TEXT, body_content TEXT)");
$db->exec("CREATE TABLE email_queue (id INTEGER PRIMARY KEY, to_email TEXT, subject TEXT, body TEXT, headers TEXT, status TEXT)");

// Insert Data
$db->prepare("INSERT INTO settings (business_name, public_config, updated_at) VALUES (?, ?, ?)")
    ->execute([
        'BenchmarkBiz',
        json_encode(['primary_color' => '#ff0000', 'logo_url' => 'logo.png', 'no_reply_email' => 'noreply@bench.com']),
        time()
    ]);

$db->prepare("INSERT INTO email_templates (key_name, subject, body_content) VALUES (?, ?, ?)")
    ->execute(['welcome', 'Welcome {{name}}', '<p>Hi {{name}}</p>']);

// Reflection to clear private static properties
function resetStaticCaches() {
    // Clear ConfigHelper memory cache ONLY (simulate new request, but allow file cache to persist)
    $configReflection = new ReflectionClass('ConfigHelper');
    $configCache = $configReflection->getProperty('cache');
    $configCache->setAccessible(true);
    $configCache->setValue(null, null);

    // Clear EmailService cache (private static)
    $reflection = new ReflectionClass('EmailService');

    // Clear templateCache
    $prop = $reflection->getProperty('templateCache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);

    // Clear settingsCache
    if ($reflection->hasProperty('settingsCache')) {
        $prop = $reflection->getProperty('settingsCache');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}

// Ensure ConfigHelper cache file is created once for the "Cached" scenario if we were benchmarking ConfigHelper specifically,
// but here we want to benchmark EmailService's behavior.
// If EmailService uses DB directly, it hits DB every time if cache is cleared.
// If EmailService uses ConfigHelper, ConfigHelper might hit file cache.

// To make the comparison fair for "EmailService overhead", we want to simulate the cost of fetching settings.
// In the original code, `EmailService` queries the DB if `self::$settingsCache` is null.
// In the optimized code, `EmailService` calls `ConfigHelper`. `ConfigHelper` will hit DB or File.
// To demonstrate the benefit, we should assume `ConfigHelper` is working efficiently (file cache) OR that reusing the data already fetched by ConfigHelper (if it was called earlier in request) is faster.

// Scenario: "New Request" simulation.
// In a real request, ConfigHelper might have already been loaded.
// So let's preload ConfigHelper to simulate "already loaded" state for the optimized version?
// No, let's simulate the worst case for original: it ALWAYS queries DB if we reset its internal cache.
// And for optimized: it calls ConfigHelper. If ConfigHelper has data, it returns fast.

// We will measure "Send Email" time.
// We will reset `EmailService::$settingsCache` every time to simulate a fresh call (or a worker that doesn't persist this indefinitely, or just the cost of that logic).
// Actually, `EmailService` is static. In a PHP-FPM request, it starts empty.
// So resetting everything simulates a fresh request.

$iterations = 1000;
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    resetStaticCaches();

    // In the optimized version, we might rely on ConfigHelper being fast.
    // To be fair, let's NOT preload ConfigHelper in the loop, so we measure the full cost.
    // However, if ConfigHelper uses a file cache, it will be faster than DB.

    EmailService::send('test@test.com', 'welcome', ['name' => 'Tester']);
}

$end = microtime(true);
$totalTime = $end - $start;
$avgTimeMs = ($totalTime / $iterations) * 1000;

echo "Total Time: " . number_format($totalTime, 4) . "s\n";
echo "Average Time per Email: " . number_format($avgTimeMs, 4) . "ms\n";
