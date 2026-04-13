<?php

final class Permissions {
    public static $Perms = [
        'geral' => [
            'profile-change' => "profile-change",
            'address-change' => "address-change",
            'notify-view' => "notify-view"
        ],
        'equipe' => [
            'user-invite' => "user-invite",
            'user-change' => "user-change",
            'group-change' => "group-change"
        ],
        'financeiro' => [
            'view-faturas' => "view-faturas",
            'view-extratos' => "view-extratos",
            'view-indicadores' => "view-indicadores",
            'change-assinatura' => "change-assinatura",
            'contratar-produtos' => "contratar-produtos"
        ],
        'seguranca' => [
            'manage-members' => "manage-members",
            'view-acessos' => "view-acessos",
            'view-auditoria' => "view-auditoria",
            'manage-grupos' => "manage-grupos"
        ]
    ];

    public static $Array = [
        'geral' => [
            'profile-change' => "Alterar dados da empresa",
            'address-change' => "Alterar endereço da empresa",
            'notify-view' => "Visualizar e interagir com notificações"
        ],
        'equipe' => [
            'user-invite' => "Convidar usuários para a empresa",
            'user-change' => "Alterar dados de usuário",
            'group-change' => "Alterar grupos e cargos do usuário"
        ],
        'financeiro' => [
            'view-faturas' => "Visualizar faturas",
            'view-extratos' => "Visualizar extrato",
            'view-indicadores' => "Visualizar indicadores",
            'change-assinatura' => "Alterar assinaturas",
            'contratar-produtos' => "Contratar novos produtos"
        ],
        'seguranca' => [
            'manage-members' => "Gerenciar membros",
            'view-acessos' => "Visualizar registro de acessos",
            'view-auditoria' => "Visualizar registro de auditoria",
            'manage-grupos' => "Gerenciar grupos e cargos"
        ]
    ];

    public static function GetLabel($perm) {
        $result = "";
        foreach(self::$Array as $perms) {
            if (isset($perms[$perm])) {
                $result = $perms[$perm];
                break;
            }
        }
        return $result;
    }

    public static function CreateGroup($name, $desc, array $perms, $parent) {
        $sql = Database::Query("SELECT * FROM grupos WHERE nome = ?", [ $name ]);

        if ($sql->validQuery()) 
            return [ "Status" => false, "Mensagem" => "O Grupo ".$name." já existe! Por favor, escolha outro nome!", "Erro" => "" ];

        $reference = HashStr();

        $sql = Database::Query("INSERT INTO grupos (parent, reference, nome, descricao, permissions) VALUES(?,?,?,?,?)", [
            $parent, $reference, $name, $desc, Encrypt(json_encode($perms))
        ]);

        if ($sql->validExecute()) {
            return [ "Status" => true, "ID" => Database::GetLastInsertID() ];
        }

        return [ "Status" => false, "Mensagem" => "Ocorreu um erro ao criar grupo!", "Erro" => $sql->error() ];
    }

    public static function UpdateGroup($name, $desc, array $perms, $id) {
        $sql = Database::Query("SELECT * FROM grupos WHERE nome = ? AND id != ?", [ $name, $id ]);

        if ($sql->validQuery()) 
            return [ "Status" => false, "Mensagem" => "O Grupo ".$name." já existe! Por favor, escolha outro nome!", "Erro" => "" ];

        $reference = HashStr();

        $sql = Database::Query("UPDATE grupos SET nome = ?, descricao = ?, permissions = ? WHERE id = ?", [
            $name, $desc, Encrypt(json_encode($perms)), $id
        ]);

        if ($sql->validExecute()) {
            return [ "Status" => true, "ID" => Database::GetLastInsertID() ];
        }

        return [ "Status" => false, "Mensagem" => "Ocorreu um erro ao salvar grupo!", "Erro" => $sql->error() ];
    }

    public static function HasPermission($user, $perm) {
        $sql = Database::Query("SELECT DISTINCT
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
                $perms = Decrypt($perms);
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