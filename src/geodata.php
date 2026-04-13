<?php

final class GeoData {
    public static function GetUfs() {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
    }

    public static function SearchUf($uf) {
        $ufs = self::GetUfs();
        return $ufs[$uf] ?? null;
    }

    public static function GetCountries() {
        return [
            "AF" => "Afeganistão", "ZA" => "África do Sul", "AL" => "Albânia", "DE" => "Alemanha",
            "AD" => "Andorra", "AO" => "Angola", "AI" => "Anguilla", "AQ" => "Antártida",
            "AG" => "Antígua e Barbuda", "SA" => "Arábia Saudita", "DZ" => "Argélia", "AR" => "Argentina",
            "AM" => "Arménia", "AW" => "Aruba", "AU" => "Austrália", "AT" => "Áustria",
            "AZ" => "Azerbaijão", "BS" => "Bahamas", "BD" => "Bangladesh", "BB" => "Barbados",
            "BH" => "Bahrein", "BE" => "Bélgica", "BZ" => "Belize", "BJ" => "Benim",
            "BM" => "Bermudas", "BY" => "Bielorrússia", "BO" => "Bolívia", "BA" => "Bósnia e Herzegovina",
            "BW" => "Botsuana", "BR" => "Brasil", "BN" => "Brunei", "BG" => "Bulgária",
            "BF" => "Burquina Faso", "BI" => "Burundi", "BT" => "Butão", "CV" => "Cabo Verde",
            "KH" => "Camboja", "CM" => "Camarões", "CA" => "Canadá", "QA" => "Catar",
            "KZ" => "Cazaquistão", "TD" => "Chade", "CL" => "Chile", "CN" => "China",
            "CY" => "Chipre", "CO" => "Colômbia", "KM" => "Comores", "CG" => "Congo - Brazzaville",
            "CD" => "Congo - Kinshasa", "KP" => "Coreia do Norte", "KR" => "Coreia do Sul",
            "CI" => "Costa do Marfim", "CR" => "Costa Rica", "HR" => "Croácia", "CU" => "Cuba",
            "DK" => "Dinamarca", "DJ" => "Djibuti", "DM" => "Dominica", "EG" => "Egito",
            "SV" => "El Salvador", "AE" => "Emirados Árabes Unidos", "EC" => "Equador", "ER" => "Eritreia",
            "SK" => "Eslováquia", "SI" => "Eslovénia", "ES" => "Espanha", "US" => "Estados Unidos",
            "EE" => "Estónia", "ET" => "Etiópia", "FJ" => "Fiji", "PH" => "Filipinas",
            "FI" => "Finlândia", "FR" => "França", "GA" => "Gabão", "GM" => "Gâmbia",
            "GH" => "Gana", "GE" => "Geórgia", "GI" => "Gibraltar", "GD" => "Granada",
            "GR" => "Grécia", "GL" => "Gronelândia", "GP" => "Guadalupe", "GU" => "Guam",
            "GT" => "Guatemala", "GY" => "Guiana", "GF" => "Guiana Francesa", "GN" => "Guiné",
            "GQ" => "Guiné Equatorial", "GW" => "Guiné-Bissau", "HT" => "Haiti", "HN" => "Honduras",
            "HK" => "Hong Kong, RAE da China", "HU" => "Hungria", "YEM" => "Iémen", "IN" => "Índia",
            "ID" => "Indonésia", "IR" => "Irão", "IQ" => "Iraque", "IE" => "Irlanda",
            "IS" => "Islândia", "IL" => "Israel", "IT" => "Itália", "JM" => "Jamaica",
            "JP" => "Japão", "JO" => "Jordânia", "KW" => "Kuwait", "LA" => "Laos",
            "LS" => "Lesoto", "LV" => "Letónia", "LB" => "Líbano", "LR" => "Libéria",
            "LY" => "Líbia", "LI" => "Liechtenstein", "LT" => "Lituânia", "LU" => "Luxemburgo",
            "MO" => "Macau, RAE da China", "MK" => "Macedónia do Norte", "MG" => "Madagáscar",
            "MY" => "Malásia", "MW" => "Maláui", "MV" => "Maldivas", "ML" => "Mali",
            "MT" => "Malta", "MA" => "Marrocos", "MQ" => "Martinica", "MU" => "Maurícia",
            "MR" => "Mauritânia", "MX" => "México", "MM" => "Mianmar (Birmânia)", "FM" => "Micronésia",
            "MZ" => "Moçambique", "MD" => "Moldávia", "MC" => "Mónaco", "MN" => "Mongólia",
            "ME" => "Montenegro", "MS" => "Monserrate", "NA" => "Namíbia", "NR" => "Nauru",
            "NP" => "Nepal", "NI" => "Nicarágua", "NE" => "Níger", "NG" => "Nigéria",
            "NU" => "Niue", "NO" => "Noruega", "NC" => "Nova Caledónia", "NZ" => "Nova Zelândia",
            "OM" => "Omã", "NL" => "Países Baixos", "PW" => "Palau", "PA" => "Panamá",
            "PG" => "Papua-Nova Guiné", "PK" => "Paquistão", "PY" => "Paraguai", "PE" => "Peru",
            "PF" => "Polinésia Francesa", "PL" => "Polónia", "PR" => "Porto Rico", "PT" => "Portugal",
            "KE" => "Quénia", "KG" => "Quirguistão", "KI" => "Quiribati", "GB" => "Reino Unido",
            "CF" => "República Centro-Africana", "CZ" => "República Checa", "DO" => "República Dominicana",
            "RE" => "Reunião", "RO" => "Roménia", "RW" => "Ruanda", "RU" => "Rússia",
            "EH" => "Saara Ocidental", "WS" => "Samoa", "AS" => "Samoa Americana", "SM" => "San Marino",
            "SH" => "Santa Helena", "LC" => "Santa Lúcia", "KN" => "São Cristóvão e Neves",
            "ST" => "São Tomé e Príncipe", "VC" => "São Vicente e Granadinas", "SN" => "Senegal",
            "SL" => "Serra Leoa", "RS" => "Sérvia", "SC" => "Seicheles", "SG" => "Singapura",
            "SY" => "Síria", "SO" => "Somália", "LK" => "Sri Lanka", "SZ" => "Suazilândia",
            "SD" => "Sudão", "SS" => "Sudão do Sul", "SE" => "Suécia", "CH" => "Suíça",
            "SR" => "Suriname", "TH" => "Tailândia", "TW" => "Taiwan", "TJ" => "Tajiquistão",
            "TZ" => "Tanzânia", "TL" => "Timor-Leste", "TG" => "Togo", "TK" => "Tokelau",
            "TO" => "Tonga", "TT" => "Trindade e Tobago", "TN" => "Tunísia", "TR" => "Turquia",
            "TM" => "Turquemenistão", "UA" => "Ucrânia", "UG" => "Uganda", "UY" => "Uruguai",
            "UZ" => "Usbequistão", "VU" => "Vanuatu", "VA" => "Cidade do Vaticano", "VE" => "Venezuela",
            "VN" => "Vietname", "WF" => "Wallis e Futuna", "ZM" => "Zâmbia", "ZW" => "Zimbabué"
        ];
    }
}
