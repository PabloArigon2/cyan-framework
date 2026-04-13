<?php

class SecureSession {
    public static function start($params) {       
        session_set_cookie_params($params);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Validar sessão
        return self::validate();
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    public static function validate() {
        // Timeout de sessão
        if (isset($_SESSION['LAST_ACTIVITY'])) {
            if (time() - $_SESSION['LAST_ACTIVITY'] > (3600 * 24)) {
                self::destroy();
                return false;
            }
        }
        $_SESSION['LAST_ACTIVITY'] = time();
        
        // Validar IP (opcional, pode causar problemas com proxies)
        // if (isset($_SESSION['IP_ADDRESS'])) {
        //     if ($_SESSION['IP_ADDRESS'] !== $_SERVER['REMOTE_ADDR']) {
        //         self::destroy();
        //         return false;
        //     }
        // } else {
        //     $_SESSION['IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
        // }
        
        // Validar User-Agent
        if (isset($_SESSION['USER_AGENT'])) {
            if ($_SESSION['USER_AGENT'] !== $_SERVER['HTTP_USER_AGENT']) {
                self::destroy();
                return false;
            }
        } else {
            $_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
        }
        
        return true;
    }
    
    public static function destroy() {
        session_unset();
        session_destroy();
        
        // Limpar cookie
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

?>