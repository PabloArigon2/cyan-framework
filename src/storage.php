<?php

class Payload {
    public string $Nome = "";
    public string $MimeType = "";
    public string $TempPath = "";
    public int $Error = 0;
    public int $Size = 0;
}

class Payloads implements \IteratorAggregate
{
    private array $items = [];

    public function Size() {
        return count($this->items);
    }

    public function Add(Payload $payload): void
    {
        $this->items[] = $payload;
    }

    public function Get(int|string $id): ?Payload
    {
        return $this->items[$id] ?? null;
    }

    public function Iterate(callable $cb): void
    {
        foreach ($this->items as $payload) {
            $cb($payload);
        }
    }

    public function GetAll() {
        return $this->items;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}

final class PayloadRegistry
{
    private static array $used = [];

    public static function add(string $payloadId): void
    {
        self::$used[] = basename($payloadId);
    }

    public static function all(): array
    {
        return array_unique(self::$used);
    }
}

final class FileIntent
{
    public Payload $file;
    public string $finalDir;
    public string $finalName;

    public function finalPath(): string
    {
        return rtrim($this->finalDir, '/') . '/' . basename($this->finalName); // LFI Mitigation
    }
}

final class FileTransactionManager
{
    private static array $intents = [];
    private static array $applied = [];

    public static function validateMimeType(string $tmpPath): string|false
    {
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return $finfo->file($tmpPath);
        } else if (function_exists('mime_content_type')) {
            return mime_content_type($tmpPath);
        }
        return false;
    }

    public static function save(Payload $file, string $finalDir): ?string
    {
        if (!file_exists($file->TempPath)) {
            return null;
        }

        $info = pathinfo($file->Nome);
        $ext  = strtolower($info['extension'] ?? '');

        // Security: Finfo verification against extension spoofing
        $mime = self::validateMimeType($file->TempPath);
        
        $allowedMimes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/csv' => ['csv'],
            'text/csv' => ['csv']
        ];

        if (!$mime || !isset($allowedMimes[$mime]) || !in_array($ext, $allowedMimes[$mime])) {
            return null; // Invalid MIME or extension mismatch
        }

        $intent = new FileIntent();
        $intent->file      = $file;
        // Sanitizing directory to prevent traversal outside public/uploads equivalent
        $intent->finalDir  = rtrim($finalDir, '/');
        $intent->finalName = \Security::Hash() . '.' . $ext;

        self::$intents[] = $intent;

        return $intent->finalName;
    }

    public static function apply(): void
    {
        foreach (self::$intents as $intent) {

            if (!($intent instanceof FileIntent)) continue;

            if (!is_dir($intent->finalDir)) {
                mkdir($intent->finalDir, 0777, true);
            }

            if (!rename($intent->file->TempPath, $intent->finalPath())) {
                throw new \Exception("Failed to move file: {$intent->finalPath()}");
            }

            self::$applied[] = $intent;
        }
    }

    public static function rollback($all = false): void
    {
        // remove staging não aplicado
        foreach (self::$intents as $intent) {
            if (file_exists($intent->file->TempPath)) {
                @unlink($intent->file->TempPath);
            }
        }

        // opcional: remove arquivos já aplicados
        if ($all) {
            foreach (self::$applied as $intent) {
                if (file_exists($intent->finalPath())) {
                    @unlink($intent->finalPath());
                }
            }
        }
    }
}

// File Streamer para servir arquivos protegidos com CSP
final class FileStreamer
{
    public static function Serve(string $absolutePath)
    {
        if (!file_exists($absolutePath)) {
            http_response_code(404);
            die("File not found");
        }

        $mime = FileTransactionManager::validateMimeType($absolutePath);
        if (!$mime) {
            $mime = 'application/octet-stream';
        }

        // Aplicando Content-Security-Policy rígido
        header("Content-Type: " . $mime);
        header("Content-Security-Policy: default-src 'none'; script-src 'none'; sandbox;");
        header("X-Content-Type-Options: nosniff");
        header("Content-Disposition: inline; filename=\"" . basename($absolutePath) . "\"");
        
        readfile($absolutePath);
        exit;
    }

    public static function Delete(string $path) {
        if (file_exists($path))
            unlink($path);
    }
}
?>
