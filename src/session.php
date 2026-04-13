<?php

/**
 * Session — Encapsulamento seguro de sessão PHP.
 * Substitui o uso direto de $_SESSION por métodos tipados, 
 * com expiração, fingerprinting e regeneração automática.
 * 
 * Uso:
 *   Session::start();
 *   Session::set('user_id', 42);
 *   $id = Session::get('user_id');
 *   Session::flash('success', 'Salvo com sucesso!');
 *   $msg = Session::getFlash('success');
 */
final class Session {

    private static bool $started = false;

    /**
     * Inicia a sessão com parâmetros seguros.
     * @param array $options Sobrescreve configurações padrão do cookie.
     */
    public static function start(array $options = []): bool {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return self::validate();
        }

        $defaults = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'] ?? '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly'  => true,
            'samesite'  => 'Lax'
        ];

        $params = array_merge($defaults, $options);
        session_set_cookie_params($params);
        session_start();
        self::$started = true;

        return self::validate();
    }

    /**
     * Valida a sessão ativa: expiração + fingerprint do browser.
     */
    public static function validate(): bool {
        $ttl = (int)(Utils::Env('SESSION_TTL') ?? 86400); // default 24h

        // Expiração por inatividade
        if (isset($_SESSION['_LAST_ACTIVITY'])) {
            if (time() - $_SESSION['_LAST_ACTIVITY'] > $ttl) {
                self::destroy();
                return false;
            }
        }
        $_SESSION['_LAST_ACTIVITY'] = time();

        // Fingerprint (User-Agent binding)
        $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . (Utils::Env('SESSION_SALT') ?? 'cyan'));
        if (isset($_SESSION['_FINGERPRINT'])) {
            if (!hash_equals($_SESSION['_FINGERPRINT'], $fingerprint)) {
                self::destroy();
                return false;
            }
        } else {
            $_SESSION['_FINGERPRINT'] = $fingerprint;
        }

        // Regeneração periódica do ID (anti-fixation)
        if (!isset($_SESSION['_CREATED_AT'])) {
            $_SESSION['_CREATED_AT'] = time();
        } else if (time() - $_SESSION['_CREATED_AT'] > 1800) {
            self::regenerate();
            $_SESSION['_CREATED_AT'] = time();
        }

        return true;
    }

    /**
     * Regenera o ID da sessão (anti session-fixation).
     */
    public static function regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Destrói a sessão e limpa o cookie.
     */
    public static function destroy(): void {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    // --- Getters / Setters ---

    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    /**
     * Retorna todos os dados da sessão (exceto metadados internos).
     */
    public static function all(): array {
        $data = $_SESSION ?? [];
        unset($data['_LAST_ACTIVITY'], $data['_FINGERPRINT'], $data['_CREATED_AT'], $data['_FLASH']);
        return $data;
    }

    // --- Flash Messages (One-Time Read) ---

    /**
     * Define uma mensagem flash que sobrevive apenas até a próxima leitura.
     */
    public static function flash(string $key, $value): void {
        $_SESSION['_FLASH'][$key] = $value;
    }

    /**
     * Lê e remove uma mensagem flash.
     */
    public static function getFlash(string $key, $default = null) {
        if (!isset($_SESSION['_FLASH'][$key])) return $default;
        $value = $_SESSION['_FLASH'][$key];
        unset($_SESSION['_FLASH'][$key]);
        return $value;
    }

    /**
     * Verifica se existe uma mensagem flash.
     */
    public static function hasFlash(string $key): bool {
        return isset($_SESSION['_FLASH'][$key]);
    }

    // --- CSRF Token ---

    /**
     * Gera ou retorna o CSRF token atual da sessão.
     */
    public static function csrf(): string {
        if (empty($_SESSION['_CSRF_TOKEN'])) {
            $_SESSION['_CSRF_TOKEN'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_CSRF_TOKEN'];
    }

    /**
     * Valida um CSRF token recebido contra o da sessão.
     */
    public static function validateCsrf(string $token): bool {
        return hash_equals(self::csrf(), $token);
    }
}

?>
