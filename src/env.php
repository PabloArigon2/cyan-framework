<?php

final class EnvVariables {
    public static function Load($path = "") {
        if (empty($path))
            $path = Utils::RootFolder();

        $dotenv = Dotenv\Dotenv::createImmutable($path);
        return $dotenv->safeLoad();
    }
}

?>