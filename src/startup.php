<?php

final class Startup {

    public static function Module(string $module) {
        $root = $_SERVER['DOCUMENT_ROOT'];
        $modulePath = str_contains($module, ".php") ? $module : $module . ".php";
        
        // Verifica se tem "/" (caminho com pasta)
        if (str_contains($module, '/') || str_contains($module, '\\')) {
            // Busca a partir da raiz do projeto
            $path = "$root/$modulePath";
        } else {
            // Busca na pasta atual (onde o arquivo que chamou está)
            $caller = debug_backtrace()[0]['file'];
            $currentDir = dirname($caller);
            $path = "$currentDir/$modulePath";
        }
        
        if (!file_exists($path)) {
            throw new \Exception("Module $module não encontrado! Caminho: $path");
        }
        
        require_once $path;
    }
}

?>