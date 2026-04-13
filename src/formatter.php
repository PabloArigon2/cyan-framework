<?php

final class Formatter {

    // --- Remoção de Acentos e Slugs ---

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

    // --- Nomes ---

    public static function FormatName($nome) {
        return preg_split('/\s+/', $nome);
    }

    // --- Datas e Tempo ---

    public static function CalcularAnos($data) {
        return Math::getAge($data);
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

    // --- Monetário ---

    public static function FormatValor($val) {
        return "R$ " . number_format((float)$val, 2, ',', '.');
    }

    // --- Crescimento / Percentuais ---

    public static function GetCrescimento($val1, $val2) {
        return Math::calculateGrow($val1, $val2);
    }

    // --- Documentos Brasileiros ---

    public static function FormatarCPF($text) {
        $value = preg_replace('/\D/', '', $text);

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 3);
        }
        if (strlen($value) >= 3) {
            $formattedValue .= '.' . substr($value, 3, 3);
        }
        if (strlen($value) >= 6) {
            $formattedValue .= '.' . substr($value, 6, 3);
        }
        if (strlen($value) >= 9) {
            $formattedValue .= '-' . substr($value, 9, 2);
        }

        return $formattedValue;
    }

    public static function ValidarCPF($number) {
        return Validate::CPF($number);
    }

    public static function FormatarCnpj($text) {
        $value = preg_replace('/\D/', '', $text);

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 2);
        }
        if (strlen($value) >= 2) {
            $formattedValue .= '.' . substr($value, 2, 3);
        }
        if (strlen($value) >= 5) {
            $formattedValue .= '.' . substr($value, 5, 3);
        }
        if (strlen($value) >= 8) {
            $formattedValue .= '/' . substr($value, 8, 4);
        }
        if (strlen($value) >= 12) {
            $formattedValue .= '-' . substr($value, 12, 2);
        }

        return $formattedValue;
    }

    public static function FormatarRG($text)
    {
        $value = preg_replace('/\D/', '', $text);

        $formattedValue = "";

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 2);
        }
        if (strlen($value) > 2) {
            $formattedValue .= "." . substr($value, 2, 3);
        }
        if (strlen($value) > 5) {
            $formattedValue .= "." . substr($value, 5, 3);
        }
        if (strlen($value) > 8) {
            $formattedValue .= "-" . substr($value, 8, 2);
        }

        return $formattedValue;
    }

    public static function FormatarRNE($text)
    {
        $firstChar = substr($text, 0, 1);
        $letter = preg_replace('/[0-9]/', '', $firstChar);

        $value = preg_replace('/\D/', '', $text);

        $formattedValue = "";

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 6);
        }
        if (strlen($value) > 6) {
            $formattedValue .= "-" . substr($value, 6, 1);
        }

        return strtoupper($letter . $formattedValue);
    }

    public static function FormatarTelefone($text) {
        $value = preg_replace('/\D/', '', $text);
        $result = "";

        if (strlen($value) > 10) {
            $text = substr($value, 0, -1);
        }

        if (strlen($value) >= 7) {
            $result = "(".substr($value, 0, 2).") ".substr($value, 2, 5)."-".substr($value, 7);
        }
        else if (strlen($value) > 2 && strlen($value) <= 7) {
            $result = "(".substr($value, 0, 2).") ".substr($value, 2, 7);
        }
        else if (strlen($value) <= 2) {
            $result = substr($value, 0, 2);
        }

        return $result;
    }

    public static function FormatarCEP(string $numero): string {
        $valorLimpo = preg_replace('/\D/', '', $numero);

        $tamanho = strlen($valorLimpo);

        if ($tamanho >= 8) {
            $primeiraParte = substr($valorLimpo, 0, 5);
            $segundaParte = substr($valorLimpo, 5, 3);
            
            return "{$primeiraParte}-{$segundaParte}";
            
        } elseif ($tamanho > 5) {
            return substr($valorLimpo, 0, 5) . substr($valorLimpo, 5);
        }
        
        return $valorLimpo;
    }

    public static function FormatarCarteirinha($text) {
        $value = preg_replace('/\D/', '', $text);

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 4);
        }
        if (strlen($value) >= 4) {
            $formattedValue .= '.' . substr($value, 4, 4);
        }
        if (strlen($value) >= 8) {
            $formattedValue .= '.' . substr($value, 8, 6);
        }
        if (strlen($value) >= 14) {
            $formattedValue .= '.' . substr($value, 14, 2);
        }
        if (strlen($value) >= 16) {
            $formattedValue .= '-' . substr($value, 16, 1);
        }

        return $formattedValue;
    }

    public static function FormatarNumGuia($numero) {
        $value = preg_replace('/\D/', '', $numero);

        $formattedValue = '';

        if (strlen($value) > 0) {
            $formattedValue .= substr($value, 0, 4);
        }
        if (strlen($value) >= 4) {
            $formattedValue .= '.' . substr($value, 4, 4);
        }
        if (strlen($value) >= 8) {
            $formattedValue .= '.' . substr($value, 8, 2);
        }
        if (strlen($value) >= 10) {
            $formattedValue .= '-' . substr($value, 10);
        }

        return $formattedValue;
    }
}

?>
