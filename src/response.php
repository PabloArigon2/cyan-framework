<?php

class Callback {
    public $mensagem = "";
    public $erro = "";
    public $values = null;
    public $status = 0;
    public $responseType = "";
    public $errorCode = null;
    private $code = 200;
    public $httpCode = -1;

    public function run() {
        if ($this->responseType == "") { $this->responseType = "application/json"; }

        if ($this->erro != "" or $this->status != 1) {
            $this->code = 400;
        }
        else if ($this->erro == "" and $this->status == 1) {
            $this->code = 200;
        }

        if ($this->httpCode != -1) { $this->code = $this->httpCode; }

        $result = array();
        $result['Status'] = $this->status;

        if (!empty($this->erro)) {
            $base = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
            $date = date("d/m/Y H:i:s");
            error_log("[ $date ][ ERROR ] {$this->erro} ($base -> {$this->code})");
        }

        if ($this->errorCode != null) { $result['ErrCode'] = $this->errorCode; }
        if ($this->mensagem != "") { $result['Mensagem'] = $this->mensagem; }
        if ($this->erro != "") { $result['Erro'] = $this->erro; }
        
        if ($this->values != null and (gettype($this->values) == "array" or gettype($this->values) == "object")) {
            foreach($this->values as $key => $value) {
                $result[$key] = $value;
            }
        } else if ($this->values != null) {
            $result['Value'] = $this->values;
        }

        $response = [];
        foreach($result as $key => $val) {
            if (in_array($key, [ 'Mensagem', 'ErrCode', 'Erro', 'Status' ])) continue;
            $response[$key] = $val;
        }

        if (!empty(\Action::$logs)) {
            \Logs::Create()->setLogContinue(\Action::$logs[count(\Action::$logs) - 1])->setStatus($this->status)->setError((!empty($result['Erro'])) ? \Security::Encrypt($result['Erro']) : '')->setMessage($result['Mensagem'] ?? '')->setResponse((!empty($response)) ? \Security::Encrypt(\JSON::Encode($response)) : '')->setHttpCode($this->code)->send();
        }

        \Utils::Response(array("Content-Type" => $this->responseType), $result, $this->code);
        $result['httpCode'] = $this->code;
        return $result;
    }

    public function setHttpCode($code) { $this->httpCode = $code; return $this; }
    public function setStatus($status) { $this->status = $status; return $this; }
    public function setValues($values) { $this->values = $values; return $this; }
    public function setError($error) { $this->erro = $error; return $this; }
    public function setErrorCode($errCode) { $this->errorCode = $errCode; return $this; }
    public function setMensagem($msg) { $this->mensagem = $msg; return $this; }
    public function setJSON() { $this->responseType = "application/json"; return $this; }
    public function setText() { $this->responseType = "text/html"; return $this; }
}

final class ApiResponse {
    public static function GetCallback() {
        return new Callback();
    }

    public static function Send(int $status = 0, string $error = "", string $mensagem = "", array $values = [], int $httpCode = 0, bool $json = true) {
        $callback = self::GetCallback();
        $callback->setStatus($status);
        
        if (!empty($error)) $callback->setError($error);
        if (!empty($mensagem)) $callback->setMensagem($mensagem);
        if (!empty($values)) $callback->setValues($values);
        if (!empty($httpCode)) $callback->setHttpCode($httpCode);
        if (!$json) $callback->setText();

        $callback->run();
    }
}
?>
