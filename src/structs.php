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


final class ReqMethod {
    public const GET    = 'GET';
    public const POST   = 'POST';
    public const PUT    = 'PUT';
    public const DELETE = 'DELETE';
    public const PATCH  = 'PATCH';
}

interface IJob {
    public function handle(array $data);
}

class Result {
    public function __construct(
        public readonly bool $status,
        public readonly ?string $erro = null,
        public readonly ?string $msg = null,
        public readonly ?array $values = null
    ) {}

    public static function ok(string $msg = '', array $values = []): self {
        return new self(true, null, $msg, $values);
    }

    public static function fail(string $erro, string $msg = '', array $values = []): self {
        return new self(false, $erro, $msg, $values);
    }
}

enum FilterOperator: string {
    case EQUALS = '=';
    case LIKE = 'LIKE';
}

class Filter {
    public readonly mixed $SearchFunc;

    public function __construct(
        public readonly ?int $limit = 0,
        public readonly ?int $offset = 0,
        public readonly ?string $search = '',
        public readonly ?FilterOperator $searchOperator = null,
        public readonly ?array $fieldsSearch = [],
        callable|null $SearchFunc = null
    ) {
        $this->SearchFunc = $SearchFunc;
    }

    public function applySearchToQuery(string $sql, array $params = []): array {
        $sqlAddit = '';
        $sqlParams = [];

        $hasSearch = !empty($this->search);
        $hasFields = !empty($this->fieldsSearch);

        if ($hasSearch && $hasFields) {
            $operator = $this->searchOperator === FilterOperator::LIKE ? 'LIKE' : '=';

            $conditions = array_map(
                fn($field) => "{$field} {$operator} ?",
                $this->fieldsSearch
            );

            $sqlAddit .= " AND (" . implode(" OR ", $conditions) . ")";

            $search = $this->search;

            if (is_callable($this->SearchFunc)) {
                $search = ($this->SearchFunc)($search);
            }

            if ($this->searchOperator === FilterOperator::LIKE) {
                $search = "%{$search}%";
            }

            foreach ($this->fieldsSearch as $_) {
                $sqlParams[] = $search;
            }
        }

        if ($sqlAddit !== '') {
            $sql = $this->injectSqlAddit($sql, $sqlAddit);
        }

        return [
            'sql' => $sql,
            'params' => array_merge($params, $sqlParams),
        ];
    }

    public function applyPaginationToQuery(string $sql, array $params = []): array {
        if (($this->limit ?? 0) > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$this->limit;
            $params[] = (int)($this->offset ?? 0);
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    public function applyToQuery(string $sql, array $params = []): array {
        $sqlAddit = '';
        $sqlParams = [];

        $hasSearch = !empty($this->search);
        $hasFields = !empty($this->fieldsSearch);

        if ($hasSearch && $hasFields) {

            $operator = $this->searchOperator == FilterOperator::LIKE ? 'LIKE' : '=';

            $conditions = array_map(
                fn($field) => "{$field} {$operator} ?",
                $this->fieldsSearch
            );

            $sqlAddit .= " AND (" . implode(" OR ", $conditions) . ")";

            $search = $this->search;

            if (is_callable($this->SearchFunc)) {
                $search = ($this->SearchFunc)($search);
            }

            if ($this->searchOperator === FilterOperator::LIKE) {
                $search = "%{$search}%";
            }

            foreach ($this->fieldsSearch as $_) {
                $sqlParams[] = $search;
            }
        }

        // Só aplica LIMIT/OFFSET quando NÃO houver search
        if (!$hasSearch && ($this->limit ?? 0) > 0) {
            $sqlAddit .= " LIMIT ? OFFSET ?";
            $sqlParams[] = (int)$this->limit;
            $sqlParams[] = (int)($this->offset ?? 0);
        }

        if ($sqlAddit !== '') {
            $sql = $this->injectSqlAddit($sql, $sqlAddit);
        }

        return [
            'sql' => $sql,
            'params' => array_merge($params, $sqlParams),
        ];
    }

    private function injectSqlAddit(string $sql, string $sqlAddit): string {
        $sql = trim($sql);

        $hasWhere = preg_match('/\bWHERE\b/i', $sql);

        if (!$hasWhere) {
            $sqlAddit = preg_replace('/^\s*AND\s+/i', ' WHERE ', $sqlAddit);
        }

        if (preg_match('/\s+ORDER\s+BY\s+/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];

            return substr($sql, 0, $pos)
                . $sqlAddit . ' '
                . substr($sql, $pos);
        }

        return $sql . $sqlAddit;
    }

    public function searchFilter(array $row, array $fields = []): bool {
        if (empty($this->search)) {
            return true;
        }

        $search = mb_strtolower($this->search);
        $fieldMap = !empty($fields) ? array_flip($fields) : null;

        foreach ($row as $field => $value) {
            if ($fieldMap !== null && !isset($fieldMap[$field])) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (str_contains(mb_strtolower((string)$value), $search)) {
                return true;
            }
        }

        return false;
    }
}

?>