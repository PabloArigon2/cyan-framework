<?php

class Security {

    private const TAG_LENGTH = 16;
    private const CIPHER = 'AES-256-GCM';

    private static function GetMasterKey() {
        $sys = Utils::Env("SYSTEM_TOKEN");
        $mst = Utils::Env("MASTER_TOKEN");
        return hash('sha256', "token@".$sys."&".$mst, true);
    }

    public static function DeriveKey(string $purpose, int $length = 32): string {
        return hash_hkdf('sha256', self::GetMasterKey(), $length, $purpose);
    }

    public static function Token($id, $identifier, $sep) {
        $masterkey = Utils::Env("MASTER_TOKEN");
        return hash('sha256', "token#$id" . $identifier . "$sep#$masterkey", true);
    }

    public static function DBHash($str) {
        return hash_hmac('sha256', $str, self::DeriveKey('blind_index'), true);
    }

    public static function Hash($str = null) {
        if (empty($str)) $str = self::UUID();
        return bin2hex(hash_hmac('sha256', $str, self::DeriveKey('hash_general'), true));
    }

    public static function Identifier() {
        return self::DBHash(self::UUID());
    }

    public static function Encrypt($string, $key = null) {
        if (empty($string)) return '';
        if (empty($key)) $key = self::DeriveKey('db_encrypt');

        $ivlen = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivlen);
        
        $ciphertext = openssl_encrypt($string, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, "", self::TAG_LENGTH);

        if ($ciphertext === false) {
            error_log("[ CRYPT ] Erro na cifragem!");
            return null;
        }

        return $iv . $tag . $ciphertext;
    }

    public static function Decrypt($data, $key = null) {
        if (empty($data)) return "";
        if (empty($key)) $key = self::DeriveKey('db_encrypt');

        $ivlen  = openssl_cipher_iv_length(self::CIPHER);
        $taglen = self::TAG_LENGTH;

        if (strlen($data) < ($ivlen + $taglen)) {
            error_log("[ CRYPT ] Dado corrompido!");
            return null;
        }

        $iv         = substr($data, 0, $ivlen);
        $tag        = substr($data, $ivlen, $taglen);
        $ciphertext = substr($data, $ivlen + $taglen);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            error_log("[ CRYPT ] Falha na autenticação! (Integridade violada).");
            return null;
        }

        return $plaintext;
    }

    public static function EncryptUI($string, $key = null) {
        return bin2hex(self::Encrypt($string, $key));
    }

    public static function DecryptUI($hexString, $key = null) {
        if (!ctype_xdigit($hexString)) return null;
        return self::Decrypt(hex2bin($hexString), $key);
    }

    public static function EncryptByIdentifier($data, $id, $identifier, $sep = '@') {
        $key = self::Token($id, $identifier, $sep);
        if (is_array($data) || is_object($data)) $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return self::Encrypt($data, $key);
    }

    public static function DecryptByIdentifier($data, $id, $identifier, $sep = '@', bool $asArray = false) {
        $key = self::Token($id, $identifier, $sep);
        $result = self::Decrypt($data, $key);
        return $asArray ? json_decode($result, true) : $result;
    }

    public static function EncryptByToken($data, string $tokenEnvKey) {
        $rawKey = Utils::Env($tokenEnvKey);
        if (empty($rawKey)) {
            error_log("[ CRYPT ] Token env key '$tokenEnvKey' não definida!");
            return null;
        }
        $key = hash('sha256', $rawKey, true);
        if (is_array($data) || is_object($data)) $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return self::Encrypt($data, $key);
    }

    public static function DecryptByToken($data, string $tokenEnvKey, bool $asArray = false) {
        $rawKey = Utils::Env($tokenEnvKey);
        if (empty($rawKey)) {
            error_log("[ CRYPT ] Token env key '$tokenEnvKey' não definida!");
            return null;
        }
        $key = hash('sha256', $rawKey, true);
        $result = self::Decrypt($data, $key);
        return $asArray ? json_decode($result, true) : $result;
    }

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