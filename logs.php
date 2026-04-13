<?php

require_once "database.php";
require_once "utils.php";

$current_log_id = "";

function getLogs() {
    return new Logs();
}

function currentLog() {
    global $current_log_id;
    return $current_log_id;
}

function logs() {
    return getLogs();
}

//getLogs()->setAcao("")->setFile("")->setParameters(json_encode($_POST))->send();

class Logs {

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

    public function send() {
        global $current_log_id;
        $user = currentUser();

        $logID = $this->logID;

        if (empty($logID)) {

            $data = Database::Query("INSERT INTO logs(usuario, file, acao, message, body, body_headers, method, error, http_code) VALUES(?,?,?,?,?,?,?,?,?)", array(
                new Parameter("i", (!empty($user)) ? $user->ID : 0),
                new Parameter("s", $this->filename),
                new Parameter("s", $this->acao),
                new Parameter("s", $this->message),
                new Parameter("s", $this->body),
                new Parameter("s", $this->bodyHeaders),
                $this->method,
                $this->error,
                $this->errCode
            ));

            if ($data->validExecute()) {
                $current_log_id = Database::GetLastInsertID();
                return $current_log_id;
            }
            else {
                echo $data->error();
                return false;
            }
        }
        else {
            $data = Database::Query("UPDATE logs SET status = ?, response = ?, message = ?, error = ?, http_code = ? WHERE id = ?", [
                $this->status,
                $this->response,
                $this->message,
                $this->error,
                $this->errCode,
                $logID
            ]);

            if ($data->validExecute()) {
                return true;
            }
            else {
                return false;
            }
        }        
    }

    public static function audit($evento, $status, $parent, $usuario = null) {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $proxy = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $shared = $_SERVER['HTTP_CLIENT_IP'] ?? '';
        $user = (empty($usuario)) ? currentUser()->ID : $usuario;

        $sql = Database::Query("INSERT INTO logs_auditoria(usuario, event, status, remote_address, proxy_address, shared_address, parent) VALUES(?,?,?,?,?,?,?)", [
            (empty($user)) ? 0 : $user,
            $evento, 
            $status,
            (!empty($remote)) ? Encrypt($remote) : null,
            (!empty($proxy)) ? Encrypt($proxy) : null,
            (!empty($shared)) ? Encrypt($shared) : null,
            $parent
        ]);

        return $sql->validExecute();
    }
}

?>