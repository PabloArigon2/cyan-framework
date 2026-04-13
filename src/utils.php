<?php

define("SELF", $_SERVER['PHP_SELF']);

class Utils {

    // --- Configuração e Ambiente ---

    public static function Env($key) {
        return isset($_ENV[$key]) ? $_ENV[$key] : null;
    }

    // --- Autenticação e Sessão ---

    public static function AuthenticateRequest() {
        $headers = getallheaders();
        $validatedSession = false;
        $validatedToken = false;
        $tenant = null;

        if (empty($headers['Authorization'])) {
            $user = Utils::CurrentUser();

            $validatedSession = true;
            $admin = false;

            $tenant = new Context();

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
                        $tenant = new Context();
                        $tenant->Valid = true;
                        $tenant->NodeServer = true;
                    }
                    else if ($name == "API_TEST_KEY") {
                        $tenant = new Context();
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

    public static function GetExecutor() {
        if (!isset($_COOKIE['SessionID']) or !isset($_COOKIE['SessionInfo'])) {
            return 0;
        }

        $sessionToken = $_COOKIE['SessionID'];
        $sessionData = $_COOKIE['SessionInfo'];

        if ($sessionToken == null) {
            return 0;
        }

        $sessionData = Security::Decrypt($sessionData, $sessionToken);

        try {
            $sessionData = json_decode($sessionData, true);
        }
        catch (\Exception $ex){
            return 0;
        }

        $id_usuario = $sessionData['id_usuario'];
        return $id_usuario;
    }

    public static function CurrentUser() : User|null {

        if (session_status() === PHP_SESSION_NONE)
            session_start();

        if (empty($_SESSION['userid'])) {
            return null;
        }

        return Utils::GetUserData($_SESSION['userid']);
    }

    public static function GetUser($id = null) {
        if (empty($id)) return Utils::CurrentUser();

        $sql = Database::Query("SELECT id FROM usuarios WHERE id = ?", [
            new Parameter("s", $id)
        ]);

        if ($sql->validQuery()) {
            return Utils::GetUserData($sql->field(0, "id"));
        }

        return null;
    }

    // --- Dados Geográficos e Referência ---

    public static function GetUfs() {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
    }

    public static function SearchUf($uf) {
        $ufs = self::GetUfs();
        return $ufs[$uf] ?? null;
    }

    public static function GetCountries() {
        return [
            "AF" => "Afeganistão", "ZA" => "África do Sul", "AL" => "Albânia", "DE" => "Alemanha",
            "AD" => "Andorra", "AO" => "Angola", "AI" => "Anguilla", "AQ" => "Antártida",
            "AG" => "Antígua e Barbuda", "SA" => "Arábia Saudita", "DZ" => "Argélia", "AR" => "Argentina",
            "AM" => "Arménia", "AW" => "Aruba", "AU" => "Austrália", "AT" => "Áustria",
            "AZ" => "Azerbaijão", "BS" => "Bahamas", "BD" => "Bangladesh", "BB" => "Barbados",
            "BH" => "Bahrein", "BE" => "Bélgica", "BZ" => "Belize", "BJ" => "Benim",
            "BM" => "Bermudas", "BY" => "Bielorrússia", "BO" => "Bolívia", "BA" => "Bósnia e Herzegovina",
            "BW" => "Botsuana", "BR" => "Brasil", "BN" => "Brunei", "BG" => "Bulgária",
            "BF" => "Burquina Faso", "BI" => "Burundi", "BT" => "Butão", "CV" => "Cabo Verde",
            "KH" => "Camboja", "CM" => "Camarões", "CA" => "Canadá", "QA" => "Catar",
            "KZ" => "Cazaquistão", "TD" => "Chade", "CL" => "Chile", "CN" => "China",
            "CY" => "Chipre", "CO" => "Colômbia", "KM" => "Comores", "CG" => "Congo - Brazzaville",
            "CD" => "Congo - Kinshasa", "KP" => "Coreia do Norte", "KR" => "Coreia do Sul",
            "CI" => "Costa do Marfim", "CR" => "Costa Rica", "HR" => "Croácia", "CU" => "Cuba",
            "DK" => "Dinamarca", "DJ" => "Djibuti", "DM" => "Dominica", "EG" => "Egito",
            "SV" => "El Salvador", "AE" => "Emirados Árabes Unidos", "EC" => "Equador", "ER" => "Eritreia",
            "SK" => "Eslováquia", "SI" => "Eslovénia", "ES" => "Espanha", "US" => "Estados Unidos",
            "EE" => "Estónia", "ET" => "Etiópia", "FJ" => "Fiji", "PH" => "Filipinas",
            "FI" => "Finlândia", "FR" => "França", "GA" => "Gabão", "GM" => "Gâmbia",
            "GH" => "Gana", "GE" => "Geórgia", "GI" => "Gibraltar", "GD" => "Granada",
            "GR" => "Grécia", "GL" => "Gronelândia", "GP" => "Guadalupe", "GU" => "Guam",
            "GT" => "Guatemala", "GY" => "Guiana", "GF" => "Guiana Francesa", "GN" => "Guiné",
            "GQ" => "Guiné Equatorial", "GW" => "Guiné-Bissau", "HT" => "Haiti", "HN" => "Honduras",
            "HK" => "Hong Kong, RAE da China", "HU" => "Hungria", "YEM" => "Iémen", "IN" => "Índia",
            "ID" => "Indonésia", "IR" => "Irão", "IQ" => "Iraque", "IE" => "Irlanda",
            "IS" => "Islândia", "IL" => "Israel", "IT" => "Itália", "JM" => "Jamaica",
            "JP" => "Japão", "JO" => "Jordânia", "KW" => "Kuwait", "LA" => "Laos",
            "LS" => "Lesoto", "LV" => "Letónia", "LB" => "Líbano", "LR" => "Libéria",
            "LY" => "Líbia", "LI" => "Liechtenstein", "LT" => "Lituânia", "LU" => "Luxemburgo",
            "MO" => "Macau, RAE da China", "MK" => "Macedónia do Norte", "MG" => "Madagáscar",
            "MY" => "Malásia", "MW" => "Maláui", "MV" => "Maldivas", "ML" => "Mali",
            "MT" => "Malta", "MA" => "Marrocos", "MQ" => "Martinica", "MU" => "Maurícia",
            "MR" => "Mauritânia", "MX" => "México", "MM" => "Mianmar (Birmânia)", "FM" => "Micronésia",
            "MZ" => "Moçambique", "MD" => "Moldávia", "MC" => "Mónaco", "MN" => "Mongólia",
            "ME" => "Montenegro", "MS" => "Monserrate", "NA" => "Namíbia", "NR" => "Nauru",
            "NP" => "Nepal", "NI" => "Nicarágua", "NE" => "Níger", "NG" => "Nigéria",
            "NU" => "Niue", "NO" => "Noruega", "NC" => "Nova Caledónia", "NZ" => "Nova Zelândia",
            "OM" => "Omã", "NL" => "Países Baixos", "PW" => "Palau", "PA" => "Panamá",
            "PG" => "Papua-Nova Guiné", "PK" => "Paquistão", "PY" => "Paraguai", "PE" => "Peru",
            "PF" => "Polinésia Francesa", "PL" => "Polónia", "PR" => "Porto Rico", "PT" => "Portugal",
            "KE" => "Quénia", "KG" => "Quirguistão", "KI" => "Quiribati", "GB" => "Reino Unido",
            "CF" => "República Centro-Africana", "CZ" => "República Checa", "DO" => "República Dominicana",
            "RE" => "Reunião", "RO" => "Roménia", "RW" => "Ruanda", "RU" => "Rússia",
            "EH" => "Saara Ocidental", "WS" => "Samoa", "AS" => "Samoa Americana", "SM" => "San Marino",
            "SH" => "Santa Helena", "LC" => "Santa Lúcia", "KN" => "São Cristóvão e Neves",
            "ST" => "São Tomé e Príncipe", "VC" => "São Vicente e Granadinas", "SN" => "Senegal",
            "SL" => "Serra Leoa", "RS" => "Sérvia", "SC" => "Seicheles", "SG" => "Singapura",
            "SY" => "Síria", "SO" => "Somália", "LK" => "Sri Lanka", "SZ" => "Suazilândia",
            "SD" => "Sudão", "SS" => "Sudão do Sul", "SE" => "Suécia", "CH" => "Suíça",
            "SR" => "Suriname", "TH" => "Tailândia", "TW" => "Taiwan", "TJ" => "Tajiquistão",
            "TZ" => "Tanzânia", "TL" => "Timor-Leste", "TG" => "Togo", "TK" => "Tokelau",
            "TO" => "Tonga", "TT" => "Trindade e Tobago", "TN" => "Tunísia", "TR" => "Turquia",
            "TM" => "Turquemenistão", "UA" => "Ucrânia", "UG" => "Uganda", "UY" => "Uruguai",
            "UZ" => "Usbequistão", "VU" => "Vanuatu", "VA" => "Cidade do Vaticano", "VE" => "Venezuela",
            "VN" => "Vietname", "WF" => "Wallis e Futuna", "ZM" => "Zâmbia", "ZW" => "Zimbabué"
        ];
    }

    public static function DocList() {
        return [ "Curriculum", "Imagem", "Documento", "RG", "CNH" ];
    }

    // --- Utilitários de I/O e JSON ---

    public static function LoadJson($caminhoArquivo) {
        if (!file_exists($caminhoArquivo)) {
            return null;
        }

        $jsonString = file_get_contents($caminhoArquivo);

        if ($jsonString === false) {
            return null;
        }

        $dadosArray = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $dadosArray;
    }

    // --- HTTP ---

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

    public static function GetHeader($header) {
        $headers = getallheaders();
        if (isset($headers[$header])) { return $headers[$header]; } else { return null; }
    }

    // --- Arrays ---

    public static function ArrayJoin(array ...$arrays) : Array {
        $result = [];

        foreach($arrays as $obj) {
            $result = array_merge($result, $obj);
        }

        return $result;
    }

    // --- Dados do Usuário e Tenant ---

    public static function GetUserData(int|null $id_usuario = null, array|null $row = null) : User|null {

        $result = new User();

        if (!empty($row) and !empty($row['dados'])) {
            $token = Security::Token($row['id'], $row['identifier'], TokenEnv::USUARIO);
            $result = new User();
            
            $data = Security::Decrypt($row['dados'], $token);
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
            $token = Security::Token($sql->field(0, "id_usuario"), $sql->field(0, "identifier"), TokenEnv::USUARIO);
            $result = new User();
            
            $data = Security::Decrypt($sql->field(0, "dados"), $token);
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

    public static function GetTenantData(int|null $id_tenant = null, string|null $identifier = null, array|null $row = null) : array {

        $result = [];

        if (!empty($row)) {
            $token = Security::Token($row['id'], $row['identifier'], TokenEnv::TENANT);
            
            $data = Security::Decrypt($row['dados'], $token);
            $result = json_decode($data, true) ?? [];

            $result['Identifier'] = $row['identifier'];
            $result['ID'] = $row['id'];

            return $result;
        }

        if (empty($id_tenant) and empty($identifier))
            return $result;

        $sql = Database::Query("SELECT 
        dados,
        identifier, 
        id,
        status
        FROM tenants
        WHERE tenants.id <=> COALESCE(?, tenants.id) AND tenants.identifier <=> COALESCE(?, tenants.identifier)", [
            $id_tenant,
            $identifier
        ]);

        if ($sql->validQuery()) {
            $token = Security::Token($sql->field(0, "id"), $sql->field(0, "identifier"), TokenEnv::TENANT);
            
            $data = Security::Decrypt($sql->field(0, "dados"), $token);
            $result = json_decode($data, true) ?? [];

            $result['Identifier'] = $sql->field(0, "identifier");
            $result['ID'] = $sql->field(0, "id");
        }
        else {
            $result['Error'] = $sql->error();
        }

        return $result;
    }

    public static function GetTenantRegistry($id_usuario) {
        $data = Database::Query("SELECT dados_usuarios.id_tenant, tenants.numero_registro FROM dados_usuarios
        LEFT JOIN tenants ON tenants.id = dados_usuarios.id_tenant WHERE dados_usuarios.id_usuario = ".$id_usuario);

        if ($data->isValid() and $data->length() > 0) {
            if ($data->field(0, "id_tenant") == null or empty($data->field(0, "id_tenant"))) {
                return null;
            }
            else {
                return $data->field(0, "numero_registro");
            }
        }

        return null;
    }

    public static function GetTenantID($registro) {
        $data = Database::Query("SELECT id FROM tenants WHERE numero_registro = '".$registro."'");

        if ($data->isValid() and $data->length() > 0) {
            return $data->field(0, "id");
        }

        return null;
    }

    public static function ValidateUserTenant($id_usuario, $id_tenant) {
        $data = Database::Query("SELECT id FROM tenants WHERE numero_registro = '".$id_tenant."'");

        if ($data->isValid() and $data->length() > 0){
            $id_tenant = intval($data->field(0, "id"));
        }

        $data = Database::Query("SELECT id_tenant FROM dados_usuarios WHERE id_usuario = ".$id_usuario);

        if ($data->isValid() and $data->length() > 0) {
            if ($data->field(0, "id_tenant") == null or empty($data->field(0, "id_tenant"))) {
                return false;
            }
            else {
                $id_tenant_us = $data->field(0, "id_tenant");

                if (gettype($id_tenant) == "integer" and $id_tenant == intval($id_tenant_us)) {
                    return true;
                }
                else {
                    return false;
                }
            }
        }

        return false;
    }

    // --- Dispositivos e IP ---

    public static function GetUserIP() {
        return file_get_contents('https://api.ipify.org');
    }

    public static function GetDeviceUUID() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $ip = self::GetUserIP();

        return hash('sha256', ($userAgent.":".$ip));
    }

    public static function GetDeviceId()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        $fingerprint = $userAgent . $acceptLanguage . $ipAddress;

        return md5($fingerprint);
    }

    // --- Arquivos ---

    public static function SaveFile($docname, $image, $location) {

        if ($image == null or !isset($image['name'])) return null;

        $info = pathinfo($image['name']);

        if (!isset($info['extension'])) return null;

        $ext = $info['extension'];

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

    // --- Geração de Strings ---

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
            $result .= random_int(0, 9);
        }
        return $result;
    }

    // --- URLs e Links ---

    public static function getDirLink() {
        $baseLink = self::getPageLink();

        $requestUri = $_SERVER['REQUEST_URI'];
        $appSlug = Utils::Env('APP_SLUG') ?? '';

        $prefix = "";
        if (!empty($appSlug) && strpos($requestUri, '/' . $appSlug . '/') !== false) {
            $prefix = "/" . $appSlug;
        }

        $parts = explode('/', trim($requestUri, '/'));
        
        if (!empty($prefix) && isset($parts[0]) && $parts[0] === $appSlug) {
            array_shift($parts);
        }

        $dir = $parts[0] ?? '';

        if (!empty($dir)) {
            return $baseLink . '/' . $dir;
        }

        return $baseLink;
    }

    public static function getPageLink() {
        $protocol = (empty($_SERVER['HTTPS']) ? 'http' : 'https');
        $url = $_SERVER['SERVER_NAME'];
        $appSlug = Utils::Env('APP_SLUG') ?? '';

        if (!empty($appSlug) && ($url == "localhost" || $url == "127.0.0.1")) {
            $url = $url . "/" . $appSlug;
        }

        return $protocol . "://" . $url;
    }

    public static function getFullUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $requestUri = $_SERVER['REQUEST_URI'];
        $url = $protocol . '://' . $host . $requestUri;
        return $url;
    }

    // --- Sanitização ---

    public static function SanitizeInput($str) {
        $str = trim($str);
        $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        return $str;
    }

    // --- Cores e Contraste ---

    /**
     * Converte uma cor hexadecimal para um array RGB.
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
     */
    public static function calculateLuminance(int $r, int $g, int $b): float {
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
    }

    public static function getSmartHoverTextColor(
        string $bgColor,
        string $preferredHoverTextColor = '#45a7c4',
        string $fallbackHoverTextColor = '#000000',
        float $luminanceThreshold = 128.0,
        float $minContrastThreshold = 50.0
    ): string {
        $rgbBg = Utils::hexToRgb($bgColor);
        $rgbPreferredHover = Utils::hexToRgb($preferredHoverTextColor);
        $rgbFallbackHover = Utils::hexToRgb($fallbackHoverTextColor);

        $luminanceBg = Utils::calculateLuminance($rgbBg['r'], $rgbBg['g'], $rgbBg['b']);
        $luminancePreferredHover = Utils::calculateLuminance($rgbPreferredHover['r'], $rgbPreferredHover['g'], $rgbPreferredHover['b']);
        $luminanceFallbackHover = Utils::calculateLuminance($rgbFallbackHover['r'], $rgbFallbackHover['g'], $rgbFallbackHover['b']);

        $defaultTextColor = ($luminanceBg > $luminanceThreshold) ? 'black' : 'white';

        $luminanceDiff = abs($luminanceBg - $luminancePreferredHover);

        if ($luminanceDiff >= $minContrastThreshold) {
            return $preferredHoverTextColor;
        } else {
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
}

?>