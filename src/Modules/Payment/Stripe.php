<?php

namespace Modules\Payment\Stripe;

use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Util\RequestOptions;

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

final class BusinessType {
    public const EMPRESA = 'company';
    public const INDIVIDUAL = 'individual';
    public const ENTIDADE_GOVERNAMENTAL = 'government_entity';
    public const NAO_LUCRATIVA = 'non_profit';
}

final class StripeMode {
    public const PAYMENT = 'payment';
    public const SETUP = 'setup'; //em caso de trial, ele digita o cartão mas não paga ainda, apenas após algum tempo
    public const SUBSCRIPTION = 'subscription';
}

// ==========================================
// Módulos de Domínio (Classes Secundárias)
// ==========================================

class StripeProducts {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    /**
     * Creates a new product object.
     *
     * @param null|array{active?: bool, default_price_data?: array{currency: string, currency_options?: array<string, array{custom_unit_amount?: array{enabled: bool, maximum?: int, minimum?: int, preset?: int}, tax_behavior?: string, tiers?: (array{flat_amount?: int, flat_amount_decimal?: string, unit_amount?: int, unit_amount_decimal?: string, up_to: array|int|string})[], unit_amount?: int, unit_amount_decimal?: string}>, custom_unit_amount?: array{enabled: bool, maximum?: int, minimum?: int, preset?: int}, metadata?: array<string, string>, recurring?: array{interval: string, interval_count?: int}, tax_behavior?: string, unit_amount?: int, unit_amount_decimal?: string}, description?: string, expand?: string[], id?: string, images?: string[], marketing_features?: array{name: string}[], metadata?: array<string, string>, name: string, package_dimensions?: array{height: float, length: float, weight: float, width: float}, shippable?: bool, statement_descriptor?: string, tax_code?: string, type?: string, unit_label?: string, url?: string} $params
     *      *
     * @return \Stripe\Product
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     */
    public function createProduct(array $params) : \Stripe\Product {
        return $this->client->products->create($params);
    }

    /**
     * Cria um preço vinculado a um produto
     * @param array{
     *   product?: string,
     *   product_data?: array{name: string, active?: bool, metadata?: array<string, string>},
     *   unit_amount: int,
     *   currency?: string,
     *   recurring?: array{interval: string, interval_count?: int},
     *   metadata?: array<string, string>
     * } $params
     */
    public function createPrice(array $params) {
        if (!isset($params['currency'])) $params['currency'] = 'brl';
        return $this->client->prices->create($params);
    }
}

class StripeCustomers {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    /**
     * Cadastra um novo cliente
     * @param array{
     *   email: string,
     *   name?: string,
     *   phone?: string,
     *   address?: array{
     *     line1: string,
     *     line2?: string,
     *     city: string,
     *     state: string,
     *     postal_code: string,
     *     country: string
     *   },
     *   metadata?: array<string, string>
     * } $params
     */
    public function createCustomer(array $params) {
        return $this->client->customers->create($params);
    }

    public function getCustomer(string $customerId) {
        return $this->client->customers->retrieve($customerId);
    }
}

class StripeSubscriptions {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    /**
     * Cria uma assinatura recorrente para um cliente
     * @param array{
     *   customer: string,
     *   items: array<int, array{
     *     price?: string,
     *     price_data?: array{currency: string, product: string, unit_amount: int, recurring: array{interval: string}},
     *     quantity?: int
     *   }>,
     *   trial_period_days?: int,
     *   collection_method?: string,
     *   days_until_due?: int,
     *   metadata?: array<string, string>
     * } $params
     */
    public function createSubscription(array $params) {
        return $this->client->subscriptions->create($params);
    }

    public function cancelSubscription(string $subscriptionId) {
        return $this->client->subscriptions->cancel($subscriptionId);
    }

    public function getSubscription(string $subscriptionId) {
        return $this->client->subscriptions->retrieve($subscriptionId);
    }
}

class StripeCheckout {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    /**
     * Cria um Link de Pagamento reutilizável
     * @param array{
     *   line_items: array<int, array{price: string, quantity: int, adjustable_quantity?: array{enabled: bool, minimum?: int, maximum?: int}}>,
     *   after_completion?: array{
     *     type: string,
     *     redirect?: array{url: string},
     *     hosted_confirmation?: array{custom_message: string}
     *   },
     *   metadata?: array<string, string>
     * } $params
     */
    public function createPaymentLink(array $params) {
        return $this->client->paymentLinks->create($params);
    }

    /**
     * Cria uma sessão de checkout hospedada pela Stripe (página de pagamento)
     * @param array{
     *   line_items?: array<int, array{
     *     price?: string,
     *     price_data?: array{
     *       currency: string,
     *       unit_amount: int,
     *       product?: string,
     *       product_data?: array{name: string, description?: string, images?: array<int, string>, metadata?: array<string, string>, tax_code?: string},
     *       recurring?: array{interval: string, interval_count?: int}
     *     },
     *     quantity: int,
     *     adjustable_quantity?: array{enabled: bool, minimum?: int, maximum?: int},
     *     tax_rates?: array<int, string>
     *   }>,
     *   mode?: string,
     *   ui_mode?: string,
     *   return_url?: string,
     *   success_url?: string,
     *   cancel_url?: string,
     *   customer?: string,
     *   customer_email?: string,
     *   customer_creation?: string,
     *   client_reference_id?: string,
     *   discounts?: array<int, array{coupon?: string, promotion_code?: string}>,
     *   shipping_address_collection?: array{allowed_countries: array<int, string>},
     *   shipping_options?: array<int, array{shipping_rate: string}>,
     *   phone_number_collection?: array{enabled: bool},
     *   tax_id_collection?: array{enabled: bool},
     *   allow_promotion_codes?: bool,
     *   custom_text?: array{submit?: array{message: string}, shipping_address?: array{message: string}, terms_of_service_acceptance?: array{message: string}},
     *   invoice_creation?: array{enabled: bool, invoice_data?: array{description?: string, metadata?: array<string, string>}},
     *   payment_method_types?: array,
     *   payment_method_options?: array{card?: array{installments?: array{enabled: bool}, request_three_d_secure?: string}},
     *   payment_intent_data?: array{receipt_email?: string, setup_future_usage?: string, description?: string, metadata?: array<string, string>, transfer_data?: array{destination: string, amount?: int}, application_fee_amount?: int},
     *   subscription_data?: array{trial_period_days?: int, description?: string, metadata?: array<string, string>, transfer_data?: array{destination: string, amount_percent?: float}, application_fee_percent?: float},
     *   metadata?: array<string, string>
     * } $params
     */
    public function createSession(array $params) {
        if (!isset($params['mode'])) $params['mode'] = 'payment';
        return $this->client->checkout->sessions->create($params);
    }
}

class StripePayments {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    /**
     * Cria uma intenção de pagamento para ser processada via Elements/API
     * @param array{
     *   amount: int,
     *   currency?: string,
     *   customer?: string,
     *   description?: string,
     *   receipt_email?: string,
     *   setup_future_usage?: string,
     *   payment_method?: string,
     *   payment_method_options?: array{card?: array{installments?: array{plan?: array{count: int, interval: string, type: string}}, request_three_d_secure?: string}},
     *   transfer_data?: array{destination: string, amount?: int},
     *   application_fee_amount?: int,
     *   transfer_group?: string,
     *   metadata?: array<string, string>
     * } $params
     */
    public function createPaymentIntent(array $params) {
        if (!isset($params['currency'])) $params['currency'] = 'brl';
        return $this->client->paymentIntents->create($params);
    }

    /**
     * Realiza o reembolso de um pagamento
     * @param array{payment_intent: string, amount?: int, reason?: string} $params
     */
    public function refund(array $params) {
        return $this->client->refunds->create($params);
    }
}

class StripeAnalytics {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    public function getBalance(string $stripeAccountId = null) {        
        if ($stripeAccountId) {
            return $this->client->balance->retrieve([], ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->balance->retrieve();
    }

    public function getTransactions(int $limit = 100, string $stripeAccountId = null) {
        $params = ['limit' => $limit];
        if ($stripeAccountId) {
            return $this->client->balanceTransactions->all($params, ['stripe_account' => $stripeAccountId]);
        }
        return $this->client->balanceTransactions->all($params);
    }
}

class StripeConnect {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    /**
     * An Account is a representation of a company, individual or other entity that a
     * user interacts with. Accounts contain identifying information about the entity,
     * and configurations that store the features an account has access to. An account
     * can be configured as any or all of the following configurations: Customer,
     * Merchant and/or Recipient.
     *
     * @param null|array{account_token?: string, business_profile?: array{annual_revenue?: array{amount: int, currency: string, fiscal_year_end: string}, estimated_worker_count?: int, mcc?: string, minority_owned_business_designation?: string[], monthly_estimated_revenue?: array{amount: int, currency: string}, name?: string, product_description?: string, support_address?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string}, support_email?: string, support_phone?: string, support_url?: null|string, url?: string}, business_type?: string, capabilities?: array{acss_debit_payments?: array{requested?: bool}, affirm_payments?: array{requested?: bool}, afterpay_clearpay_payments?: array{requested?: bool}, alma_payments?: array{requested?: bool}, amazon_pay_payments?: array{requested?: bool}, app_distribution?: array{requested?: bool}, au_becs_debit_payments?: array{requested?: bool}, bacs_debit_payments?: array{requested?: bool}, bancontact_payments?: array{requested?: bool}, bank_transfer_payments?: array{requested?: bool}, billie_payments?: array{requested?: bool}, blik_payments?: array{requested?: bool}, boleto_payments?: array{requested?: bool}, card_issuing?: array{requested?: bool}, card_payments?: array{requested?: bool}, cartes_bancaires_payments?: array{requested?: bool}, cashapp_payments?: array{requested?: bool}, crypto_payments?: array{requested?: bool}, eps_payments?: array{requested?: bool}, fpx_payments?: array{requested?: bool}, gb_bank_transfer_payments?: array{requested?: bool}, giropay_payments?: array{requested?: bool}, grabpay_payments?: array{requested?: bool}, ideal_payments?: array{requested?: bool}, india_international_payments?: array{requested?: bool}, jcb_payments?: array{requested?: bool}, jp_bank_transfer_payments?: array{requested?: bool}, kakao_pay_payments?: array{requested?: bool}, klarna_payments?: array{requested?: bool}, konbini_payments?: array{requested?: bool}, kr_card_payments?: array{requested?: bool}, legacy_payments?: array{requested?: bool}, link_payments?: array{requested?: bool}, mb_way_payments?: array{requested?: bool}, mobilepay_payments?: array{requested?: bool}, multibanco_payments?: array{requested?: bool}, mx_bank_transfer_payments?: array{requested?: bool}, naver_pay_payments?: array{requested?: bool}, nz_bank_account_becs_debit_payments?: array{requested?: bool}, oxxo_payments?: array{requested?: bool}, p24_payments?: array{requested?: bool}, pay_by_bank_payments?: array{requested?: bool}, payco_payments?: array{requested?: bool}, paynow_payments?: array{requested?: bool}, payto_payments?: array{requested?: bool}, pix_payments?: array{requested?: bool}, promptpay_payments?: array{requested?: bool}, revolut_pay_payments?: array{requested?: bool}, samsung_pay_payments?: array{requested?: bool}, satispay_payments?: array{requested?: bool}, sepa_bank_transfer_payments?: array{requested?: bool}, sepa_debit_payments?: array{requested?: bool}, sofort_payments?: array{requested?: bool}, sunbit_payments?: array{requested?: bool}, swish_payments?: array{requested?: bool}, tax_reporting_us_1099_k?: array{requested?: bool}, tax_reporting_us_1099_misc?: array{requested?: bool}, transfers?: array{requested?: bool}, treasury?: array{requested?: bool}, twint_payments?: array{requested?: bool}, upi_payments?: array{requested?: bool}, us_bank_account_ach_payments?: array{requested?: bool}, us_bank_transfer_payments?: array{requested?: bool}, zip_payments?: array{requested?: bool}}, company?: array{address?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string}, address_kana?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string, town?: string}, address_kanji?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string, town?: string}, directors_provided?: bool, directorship_declaration?: array{date?: int, ip?: string, user_agent?: string}, executives_provided?: bool, export_license_id?: string, export_purpose_code?: string, name?: string, name_kana?: string, name_kanji?: string, owners_provided?: bool, ownership_declaration?: array{date?: int, ip?: string, user_agent?: string}, ownership_exemption_reason?: null|string, phone?: string, registration_date?: null|array{day: int, month: int, year: int}, registration_number?: string, representative_declaration?: array{date?: int, ip?: string, user_agent?: string}, structure?: null|string, tax_id?: string, tax_id_registrar?: string, vat_id?: string, verification?: array{document?: array{back?: string, front?: string}}}, controller?: array{fees?: array{payer?: string}, losses?: array{payments?: string}, requirement_collection?: string, stripe_dashboard?: array{type?: string}}, country?: string, default_currency?: string, documents?: array{bank_account_ownership_verification?: array{files?: string[]}, company_license?: array{files?: string[]}, company_memorandum_of_association?: array{files?: string[]}, company_ministerial_decree?: array{files?: string[]}, company_registration_verification?: array{files?: string[]}, company_tax_id_verification?: array{files?: string[]}, proof_of_address?: array{files?: string[]}, proof_of_registration?: array{files?: string[], signer?: array{person?: string}}, proof_of_ultimate_beneficial_ownership?: array{files?: string[], signer?: array{person?: string}}}, email?: string, expand?: string[], external_account?: array|string, groups?: array{payments_pricing?: null|string}, individual?: array{address?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string}, address_kana?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string, town?: string}, address_kanji?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string, town?: string}, dob?: null|array{day: int, month: int, year: int}, email?: string, first_name?: string, first_name_kana?: string, first_name_kanji?: string, full_name_aliases?: null|string[], gender?: string, id_number?: string, id_number_secondary?: string, last_name?: string, last_name_kana?: string, last_name_kanji?: string, maiden_name?: string, metadata?: null|array<string, string>, phone?: string, political_exposure?: string, registered_address?: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string}, relationship?: array{director?: bool, executive?: bool, owner?: bool, percent_ownership?: null|float, title?: string}, ssn_last_4?: string, verification?: array{additional_document?: array{back?: string, front?: string}, document?: array{back?: string, front?: string}}}, metadata?: null|array<string, string>, settings?: array{bacs_debit_payments?: array{display_name?: string}, branding?: array{icon?: string, logo?: string, primary_color?: string, secondary_color?: string}, card_issuing?: array{tos_acceptance?: array{date?: int, ip?: string, user_agent?: null|string}}, card_payments?: array{decline_on?: array{avs_failure?: bool, cvc_failure?: bool}, statement_descriptor_prefix?: string, statement_descriptor_prefix_kana?: null|string, statement_descriptor_prefix_kanji?: null|string}, invoices?: array{hosted_payment_method_save?: string}, payments?: array{statement_descriptor?: string, statement_descriptor_kana?: string, statement_descriptor_kanji?: string}, payouts?: array{debit_negative_balances?: bool, schedule?: array{delay_days?: array|int|string, interval?: string, monthly_anchor?: int, monthly_payout_days?: int[], weekly_anchor?: string, weekly_payout_days?: string[]}, statement_descriptor?: string}, treasury?: array{tos_acceptance?: array{date?: int, ip?: string, user_agent?: null|string}}}, tos_acceptance?: array{date?: int, ip?: string, service_agreement?: string, user_agent?: string}, type?: string} $params
     * @param null|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\V2\Core\Account
     *
     * @throws \Stripe\Exception\RateLimitException
     */
    public function createAccount(array $params, null|RequestOptions $opts = null): \Stripe\Account {

        // investigar processo de account token

        // $params['configuration'] = [
        //     'customer' => [
        //         'capabilities' => [
        //             'automatic_indirect_tax' => [ 'requested' => true ]
        //         ],
        //         'automatic_indirect_tax' => [
        //             'exempt' => 'none'
        //         ]                
        //     ],
        //     'merchant' => [
        //         'capabilities' => [
        //             'boleto_payments' => [ 'requested' => true ],
        //             'card_payments' => [ 'requested' => true ],
        //             'samsung_pay_payments' => [ 'requested' => true ]
        //         ],
        //         'card_payments' => [
        //             'decline_on' => [
        //                 'cvc_failure' => true
        //             ]
        //         ]
        //     ]
        // ];

        // $params['include'] = [
        //     'configuration.merchant',
        //     'configuration.customer',
        //     'identity',
        //     'defaults',
        // ];
        // $params['dashboard'] = 'none';
        // $params['defaults'] = [
        //     'responsibilities' => [
        //         'fees_collector' => 'application',
        //         'losses_collector' => 'stripe'
        //     ]
        // ];


        $params['controller'] = [
            'fees' => [ 'payer' => 'application' ],
            'losses' => [ 'payments' => 'stripe' ],
            'requirement_collection' => 'stripe',
            'stripe_dashboard' => [ 'type' => 'none' ]
        ];

        $params['capabilities'] = [
            'boleto_payments' => [ 'requested' => true ],
            'card_payments' => [ 'requested' => true ],
            'transfers' => [ 'requested' => true ]
            // 'crypto_payments' => [ 'requested' => false ]
            // 'pix_payments' => [ 'requested' => true ], // Pix não é solicitado via API para contas BR
        ];

        return $this->client->accounts->create($params, $opts);
        // return $this->client->v2->core->accounts->create($params, $opts);
    }

    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl) {
        return $this->client->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }
}

class StripeWebhooks {
    private string $webhookSecret;

    public function __construct(string $webhookSecret = '') {
        $this->webhookSecret = $webhookSecret;
    }

    public function setSecret(string $secret) {
        $this->webhookSecret = $secret;
    }

    public function handle(string $payload, string $sigHeader) {
        if (empty($this->webhookSecret)) {
            throw new \Exception("Webhook secret não configurado.");
        }

        try {
            return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch(\UnexpectedValueException $e) {
            throw new \Exception("Payload de webhook inválido.");
        } catch(SignatureVerificationException $e) {
            throw new \Exception("Assinatura de webhook inválida.");
        }
    }
}

class StripeKyc {
    private StripeClient $client;
    public function __construct(StripeClient $client) { $this->client = $client; }

    public function createAccountSession(string $accountId) {
        return $this->client->accountSessions->create([
            'account' => $accountId,
            'components' => [
                'account_onboarding' => ['enabled' => true],
                'documents' => ['enabled' => true],
            ],
        ]);
    }
}

class Stripe {
    private string $ApiKey = '';
    private string $Ambiente = '';
    private StripeClient $Client;

    public StripeProducts $products;
    public StripeCustomers $customers;
    public StripeSubscriptions $subscriptions;
    public StripeCheckout $checkout;
    public StripePayments $payments;
    public StripeAnalytics $analytics;
    public StripeConnect $connect;
    public StripeWebhooks $webhooks;
    public StripeKyc $kyc;

    public function __construct(string $apiKey, string $ambient = Ambients::TESTE)
    {
        if ($ambient == Ambients::TESTE && !str_contains(strtolower($apiKey), 'test')) {
            throw new \Exception("Erro! A chave de API especificada não corresponde ao ambiente de teste!");    
        }

        if (!class_exists('\Stripe\StripeClient')) {
            throw new \Exception("A SDK oficial stripe/stripe-php não foi encontrada. Instale usando o composer.");
        }

        $this->ApiKey = $apiKey;
        $this->Ambiente = $ambient;
        $this->Client = new StripeClient($apiKey);

        $this->products = new StripeProducts($this->Client);
        $this->customers = new StripeCustomers($this->Client);
        $this->subscriptions = new StripeSubscriptions($this->Client);
        $this->checkout = new StripeCheckout($this->Client);
        $this->payments = new StripePayments($this->Client);
        $this->analytics = new StripeAnalytics($this->Client);
        $this->connect = new StripeConnect($this->Client);
        $this->webhooks = new StripeWebhooks();
        $this->kyc = new StripeKyc($this->Client);
    }
    
    public function getClient(): StripeClient {
        return $this->Client;
    }
}

?>