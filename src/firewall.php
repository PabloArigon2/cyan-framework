<?php

/**
 * Firewall — Módulo anti-bruteforce com blacklisting e rate-limiting.
 * Integra-se automaticamente ao actions.php para proteger endpoints de API.
 * 
 * Uso:
 *   // No início de login.php ou qualquer endpoint sensível:
 *   Firewall::guard('login', 5, 300);  // max 5 tentativas em 300s por IP
 *   
 *   // Na resposta de falha:
 *   Firewall::recordFailure('login');
 *   
 *   // Na resposta de sucesso (opcional, limpa contador):
 *   Firewall::clearAttempts('login');
 *   
 *   // Blacklist manual:
 *   Firewall::blacklist($ip, 'Ataque automatizado', 3600);
 */
final class Firewall {

    /**
     * Verifica se o IP atual está bloqueado ou excedeu o rate limit.
     * Se sim, encerra a requisição com 429 Too Many Requests.
     * 
     * @param string $action   Identificador da ação (ex: 'login', 'register')
     * @param int    $maxAttempts Máximo de tentativas permitidas
     * @param int    $windowSec   Janela de tempo em segundos
     */
    public static function guard(string $action, int $maxAttempts = 5, int $windowSec = 300): void {
        $ip = self::getClientIp();

        // 1) Verificar blacklist
        if (self::isBlacklisted($ip)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['Status' => 0, 'Mensagem' => 'Acesso bloqueado.']);
            exit;
        }

        // 2) Verificar rate limit
        $attempts = self::getAttempts($ip, $action, $windowSec);
        if ($attempts >= $maxAttempts) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $windowSec);
            echo json_encode(['Status' => 0, 'Mensagem' => 'Muitas tentativas. Tente novamente mais tarde.']);
            exit;
        }
    }

    /**
     * Registra uma tentativa falha para o IP atual.
     */
    public static function recordFailure(string $action): void {
        $ip = self::getClientIp();
        \Database::Query(
            "INSERT INTO firewall_attempts (ip_hash, action, attempted_at) VALUES (?, ?, NOW())",
            [self::hashIp($ip), $action]
        );
    }

    /**
     * Limpa tentativas do IP atual para uma ação (ex: após login bem-sucedido).
     */
    public static function clearAttempts(string $action): void {
        $ip = self::getClientIp();
        \Database::Query(
            "DELETE FROM firewall_attempts WHERE ip_hash = ? AND action = ?",
            [self::hashIp($ip), $action]
        );
    }

    /**
     * Conta tentativas do IP dentro da janela de tempo.
     */
    private static function getAttempts(string $ip, string $action, int $windowSec): int {
        $result = \Database::Query(
            "SELECT COUNT(*) AS total FROM firewall_attempts WHERE ip_hash = ? AND action = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [self::hashIp($ip), $action, $windowSec]
        );

        return $result->valid() ? (int)$result->field(0, 'total') : 0;
    }

    // --- Blacklist Management ---

    /**
     * Adiciona um IP à blacklist.
     * @param string  $ip       Endereço IP
     * @param string  $reason   Motivo do bloqueio
     * @param int     $duration Duração em segundos (0 = permanente)
     */
    public static function blacklist(string $ip, string $reason = '', int $duration = 0): void {
        $expiresAt = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;

        \Database::Query(
            "INSERT INTO firewall_blacklist (ip_hash, reason, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)",
            [self::hashIp($ip), $reason, $expiresAt]
        );
    }

    /**
     * Remove um IP da blacklist.
     */
    public static function unblock(string $ip): void {
        \Database::Query(
            "DELETE FROM firewall_blacklist WHERE ip_hash = ?",
            [self::hashIp($ip)]
        );
    }

    /**
     * Verifica se o IP está na blacklist ativa.
     */
    public static function isBlacklisted(string $ip): bool {
        $result = \Database::Query(
            "SELECT id FROM firewall_blacklist WHERE ip_hash = ? AND (expires_at IS NULL OR expires_at > NOW())",
            [self::hashIp($ip)]
        );

        return $result->valid();
    }

    /**
     * Limpa registros expirados da blacklist e tentativas antigas.
     * Pode ser chamado periodicamente (cron ou shutdown).
     */
    public static function cleanup(int $attemptRetentionSec = 86400): void {
        // Blacklist expirada
        \Database::Query("DELETE FROM firewall_blacklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");

        // Tentativas antigas
        \Database::Query(
            "DELETE FROM firewall_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$attemptRetentionSec]
        );
    }

    // --- Analytics ---

    /**
     * Retorna as top IPs com mais tentativas no período.
     */
    public static function topOffenders(int $topN = 10, int $windowHours = 24): array {
        $result = \Database::Query(
            "SELECT ip_hash, action, COUNT(*) AS attempts FROM firewall_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) GROUP BY ip_hash, action ORDER BY attempts DESC LIMIT ?",
            [$windowHours, $topN]
        );

        return $result->valid() ? $result->get() : [];
    }

    /**
     * Retorna todos os IPs bloqueados ativos.
     */
    public static function getBlacklist(): array {
        $result = \Database::Query(
            "SELECT * FROM firewall_blacklist WHERE expires_at IS NULL OR expires_at > NOW() ORDER BY created_at DESC"
        );

        return $result->valid() ? $result->get() : [];
    }

    // --- Internos ---

    private static function getClientIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /**
     * Gera hash HMAC do IP usando a MasterKey — nunca armazenamos IPs em texto plano.
     */
    private static function hashIp(string $ip): string {
        return \Security::Hash($ip);
    }
}

?>
