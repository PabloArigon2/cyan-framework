<?php

final class RequestType {
    public const FILE = "FILE";
    public const FORM = "FORM";
}

class FunctionReturn {
    public bool $Status = false;
    public string $Error = "";
    public string $Message = "";
    public array $Values = [];

    public static function Create($status = false, $error = "", $message = "", $values = []): self {
        $ret = new self();

        $ret->Status = $status;
        $ret->Error = $error;
        $ret->Message = $message;
        $ret->Values = $values;

        return $ret;
    }
}

class Request {
    public string $action = "";
    public string $id = "";
    public string $method = "";
    public string $rawInput = "";
    public ?Payloads $payloads = null;
    public bool $hasPayloads = false;
    public string $payloadID = "";
    public array $body = "";

    public static function Get() : self {
        $action = (!empty($_GET['action'])) ? $_GET['action'] : ((!empty($_GET['acao'])) ? $_GET['acao'] : null);
        $id = (isset($_GET['id']) and !empty($_GET['id'])) ? $_GET['id'] : null;
        $method = $_SERVER['REQUEST_METHOD'];
        $rawInput = file_get_contents('php://input');

        $req = new self();
        $req->action = $action;
        $req->id = $id;
        $req->method = $method;
        $req->rawInput = $rawInput;

        $headers = getallheaders();

        if (isset($headers['X-Requisition-Payload'])) {
            $req->hasPayloads = true;
            $req->payloadID = $headers['X-Requisition-Payload'];
        }

        return $req;
    }
}

final class InputResolver {
    public static function PayloadProcess() {
        $uuid = Security::UUID();
        $finalId = Security::Hash();

        $tempFolder = (TEMP_PATH."/stream.$finalId");

        if (!is_dir($tempFolder)) {
            mkdir($tempFolder, 0777, true);
        }

        $realFolder = realpath(TEMP_PATH."/stream.$finalId");

        if (!$realFolder)
            return FunctionReturn::Create(false, "Falha ao processar pasta temporária do payload!");

        $tempFile = "$realFolder/stream.bin";

        try {
            $stream = new Stream($tempFile);

            if (!$stream->Start()) {
                return FunctionReturn::Create(false, "Falha ao enviar payload!", '');
            }
            
            return FunctionReturn::Create(true, values: [ "id" => "stream.$finalId" ]);
        }
        catch (Throwable $e) {
            return FunctionReturn::Create(false, $e->getMessage());
        }
    }

    public static function BodyProcess(Request $req) {

        $data = [];

        if (!empty($req->rawInput)) {
            if (gettype($req->rawInput) == "string") {
                $data = json_decode($req->rawInput, true);
            }
            else {
                $data = $req->rawInput;
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE && $_SERVER['REQUEST_METHOD'] != "GET") {

            $parse = ActionHelper::ParseFormData($req->rawInput);
            $data = $parse['Data'];

            if (empty($data)) {
                return FunctionReturn::Create(false, "O Body da requisição não é válido!");
            }
        }

        return FunctionReturn::Create(true, values: [ 'Data' => $data ]);
    }
}

final class ActionHelper {

    public static function GetRequestType() {
        $headers = getallheaders();

        $reqType = $headers['X-Upload-Type'] ?? null; //FileUpload or FormUpload
        $reqID = $headers['X-Requisition-Identifier'] ?? null;

        if (empty($reqType) or empty($reqID)) {
            return null;
        }

        return [ "ReqType" => $reqType, "RedId" => $reqID ];
    }

    public static function IsDocumentPreview() {
        $base = basename($_SERVER['SCRIPT_FILENAME']);
        $path = $_SERVER['SCRIPT_FILENAME'];

        if (str_contains(strtolower($path), strtolower('/document'))) {
            return true;
        }

        return false;
    }

    public static function IsBypassed() {
        $base = basename($_SERVER['SCRIPT_FILENAME']);
        $path = $_SERVER['SCRIPT_FILENAME'];

        if ((str_contains(strtolower($path), (strtolower("api/".$base))) or 
            str_contains(strtolower($path), ("/api/"))) and 
            !str_contains(strtolower($path), "/login.php") and 
            !str_contains(strtolower($path), "/cadastro.php") and
            !str_contains(strtolower($path), "/auth.php") and
            !str_contains(strtolower($path), "/mobile/")) {
            return false;
        }

        return true;
    }

    public static function GetHeaders() {
        return getallheaders();
    }

    public static function SetupCors() {
        $allowedOrigins = array_filter(explode(',', Utils::Env('CORS_ORIGINS') ?? '*'));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Upload-Type, X-Requisition-Identifier, X-Requisition-Payload, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Allow-Credentials: true');
    }

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

            $base = realpath(TEMP_PATH);
            $folder = realpath(TEMP_PATH . "/$payloadId");

            if ($folder === false || !str_starts_with($folder, $base))
                continue;

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

?>