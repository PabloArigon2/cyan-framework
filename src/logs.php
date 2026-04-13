<?php

//getLogs()->setAcao("")->setFile("")->setParameters(json_encode($_POST))->send();

class Logs {

    private static $currentLogId = "";

    private $message;
    private $body;
    private $bodyHeaders;
    private $response;
    private $status;
    private $acao;
    private $filename;
    private $logID;
    private $method;
    private $logInfoData;
    private $error;
    private $errCode;

    public function __construct() {
        $this->message = "";
        $this->response = "";
        $this->status = -1;
        $this->body = "";
        $this->bodyHeaders = "";
        $this->acao = "";
    }

    public function logInfo($arr) {
        $this->logInfoData = $arr;
        return $this;
    }

    public function setMethod($method) {
        $this->method = $method;
        return $this;
    }

    public function setFile($file) {
        $this->filename = $file;
        return $this;
    }

    public function setMessage($message) {
        $this->message = $message;
        return $this;
    }

    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    public function setError($error) {
        $this->error = $error;
        return $this;
    }

    public function setBody($body, $headers) {
        if (gettype($body) == "array" or gettype($body) == "object") {
            $result = json_encode($body);
        }
        else if (gettype($body) != "string") {
            $result = strval($body);
        }
        else {
            $result = $body;
        }

        if (gettype($headers) == "array" or gettype($headers) == "object") {
            $hdrs = json_encode($headers);
        }
        else if (gettype($headers) != "string") {
            $hdrs = strval($headers);
        }
        else {
            $hdrs = $headers;
        }

        $this->body = $result;
        $this->bodyHeaders = $hdrs;
        return $this;
    }

    public function setAcao($acao) {
        $this->acao = $acao;
        return $this;
    }

    public function setSuccess($result = "") {
        if (gettype($result) == "array" or gettext($result) == "object") {
            $result = json_encode($result);
        }
        else if (gettype($result) != "string") {
            $result = strval($result);
        }

        $this->status = 1;
        $this->response = $result;
        return $this;
    } 

    public function setLogContinue($logID) {
        $this->logID = $logID;
        return $this;
    }

    public function setResponse($response) {
        if (gettype($response) == "array" or gettype($response) == "object") {
            $response = json_encode($response);
        }
        else if (gettype($response) != "string") {
            $response = strval($response);
        }

        $this->response = $response;
        return $this;
    }
    
    public function setHttpCode($httpCode) {
        $this->errCode = $httpCode;
        return $this;
    }

    private static $logContext = null;

    private static function GetLogContext() {
        if (self::$logContext === null) {
            self::$logContext = \Database::CreateContext(\Database::$server, \Database::$user, \Database::$pass, \Database::$dbn, \Database::$port);
        }
        return self::$logContext;
    }

    public static function Create() {
        return new self();
    }

    public static function CurrentLogId() {
        return self::$currentLogId;
    }

    public function send() {
        $user = Utils::CurrentUser();
        $logID = $this->logID;
        $ctx = self::GetLogContext();

        if (empty($logID)) {

            $data = \Database::Query("INSERT INTO logs(usuario, file, acao, message, body, body_headers, method, error, http_code) VALUES(?,?,?,?,?,?,?,?,?)", array(
                new \Parameter("i", (!empty($user)) ? $user->ID : 0),
                new \Parameter("s", $this->filename),
                new \Parameter("s", $this->acao),
                new \Parameter("s", $this->message),
                new \Parameter("s", $this->body),
                new \Parameter("s", $this->bodyHeaders),
                $this->method,
                $this->error,
                $this->errCode
            ), $ctx);

            if ($data->validExecute()) {
                self::$currentLogId = \Database::GetLastInsertID($ctx);
                return self::$currentLogId;
            }
            else {
                return false;
            }
        }
        else {
            $data = \Database::Query("UPDATE logs SET status = ?, response = ?, message = ?, error = ?, http_code = ? WHERE id = ?", [
                $this->status,
                $this->response,
                $this->message,
                $this->error,
                $this->errCode,
                $logID
            ], $ctx);

            if ($data->validExecute()) {
                return true;
            }
            else {
                return false;
            }
        }        
    }

    public static function audit($evento, $status, $parent, $usuario = null) {
        $ctx = self::GetLogContext();
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $proxy = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $shared = $_SERVER['HTTP_CLIENT_IP'] ?? '';
        $user_obj = Utils::CurrentUser();
        $user = (empty($usuario)) ? ($user_obj ? $user_obj->ID : 0) : $usuario;

        $sql = \Database::Query("INSERT INTO logs_auditoria(usuario, event, status, remote_address, proxy_address, shared_address, remote_hash, proxy_hash, shared_hash, parent) VALUES(?,?,?,?,?,?,?,?,?,?)", [
            (empty($user)) ? 0 : $user,
            $evento, 
            $status,
            (!empty($remote)) ? \Security::Encrypt($remote) : null,
            (!empty($proxy)) ? \Security::Encrypt($proxy) : null,
            (!empty($shared)) ? \Security::Encrypt($shared) : null,
            (!empty($remote)) ? \Security::Hash($remote) : null,
            (!empty($proxy)) ? \Security::Hash($proxy) : null,
            (!empty($shared)) ? \Security::Hash($shared) : null,
            $parent
        ], $ctx);

        return $sql->validExecute();
    }
}

?>