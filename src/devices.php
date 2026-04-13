<?php

class Devices {
    public static function getUUID() {
        $ip = \Http::GetUserIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        return Security::Hash("$userAgent@$ip");
    }
}

?>