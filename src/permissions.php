<?php

final class Permissions {
    private static $Perms = [];
    private static $Labels = [];

    /**
     * Registra um escopo de permissões na plataforma.
     * @param string $scope Ex: 'geral', 'financeiro'
     * @param array $permissions Array associativa [ 'slug' => 'Descrição' ]
     */
    public static function RegisterScope(string $scope, array $permissions) {
        if (!isset(self::$Perms[$scope])) {
            self::$Perms[$scope] = [];
            self::$Labels[$scope] = [];
        }

        foreach ($permissions as $slug => $label) {
            self::$Perms[$scope][$slug] = $slug;
            self::$Labels[$scope][$slug] = $label;
        }
    }

    public static function GetScopes() {
        return self::$Perms;
    }

    public static function GetLabels() {
        return self::$Labels;
    }

    public static function GetLabel($perm) {
        $result = $perm;
        foreach(self::$Labels as $perms) {
            if (isset($perms[$perm])) {
                $result = $perms[$perm];
                break;
            }
        }
        return $result;
    }

    public static function GetRoles(int $empresa = 0, int $id = 0) {
        $sql = Database::Query("SELECT * FROM roles WHERE (empresa_id <=> COALESCE(?, empresa_id) AND id <=> COALESCE(?, id)) OR is_system = 1", [
            ($empresa == 0 ? null : $empresa), ($id == 0 ? null : $id)
        ]);

        return $sql->isValid() ? $sql->get() : null;
    }

    public static function GetPermissions() {
        $sql = Database::Query("SELECT * FROM permissoes");

        return $sql->isValid() ? $sql->get() : null;
    }

    public static function GetRolePermissions(int $role = 0) {
        $sql = Database::Query("SELECT permissoes.* 
        FROM permissoes 
        INNER JOIN role_permissoes ON role_permissoes.permissao_id = permissoes.id
        WHERE role_permissoes.role_id <=> COALESCE(?, role_permissoes.role_id)", [
            ($role == 0 ? null : $role)
        ]);

        return $sql->isValid() ? $sql->get() : null;
    }

    public static function CreateRole($empresa_id, $nome, array $perms, $is_default = 0, $is_system = 0) {
        $sql = \Database::Query("SELECT * FROM roles WHERE nome = ? AND empresa_id <=> ?", [ $nome, $empresa_id ]);

        if ($sql->valid()) 
            return [ "Status" => false, "Mensagem" => "A Role ".$nome." já existe para esta empresa! Por favor, escolha outro nome!", "Erro" => "" ];

        $sql = \Database::Query("INSERT INTO roles (empresa_id, nome, is_default, is_system) VALUES(?,?,?,?)", [
            $empresa_id, $nome, $is_default, $is_system
        ]);

        if ($sql->valid()) {
            $role_id = \Database::GetLastInsertID();
            self::SyncRolePermissions($role_id, $perms);
            return [ "Status" => true, "ID" => $role_id ];
        }

        return [ "Status" => false, "Mensagem" => "Ocorreu um erro ao criar a role!", "Erro" => $sql->error() ];
    }

    public static function UpdateRole($id, $empresa_id, $nome, array $perms, $is_default = 0, $is_system = 0) {
        $sql = \Database::Query("SELECT * FROM roles WHERE nome = ? AND empresa_id <=> ? AND id != ?", [ $nome, $empresa_id, $id ]);

        if ($sql->valid()) 
            return [ "Status" => false, "Mensagem" => "A Role ".$nome." já existe para esta empresa! Por favor, escolha outro nome!", "Erro" => "" ];

        $sql = \Database::Query("UPDATE roles SET nome = ?, is_default = ?, is_system = ? WHERE id = ? AND empresa_id <=> ?", [
            $nome, $is_default, $is_system, $id, $empresa_id
        ]);

        if ($sql->valid()) {
            self::SyncRolePermissions($id, $perms);
            return [ "Status" => true, "ID" => $id ];
        }

        return [ "Status" => false, "Mensagem" => "Ocorreu um erro ao salvar a role!", "Erro" => $sql->error() ];
    }

    private static function SyncRolePermissions($role_id, array $perms) {
        // Limpa permissões antigas da role
        \Database::Query("DELETE FROM role_permissoes WHERE role_id = ?", [$role_id]);
        
        foreach ($perms as $chave => $hasPerm) {
            // Suporta array ['chave' => true] ou lista simples ['chave1', 'chave2']
            if (is_int($chave)) {
                $chave = $hasPerm;
                $hasPerm = true;
            }
            
            if ($hasPerm) {
                // Busca ID da permissão
                $sql = \Database::Query("SELECT id FROM permissoes WHERE chave = ?", [$chave]);
                
                if ($sql->valid()) {
                    $permissao_id = $sql->get()[0]['id'];
                } else {
                    // Cria a permissão se ela não existir no banco
                    $label = self::GetLabel($chave);
                    \Database::Query("INSERT INTO permissoes (chave, descricao) VALUES (?, ?)", [$chave, $label]);
                    $permissao_id = \Database::GetLastInsertID();
                }
                
                // Associa a permissão à role
                \Database::Query("INSERT INTO role_permissoes (role_id, permissao_id) VALUES (?, ?)", [$role_id, $permissao_id]);
            }
        }
    }

    public static function HasPermission($usuario_id, $chave, $empresa_id = null) {
        $sqlStr = "SELECT DISTINCT r.id 
                   FROM empresa_usuarios eu
                   INNER JOIN roles r ON r.id = eu.role_id
                   INNER JOIN role_permissoes rp ON rp.role_id = r.id
                   INNER JOIN permissoes p ON p.id = rp.permissao_id
                   WHERE eu.usuario_id = ? AND eu.status = 'ativo' AND p.chave = ?";
                   
        $params = [$usuario_id, $chave];
        
        if ($empresa_id !== null) {
            $sqlStr .= " AND eu.empresa_id = ?";
            $params[] = $empresa_id;
        }
        
        $sql = \Database::Query($sqlStr, $params);
        return $sql->valid();
    }
}

?>