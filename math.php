<?php

class Math {
    public static function parseInt($value) {
        
        if (gettype($value) == "string") {
            $value = str_replace(' ', '', $value);
        }

        return is_numeric($value) ? intval($value) : 0;
    }

    public static function getAge($dataNascimento) {
        // 1. Cria um objeto DateTime para a data de nascimento
        try {
            $nascimento = new DateTime($dataNascimento);
        } catch (Exception $e) {
            // Retorna 0 ou lança uma exceção se a data for inválida
            return 0; 
        }
        
        // 2. Cria um objeto DateTime para a data atual
        $hoje = new DateTime();
        
        // 3. Calcula a diferença entre as duas datas (DateInterval)
        $diferenca = $hoje->diff($nascimento);
        
        // 4. Retorna a diferença em anos
        return $diferenca->y;
    }

    public static function parseDouble($value) {

        if (gettype($value) == "string") {
            $value = str_replace(' ', '', $value);
        }
        
        return is_numeric($value) ? doubleval($value) : 0.0;
    }

    public static function parseFloat($value) {

        if (gettype($value) == "string") {
            $value = str_replace(' ', '', $value);
        }

        return is_numeric($value) ? floatval($value) : 0.0;
    }

    public static function formatCurrency($value, $cipher = true, $decimal = 2) {
        // Remove qualquer formatação existente

        if (gettype($value) == "string") {
            $value = str_replace(' ', '', $value);
        }

        $val = $value;
        if (gettype($value) == "string") {
            $val = Math::parseDouble($value);
        }

        // Formata com 2 casas decimais, separador de milhares e decimal
        return ($cipher ? "R$ " : "").number_format($val, $decimal, ',', '.');
    }

    public static function formatGrow($v) {
        $r = Math::parseDouble($v);

        return number_format($r, "2", ",", ".")."%";
    }

    public static function calculateGrow($v1, $v2, $format = false) {
        
        if ($v1 != 0) {
            $calc = (($v2 - $v1) / $v1) * 100;
        }
        else {
            $calc = 0;
        }

        return $format 
        ? number_format($calc, "2", ",", ".")."%"
        : $calc;
    }

    public static function formatNumeric($val) {
        $valor = $val;
        $valor = mb_convert_encoding($valor, 'UTF-8', 'UTF-8');
        $valor = str_replace("R$", "", $valor);
        $valor = str_replace(".", "", $valor);
        $valor = str_replace(",", ".", $valor);
        $valor = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\s]+/u', '', $valor);
        $valor = str_replace(["\n", "\r", "\t"], "", $valor);
        $valor = Math::parseDouble($valor);
        return $valor;
    }
}

?>