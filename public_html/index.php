<?php
// Adjust this path to point to your private backend folder
require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';
require_once __DIR__ . '/../clarity_app/api/core/Router.php';
require_once __DIR__ . '/../clarity_app/api/core/CacheService.php';

use Core\CacheService;

// 0. API ROUTING
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/api/') === 0) {
    header('Content-Type: application/json');
    $router = new Router();

    // Define Routes
    $router->add('POST', '/install', 'InstallController', 'install');

    // Auth
    $router->add('POST', '/auth/magic-link', 'AuthController', 'requestLink');
    $router->add('POST', '/auth/verify', 'AuthController', 'verifyLink');

    // Admin Settings
    $router->add('GET', '/admin/settings', 'SettingsController', 'getSettings');
    $router->add('POST', '/admin/settings/storage', 'SettingsController', 'updateStorage');
    $router->add('GET', '/admin/health', 'SettingsController', 'getSystemHealth');
    $router->add('GET', '/admin/logs', 'SettingsController', 'getLogs');

    // Logging
    $router->add('POST', '/log/client', 'SettingsController', 'logClientEvent');

    // Projects
    $router->add('GET', '/projects/{id}/photos', 'ProjectController', 'listPhotos');

    // Download
    $router->add('POST', '/projects/{id}/download-link', 'DownloadController', 'generateLink');
    $router->add('GET', '/download/stream', 'DownloadController', 'streamZip');

    $router->dispatch($requestUri);
    exit;
}

// 1. Identify the requested path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($path, '/') ?: 'home';

// 2. Fetch SEO Data (Cached via CacheService)
// Cache TTL: 1 hour (3600s)
$page = CacheService::remember('seo', md5($slug), 3600, function() use ($slug) {
    // Connect to DB only if cache miss
    $db = Database::getInstance()->connect();

    if ($db) {
        try {
            $stmt = $db->prepare("SELECT title, meta_description, og_image_url FROM pages WHERE slug = ?");
            $stmt->execute([$slug]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // DB error or not installed
            return null;
        }
    }
    return null;
});

// 3. Fetch Global Config (Memoized & Cached)
$config = ConfigHelper::getPublicConfig();
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
