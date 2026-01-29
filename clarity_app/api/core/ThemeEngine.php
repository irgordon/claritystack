<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailService.php';

class ThemeEngine {
    private $themePath;
    private $publicUrl;
    private $db;
    
    // Safety Limits to prevent infinite loops (e.g. Block A includes Block B includes Block A)
    private const MAX_RECURSION_DEPTH = 10;
    private $currentDepth = 0;

    public function __construct($themeName = 'clarity_default') {
        // Points to clarity_app/themes/clarity_default
        $this->themePath = __DIR__ . '/../../themes/' . $themeName;
        // The URL prefix for assets (css/js) served via the public web root
        $this->publicUrl = '/themes/' . $themeName . '/public';
        $this->db = (new \Database())->connect();
    }

    /**
     * The Main Rendering Function.
     * Combines the Layout File (PHP) with the CMS Block Tree (JSON).
     * * @param string $layoutSlug The folder name in themes/layouts/ (e.g., 'master')
     * @param array $blocksTree The JSON decoded array from pages.blocks
     * @return string The final HTML
     */
    public function renderPage($layoutSlug, $blocksTree) {
        try {
            // 1. Reset Recursion Guard
            $this->currentDepth = 0;

            // 2. Validate Layout Existence
            $layoutPath = $this->themePath . "/layouts/$layoutSlug/view.php";
            if (!file_exists($layoutPath)) {
                throw new Exception("Layout file missing: $layoutSlug");
            }

            // 3. Fetch Global Settings (Branding, Socials, Config)
            // We inject these into the layout scope so view.php can use $social_instagram, etc.
            $stmt = $this->db->query("SELECT business_name, public_config FROM settings LIMIT 1");
            $settingsRow = $stmt->fetch();
            
            // Default empty if settings missing
            $globalConfig = [];
            if ($settingsRow) {
                $globalConfig = json_decode($settingsRow['public_config'] ?? '{}', true);
                $globalConfig['business_name'] = $settingsRow['business_name'];
            }
            
            // Extract to variables: $primary_color, $social_instagram, $business_name
            extract($globalConfig); 

            // 4. Render the Page Body (The Blocks)
            // We do this BEFORE the layout so we can catch block errors without killing the shell
            $bodyHtml = $this->renderTree($blocksTree);

            // 5. Render the Layout (Shell)
            ob_start();
            // This variable $body is required by the layout file (<?= $body ?>)
            $body = $bodyHtml; 
            
            // Include the PHP file. 
            // Because we used extract() above, the layout has access to global settings.
            include $layoutPath;
            
            $fullHtml = ob_get_clean();

            // 6. Final Pass: Replace Global Shortcodes
            return $this->parseShortcodes($fullHtml);

        } catch (Exception $e) {
            // CRITICAL ERROR: The entire page failed (likely layout missing or DB down)
            $this->logAndNotify('critical', 'Page Rendering Failed', [
                'layout' => $layoutSlug,
                'error' => $e->getMessage()
            ]);
            
            // Return a safe fallback so the user doesn't see a raw stack trace
            http_response_code(500);
            return $this->getFallbackHtml();
        }
    }

    /**
     * Recursively renders a list of blocks.
     */
    private function renderTree($nodes) {
        // Recursion Guard
        if ($this->currentDepth > self::MAX_RECURSION_DEPTH) {
            $this->logAndNotify('urgent', 'Max Recursion Depth Exceeded', ['nodes_count' => count($nodes)]);
            return "";
        }
        $this->currentDepth++;

        $html = '';
        if (!is_array($nodes)) {
            $this->currentDepth--;
            return '';
        }

        foreach ($nodes as $node) {
            try {
                // Render individual block
                $html .= $this->renderBlock(
                    $node['type'], 
                    $node['props'] ?? [], 
                    $node['children'] ?? []
                );
            } catch (Throwable $e) {
                // Block-Level Error Handler
                // If one block fails (e.g. bad PHP in hero/view.php), we log it
                // but CONTINUE rendering the rest of the page.
                $this->logAndNotify('urgent', 'Block Rendering Failed', [
                    'block_type' => $node['type'],
                    'error' => $e->getMessage()
                ]);
                $html .= "";
            }
        }
        
        $this->currentDepth--;
        return $html;
    }

    /**
     * Renders a single block file.
     */
    private function renderBlock($type, $props, $children) {
        // Security: sanitize block name to prevent ../ directory traversal
        $safeType = preg_replace('/[^a-z0-9_]/', '', $type);
        $blockPath = $this->themePath . "/blocks/$safeType";
        
        // Check for view.php (Dynamic) or view.html (Static)
        $viewFile = file_exists("$blockPath/view.php") ? "$blockPath/view.php" : "$blockPath/view.html";
        
        if (!file_exists($viewFile)) {
            // Log missing file but don't crash
            return "";
        }

        // Start Output Buffering
        ob_start();
        
        // Expose props as PHP variables (e.g. $props['title'] -> $title)
        extract($props); 
        
        try {
            include $viewFile;
        } catch (Throwable $e) {
            // Clean buffer if the include crashed
            ob_end_clean();
            throw $e; 
        }

        $content = ob_get_clean();

        // Handle Inner Blocks (Nesting)
        // Replaces [blocks-container] with the result of rendering the children
        if (!empty($children)) {
            $childrenHtml = $this->renderTree($children);
            $content = str_replace('[blocks-container]', $childrenHtml, $content);
        } else {
            // Clean up the placeholder if no children exist
            $content = str_replace('[blocks-container]', '', $content);
        }

        // Handle Shortcodes inside the block content (e.g. [block slug="header"])
        return $this->parseIncludes($content);
    }

    /**
     * Replaces [block slug="..."] with a rendered block.
     * Useful for including global headers/footers in layouts.
     */
    private function parseIncludes($content) {
        return preg_replace_callback('/\[block slug="([^"]+)"\]/', function($matches) {
            // We use an empty array for props/children for simple includes
            return $this->renderBlock($matches[1], [], []);
        }, $content);
    }

    /**
     * Replaces global theme variables.
     */
    private function parseShortcodes($content) {
        return str_replace('[theme-url]', $this->publicUrl, $content);
    }

    /**
     * Centralized Logger.
     * Writes to DB and optionally triggers emails for critical issues.
     */
    private function logAndNotify($severity, $message, $context = []) {
        try {
            $stmt = $this->db->prepare("INSERT INTO system_logs (severity, category, message, context) VALUES (?, 'cms', ?, ?)");
            $stmt->execute([$severity, $message, json_encode($context)]);

            // Email Admin on Critical Failures
            if ($severity === 'critical') {
                $admin = $this->db->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1")->fetch();
                if ($admin) {
                    EmailService::send($admin['email'], 'system_alert_critical', [
                        'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                        'message' => $message,
                        'file' => $context['layout'] ?? 'Unknown',
                        'time' => date('Y-m-d H:i:s'),
                        'admin_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/admin/logs'
                    ]);
                }
            }
        } catch (Exception $e) {
            // Last resort: If DB logging fails, write to server error log
            error_log("ClarityStack Logging Failed: " . $e->getMessage());
        }
    }

    /**
     * Used by Admin UI to list available blocks in the sidebar.
     */
    public function getAvailableBlocks() {
        $blocks = [];
        $dir = $this->themePath . '/blocks';
        
        if (!is_dir($dir)) return [];

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $isDynamic = file_exists("$dir/$item/view.php");
            $viewFile = "$dir/$item/" . ($isDynamic ? 'view.php' : 'view.html');
            
            // Check if block allows nesting by scanning for tag
            $allowsNesting = false;
            if (file_exists($viewFile)) {
                $content = file_get_contents($viewFile);
                if (strpos($content, '[blocks-container]') !== false) {
                    $allowsNesting = true;
                }
            }

            $blocks[] = [
                'type' => $item, 
                'is_dynamic' => $isDynamic,
                'allows_nesting' => $allowsNesting
            ];
        }
        return $blocks;
    }

    /**
     * "Maintenance Mode" HTML returned on critical failure.
     */
    private function getFallbackHtml() {
        return "
        <div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h1>Temporarily Unavailable</h1>
            <p>Our website is currently experiencing a technical issue.</p>
            <p>The site administrator has been notified.</p>
        </div>";
    }
}
?>
