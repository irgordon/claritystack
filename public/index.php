<?php
require_once __DIR__ . '/../api/core/Database.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($path, '/') ?: 'home';

$db = (new \Database())->connect();
$stmt = $db->prepare("SELECT title, meta_description, og_image_url FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

$html = file_get_contents(__DIR__ . '/index.html'); // The React Shell

if ($page) {
    $html = str_replace('<title>Vite App</title>', "<title>{$page['title']}</title>", $html);
    $html = str_replace('__META_DESCRIPTION__', $page['meta_description'], $html);
}

echo $html;
