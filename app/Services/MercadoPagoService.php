<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoService
{
    public function createPixOrder(
        Business $business,
        Subscription $subscription,
        Plan $plan
    ): array {
        $amount = number_format(
            (float) $plan->price,
            2,
            '.',
            ''
        );

        $payer = [
            'email' =>
                $this->payerEmail(
                    $business
                ),
        ];

        /*
         * O simulador oficial de Pix do Mercado Pago
         * utiliza APRO no ambiente de teste.
         */
        if ($this->isTest()) {
            $payer['first_name'] =
                'APRO';
        }

        $payload = [
            'type' =>
                'online',

            'external_reference' =>
                'negozia-pix-subscription-'
                . $subscription->id,

            'total_amount' =>
                $amount,

            'processing_mode' =>
                'automatic',

            'payer' =>
                $payer,

            'transactions' => [
                'payments' => [
                    [
                        'amount' =>
                            $amount,

                        'payment_method' => [
                            'id' =>
                                'pix',

                            'type' =>
                                'bank_transfer',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this
            ->request()
            ->withHeaders([
                'X-Idempotency-Key' =>
                    (string) Str::uuid(),
            ])
            ->post(
                '/v1/orders',
                $payload
            );

        $response->throw();

        $order =
            $response->json();

        if (
            !is_array($order)
            || empty($order['id'])
        ) {
            throw new RuntimeException(
                'O Mercado Pago não retornou uma order Pix válida.'
            );
        }

        $payment = data_get(
            $order,
            'transactions.payments.0'
        );

        if (!is_array($payment)) {
            throw new RuntimeException(
                'O Mercado Pago não retornou os dados do pagamento Pix.'
            );
        }

        return $order;
    }

    public function getOrder(
        string $orderId
    ): array {
        $response = $this
            ->request()
            ->get(
                '/v1/orders/'
                . rawurlencode(
                    $orderId
                )
            );

        $response->throw();

        $order =
            $response->json();

        if (
            !is_array($order)
            || empty($order['id'])
        ) {
            throw new RuntimeException(
                'Order do Mercado Pago inválida.'
            );
        }

        return $order;
    }

    public function syncPixOrder(
        Subscription $subscription,
        array $order
    ): bool {
        if (
            !$this->belongsToSubscription(
                $subscription,
                $order
            )
        ) {
            throw new RuntimeException(
                'A order Pix não pertence a esta assinatura.'
            );
        }

        if (!$this->isPixOrder($order)) {
            throw new RuntimeException(
                'A order recebida não é um pagamento Pix válido.'
            );
        }

        $payment = data_get(
            $order,
            'transactions.payments.0',
            []
        );

        $orderStatus =
            (string) data_get(
                $order,
                'status',
                ''
            );

        $orderStatusDetail =
            (string) data_get(
                $order,
                'status_detail',
                ''
            );

        $paymentId =
            (string) data_get(
                $payment,
                'id',
                ''
            );

        $paymentStatus =
            (string) data_get(
                $payment,
                'status',
                ''
            );

        $paymentStatusDetail =
            (string) data_get(
                $payment,
                'status_detail',
                ''
            );

        $baseUpdates = [
            'payment_provider' =>
                'mercadopago_pix',

            'provider_checkout_id' =>
                (string) data_get(
                    $order,
                    'id'
                ),

            'provider_checkout_status' =>
                $orderStatusDetail !== ''
                    ? $orderStatus
                        . '/'
                        . $orderStatusDetail
                    : $orderStatus,

            'provider_payment_id' =>
                $paymentId !== ''
                    ? $paymentId
                    : $subscription
                        ->provider_payment_id,

            'provider_payment_status' =>
                $paymentStatusDetail !== ''
                    ? $paymentStatus
                        . '/'
                        . $paymentStatusDetail
                    : $paymentStatus,
        ];

        /*
         * Guardamos o ID anterior ANTES de sincronizar a nova
         * order. Isso permite diferenciar:
         *
         * - reprocessamento do mesmo pagamento;
         * - uma nova renovação Pix.
         */
        $alreadyAppliedPayment =
            $subscription->provider_payment_id === $paymentId
            && $subscription->billing_status === 'current'
            && $subscription->payment_provider === 'mercadopago_pix'
            && $subscription->plan?->slug === 'pro';

        $subscription->update(
            $baseUpdates
        );

        if (
            $orderStatus !== 'processed'
            || $orderStatusDetail
                !== 'accredited'
            || $paymentStatus
                !== 'processed'
            || $paymentStatusDetail
                !== 'accredited'
        ) {
            return false;
        }

        /*
         * Evita conceder mais 30 dias caso o mesmo
         * pagamento seja sincronizado novamente.
         */
        if ($alreadyAppliedPayment) {
            return true;
        }

        $proPlan = Plan::query()
            ->where(
                'slug',
                'pro'
            )
            ->where(
                'is_active',
                true
            )
            ->firstOrFail();

        $expectedAmount =
            round(
                (float) $proPlan->price,
                2
            );

        $receivedAmount =
            round(
                (float) data_get(
                    $order,
                    'total_amount',
                    0
                ),
                2
            );

        if (
            abs(
                $expectedAmount
                - $receivedAmount
            ) > 0.001
        ) {
            throw new RuntimeException(
                'O valor recebido no Pix não corresponde ao Negozia Pro.'
            );
        }

        $wasPro =
            $subscription
                ->plan
                ?->slug === 'pro';

        $periodStart =
            now();

        /*
         * Se no futuro permitirmos renovação antecipada
         * de um Pro pago por Pix, acumulamos os 30 dias
         * sobre o período ainda vigente.
         */
        if (
            $wasPro
            && $subscription
                ->payment_provider
                === 'mercadopago_pix'
            && $subscription
                ->current_period_ends_at
                ?->isFuture()
        ) {
            $periodStart =
                $subscription
                    ->current_period_ends_at
                    ->copy()
                    ->addSecond();
        }

        $periodEnd =
            $periodStart
                ->copy()
                ->addDays(30)
                ->subSecond();

        $subscription->update([
            'plan_id' =>
                $proPlan->id,

            'status' =>
                'active',

            'billing_status' =>
                'current',

            'starts_at' =>
                $wasPro
                    ? (
                        $subscription
                            ->starts_at
                        ?: $periodStart
                    )
                    : $periodStart,

            'trial_ends_at' =>
                null,

            'current_period_starts_at' =>
                $periodStart,

            'current_period_ends_at' =>
                $periodEnd,

            /*
             * Pix mensal não possui renovação automática.
             * ends_at representa o fim do acesso adquirido.
             */
            'ends_at' =>
                $periodEnd,

            'canceled_at' =>
                null,

            'past_due_at' =>
                null,

            'grace_ends_at' =>
                null,

            'access_suspended_at' =>
                null,

            'last_payment_confirmed_at' =>
                now(),

            'provider_subscription_id' =>
                null,

            'provider_checkout_status' =>
                'processed/accredited',

            'provider_payment_status' =>
                'processed/accredited',
        ]);

        return true;
    }

    public function isPixOrder(
        array $order
    ): bool {
        return
            data_get(
                $order,
                'transactions.payments.0.payment_method.id'
            ) === 'pix'

            && data_get(
                $order,
                'transactions.payments.0.payment_method.type'
            ) === 'bank_transfer';
    }

    private function belongsToSubscription(
        Subscription $subscription,
        array $order
    ): bool {
        return data_get(
            $order,
            'external_reference'
        ) ===
            'negozia-pix-subscription-'
            . $subscription->id;
    }

    private function payerEmail(
        Business $business
    ): string {
        if ($this->isTest()) {
            return 'test_user_br@testuser.com';
        }

        $email = trim(
            (string) (
                $business->email
                ?: $business->user?->email
            )
        );

        if (
            $email === ''
            || !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Informe um e-mail válido antes de pagar com Pix.'
            );
        }

        return $email;
    }

    private function request(): PendingRequest
    {
        $token = trim(
            (string) config(
                'services.mercadopago.access_token'
            )
        );

        if ($token === '') {
            throw new RuntimeException(
                'O Access Token do Mercado Pago não está configurado.'
            );
        }

        return Http::baseUrl(
            rtrim(
                (string) config(
                    'services.mercadopago.base_url'
                ),
                '/'
            )
        )
            ->withToken(
                $token
            )
            ->acceptJson()
            ->asJson();
    }

    private function isTest(): bool
    {
        return config(
            'services.mercadopago.environment'
        ) !== 'production';
    }
}
