<?php

final class Str {
    public static function Random($length = 8, $onlyNumbers = false, $onlyLetters = false, $includeNonAlphaNumerical = false, $customCharacters = "") {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($onlyNumbers) { $characters = '0123456789'; }
        else if ($onlyLetters) { $characters = 'abcdefghijklmnopqrstuvwxyz'; }
        if ($includeNonAlphaNumerical) { $characters .= ".,@;?{}"; }
        $characters .= $customCharacters;

        $randstring = '';
        for ($i = 0; $i < $length; $i++) { $randstring .= $characters[rand(0, strlen($characters) - 1)]; }
        return $randstring;
    }

    public static function RandomNumbers($length = 4) {
        $result = '';
        for ($i = 0; $i < $length; $i++) { $result .= random_int(0, 9); }
        return $result;
    }

    public static function Sanitize($str) {
        return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
    }

    public static function Join(array ...$arrays): array {
        return array_merge([], ...$arrays);
    }

    public static function LoadJson($path) {
        if (!file_exists($path)) return null;
        $json = file_get_contents($path);
        return ($json === false) ? null : json_decode($json, true);
    }
}
