<?php

final class Formatter {
    public static function RemoverAcentos($string) {
        $mapa = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];

        return strtr($string, $mapa);
    }

    public static function RemoveSlugs($string) {
        $string = self::RemoverAcentos($string);
        $string = mb_strtolower($string, 'UTF-8');
        return preg_replace('/[^a-z0-9]/', '', $string);
    }

    public static function FormatName($nome) {
        return preg_split('/\s+/', $nome);
    }

    public static function CalcularAnos($data) {
        if (empty($data)) return 0;
        $d1 = new \DateTime($data);
        $d2 = new \DateTime(date("Y-m-d"));
        $diff = $d2->diff($d1);
        return $diff->y;
    }

    public static function FormatValor($val) {
        return "R$ " . number_format((float)$val, 2, ',', '.');
    }

    public static function FormatarDuracao($segundos) {
        $horas = floor($segundos / 3600);
        $minutos = floor(($segundos % 3600) / 60);

        if ($horas > 0) {
            return $horas . 'hr ' . $minutos . 'min';
        } else {
            return $minutos . 'min';
        }
    }

    public static function GetCrescimento($val1, $val2) {
        if ($val1 == 0) return 0;
        return (($val2 - $val1) / $val1) * 100;
    }
}

?>
