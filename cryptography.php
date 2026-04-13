<?php

class Security {

    private const TAG_LENGTH = 16;

    private static function GetMasterKey() {
        $sys = env("SYSTEM_TOKEN");
        $mst = env("MASTER_TOKEN");
        // Retorna 32 bytes binários
        return hash('sha256', "token@".$sys."&".$mst, true);
    }

    /**
     * Gera a chave de criptografia única para um registro.
     * Como o banco é novo, o $identifier SEMPRE entra como binário (32 bytes).
     */
    public static function Token($id, $identifier, $sep) {
        $masterkey = env("MASTER_TOKEN");
        // Concatenamos os bytes diretamente. O PHP é binary-safe.
        return hash('sha256', "token#$id" . $identifier . "$sep#$masterkey", true);
    }

    /**
     * Gera um Hash de busca (Blind Index) ou ID interno.
     * Retorna sempre 32 bytes binários.
     */
    public static function DBHash($str) {
        return hash_hmac('sha256', $str, self::GetMasterKey(), true);
    }

    public static function Hash($str = null) {
        if (empty($str)) $str = self::UUID();
        return bin2hex(hash_hmac('sha256', $str, self::GetMasterKey(), true));
    }

    /**
     * Gera um novo identificador binário para um registro.
     */
    public static function Identifier() {
        return self::DBHash(self::UUID());
    }

    // --- Criptografia de Armazenamento (Banco de Dados - BLOB/BINARY) ---

    public static function Encrypt($string, $key = null) {
        if (empty($key)) $key = self::GetMasterKey();

        $cipher_algo = "AES-256-GCM";
        $ivlen = openssl_cipher_iv_length($cipher_algo);
        $iv = random_bytes($ivlen);
        
        $ciphertext = openssl_encrypt($string, $cipher_algo, $key, OPENSSL_RAW_DATA, $iv, $tag, "", self::TAG_LENGTH);

        if ($ciphertext === false) throw new Exception("Erro na cifragem.");

        return $iv . $tag . $ciphertext;
    }

    public static function Decrypt($data, $key = null) {
        if (empty($key)) $key = self::GetMasterKey();
        if (empty($data)) return "";

        $cipher_algo = "AES-256-GCM";
        $ivlen = openssl_cipher_iv_length($cipher_algo);
        $taglen = self::TAG_LENGTH;

        if (strlen($data) < ($ivlen + $taglen)) throw new Exception("Dado corrompido.");

        $iv         = substr($data, 0, $ivlen);
        $tag        = substr($data, $ivlen, $taglen);
        $ciphertext = substr($data, $ivlen + $taglen);

        $plaintext = openssl_decrypt($ciphertext, $cipher_algo, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) throw new Exception("Falha na autenticação (Integridade violada).");

        return $plaintext;
    }

    // --- Criptografia de Interface (Frontend/URLs - HEX) ---

    public static function EncryptUI($string, $key = null) {
        return bin2hex(self::Encrypt($string, $key));
    }

    public static function DecryptUI($hexString, $key = null) {
        if (!ctype_xdigit($hexString)) throw new Exception("Formato inválido.");
        return self::Decrypt(hex2bin($hexString), $key);
    }

    // --- Utilitários (UUID e Password) ---

    public static function UUID(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function CreatePassword($str) {
        return password_hash($str, PASSWORD_ARGON2ID, ["memory_cost" => 65536, "time_cost" => 4, "threads" => 3]);
    }

    public static function CheckPassword($str, $hash) {
        return password_verify($str, $hash);
    }
}

?>