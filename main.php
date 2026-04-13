<?php

require_once "utils.php";
require_once "builder.php";
require_once "actions.php";
require_once "database.php";
require_once "cryptography.php";
require_once "math.php";
require_once "logs.php";
require_once "permissions.php";
require_once "debug.php";
require_once "gov.php";
require_once "pagamentos.php";
require_once "security.php";

use Security as S;

Debug::Start();

date_default_timezone_set('America/Bahia');
$asaasToken = env("ASAAS_TOKEN_AUTH");

$dir = Utils::getDirLink();

function isEvent($v1, $event) {
    if ($v1 == $event) { return true; }
    return false;
}

class JSON {
    public static function Encode($val) {
        return json_encode($val);
    }

    public static function Decode($val) {
        return json_decode($val, true, 512, JSON_UNESCAPED_UNICODE);
    }
}

function GenerateSessionToken() {
    $token = Cryptography::UUID();
    $sql = Database::Query("SELECT id FROM usuarios WHERE sessionToken = '".$token."'");

    while ($sql->isValid() and $sql->length() > 0) {
        $token = Cryptography::UUID();
        $sql = Database::Query("SELECT id FROM usuarios WHERE sessionToken = '".$token."'");
    }

    return $token;
}

class UserType {
    public const ADMIN = 0;
    public const CLIENTE = 1;
    public const UNIDADE = 2;
    public const PROFISSIONAL = 3;
    public const PACIENTE = 4;
}

final class HttpCode {

    // 2xx - Códigos de Sucesso
    public const OK                  = 200; // Requisição bem-sucedida.
    public const CREATED             = 201; // Recurso criado com sucesso.
    public const ACCEPTED            = 202; // Requisição aceita para processamento.
    public const NO_CONTENT          = 204; // Requisição bem-sucedida, sem conteúdo para retornar.

    // 3xx - Códigos de Redirecionamento
    public const MOVED_PERMANENTLY   = 301; // O recurso foi movido permanentemente.
    public const FOUND               = 302; // O recurso foi encontrado em outro local (temporariamente).
    public const NOT_MODIFIED        = 304; // O recurso não foi modificado.

    // 4xx - Erros do Cliente
    public const BAD_REQUEST             = 400; // A sintaxe da requisição está incorreta.
    public const UNAUTHORIZED            = 401; // A requisição requer autenticação.
    public const FORBIDDEN               = 403; // O servidor se recusou a autorizar a requisição.
    public const NOT_FOUND               = 404; // O recurso solicitado não foi encontrado.
    public const METHOD_NOT_ALLOWED      = 405; // O método HTTP da requisição não é suportado para o recurso.
    public const CONFLICT                = 409; // A requisição não pôde ser completada devido a um conflito.
    public const UNPROCESSABLE_ENTITY    = 422; // A requisição contém erros semânticos.
    
    // 5xx - Erros do Servidor
    public const INTERNAL_SERVER_ERROR   = 500; // O servidor encontrou uma condição inesperada.
    public const NOT_IMPLEMENTED         = 501; // O servidor não suporta a funcionalidade.
    public const BAD_GATEWAY             = 502; // O servidor, atuando como gateway, recebeu uma resposta inválida.
    public const SERVICE_UNAVAILABLE     = 503; // O servidor não está pronto para lidar com a requisição.
}

$ufs = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins',
];
?>