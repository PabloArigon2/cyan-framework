<?php

class Validate {
    public static function CPF($number) {
        $cpf = preg_replace('/[^0-9]/', "", $number);

        if (strlen($cpf) != 11 || preg_match('/([0-9])\1{10}/', $cpf)) {
            return false;
        }

        $number_quantity_to_loop = [9, 10];

        foreach ($number_quantity_to_loop as $item) {

            $sum = 0;
            $number_to_multiplicate = $item + 1;

            for ($index = 0; $index < $item; $index++) {

                $sum += $cpf[$index] * ($number_to_multiplicate--);

            }

            $result = (($sum * 10) % 11);

            if ($cpf[$item] != $result) {
                return false;
            }

        }

        return true;
    }

    public static function Conselho($conselho, $number, $uf, &$errorMsg = null) {
        $tipo = "";

        switch($conselho) {
            case Conselhos::CRM:
            case Conselhos::CRO:
            case Conselhos::OAB:
            case Conselhos::CRP:
            case Conselhos::CREA:
            case Conselhos::CAU:
            case Conselhos::CRN:
                $tipo = strtolower($conselho); 
                break;
            default:
                $tipo = null;
                break;
        }

        if (empty($tipo)) {
            $errorMsg = "Não foi possível validar o conselho!";
            return false;
        }
            

        $envKey = env("API_KEY_MEDICAL");

        if (empty($envKey)) {
            $errorMsg = "Não foi possível realizar validação!";
            return false;
        }

        if (empty($number)) {
            $errorMsg = "O Número do conselho especificado é inválido!";
            return false;
        }        

        $response = Utils::HttpRequest("https://www.consultacrm.com.br/api/index.php?tipo=$tipo&uf=$uf&q=$number&chave=$envKey&destino=json", ReqMethod::GET);

        if ($response['Status'] == 1) {
            $total = $response['Body']['total'];
            $status = $response['Body']['status'];

            if (Math::parseInt($total) <= 0) {
                $errorMsg = "O registro médico não foi encontrado! ";
                return false;
            }
            
            if ($status != "true"){
                $errorMsg = "Não foi possível validar o registro! E1";
                return false;
            }

            if ($response['Body']['item'][0]['situacao'] == "Falecido") {
                $errorMsg = "Esse registro pertence a um médico já falecido!";
                return false;
            }

            return true;
        }
        else {
            $errorMsg = "Não foi possível validar o registro! E2";
            return false;
        }
    }
}

final class Conselhos {
    public const CRM = "CRM";
    public const CRO = "CRO";
    public const OAB = "OAB";
    public const CRP = "CRP";
    public const CREA = "CREA";
    public const CAU = "CAU";
    public const CRN = "CRN";
}

?>