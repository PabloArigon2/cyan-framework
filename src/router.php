<?php

/**
 * ViewContext — DTO fortemente tipado que substitui a global $user
 * nos templates renderizados pelo Router.
 */
final class ViewContext {
    public readonly ?object $user;
    public readonly ?string $panel;
    public readonly string  $link;
    public readonly string  $dir;
    public readonly array   $route;

    public function __construct(?object $user, ?string $panel, string $link, string $dir, array $route) {
        $this->user  = $user;
        $this->panel = $panel;
        $this->link  = $link;
        $this->dir   = $dir;
        $this->route = $route;
    }
}

final class Router {
    public static $config = [
        "baseDir"     => 'content',
        "popupDir"    => 'content/popup',
        "errorDir"    => 'content/errors',
        "defaultPage" => 'home'
    ];

    private static $isAjax   = false;
    private static $isPartial = false;
    private static $routeInfo = null;
    private static $rota      = [];
    private static ?ViewContext $viewContext = null;

    public static function isAjax()       { return self::$isAjax; }
    public static function isPartial()    { return self::$isPartial; }
    public static function getRouteInfo() { return self::$routeInfo; }
    public static function getRota()      { return self::$rota; }
    public static function getView()      { return self::$viewContext; }

    /**
     * Sanitiza nome de rota, bloqueando path traversal (LFI).
     * Apenas letras, números, hífens e underscores são permitidos.
     */
    private static function SanitizeFragment(string $fragment): string {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $fragment);
    }

    public static function ProcessMenu($target = null, $additHtml = "") {
        if (!self::$routeInfo || empty(self::$routeInfo['info']['module'])) return;

        $moduleName   = self::$routeInfo['info']['module'];
        $manifestPath = self::$config['baseDir'] . "/" . $moduleName . "/manifest.json";

        if (!file_exists($manifestPath)) return;

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest) || !isset($manifest['pages'])) return;

        $categorias = $manifest['categorias'] ?? [];
        $pages = $manifest['pages'];

        $activeTabStr = self::$routeInfo['info']['child_name'] ?? array_key_first($pages);

        $code = [];
        $code[''] = [
            "label" => null,
            "items" => []
        ];

        foreach($categorias as $key => $label) {
            $code[$key] = [
                "label" => $label,
                "items" => []
            ];
        }

        foreach($pages as $key => $data) {
            $icon  = htmlspecialchars($data['icon']  ?? '', ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $url   = htmlspecialchars("{$moduleName}/{$key}", ENT_QUOTES, 'UTF-8');

            // Verifica se a page tem submenus
            if (!empty($data['submenus']) && is_array($data['submenus'])) {
                // Verificar se algum submenu filho está ativo
                $parentActive = false;
                $subItems = [];
                foreach ($data['submenus'] as $subKey => $subData) {
                    $subIsActive = ($subKey === $activeTabStr);
                    if ($subIsActive) $parentActive = true;
                    $subTitle = htmlspecialchars($subData['title'] ?? '', ENT_QUOTES, 'UTF-8');
                    $subUrl   = htmlspecialchars("{$moduleName}/{$subKey}", ENT_QUOTES, 'UTF-8');
                    $subItems[] = [
                        "active" => $subIsActive ? 'active' : '',
                        "url" => $subUrl,
                        "title" => $subTitle
                    ];
                }

                $itemData = [
                    "type" => "parent",
                    "active" => $parentActive ? 'active' : '',
                    "icon" => $icon,
                    "title" => $title,
                    "collapse_id" => "collapse-{$key}",
                    "submenus" => $subItems
                ];
            } else {
                $isActive = ($key === $activeTabStr) ? 'active' : '';
                $itemData = [
                    "type" => "item",
                    "active" => $isActive,
                    "icon" => $icon,
                    "url" => $url,
                    "title" => $title
                ];
            }

            if (!empty($data['categoria']) && isset($code[$data['categoria']])) {
                $code[$data['categoria']]['items'][] = $itemData;
            } else {
                $code['']['items'][] = $itemData;
            }
        }

        foreach($code as $data) {
            if (empty($data['items'])) continue;

            $grupoAttr = $data['label'] ? " grupo=\"{$data['label']}\"" : "";

            echo <<<HTML
                <ul class="sidebar-nav-menu"{$grupoAttr}>
            HTML;

            foreach($data['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'parent') {
                    $parentActiveClass = $item['active'];
                    $collapseId = $item['collapse_id'];
                    echo <<<HTML
                    <li class="sidebar-nav-item-parent">
                        <a class="submenu-toggle {$parentActiveClass}">
                            <i class="{$item['icon']}"></i> {$item['title']}
                            <i class="bi bi-chevron-down" style="font-size: 12px; margin-left: auto;"></i>
                        </a>
                        <ul class="submenu-list" id="{$collapseId}">
                    HTML;

                    foreach ($item['submenus'] as $sub) {
                        echo <<<HTML
                            <li class="sidebar-nav-item spa-router {$sub['active']}" target="{$sub['url']}">
                                {$sub['title']}
                            </li>
                        HTML;
                    }

                    echo <<<HTML
                        </ul>
                    </li>
                    HTML;
                } else {
                    echo <<<HTML
                    <li class="sidebar-nav-item spa-router {$item['active']}" target="{$item['url']}">
                        <i class="{$item['icon']}"></i> {$item['title']}
                    </li>
                    HTML;
                }
            }

            echo <<<HTML
                </ul>
            HTML;
        }

        if (!empty($additHtml)) {
            echo <<<HTML
            <div class="sidebar-append" style="display: contents;">
                {$additHtml}
            </div>
            HTML;
        }
    }

    public static function Process($body) {
        self::$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        $url = isset($body['content']) ? rtrim($body['content'], '/') : self::$config['defaultPage'];
        self::$rota = array_map([self::class, 'SanitizeFragment'], explode('/', $url));

        $file = self::$rota[0];

        // Popups — blindagem LFI: sanitizado acima, nenhum ".." ou "/" externo sobrevive
        if (str_starts_with($file, "popup-")) {
            $route = str_replace('popup-', '', $file);
            $arquivo_pagina = self::$config['popupDir'] . "/" . $route . ".php";

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

        // Constrói o ViewContext DTO
        $userObj = Utils::CurrentUser();
        self::$viewContext = new ViewContext(
            $userObj,
            $_SESSION['currentPanel'] ?? null,
            Utils::getPageLink(),
            Utils::getDirLink(),
            self::$rota
        );

        if (self::$isPartial) {
            if (self::$routeInfo && !empty(self::$routeInfo['info']['child_path']) && file_exists(self::$routeInfo['info']['child_path'])) {
                $view = self::$viewContext;
                include self::$routeInfo['info']['child_path'];
            } else {
                http_response_code(404);
                include self::$config['errorDir'] . '/404.php';
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

        if (isset(self::$routeInfo['status']) && self::$routeInfo['status'] === 403) {
            http_response_code(403);
            include self::$config['errorDir'] . "/403.php";
            return;
        }

        // Injeta ViewContext ao invés de globals
        $view = self::$viewContext;
        $routeInfo = self::$routeInfo['info'] ?? null;

        include self::$routeInfo['path'];
    }

    public static function RenderChild() {
        if (self::$routeInfo && self::$routeInfo['info']['is_nested'] && file_exists(self::$routeInfo['info']['child_path'])) {
            $view = self::$viewContext;
            include self::$routeInfo['info']['child_path'];
        } else {
            echo "<p class='text-muted'>Selecione uma opção no menu ao lado.</p>";
        }
    }

    private static function Resolve($basePath, $fragments) {
        $moduleName = $fragments[0];

        if (empty($moduleName)) return null;

        $masterView = $basePath . "/" . $moduleName . ".php";

        if (!file_exists($masterView)) {
            return null;
        }

        $result = [
            'status' => 200,
            'path'   => $masterView,
            'info'   => [
                'module'     => $moduleName,
                'is_nested'  => false,
                'child_path' => null,
                'child_name' => null
            ]
        ];

        $moduleDir = $basePath . "/" . $moduleName;

        if (is_dir($moduleDir)) {
            if (count($fragments) > 1 && !empty($fragments[1])) {
                $subFileName = $fragments[1];

                $manifestPath = $moduleDir . "/manifest.json";
                if (file_exists($manifestPath)) {
                    $manifestJSON = json_decode(file_get_contents($manifestPath), true);

                    if (is_array($manifestJSON) && isset($manifestJSON['pages'])) {
                        // Verifica se é uma page direta OU um submenu de alguma page
                        $isAllowed = isset($manifestJSON['pages'][$subFileName]);
                        
                        if (!$isAllowed) {
                            foreach ($manifestJSON['pages'] as $pageData) {
                                if (!empty($pageData['submenus']) && isset($pageData['submenus'][$subFileName])) {
                                    $isAllowed = true;
                                    break;
                                }
                            }
                        }

                        if (!$isAllowed) {
                            return ['status' => 403, 'info' => []];
                        }
                    }
                }

                $childPath = $moduleDir . "/" . $subFileName . ".php";
                if (file_exists($childPath)) {
                    $result['info']['is_nested']   = true;
                    $result['info']['child_path']   = $childPath;
                    $result['info']['child_name']   = $subFileName;
                } else {
                    return null;
                }
            } else {
                $manifestPath = $moduleDir . "/manifest.json";
                if (file_exists($manifestPath)) {
                    $manifestJSON = json_decode(file_get_contents($manifestPath), true);

                    if (is_array($manifestJSON) && isset($manifestJSON['pages']) && count($manifestJSON['pages']) > 0) {
                        $firstKey = array_keys($manifestJSON['pages'])[0];
                        $result['info']['is_nested']   = true;
                        $result['info']['child_path']   = $moduleDir . "/" . $firstKey . ".php";
                        $result['info']['child_name']   = $firstKey;
                    }
                }
            }
        }

        return $result;
    }
}

?>