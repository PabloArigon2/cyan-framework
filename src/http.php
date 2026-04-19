<?php

final class Http {
    public static function Request($url, $method, $headers = [], $body = []) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers
        ]);

        if (!empty($body) and gettype($body) == "array") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'Status' => ($code >= 200 and $code < 300) ? 1 : 0,
            'Body' => json_decode($response, true)
        ];
    }

    public static function CloserRequisition2(callable $callback) {
        ob_start();

        $size = ob_get_length();
        header("Content-Length: $size");
        header("Connection: close");
        ob_end_flush();
        @ob_flush();
        flush();

        // A partir daqui o cliente já recebeu a resposta
        $callback(); // post-processing
    }

    public static function CloseRequisition(callable $callback): void
    {
        ignore_user_abort(true); // deve vir primeiro

        // Garante que há um buffer ativo
        if (ob_get_level() === 0) {
            ob_start();
        }

        $length = ob_get_length();

        header('Content-Length: ' . ($length ?: 0));
        header('Connection: close');

        ob_end_flush(); // despeja o buffer interno do PHP
        flush();        // força o envio pelo servidor (Apache/nginx)

        // Callback roda aqui, depois do flush — cliente já recebeu a resposta
        $callback();
    }

    public static function Response($headers, $result, $code = 200) {
        foreach($headers as $header => $value) { header($header.": ".$value); }
        http_response_code($code);
        header("Server: CYANTECH/SERVER v1");
        header("Access-Control-Allow-Origin: " . (Utils::Env('CORS_ORIGINS') ?? '*'));

        if (isset($headers['Content-Type']) and $headers['Content-Type'] == "application/json") {
            echo json_encode($result);
        } else {
            echo is_array($result) ? json_encode($result) : $result;
        }

        session_write_close();
        
        if (function_exists("fastcgi_finish_request"))
            fastcgi_finish_request();

        exit();
    }

    public static function GetHeader($header) {
        $headers = getallheaders();
        return $headers[$header] ?? null;
    }

    public static function GetUserIP() {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['HTTP_CLIENT_IP'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '0.0.0.0';
    }
}

final class Url {
    public static function getDirLink() {
        $baseLink = self::getPageLink();
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $appSlug = Utils::Env('APP_SLUG') ?? '';

        $parts = explode('/', trim($requestUri, '/'));
        if (!empty($appSlug) && isset($parts[0]) && $parts[0] === $appSlug) { array_shift($parts); }

        $dir = $parts[0] ?? '';
        return !empty($dir) ? $baseLink . '/' . $dir : $baseLink;
    }

    public static function getPageLink() {
        $protocol = (empty($_SERVER['HTTPS']) ? 'http' : 'https');
        $url = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $appSlug = Utils::Env('APP_SLUG') ?? '';

        if (!empty($appSlug) && ($url == "localhost" || $url == "127.0.0.1")) {
            $url .= "/" . $appSlug;
        }
        return $protocol . "://" . $url . (($url == "localhost" or $url == "127.0.0.1") ? '/ecoglobal' : '');
    }

    public static function getFullUrl() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
        return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    }
}
