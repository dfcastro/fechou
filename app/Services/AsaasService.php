<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasService
{
    public function createProCheckout(
        Business $business,
        Subscription $subscription,
        Plan $plan
    ): array {
        $customerId = $this->ensureCustomer(
            $business,
            $subscription
        );

        $payload = [
            'billingTypes' => [
                'CREDIT_CARD',
            ],

            'chargeTypes' => [
                'RECURRENT',
            ],

            'minutesToExpire' => 60,

            'externalReference' =>
                'fechou-subscription-'
                . $subscription->id,

            /*
             * O cliente é criado/reutilizado ANTES do checkout.
             *
             * Isso garante uma chave estável para relacionar
             * SUBSCRIPTION_CREATED à assinatura local mesmo
             * quando esse evento chega antes de CHECKOUT_PAID.
             */
            'customer' => $customerId,

            'callback' => [
                'successUrl' => route(
                    'settings.subscription',
                    [
                        'checkout' => 'success',
                    ]
                ),

                'cancelUrl' => route(
                    'settings.subscription',
                    [
                        'checkout' => 'canceled',
                    ]
                ),

                'expiredUrl' => route(
                    'settings.subscription',
                    [
                        'checkout' => 'expired',
                    ]
                ),
            ],

            'items' => [
                [
                    'name' => 'Fechou Pro',

                    'description' =>
                        'Assinatura mensal do Fechou Pro',

                    'quantity' => 1,

                    'value' => round(
                        (float) $plan->price,
                        2
                    ),
                ],
            ],

            'subscription' => [
                'cycle' => 'MONTHLY',

                /*
                 * Primeira cobrança imediatamente.
                 */
                'nextDueDate' => now()
                    ->format('Y-m-d H:i:s'),
            ],
        ];

        $response = $this->request()
            ->post(
                $this->baseUrl()
                . '/checkouts',
                $payload
            );

        $response->throw();

        $checkout = $response->json();

        if (
            !is_array($checkout)
            || empty($checkout['id'])
        ) {
            throw new RuntimeException(
                'O Asaas não retornou o identificador do checkout.'
            );
        }

        $checkout['link'] =
            $checkout['link']
            ?? $this->checkoutLink(
                $checkout['id']
            );

        return $checkout;
    }

    public function ensureCustomer(
        Business $business,
        Subscription $subscription
    ): string {
        if ($subscription->provider_customer_id) {
            return $subscription
                ->provider_customer_id;
        }

        $externalReference =
            'fechou-business-'
            . $business->id;

        /*
         * Antes de criar, tentamos localizar um cliente
         * já criado anteriormente. Isso evita duplicidade
         * em retentativas de checkout.
         */
        $existingResponse = $this->request()
            ->get(
                $this->baseUrl()
                . '/customers',
                [
                    'externalReference' =>
                        $externalReference,

                    'limit' => 1,
                ]
            );

        $existingResponse->throw();

        $existingCustomerId = data_get(
            $existingResponse->json(),
            'data.0.id'
        );

        if (
            is_string($existingCustomerId)
            && $existingCustomerId !== ''
        ) {
            $subscription->update([
                'payment_provider' =>
                    'asaas',

                'provider_customer_id' =>
                    $existingCustomerId,
            ]);

            return $existingCustomerId;
        }

        $document = preg_replace(
            '/\D+/',
            '',
            (string) $business->document
        );

        if (
            !$this->isValidCpfCnpj(
                $document
            )
        ) {
            throw new RuntimeException(
                'Informe um CPF ou CNPJ válido em Configurações > Empresa antes de assinar o Fechou Pro.'
            );
        }

        if (
            !$business->name
            || trim($business->name) === ''
        ) {
            throw new RuntimeException(
                'Informe o nome da empresa antes de assinar o Fechou Pro.'
            );
        }

        $customerPayload = [
            'name' =>
                trim($business->name),

            'cpfCnpj' =>
                $document,

            'externalReference' =>
                $externalReference,
        ];

        if (
            $business->email
            && filter_var(
                $business->email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $customerPayload['email'] =
                trim($business->email);
        }

        $mobilePhone = preg_replace(
            '/\D+/',
            '',
            (string) (
                $business->whatsapp
                ?: $business->phone
            )
        );

        if (
            strlen($mobilePhone) >= 10
            && strlen($mobilePhone) <= 11
        ) {
            $customerPayload[
                'mobilePhone'
            ] = $mobilePhone;
        }

        /*
         * Endereço não é enviado aqui.
         *
         * O Fechou ainda não possui todos os campos de
         * faturamento (número/bairro etc.). O Checkout do
         * Asaas poderá solicitar os dados faltantes.
         */
        $createResponse = $this->request()
            ->post(
                $this->baseUrl()
                . '/customers',
                $customerPayload
            );

        $createResponse->throw();

        $customerId = data_get(
            $createResponse->json(),
            'id'
        );

        if (
            !is_string($customerId)
            || $customerId === ''
        ) {
            throw new RuntimeException(
                'O Asaas não retornou o identificador do cliente.'
            );
        }

        $subscription->update([
            'payment_provider' =>
                'asaas',

            'provider_customer_id' =>
                $customerId,
        ]);

        return $customerId;
    }

    public function checkoutLink(
        string $checkoutId
    ): string {
        $base = (string) config(
            'services.asaas.checkout_url'
        );

        if (!$base) {
            throw new RuntimeException(
                'A URL do checkout do Asaas não está configurada.'
            );
        }

        return $base . urlencode(
            $checkoutId
        );
    }

    private function request(): PendingRequest
    {
        $apiKey = config(
            'services.asaas.api_key'
        );

        if (!$apiKey) {
            throw new RuntimeException(
                'A chave da API do Asaas não está configurada.'
            );
        }

        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' =>
                    $apiKey,

                'User-Agent' =>
                    'Fechou/1.0 (Laravel; '
                    . config(
                        'services.asaas.environment',
                        'sandbox'
                    )
                    . ')',
            ])
            ->timeout(60);
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim(
            (string) config(
                'services.asaas.base_url'
            ),
            '/'
        );

        if (!$baseUrl) {
            throw new RuntimeException(
                'A URL da API do Asaas não está configurada.'
            );
        }

        return $baseUrl;
    }

    private function isValidCpfCnpj(
        string $document
    ): bool {
        return match (
            strlen($document)
        ) {
            11 => $this->isValidCpf(
                $document
            ),

            14 => $this->isValidCnpj(
                $document
            ),

            default => false,
        };
    }

    private function isValidCpf(
        string $cpf
    ): bool {
        if (
            preg_match(
                '/^(\d)\1{10}$/',
                $cpf
            )
        ) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for (
                $index = 0;
                $index < $position;
                $index++
            ) {
                $sum +=
                    (int) $cpf[$index]
                    * (
                        $position
                        + 1
                        - $index
                    );
            }

            $digit =
                (10 * $sum) % 11;

            if ($digit === 10) {
                $digit = 0;
            }

            if (
                $digit
                !== (int) $cpf[$position]
            ) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(
        string $cnpj
    ): bool {
        if (
            preg_match(
                '/^(\d)\1{13}$/',
                $cnpj
            )
        ) {
            return false;
        }

        $calculateDigit = static function (
            string $base,
            array $weights
        ): int {
            $sum = 0;

            foreach (
                $weights as $index => $weight
            ) {
                $sum +=
                    (int) $base[$index]
                    * $weight;
            }

            $remainder = $sum % 11;

            return $remainder < 2
                ? 0
                : 11 - $remainder;
        };

        $firstDigit = $calculateDigit(
            substr($cnpj, 0, 12),
            [
                5, 4, 3, 2,
                9, 8, 7, 6,
                5, 4, 3, 2,
            ]
        );

        if (
            $firstDigit
            !== (int) $cnpj[12]
        ) {
            return false;
        }

        $secondDigit = $calculateDigit(
            substr($cnpj, 0, 13),
            [
                6, 5, 4, 3,
                2, 9, 8, 7,
                6, 5, 4, 3,
                2,
            ]
        );

        return $secondDigit
            === (int) $cnpj[13];
    }
}
