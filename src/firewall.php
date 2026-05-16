<?php

final class Firewall {
    private static int $maxAttempts = 50;
    private static int $windowSec = 50;
    private static ?Cache $cache = null;
    private static ?Queue $queue = null;

    private static function init() {
        if (self::$cache === null) {
            try {
                self::$cache = Cache::init(Driver::PREDIS, [ 'database' => 1 ]);
                // Inicializa a Fila para gravar no DB de forma assíncrona
                self::$queue = new Queue([ 'database' => 1 ]);
            } catch(Throwable $ex) {
                self::$cache = null;
                self::$queue = null;
            }
        }
    }

    private static function isEnabled(): bool {
        self::init();
        return self::$cache && self::$cache->getDriver()->connected();
    }

    /**
     * Rate Limiter Genérico: Incrementa a cada requisição.
     * Útil para usar no topo de actions.php (ex: max 50 reqs em 1000s)
     */
    public static function protect(string $action, int $maxRequests = 50, int $windowSec = 1): void {
        if (!self::isEnabled()) return;

        $ip = self::getClientIp();
        $hashIp = self::hashIp($ip);
        
        // 1. Verifica se está na blacklist
        if (self::isBlacklisted($ip)) {
            self::blockResponse('Acesso bloqueado permanentemente.');
        }

        // 2. Incrementa e verifica o Rate Limit (deslizante)
        $key = "fw:ratelimit:{$action}:{$hashIp}";
        $count = (int)(self::$cache->get($key) ?? 0);

        if ($count >= $maxRequests) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $windowSec);
            echo json_encode(['Status' => 0, 'Mensagem' => 'Muitas requisições. Tente novamente mais tarde.']);
            exit;
        }

        self::$cache->set($key, $count + 1, $windowSec);
    }

    /**
     * Guardião baseado em Falhas (ex: falhas de login).
     * Requer o uso de Firewall::recordFailure() quando algo der errado.
     */
    public static function guard(string $action, int $maxAttempts = 5, int $windowSec = 30): void {
        if (!self::isEnabled()) return;

        $ip = self::getClientIp();
        $hashIp = self::hashIp($ip);

        if (self::isBlacklisted($ip)) {
            self::blockResponse('Acesso bloqueado.');
        }

        $key = "fw:fails:{$action}:{$hashIp}";
        $attempts = (int)(self::$cache->get($key) ?? 0);

        if ($attempts >= $maxAttempts) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $windowSec);
            echo json_encode(['Status' => 0, 'Mensagem' => 'Muitas tentativas falhas. Tente novamente mais tarde.']);
            exit;
        }
    }

    /**
     * Registra uma falha (ex: senha incorreta)
     */
    public static function recordFailure(string $action, int $windowSec = 300): void {
        if (!self::isEnabled()) return;

        $ip = self::getClientIp();
        $hashIp = self::hashIp($ip);
        
        $key = "fw:fails:{$action}:{$hashIp}";
        $attempts = (int)(self::$cache->get($key) ?? 0);
        self::$cache->set($key, $attempts + 1, $windowSec);

        // Salva de forma assíncrona no MySQL usando a Queue
        if (self::$queue) {
            self::$queue->add('firewall_logs', 'FirewallLogJob', [
                'type' => 'failure',
                'ip_hash' => $hashIp,
                'action' => $action
            ]);
        }
    }

    /**
     * Limpa tentativas (ex: após sucesso no login)
     */
    public static function clearAttempts(string $action): void {
        if (!self::isEnabled()) return;

        $ip = self::getClientIp();
        $hashIp = self::hashIp($ip);
        self::$cache->del("fw:fails:{$action}:{$hashIp}");
        self::$cache->del("fw:ratelimit:{$action}:{$hashIp}");

        if (self::$queue) {
            self::$queue->add('firewall_logs', 'FirewallLogJob', [
                'type' => 'clear',
                'ip_hash' => $hashIp,
                'action' => $action
            ]);
        }
    }

    /**
     * Adiciona um IP à blacklist
     */
    public static function blacklist(string $ip, string $reason = '', int $duration = 0): void {
        if (!self::isEnabled()) return;

        $hashIp = self::hashIp($ip);
        $expiresAt = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;
        
        // Se duration = 0, no redis podemos usar 0 como não expira (ou omitir)
        // O Cache set com TTL 0 é permanente
        self::$cache->set("fw:blacklist:{$hashIp}", $reason, $duration);

        if (self::$queue) {
            self::$queue->add('firewall_logs', 'FirewallLogJob', [
                'type' => 'blacklist',
                'ip_hash' => $hashIp,
                'reason' => $reason,
                'expires_at' => $expiresAt
            ]);
        }
    }

    public static function unblock(string $ip): void {
        if (!self::isEnabled()) return;

        $hashIp = self::hashIp($ip);
        self::$cache->del("fw:blacklist:{$hashIp}");

        if (self::$queue) {
            self::$queue->add('firewall_logs', 'FirewallLogJob', [
                'type' => 'unblock',
                'ip_hash' => $hashIp
            ]);
        }
    }

    public static function isBlacklisted(string $ip): bool {
        if (!self::isEnabled()) return false;
        
        $hashIp = self::hashIp($ip);
        return self::$cache->get("fw:blacklist:{$hashIp}") !== null;
    }

    private static function blockResponse(string $message): void {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['Status' => 0, 'Mensagem' => $message]);
        exit;
    }

    private static function getClientIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    private static function hashIp(string $ip): string {
        return \Security::Hash($ip);
    }
}

/**
 * Job para rodar de forma assíncrona (via Worker)
 * Processa as alterações do firewall no banco de dados.
 */
class FirewallLogJob implements IJob {
    public function handle(array $data) {
        $type = $data['type'] ?? '';
        $ip_hash = $data['ip_hash'] ?? '';
        $action = $data['action'] ?? '';
        
        if ($type === 'failure') {
            \Database::Query(
                "INSERT INTO firewall_attempts (ip_hash, action, attempted_at) VALUES (?, ?, NOW())",
                [$ip_hash, $action]
            );
        } elseif ($type === 'blacklist') {
            $reason = $data['reason'] ?? null;
            $expires_at = $data['expires_at'] ?? null;
            \Database::Query(
                "INSERT INTO firewall_blacklist (ip_hash, reason, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)",
                [$ip_hash, $reason, $expires_at]
            );
        } elseif ($type === 'unblock') {
            \Database::Query(
                "DELETE FROM firewall_blacklist WHERE ip_hash = ?",
                [$ip_hash]
            );
        } elseif ($type === 'clear') {
            \Database::Query(
                "DELETE FROM firewall_attempts WHERE ip_hash = ? AND action = ?",
                [$ip_hash, $action]
            );
        }
    }
}
?>
