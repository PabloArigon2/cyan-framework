<?php

/**
 * Query Builder — Utilitário opcional para construção fluente de queries SQL.
 * Não substitui queries manuais, serve como atalho para operações comuns.
 * 
 * Uso:
 *   $users = QB::table('usuarios')->where('status', 1)->orderBy('nome')->get();
 *   $user  = QB::table('usuarios')->where('id', 5)->first();
 *   QB::table('usuarios')->insert(['nome' => 'Pablo', 'email' => 'x@y.com']);
 *   QB::table('usuarios')->where('id', 5)->update(['nome' => 'Pablo Arigon']);
 *   QB::table('usuarios')->where('id', 5)->delete();
 */
final class QB {
    private string $table;
    private array  $wheres     = [];
    private array  $params     = [];
    private array  $orderBy    = [];
    private ?int   $limitVal   = null;
    private ?int   $offsetVal  = null;
    private array  $selects    = ['*'];
    private array  $joins      = [];
    private ?string $groupBy   = null;

    private function __construct(string $table) {
        $this->table = $table;
    }

    public static function table(string $table): self {
        return new self($table);
    }

    // --- SELECT ---

    public function select(string ...$columns): self {
        $this->selects = $columns;
        return $this;
    }

    // --- JOIN ---

    public function join(string $table, string $on, string $type = 'INNER'): self {
        $this->joins[] = strtoupper($type) . " JOIN {$table} ON {$on}";
        return $this;
    }

    public function leftJoin(string $table, string $on): self {
        return $this->join($table, $on, 'LEFT');
    }

    // --- WHERE ---

    public function where(string $column, $value, string $operator = '='): self {
        $this->wheres[] = "{$column} {$operator} ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereNull(string $column): self {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values): self {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        foreach ($values as $v) $this->params[] = $v;
        return $this;
    }

    public function whereRaw(string $raw, array $params = []): self {
        $this->wheres[] = $raw;
        foreach ($params as $p) $this->params[] = $p;
        return $this;
    }

    // --- ORDER / LIMIT / GROUP ---

    public function orderBy(string $column, string $direction = 'ASC'): self {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$dir}";
        return $this;
    }

    public function limit(int $limit): self {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): self {
        $this->offsetVal = $offset;
        return $this;
    }

    public function groupBy(string $column): self {
        $this->groupBy = $column;
        return $this;
    }

    // --- BUILD ---

    private function buildSelectSQL(): string {
        $cols = implode(', ', $this->selects);
        $sql  = "SELECT {$cols} FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " {$join}";
        }

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        if ($this->groupBy) {
            $sql .= " GROUP BY {$this->groupBy}";
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return $sql;
    }

    // --- EXECUTE ---

    /**
     * Executa SELECT e retorna QueryResult.
     */
    public function get(): \QueryResult {
        return \Database::Query($this->buildSelectSQL(), $this->params);
    }

    /**
     * Executa SELECT e retorna apenas o primeiro registro como array, ou null.
     */
    public function first(): ?array {
        $this->limitVal = 1;
        $result = $this->get();
        return $result->validQuery() ? $result->get(0) : null;
    }

    /**
     * Retorna a contagem de registros.
     */
    public function count(): int {
        $saved = $this->selects;
        $this->selects = ['COUNT(*) AS total'];
        $result = $this->get();
        $this->selects = $saved;
        return $result->validQuery() ? (int)$result->field(0, 'total') : 0;
    }

    /**
     * Verifica se existe ao menos um registro.
     */
    public function exists(): bool {
        return $this->count() > 0;
    }

    /**
     * INSERT — Recebe array associativo ['coluna' => 'valor'].
     * Retorna o QueryResult da execução.
     */
    public function insert(array $data): \QueryResult {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        return \Database::Query($sql, array_values($data));
    }

    /**
     * UPDATE — Recebe array associativo ['coluna' => 'valor'].
     * Aplica os WHEREs previamente definidos.
     */
    public function update(array $data): \QueryResult {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
            $params = array_merge($params, $this->params);
        }

        return \Database::Query($sql, $params);
    }

    /**
     * DELETE — Remove registros com base nos WHEREs definidos.
     * EXIGE que ao menos um WHERE esteja definido para prevenir exclusão total acidental.
     */
    public function delete(): \QueryResult {
        if (empty($this->wheres)) {
            throw new \Exception("QB::delete() requer ao menos um WHERE para prevenir exclusão total.");
        }

        $sql = "DELETE FROM {$this->table}";
        $sql .= " WHERE " . implode(' AND ', $this->wheres);
        return \Database::Query($sql, $this->params);
    }
}

?>
