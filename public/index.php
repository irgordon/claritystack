<?php
// Adjust this path to point to your private backend folder
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// 1. Identify the requested path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($path, '/') ?: 'home';

// 2. Fetch SEO Data
$db = (new Database())->connect();
$stmt = $db->prepare("SELECT title, meta_description, og_image_url FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

// 3. Fetch Global Config
$settings = $db->query("SELECT public_config FROM settings LIMIT 1")->fetch();
$config = json_decode($settings['public_config'] ?? '{}', true);
$seo = $config['seo'] ?? [];

// 4. Determine Meta Tags
$siteName = $seo['site_name'] ?? 'ClarityStack';
$title = $page ? ($page['title'] . ' | ' . $siteName) : $siteName;
$desc = $page['meta_description'] ?? $seo['default_description'] ?? '';
$image = $page['og_image_url'] ?? $seo['default_og_image'] ?? '';

// 5. Load React Shell
$html = file_get_contents(__DIR__ . '/index.html'); 

// 6. Inject Metadata
$html = str_replace('<title>Vite App</title>', "<title>" . htmlspecialchars($title) . "</title>", $html);
$html = str_replace('__META_DESCRIPTION__', htmlspecialchars($desc), $html);
$html = str_replace('__OG_IMAGE__', htmlspecialchars($image), $html);

// 7. Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

echo $html;
?>
