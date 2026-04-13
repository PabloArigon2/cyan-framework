<?php

class Stream {
    private const CHUNK_SIZE = 8192;
    private const DETECTION_BUFFER_SIZE = 512;
    
    private int $Size = 0;
    private string $Path;
    private string $FileReadPath = "php://input";
    private ?string $DetectedExt = null;

    public function __construct(string $path) {
        $this->Path = $path;
    }

    public function Start(): bool {
        $in = fopen($this->FileReadPath, "rb");
        if (!$in) return false;

        $out = fopen($this->Path, "wb");
        if (!$out) {
            fclose($in);
            return false;
        }

        // Apenas escreve o arquivo, sem tentar detectar durante o streaming
        while (!feof($in)) {
            $chunk = fread($in, 8192);
            if ($chunk === false) break;
            
            $this->Size += strlen($chunk);
            fwrite($out, $chunk);
        }

        fclose($in);
        fclose($out);

        // AGORA SIM: Detecta o MIME do arquivo completo
        if (file_exists($this->Path) && $this->Size > 0) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($this->Path); // Usa ->file() em vez de ->buffer()
            $this->DetectedExt = $this->MapMimeToExt($mime);
        }

        // Renomeia com a extensão correta (ou 'bin' se não detectou)
        $ext = $this->DetectedExt ?? 'bin';
        $newPath = pathinfo($this->Path, PATHINFO_DIRNAME) . '/' . 
                pathinfo($this->Path, PATHINFO_FILENAME) . '.' . $ext;
        
        if (!rename($this->Path, $newPath)) {
            error_log("Falha ao renomear: $this->Path -> $newPath");
            return false;
        }
        
        $this->Path = $newPath;
        return true;
    }

    public function getPath(): string {
        return $this->Path;
    }

    public function getSize(): int {
        return $this->Size;
    }

    private function mapMimeToExt(string $mime): ?string {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'video/mp4'  => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/zip' => 'zip'
        ];
        return $map[$mime] ?? null;
    }
}

?>