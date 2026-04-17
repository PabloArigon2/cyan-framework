<?php

class Config {
    static array $config = [];
    static bool $loaded = false;
    
    public static function LoadConfig() {
        $path = Utils::RootFolder() . "/config/app.php";

        if (file_exists($path)) {
            self::$config = require $path;
        } else {
            self::$config = [];
        }

        self::$loaded = true;
    }

    public static function Get(string ...$keys) {
        if (!self::$loaded) {
            self::LoadConfig();
        }

        if (empty($keys)) {
            return self::$config;
        }

        $cur = self::$config;

        foreach ($keys as $key) {
            if (!is_array($cur) || !array_key_exists($key, $cur)) {
                return null;
            }

            $cur = $cur[$key];
        }

        return $cur;
    }
}

?>