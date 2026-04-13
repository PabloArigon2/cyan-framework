<?php

require_once "utils.php";

final class Router {
    public static $config = [
        "baseDir" => 'content',
        "popupDir" => 'content/popup',
        "errorDir" => 'content/errors',
        "defaultPage" => 'home'
    ];

    private static $isAjax = false;
    private static $isPartial = false;
    private static $routeInfo = null;
    private static $rota = [];

    public static function isAjax() { return self::$isAjax; }
    public static function isPartial() { return self::$isPartial; }
    public static function getRouteInfo() { return self::$routeInfo; }
    public static function getRota() { return self::$rota; }

    public static function ProcessChildMenu($target = ".profile-tab") {
        if (!self::$routeInfo || empty(self::$routeInfo['info']['module'])) return;

        $moduleName = self::$routeInfo['info']['module'];
        $manifestPath = self::$config['baseDir'] . "/" . $moduleName . "/manifest.json";
        
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            
            // Qual o nome do filho atual na URL sendo carregado?
            $activeTabStr = self::$routeInfo['info']['child_name'] ?? array_key_first($manifest);

            foreach ($manifest as $key => $data) {
                $isActive = ($key === $activeTabStr) ? 'active' : '';
                $icon = htmlspecialchars($data['icon']);
                $title = htmlspecialchars($data['title']);

                // Aqui definimos o Roteamento em base relativa (Pasta Pai / FIlho)
                $url = "{$moduleName}/{$key}";
                
                echo <<<HTML
                <li class="menu-item {$isActive}">
                    <a href="{$url}" class="dynamic-trigger" data-target="{$target}">
                        <i class="fa fa-fw {$icon}"></i>
                        <span class="ps-2">{$title}</span>
                    </a>
                </li>
                HTML;
            }
        }
    }

    public static function Process($body) {
        self::$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        $url = isset($body['content']) ? rtrim($body['content'], '/') : self::$config['defaultPage'];
        self::$rota = explode('/', $url);

        $file = self::$rota[0];

        // Exceção de Popups
        if (str_contains($file, "popup-")) {
            $route = str_replace('popup-', '', $file);
            $arquivo_pagina = self::$config['popupDir']."/" . $route . ".php";

            if (self::$isAjax) {
                if (file_exists($arquivo_pagina)) {
                    include $arquivo_pagina;
                } else {
                    http_response_code(404);
                    echo "Popup não encontrado.";
                }
                exit;
            }
        }

        // Rota Parcial: Retorna só o filho (sem wrapper) quando chamado por navegação interna (sidebar)
        self::$isPartial = self::$isAjax && isset($body['partial']) && $body['partial'] == '1';

        self::$routeInfo = self::Resolve(self::$config['baseDir'], self::$rota);

        if (self::$isPartial) {
            if (self::$routeInfo && !empty(self::$routeInfo['info']['child_path']) && file_exists(self::$routeInfo['info']['child_path'])) {
                include self::$routeInfo['info']['child_path'];
            } else {
                http_response_code(404);
                include self::$config['errorDir'].'/404.php';
            }
            exit;
        }
    }

    public static function Render() {
        if (self::$routeInfo === null) {
            http_response_code(404);
            include self::$config['errorDir'] . "/404.php";
            return;
        }

        if (self::$routeInfo['status'] === 403) {
            http_response_code(403);
            echo "<h3>403 Acesso Negado</h3><p>O arquivo requisitado não está no Manifesto de roteamento seguro desta pasta.</p>";
            return;
        }

        global $user, $currentPanel, $link, $dir;
        
        // Para que o RenderChild() funcione dentro do arquivo pai
        $routeInfo = self::$routeInfo['info'] ?? null;
        
        include self::$routeInfo['path'];
    }

    public static function RenderChild() {
        if (self::$routeInfo && self::$routeInfo['info']['is_nested'] && file_exists(self::$routeInfo['info']['child_path'])) {
             include self::$routeInfo['info']['child_path'];
        } else {
             echo "<p class='text-muted'>Selecione uma opção no menu ao lado.</p>";
        }
    }

    private static function Resolve($basePath, $fragments) {
        $currentPathDir = $basePath;
        $moduleName = $fragments[0];
        
        $masterView = $currentPathDir . "/" . $moduleName . ".php";
        
        if (!file_exists($masterView)) {
            return null;
        }

        $result = [
            'status' => 200,
            'path' => $masterView,
            'info' => [
                'module' => $moduleName,
                'is_nested' => false,
                'child_path' => null
            ]
        ];
        
        $moduleDir = $currentPathDir . "/" . $moduleName;
        
        if (is_dir($moduleDir)) {
            if (count($fragments) > 1 && !empty($fragments[1])) {
                $subFileName = $fragments[1];
                
                $manifestPath = $moduleDir . "/manifest.json";
                if (file_exists($manifestPath)) {
                    $manifestJSON = json_decode(file_get_contents($manifestPath), true);
                    if (!isset($manifestJSON[$subFileName])) {
                        return ['status' => 403];
                    }
                }
                
                $childPath = $moduleDir . "/" . $subFileName . ".php";
                if (file_exists($childPath)) {
                    $result['info']['is_nested'] = true;
                    $result['info']['child_path'] = $childPath;
                    $result['info']['child_name'] = $subFileName;
                } else {
                     return null;
                }
            } else {
                $manifestPath = $moduleDir . "/manifest.json";
                if (file_exists($manifestPath)) {
                    $manifestJSON = json_decode(file_get_contents($manifestPath), true);
                     if (is_array($manifestJSON) && count($manifestJSON) > 0) {
                         $firstKey = array_keys($manifestJSON)[0];
                         $result['info']['is_nested'] = true;
                         $result['info']['child_path'] = $moduleDir . "/" . $firstKey . ".php";
                         $result['info']['child_name'] = $firstKey;
                     }
                }
            }
        }
        
        return $result;
    }
}

?>