<?php

final class Auth {
    public static function AuthenticateRequest() {
        $headers = getallheaders();
        $validatedSession = false;
        $validatedToken = false;
        $tenant = null;
        $envUse = "";

        if (empty($headers['Authorization'])) {
            $user = Auth::CurrentUser();

            if (!$user or empty($user))
                return [ "Auth" => false, "Tenant" => null, "Environment" => $envUse ];

            $validatedSession = true;

            $tenant = new Context();
            $tenant->ApiRequest = false;
            $tenant->IdUsuario = $user->ID ?? 0;
            $tenant->NodeServer = false;
            $tenant->Valid = true;
            $tenant->User = $user;
            $tenant->IsAdmin = Security::IsAdmin($user->ID, $user->Email, $user->Signature);

            $envUse = "user_tenant";
        }
        else {
            $auth = $headers['Authorization'];
            $sql = Database::Query("SELECT * FROM env_keys WHERE env_key = ?", [ $auth ]);

            if ($sql->valid()) {
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

            $envUse = "api_token";
        }

        if ((empty($tenant) or !$tenant->Valid) && !$validatedToken) {
            $validatedSession = false;
        }

        return [ "Auth" => ($validatedSession || $validatedToken), "Tenant" => $tenant, "Environment" => $envUse ];
    }

    public static function GetTenantLinked(int $usuario) {
        if (empty($usuario)) return [];
        $tenants = [];

        $sql = Database::Query("SELECT id FROM empresas WHERE owner_id = ?", [ $usuario ]);
        
        if ($sql->valid()) {
            foreach($sql->get() as $row) {
                $tenants[] = intval($row['id']);
            }
        }

        $sql = Database::Query("SELECT empresa_id FROM empresa_usuarios WHERE usuario_id = ?", [ $usuario ]);

        if ($sql->valid()) {
            foreach($sql->get() as $row) {
                $tenants[] = intval($row['empresa_id']);
            }
        }

        return $tenants;
    }

    public static function CurrentUser() : User|null {
        try {
            $dataset = Session::get("_DATASET");

            if (empty($dataset) or !isset($dataset['id']))
                return new User();
            
            return Auth::GetUserData($dataset['id']) ?? new User();
        }
        catch (Throwable $ex) {
            return null;
        }
    }

    public static function GetUser($id = null) {
        if (empty($id)) return Auth::CurrentUser();

        $sql = Database::Query("SELECT id FROM usuarios WHERE id = ?", [ $id ]);

        if ($sql->valid()) {
            return Auth::GetUserData($sql->field(0, "id"));
        }

        return null;
    }

    public static function GetUserData(int|null $id_usuario = null) : User|null {
        $result = new User();

        if (empty($id_usuario)) return $result;

        $sql = Database::Query("SELECT dados, identifier, id as id_usuario, status, sign_hash FROM usuarios WHERE id = ?", [ $id_usuario ]);

        if ($sql->valid()) {
            $token = Security::Token($sql->field(0, "id_usuario"), $sql->field(0, "identifier"), TokenEnv::USUARIO);
            $data = Security::Decrypt($sql->field(0, "dados"), $token);
            $data = json_decode($data, true);
            $result = User::Build($data);
            $result->Identifier = $sql->field(0, "identifier");
            $result->ID = $sql->field(0, "id_usuario");
            $result->Signature = $sql->field(0, 'sign_hash');
        }

        return $result;
    }

    public static function ProcessUserData(array|null $row = null, bool $getSignature = false) : User|null {
        $result = new User();

        if (!empty($row) and !empty($row['dados'])) {
            $token = Security::Token($row['id'], $row['identifier'], TokenEnv::USUARIO);
            $data = Security::Decrypt($row['dados'], $token);
            $data = json_decode($data, true);
            $result = User::Build($data);
            $result->Identifier = $row['identifier'];
            $result->ID = $row['id'];
            $result->ParentID = $row['parent_id'] ?? 0;
            $result->Signature = $row['sign_hash'] ?? ($getSignature ? self::GetUserSignature($row['id']) : null);
            return $result;
        }

        return $result;
    }

    public static function GetUserSignature(int|null $usuario) : string|null {
        $sql = Database::Query("SELECT sign_hash FROM usuarios WHERE id = ?", [ $usuario ]);

        if ($sql->valid()) {
            return $sql->field(0, 'sign_hash');
        }

        return null;
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

        if (empty($id_tenant) and empty($identifier)) return $result;

        $sql = Database::Query("SELECT dados, identifier, id, status FROM tenants 
            WHERE tenants.id <=> COALESCE(?, tenants.id) AND tenants.identifier <=> COALESCE(?, tenants.identifier)", [ $id_tenant, $identifier ]);

        if ($sql->valid()) {
            $token = Security::Token($sql->field(0, "id"), $sql->field(0, "identifier"), TokenEnv::TENANT);
            $data = Security::Decrypt($sql->field(0, "dados"), $token);
            $result = json_decode($data, true) ?? [];
            $result['Identifier'] = $sql->field(0, "identifier");
            $result['ID'] = $sql->field(0, "id");
        }

        return $result;
    }

    public static function GetTenantRegistry($id_usuario) {
        $data = Database::Query("SELECT dados_usuarios.id_tenant, tenants.numero_registro FROM dados_usuarios
        LEFT JOIN tenants ON tenants.id = dados_usuarios.id_tenant WHERE dados_usuarios.id_usuario = ?", [ $id_usuario ]);

        if ($data->isValid() and $data->length() > 0) {
            return $data->field(0, "numero_registro");
        }
        return null;
    }

    public static function GetTenantID($registro) {
        $data = Database::Query("SELECT id FROM tenants WHERE numero_registro = ?", [ $registro ]);
        return ($data->isValid() and $data->length() > 0) ? $data->field(0, "id") : null;
    }

    public static function ValidateUserTenant($id_usuario, $id_tenant) {
        $data = Database::Query("SELECT id FROM tenants WHERE numero_registro = ?", [ $id_tenant ]);
        if ($data->isValid() and $data->length() > 0) { $id_tenant = intval($data->field(0, "id")); }

        $data = Database::Query("SELECT id_tenant FROM dados_usuarios WHERE id_usuario = ?", [ $id_usuario ]);
        if ($data->isValid() and $data->length() > 0) {
            $id_tenant_us = $data->field(0, "id_tenant");
            return (gettype($id_tenant) == "integer" and $id_tenant == intval($id_tenant_us));
        }
        return false;
    }

    public static function GetDeviceId() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $ipAddress = Http::GetUserIP();
        return md5($userAgent . $acceptLanguage . $ipAddress);
    }
}
