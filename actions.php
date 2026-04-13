<?php

require_once "utils.php";
require_once "logs.php";
require_once "stream.php";
require_once "cryptography.php";
require_once "builder.php";

use Security as S;

final class UpType {
    public const FILE = "FILE";
    public const FORM = "FORM";
}

define("TEMP_PATH", sys_get_temp_dir()."/ecoglobal");
$base = basename($_SERVER['SCRIPT_FILENAME']);
$path = $_SERVER['SCRIPT_FILENAME'];
$auth = false;
$headers = getallheaders();

#region request autentication
if ((str_contains(strtolower($path), (strtolower("api/".$base))) or 
     str_contains(strtolower($path), ("/api/"))) and 
     !str_contains(strtolower($path), "/login.php") and 
     !str_contains(strtolower($path), "/cadastro.php") and
     !str_contains(strtolower($path), "/auth.php") and
     !str_contains(strtolower($path), "/mobile/")) {
    $auth = AutenticateRequisition();
    $auth['IsLogin'] = false;
}
else {
    $auth = [ 'Auth' => true, "IsLogin" => true ];
}

if (str_contains(strtolower($path), strtolower('/document'))) {
    $auth = [ 'Auth' => true, "IsDocPreview" => true ];
}

if (!$auth['Auth']) {
    getCallback()->setStatus(0)->setHttpCode(401)->setError("Token de autorização é inválido!")->setJSON()->run();
}

if (!empty($auth['IsDocPreview'])) {
    return;
}

#endregion

#region headers validation
$headers = getallheaders();

$reqType = $headers['X-Upload-Type'] ?? null; //FileUpload or FormUpload
$reqID = $headers['X-Requisition-Identifier'] ?? null;

if ((empty($reqType) or empty($reqID)) && empty($auth['IsLogin'])) {
    callback()->setStatus(0)->setError("Missing requisition identifier headers: $reqType $reqID")->run();
}
#endregion

#region file stream process
if (!is_dir(TEMP_PATH)) {
    mkdir(TEMP_PATH, 0777, true);
}

if ($reqType == UpType::FILE) {
    $uuid = S::UUID();
    $finalId = S::Hash();

    $tempFolder = TEMP_PATH."/stream.$finalId";

    if (!is_dir($tempFolder)) {
        mkdir($tempFolder, 0777, true);
    }

    $tempFile = "$tempFolder/stream.bin";

    try {
        $stream = new Stream($tempFile);

        if (!$stream->Start()) {
            callback()->setStatus(0)->setError("upload stream failed")->run();
        }
        
        callback()->setStatus(1)->setValues([ 'payload_id' => "stream.$finalId" ])->run();
    }
    catch (Throwable $e) {
        callback()->setStatus(0)->setError($e->getMessage())->run();
    }
}
#endregion

#region variables define
$usePayload = false;
$payload = null;

if (isset($headers['X-Requisition-Payload'])) {
    $usePayload = true;
    $payload = $headers['X-Requisition-Payload'];
}

$acao = (!empty($_GET['acao'])) ? $_GET['acao'] : null;
$id = (isset($_GET['id']) and !empty($_GET['id'])) ? $_GET['id'] : null;
$method = $_SERVER['REQUEST_METHOD'];
$dados_json = file_get_contents('php://input');
$data = [];
$files = [];
#endregion

#region body parse
if (!empty($dados_json)) {
    if (gettype($dados_json) == "string") {
        $data = json_decode($dados_json, true);
    }
    else {
        $data = $dados_json;
    }
}

function parse_formdata($raw) {
    if (empty($_SERVER["CONTENT_TYPE"]) ||
        strpos($_SERVER["CONTENT_TYPE"], "multipart/form-data") === false) {
        return ["Data" => [], "Files" => []];
    }

    // Extrai o boundary
    preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
    if (!isset($matches[1])) return [[], []];
    $boundary = $matches[1];

    // Separa os blocos pelo boundary principal
    $blocks = preg_split("/-+$boundary/", $raw);
    array_pop($blocks); // Remove o "--" final

    $fields = [];
    $files  = [];

    foreach ($blocks as $block) {
        if (!trim($block)) continue;

        // Pega o cabeçalho
        if (!preg_match('/name="([^"]+)"/', $block, $nameMatch)) continue;
        $fullName = $nameMatch[1];

        // Extrai filename, se existir
        $isFile = preg_match('/filename="([^"]*)"/', $block, $fileMatch);
        $filename = $isFile ? $fileMatch[1] : null;

        // Extrai corpo do campo
        if (!preg_match("/\r\n\r\n(.*)$/s", $block, $bodyMatch)) continue;

        $value = $bodyMatch[1];

        // Remove CRLF no fim
        $value = rtrim($value, "\r\n");

        // Remove boundaries que vazaram dentro do valor
        $value = preg_replace('/-{6,}WebKitFormBoundary[^\r\n]*/', '', $value);

        // Expande nome do campo para arrays/aninhados
        $keys = preg_split('/(\[|\])/i', $fullName);
        $keys = array_filter($keys, fn($k) => $k !== "");

        // Salva arquivo
        if ($isFile) {
            $ref = &$files;

            foreach ($keys as $k) {
                if (!isset($ref[$k])) $ref[$k] = [];
                $ref = &$ref[$k];
            }

            $ref = [
                'filename' => $filename,
                'size'     => strlen($value),
                'content'  => $value
            ];

            continue;
        }

        // Salva campo comum
        $ref = &$fields;
        foreach ($keys as $k) {
            if (!isset($ref[$k])) $ref[$k] = [];
            $ref = &$ref[$k];
        }

        $ref = $value;
    }

    return ["Data" => $fields, "Files" => $files];
}

function parse_files()
{
    if (
        empty($_SERVER['CONTENT_TYPE']) ||
        strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === false
    ) {
        return [];
    }

    if (!preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $m)) {
        return [];
    }

    $boundary = $m[1];

    // Lê o body em modo binário
    $raw = fopen('php://input', 'rb');
    $body = stream_get_contents($raw);
    fclose($raw);

    if ($body === false || $body === '') {
        return [];
    }

    $files = [];

    // Split binário-safe
    $blocks = preg_split(
        "/-+$boundary/",
        $body
    );

    foreach ($blocks as $block) {
        // precisa conter filename
        if (
            strpos($block, 'filename="') === false ||
            !preg_match('/name="([^"]+)"/', $block, $nameMatch) ||
            !preg_match('/filename="([^"]*)"/', $block, $fileMatch)
        ) {
            continue;
        }

        $fieldName = $nameMatch[1];
        $fileName  = $fileMatch[1];

        // separa headers do corpo
        $pos = strpos($block, "\r\n\r\n");
        if ($pos === false) continue;

        $fileData = substr($block, $pos + 4);

        // remove CRLF final e boundary residual
        $fileData = preg_replace("/\r\n--$/", '', $fileData);
        $fileData = preg_replace("/\r\n$/", '', $fileData);

        if ($fileName === '') continue;

        // cria arquivo temporário real
        $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmpPath, $fileData);

        $files[$fieldName] = [
            'name'     => $fileName,
            'type'     => mime_content_type($tmpPath),
            'tmp_name' => $tmpPath,
            'error'    => 0,
            'size'     => filesize($tmpPath)
        ];
    }

    return $files;
}

if (json_last_error() !== JSON_ERROR_NONE && $_SERVER['REQUEST_METHOD'] != "GET") {

    $parse = parse_formdata($dados_json);
    $data = $parse['Data'];

    if (empty($data)) {
        callback()->setStatus(0)->setError("Invalid JSON passed!")->setValues([ "JSONError" => json_last_error_msg(), "JSON_ERR" => json_last_error(), "Echo" => $dados_json ])->setHttpCode(HttpCode::BAD_REQUEST)->run();
    }
}

#endregion

#region payloads process
function resolvePayloads(array $payloadIds): Payloads
{
    $payloads = new Payloads();

    foreach ($payloadIds as $payloadId) {
        $payloadId = basename($payloadId);
        PayloadRegistry::add($payloadId);

        $folder = TEMP_PATH . "/$payloadId";
        if (!is_dir($folder)) continue;

        $streams = glob("$folder/stream.*");
        if (empty($streams)) continue;

        $tmp = $streams[0];

        $payload = new Payload();
        $payload->Nome     = basename($tmp);
        $payload->MimeType = mime_content_type($tmp) ?: 'application/octet-stream';
        $payload->TempPath = $tmp;
        $payload->Error    = 0;
        $payload->Size     = filesize($tmp);

        $payloads->Add($payload);
    }

    return $payloads;
}

function disposePayload(string $payload) {
    $dir = TEMP_PATH . "/$payload";
    if (!is_dir($dir)) return;

    foreach (glob("$dir/*") as $file) {
        @unlink($file);
    }

    @rmdir($dir);
}

function disposeTtlPayload(int $ttlSeconds = 3600): void
{
    foreach (glob(TEMP_PATH . "/stream.*") as $dir) {
        if (!is_dir($dir)) continue;

        if (time() - filemtime($dir) > $ttlSeconds) {
            foreach (glob("$dir/*") as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
}

if (!empty($data['payloads'])) {
    $files = resolvePayloads($data['payloads']);
}
else {
    $files = new Payloads();
}
#endregion

if (empty($acao)) {
    $acao = str_replace(".php", "", $base);
}

$dr = new EnvData();
$dr->DIR = Utils::getDirLink();
$dr->LINK = Utils::getPageLink();

$availableArgs = [
    "id" => $id,
    "body" => $data,
    "data" => $data,
    "fields" => $data,
    "dados" => $data,
    "cliente" => $id,
    "paciente" => $id,
    "medico" => $id,
    "grupo" => $id,
    "permission" => $id,
    "perm" => $id,
    "files" => $files,
    "payloads" => $files,
    "query" => $_GET,
    "qry" => $_GET,
    "str" => $_GET,
    "tenant" => $auth['Tenant'] ?? null,
    "bound" => $auth['Tenant'] ?? null,
    "env" => $dr
];

class Action {
    public static $actions = [];
    public static $logs = [];
    public static $runned = false;
    public static $tenant = null;

    public static function Create($name, $method, callable $func) {
        if (!empty(self::$actions[$method][$name])) { throw new Exception("Action already registered!"); }
        
        if (empty(self::$actions[$method])) {
            self::$actions[$method] = [
                $name => $func
            ];
        }
        else {
            self::$actions[$method][$name] = $func;
        }
    }

    public static function Call($name, $method) {
        global $availableArgs;
        if (empty(self::$actions[$method][$name])) throw new Exception("Action not registered!");
        $ref = new ReflectionFunction(self::$actions[$method][$name]);

        $args = [];

        foreach($ref->getParameters() as $parameter) {
            $param = $parameter->getName();

            if (isset($availableArgs[$param])) {
                $args[] = $availableArgs[$param];
            }
            else {
                throw new Exception("Parameter ".$param." is invalid for these action!");
            }
        }

        $ref->invokeArgs($args);
    }

    public static function Run() {
        global $acao, $auth, $method, $availableArgs, $path, $base;

        if (!empty(self::$actions[$method][$acao])) {
            $ref = new ReflectionFunction(self::$actions[$method][$acao]);

            $args = [];
            $arguments = [];

            $containsTenant = false;

            foreach($ref->getParameters() as $parameter) {
                $param = $parameter->getName();

                if ($param == "tenant" or $param == "bound") {
                    $containsTenant = true;
                }

                if (isset($availableArgs[$param])) {
                    $args[] = $availableArgs[$param];

                    if ($param != "files") {
                        $arg = $availableArgs[$param];

                        if (gettype($arg) == "string") {
                            $arguments[$param] = $arg;
                        }
                        else {
                            foreach($arg as $key => $val) {
                                $arguments[$key] = $val;
                            }
                        }
                    }
                }
                else {
                    $args[] = null;
                }
            }

            if (!$containsTenant && $base !== "login.php" && $base !== "auth.php" and !str_contains($path, "/mobile/")) {
                callback()->setStatus(0)->setError("tenant not found for $acao -> $method")->setMensagem("Ocorreu um erro ao executar ação!")->run();
            }

            $fpath = preg_replace('/^.*ecoglobal[\/\\\\]/', '', $path);

            if (!$auth['Auth'] or ($auth['Auth'] == true and !$auth['IsLogin'] and empty($auth['Tenant']))) {
                if (!str_contains(strtolower($path), "/cyan/")) {
                    callback()->setStatus(0)->setError("tenant not provided")->run();
                }
            }

            $body = [];
            $headers = empty(getallheaders()) ? null : S::Encrypt(JSON::Encode(getallheaders()));

            foreach($availableArgs as $key => $arg) {
                if (empty($key) or empty($arg) or in_array($key, [ 'tenant', 'bound', 'files', 'payloads', 'env' ])) continue;

                if (in_array($key, [ 'query', 'qry', 'str' ]) and (isset($body['query']) or isset($body['qry']) or isset($body['str']))) continue;
                if (in_array($key, [ 'body', 'data', 'fields', 'dados' ]) and (isset($body['body']) or isset($body['data']) or isset($body['fields']) or isset($body['dados']))) continue;
                if (in_array($key, [ 'id', 'cliente', 'paciente', 'medico', 'grupo', 'permission', 'perm' ]) and (isset($body['id']) or isset($body['cliente']) or isset($body['paciente']) or isset($body['medico']) or isset($body['grupo']) or isset($body['permission']) or isset($body['perm']))) continue;

                $body[$key] = $arg;
            }
            
            $logID = logs()->setAcao($acao)->setFile($fpath)->setMethod($method)->setBody(S::Encrypt(JSON::Encode($body)), $headers)->setStatus(1)->send();
            self::$logs[] = $logID;

            $ref->invokeArgs($args);
        }
        else {
            callback()->setStatus(0)->setError("action $acao ($method) não encontrada.")->setMensagem("Ocorreu um erro ao executar ação!")->run();
        }
    }
}

register_shutdown_function(function() {
    global $path, $base;
    if ((str_contains(strtolower($path), (strtolower("api/".$base))) or str_contains(strtolower($path), ("/api/")) or str_contains(strtolower($path), ("/mobile/")))) {
        Action::Run();

        foreach(PayloadRegistry::all() as $payload) {
            disposePayload($payload);
        }
    }
        
})

?>