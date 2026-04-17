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



class EnvData {
    public $DIR = "";
    public $LINK = "";
}





class Context {
    public $Valid = false;
    public $IdUsuario = null;
    public User|null $User = null;
    public $NodeServer = false;
    public $ApiRequest = false;
    public $ParentID = null;
    public $Bypassed = false;

    public function Validate($data) : bool {
        if (empty($this) or empty($data)) return false;

        if ($data instanceof User) {
            return IsEqual($data->ID, $this->IdUsuario);
        }
        else {
            return IsEqual($this->IdUsuario, $data);
        }

        return false;
    }
}


?>