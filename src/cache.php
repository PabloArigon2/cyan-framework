<?php

/**
 * Interface ICacheDriver
 * Contrato para todos os drivers de cache do framework.
 */

use Predis\Client as PredisClient;

final class Driver {
    public const FILE = 'file';
    public const REDIS = 'redis';
    public const MEMORY = 'memory';
    public const PREDIS = 'predis';
}

interface ICacheDriver {
    public function get(string $key, bool $secured = false);
    public function set(string $key, $value, int $ttl, bool $secure = false): bool;
    public function del(string $key): bool;
    public function flush(): bool;
    public function allowClass(string $string): bool;
    public function removeClass(string $string): bool;
    public function append(string $key, $value, bool $secure = false): bool;
    public function read(string $key, bool $secured = false): array;
    public function connected() : bool;
    public function deleteByPattern(string $pattern): int; // <-- novo
}

/**
 * CacheHelper - Logica centralizada para processamento de dados do cache.
 * Garante que a ordem (serialize -> encrypt) seja respeitada.
 */
trait CacheHelper {
    protected array $allowed_classes = [
        'User', 'Context', 'Endereco', 'EnvData', 'QueryResult', 'Parameter',
        'DateTime', 'stdClass', 'ApiResponse'
    ];

    protected function addAllowedClass(string $string): bool {
        if (empty($string)) return false;

        if (!in_array($string, $this->allowed_classes)) {
            $this->allowed_classes[] = $string;
            return true;
        }

        return false;
    }

    protected function removeAllowedClass(string $string): bool {
        if (empty($string)) return false;

        $key = array_search($string, $this->allowed_classes, true);
        if ($key === false) {
            return false;
        }

        unset($this->allowed_classes[$key]);
        // Reindexa o array
        $this->allowed_classes = array_values($this->allowed_classes);
        return true;
    }

    protected function pack($value, bool $secure): string {
        $data = serialize($value);
        if ($secure) {
            $data = Security::Encrypt($data, Security::DeriveKey("cache_encrypt"));
        }
        return $data;
    }

    protected function unpack(?string $data, bool $secured) {
        if ($data === null || $data === '') return null;

        try {
            if ($secured) {
                $data = Security::Decrypt($data, Security::DeriveKey("cache_encrypt"));

                if ($data === false || $data === null || $data === '') {
                    return null;
                }
            }

            $result = unserialize($data, [
                'allowed_classes' => $this->allowed_classes
            ]);

            if ($result === false && $data !== 'b:0;') {
                return null;
            }

            return $result;

        } catch (\Throwable $e) {
            error_log("Cache unpack error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * MemoryDriver
 * Armazena dados em um array PHP estático. 
 */
final class MemoryDriver implements ICacheDriver {
    use CacheHelper;
    private static array $storage = [];
    private string $prefix = "";

    public function deleteByPattern(string $pattern): int
    {
        return 0; // wildcard só faz sentido no Redis
    }

    public function connected(): bool {
        return true;
    }

    public function append(string $key, $value, bool $secure = false): bool {
        if ($value === null) return false;

        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = [];
        }

        $data = [
            'value' => $secure
                ? base64_encode($this->pack($value, $secure))
                : $value,
            'time' => time()
        ];

        self::$storage[$key][] = $data;

        return true;
    }

    public function read(string $key, bool $secure = false): array {
        if (empty(self::$storage[$key])) return [];

        $result = [];

        foreach (self::$storage[$key] as $item) {
            $value = $item['value'] ?? null;

            if ($secure && $value !== null) {
                $decoded = base64_decode($value, true);

                if ($decoded === false) continue;

                $value = $this->unpack($decoded, true);
            }

            $result[] = [
                'value' => $value,
                'time'  => $item['time'] ?? null
            ];
        }

        return $result;
    }

    public function setPrefix(string $string): bool {
        if (!empty($string)) {
            $this->prefix = $string;
            return true;
        }

        return false;
    }

    public function allowClass(string $string): bool {
        return $this->addAllowedClass($string);
    }

    public function removeClass(string $string): bool {
        return $this->removeAllowedClass($string);
    }

    public function get(string $key, bool $secured = false) {
        if (!isset(self::$storage[$key])) return null;
        $item = self::$storage[$key];
        if ($item['expire'] !== 0 && time() > $item['expire']) {
            $this->forget($key);
            return null;
        }
        return $this->unpack($item['value'], $secured);
    }

    public function set(string $key, $value, int $ttl, bool $secure = false): bool {
        if ($value === null) return false;
        self::$storage[$key] = [
            'value'  => $this->pack($value, $secure),
            'expire' => ($ttl === 0) ? 0 : (time() + $ttl)
        ];
        return true;
    }

    public function del(string $key): bool {
        unset(self::$storage[$key]);
        return true;
    }

    public function flush(): bool {
        self::$storage = [];
        return true;
    }
}

/**
 * FileDriver
 * Armazena dados no sistema de arquivos local.
 */
final class FileDriver implements ICacheDriver {
    use CacheHelper;
    private string $path;

    public function deleteByPattern(string $pattern): int
    {
        return 0; // wildcard só faz sentido no Redis
    }

    public function connected(): bool {
        return true;
    }

    public function allowClass(string $string): bool {
        return $this->addAllowedClass($string);
    }

    public function removeClass(string $string): bool {
        return $this->removeAllowedClass($string);
    }

    public function __construct(array $config = []) {
        $base = $config['path'] ?? (defined('TEMP_PATH') ? TEMP_PATH . '/cache' : sys_get_temp_dir() . '/cyan/cache');
        $this->path = rtrim($base, '/');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    private function getFilename(string $key): string {
        return $this->path . '/' . hash('sha256', $key) . '.cache';
    }

    public function get(string $key, bool $secured = false) {
        $file = $this->getFilename($key);
        if (!file_exists($file)) return null;
        $content = file_get_contents($file);
        $envelope = unserialize($content, ['allowed_classes' => $this->allowed_classes]);
        if ($envelope['expire'] !== 0 && time() > $envelope['expire']) {
            $this->forget($key);
            return null;
        }
        return $this->unpack($envelope['value'] ?? null, $secured);
    }

    public function read(string $key, bool $secured = false): array {
        $file = $this->getFilename($key);

        if (!file_exists($file)) return [];

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $result = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);

            if (!$data) continue;

            $value = $data['value'] ?? null;

            if ($secured && $value !== null) {
                $value = $this->unpack(base64_decode($value), true);
            }

            $result[] = [
                'value' => $value,
                'time'  => $data['time'] ?? null
            ];
        }

        return $result;
    }

    public function set(string $key, $value, int $ttl, bool $secure = false): bool {
        if ($value === null) return false;
        $file = $this->getFilename($key);
        $envelope = [
            'value'  => $this->pack($value, $secure),
            'expire' => ($ttl === 0) ? 0 : (time() + $ttl)
        ];
        return file_put_contents($file, serialize($envelope), LOCK_EX) !== false;
    }

    public function append(string $key, $value, bool $secure = false): bool {
        if ($value === null) return false;

        $file = $this->getFilename($key);

        $data = [
            'value' => $secure
                ? base64_encode($this->pack($value, true))
                : $value,
            'time' => time()
        ];

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($encoded === false) return false;

        return file_put_contents(
            $file,
            $encoded . PHP_EOL,
            FILE_APPEND | LOCK_EX
        ) !== false;
    }

    public function del(string $key): bool {
        $file = $this->getFilename($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public function flush(): bool {
        $files = glob($this->path . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
}

/**
 * RedisDriver
 * Armazena dados em um servidor Redis.
 */
final class RedisDriver implements ICacheDriver {
    use CacheHelper;
    private Redis $redis;
    private bool $conn = false;

    public function connected(): bool {
        return $this->conn;
    }

    public function append(string $key, $value, bool $secure = false): bool {
        if ($value === null) return false;

        $data = [
            'value' => $secure
                ? base64_encode($this->pack($value, true))
                : $value,
            'time' => time()
        ];

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($encoded === false) return false;

        return $this->redis->rPush($key, $encoded) > 0;
    }

    public function read(string $key, bool $secure = false): array {
        $items = $this->redis->lRange($key, 0, -1);

        if (!$items) return [];

        $result = [];

        foreach ($items as $item) {
            $data = json_decode($item, true);

            if (!$data) continue;

            $value = $data['value'] ?? null;

            if ($secure && $value !== null) {
                $value = $this->unpack(base64_decode($value), true);
            }

            $result[] = [
                'value' => $value,
                'time'  => $data['time'] ?? null
            ];
        }

        return $result;
    }

    public function allowClass(string $string): bool {
        return $this->addAllowedClass($string);
    }

    public function removeClass(string $string): bool {
        return $this->removeAllowedClass($string);
    }

    public function __construct(array $config = []) {
        if (!class_exists('Redis')) {
            throw new Exception("Extensão Redis não encontrada.");
        }

        $this->redis = new Redis();

        try {
            $this->redis->connect('127.0.0.1', 6379);
            if (!empty($config['password'])) {
                $this->redis->auth($config['password']);
            }

            $this->conn = true;
            $this->redis->select((int)($config['database'] ?? 0));

        } catch (Exception $ex) {
            $this->conn = false;
            throw new Exception("Connection failed: {$ex->getMessage()}", 1);
        }
    }

    public function deleteByPattern(string $pattern): int
    {
        $it = null;
        $deleted = 0;

        do {
            $keys = $this->redis->scan($it, $pattern, 1000);

            if ($keys !== false && !empty($keys)) {
                $deleted += $this->redis->del($keys);
            }

        } while ($it > 0);

        return $deleted;
    }

    public function get(string $key, bool $secured = false) {
        $data = $this->redis->get($key);
        if ($data === false) return null;
        return $this->unpack($data, $secured);
    }

    public function set(string $key, $value, int $ttl, bool $secure = false): bool {
        if ($value === null) return false;
        $packed = $this->pack($value, $secure);
        if ($ttl === 0) {
            return $this->redis->set($key, $packed);
        }
        return $this->redis->setex($key, $ttl, $packed);
    }

    public function del(string $key): bool {
        return $this->redis->del($key) > 0;
    }

    public function flush(): bool {
        $it = 0;
        $deleted = 0;
        $pattern = '*';

        do {
            $keys = $this->redis->scan($it, $pattern, 100);

            if ($keys === false) {
                continue;
            }

            if (!empty($keys)) {
                $this->redis->del($keys);
                $deleted += count($keys);
            }

        } while ((int)$it !== 0);

        return $deleted > 0;
    }
}

final class PredisDriver implements ICacheDriver {
    use CacheHelper;
    private PredisClient $redis;
    private bool $conn = false;

    public function connected(): bool {
        return $this->conn;
    }

    public function append(string $key, $value, bool $secure = false): bool {
        if ($value === null) return false;

        $data = [
            'value' => $secure
                ? base64_encode($this->pack($value, true))
                : $value,
            'time' => time()
        ];

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($encoded === false) return false;

        return $this->redis->rPush($key, $encoded) > 0;
    }

    public function read(string $key, bool $secure = false): array {
        $items = $this->redis->lRange($key, 0, -1);

        if (!$items) return [];

        $result = [];

        foreach ($items as $item) {
            $data = json_decode($item, true);

            if (!$data) continue;

            $value = $data['value'] ?? null;

            if ($secure && $value !== null) {
                $value = $this->unpack(base64_decode($value), true);
            }

            $result[] = [
                'value' => $value,
                'time'  => $data['time'] ?? null
            ];
        }

        return $result;
    }

    public function allowClass(string $string): bool {
        return $this->addAllowedClass($string);
    }

    public function removeClass(string $string): bool {
        return $this->removeAllowedClass($string);
    }

    public function __construct(array $config = []) {
        if (!class_exists('Redis')) {
            throw new Exception("Extensão Redis não encontrada.");
        }

        try {
            $this->redis = new PredisClient([
                'scheme' => 'tcp',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 6379,
                'password' => $config['password'] ?? '',
                'database' => $config['database'] ?? 0
            ]);

            if ($this->redis and $this->redis->isConnected()) {
                $this->conn = true;
            }
            else {
                $this->conn = false;
                throw new Exception("Connection failed Predis!", 1);
            }
        }
        catch(Throwable $ex) {
            $this->conn = false;
            throw new Exception("Connection failed Predis: {$ex->getMessage()}", 1);
        }
    }

    public function deleteByPattern(string $pattern): int
    {
        $it = null;
        $deleted = 0;

        do {
           [$cursor, $keys] = $this->redis->scan($cursor, [
                'MATCH' => $pattern,
                'COUNT' => 100,
            ]);

            foreach ($keys as $key) {
                $this->redis->del($key);
            }

        } while ($it > 0);

        return $deleted;
    }

    public function get(string $key, bool $secured = false) {
        $data = $this->redis->get($key);
        if ($data === false) return null;
        return $this->unpack($data, $secured);
    }

    public function set(string $key, $value, int $ttl, bool $secure = false): bool {
        if ($value === null) return false;
        $packed = $this->pack($value, $secure);
        if ($ttl === 0) {
            $this->redis->set($key, $packed);
            return true;
        }
        $this->redis->setex($key, $ttl, $packed);
        return true;
    }

    public function del(string $key): bool {
        return $this->redis->del($key) > 0;
    }

    public function flush(): bool {
        $it = null;
        $deleted = 0;
        $pattern = "*";

        do {
           [$cursor, $keys] = $this->redis->scan($cursor, [
                'MATCH' => $pattern,
                'COUNT' => 100,
            ]);

            foreach ($keys as $key) {
                $this->redis->del($key);
            }

        } while ($it > 0);

        return $deleted;
    }
}

/*

para o redis funcionar, precisa de um servidor, o recomendado seria instala-lo em nosso próprio dessa forma:

    sudo apt update
    sudo apt install redis-server
    sudo systemctl start redis

    redis-cli ping (resposta esperada: PONG)

*/

/**
 * Cache - Facade Principal
 */

class Cache {
    private ?ICacheDriver $instance = null;

    private static function normalizeSql(string $sql): string {
        // Remove espaços extras
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // Normaliza espaços ao redor de operadores comuns
        $sql = preg_replace('/\s*([=<>(),])\s*/', '$1', $sql);

        // Uppercase keywords principais (opcional)
        $keywords = [
            'select', 'from', 'where', 'and', 'or',
            'insert', 'into', 'update', 'delete',
            'join', 'left', 'right', 'inner', 'outer',
            'on', 'group by', 'order by', 'limit',
            'values', 'set', 'having'
        ];

        foreach ($keywords as $keyword) {
            $sql = preg_replace_callback(
                '/\b' . preg_quote($keyword, '/') . '\b/i',
                fn($m) => strtoupper($m[0]),
                $sql
            );
        }

        return $sql;
    }

    private static function normalizeParams(array $params): array {
        return array_map(function ($param) {
            if ($param instanceof Parameter) {
                return [
                    'type'  => $param->type,
                    'value' => $param->value,
                ];
            }

            if (is_bool($param)) {
                return $param ? 1 : 0;
            }

            if (is_array($param)) {
                return json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $param;
        }, $params);
    }

    public function deleteByPattern(string $pattern): int
    {
        return $this->getDriver()->deleteByPattern($pattern);
    }

    public function CacheKey(string $sql, string $dbname, string $version, array $params, array $extra = []): string
    {
        $table = '';
        if (preg_match('/\bFROM\s+`?(\w+)`?/i', $sql, $m)) {
            $table = strtolower($m[1]);
        }

        $hash = hash('sha256', json_encode([
            'sql'     => self::normalizeSql($sql),
            'params'  => self::normalizeParams($params),
            'extra'   => $extra,
            'version' => $version,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return "db:{$dbname}:{$table}:{$hash}";
    }

    public static function init(string $driver, array $config = []) : self {

        $cache = new self();

        if ($driver == Driver::REDIS and !extension_loaded("redis"))
            $driver = Driver::FILE;

        switch (strtolower($driver)) {
            case Driver::PREDIS:
                $cache->instance = new PredisDriver($config);
                break;
            case Driver::REDIS:
                $cache->instance = new RedisDriver($config);
                break;
            case Driver::FILE:
                $cache->instance = new FileDriver($config);
                break;
            case Driver::MEMORY:
            default:
                $cache->instance = new MemoryDriver();
                break;
        }

        return $cache;
    }

    public function getDriver(): ICacheDriver {
        if ($this->instance === null) {
            $this->instance = new MemoryDriver();
        }
        return $this->instance;
    }

    public function allowClass(string $string): bool {
        return $this->getDriver()->allowClass($string);
    }

    public function removeClass(string $string): bool {
        return $this->getDriver()->removeClass($string);
    }

    public function get(string $key, bool $secured = false) {
        return $this->getDriver()->get($key, $secured);
    }

    public function set(string $key, $value, int $ttl = 3600, bool $secure = false): bool {
        return $this->getDriver()->set($key, $value, $ttl, $secure);
    }

    public function del(string $key): bool {
        return $this->getDriver()->del($key);
    }

    public function flush(): bool {
        return $this->getDriver()->flush();
    }

    public function append(string $key, $value, bool $secure = false): bool {
        return $this->getDriver()->append($key, $value, $secure);
    }

    public function read(string $key, bool $secure = false): array {
        return $this->getDriver()->read($key, $secure);
    }

    public function remember(string $key, int $ttl, callable $callback, bool $secure = false) {
        $value = $this->get($key, $secure);
        if ($value !== null) return $value;
        $value = $callback();
        $this->set($key, $value, $ttl, $secure);
        return $value;
    }
}

?>
