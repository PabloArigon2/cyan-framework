<?php

// interessante, processar envio de logs usando o fastcgi_finish_request(); e depois enviando os registros de bug

class Debug {

    static $database = null;
    static $postProcessingData = [];
    static ?Cache $cacheInstance = null;

    public static function Start() {
        register_shutdown_function('Debug::shutdownHandler');
        set_exception_handler('Debug::exceptionHandler');
        set_error_handler('Debug::errorHandler');

        self::$cacheInstance = Cache::init(Driver::FILE, [
            'path' => Utils::RootFolder()."/cache"
        ]);
    }

    public static function SendReport() {
        
    }

    public static function Stop() {
        restore_error_handler();
        restore_exception_handler();
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $message = "[ ERRO ] [$errno] $errstr em $errfile:$errline";
        error_log($message);
        
        if (self::$cacheInstance) {
            $cachedErrors = [ "errNumber" => $errno, "errStr" => $errstr, "errFile" => $errfile, "errLine" => $errline ];
            self::$cacheInstance->append("error_cache", $cachedErrors);
        }

        return true;
    }

    public static function exceptionHandler(Throwable $ex) {
        $message = "[ EXCEPTION ] [".$ex->getMessage()."] em ".$ex->getFile().":".$ex->getLine();
        error_log($message);

        if (self::$cacheInstance) {
            $cachedErrors = [ "errNumber" => $ex->getCode(), "errStr" => $ex->getMessage(), "errFile" => $ex->getFile(), "errLine" => $ex->getLine() ];
            self::$cacheInstance->append("error_cache", $cachedErrors);
        }
    }

    public static function shutdownHandler() {
        $erro  = error_get_last();

        if ($erro) {
            $message = "[ FATAL ] [".$erro['message']."] em ".$erro['file'].":".$erro['line'];
            error_log($message);

            if (self::$cacheInstance) {
                $cachedErrors = [ "errNumber" => $erro['type'], "errStr" => $erro['message'], "errFile" => $erro['file'], "errLine" => $erro['line'] ];
                self::$cacheInstance->append("error_cache", $cachedErrors);
            }
        }
    }
}

class ErrType {
    public const ERROR = 0;
    public const EXCEPTION = 1;
    public const FATAL = 2;
}

?>