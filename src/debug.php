<?php

class Debug {

    static $database = null;

    public static function Start() {
        register_shutdown_function('Debug::shutdownHandler');
        set_exception_handler('Debug::exceptionHandler');
        set_error_handler('Debug::errorHandler');
    }

    public static function Stop() {
        restore_error_handler();
        restore_exception_handler();
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $message = "[ ERRO ] [$errno] $errstr em $errfile:$errline";
        error_log($message);
        return true;
    }

    public static function exceptionHandler($ex) {
        $message = "[ EXCEPTION ] [".$ex->getMessage()."] em ".$ex->getFile().":".$ex->getLine();
        error_log($message);
    }

    public static function shutdownHandler() {
        $erro  = error_get_last();

        if ($erro) {
            $message = "[ FATAL ] [".$erro['message']."] em ".$erro['file'].":".$erro['line'];
            error_log($message);
        }
    }
}

class ErrType {
    public const ERROR = 0;
    public const EXCEPTION = 1;
    public const FATAL = 2;
}

?>