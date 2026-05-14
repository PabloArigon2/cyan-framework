<?php

namespace Modules\Payment\Stripe;

use Stripe\Account;
use Stripe\Price;

final class Ambients {
    public const PRODUCAO = 'producao';
    public const TESTE = 'teste';
}

final class Intervals {
    public const DIARIO = 'day';
    public const MENSAL = 'month';
    public const SEMANAL = 'week';
    public const ANUAL = 'year';
    public const NONE = '';
}

final class TaxBehaviour {
    public const EXCLUSIVE = 'exclusive';
    public const INCLUSIVE = 'inclusive';
    public const UNSPECIFIED = 'unspecified';
}

final class BusinessType {
    public const EMPRESA = 'company';
    public const ENTIDADE_GOVERNAMENTAL = 'government_entity';
    public const INDIVIDUAL = 'individual';
    public const NAO_LUCRATIVA = 'non_profit';
}

final class Controllers {
    public const CONTA = 'account';
    public const PLATAFORMA = 'application';
    public const STRIPE = 'stripe';
}

class AccountOwner {
    public $email = '';
}

class Stripe {
    private $ApiKey = '';
    private $Ambiente = '';
    private ?\Stripe\StripeClient $Client = null;

    function __construct(string $apiKey, string $ambient = Ambients::TESTE)
    {
        if ($ambient == Ambients::TESTE and !str_contains(strtolower($apiKey), 'test')) {
            throw new \Exception("Erro! A chave de API especificada não corresponde ao ambiente de teste!");    
        }

        $this->ApiKey = $apiKey;
        $this->Ambiente = $ambient;
        $this->Client = new \Stripe\StripeClient($apiKey);
    }

    public function CreateToken(string $businessType = BusinessType::EMPRESA, \Tenant|null $empresa = null, \User|null $userIndividual = null) {
        $body = [ 'account' => [
            'business_type' => $businessType
        ]];

        if (!empty($empresa) and ($empresa instanceof \Tenant)) {
            $body['account']['company'] = [
                'address' => [
                    'city' => $empresa->Endereco['Cidade'],
                    'country' => 'BR',
                    'line1' => $empresa->Endereco['Endereco'],
                    'line2' => $empresa->Endereco['Complemento'],
                    'postal_code' => $empresa->Endereco['CEP'],
                    'state' => $empresa->Endereco['Estado']
                ],
                'name' => $empresa->RazaoSocial,
                'phone' => $empresa->Contato['Telefone'],
                'registration_number' => preg_replace("/[^0-9]/", "", $empresa->CNPJ)
            ];
        }
    }

    public function CreateAccount(string $businessType = BusinessType::EMPRESA, \Tenant|null $empresa = null, string $feeController = Controllers::CONTA, string $lossesController = Controllers::STRIPE, string $requirement_collection = Controllers::STRIPE, string $countryISO = 'BR', AccountOwner $owner = new AccountOwner()) {

    }
}

?>