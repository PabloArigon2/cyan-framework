<?php

final class Env {
    static $loaded = false;
    public static function Load($path = "") {
        if (empty($path))
            $path = Utils::RootFolder();

        if (!class_exists(\Dotenv\Dotenv::class)) {
            throw new Exception("[ ERRO ] Não foi possível carregar variáveis de ambiente! Dependência PHPDOTENV não carregada!");
            return false;
        }

        $dotenv = Dotenv\Dotenv::createImmutable($path);
        self::$loaded = $dotenv->safeLoad();
        return self::$loaded;
    }

    public static function Get($key, bool $json = false) {
        if (!self::$loaded) return null;
        if (!isset($_ENV[$key])) return null;

        $val = $_ENV[$key];

        if ($json) {
            $val = json_decode($val, true);
        }
        
        return $val;
    }
}

?>