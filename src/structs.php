<?php

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

final class ContextScope {
    public const MEMBER = "member";
    public const BUSINESS = "business";
}

final class Pessoa {
    public const JURIDICA = 1;
    public const FISICA = 0;
    public const UNDEFINED = null;
}

final class TokenEnv {
    public const USUARIO = 'usuario';
    public const TENANT = 'tenant';
    public const ADMIN = 'admin';
}

final class UserType {
    public const ADMIN = 0;
    public const CLIENT_USER = 1; // Substitui escopos customizados como paciente/unidade
    public const TENANT_USER = 2; // Profissional ou funcionário
}

class JSON {
    public static function Encode($val) {
        return json_encode($val);
    }

    public static function Decode($val) {
        return json_decode($val, true, 512, JSON_UNESCAPED_UNICODE);
    }
}

?>