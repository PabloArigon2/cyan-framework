<?php

define("SELF", $_SERVER['PHP_SELF'] ?? '');

class Utils {

    // --- Especializadas (Proxies para Retrocompatibilidade) ---

    public static function Env($key) { 
        return isset($_ENV[$key]) ? $_ENV[$key] : null; 
    }

    public static function AuthenticateRequest() { 
        return Auth::AuthenticateRequest(); 
    }

    public static function CurrentUser(): User|null { 
        return Auth::CurrentUser(); 
    }

    public static function GetUser($id = null) { 
        return Auth::GetUser($id); 
    }

    public static function GetUserData(int|null $id_usuario = null, array|null $row = null): User|null { 
        return Auth::GetUserData($id_usuario, $row); 
    }

    public static function GetTenantData(int|null $id_tenant = null, string|null $identifier = null, array|null $row = null): array { 
        return Auth::GetTenantData($id_tenant, $identifier, $row); 
    }

    public static function GetTenantRegistry($id_usuario) { 
        return Auth::GetTenantRegistry($id_usuario); 
    }

    public static function GetTenantID($registro) { 
        return Auth::GetTenantID($registro); 
    }

    public static function ValidateUserTenant($id_usuario, $id_tenant) { 
        return Auth::ValidateUserTenant($id_usuario, $id_tenant); 
    }

    public static function GetDeviceId() { 
        return Auth::GetDeviceId(); 
    }

    public static function HttpRequest($url, $method, $headers = [], $body = []) { 
        return Http::Request($url, $method, $headers, $body); 
    }

    public static function Response($headers, $result, $code = 200) { 
        Http::Response($headers, $result, $code); 
    }

    public static function GetHeader($header) { 
        return Http::GetHeader($header); 
    }

    public static function GetUserIP() { 
        return Http::GetUserIP(); 
    }

    public static function getDirLink() { 
        return Url::getDirLink(); 
    }

    public static function getPageLink() { 
        return Url::getPageLink(); 
    }

    public static function getFullUrl() { 
        return Url::getFullUrl(); 
    }

    public static function GetUfs() { 
        return GeoData::GetUfs(); 
    }

    public static function SearchUf($uf) { 
        return GeoData::SearchUf($uf); 
    }

    public static function GetCountries() { 
        return GeoData::GetCountries(); 
    }

    public static function RandomString($length = 8, $onlyNumbers = false, $onlyLetters = false, $includeNonAlphaNumerical = false, $customCharacters = "") { 
        return Str::Random($length, $onlyNumbers, $onlyLetters, $includeNonAlphaNumerical, $customCharacters); 
    }

    public static function RandomNumbers($length = 4) { 
        return Str::RandomNumbers($length); 
    }

    public static function SanitizeInput($str) { 
        return Str::Sanitize($str); 
    }

    public static function ArrayJoin(array ...$arrays): array { 
        return Str::Join(...$arrays); 
    }

    public static function LoadJson($path) { 
        return Str::LoadJson($path); 
    }

    public static function hexToRgb(string $hexColor): array { 
        return Color::HexToRgb($hexColor); 
    }

    public static function calculateLuminance(int $r, int $g, int $b): float { 
        return Color::Luminance($r, $g, $b); 
    }

    public static function getSmartHoverTextColor(string $bgColor, string $preferred = '#45a7c4', string $fallback = '#000000'): string { 
        return Color::SmartHover($bgColor, $preferred, $fallback); 
    }

    public static function getContrastTextColor(string $hexColor): string { 
        return Color::Contrast($hexColor); 
    }

    // --- Métodos de Sistema ---

    public static function DocList() {
        return [ "Curriculum", "Imagem", "Documento", "RG", "CNH" ];
    }

    public static function GetDeviceUUID() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = self::GetUserIP();
        return hash('sha256', ($userAgent . ":" . $ip));
    }
}

?>