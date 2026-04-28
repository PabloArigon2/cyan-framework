<?php

namespace Modules\Payment;

class Ambients {
    public const PROD = 0;
    public const HMLG = 1;
}


class AsaasErrors {
    public static $NONE = -1;
    public static $INVALID_API_KEY = 0;
    public static $INVALID_PIX_KEY = 1;
    public static $NO_BALANCE = 2;
    public static $PIX_KEY_TYPE_INVALID = 4;
    public static $ERROR_ON_REQ_PROCESS = 5;
    public static $ERROR_ON_PROCESSMENT = 6;
    public static $ERROR_ON_PAYMENT = 7;
    public static $LIMIT_REACHED = 8;
    public static $TRANSFER_CANCELLED = 9;
    public static $PREVIOUS_ERROR = 10;
    public static $WEBHOOK_PROCESSMENT_ERROR = 11;
    public static $WEBHOOK_ALREADY_PROCESSED = 12;
    public static $WEBHOOK_TRANSFER_CANCELLED = 13;
    public static $WEBHOOK_SYNC_ERROR = 14;
    public static $WEBHOOK_DATA_DIVERGED = 15;
    public static $WEBHOOK_TRANSFER_PENDING = 16;
    public static $WEBHOOK_MANUAL_CHECK = 17;
    public static $WEBHOOK_TRANSFER_DONE = 18;
    public static $WEBHOOK_VALIDATE_ERROR = 19;
    public static $WEBHOOK_TRANSFER_BLOCKED = 20;
    public static $WEBHOOK_TRANSFER_FAILED = 21;
    public static $WEBHOOK_TRANSFER_BANK_PROCESSMENT = 22;
    public static $WEBHOOK_INCOMPATIBLE_DATA = 23;
    public static $INVALID_TRANSFER_VALUE = 24;
    public static $WEBHOOK_NOT_READY = 25;
    public static $NON_FATURED = 26;
    public static $FATURA_FAILED = 27;
    public static $DIVERGED_VALUE_PER_PROF = 28;
    public static $DIVERGED_VALUE_TOTAL = 29;
    public static $OUT_OF_MEDIA_PAYMENTS = 30;
    public static $PAYMENT_VALUE_EXCEEDED = 31;
    public static $DISABLED_MODULE = 32;
    public static $MAINTENANCE = 33;
}

final class AsaasURL {
    public const PROD = 'api.asaas.com';
    public const HMLG = 'api-sandbox.asaas.com';
}

class Asaas {
    private static string $apiKey = '';
    private static int $ambient = Ambients::HMLG;

    public static function Initialize(int $ambient = Ambients::HMLG, string $apiKey = "") {
        self::$apiKey = $apiKey;
        self::$ambient = $ambient;
    }

    public static function GetAmbient() {
        return self::$ambient;
    }

    public static function SetAmbient(int $ambient) {
        self::$ambient = $ambient;
    }

    public static function IsKeyValid($key = "") {
        $ambientPrefix = "";

        if (empty($key)) {
            $key = self::$apiKey;
        }

        switch(self::GetAmbient()) {
            case Ambients::PROD: $ambientPrefix = "prod"; break;
            case Ambients::HMLG: $ambientPrefix = "hmlg"; break;
        }

        if (!empty($key)) {
            if (strpos($key, $ambientPrefix) !== false) { return true; }
        }

        return false;
    }

    public static function SetApiKey(string $key) { self::$apiKey = $key; }

    public static function GetApiKey() { return self::$apiKey; }

    private static function Process($data) {
        $result = array();
        $headersStr = $data['headers'] ?? "";

        if (gettype($data) == "string") {
            $data = json_decode($data, true);
        }

        if (isset($data['body']) and gettype($data['body']) == "string" and strpos($data['body'], "The requested URL returned error") !== false) {
            $result['status'] = 0;
            $result['errCode'] = str_replace(" ", "", explode(":", $data['body'])[1]);
            $result['description'] = $data;
        }
        else {
            if (isset($data['body'])) {
                $params = [];

                if (gettype($data['body']) == "string") {
                    $params = json_decode($data['body'], true);
                }
                else if (gettype($data['body']) == "array" or gettype($data['body']) == "object") {
                    $params = $data['body'];
                }

                if (isset($params['errors'])) {
                    $result['status'] = 0;
                    $result['errCode'] = $params['errors'][0]['code'];
                    $result['description'] = $params['errors'][0]['description'];
                }
                else {
                    $result = $params;

                    if (isset($result['status'])) {
                        $result['statusRequest'] = $result['status'];
                    }

                    $result['status'] = 1;
                }
            }
            else {
                if (isset($data['errors'])) {
                    $result['status'] = 0;
                    $result['errCode'] = $data['errors'][0]['code'];
                    $result['description'] = $data['errors'][0]['description'];
                }
                else {
                    $result = $data;

                    if (isset($result['status'])) {
                        $result['statusRequest'] = $result['status'];
                    }

                    $result['status'] = 1;
                }
            }
        }

        if ($headersStr and $headersStr != "") {
            $headers = [];
            $output = rtrim($headersStr);
            $data = explode("\n",$output);
            $headers['status'] = $data[0];
            array_shift($data);

            foreach($data as $part){

                //some headers will contain ":" character (Location for example), and the part after ":" will be lost, Thanks to @Emanuele
                $middle = explode(":",$part,2);

                //Supress warning message if $middle[1] does not exist, Thanks to @crayons
                if ( !isset($middle[1]) ) { $middle[1] = null; }

                $headers[trim($middle[0])] = trim($middle[1]);
            }

            $result['headers'] = $headers;
        }
        else {
            $result['headers'] = [];
        }

        return $result;
    }

    private static function SendRequest($api, string $req = \ReqMethod::POST, $fields = array()) {

        $url = 'https://'.(self::GetAmbient() == Ambients::PROD ? AsaasURL::PROD : AsaasURL::HMLG)."/v3/".$api;

        if (!self::IsKeyValid()) { return [ "body" => [ "errors" => [ [ "code" => 0, "description" => "API Key inválida!" ] ] ], "headers" => [] ]; }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $req,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'access_token: '.self::$apiKey,
                'content-type: application/json'
            ],
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
        ));

        if ($fields != "" && $fields != null && (gettype($fields) == "array" or gettype($fields) == "object")) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($fields));
        }

        $response = curl_exec($curl);

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $err = curl_error($curl);
        curl_close($curl);

        return [ "body" => $body, "headers" => $header ];
    }

    public static function GeneratePayment($nome, $description, $reference, $value = 0.0, string $chargeType = "DETACHED", string $cycle = "", int $maxParcelas = 1) {
        $callback = [
            'successUrl' => '',
            'autoRedirect' => true
        ];

        if ($value <= 0.1) {
            return null;
        }

        $endDate = date("Y-m-d", strtotime("+2 days"));

        $req = self::SendRequest("paymentLinks", \ReqMethod::POST, [
            'billingType' => BillingType::ALL,
            'chargeType' => $chargeType,
            'name' => $nome,
            'description' => $description,
            'endDate' => $endDate,
            'value' => $value,
            'dueDateLimitDays' => 5,
            'subscriptionCycle' => $cycle,
            'maxInstallmentCount' => $maxParcelas,
            'externalReference' => $reference,
            'notificationEnabled' => true,
            'callback' => $callback
        ]);

        $req = self::Process($req);

        return $req;
    }

    public static function GetPayment($id = "") {
        $req = self::SendRequest("paymentLinks/".$id, \ReqMethod::GET);
        $req = self::Process($req);
        return $req;
    }

    public static function RemovePayment($id = "") {
        $req = self::SendRequest("paymentLinks/".$id, \ReqMethod::DELETE);
        $req = self::Process($req);
        return $req;
    }

    public static function CreateCheckout($reference = "", array $items = [], array $chargeType = [], array $billingType = [], \User|null $customer = null, array $subscription = [], string $url = "") {
        $minutes = 30;

        $arrItems = [];

        $callback = [
            'successUrl' => $url.'/callback/success',
            'cancelUrl' => $url.'/callback/cancel'
        ];

        foreach($items as $item) {
            if ($item instanceof CheckoutItem) {
                $arrItems[] = $item->Build();
            }
            else {
                $arrItems[] = $item;
            }
        }

        $fields = [
            'billingTypes' => $billingType,
            'chargeTypes' => $chargeType,
            'callback' => $callback,
            'items' => $arrItems,
            'minutesToExpire' => $minutes,
            'externalReference' => $reference
        ];

        if (!empty($subscription)) {
            $fields['subscription'] = $subscription;
        }

        if ($customer != null and $customer instanceof \User) {
            $fields['customerData'] = [
                'name' => $customer->Nome,
                'cpfCnpj' => $customer->CPF,
                'email' => $customer->Email,
                'phone' => $customer->Telefone1,
                'address' => $customer->Endereco['Endereco'],
                'addressNumber' => $customer->Endereco['Numero'],
                'complement' => $customer->Endereco['Complemento'],
                'province' => $customer->Endereco['Bairro'],
                'postalCode' => $customer->Endereco['CEP'],
                'city' => $customer->Endereco['Cidade']
            ];
        }

        $req = self::SendRequest("checkouts", \ReqMethod::POST, $fields);
        $req = self::Process($req);
        return $req;
    }

    public static function CancelCheckout($id) {
        $req = self::SendRequest("checkouts/".$id."/cancel", \ReqMethod::POST);
        $req = self::Process($req);
        return $req;
    }
}

final class BillingType {
    public const ALL = "UNDEFINED";
    public const BOLETO = "BOLETO";
    public const CREDIT_CARD = "CREDIT_CARD";
    public const PIX = "PIX";
}

final class CheckoutItem {
    public string $externalReference = '';
    public string $description = '';
    public string $image = '';
    public string $name = '';
    public int $qtd = 0;
    public float $value = 0.0;

    function __construct(string $reference = "", string $name = "", string $desc = "", float $value = 0.0, int $qtd = 0, $image = '')
    {
        $this->externalReference = $reference;
        $this->description = $desc;
        $this->name = $name;
        $this->image = $image;
        $this->qtd = $qtd;
        $this->value = $value;
    }

    public function Build() {
        $result = [
            'externalReference' => $this->externalReference,
            'description' => $this->description,
            'name' => $this->name,
            'quantity' => $this->qtd,
            'value' => $this->value
        ];

        if (!empty($this->image)) {
            $result['imageBase64'] = $this->image;
        }
        
        return $result;
    }

    public static function Construct(string | array $str) {
        $arr = [];
        $item = new CheckoutItem();

        if (gettype($str) == "string") {
            $arr = json_decode($str, true);
        }
        else if (gettype($str) == "array") {
            $arr = $str;
        }
        else {
            return null;
        }

        $item->externalReference = $arr['externalReference'];
        $item->description = $arr['description'];
        $item->image = $arr['imageBase64'];
        $item->name = $arr['name'];
        $item->qtd = \Math::parseInt($arr['quantity']);
        $item->value = \Math::parseDouble($arr['value']);

        return $item;
    }
}

final class ChargeType {
    public const AVULSO = "DETACHED";
    public const RECORRENTE = "RECURRENT";
    public const PARCELA = "INSTALLMENT";
}

final class SubscriptionCycle {
    public const SEMANAL = "WEEKLY";
    public const MENSAL = "MONTHLY";
    public const SEMESTRAL = "SEMIANNUALLY";
    public const ANUAL = "YEARLY";
}

class Cobrancas {
    public static function GetPayment(int $id = 0, string $reference = "", string $paymentID = "") {
        $sql = \Database::Query("SELECT * FROM pagamentos WHERE id = ? OR reference = ? OR id_pagamento = ?", [
            new \Parameter("i", $id),
            new \Parameter("s", $reference),
            new \Parameter("s", \Security::Encrypt($paymentID))
        ]);

        return $sql->validQuery() ? $sql->get(0) : null;
    }
}

final class PAYMENT
{
    public const CREATED = 'PAYMENT_CREATED';
    public const AWAITING_RISK_ANALYSIS = 'PAYMENT_AWAITING_RISK_ANALYSIS';
    public const APPROVED_BY_RISK_ANALYSIS = 'PAYMENT_APPROVED_BY_RISK_ANALYSIS';
    public const REPROVED_BY_RISK_ANALYSIS = 'PAYMENT_REPROVED_BY_RISK_ANALYSIS';
    public const AUTHORIZED = 'PAYMENT_AUTHORIZED';
    public const UPDATED = 'PAYMENT_UPDATED';
    public const CONFIRMED = 'PAYMENT_CONFIRMED';
    public const RECEIVED = 'PAYMENT_RECEIVED';
    public const CREDIT_CARD_CAPTURE_REFUSED = 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED';
    public const ANTICIPATED = 'PAYMENT_ANTICIPATED';
    public const OVERDUE = 'PAYMENT_OVERDUE';
    public const DELETED = 'PAYMENT_DELETED';
    public const RESTORED = 'PAYMENT_RESTORED';
    public const REFUNDED = 'PAYMENT_REFUNDED';
    public const PARTIALLY_REFUNDED = 'PAYMENT_PARTIALLY_REFUNDED';
    public const REFUND_IN_PROGRESS = 'PAYMENT_REFUND_IN_PROGRESS';
    public const RECEIVED_IN_CASH_UNDONE = 'PAYMENT_RECEIVED_IN_CASH_UNDONE';
    public const CHARGEBACK_REQUESTED = 'PAYMENT_CHARGEBACK_REQUESTED';
    public const CHARGEBACK_DISPUTE = 'PAYMENT_CHARGEBACK_DISPUTE';
    public const AWAITING_CHARGEBACK_REVERSAL = 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL';
    public const DUNNING_RECEIVED = 'PAYMENT_DUNNING_RECEIVED';
    public const DUNNING_REQUESTED = 'PAYMENT_DUNNING_REQUESTED';
    public const BANK_SLIP_VIEWED = 'PAYMENT_BANK_SLIP_VIEWED';
    public const CHECKOUT_VIEWED = 'PAYMENT_CHECKOUT_VIEWED';
    public const SPLIT_CANCELLED = 'PAYMENT_SPLIT_CANCELLED';
    public const SPLIT_DIVERGENCE_BLOCK = 'PAYMENT_SPLIT_DIVERGENCE_BLOCK';
    public const SPLIT_DIVERGENCE_BLOCK_FINISHED = 'PAYMENT_SPLIT_DIVERGENCE_BLOCK_FINISHED';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class SUBSCRIPTION
{
    public const CREATED = 'SUBSCRIPTION_CREATED';
    public const UPDATED = 'SUBSCRIPTION_UPDATED';
    public const INACTIVATED = 'SUBSCRIPTION_INACTIVATED';
    public const DELETED = 'SUBSCRIPTION_DELETED';
    public const SPLIT_DIVERGENCE_BLOCK = 'SUBSCRIPTION_SPLIT_DIVERGENCE_BLOCK';
    public const SPLIT_DIVERGENCE_BLOCK_FINISHED = 'SUBSCRIPTION_SPLIT_DIVERGENCE_BLOCK_FINISHED';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class INVOICE
{
    public const CREATED = 'INVOICE_CREATED';
    public const UPDATED = 'INVOICE_UPDATED';
    public const SYNCHRONIZED = 'INVOICE_SYNCHRONIZED';
    public const AUTHORIZED = 'INVOICE_AUTHORIZED';
    public const PROCESSING_CANCELLATION = 'INVOICE_PROCESSING_CANCELLATION';
    public const CANCELED = 'INVOICE_CANCELED';
    public const CANCELLATION_DENIED = 'INVOICE_CANCELLATION_DENIED';
    public const ERROR = 'INVOICE_ERROR';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class TRANSFER
{
    public const CREATED = 'TRANSFER_CREATED';
    public const PENDING = 'TRANSFER_PENDING';
    public const IN_BANK_PROCESSING = 'TRANSFER_IN_BANK_PROCESSING';
    public const BLOCKED = 'TRANSFER_BLOCKED';
    public const DONE = 'TRANSFER_DONE';
    public const FAILED = 'TRANSFER_FAILED';
    public const CANCELLED = 'TRANSFER_CANCELLED';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class BILL
{
    public const CREATED = 'BILL_CREATED';
    public const PENDING = 'BILL_PENDING';
    public const BANK_PROCESSING = 'BILL_BANK_PROCESSING';
    public const PAID = 'BILL_PAID';
    public const CANCELLED = 'BILL_CANCELLED';
    public const FAILED = 'BILL_FAILED';
    public const REFUNDED = 'BILL_REFUNDED';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class ANTECIPATION
{
    public const ANTICIPATION_CANCELLED = 'RECEIVABLE_ANTICIPATION_CANCELLED';
    public const ANTICIPATION_SCHEDULED = 'RECEIVABLE_ANTICIPATION_SCHEDULED';
    public const ANTICIPATION_PENDING = 'RECEIVABLE_ANTICIPATION_PENDING';
    public const ANTICIPATION_CREDITED = 'RECEIVABLE_ANTICIPATION_CREDITED';
    public const ANTICIPATION_DEBITED = 'RECEIVABLE_ANTICIPATION_DEBITED';
    public const ANTICIPATION_DENIED = 'RECEIVABLE_ANTICIPATION_DENIED';
    public const ANTICIPATION_OVERDUE = 'RECEIVABLE_ANTICIPATION_OVERDUE';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class ACCOUNT
{
    public const STATUS_BANK_ACCOUNT_INFO_APPROVED = 'ACCOUNT_STATUS_BANK_ACCOUNT_INFO_APPROVED';
    public const STATUS_BANK_ACCOUNT_INFO_AWAITING_APPROVAL = 'ACCOUNT_STATUS_BANK_ACCOUNT_INFO_AWAITING_APPROVAL';
    public const STATUS_BANK_ACCOUNT_INFO_PENDING = 'ACCOUNT_STATUS_BANK_ACCOUNT_INFO_PENDING';
    public const STATUS_BANK_ACCOUNT_INFO_REJECTED = 'ACCOUNT_STATUS_BANK_ACCOUNT_INFO_REJECTED';
    public const STATUS_COMMERCIAL_INFO_APPROVED = 'ACCOUNT_STATUS_COMMERCIAL_INFO_APPROVED';
    public const STATUS_COMMERCIAL_INFO_AWAITING_APPROVAL = 'ACCOUNT_STATUS_COMMERCIAL_INFO_AWAITING_APPROVAL';
    public const STATUS_COMMERCIAL_INFO_PENDING = 'ACCOUNT_STATUS_COMMERCIAL_INFO_PENDING';
    public const STATUS_COMMERCIAL_INFO_REJECTED = 'ACCOUNT_STATUS_COMMERCIAL_INFO_REJECTED';
    public const STATUS_DOCUMENT_APPROVED = 'ACCOUNT_STATUS_DOCUMENT_APPROVED';
    public const STATUS_DOCUMENT_AWAITING_APPROVAL = 'ACCOUNT_STATUS_DOCUMENT_AWAITING_APPROVAL';
    public const STATUS_DOCUMENT_PENDING = 'ACCOUNT_STATUS_DOCUMENT_PENDING';
    public const STATUS_DOCUMENT_REJECTED = 'ACCOUNT_STATUS_DOCUMENT_REJECTED';
    public const STATUS_GENERAL_APPROVAL_APPROVED = 'ACCOUNT_STATUS_GENERAL_APPROVAL_APPROVED';
    public const STATUS_GENERAL_APPROVAL_AWAITING_APPROVAL = 'ACCOUNT_STATUS_GENERAL_APPROVAL_AWAITING_APPROVAL';
    public const STATUS_GENERAL_APPROVAL_PENDING = 'ACCOUNT_STATUS_GENERAL_APPROVAL_PENDING';
    public const STATUS_GENERAL_APPROVAL_REJECTED = 'ACCOUNT_STATUS_GENERAL_APPROVAL_REJECTED';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

final class CHECKOUT
{
    public const CREATED = 'CHECKOUT_CREATED';
    public const CANCELED = 'CHECKOUT_CANCELED';
    public const EXPIRED = 'CHECKOUT_EXPIRED';
    public const PAID = 'CHECKOUT_PAID';

    public static function ToArray(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}

?>
