<?php
// tests/session_lock_worker.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessionId = $argv[1] ?? 'test_session_lock';
$shouldClose = ($argv[2] ?? 'false') === 'true';
$sleepTime = 1;

$savePath = sys_get_temp_dir() . '/clarity_sessions';
if (!is_dir($savePath)) {
    mkdir($savePath, 0777, true);
}
ini_set('session.save_path', $savePath);
session_id($sessionId);

$start = microtime(true);
if (!session_start()) {
    echo "session_start failed\n";
    print_r(error_get_last());
    exit(1);
}
$acquired = microtime(true);

echo "Session ID: " . session_id() . "\n";
echo "Save Path: " . session_save_path() . "\n";
echo "Acquired session lock in " . number_format($acquired - $start, 4) . "s\n";

$_SESSION['access_time'] = microtime(true);

if ($shouldClose) {
    session_write_close();
    echo "Session closed\n";
}

sleep($sleepTime);

echo "Done\n";
