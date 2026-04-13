<?php

class Debug {

    static $database = null;

    public static function Start() {
        register_shutdown_function('Debug::shutdownHandler');
        set_exception_handler('Debug::exceptionHandler');
        set_error_handler('Debug::errorHandler');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // try { 

        //     self::$database = Database::CreateContext("localhost", "root", "", "develop");
        //     Database::QueryCtx(self::$database, "CREATE TABLE IF NOT EXISTS `debug_reporter` (
        //             `id` INT(11) NOT NULL AUTO_INCREMENT,
        //             `referencia` VARCHAR(32) NOT NULL COLLATE 'utf8_general_ci',
        //             `mensagem` LONGTEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci',
        //             `arquivo` LONGTEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci',
        //             `linha` LONGTEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci',
        //             `status` INT(11) NULL DEFAULT NULL COMMENT '0 - Reportado; 1 - Consertado',
        //             `tipo` INT(11) NULL DEFAULT NULL COMMENT '0 - Erro; 1 - Exception; 2 - Fatal',
        //             `data_cadastro` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        //             `cliente` INT(11) NULL DEFAULT '0',
        //             PRIMARY KEY (`id`) USING BTREE,
        //             UNIQUE INDEX `referencia` (`referencia`) USING BTREE
        //         )
        //         COLLATE='utf8_general_ci'
        //         ENGINE=InnoDB
        //         AUTO_INCREMENT=1
        //     ;");
        // }
        // catch(\Throwable $ex) {
        //     error_log("[ DEBUG ERROR ] Erro ao criar tabela de reportes!".PHP_EOL.
        //     "   => ".$ex->getMessage());
        // }
    }

    public static function Stop() {
        restore_error_handler();
        restore_exception_handler();
        mysqli_report(MYSQLI_REPORT_OFF);
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $errType = ErrType::ERROR;
        $message = "[ ERRO ] [$errno] $errstr em $errfile:$errline";
        error_log($message);

        // $referencia = md5("error_reported_".date("Y-m-d H:i:s")."_".microtime(true));

        // try {
        //     $file = $errfile;
        //     $line = $errline;

        //     Database::QueryCtx(self::$database, "INSERT INTO debug_reporter(referencia, mensagem, arquivo, linha, status, tipo) VALUES(?, ?, ?, ?, 0, ?, ?)", [
        //         new Parameter("s", $referencia),
        //         new Parameter("s", $message),
        //         new Parameter("s", $file),
        //         new Parameter("s", $line),
        //         new Parameter("i", $errType)
        //     ]);
        // }
        // catch (\Throwable $ex) {
        //     error_log("[ DEBUG ERROR ] Erro ao salvar error no banco!".PHP_EOL.
        //     "   => ".$message.PHP_EOL.
        //     "   => ".$ex->getMessage());
        // }

        return true;
    }

    public static function exceptionHandler($ex) {
        $errType = ErrType::EXCEPTION;
        $message = "[ EXCEPTION ] [".$ex->getMessage()."] em ".$ex->getFile().":".$ex->getLine();
        error_log($message);

        // $referencia = md5("exception_reported_".date("Y-m-d H:i:s")."_".microtime(true));

        // try {
        //     $file = $ex->getFile();
        //     $line = $ex->getLine();

        //     Database::QueryCtx(self::$database, "INSERT INTO debug_reporter(referencia, mensagem, arquivo, linha, status, tipo) VALUES(?, ?, ?, ?, 0, ?, ?)", [
        //         new Parameter("s", $referencia),
        //         new Parameter("s", $message),
        //         new Parameter("s", $file),
        //         new Parameter("s", $line),
        //         new Parameter("i", $errType)
        //     ]);
        // }
        // catch (\Throwable $ex) {
        //     error_log("[ DEBUG ERROR ] Erro ao salvar exceção no banco!".PHP_EOL.
        //     "  [ MESSAGE ]     => ".$message.PHP_EOL.
        //     "  [ REG QRY ERR ] => ".$ex->getMessage());
        // }
    }

    public static function shutdownHandler() {
        $erro  = error_get_last();

        if ($erro) {
            $errType = ErrType::FATAL;
            $message = "[ FATAL ] [".$erro['message']."] em ".$erro['file'].":".$erro['line'];
            error_log($message);

            // $referencia = md5("fatal_reported_".date("Y-m-d H:i:s")."_".microtime(true));

            // try {
            //     $file = $erro['file'];
            //     $line = $erro['line'];

            //     Database::QueryCtx(self::$database, "INSERT INTO debug_reporter(referencia, mensagem, arquivo, linha, status, tipo) VALUES(?, ?, ?, ?, 0, ?, ?)", [
            //         new Parameter("s", $referencia),
            //         new Parameter("s", $message),
            //         new Parameter("s", $file),
            //         new Parameter("s", $line),
            //         new Parameter("i", $errType)
            //     ]);
            // }
            // catch (\Throwable $ex) {
            //     error_log("[ DEBUG ERROR ] Erro ao salvar shutdown no banco!".PHP_EOL.
            //     "   => ".$message.PHP_EOL.
            //     "   => ".$ex->getMessage());
            // }
        }
    }
}

class ErrType {
    public const ERROR = 0;
    public const EXCEPTION = 1;
    public const FATAL = 2;
}

?>