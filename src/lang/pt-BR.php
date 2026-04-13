<?php

return [
    'http' => [
        'errors' => [
            'unauthorized'  => 'Não autorizado.',
            'forbidden'     => 'Acesso negado.',
            'notfound'      => 'Recurso não encontrado.',
            'bad_request'   => 'Requisição inválida.',
            'server_error'  => 'Erro interno do servidor.',
            'too_many'      => 'Muitas tentativas. Tente novamente em :seconds segundos.',
        ]
    ],
    'auth' => [
        'invalid_token'     => 'Token de autorização é inválido!',
        'missing_headers'   => 'Cabeçalhos de requisição ausentes.',
        'tenant_missing'    => 'Tenant não fornecido.',
        'tenant_not_found'  => 'Tenant não encontrado para :action -> :method.',
    ],
    'action' => [
        'not_found'     => 'Ação :action (:method) não encontrada.',
        'execute_error' => 'Ocorreu um erro ao executar a ação!',
        'invalid_json'  => 'JSON inválido recebido!',
    ],
    'group' => [
        'already_exists' => 'O Grupo :name já existe! Por favor, escolha outro nome!',
        'create_error'   => 'Ocorreu um erro ao criar grupo!',
        'save_error'     => 'Ocorreu um erro ao salvar grupo!',
    ],
    'upload' => [
        'stream_failed' => 'Falha no stream de upload.',
    ],
    'firewall' => [
        'blocked'       => 'Acesso bloqueado.',
        'rate_limited'  => 'Muitas tentativas. Tente novamente mais tarde.',
    ],
    'session' => [
        'expired' => 'Sua sessão expirou. Faça login novamente.',
    ],
    'general' => [
        'success' => 'Operação realizada com sucesso!',
        'error'   => 'Ocorreu um erro inesperado.',
    ],
];
