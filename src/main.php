<?php

if (file_exists(__DIR__ . "/vendor/autoload.php")) {
    require_once __DIR__ . "/vendor/autoload.php";
} else {
    die("Autoload PSR-4 não encontrado. Execute 'composer dump-autoload'.");
}

Debug::Start();

date_default_timezone_set(Utils::Env('APP_TIMEZONE') ?? 'America/Bahia');

$dir = Utils::getDirLink();

?>