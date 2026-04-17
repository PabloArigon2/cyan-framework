<?php

ob_start();

$paths = Config::Get("app", "frontend", "paths") ?? [];
ActionHelper::SetFrontend($paths);

if (ActionHelper::IsFrontend())
    return;

define("TEMP_PATH", sys_get_temp_dir() . "/" . (Utils::Env("APP_SLUG") ?? "cyan"));
ActionHelper::SetupCors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (ActionHelper::IsDocumentPreview())
    return;

$base = basename($_SERVER['SCRIPT_FILENAME']);
$path = $_SERVER['SCRIPT_FILENAME'];
$auth = false;

if (!ActionHelper::IsBypassed()) {
    $auth = Auth::AuthenticateRequest();

    if (!$auth['Auth'])
        ApiResponse::GetCallback()->setStatus(0)->setHttpCode(401)->setError($auth['Environment'] == "user_tenant" ? "Não foi possível autenticar usuário!" : "Token de autorização é inválido!")->setJSON()->run();

    if (!empty($auth['IsDocPreview'])) {
        return;
    }
}
else {
    $ctx = new Context();
    $ctx->Bypassed = true;

    $auth = [ "Auth" => true, "Tenant" => $ctx, "Environment" => "bypassed" ];
}

$requestData = ActionHelper::GetRequestType();

if (empty($requestData)) 
    ApiResponse::GetCallback()->setStatus(0)->setError("Missing requisition identifier headers!")->run();

if (!is_dir(TEMP_PATH)) {
    mkdir(TEMP_PATH, 0777, true);
}

if ($requestData['ReqType'] == RequestType::FILE) {
    $upload = InputResolver::PayloadProcess();

    if ($upload->Status) {
        ApiResponse::Response(1, values: [ "payload_id" => $upload->Values['id'] ]);
        return;
    }

    ApiResponse::Response(0, $upload->Error, 'Ocorreu um erro ao processar payload enviado!');
    return;
}

$request = Request::Get();

$bodyData = InputResolver::BodyProcess($request);

if (!$bodyData->Status) {
    ApiResponse::Response(0, $bodyData->Error, $bodyData->Message, $bodyData->Values, HttpCode::BAD_REQUEST);
    return;
}

$request->body = $bodyData->Values['Data'];

if (!empty($request->body['payloads'])) {
    $request->payloads = ActionHelper::ResolvePayloads($request->body['payloads']);
}
else {
    $request->payloads = new Payloads();
}

if (empty($request->action)) {
    $action = str_replace(".php", "", $base);
}

$dr = new EnvData();
$dr->DIR = Utils::getDirLink();
$dr->LINK = Utils::getPageLink();

$availableArgs = [
    "id" => $request->id,
    "body" => $request->body,
    "data" => $request->body,
    "fields" => $request->body,
    "files" => $request->payloads,
    "payloads" => $request->payloads,
    "query" => $_GET,
    "tenant" => $auth['Tenant'] ?? null,
    "bound" => $auth['Tenant'] ?? null,
    "context" => $auth['Tenant'] ?? null,
    "ctx" => $auth['Tenant'] ?? null,
    "env" => $dr
];

// dispose temp files sended
PostProcessing::register(function() {
    ActionHelper::DisposeTtlPayload();
}, priority: 20);

// send bug report
PostProcessing::register(function() {
    Debug::SendReport();
});

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

                if ($param == "tenant" or $param == "bound" or $param == "ctx" or $param == "context") {
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

            if (!$containsTenant && !ActionHelper::IsBypassed()) {
                ApiResponse::GetCallback()->setStatus(0)->setError("tenant not found for $action -> $method")->setMensagem("Ocorreu um erro ao executar ação!")->run();
            }

            $appSlug = Utils::Env('APP_SLUG') ?? 'cyan';
            $fpath = preg_replace('/^.*' . preg_quote($appSlug, '/') . '[\/\\\\]/', '', $path);

            if (empty($auth['Tenant']) && !ActionHelper::IsBypassed()) {
                ApiResponse::GetCallback()->setStatus(0)->setError("Tenant context missing for action: $action")->setMensagem("Requisição inválida: contexto não identificado.")->run();
            }

            $body = [];
            $headers = empty(getallheaders()) ? null : Security::Encrypt(JSON::Encode(getallheaders()));

            foreach($availableArgs as $key => $arg) {
                if (empty($key) or empty($arg) or in_array($key, [ 'tenant', 'bound', 'ctx', 'context', 'files', 'payloads', 'env' ])) continue;

                if (in_array($key, [ 'query' ]) and isset($body['query'])) continue;
                if (in_array($key, [ 'body', 'data', 'fields' ]) and (isset($body['body']) or isset($body['data']) or isset($body['fields']))) continue;
                if ($key === 'id' and isset($body['id'])) continue;

                $body[$key] = $arg;
            }
            
            $logID = Logs::Create()->setAcao($action)->setFile($fpath)->setMethod($method)->setBody(Security::Encrypt(JSON::Encode($body)), $headers)->setStatus(1)->send();
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
    if (ActionHelper::ShouldRunActions()) {
        Action::Run();

        foreach(PayloadRegistry::all() as $payload) {
            ActionHelper::DisposePayload($payload);
        }
    }
});

?>