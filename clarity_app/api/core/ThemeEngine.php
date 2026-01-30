<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailService.php';

class ThemeEngine {
    private $themePath;
    private $publicUrl;
    private $db;
    private $dom;
    
    // Safety Limits
    private const MAX_RECURSION_DEPTH = 10;
    private $currentDepth = 0;

    private $blockFileCache = [];

    // SECURITY: Allowed HTML Tags for CMS Content
    private const ALLOWED_TAGS = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'img', 'blockquote'];

    public function __construct($themeName = 'clarity_default') {
        $this->themePath = __DIR__ . '/../../themes/' . $themeName;
        $this->publicUrl = '/themes/' . $themeName . '/public';
        $this->db = \Database::getInstance()->connect();
    }

    public function renderPage($layoutSlug, $blocksTree) {
        try {
            $this->currentDepth = 0;
            $layoutPath = $this->themePath . "/layouts/$layoutSlug/view.php";
            if (!file_exists($layoutPath)) {
                throw new Exception("Layout file missing: $layoutSlug");
            }

            // Fetch Global Settings
            $stmt = $this->db->query("SELECT business_name, public_config FROM settings LIMIT 1");
            $settingsRow = $stmt->fetch();
            
            $globalConfig = [];
            if ($settingsRow) {
                $globalConfig = json_decode($settingsRow['public_config'] ?? '{}', true);
                $globalConfig['business_name'] = $settingsRow['business_name'];
            }
            
            // XSS PROTECTION: Sanitize Business Name and Global Config Strings
            foreach ($globalConfig as $key => $val) {
                if (is_string($val)) {
                    // Business name and strict config values get strict escaping
                    $globalConfig[$key] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                }
            }
            
            extract($globalConfig); 

            // Render Body
            $bodyHtml = $this->renderTree($blocksTree);

            // Render Layout
            ob_start();
            $body = $bodyHtml; 
            include $layoutPath;
            $fullHtml = ob_get_clean();

            return $this->parseShortcodes($fullHtml);

        } catch (Exception $e) {
            $this->logAndNotify('critical', 'Page Rendering Failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            return $this->getFallbackHtml();
        }
    }

    private function renderTree($nodes) {
        if ($this->currentDepth > self::MAX_RECURSION_DEPTH) {
            return "";
        }
        $this->currentDepth++;
        $html = '';

        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                try {
                    $html .= $this->renderBlock(
                        $node['type'], 
                        $node['props'] ?? [], 
                        $node['children'] ?? []
                    );
                } catch (Throwable $e) {
                    $html .= "";
                }
            }
        }
        
        $this->currentDepth--;
        return $html;
    }

    private function renderBlock($type, $props, $children) {
        $safeType = preg_replace('/[^a-z0-9_]/', '', $type);

        if (array_key_exists($safeType, $this->blockFileCache)) {
            $viewFile = $this->blockFileCache[$safeType];
        } else {
            $blockPath = $this->themePath . "/blocks/$safeType";
            if (file_exists("$blockPath/view.php")) {
                $viewFile = "$blockPath/view.php";
            } elseif (file_exists("$blockPath/view.html")) {
                $viewFile = "$blockPath/view.html";
            } else {
                $viewFile = null;
            }
            $this->blockFileCache[$safeType] = $viewFile;
        }
        
        if (!$viewFile) return "";

        // SECURITY: HARDENING CMS OUTPUT
        // We sanitize specific props known to contain Rich Text (like 'content')
        // while strictly escaping others.
        $sanitizedProps = [];
        foreach ($props as $key => $value) {
            if (is_string($value)) {
                if ($key === 'content' || $key === 'body' || $key === 'description') {
                    // Allow Rich Text, but strip Scripts/XSS
                    $sanitizedProps[$key] = $this->purifyHtml($value);
                } else {
                    // Strict Escaping for titles, labels, etc.
                    $sanitizedProps[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            } else {
                $sanitizedProps[$key] = $value;
            }
        }

        ob_start();
        extract($sanitizedProps); 
        include $viewFile;
        $content = ob_get_clean();

        if (!empty($children)) {
            $childrenHtml = $this->renderTree($children);
            $content = str_replace('[blocks-container]', $childrenHtml, $content);
        } else {
            $content = str_replace('[blocks-container]', '', $content);
        }

        return $this->parseIncludes($content);
    }

    /**
     * NATIVE HTML PURIFIER (No Dependencies)
     * Uses DOMDocument to parse HTML and strip dangerous tags/attributes.
     */
    private function purifyHtml($dirtyHtml) {
        if (empty($dirtyHtml)) return '';

        // Suppress parsing errors for invalid HTML
        libxml_use_internal_errors(true);

        if ($this->dom === null) {
            $this->dom = new DOMDocument();
        }

        // Load HTML with UTF-8 fix
        $this->dom->loadHTML(mb_convert_encoding("<div>$dirtyHtml</div>", 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // 1. Remove Disallowed Tags (Script, Object, Iframe, Style, etc.)
        // We select ALL elements and remove those not in our allowed list
        $nodes = $this->dom->getElementsByTagName('*');
        $nodesList = iterator_to_array($nodes);

        foreach ($nodesList as $node) {
            // Check if node is still attached or part of a removed branch
            if (!$node->parentNode) {
                continue;
            }

            if (!in_array($node->nodeName, self::ALLOWED_TAGS)) {
                $node->parentNode->removeChild($node);
            } else {
                // 2. Remove Dangerous Attributes (on*, javascript:)
                if ($node->hasAttributes()) {
                    // Iterate backwards to safely remove attributes while looping
                    for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                        $attr = $node->attributes->item($i);
                        $attrName = strtolower($attr->name);
                        $attrVal = strtolower($attr->value);

                        // Remove event handlers (onclick, onload)
                        if (strpos($attrName, 'on') === 0) {
                            $node->removeAttribute($attr->name);
                            continue;
                        }

                        // Remove javascript: URIs in href or src
                        if (($attrName === 'href' || $attrName === 'src') && strpos($attrVal, 'javascript:') !== false) {
                            $node->removeAttribute($attr->name);
                        }
                    }
                }
            }
        }

        // Save sanitized HTML (strip the wrapper div we added)
        $cleanHtml = $this->dom->saveHTML($this->dom->documentElement);
        $cleanHtml = substr($cleanHtml, 5, -6); // Remove <div> and </div>

        libxml_clear_errors();
        return $cleanHtml;
    }

    private function parseIncludes($content) {
        return preg_replace_callback('/\[block slug="([^"]+)"\]/', function($matches) {
            return $this->renderBlock($matches[1], [], []);
        }, $content);
    }

    private function parseShortcodes($content) {
        return str_replace('[theme-url]', $this->publicUrl, $content);
    }

    private function logAndNotify($severity, $message, $context = []) {
        // (Logging logic remains the same as previous examples)
        try {
            $stmt = $this->db->prepare("INSERT INTO system_logs (severity, category, message, context) VALUES (?, 'cms', ?, ?)");
            $stmt->execute([$severity, $message, json_encode($context)]);
        } catch (Exception $e) { }
    }

    private function getFallbackHtml() {
        return "<div style='text-align:center;padding:50px;'><h1>Temporarily Unavailable</h1></div>";
    }
}
?>
