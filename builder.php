<?php

/* DEFAULT FUNCTIONS TO BUILDER CLASS
public function ToJson($camelSnake = false) {
    $data = [];
    
    $reflection = new ReflectionClass($this);
    
    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
    
    foreach ($properties as $property) {
        $property->setAccessible(true);
        $propertyName = $property->getName();
        $propertyValue = $property->getValue($this);

        $snakeCase = function($input) {
            // Encontra todas as letras maiúsculas e adiciona um '_' antes delas
            $snakeCase = preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $input);
            // Remove o underscore inicial se ele existir e converte para minúsculo
            $snakeCase = ltrim($snakeCase, '_');
            
            return strtolower($snakeCase);
        };

        $data[($camelSnake ? $snakeCase($propertyName) : $propertyName)] = $propertyValue;
    }
    
    return json_encode($data);
}

public function ToArray($camel = false) {
    return json_decode($this->ToJson($camel), true);
}

public function Validate() {
    if (empty($this->Nome) or
        empty($this->CPF) or
        empty($this->ID)) {
            return false;
    }

    return true;
}

public static function Build($arr) : self {

    if (empty($arr)) return new self();

    $data = new self();
    $normalizedArr = [];

    if (isset($arr['telefone'])) {
        $arr['telefone1'] = $arr['telefone'];
        $arr['telefone'] = null;
    }

    foreach ($arr as $key => $value) {
        $cleanKey = str_replace(['_','-'], ['',''], $key);
        $normalizedArr[strtolower($cleanKey)] = $value;
    }
    
    $reflection = new ReflectionClass($data);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    
    foreach ($properties as $property) {
        $propertyName = $property->getName();
        $property->setAccessible(true);
        $defaultValue = $property->getDefaultValue();

        if (is_array($defaultValue)) {
            // É o array Endereco
            $nestedArray = [];
            $normalizedPropName = strtolower($propertyName);

            // Prioriza a entrada aninhada
            if (isset($normalizedArr[$normalizedPropName]) && is_array($normalizedArr[$normalizedPropName])) {
                $nestedArray = $normalizedArr[$normalizedPropName];
            } else {
                // Trata a entrada "achatada"
                foreach ($defaultValue as $nestedKey => $nestedValue) {
                    $normalizedNestedKey = strtolower($nestedKey);
                    if (isset($normalizedArr[$normalizedNestedKey])) {
                        $nestedArray[$nestedKey] = $normalizedArr[$normalizedNestedKey];
                    } else {
                        $nestedArray[$nestedKey] = $nestedValue;
                    }
                }
            }
            $property->setValue($data, $nestedArray);

        } else { 
            // É um campo simples
            if (isset($normalizedArr[strtolower($propertyName)])) {
                $property->setValue($data, $normalizedArr[strtolower($propertyName)]);
            }
        }
    }
    
    return $data;
}
*/

class User {
    public string $DisplayName = "";
    public string $MemberID = "";
    public string $Nome = "";
    public string $Titulo = "";
    public string $CPF = "";
    public string $CNPJ = "";
    public string $Email = "";
    public string $DataNascimento = "";
    public string $Telefone1 = "";
    public string $Telefone2 = "";
    public string $Imagem = "";
    public string $BannerImagem = "";
    public string $Sexo = "";
    public string $RG = "";
    public string $AboutMe = "";
    public $Endereco = [
        "CEP" => "",
        "Estado" => "",
        "Cidade" => "",
        "Endereco" => "",
        "Numero" => "",
        "Complemento" => "",
        "Bairro" => ""
    ];

    //System Data
    public int $ID = 0;
    public string $Identifier = "";
    public string $Usuario = "";
    public int $ParentID = 0;

    public function ToJson($camelSnake = false) {
        $data = [];
        
        $reflection = new ReflectionClass($this);
        
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $propertyName = $property->getName();
            $propertyValue = $property->getValue($this);

            $snakeCase = function($input) {
                // Encontra todas as letras maiúsculas e adiciona um '_' antes delas
                $snakeCase = preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $input);
                // Remove o underscore inicial se ele existir e converte para minúsculo
                $snakeCase = ltrim($snakeCase, '_');
                
                return strtolower($snakeCase);
            };

            $data[($camelSnake ? $snakeCase($propertyName) : $propertyName)] = $propertyValue;
        }
        
        return json_encode($data);
    }

    public function ToArray($camel = false) {
        return json_decode($this->ToJson($camel), true);
    }

    public static function Build($arr) : self {

        if (empty($arr)) return new self();

        $data = new self();
        $normalizedArr = [];

        if (isset($arr['telefone'])) {
            $arr['telefone1'] = $arr['telefone'];
            $arr['telefone'] = null;
        }

        foreach ($arr as $key => $value) {
            $cleanKey = str_replace(['_','-'], ['',''], $key);
            $normalizedArr[strtolower($cleanKey)] = $value;
        }
        
        $reflection = new ReflectionClass($data);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $property->setAccessible(true);
            $defaultValue = $property->getDefaultValue();

            if (is_array($defaultValue)) {
                // É o array Endereco
                $nestedArray = [];
                $normalizedPropName = strtolower($propertyName);

                // Prioriza a entrada aninhada
                if (isset($normalizedArr[$normalizedPropName]) && is_array($normalizedArr[$normalizedPropName])) {
                    $nestedArray = $normalizedArr[$normalizedPropName];
                } else {
                    // Trata a entrada "achatada"
                    foreach ($defaultValue as $nestedKey => $nestedValue) {
                        $normalizedNestedKey = strtolower($nestedKey);
                        if (isset($normalizedArr[$normalizedNestedKey])) {
                            $nestedArray[$nestedKey] = $normalizedArr[$normalizedNestedKey];
                        } else {
                            $nestedArray[$nestedKey] = $nestedValue;
                        }
                    }
                }
                $property->setValue($data, $nestedArray);

            } else { 
                // É um campo simples
                if (isset($normalizedArr[strtolower($propertyName)])) {
                    $property->setValue($data, $normalizedArr[strtolower($propertyName)]);
                }
            }
        }
        
        return $data;
    }
}

class Endereco {
    public $ID = "";
    public $CEP = "";
    public $Estado = "";
    public $Cidade = "";
    public $Endereco = "";
    public $Numero = "";
    public $Complemento = "";
    public $Bairro = "";
}

final class Profissao
{
    public const CUIDADOR = "Cuidador";
    public const ENFERMEIRO = "Enfermeiro";
    public const MEDICO = "Médico";
    public const FISIOTERAPEUTA = "Fisioterapeuta";
    public const NUTRICIONISTA = "Nutricionista";
    public const TECNICO_ENFERMAGEM = "Técnico de Enfermagem";
    public const ASSISTENTE_SOCIAL = "Assistente Social";
    public const AUXILIAR_ADMINISTRATIVO = "Auxiliar Administrativo";
    public const AUXILIAR_COZINHA = "Auxiliar de Cozinha";
    public const AUXILIAR_SERVICOS_GERAIS = "Auxiliar de Serviços Gerais";
    public const AUXILIAR_ENFERMAGEM = "Auxiliar de Enfermagem";

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class Pessoa {
    public const JURIDICA = 1;
    public const FISICA = 0;
    public const UNDEFINED = null;
}

final class Context {
    public const MEMBER = "member";
    public const BUSINESS = "business";
}

class CadEmpresa {
    public $IdUsuario = "";
    public $Identifier = "";
    public $IdEmpresa = "";
    public $Cargo = "";
    public $NivelAcesso = "";
    public $Permissoes = [];

    public function ToJson($camelSnake = false) {
        $data = [];
        
        $reflection = new ReflectionClass($this);
        
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $propertyName = $property->getName();
            $propertyValue = $property->getValue($this);

            $snakeCase = function($input) {
                // Encontra todas as letras maiúsculas e adiciona um '_' antes delas
                $snakeCase = preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $input);
                // Remove o underscore inicial se ele existir e converte para minúsculo
                $snakeCase = ltrim($snakeCase, '_');
                
                return strtolower($snakeCase);
            };

            $data[($camelSnake ? $snakeCase($propertyName) : $propertyName)] = $propertyValue;
        }
        
        return json_encode($data);
    }

    public function ToArray($camel = false) {
        return json_decode($this->ToJson($camel), true);
    }

    public static function Build($arr) : self {

        if (empty($arr)) return new self();

        $data = new self();
        $normalizedArr = [];

        if (isset($arr['telefone'])) {
            $arr['telefone1'] = $arr['telefone'];
            $arr['telefone'] = null;
        }

        foreach ($arr as $key => $value) {
            $cleanKey = str_replace(['_','-'], ['',''], $key);
            $normalizedArr[strtolower($cleanKey)] = $value;
        }
        
        $reflection = new ReflectionClass($data);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $property->setAccessible(true);
            $defaultValue = $property->getDefaultValue();

            if (is_array($defaultValue)) {
                // É o array Endereco
                $nestedArray = [];
                $normalizedPropName = strtolower($propertyName);

                // Prioriza a entrada aninhada
                if (isset($normalizedArr[$normalizedPropName]) && is_array($normalizedArr[$normalizedPropName])) {
                    $nestedArray = $normalizedArr[$normalizedPropName];
                } else {
                    // Trata a entrada "achatada"
                    foreach ($defaultValue as $nestedKey => $nestedValue) {
                        $normalizedNestedKey = strtolower($nestedKey);
                        if (isset($normalizedArr[$normalizedNestedKey])) {
                            $nestedArray[$nestedKey] = $normalizedArr[$normalizedNestedKey];
                        } else {
                            $nestedArray[$nestedKey] = $nestedValue;
                        }
                    }
                }
                $property->setValue($data, $nestedArray);

            } else { 
                // É um campo simples
                if (isset($normalizedArr[strtolower($propertyName)])) {
                    $property->setValue($data, $normalizedArr[strtolower($propertyName)]);
                }
            }
        }
        
        return $data;
    }
}

class Cliente {
    public $ID = 0;
    public $Identifier = "";
    public $Descricao = "";

    public int|null $TipoPessoa = Pessoa::UNDEFINED;
    
    public $CNPJ = "";
    public $RazaoSocial = "";
    public $InscricaoMunicipal = "";
    public $InscricaoEstadual = "";

    public $Responsavel = [
        "Nome" => "",
        "CPF" => "",
        "Funcao" => ""
    ];

    public $Contato = [
        "Nome" => "",
        "Funcao" => "",
        "Telefone" => "",
        "Email" => ""
    ];

    public $Nome = "";
    public $CPF = "";
    public $RG = "";
    public $DataNascimento = "";
    // public $Sexo = "";
    public $CNES = "";

    public $Financeiro = [
        "ValorCobranca" => 0.0,
        "DataIngresso" => '0000-00-00'
    ];

    public $Endereco = [
        "CEP" => "",
        "Estado" => "",
        "Cidade" => "",
        "Endereco" => "",
        "Numero" => "",
        "Complemento" => "",
        "Bairro" => ""
    ];

    public $Status = 0;

    public function IsValid() {
        return $this->ID == 0 ? false : true;
    }

    public function ToJson($camelSnake = false) {
        $data = [];
        
        $reflection = new ReflectionClass($this);
        
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $propertyName = $property->getName();
            $propertyValue = $property->getValue($this);

            $snakeCase = function($input) {
                // Encontra todas as letras maiúsculas e adiciona um '_' antes delas
                $snakeCase = preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $input);
                // Remove o underscore inicial se ele existir e converte para minúsculo
                $snakeCase = ltrim($snakeCase, '_');
                
                return strtolower($snakeCase);
            };

            $data[($camelSnake ? $snakeCase($propertyName) : $propertyName)] = $propertyValue;
        }
        
        return json_encode($data);
    }

    public function ToArray($camel = false) {
        return json_decode($this->ToJson($camel), true);
    }

    public static function Build(array $arr) : self {
        $data = new self();
        $normalizedArr = [];

        if (empty($arr)) {
            return $data;
        }

        foreach ($arr as $key => $value) {
            $cleanKey = str_replace('_', '', $key);
            $normalizedArr[strtolower($cleanKey)] = $value;
        }

        if (isset($arr['cpf']) or isset($arr['CPF'])) {
            $data->TipoPessoa = Pessoa::FISICA;
        }
        else if (isset($arr['cnpj']) or isset($arr['CNPJ'])) {
            $data->TipoPessoa = Pessoa::JURIDICA;
        }
        else {
            $data->TipoPessoa = Pessoa::UNDEFINED;
        }
        
        $reflection = new ReflectionClass($data);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $property->setAccessible(true);
            
            $defaultValue = $property->getDefaultValue();

            if (is_array($defaultValue)) {
                $nestedArray = [];
                $normalizedPropName = strtolower($propertyName);

                // Prioriza a entrada aninhada (se a array de entrada já estiver no formato correto)
                if (isset($normalizedArr[$normalizedPropName]) && is_array($normalizedArr[$normalizedPropName])) {
                    $nestedArray = $normalizedArr[$normalizedPropName];
                } else {
                    // Trata a entrada "achatada" com prefixos
                    foreach ($defaultValue as $nestedKey => $nestedValue) {
                        $normalizedNestedKey = strtolower($nestedKey);
                        // A chave esperada no array de entrada é "propriedade-chave"
                        $prefixedKey = $normalizedPropName . '-' . $normalizedNestedKey;
                        
                        if (isset($normalizedArr[$prefixedKey])) {
                            $nestedArray[$nestedKey] = $normalizedArr[$prefixedKey];
                        } else {
                            // Se não encontrar o prefixo, procura pela chave "achatada" sem prefixo
                            if (isset($normalizedArr[$normalizedNestedKey])) {
                                $nestedArray[$nestedKey] = $normalizedArr[$normalizedNestedKey];
                            } else {
                                $nestedArray[$nestedKey] = $nestedValue;
                            }
                        }
                    }
                }
                $property->setValue($data, $nestedArray);

            } else { // É um campo simples
                if (isset($normalizedArr[strtolower($propertyName)])) {
                    $property->setValue($data, $normalizedArr[strtolower($propertyName)]);
                }
            }
        }
        
        return $data;
    }

    protected function copyFrom(self $from) : void {
        if (!($this instanceof self)) {
            throw new InvalidArgumentException("Copy requer objeto da mesma classe");
        }

        $reflection = new ReflectionObject($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();
            
            if (strtolower($name) == "id" or strtolower($name) == "identifier" or
                strtolower($name) == "status" or strtolower($name) == "tenanttype" or
                strtolower($name) == "parentid" or strtolower($name) == "isvalid")
                continue;

            $this->$name = $from->$name;
        }
    }

    public function Copy(Cliente $from) {
        $id = $this->ID;
        $identifier = $this->Identifier;

        $this->copyFrom($from);

        $this->ID = $id;
        $this->Identifier = $identifier;
    }
}

class EnvData {
    public $DIR = "";
    public $LINK = "";
}

class TokenEnv {
    public const USUARIO = 'usuario';
    public const EMPRESA = 'empresa';
    public const ADMIN = 'admin';
}

class Payload {
    public string $Nome = "";
    public string $MimeType = "";
    public string $TempPath = "";
    public int $Error = 0;
    public int $Size = 0;
}

class Payloads implements IteratorAggregate
{
    private array $items = [];

    public function Size() {
        return count($this->items);
    }

    public function Add(Payload $payload): void
    {
        $this->items[] = $payload;
    }

    public function Get(int|string $id): ?Payload
    {
        return $this->items[$id] ?? null;
    }

    public function Iterate(callable $cb): void
    {
        foreach ($this->items as $payload) {
            $cb($payload);
        }
    }

    public function GetAll() {
        return $this->items;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

final class PayloadRegistry
{
    private static array $used = [];

    public static function add(string $payloadId): void
    {
        self::$used[] = basename($payloadId);
    }

    public static function all(): array
    {
        return array_unique(self::$used);
    }
}

final class FileIntent
{
    public Payload $file;
    public string $finalDir;
    public string $finalName;

    public function finalPath(): string
    {
        return rtrim($this->finalDir, '/') . '/' . $this->finalName;
    }
}

final class FileTransactionManager
{
    private static array $intents = [];
    private static array $applied = [];

    public static function save(Payload $file, string $finalDir): ?string
    {
        if (!file_exists($file->TempPath)) {
            return null;
        }

        $info = pathinfo($file->Nome);
        $ext  = $info['extension'] ?? 'bin';

        $permitidos = [ 
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', // Imagens
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'txt'
        ];

        if (!in_array($ext, $permitidos)) {
            return null;
        }

        $intent = new FileIntent();
        $intent->file      = $file;
        $intent->finalDir  = rtrim($finalDir, '/');
        $intent->finalName = HashStr() . '.' . $ext;

        self::$intents[] = $intent;

        return $intent->finalName;
    }

    public static function apply(): void
    {
        foreach (self::$intents as $intent) {

            if (!($intent instanceof FileIntent)) continue;

            if (!is_dir($intent->finalDir)) {
                mkdir($intent->finalDir, 0777, true);
            }

            $permitidos = [ 
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', // Imagens
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'txt'
            ];

            $info = pathinfo($intent->finalName);
            $ext  = $info['extension'] ?? 'bin';

            if (!in_array($ext, $permitidos)) {
                continue;
            }

            if (!rename($intent->file->TempPath, $intent->finalPath())) {
                throw new Exception("Failed to move file: {$intent->finalPath()}");
            }

            self::$applied[] = $intent;
        }
    }

    public static function rollback($all = false): void
    {
        // remove staging não aplicado
        foreach (self::$intents as $intent) {
            if (file_exists($intent->file->TempPath)) {
                @unlink($intent->file->TempPath);
            }
        }

        // opcional: remove arquivos já aplicados
        if ($all) {
            foreach (self::$applied as $intent) {
                if (file_exists($intent->finalPath())) {
                    @unlink($intent->finalPath());
                }
            }
        }
    }
}

class Tenant {
    public $Valid = false;
    public $IdUsuario = null;
    public User|null $User = null;
    public $NodeServer = false;
    public $ApiRequest = false;
    public $ParentID = null;

    public function Validate($data) : bool {
        if (empty($this) or empty($data)) return false;

        if ($data instanceof User) {
            return IsEqual($data->ID, self::$IdUsuario);
        }
        else {
            return IsEqual(self::$IdUsuario, $data);
        }

        return false;
    }
}


?>