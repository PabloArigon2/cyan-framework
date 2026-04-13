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

    public static function CreateGroup($name, $desc, array $perms, $parent) {
        $sql = \Database::Query("SELECT * FROM grupos WHERE nome = ?", [ $name ]);

        if ($sql->validQuery()) 
            return [ "Status" => false, "Mensagem" => "O Grupo ".$name." já existe! Por favor, escolha outro nome!", "Erro" => "" ];

        $reference = HashStr();

        $sql = \Database::Query("INSERT INTO grupos (parent, reference, nome, descricao, permissions) VALUES(?,?,?,?,?)", [
            $parent, $reference, $name, $desc, \Encrypt(json_encode($perms))
        ]);

        if ($sql->validExecute()) {
            return [ "Status" => true, "ID" => \Database::GetLastInsertID() ];
        }

        return [ "Status" => false, "Mensagem" => "Ocorreu um erro ao criar grupo!", "Erro" => $sql->error() ];
    }

    public static function UpdateGroup($name, $desc, array $perms, $id) {
        $sql = \Database::Query("SELECT * FROM grupos WHERE nome = ? AND id != ?", [ $name, $id ]);

        if ($sql->validQuery()) 
            return [ "Status" => false, "Mensagem" => "O Grupo ".$name." já existe! Por favor, escolha outro nome!", "Erro" => "" ];

        $reference = HashStr();

        $sql = \Database::Query("UPDATE grupos SET nome = ?, descricao = ?, permissions = ? WHERE id = ?", [
            $name, $desc, \Encrypt(json_encode($perms)), $id
        ]);

        if ($sql->validExecute()) {
            return [ "Status" => true, "ID" => \Database::GetLastInsertID() ];
        }

        return [ "Status" => false, "Mensagem" => "Ocorreu um erro ao salvar grupo!", "Erro" => $sql->error() ];
    }

    public static function HasPermission($user, $perm) {
        $sql = \Database::Query("SELECT DISTINCT
        grupos.* 
        FROM grupos_usuario 
        INNER JOIN grupos ON grupos.id = grupos_usuario.grupo
        WHERE grupos_usuario.usuario = ? AND grupos_usuario.status = 1", [
            $user
        ]);

        if ($sql->validQuery()) {
            $hasperm = false;

            foreach($sql->get() as $row) {
                $perms = $row['permissions'];
                $perms = \Security::Decrypt($perms);
                $perms = json_decode($perms, true);

                foreach($perms as $key => $prm) {
                    if ($key == $perm and $prm == true) {
                        $hasperm = true;
                        break;
                    }
                }

                if ($hasperm)
                    break;
            }

            return $hasperm;
        }

        return false;
    }
}

?>