<?php

/**
 * Interface ICacheDriver
 * Contrato para todos os drivers de cache do framework.
 */

final class Driver {
    public const FILE = 'file';
    public const REDIS = 'redis';
    public const MEMORY = 'memory';
}

interface ICacheDriver {
    public function get(string $key, bool $secured = false);
    public function set(string $key, $value, int $ttl, bool $secure = false): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function allowClass(string $string): bool;
    public function removeClass(string $string): bool;
    public function append(string $key, $value, bool $secure = false): bool;
    public function read(string $key, bool $secured = false): array;
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
        if ($data === null) return null;

        try {
            if ($secured) {
                $data = Security::Decrypt($data, Security::DeriveKey("cache_encrypt"));
            }
            
            return unserialize($data, [
                'allowed_classes' => $this->allowed_classes
            ]);
        } catch (\Throwable $e) {
            error_log("Cache decompress error: " . $e->getMessage());
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

    public function forget(string $key): bool {
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

    public function forget(string $key): bool {
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
    private $redis;
    private string $prefix;

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

        return $this->redis->rPush($this->prefix . $key, $encoded) > 0;
    }

    public function read(string $key, bool $secure = false): array {
        $items = $this->redis->lRange($this->prefix . $key, 0, -1);

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
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $pass = $config['password'] ?? null;
        $this->prefix = $config['prefix'] ?? 'cyan:';
        if (!$this->redis->connect($host, $port)) {
            throw new Exception("Não foi possível conectar ao Redis.");
        }
        if ($pass) {
            $this->redis->auth($pass);
        }
    }

    public function get(string $key, bool $secured = false) {
        $data = $this->redis->get($this->prefix . $key);
        if ($data === false) return null;
        return $this->unpack($data, $secured);
    }

    public function set(string $key, $value, int $ttl, bool $secure = false): bool {
        if ($value === null) return false;
        $packed = $this->pack($value, $secure);
        if ($ttl === 0) {
            return $this->redis->set($this->prefix . $key, $packed);
        }
        return $this->redis->setex($this->prefix . $key, $ttl, $packed);
    }

    public function forget(string $key): bool {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function flush(): bool {
        $it = null;
        $deleted = 0;
        $pattern = $this->prefix . '*';

        do {
            $keys = $this->redis->scan($it, $pattern, 100);

            if (!empty($keys)) {
                $this->redis->del(...$keys);
                $deleted += count($keys);
            }
        } while ($it !== 0);

        return $deleted > 0;
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

    public static function init(string $driver, array $config = []) : self {

        $cache = new self();

        if ($driver == Driver::REDIS and !extension_loaded("redis"))
            $driver = Driver::FILE;

        switch (strtolower($driver)) {
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

    private function getDriver(): ICacheDriver {
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

    public function forget(string $key): bool {
        return $this->getDriver()->forget($key);
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

// final class Cache {
//     private static ?ICacheDriver $instance = null;

//     public static function init(string $driver, array $config = []) {

//         if ($driver == Driver::REDIS and !extension_loaded("redis"))
//             $driver = Driver::FILE;

//         switch (strtolower($driver)) {
//             case Driver::REDIS:
//                 self::$instance = new RedisDriver($config);
//                 break;
//             case Driver::FILE:
//                 self::$instance = new FileDriver($config);
//                 break;
//             case Driver::MEMORY:
//             default:
//                 self::$instance = new MemoryDriver();
//                 break;
//         }
//     }

//     private static function getDriver(): ICacheDriver {
//         if (self::$instance === null) {
//             self::$instance = new MemoryDriver();
//         }
//         return self::$instance;
//     }

//     public static function allowClass(string $string): bool {
//         return self::getDriver()->allowClass($string);
//     }

//     public static function removeClass(string $string): bool {
//         return self::getDriver()->removeClass($string);
//     }

//     public static function get(string $key, bool $secured = false) {
//         return self::getDriver()->get($key, $secured);
//     }

//     public static function set(string $key, $value, int $ttl = 3600, bool $secure = false): bool {
//         return self::getDriver()->set($key, $value, $ttl, $secure);
//     }

//     public static function forget(string $key): bool {
//         return self::getDriver()->forget($key);
//     }

//     public static function flush(): bool {
//         return self::getDriver()->flush();
//     }

//     public static function remember(string $key, int $ttl, callable $callback, bool $secure = false) {
//         $value = self::get($key, $secure);
//         if ($value !== null) return $value;
//         $value = $callback();
//         self::set($key, $value, $ttl, $secure);
//         return $value;
//     }
// }

?>
