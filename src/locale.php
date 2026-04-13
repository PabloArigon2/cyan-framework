<?php

/**
 * Locale — Tradutor em Arquitetura Chave/Valor.
 * Carrega arquivos de tradução e permite acesso via notação de ponto.
 * 
 * Uso:
 *   Locale::load(__DIR__ . '/lang/pt-BR.php');
 *   echo __('http.errors.unauthorized');   // "Não autorizado"
 *   echo __('http.errors.notfound', ['page' => 'perfil']);  // "Página perfil não encontrada"
 */
final class Locale {

    private static array $translations = [];
    private static string $lang = 'pt-BR';

    /**
     * Carrega um arquivo de tradução (retorna array associativo).
     * Pode ser chamado múltiplas vezes para mesclar traduções.
     */
    public static function load(string $filePath): void {
        if (!file_exists($filePath)) return;
        $data = require $filePath;
        if (is_array($data)) {
            self::$translations = array_replace_recursive(self::$translations, $data);
        }
    }

    /**
     * Define o idioma ativo.
     */
    public static function setLang(string $lang): void {
        self::$lang = $lang;
    }

    public static function getLang(): string {
        return self::$lang;
    }

    /**
     * Busca uma tradução pela chave em notação de ponto.
     * Ex: 'http.errors.unauthorized' => $translations['http']['errors']['unauthorized']
     * @param string $key      Chave de tradução (dot notation)
     * @param array  $replace  Placeholders para substituir: ['name' => 'Pablo'] → ":name" → "Pablo"
     * @param string|null $fallback  Valor padrão se não encontrado (null retorna a própria chave)
     */
    public static function get(string $key, array $replace = [], ?string $fallback = null): string {
        $segments = explode('.', $key);
        $value = self::$translations;

        foreach ($segments as $segment) {
            if (!is_array($value) || !isset($value[$segment])) {
                return $fallback ?? $key;
            }
            $value = $value[$segment];
        }

        if (!is_string($value)) {
            return $fallback ?? $key;
        }

        // Substituição de placeholders :chave
        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, $replacement, $value);
        }

        return $value;
    }

    /**
     * Verifica se a chave de tradução existe.
     */
    public static function has(string $key): bool {
        $segments = explode('.', $key);
        $value = self::$translations;

        foreach ($segments as $segment) {
            if (!is_array($value) || !isset($value[$segment])) {
                return false;
            }
            $value = $value[$segment];
        }

        return is_string($value);
    }

    /**
     * Retorna todas as traduções carregadas.
     */
    public static function all(): array {
        return self::$translations;
    }
}

/**
 * Função-atalho global para tradução.
 */
function __(string $key, array $replace = [], ?string $fallback = null): string {
    return Locale::get($key, $replace, $fallback);
}

?>
