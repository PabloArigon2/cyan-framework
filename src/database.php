<?php


class QueryResult
{
    public $values = array();
    public $error = null;
    public $errorCode = 0;
    public $rows = 0;
    public $duplicated = false;
    private $origQuery = "";

    function SetError($err)
    {
        $this->error = $err;
    }

    function SetErrorCode($errCode) {
        $this->errorCode = $errCode;
    }

    function __construct($arr, $qry)
    {
        $this->values = $arr;
        $this->origQuery = $qry;
    }

    public function isDuplicated() {
        return str_contains($this->error(), "1062") or $this->errorCode == 1062;
    }

    public function getDuplicateKey() {
        if (!$this->isDuplicated()) return null;
        
        // Regex para pegar o que está dentro de 'key' na mensagem do MySQL
        if (preg_match("/for key '(.+?)'/", $this->error(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function isFieldValid($idx, $field)
    {
        return ($this->values[$idx][$field] != null and !empty($this->values[$idx][$field]));
    }

    public function field($idx, $field)
    {
        return $this->values[$idx][$field] ?? null;
    }

    public function length()
    {
        return gettype($this->values) == "array" ? count($this->values) : 0;
    }

    public function error()
    {
        return $this->error ?? "";
    }

    public function getjson($idx = null)
    {
        return ($idx != null and $idx >= 0) ? json_encode($this->values[$idx]) : json_encode($this->values);
    }

    public function each($func)
    {
        foreach ($this->values as $value) {
            $func($value);
        }
    }

    public function for($func)
    {
        for ($i = 0; $i < $this->length(); $i++) {
            $func($i, $this->get($i));
        }
    }

    public function valid() {
        $qry = trim(strtolower($this->origQuery));

        $qry = preg_replace('/^\s*(?:--[^\r\n]*(?:\r?\n|$)|\/\*.*?\*\/\s*)+/s', '', $qry);

        $command = strtok($qry, " \t\n\r");

        return match ($command) {
            'select', 'show', 'describe', 'desc', 'explain', 'with'
                => $this->validQuery(),

            default
                => $this->validExecute()
        };
    }

    public function isValid()
    {
        if ((gettype($this->values) == "boolean" and $this->values) or ($this->values and gettype($this->values) == "array" and ($this->error == null or empty($this->error)))) return true;
        else return false;
    }

    public function hasError()
    {
        return ($this->error == null or $this->error == "" or empty($this->error)) ? false : true;
    }

    private function validExecute(){
        return (!$this->hasError() and $this->get());
    }

    private function validQuery() {
        return ($this->isValid() and $this->length() > 0);
    }

    public function isBoolean()
    {
        return gettype($this->values) == "boolean";
    }

    public function set($i, $v)
    {
        $this->values[$i] = $v;
    }

    public function setRows($rows) {
        $this->rows = $rows;
    }

    public function rowsChanged() {
        return $this->rows;
    }

    public function get($param = null)
    {
        if ($param === null) {
            return $this->values;
        } else {
            return $this->values[$param];
        }
    }
}

class Parameter
{
    public $type = "";
    public $value = "";

    function __construct($type = "s", $value = "")
    {
        $this->type = $type;
        $this->value = $value;
    }
}


class Database
{
    private static ?Cache $cache = null;
    private static bool $CacheUnavailable = false;
    public static $server = "";
    public static $user = "";
    public static $pass = "";
    public static $dbn = "";
    public static $port = 0;
    static $onTransaction = false;
    static ?PDO $context = null;

    public static function BuildStructure($parentTypeEnum = []) {
        $sqlUsuarios = "CREATE TABLE `usuarios` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            `identifier` BINARY(32) NOT NULL,
            `member_id` VARCHAR(150) NOT NULL DEFAULT '' COLLATE 'utf8mb4_general_ci',
            `email` BINARY(32) NOT NULL,
            `cpf` BINARY(32) NULL DEFAULT NULL,
            `rg` BINARY(32) NULL DEFAULT NULL,
            `dados` BLOB NULL DEFAULT NULL,
            `usuario` BINARY(32) NULL DEFAULT NULL,
            `senha` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_bin',
            `status` ENUM('inativo','ativo','bloqueado') NOT NULL DEFAULT 'ativo' COLLATE 'utf8mb4_general_ci',
            `ultimo_login` DATETIME NULL DEFAULT NULL,
            `allow_access` INT(11) NOT NULL DEFAULT '1',
            `sign_hash` BINARY(32) NULL DEFAULT NULL,
            PRIMARY KEY (`id`) USING BTREE,
            UNIQUE INDEX `identifier` (`identifier`) USING BTREE,
            UNIQUE INDEX `email` (`email`) USING BTREE,
            UNIQUE INDEX `member_id` (`member_id`) USING BTREE,
            UNIQUE INDEX `cpf` (`cpf`) USING BTREE,
            UNIQUE INDEX `rg` (`rg`) USING BTREE,
            UNIQUE INDEX `usuario` (`usuario`) USING BTREE
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1
        ;
        ";

        $sqlTenant = "CREATE TABLE `tenant` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            `parent_id` INT(11) NOT NULL DEFAULT '0',
            `parent_type` ENUM(".((!empty($parentTypeEnum)) ? implode(", ", $parentTypeEnum) : "empresa, none").") NOT NULL DEFAULT 'none' COLLATE 'utf8mb4_general_ci',
            PRIMARY KEY (`id`) USING BTREE,
            INDEX `fk_key_userid` (`user_id`) USING BTREE,
            CONSTRAINT `fk_key_userid` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1
        ;
        ";

        $sqlLogs = "CREATE TABLE `logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
            `usuario` INT(11) NOT NULL DEFAULT '0',
            `file` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `acao` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `message` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `body` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `body_headers` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `error` LONGTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
            `response` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `method` LONGTEXT NOT NULL COLLATE 'utf8mb4_general_ci',
            `http_code` INT(11) NULL DEFAULT NULL,
            `status` INT(11) NOT NULL DEFAULT '0',
            `level` ENUM('critico','debug','info','aviso','erro','alerta') NULL DEFAULT 'info' COLLATE 'utf8mb4_general_ci',
            PRIMARY KEY (`id`) USING BTREE
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1
        ;
        ";

        $sqlAudit = "CREATE TABLE `logs_auditoria` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `created_at` DATETIME NULL DEFAULT current_timestamp(),
            `usuario` INT(11) NULL DEFAULT NULL,
            `event` LONGTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
            `status` INT(11) NULL DEFAULT NULL,
            `remote_address` BINARY(32) NULL DEFAULT NULL,
            `proxy_address` BINARY(32) NULL DEFAULT NULL,
            `shared_address` BINARY(32) NULL DEFAULT NULL,
            `remote_hash` BINARY(32) NULL DEFAULT NULL,
            `proxy_hash` BINARY(32) NULL DEFAULT NULL,
            `shared_hash` BINARY(32) NULL DEFAULT NULL,
            `parent` INT(11) NULL DEFAULT NULL,
            `level` ENUM('critico','debug','info','aviso','erro','alerta') NULL DEFAULT 'info' COLLATE 'utf8mb4_general_ci',
            PRIMARY KEY (`id`) USING BTREE
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1
        ;
        ";

        $sqlRoles1 = "CREATE TABLE `roles` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `empresa_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            `nome` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_general_ci',
            `is_default` TINYINT(1) NOT NULL DEFAULT '0',
            `is_system` TINYINT(1) NOT NULL DEFAULT '0',
            `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`) USING BTREE,
            UNIQUE INDEX `unq_empresa_role_nome` (`empresa_id`, `nome`) USING BTREE,
            INDEX `idx_roles_empresa` (`empresa_id`) USING BTREE,
            CONSTRAINT `fk_roles_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ;
        ";

        $sqlRoles2 = "CREATE TABLE `role_permissoes` (
            `role_id` BIGINT(20) UNSIGNED NOT NULL,
            `permissao_id` BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `permissao_id`) USING BTREE,
            INDEX `idx_role_permissoes_permissao` (`permissao_id`) USING BTREE,
            CONSTRAINT `fk_role_permissoes_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
            CONSTRAINT `fk_role_permissoes_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ;
        ";

        $sqlRoles3 = "CREATE TABLE `permissoes` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `chave` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_general_ci',
            `descricao` VARCHAR(250) NOT NULL COLLATE 'utf8mb4_general_ci',
            PRIMARY KEY (`id`) USING BTREE,
            UNIQUE INDEX `unq_permissao_chave` (`chave`) USING BTREE
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ;
        ";

        $sqlLogsAcesso = "CREATE TABLE `logs_acesso` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `created_at` DATETIME NULL DEFAULT current_timestamp(),
            `usuario` INT(11) NULL DEFAULT NULL,
            `event` LONGTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
            `status` INT(11) NULL DEFAULT NULL,
            `remote_address` BINARY(32) NULL DEFAULT NULL,
            `proxy_address` BINARY(32) NULL DEFAULT NULL,
            `shared_address` BINARY(32) NULL DEFAULT NULL,
            `remote_hash` BINARY(32) NULL DEFAULT NULL,
            `proxy_hash` BINARY(32) NULL DEFAULT NULL,
            `shared_hash` BINARY(32) NULL DEFAULT NULL,
            `parent` INT(11) NULL DEFAULT NULL,
            `level` ENUM('critico','debug','info','aviso','erro','alerta') NULL DEFAULT 'info' COLLATE 'utf8mb4_general_ci',
            PRIMARY KEY (`id`) USING BTREE
        )
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ;
        ";

        $firewall1 = "CREATE TABLE `firewall_attempts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ip_hash` VARCHAR(255) NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `attempted_at` DATETIME NOT NULL,
        INDEX `idx_ip_action_time` (`ip_hash`, `action`, `attempted_at`)
        );";

        $firewall2 = "CREATE TABLE `firewall_blacklist` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ip_hash` VARCHAR(255) NOT NULL UNIQUE,
        `reason` VARCHAR(255) DEFAULT NULL,
        `expires_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL
        );";

        Database::Transaction();
        $execUsuarios = Database::Query($sqlUsuarios);
        $execTenant = Database::Query($sqlTenant);
        $execLogs = Database::Query($sqlLogs);
        $execAudit = Database::Query($sqlAudit);
        $execRoles1 = Database::Query($sqlRoles1);
        $execRoles2 = Database::Query($sqlRoles2);
        $execRoles3 = Database::Query($sqlRoles3);
        $execLogsAcesso = Database::Query($sqlLogsAcesso);
        $execFirewall1 = Database::Query($firewall1);
        $execFirewall2 = Database::Query($firewall2);

        if (!$execUsuarios->valid() or !$execTenant->valid() or !$execLogs->valid() or !$execAudit->valid() or 
            !$execRoles1->valid() or !$execRoles2->valid() or !$execRoles3->valid() or !$execLogsAcesso->valid() or
            !$execFirewall1->valid() or !$execFirewall2->valid()){
            Database::Revert();
            $errors = [ "Usuarios" => $execUsuarios->error(), "Tenant" => $execTenant->error(), "Logs" => $execLogs->error(), "Audit" => $execAudit->error(), "Roles1" => $execRoles1->error(), "Roles2" => $execRoles2->error(), "Roles3" => $execRoles3->error(), "LogsAcesso" => $execLogsAcesso->error(), "Firewall1" => $execFirewall1->error(), "Firewall2" => $execFirewall2->error() ];
            throw new Exception("Erro ao inicializar estrutura do banco de dados! Data: ".json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        Database::Apply();
    }

    public static function Escape($val)
    {
        return self::$context ? substr(self::$context->quote($val), 1, -1) : $val;
    }

    public static function CreateContext($server, $user, $pass, $db, $port = 3306)
    {
        $dsn = "mysql:host={$server};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ];
        return new PDO($dsn, $user, $pass, $options);
    }

    public static function QueryCtx($ctx, $str, $params = [])
    {
        return self::Query($str, $params, $ctx);
    }

    public static function GetContext()
    {
        return self::$context;
    }

    public static function GetStatus() {
        return self::$context ? self::$context->getAttribute(PDO::ATTR_CONNECTION_STATUS) : false;
    }

    public static function Initialize($server, $user, $pass, $dbname)
    {
        self::$server = $server;
        self::$user = $user;
        self::$pass = $pass;
        self::$dbn = $dbname;

        self::$context = self::CreateContext($server, $user, $pass, $dbname);
        return self::$context;
    }

    public static function Query($str, $params = [], $context = null, $tryCatch = true, $bypass = false, $cacheTtl = 20) : QueryResult
    {
        $qr = new QueryResult([], $str);

        if (str_contains(strtolower($str), "update") or str_contains(strtolower($str), "delete")) {
            if (!str_contains(strtolower($str), "where") and !$bypass) {
                $qr->SetError("[ DB LOCK ] Uma operação de escrita sem condição não pode ser executada");
                return $qr;
            }
        }     

        if (empty($context)) $context = self::$context;

        if (empty($context)) {
            $qr->SetError("Invalid Database Context!");
            return $qr;
        }

        if ($tryCatch) {
            try {
                return self::Query($str, $params, $context, false, $bypass, $cacheTtl);
            }
            catch (Exception $ex) {
                $qr->SetError($ex->getMessage());
                if (isset($ex->errorInfo[1])) {
                    $qr->SetErrorCode($ex->errorInfo[1]);
                }
                return $qr;
            }
        }

        $cleanStr = preg_replace('/^\s*(?:--[^\r\n]*(?:\r?\n|$)|\/\*.*?\*\/\s*)+/s', '', $str);
        $isSelect = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|WITH)\b/i', $cleanStr);

        if (empty(self::$cache) and !self::$CacheUnavailable and !self::$onTransaction and $isSelect) {
            try {
                self::$cache = Cache::init(Driver::PREDIS);
            }
            catch(Throwable $ex) {
                self::$cache = null;
                self::$CacheUnavailable = true;
            }
        }

        if (self::$cache and self::$cache->getDriver()->connected() and $isSelect) {
            $hash = self::$cache->CacheKey($str, self::$dbn, "1", $params);
            $value = self::$cache->get($hash, true);

            if ($value !== null) {
                $qr = new QueryResult($value, $str);
                $qr->setRows(count($value));
                return $qr;
            }
        }

        $stmt = $context->prepare($str);

        if ($stmt === false) {
            $qr->SetError("Erro na preparação da consulta.");
            return $qr;
        }

        $idx = 1;
        foreach ($params as $param) {
            $val = $param instanceof Parameter ? $param->value : $param;
            if (is_array($val)) $val = json_encode($val);
            elseif (is_bool($val)) $val = $val ? 1 : 0;
            
            $type = PDO::PARAM_STR;
            if (is_int($val)) $type = PDO::PARAM_INT;
            elseif (is_null($val)) $type = PDO::PARAM_NULL;

            $stmt->bindValue($idx++, $val, $type);
        }

        $success = $stmt->execute();

        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            $qr->SetError("Erro na execução da consulta: " . ($errorInfo[2] ?? 'Erro Desconhecido'));
            $qr->SetErrorCode($errorInfo[1] ?? 0);
            return $qr;
        }

        if ($isSelect) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $qr = new QueryResult($rows, $str);

            if (self::$cache and self::$cache->getDriver()->connected() and !self::$onTransaction) {
                $hash = self::$cache->CacheKey($str, self::$dbn, "1", $params);
                self::$cache->set($hash, $rows, $cacheTtl, true);
            }
        } else {
            $qr = new QueryResult(true, $str);

            if (self::$cache and self::$cache->getDriver()->connected() and !self::$onTransaction) {
                self::invalidateTableCache($str);
            }
        }

        $qr->setRows($stmt->rowCount());
        return $qr;
    }

    private static function invalidateTableCache(string $sql): void
    {
        if (preg_match('/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?(\w+)`?/i', $sql, $matches)) {
            $table = strtolower($matches[1]);
            $pattern = "db:" . self::$dbn . ":{$table}:*";

            self::$cache->deleteByPattern($pattern);
        }
    }

    public static function Transaction($context = null) {
        if (!self::$onTransaction) {
            if (!$context) $context = self::$context;
            $context->beginTransaction();
            self::$onTransaction = true;
        }
    }

    public static function EndTransaction($context = null) {
        self::$onTransaction = false;
    }

    public static function Apply($context = null) {
        if (!$context) $context = self::$context;
        if (self::$onTransaction) $context->commit();
        self::EndTransaction($context);
    }

    public static function Revert($context = null) {
        if (!$context) $context = self::$context;
        if (self::$onTransaction) $context->rollBack();
        self::EndTransaction($context);
    }

    public static function GetLastInsertID($context = null)
    {
        if (!$context) $context = self::$context;
        return $context->lastInsertId();
    }

    public static function GetRowsChanged($context = null) {
        return 0; // Removido por desuso estrito, priorize $qr->rowsChanged()
    }

    public static function GetError($context = null)
    {
        if (!$context) $context = self::$context;
        return implode(" ", $context->errorInfo());
    }

    public static function Close() {
        self::$context = null; // A desconexão e liberação da pool é automática em destruição PDO
    }
}



?>
