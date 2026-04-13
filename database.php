<?php

require_once "utils.php";

class QueryResult
{
    public $values = array();
    public $error = null;
    public $errorCode = 0;
    public $rows = 0;
    public $duplicated = false;

    function SetError($err)
    {
        $this->error = $err;
    }

    function SetErrorCode($errCode) {
        $this->errorCode = $errCode;
    }

    function __construct($arr)
    {
        $this->values = $arr;
    }

    public function isDuplicated() {
        return str_contains($this->error(), "1062") or $this->errorCode == 1062;
    }

    public function getDuplicateKey() {
        if (!$this->isDuplicated()) return null;
        
        // Regex para pegar o que está dentro de 'key' na mensagem do MySQL
        if (preg_match("/for key '(.+?)'/", $this->error(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function isFieldValid($idx, $field)
    {
        return ($this->values[$idx][$field] != null and !empty($this->values[$idx][$field]));
    }

    public function field($idx, $field)
    {
        return $this->values[$idx][$field] ?? null;
    }

    public function length()
    {
        return gettype($this->values) == "array" ? count($this->values) : 0;
    }

    public function error()
    {
        return $this->error ?? "";
    }

    public function getjson($idx = null)
    {
        return ($idx != null and $idx >= 0) ? json_encode($this->values[$idx]) : json_encode($this->values);
    }

    public function each($func)
    {
        foreach ($this->values as $value) {
            $func($value);
        }
    }

    public function for($func)
    {
        for ($i = 0; $i < $this->length(); $i++) {
            $func($i, $this->get($i));
        }
    }

    public function isValid()
    {
        if ((gettype($this->values) == "boolean" and $this->values) or ($this->values and gettype($this->values) == "array" and ($this->error == null or empty($this->error)))) return true;
        else return false;
    }

    public function hasError()
    {
        return ($this->error == null or $this->error == "" or empty($this->error)) ? false : true;
    }

    public function validExecute(){
        return (!$this->hasError() and $this->get());
    }

    public function validQuery() {
        return ($this->isValid() and $this->length() > 0);
    }

    public function isBoolean()
    {
        return gettype($this->values) == "boolean";
    }

    public function set($i, $v)
    {
        $this->values[$i] = $v;
    }

    public function setRows($rows) {
        $this->rows = $rows;
    }

    public function rowsChanged() {
        return $this->rows;
    }

    public function get($param = null)
    {
        if ($param === null) {
            return $this->values;
        } else {
            return $this->values[$param];
        }
    }
}

class Parameter
{
    public $type = "";
    public $value = "";

    function __construct($type = "s", $value = "")
    {
        $this->type = $type;
        $this->value = $value;
    }
}

function parameter($type, $val) {
    return new Parameter($type, $val);
}

class Database
{
    public static $server = "";
    public static $user = "";
    public static $pass = "";
    public static $dbn = "";
    public static $port = 0;
    static $onTransaction = false;
    static $context = null;

    public static function Escape($val)
    {
        return mysqli_real_escape_string(self::$context, $val);
    }

    public static function CreateContext($server, $user, $pass, $db, $port = 3306)
    {
        return mysqli_connect($server, $user, $pass, $db, $port);
    }

    public static function QueryCtx($ctx, $str, $params = [])
    {
        if ($ctx == false) {
            return array();
        } else {
            return self::Query($str, $params, $ctx);
        }
    }

    public static function GetContext()
    {
        return self::$context;
    }

    public static function GetStatus() {
        return mysqli_stat(self::$context);
    }

    public static function Initialize($server, $user, $pass, $dbname)
    {
        self::$server = $server;
        self::$user = $user;
        self::$pass = $pass;
        self::$dbn = $dbname;

        $sv = $_SERVER['SERVER_NAME'] == "localhost" ? self::$server : "p:".self::$server;

        $ctx = mysqli_connect($sv, self::$user, self::$pass, self::$dbn);

        if (!$ctx)
            die("Erro ao conectar ao banco!");

        self::$context = $ctx;
        return $ctx;
    }

    public static function Query($str, $params = [], $context = null, $tryCatch = true) : QueryResult
    {
        $qr = new QueryResult([]);

        if (empty($context) and empty(self::$context))
        {
            $qr->SetError("Invalid Database Context!");
            return $qr;
        }

        if (empty($context)) { $context = self::$context; }       

        if ($tryCatch) {
            try {
                return self::Query($str, $params, $context, false);
            }
            catch (Exception $ex) {
                $qr->SetError($ex->getMessage());
                return $qr;
            }
        }

        $stmt = mysqli_prepare($context, $str);

        if ($stmt === false) {
            $qr->SetError("Erro na preparação da consulta: " . mysqli_error($context));
            $qr->SetErrorCode(mysqli_errno($context));
            return $qr;
        }

        $types = "";
        $arrParams = array();
        $refCount = 0;

        foreach ($params as $param) {
            if ($param instanceof Parameter) {
                $types .= $param->type;
                $arrParams[] = &$param->value;
            } else {

                $tempValue = null;
                $tempType = null;
                $refName = "ref_val_".$refCount++;

                $$refName = null;

                if (gettype($param) == "boolean") {
                    $tempType .= "i";
                    $tempValue = ($param ? 1 : 0);
                }
                else if (gettype($param) == "integer") {
                    $tempType .= "i";
                    $tempValue = $param;
                }
                else if (gettype($param) == "string") {
                    $tempType .= "s";
                    $tempValue = $param;
                }
                else if (gettype($param) == "double") {
                    $tempType .= "d";
                    $tempValue = $param;
                }
                else if (gettype($param) == "array") {
                    $tempType .= "s";
                    $tempValue = json_encode($param);
                }
                else if (is_null($param)) {
                    $tempType .= "s";
                    $tempValue = null;
                }

                $$refName = $tempValue;

                $types .= $tempType;
                $arrParams[] = &$$refName;
            }
        }

        if ($types != "" && count($arrParams) > 0) {
            array_unshift($arrParams, $stmt, $types);
            call_user_func_array('mysqli_stmt_bind_param', $arrParams);
        }

        $executeResult = mysqli_stmt_execute($stmt);

        if (!$executeResult) {
            $qr->SetError("Erro na execução da consulta: " . mysqli_error($context));
            $qr->SetErrorCode(mysqli_errno($context));
            return $qr;
        }

        if (str_contains($str, "SELECT") && (strpos($str, "SELECT") === 0 or str_starts_with(strtolower($str), strtolower("SELECT")))) {
            $meta = mysqli_stmt_result_metadata($stmt);
            $fields = mysqli_fetch_fields($meta);

            // Aqui, criamos um array para armazenar as referências dos campos
            $bindNames = [];
            $resultArray = [];

            foreach ($fields as $field) {
                // Cria uma variável para cada campo (sem referência)
                $resultArray[$field->name] = null;
                $bindNames[] = &$resultArray[$field->name]; // Liga as variáveis à referência
            }

            call_user_func_array('mysqli_stmt_bind_result', array_merge([$stmt], $bindNames));

            $rows = [];
            while (mysqli_stmt_fetch($stmt)) {
                // Aqui, ao invés de adicionar diretamente $resultArray, criamos uma cópia
                $rowCopy = [];
                foreach ($resultArray as $key => $value) {
                    $rowCopy[$key] = $value; // Fazendo uma cópia dos valores de $resultArray
                }
                $rows[] = $rowCopy; // Adiciona a cópia da linha
            }

            if (count($rows) > 0) {
                $qr = new QueryResult($rows);
            } else {
                $qr = new QueryResult(array());
            }
        } else {
            $qr = new QueryResult(true);
        }

        $rows = $stmt->affected_rows;
        mysqli_stmt_close($stmt);
        
        $qr->setRows($rows);
        return $qr;
    }

    public static function Transaction($context = null) {
        if (!self::$onTransaction) {
            if (!$context) { $context = self::$context; }
            mysqli_autocommit($context, false);
            self::$onTransaction = true;
        }
    }

    public static function EndTransaction($context = null) {
        if (!$context) { $context = self::$context; }
        mysqli_autocommit($context, true);
        self::$onTransaction = false;
    }

    public static function Apply($context = null) {
        if (!$context) { $context = self::$context; }
        mysqli_commit($context);
        self::EndTransaction($context);
    }

    public static function Revert($context = null) {
        if (!$context) { $context = self::$context; }
        mysqli_rollback($context);
        self::EndTransaction($context);
    }
    public static function GetLastInsertID($context = null)
    {
        if (!$context) { $context = self::$context; }
        return $context->insert_id;
    }

    public static function GetRowsChanged($context = null) {
        if (!$context) { $context = self::$context; }
        return $context->affected_rows;
    }

    public static function GetError($context = null)
    {
        if (!$context) { $context = self::$context; }
        return mysqli_error($context);
    }

    public static function Close() {
        if (self::$context) {
            mysqli_close(self::$context);
            self::$context = null;
        }
    }
}

$dbhost = "";
$dbuser = "";
$dbpass = "";
$db = "";

if ($_SERVER['SERVER_NAME'] == "develop.ecoglobal.com.br") {
    $dbuser = env("DEV_DATABASE_USER");
    $dbpass = env("DEV_DATABASE_PASS");
    $dbhost = "localhost";
    $db = "ecoglobal";
}
else if ($_SERVER['SERVER_NAME'] == "app.ecoglobal.com.br") {
    $dbuser = env("PROD_DATABASE_USER");
    $dbpass = env("PROD_DATABASE_PASS");
    $dbhost = "localhost";
    $db = "ecoglobal";
}
else if ($_SERVER['SERVER_NAME'] == "localhost") {
    $dbuser = env("DEV_DATABASE_USER");
    $dbpass = env("DEV_DATABASE_PASS");
    $dbhost = env("DATABASE_HOST");
    $db = "ecoglobal";
}

$rs = Database::Initialize($dbhost, $dbuser, $dbpass, $db);

register_shutdown_function(function() {
    //Database::Close();
});

?>
