<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailService.php';

class ThemeEngine {
    private $themePath;
    private $publicUrl;
    private $db;
    private const MAX_RECURSION_DEPTH = 10;
    private $currentDepth = 0;

    public function __construct($themeName = 'clarity_default') {
        $this->themePath = __DIR__ . '/../../themes/' . $themeName;
        $this->publicUrl = '/themes/' . $themeName . '/public';
        $this->db = (new \Database())->connect();
    }

    public function renderPage($layoutSlug, $blocksTree) {
        try {
            $this->currentDepth = 0;
            $layoutPath = $this->themePath . "/layouts/$layoutSlug/view.php";
            if (!file_exists($layoutPath)) throw new Exception("Layout missing: $layoutSlug");

            $bodyHtml = $this->renderTree($blocksTree);
            ob_start();
            $body = $bodyHtml; 
            include $layoutPath;
            $fullHtml = ob_get_clean();

            return str_replace('[theme-url]', $this->publicUrl, $fullHtml);
        } catch (Exception $e) {
            $this->logAndNotify('critical', 'Page Render Failed', ['error' => $e->getMessage()]);
            return "<h1>Temporarily Unavailable</h1>";
        }
    }

    private function renderTree($nodes) {
        if ($this->currentDepth > self::MAX_RECURSION_DEPTH) return "";
        $this->currentDepth++;
        $html = '';
        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                $html .= $this->renderBlock($node['type'], $node['props'] ?? [], $node['children'] ?? []);
            }
        }
        $this->currentDepth--;
        return $html;
    }

    private function renderBlock($type, $props, $children) {
        $safeType = preg_replace('/[^a-z0-9_]/', '', $type);
        $blockPath = $this->themePath . "/blocks/$safeType";
        $viewFile = file_exists("$blockPath/view.php") ? "$blockPath/view.php" : "$blockPath/view.html";
        
        if (!file_exists($viewFile)) return "";

        ob_start();
        extract($props);
        include $viewFile;
        $content = ob_get_clean();

        if (!empty($children)) {
            $content = str_replace('[blocks-container]', $this->renderTree($children), $content);
        } else {
            $content = str_replace('[blocks-container]', '', $content);
        }
        return $content;
    }

    private function logAndNotify($severity, $message, $context) {
        $this->db->prepare("INSERT INTO system_logs (severity, category, message, context) VALUES (?, 'cms', ?, ?)")
                 ->execute([$severity, $message, json_encode($context)]);
    }
    
    public function getAvailableBlocks() {
        $blocks = [];
        $dir = $this->themePath . '/blocks';
        if (!is_dir($dir)) return [];
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $isDynamic = file_exists("$dir/$item/view.php");
            $content = @file_get_contents("$dir/$item/" . ($isDynamic ? 'view.php' : 'view.html'));
            $blocks[] = [
                'type' => $item, 
                'allows_nesting' => strpos($content, '[blocks-container]') !== false
            ];
        }
        return $blocks;
    }
}
