<?php

if (file_exists(__DIR__ . "/vendor/autoload.php")) {
    require_once __DIR__ . "/vendor/autoload.php";
} else {
    die("Autoload PSR-4 não encontrado. Execute 'composer dump-autoload'.");
}

use Security as S;

Debug::Start();

date_default_timezone_set('America/Bahia');

$dir = Utils::getDirLink();

function isEvent($v1, $event) {
    if ($v1 == $event) { return true; }
    return false;
}
?>