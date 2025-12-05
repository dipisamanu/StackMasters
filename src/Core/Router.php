<?php
namespace Ottaviodipisa\StackMasters\Core;

class Router {
    protected $routes = [];

    public function add($route, $params = []) {
        // Converte la route (es. 'registrazione') in regex
        $route = preg_replace('/\//', '\\/', $route);
        $route = '/^' . $route . '$/i';
        $this->routes[$route] = $params;
    }

    public function dispatch($url) {
        // Rimuove query string (es. ?id=1)
        $url = strtok($url, '?');

        // Rimuove la cartella base se presente (es. /StackMasters/public/)
        $basePath = '/StackMasters/public/';
        if (strpos($url, $basePath) === 0) {
            $url = substr($url, strlen($basePath));
        }
        // Rimuove slash iniziali/finali
        $url = trim($url, '/');

        foreach ($this->routes as $route => $params) {
            if (preg_match($route, $url, $matches)) {
                $controllerName = "Ottaviodipisa\\StackMasters\\Controllers\\" . $params['controller'];
                $action = $params['action'];

                if (class_exists($controllerName)) {
                    $controller = new $controllerName();
                    if (method_exists($controller, $action)) {
                        $controller->$action();
                        return;
                    }
                }
            }
        }

        // Fallback 404
        header("HTTP/1.0 404 Not Found");
        echo "404 - Pagina non trovata ($url)";
    }
}