<?php

class CSRF {
    public static function generateToken() {
        return Session::csrf();
    }
    
    public static function validateToken($token) {
        return Session::validateCsrf($token);
    }
    
    public static function getTokenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . 
               htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

?>