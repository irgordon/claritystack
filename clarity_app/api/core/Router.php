<?php

class Router {
    private $routes = [];

    public function add($method, $path, $controller, $action) {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->convertPathToRegex($path),
            'controller' => $controller,
            'action' => $action
        ];
    }

    private function convertPathToRegex($path) {
        // Convert {param} to capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        // Escape forward slashes and add start/end anchors
        return '#^' . str_replace('/', '\/', $pattern) . '$#';
    }

    public function dispatch($uri) {
        $method = $_SERVER['REQUEST_METHOD'];

        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH);

        // Remove /api prefix if present (assuming this router is called for /api/*)
        if (strpos($path, '/api') === 0) {
            $path = substr($path, 4);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {

                // Load Controller
                $controllerName = $route['controller'];
                $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';

                if (file_exists($controllerFile)) {
                    require_once $controllerFile;
                    $controller = new $controllerName();

                    // Filter numeric keys from matches to get named params
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    try {
                        call_user_func_array([$controller, $route['action']], $params);
                        return;
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => $e->getMessage()]);
                        return;
                    }
                }
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo json_encode(['error' => 'API Endpoint Not Found']);
    }
}
