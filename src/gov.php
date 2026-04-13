<?php

class GovApi {
    public static function GetCNES($cnes = 0) {

        if (!$cnes) { return null; }

        $buscar = function($cnes) {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://apidadosabertos.saude.gov.br/cnes/estabelecimentos/'.$cnes,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    "accept" => "application/json"
                ],
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
                //CURLOPT_FAILONERROR => true
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            return [ "response" => $response, "code" => $code ];
        };

        $data = $buscar($cnes);

        if ($data['code'] == 200) {
            return [ "Status" => 1, "Response" => json_decode($data['response'], true) ];
        }
        else {
            return [ "Status" => 0, "ErrCode" => $data['code'], "Response" => json_decode($data['response'], true) ];
        }
    }
}

?>