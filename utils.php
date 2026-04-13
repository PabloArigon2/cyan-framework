<?php

function env($key) {
    return isset($_ENV[$key]) ? $_ENV[$key] : null;
}

define("SELF", $_SERVER['PHP_SELF']);

require_once ROOT."/utils/database.php";
require_once ROOT."/utils/math.php";
require_once ROOT."/utils/builder.php";
require_once ROOT."/utils/cryptography.php";
require_once ROOT."/vendor/autoload.php";

use Security as S;

final class Refund {
    public const DONE = "DONE";
    public const PENDING = "PENDING";
    public const CANCELLED = "CANCELLED";
}

final class PayMethods {
    public const ALL = "UNDEFINED";
    public const BOLETO = "BOLETO";
    public const CREDITO = "CREDIT_CARD";
    public const PIX = "PIX";
}

final class ReqMethod {
    public const GET = "GET";
    public const POST = "POST";
    public const DELETE = "DELETE";
    public const PUT = "PUT";
    public const PATCH = "PATCH";
}

final class MultaType {
    public const FIXED = "FIXED";
    public const PERCENTAGE = "PERCENTAGE";
}

final class PayStatus {
    public const PENDING = "PENDING";
    public const RECEIVED = "RECEIVED";
    public const CONFIRMED = "CONFIRMED";
    public const OVERDUE = "OVERDUE";
    public const REFUNDED = "REFUNDED";
    public const RECEIVED_CASH = "RECEIVED_IN_CASH";
    public const REFUND_REQUESTED = "REFUND_REQUESTED";
    public const REFUND_IN_PROGRESS = "REFUND_IN_PROGRESS";
    public const CHARGEBACK_REQUESTED = "CHARGEBACK_REQUESTED";
    public const CHARGEBACK_DISPUTE = "CHARGEBACK_DISPUTE";
    public const AWAITING_CHARGEBACK_REVERSAL = "AWAITING_CHARGEBACK_REVERSAL";
    public const DUNNING_REQUESTED = "DUNNING_REQUESTED";
    public const DUNNING_RECEIVED = "DUNNING_RECEIVED";
    public const AWAITING_RISK_ANALYSIS = "AWAITING_RISK_ANALYSIS";
    public const DELETED = "DELETED";
}

class Callback {
    public $mensagem = "";
    public $erro = "";
    public $values = null;
    public $status = 0;
    public $responseType = "";
    public $errorCode = null;
    private $code = 200;
    public $httpCode = -1;

    function __construct() {

    }

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
            $base = basename($_SERVER['SCRIPT_FILENAME']);
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
        }
        else if ($this->values != null and (gettype($this->values) == "boolean" or gettype($this->values) == "integer" or gettype($this->values) == "double" or
        gettype($this->values) == "string")) {
            $result['Value'] = $this->values;
        }

        $response = [];

        foreach($result as $key => $val) {
            if (in_array($key, [ 'Mensagem', 'ErrCode', 'Erro', 'Status' ])) continue;
            $response[$key] = $val;
        }

        if (!empty(Action::$logs)) {
            logs()->setLogContinue(Action::$logs[count(Action::$logs) - 1])->setStatus($this->status)->setError((!empty($result['Erro'])) ? S::Encrypt($result['Erro']) : '')->setMessage($result['Mensagem'] ?? '')->setResponse((!empty($response)) ? S::Encrypt(JSON::Encode($response)) : '')->setHttpCode($this->code)->send();
        }

        Utils::Response(array("Content-Type" => $this->responseType), $result, $this->code);
        $result['httpCode'] = $this->code;
        return $result;
    }

    public function setHttpCode($code) {
        $this->httpCode = $code;
        return $this;
    }

    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    public function setValues($values) {
        $this->values = $values;
        return $this;
    }

    public function setError($error) {
        $this->erro = $error;
        return $this;
    }

    public function setErrorCode($errCode) {
        $this->errorCode = $errCode;
        return $this;
    }

    public function setMensagem($msg) {
        $this->mensagem = $msg;
        return $this;
    }

    public function setJSON() {
        $this->responseType = "application/json";
        return $this;
    }

    public function setVariable(&$var, $value) {
        $var = $value;
        return $this;
    }

    public function setText() {
        $this->responseType = "text/html";
        return $this;
    }
}

function getCallback() {
    return new Callback();
}

function callback() { return getCallback(); }

function response(int $status = 0, string $error = "", string $mensagem = "", array $values = [], int $httpCode = 0, bool $json = true) {
    $callback = callback();
    $callback->setStatus($status);
    
    if (!empty($error)) {
        $callback->setError($error);
    }

    if (!empty($mensagem)) {
        $callback->setMensagem($mensagem);
    }

    if (!empty($values)) {
        $callback->setValues($values);
    }

    if (!empty($httpCode)) {
        $callback->setHttpCode($httpCode);
    }

    if (!$json) {
        $callback->setText();
    }

    $callback->run();
}

function removerAcentos($string) {
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N'
    ];

    return strtr($string, $mapa);
}

function removeSlugs($string) {
    // 1. Remove acentos usando seu mapa (ou iconv se preferir)
    $string = removerAcentos($string);
    
    // 2. Converte para minúsculo
    $string = mb_strtolower($string, 'UTF-8');
    
    // 3. Remove qualquer coisa que não seja letra de a-z ou número
    // Isso mata pontos, aspas, espaços extras, etc.
    $string = preg_replace('/[^a-z0-9]/', '', $string);
    
    return $string;
}

function formatName($nome) {
    $nome = preg_split('/\s+/', $nome);
    return $nome;
}

function calcularAnos($data) {
    $d1 = new DateTime($data);
    $d2 = new DateTime(date("Y-m-d"));

    $diff = $d2->diff($d1);

    return $diff->y;
}

function formatValor($val) {
    $valor_formatado = "R$ " . number_format($val, 2, ',', '.');
    return $valor_formatado;
}

function formatarDuracao($segundos) {
    $horas = floor($segundos / 3600); // Calcula as horas
    $minutos = floor(($segundos % 3600) / 60); // Calcula os minutos restantes

    // Se houver horas, exibe a parte da hora
    if ($horas > 0) {
        return $horas . 'hr ' . $minutos . 'min';
    } else {
        return $minutos . 'min';
    }
}

function getCrescimento($val1, $val2) {
    return (($val2 - $val1) / $val1) * 100;
}

function AutenticateRequisition() {
    $headers = getallheaders();
    $validatedSession = false;
    $validatedToken = false;
    $tenant = null;

    if (empty($headers['Authorization'])) {
        $user = currentUser();

        $validatedSession = true;
        $admin = false;

        $tenant = new Tenant();

        $tenant->ApiRequest = false;
        $tenant->IdUsuario = $user->ID;
        $tenant->NodeServer = false;
        $tenant->Valid = true;
        $tenant->User = $user;
        $tenant->ParentID = $_SESSION['businessid'] ?? 0;
    }
    else {
        $auth = $headers['Authorization'];
        $sql = Database::Query("SELECT * FROM env_keys WHERE env_key = ?", [ $auth ]);

        if ($sql->validQuery()) {
            if ($sql->length() > 0) {
                $name = $sql->get(0)['env_name'];
                $validatedToken = true;
                
                if ($name == "SOCKET_SERVER_AUTH_KEY") {
                    $tenant = new Tenant();
                    $tenant->Valid = true;
                    $tenant->NodeServer = true;
                }
                else if ($name == "API_TEST_KEY") {
                    $tenant = new Tenant();
                    $tenant->Valid = true;
                    $tenant->ApiRequest = true;
                }
            }
        }
    }

    if ((empty($tenant) or !$tenant->Valid) && !$validatedToken) {
        $validatedSession = false;
    }

    return [ "Auth" => ($validatedSession || $validatedToken), "Tenant" => $tenant ];
}

function getExecutor() {
    if (!isset($_COOKIE['SessionID']) or !isset($_COOKIE['SessionInfo'])) {
        return 0;
    }

    $sessionToken = $_COOKIE['SessionID'];
    $sessionData = $_COOKIE['SessionInfo'];

    if ($sessionToken == null) {
        return 0;
    }

    $sessionData = Cryptography::Decrypt($sessionData, $sessionToken);

    try {
        $sessionData = json_decode($sessionData, true);
    }
    catch (Exception $ex){
        return 0;
    }

    $id_usuario = $sessionData['id_usuario'];
    return $id_usuario;
}

function getUfs() {
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

    return $ufs;
}

function searchUf($uf) {
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

    return $ufs[$uf] ?? null;
}

final class Roles {
    public const MEMBER = 0;
    public const ADMIN = 1;
}

final class Version {
    public const FREE = "Free";
    public const PLUS = "Plus";
    public const PRO = "Pro";
    public const DIAMOND = "Diamond";
}

function loadJson($caminhoArquivo) {
    // 1. Verifica se o arquivo existe
    if (!file_exists($caminhoArquivo)) {
        echo "Erro: O arquivo JSON não foi encontrado em: $caminhoArquivo\n";
        return null;
    }

    // 2. Lê o conteúdo do arquivo JSON como uma string
    $jsonString = file_get_contents($caminhoArquivo);

    // Verifica se a leitura foi bem-sucedida
    if ($jsonString === false) {
        echo "Erro: Falha ao ler o conteúdo do arquivo.\n";
        return null;
    }

    // 3. Decodifica a string JSON em um array associativo
    // O segundo argumento 'true' é crucial para retornar um array associativo,
    // em vez de um objeto (stdClass).
    $dadosArray = json_decode($jsonString, true);

    // 4. Verifica se a decodificação foi bem-sucedida
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Erro ao decodificar JSON: " . json_last_error_msg() . "\n";
        return null;
    }

    return $dadosArray;
}

function getCountries() {
    return [
        "AF" => "Afeganistão",
        "ZA" => "África do Sul",
        "AL" => "Albânia",
        "DE" => "Alemanha",
        "AD" => "Andorra",
        "AO" => "Angola",
        "AI" => "Anguilla",
        "AQ" => "Antártida",
        "AG" => "Antígua e Barbuda",
        "SA" => "Arábia Saudita",
        "DZ" => "Argélia",
        "AR" => "Argentina",
        "AM" => "Arménia",
        "AW" => "Aruba",
        "AU" => "Austrália",
        "AT" => "Áustria",
        "AZ" => "Azerbaijão",
        "BS" => "Bahamas",
        "BD" => "Bangladesh",
        "BB" => "Barbados",
        "BH" => "Bahrein",
        "BE" => "Bélgica",
        "BZ" => "Belize",
        "BJ" => "Benim",
        "BM" => "Bermudas",
        "BY" => "Bielorrússia",
        "BO" => "Bolívia",
        "BA" => "Bósnia e Herzegovina",
        "BW" => "Botsuana",
        "BR" => "Brasil",
        "BN" => "Brunei",
        "BG" => "Bulgária",
        "BF" => "Burquina Faso",
        "BI" => "Burundi",
        "BT" => "Butão",
        "CV" => "Cabo Verde",
        "KH" => "Camboja",
        "CM" => "Camarões",
        "CA" => "Canadá",
        "QA" => "Catar",
        "KZ" => "Cazaquistão",
        "TD" => "Chade",
        "CL" => "Chile",
        "CN" => "China",
        "CY" => "Chipre",
        "CO" => "Colômbia",
        "KM" => "Comores",
        "CG" => "Congo - Brazzaville",
        "CD" => "Congo - Kinshasa",
        "KP" => "Coreia do Norte",
        "KR" => "Coreia do Sul",
        "CI" => "Costa do Marfim",
        "CR" => "Costa Rica",
        "HR" => "Croácia",
        "CU" => "Cuba",
        "DK" => "Dinamarca",
        "DJ" => "Djibuti",
        "DM" => "Dominica",
        "EG" => "Egito",
        "SV" => "El Salvador",
        "AE" => "Emirados Árabes Unidos",
        "EC" => "Equador",
        "ER" => "Eritreia",
        "SK" => "Eslováquia",
        "SI" => "Eslovénia",
        "ES" => "Espanha",
        "US" => "Estados Unidos",
        "EE" => "Estónia",
        "ET" => "Etiópia",
        "FJ" => "Fiji",
        "PH" => "Filipinas",
        "FI" => "Finlândia",
        "FR" => "França",
        "GA" => "Gabão",
        "GM" => "Gâmbia",
        "GH" => "Gana",
        "GE" => "Geórgia",
        "GI" => "Gibraltar",
        "GD" => "Granada",
        "GR" => "Grécia",
        "GL" => "Gronelândia",
        "GP" => "Guadalupe",
        "GU" => "Guam",
        "GT" => "Guatemala",
        "GY" => "Guiana",
        "GF" => "Guiana Francesa",
        "GN" => "Guiné",
        "GQ" => "Guiné Equatorial",
        "GW" => "Guiné-Bissau",
        "HT" => "Haiti",
        "HN" => "Honduras",
        "HK" => "Hong Kong, RAE da China",
        "HU" => "Hungria",
        "YEM" => "Iémen",
        "IN" => "Índia",
        "ID" => "Indonésia",
        "IR" => "Irão",
        "IQ" => "Iraque",
        "IE" => "Irlanda",
        "IS" => "Islândia",
        "IL" => "Israel",
        "IT" => "Itália",
        "JM" => "Jamaica",
        "JP" => "Japão",
        "JO" => "Jordânia",
        "KW" => "Kuwait",
        "LA" => "Laos",
        "LS" => "Lesoto",
        "LV" => "Letónia",
        "LB" => "Líbano",
        "LR" => "Libéria",
        "LY" => "Líbia",
        "LI" => "Liechtenstein",
        "LT" => "Lituânia",
        "LU" => "Luxemburgo",
        "MO" => "Macau, RAE da China",
        "MK" => "Macedónia do Norte",
        "MG" => "Madagáscar",
        "MY" => "Malásia",
        "MW" => "Maláui",
        "MV" => "Maldivas",
        "ML" => "Mali",
        "MT" => "Malta",
        "MA" => "Marrocos",
        "MQ" => "Martinica",
        "MU" => "Maurícia",
        "MR" => "Mauritânia",
        "MX" => "México",
        "MM" => "Mianmar (Birmânia)",
        "FM" => "Micronésia",
        "MZ" => "Moçambique",
        "MD" => "Moldávia",
        "MC" => "Mónaco",
        "MN" => "Mongólia",
        "ME" => "Montenegro",
        "MS" => "Monserrate",
        "NA" => "Namíbia",
        "NR" => "Nauru",
        "NP" => "Nepal",
        "NI" => "Nicarágua",
        "NE" => "Níger",
        "NG" => "Nigéria",
        "NU" => "Niue",
        "NO" => "Noruega",
        "NC" => "Nova Caledónia",
        "NZ" => "Nova Zelândia",
        "OM" => "Omã",
        "NL" => "Países Baixos",
        "PW" => "Palau",
        "PA" => "Panamá",
        "PG" => "Papua-Nova Guiné",
        "PK" => "Paquistão",
        "PY" => "Paraguai",
        "PE" => "Peru",
        "PF" => "Polinésia Francesa",
        "PL" => "Polónia",
        "PR" => "Porto Rico",
        "PT" => "Portugal",
        "KE" => "Quénia",
        "KG" => "Quirguistão",
        "KI" => "Quiribati",
        "GB" => "Reino Unido",
        "CF" => "República Centro-Africana",
        "CZ" => "República Checa",
        "DO" => "República Dominicana",
        "RE" => "Reunião",
        "RO" => "Roménia",
        "RW" => "Ruanda",
        "RU" => "Rússia",
        "EH" => "Saara Ocidental",
        "WS" => "Samoa",
        "AS" => "Samoa Americana",
        "SM" => "San Marino",
        "SH" => "Santa Helena",
        "LC" => "Santa Lúcia",
        "KN" => "São Cristóvão e Neves",
        "ST" => "São Tomé e Príncipe",
        "VC" => "São Vicente e Granadinas",
        "SN" => "Senegal",
        "SL" => "Serra Leoa",
        "RS" => "Sérvia",
        "SC" => "Seicheles",
        "SG" => "Singapura",
        "SY" => "Síria",
        "SO" => "Somália",
        "LK" => "Sri Lanka",
        "SZ" => "Suazilândia",
        "SD" => "Sudão",
        "SS" => "Sudão do Sul",
        "SE" => "Suécia",
        "CH" => "Suíça",
        "SR" => "Suriname",
        "TH" => "Tailândia",
        "TW" => "Taiwan",
        "TJ" => "Tajiquistão",
        "TZ" => "Tanzânia",
        "TL" => "Timor-Leste",
        "TG" => "Togo",
        "TK" => "Tokelau",
        "TO" => "Tonga",
        "TT" => "Trindade e Tobago",
        "TN" => "Tunísia",
        "TR" => "Turquia",
        "TM" => "Turquemenistão",
        "UA" => "Ucrânia",
        "UG" => "Uganda",
        "UY" => "Uruguai",
        "UZ" => "Usbequistão",
        "VU" => "Vanuatu",
        "VA" => "Cidade do Vaticano",
        "VE" => "Venezuela",
        "VN" => "Vietname",
        "WF" => "Wallis e Futuna",
        "ZM" => "Zâmbia",
        "ZW" => "Zimbabué"
    ];
}

function currentUser() : User|null {

    if (session_status() === PHP_SESSION_NONE)
        session_start();

    if (empty($_SESSION['userid'])) {
        return null;
    }

    return Utils::GetUserData($_SESSION['userid']);
}

function getUser($id = null) {
    if (empty($id)) return currentUser();

    $sql = Database::Query("SELECT id FROM usuarios WHERE id = ?", [
        parameter("s", $id)
    ]);

    if ($sql->validQuery()) {
        return Utils::GetUserData($sql->field(0, "id"));
    }

    return null;
}

function docList() {
    return [ "Curriculum", "Imagem", "Documento", "RG", "CNH" ];
}

class Utils {

    public static function HttpRequest($url, $method, $headers = [], $body = []) {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers
        ]);

        if (!empty($body) and gettype($body) == "array") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $result = [];
        $response = json_decode($response, true);

        if ($code >= 200 and $code <= 300) {
            $result['Status'] = 1;
        }
        else {
            $result['Status'] = 0;
        }

        $result['Body'] = $response;

        return $result;
    }

    public static function formatarCEP(string $numero): string {
        
        // 1. Remove todos os caracteres que não são dígitos (equivalente a /\.D/g, "")
        $valorLimpo = preg_replace('/\D/', '', $numero); 

        // O CEP brasileiro tem exatamente 8 dígitos.
        $tamanho = strlen($valorLimpo);

        if ($tamanho >= 8) {
            // Se tiver 8 ou mais dígitos, formata: XXXXX-XXX
            // Pega os primeiros 5 e junta com os 3 seguintes, separados por hífen.
            $primeiraParte = substr($valorLimpo, 0, 5);
            $segundaParte = substr($valorLimpo, 5, 3);
            
            return "{$primeiraParte}-{$segundaParte}";
            
        } elseif ($tamanho > 5) {
            // Se tiver 6 ou 7 dígitos, retorna a primeira parte com o início da segunda (sem hífen)
            // Ex: 1234567 -> 1234567
            return substr($valorLimpo, 0, 5) . substr($valorLimpo, 5);
            
        }
        
        // Se tiver 5 ou menos dígitos, retorna o número limpo (não formatado).
        return $valorLimpo;
    }

    public static function ArrayJoin(array ...$arrays) : Array {
        $result = [];

        foreach($arrays as $obj) {
            $result = array_merge($result, $obj);
        }

        return $result;
    }

    public static function GetUserData(int|null $id_usuario = null, array|null $row = null) : User|null {

        $result = new User();

        if (!empty($row) and !empty($row['dados'])) {
            $token = GetToken($row['id'], $row['identifier'], TokenEnv::USUARIO);
            $result = new User();
            
            $data = Decrypt($row['dados'], $token);
            $data = json_decode($data, true);
            $result = User::Build($data);

            $result->Identifier = $row['identifier'];
            $result->ID = $row['id'];
            $result->ParentID = $row['parent_id'] ?? 0;

            return $result;
        }

        if (empty($id_usuario))
            return $result;

        $sql = Database::Query("SELECT 
        dados,
        identifier, 
        id as id_usuario,
        status
        FROM usuarios
        WHERE usuarios.id = ?", [
            $id_usuario
        ]);

        if ($sql->validQuery()) {
            $token = GetToken($sql->field(0, "id_usuario"), $sql->field(0, "identifier"), TokenEnv::USUARIO);
            $result = new User();
            
            $data = Decrypt($sql->field(0, "dados"), $token);
            $data = json_decode($data, true);
            $result = User::Build($data);

            $result->Identifier = $sql->field(0, "identifier");
            $result->ID = $sql->field(0, "id_usuario");
        }
        else {
            $result->Nome = $sql->error();
        }

        return $result;
    }

    public static function GetClientData(int|null $id_cliente = null, string|null $identifier = null, array|null $row = null) : Cliente {

        $result = new Cliente();

        if (!empty($row)) {
            $token = GetToken($row['id'], $row['identifier'], TokenEnv::EMPRESA);
            
            $data = Decrypt($row['dados'], $token);
            $data = json_decode($data, true);
            $result = Cliente::Build($data);

            $result->Identifier = $row['identifier'];
            $result->ID = $row['id'];

            return $result;
        }

        if (empty($id_cliente) and empty($identifier))
            return $result;

        $sql = Database::Query("SELECT 
        dados,
        identifier, 
        id,
        status
        FROM empresas
        WHERE empresas.id <=> COALESCE(?, empresas.id) AND empresas.identifier <=> COALESCE(?, empresas.identifier)", [
            $id_cliente,
            $identifier
        ]);

        if ($sql->validQuery()) {
            $token = GetToken($sql->field(0, "id"), $sql->field(0, "identifier"), TokenEnv::EMPRESA);
            
            $data = Decrypt($sql->field(0, "dados"), $token);
            $data = json_decode($data, true);
            $result = Cliente::Build($data);

            $result->Identifier = $sql->field(0, "identifier");
            $result->ID = $sql->field(0, "id");
        }
        else {
            $result->Nome = $sql->error();
        }

        return $result;
    }
    /**
     * Converte uma cor hexadecimal para um array RGB.
     * @param string $hexColor A cor em formato hexadecimal (ex: "#RRGGBB" ou "RRGGBB").
     * @return array Um array associativo com as chaves 'r', 'g', 'b'.
     */
    public static function hexToRgb(string $hexColor): array {
        $hexColor = ltrim($hexColor, '#');
        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }
        return [
            'r' => hexdec(substr($hexColor, 0, 2)),
            'g' => hexdec(substr($hexColor, 2, 2)),
            'b' => hexdec(substr($hexColor, 4, 2))
        ];
    }

    /**
     * Calcula a luminância percebida de uma cor RGB.
     * @param int $r Valor do componente vermelho (0-255).
     * @param int $g Valor do componente verde (0-255).
     * @param int $b Valor do componente azul (0-255).
     * @return float A luminância da cor (0-255).
     */
    public static function calculateLuminance(int $r, int $g, int $b): float {
        // Fórmula de luminância para sRGB (baseada em WCAG)
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
    }

    /**
     * Define a melhor cor de texto de hover com base na cor de fundo,
     * priorizando uma cor preferencial e fornecendo uma alternativa se o contraste for baixo.
     *
     * @param string $bgColor A cor de fundo em formato hexadecimal (ex: "#RRGGBB" ou "RRGGBB").
     * @param string $preferredHoverTextColor A cor de texto de hover preferencial (ex: "#45a7c4").
     * @param string $fallbackHoverTextColor A cor de texto de hover alternativa (ex: "#000000" para preto ou "#FFFFFF" para branco).
     * @param float $luminanceThreshold Para a cor do texto padrão (preto/branco). Default 128.
     * @param float $minContrastThreshold Mínimo de diferença de luminância para usar a cor preferencial. Default 50.
     * @return string Retorna a cor hexadecimal para o texto de hover.
     */
    public static function getSmartHoverTextColor(
        string $bgColor,
        string $preferredHoverTextColor = '#45a7c4',
        string $fallbackHoverTextColor = '#000000', // Padrão preto, mas pode ser branco se preferir
        float $luminanceThreshold = 128.0, // Limiar para texto black/white
        float $minContrastThreshold = 50.0 // Limiar para contraste entre fundo e cor preferencial
    ): string {
        $rgbBg = Utils::hexToRgb($bgColor);
        $rgbPreferredHover = Utils::hexToRgb($preferredHoverTextColor);
        $rgbFallbackHover = Utils::hexToRgb($fallbackHoverTextColor);

        $luminanceBg = Utils::calculateLuminance($rgbBg['r'], $rgbBg['g'], $rgbBg['b']);
        $luminancePreferredHover = Utils::calculateLuminance($rgbPreferredHover['r'], $rgbPreferredHover['g'], $rgbPreferredHover['b']);
        $luminanceFallbackHover = Utils::calculateLuminance($rgbFallbackHover['r'], $rgbFallbackHover['g'], $rgbFallbackHover['b']);

        // 1. Determine a cor do texto padrão (preto ou branco) para a cor de fundo.
        // Isso é útil para determinar a 'polaridade' da cor de fundo (claro/escuro).
        $defaultTextColor = ($luminanceBg > $luminanceThreshold) ? 'black' : 'white';

        // 2. Calcule a diferença absoluta de luminância entre o fundo e a cor de hover preferencial.
        $luminanceDiff = abs($luminanceBg - $luminancePreferredHover);

        // 3. Verifique se a cor preferencial tem contraste suficiente com o fundo.
        if ($luminanceDiff >= $minContrastThreshold) {
            // Se o contraste for suficiente, use a cor preferencial.
            return $preferredHoverTextColor;
        } else {
            // Se o contraste for muito baixo, use a cor alternativa.
            // É bom garantir que a cor alternativa tenha bom contraste com o fundo.
            // Podemos usar a lógica anterior de preto/branco para o fallback, se o fallback não for fixo.
            // Ou, se o fallback for fixo, apenas use-o.

            // Aqui usamos a `fallbackHoverTextColor` que foi passada.
            // Uma melhoria seria testar o contraste do fallback também, mas para simplicidade,
            // vamos assumir que o fallback fornecido já é bom.
            return $fallbackHoverTextColor;
        }
    }

    public static function getContrastTextColor(string $hexColor): string {
        $hexColor = ltrim($hexColor, '#');

        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }

        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
        $threshold = 128;

        if ($luminance > $threshold) {
            return '#000000';
        } else {
            return '#ffffff';
        }
    }

    public static function getClientToken($id_cliente) {
        $data = Database::Query("SELECT token FROM cryptography WHERE id_cliente = ?", array( new Parameter("i", $id_cliente) ));
        $result = null;

        if ($data->isValid() and $data->length() > 0) {
            $result = $data->field(0, "token");
        }

        return $result;
    }
    public static function getDirLink() {
        // Obtém o link base sem o caminho atual
        $baseLink = self::getPageLink();

        // Obtém o caminho da URL (ex: /telemedicina/admin/dashboard)
        $requestUri = $_SERVER['REQUEST_URI'];

        // Remove o prefixo se existir (ex: /telemedicina/)
        $prefix = "";
        if (strpos($requestUri, '/ecoglobal/') !== false) {
            $prefix = "/ecoglobal";
        }

        // Divide o caminho em partes
        $parts = explode('/', trim($requestUri, '/'));
        
        // Remove o prefixo do array se for um ambiente de desenvolvimento
        if (!empty($prefix) && $parts[0] === 'ecoglobal') {
            array_shift($parts);
        }

        // O primeiro diretório é o que queremos
        $dir = $parts[0] ?? '';

        // Se o primeiro diretório existir, retorna o link base + o diretório
        if (!empty($dir)) {
            return $baseLink . '/' . $dir;
        }

        // Se não houver diretório (ex: página inicial), retorna apenas o link base
        return $baseLink;
    }

    public static function getPageLink() {
        $protocol = (empty($_SERVER['HTTPS']) ? 'http' : 'https');
        $url = $_SERVER['SERVER_NAME'];
        $url2 = $url;
        $prefix = "";

        if ($url == "localhost" || $url == "192.168.15.6") {
            $url = $url . "/ecoglobal/";
            $url2 = $url2 . "/ecoglobal";
        }

        $link = $protocol."://".$url2;

        return $link;
    }

    public static function SanitizeInput($str) {
        $str = trim($str);
        $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        return $str;
    }

    public static function getFullUrl() {
        // Protocolo (http ou https)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';

        // Host (domínio ou IP)
        $host = $_SERVER['HTTP_HOST'];

        // Caminho e consulta (path e query string)
        $requestUri = $_SERVER['REQUEST_URI'];

        // URL completa
        $url = $protocol . '://' . $host . $requestUri;
        return $url;
    }

    public static function GetClienteRegistro($id_usuario) {
        $data = Database::Query("SELECT dados_usuarios.id_cliente, clientes.numero_registro FROM dados_usuarios
        LEFT JOIN clientes ON clientes.id = dados_usuarios.id_cliente WHERE dados_usuarios.id_usuario = ".$id_usuario);

        if ($data->isValid() and $data->length() > 0) {
            if ($data->field(0, "id_cliente") == null or empty($data->field(0, "id_cliente"))) {
                return null;
            }
            else {
                return $data->field(0, "numero_registro");
            }
        }

        return null;
    }

    public static function GetClientID($registro) {
        $data = Database::Query("SELECT id FROM clientes WHERE numero_registro = '".$registro."'");

        if ($data->isValid() and $data->length() > 0) {
            return $data->field(0, "id");
        }

        return null;
    }

    public static function ValidateUserClient($id_usuario, $id_cliente) {
        $data = Database::Query("SELECT id FROM clientes WHERE numero_registro = '".$id_cliente."'");

        if ($data->isValid() and $data->length() > 0){
            $id_cliente = intval($data->field(0, "id"));
        }

        $data = Database::Query("SELECT id_cliente FROM dados_usuarios WHERE id_usuario = ".$id_usuario);

        if ($data->isValid() and $data->length() > 0) {
            if ($data->field(0, "id_cliente") == null or empty($data->field(0, "id_cliente"))) {
                return false;
            }
            else {
                $id_cliente_us = $data->field(0, "id_cliente");

                if (gettype($id_cliente) == "integer" and $id_cliente == intval($id_cliente_us)) {
                    return true;
                }
                else {
                    return false;
                }
            }
        }

        return false;
    }

    public static function getUserIP() {
        return file_get_contents('https://api.ipify.org');
    }

    public static function getDeviceUUID() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $ip = self::getUserIP();

        return hash('sha256', ($userAgent.":".$ip));
    }

    public static function getDeviceId()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        $fingerprint = $userAgent . $acceptLanguage . $ipAddress;

        return md5($fingerprint);
    }

    public static function SaveFile($docname, $image, $location) {

        if ($image == null or !isset($image['name'])) return null;

        $info = pathinfo($image['name']);

        if (!isset($info['extension'])) return null;

        $ext = $info['extension']; // get the extension of the file

        if (empty($docname)) {
            $docname = $info['filename'];
        }

        $newname = $docname.".".$ext;

        if (!is_dir($location)) {
            mkdir($location, 0777, true);
        }

        $target = $location."/".$newname;

        $idx = 1;

        while(is_file($target)) {
            $target = $location."/".($docname."_".$idx.".".$ext);
            $newname = $docname."_".$idx.".".$ext;
            $idx++;
        }

        $uploaded = move_uploaded_file($image['tmp_name'], $target);

        if ($uploaded) { return array( "Name" => $newname, "Location" => $target); } else { return null; }
    }

    public static function DeleteFile($fileName) {
        if ($fileName != "" and file_exists($fileName)) {
            return unlink($fileName);
        }
    }

    public static function RandomString($lenght = 8, $onlyNumbers = false, $onlyLetters = false, $includeNonAlphaNumerical = false, $customCharacters = "")
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        if ($onlyNumbers) {
            $characters = '0123456789';
        }
        else if ($onlyLetters) {
            $characters = 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($includeNonAlphaNumerical) {
            $characters .= ".,@;?{}";
        }

        $characters .= $customCharacters;

        $randstring = '';
        for ($i = 0; $i < $lenght; $i++) {
            $randstring .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randstring;
    }

    public static function RandomNumbers($length = 4) {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9); // Mais seguro que rand()
        }
        return $result;
    }

    public static function validateCPF($number) {

        $cpf = preg_replace('/[^0-9]/', "", $number);

        if (strlen($cpf) != 11 || preg_match('/([0-9])\1{10}/', $cpf)) {
            return false;
        }

        $number_quantity_to_loop = [9, 10];

        foreach ($number_quantity_to_loop as $item) {

            $sum = 0;
            $number_to_multiplicate = $item + 1;

            for ($index = 0; $index < $item; $index++) {

                $sum += $cpf[$index] * ($number_to_multiplicate--);

            }

            $result = (($sum * 10) % 11);

            if ($cpf[$item] != $result) {
                return false;
            }

        }

        return true;
    }

    public static function formatarNumGuia($numero) {
        // Remove todos os caracteres não numéricos
        $value = preg_replace('/\D/', '', $numero);

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 4);
        }
        if (strlen($value) >= 4) {
            $formattedValue .= '.' . substr($value, 4, 4);
        }
        if (strlen($value) >= 8) {
            $formattedValue .= '.' . substr($value, 8, 2);
        }
        if (strlen($value) >= 10) {
            $formattedValue .= '-' . substr($value, 10);
        }

        return $formattedValue;
    }

    public static function Response($headers, $result, $code = 200) {

        foreach($headers as $header => $value) {
            header($header.": ".$value);
        }

        http_response_code($code);
        header("Server: CYANTECH/SERVER v1");
        header("X-Powered-By: CYANTECH/SYS v1");
        header("Access-Control-Allow-Origin: *");

        if (isset($headers['Content-Type']) and $headers['Content-Type'] == "application/json") {
            echo json_encode($result);
        }
        else {
            echo gettype($result) == "array" ? json_encode($result) : $result;
        }

        exit();
    }

    public static function formatarCPF($text) {
        $value = preg_replace('/\D/', '', $text);// Remove todos os caracteres não numéricos

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 3);
        }
        if (strlen($value) >= 3) {
            $formattedValue .= '.' . substr($value, 3, 3);
        }
        if (strlen($value) >= 6) {
            $formattedValue .= '.' . substr($value, 6, 3);
        }
        if (strlen($value) >= 9) {
            $formattedValue .= '-' . substr($value, 9, 2);
        }

        return $formattedValue;
    }

    public static function formatarTelefone($text) {
        $value = preg_replace('/\D/', '', $text);
        $result = "";

        if (strlen($value) > 10) {
            $text = substr($value, 0, -1);
        }

        if (strlen($value) >= 7) {
            $result = "(".substr($value, 0, 2).") ".substr($value, 2, 5)."-".substr($value, 7);
        }
        else if (strlen($value) > 2 && strlen($value) <= 7) {
            $result = "(".substr($value, 0, 2).") ".substr($value, 2, 7);
        }
        else if (strlen($value) <= 2) {
            $result = substr($value, 0, 2);
        }

        return $result;
    }

    public static function formatarCarteirinha($text) {
        $value = preg_replace('/\D/', '', $text);// Remove todos os caracteres não numéricos

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 4);
        }
        if (strlen($value) >= 4) {
            $formattedValue .= '.' . substr($value, 4, 4);
        }
        if (strlen($value) >= 8) {
            $formattedValue .= '.' . substr($value, 8, 6);
        }
        if (strlen($value) >= 14) {
            $formattedValue .= '.' . substr($value, 14, 2);
        }
        if (strlen($value) >= 16) {
            $formattedValue .= '-' . substr($value, 16, 1);
        }

        return $formattedValue;
    }

    public static function formatarCnpj($text) {
        $value = preg_replace('/\D/', '', $text);// Remove todos os caracteres não numéricos

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 2);
        }
        if (strlen($value) >= 2) {
            $formattedValue .= '.' . substr($value, 2, 3);
        }
        if (strlen($value) >= 5) {
            $formattedValue .= '.' . substr($value, 5, 3);
        }
        if (strlen($value) >= 8) {
            $formattedValue .= '/' . substr($value, 8, 4);
        }
        if (strlen($value) >= 12) {
            $formattedValue .= '-' . substr($value, 12, 2);
        }

        return $formattedValue;
    }

    public static function formatarRG($text)
    {
        // Remove todos os caracteres não numéricos
        $value = preg_replace('/\D/', '', $text);

        $formattedValue = "";

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 2);
        }
        if (strlen($value) > 2) {
            $formattedValue .= "." . substr($value, 2, 3); // Do índice 2, 3 caracteres
        }
        if (strlen($value) > 5) {
            $formattedValue .= "." . substr($value, 5, 3); // Do índice 5, 3 caracteres
        }
        if (strlen($value) > 8) {
            $formattedValue .= "-" . substr($value, 8, 2); // Do índice 8, 2 caracteres
        }

        return $formattedValue;
    }

    public static function formatarRNE($text)
    {
        // Pega a primeira letra, removendo dígitos (se houver algum dígito no charAt(0))
        // No JS, charAt(0).replace(/[0-9]/g, "") pode resultar em string vazia se o primeiro char for um dígito.
        // No PHP, podemos pegar o primeiro caractere e verificar se é uma letra.
        $firstChar = substr($text, 0, 1);
        $letter = preg_replace('/[0-9]/', '', $firstChar); // Garante que é uma letra ou vazio

        // Remove todos os caracteres não numéricos do restante para obter apenas os números
        $value = preg_replace('/\D/', '', $text);

        $formattedValue = "";

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 6);
        }
        if (strlen($value) > 6) {
            $formattedValue .= "-" . substr($value, 6, 1);
        }

        // Converte a letra para maiúscula e concatena com o valor formatado
        return strtoupper($letter . $formattedValue);
    }

    public static function GetMyToken() {
        $sql_token = Database::Query("SELECT token FROM login WHERE id = ".$_COOKIE['cc_id_login']);
        $token = $sql_token->get(0)['token'];
        $token = base64_decode($token);
        return $token;
    }

    public static function GetEmpresaToken() {
        $sql_token = Database::Query("SELECT token FROM empresas WHERE id = ".$_COOKIE['cc_id_empresa']);
        $token = $sql_token->get(0)['token'];
        $token = base64_decode($token);
        return $token;
    }

    public static function getHeader($header) {
        $headers = getallheaders();
        if (isset($headers[$header])) { return $headers[$header]; } else { return null; }
    }
}

?>