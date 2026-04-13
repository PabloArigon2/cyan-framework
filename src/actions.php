<?php

use Security as S;

final class UpType {
    public const FILE = "FILE";
    public const FORM = "FORM";
}

define("TEMP_PATH", sys_get_temp_dir() . "/" . (Utils::Env("APP_SLUG") ?? "cyan"));

#region CORS Middleware
$allowedOrigins = array_filter(explode(',', Utils::Env('CORS_ORIGINS') ?? '*'));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Upload-Type, X-Requisition-Identifier, X-Requisition-Payload, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
#endregion
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
    $auth = Utils::AuthenticateRequest();
    $auth['IsLogin'] = false;
}
else {
    $auth = [ 'Auth' => true, "IsLogin" => true ];
}

if (str_contains(strtolower($path), strtolower('/document'))) {
    $auth = [ 'Auth' => true, "IsDocPreview" => true ];
}

if (!$auth['Auth']) {
    ApiResponse::GetCallback()->setStatus(0)->setHttpCode(401)->setError("Token de autorização é inválido!")->setJSON()->run();
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
    ApiResponse::GetCallback()->setStatus(0)->setError("Missing requisition identifier headers: $reqType $reqID")->run();
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
            ApiResponse::GetCallback()->setStatus(0)->setError("upload stream failed")->run();
        }
        
        ApiResponse::GetCallback()->setStatus(1)->setValues([ 'payload_id' => "stream.$finalId" ])->run();
    }
    catch (Throwable $e) {
        ApiResponse::GetCallback()->setStatus(0)->setError($e->getMessage())->run();
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

$action = (!empty($_GET['action'])) ? $_GET['action'] : ((!empty($_GET['acao'])) ? $_GET['acao'] : null);
$id = (isset($_GET['id']) and !empty($_GET['id'])) ? $_GET['id'] : null;
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$data = [];
$files = [];
#endregion

#region body parse
if (!empty($rawInput)) {
    if (gettype($rawInput) == "string") {
        $data = json_decode($rawInput, true);
    }
    else {
        $data = $rawInput;
    }
}

final class ActionHelper {

    public static function ParseFormData($raw) {
        if (empty($_SERVER["CONTENT_TYPE"]) ||
            strpos($_SERVER["CONTENT_TYPE"], "multipart/form-data") === false) {
            return ["Data" => [], "Files" => []];
        }

        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        if (!isset($matches[1])) return [[], []];
        $boundary = $matches[1];

        $blocks = preg_split("/-+$boundary/", $raw);
        array_pop($blocks);

        $fields = [];
        $files  = [];

        foreach ($blocks as $block) {
            if (!trim($block)) continue;

            if (!preg_match('/name="([^"]+)"/', $block, $nameMatch)) continue;
            $fullName = $nameMatch[1];

            $isFile = preg_match('/filename="([^"]*)"/', $block, $fileMatch);
            $filename = $isFile ? $fileMatch[1] : null;

            if (!preg_match("/\r\n\r\n(.*)$/s", $block, $bodyMatch)) continue;

            $value = $bodyMatch[1];

            $value = rtrim($value, "\r\n");

            $value = preg_replace('/-{6,}WebKitFormBoundary[^\r\n]*/', '', $value);

            $keys = preg_split('/(\[|\])/i', $fullName);
            $keys = array_filter($keys, fn($k) => $k !== "");

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

            $ref = &$fields;
            foreach ($keys as $k) {
                if (!isset($ref[$k])) $ref[$k] = [];
                $ref = &$ref[$k];
            }

            $ref = $value;
        }

        return ["Data" => $fields, "Files" => $files];
    }

    public static function ParseFiles()
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

        $raw = fopen('php://input', 'rb');
        $body = stream_get_contents($raw);
        fclose($raw);

        if ($body === false || $body === '') {
            return [];
        }

        $files = [];

        $blocks = preg_split(
            "/-+$boundary/",
            $body
        );

        foreach ($blocks as $block) {
            if (
                strpos($block, 'filename="') === false ||
                !preg_match('/name="([^"]+)"/', $block, $nameMatch) ||
                !preg_match('/filename="([^"]*)"/', $block, $fileMatch)
            ) {
                continue;
            }

            $fieldName = $nameMatch[1];
            $fileName  = $fileMatch[1];

            $pos = strpos($block, "\r\n\r\n");
            if ($pos === false) continue;

            $fileData = substr($block, $pos + 4);

            $fileData = preg_replace("/\r\n--$/", '', $fileData);
            $fileData = preg_replace("/\r\n$/", '', $fileData);

            if ($fileName === '') continue;

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

    public static function ResolvePayloads(array $payloadIds): Payloads
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

    public static function DisposePayload(string $payload) {
        $dir = TEMP_PATH . "/$payload";
        if (!is_dir($dir)) return;

        foreach (glob("$dir/*") as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }

    public static function DisposeTtlPayload(int $ttlSeconds = 3600): void
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
}

if (json_last_error() !== JSON_ERROR_NONE && $_SERVER['REQUEST_METHOD'] != "GET") {

    $parse = ActionHelper::ParseFormData($rawInput);
    $data = $parse['Data'];

    if (empty($data)) {
        ApiResponse::GetCallback()->setStatus(0)->setError("Invalid JSON passed!")->setValues([ "JSONError" => json_last_error_msg(), "JSON_ERR" => json_last_error(), "Echo" => $rawInput ])->setHttpCode(HttpCode::BAD_REQUEST)->run();
    }
}

#endregion

#region payloads process
if (!empty($data['payloads'])) {
    $files = ActionHelper::ResolvePayloads($data['payloads']);
}
else {
    $files = new Payloads();
}
#endregion

if (empty($action)) {
    $action = str_replace(".php", "", $base);
}

$dr = new EnvData();
$dr->DIR = Utils::getDirLink();
$dr->LINK = Utils::getPageLink();

$availableArgs = [
    "id" => $id,
    "body" => $data,
    "data" => $data,
    "fields" => $data,
    "files" => $files,
    "payloads" => $files,
    "query" => $_GET,
    "tenant" => $auth['Tenant'] ?? null,
    "bound" => $auth['Tenant'] ?? null,
    "context" => $auth['Tenant'] ?? null,
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
        global $action, $auth, $method, $availableArgs, $path, $base;

        if (!empty(self::$actions[$method][$action])) {
            $ref = new ReflectionFunction(self::$actions[$method][$action]);

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
                ApiResponse::GetCallback()->setStatus(0)->setError("tenant not found for $action -> $method")->setMensagem("Ocorreu um erro ao executar ação!")->run();
            }

            $appSlug = Utils::Env('APP_SLUG') ?? 'cyan';
            $fpath = preg_replace('/^.*' . preg_quote($appSlug, '/') . '[\/\\\\]/', '', $path);

            if (!$auth['Auth'] or ($auth['Auth'] == true and !$auth['IsLogin'] and empty($auth['Tenant']))) {
                if (!str_contains(strtolower($path), "/cyan/")) {
                    ApiResponse::GetCallback()->setStatus(0)->setError("tenant not provided")->run();
                }
            }

            $body = [];
            $headers = empty(getallheaders()) ? null : S::Encrypt(JSON::Encode(getallheaders()));

            foreach($availableArgs as $key => $arg) {
                if (empty($key) or empty($arg) or in_array($key, [ 'tenant', 'bound', 'files', 'payloads', 'env' ])) continue;

                if (in_array($key, [ 'query' ]) and isset($body['query'])) continue;
                if (in_array($key, [ 'body', 'data', 'fields' ]) and (isset($body['body']) or isset($body['data']) or isset($body['fields']))) continue;
                if ($key === 'id' and isset($body['id'])) continue;

                $body[$key] = $arg;
            }
            
            $logID = Logs::Create()->setAcao($action)->setFile($fpath)->setMethod($method)->setBody(S::Encrypt(JSON::Encode($body)), $headers)->setStatus(1)->send();
            self::$logs[] = $logID;

            $ref->invokeArgs($args);
        }
        else {
            ApiResponse::GetCallback()->setStatus(0)->setError("action $action ($method) not found.")->setMensagem("Ocorreu um erro ao executar ação!")->run();
        }
    }
}

register_shutdown_function(function() {
    global $path, $base;
    if ((str_contains(strtolower($path), (strtolower("api/".$base))) or str_contains(strtolower($path), ("/api/")) or str_contains(strtolower($path), ("/mobile/")))) {
        Action::Run();

        foreach(PayloadRegistry::all() as $payload) {
            ActionHelper::DisposePayload($payload);
        }
    }
        
});

?>