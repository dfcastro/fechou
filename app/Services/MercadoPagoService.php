<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
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

    public function createProSubscription(
        Business $business,
        Subscription $subscription,
        string $cardTokenId
    ): array {
        $planId = trim(
            (string) config(
                'services.mercadopago.subscription_plan_pro'
            )
        );

        if ($planId === '') {
            throw new RuntimeException(
                'O plano recorrente do Mercado Pago não está configurado.'
            );
        }

        $cardTokenId = trim(
            $cardTokenId
        );

        if ($cardTokenId === '') {
            throw new RuntimeException(
                'O token do cartão não foi informado.'
            );
        }

        $payload = [
            'preapproval_plan_id' =>
                $planId,

            'reason' =>
                'Negozia Pro',

            'external_reference' =>
                'negozia-subscription-'
                . $subscription->id,

            'payer_email' =>
                $this->payerEmail(
                    $business
                ),

            'card_token_id' =>
                $cardTokenId,

            'back_url' =>
                route(
                    'settings.subscription'
                ),

            'status' =>
                'authorized',
        ];

        $response = $this
            ->request()
            ->post(
                '/preapproval',
                $payload
            );

        $response->throw();

        $preapproval =
            $response->json();

        if (
            ! is_array($preapproval)
            || empty($preapproval['id'])
        ) {
            throw new RuntimeException(
                'O Mercado Pago não retornou uma assinatura válida.'
            );
        }

        if (
            data_get(
                $preapproval,
                'status'
            ) !== 'authorized'
        ) {
            throw new RuntimeException(
                'A assinatura não foi autorizada pelo Mercado Pago.'
            );
        }

        return $preapproval;
    }

    public function cancelSubscription(
        Subscription $subscription
    ): array {
        $preapprovalId = trim(
            (string) $subscription
                ->provider_subscription_id
        );

        if ($preapprovalId === '') {
            throw new RuntimeException(
                'A assinatura do Mercado Pago não foi localizada.'
            );
        }

        /*
         * Consulta primeiro o estado atual para tornar
         * o cancelamento idempotente.
         *
         * Isso também cobre o caso em que o Mercado Pago
         * cancelou a assinatura antes de atualizarmos
         * o estado local.
         */
        $current =
            $this->getPreapproval(
                $preapprovalId
            );

        if (
            data_get(
                $current,
                'status'
            ) === 'cancelled'
        ) {
            return $current;
        }

        $response = $this
            ->request()
            ->put(
                '/preapproval/'
                . rawurlencode(
                    $preapprovalId
                ),
                [
                    'status' =>
                        'cancelled',
                ]
            );

        $response->throw();

        $preapproval =
            $response->json();

        if (
            ! is_array($preapproval)
            || (string) data_get(
                $preapproval,
                'id',
                ''
            ) !== $preapprovalId
        ) {
            throw new RuntimeException(
                'O Mercado Pago não retornou uma assinatura válida.'
            );
        }

        if (
            data_get(
                $preapproval,
                'status'
            ) !== 'cancelled'
        ) {
            throw new RuntimeException(
                'O Mercado Pago não confirmou o cancelamento.'
            );
        }

        return $preapproval;
    }

    public function syncSubscriptionPreapproval(
        Subscription $subscription,
        array $preapproval
    ): void {
        $preapprovalId =
            (string) data_get(
                $preapproval,
                'id',
                ''
            );

        if (
            $preapprovalId === ''
            || $preapprovalId !==
                (string) $subscription
                    ->provider_subscription_id
        ) {
            throw new RuntimeException(
                'A assinatura recebida não corresponde à assinatura local.'
            );
        }

        $reference =
            (string) data_get(
                $preapproval,
                'external_reference',
                ''
            );

        $expectedReference =
            'negozia-subscription-'
            . $subscription->id;

        if (
            $reference !== ''
            && $reference !== $expectedReference
        ) {
            throw new RuntimeException(
                'A referência da assinatura recorrente é inválida.'
            );
        }

        $status =
            (string) data_get(
                $preapproval,
                'status',
                ''
            );

        $updates = [
            'payment_provider' =>
                'mercadopago_subscription',

            'provider_checkout_status' =>
                $status,

            'provider_customer_id' =>
                filled(
                    data_get(
                        $preapproval,
                        'payer_id'
                    )
                )
                    ? (string) data_get(
                        $preapproval,
                        'payer_id'
                    )
                    : $subscription
                        ->provider_customer_id,
        ];

        if ($status === 'cancelled') {
            /*
             * Uma assinatura que nunca ativou o Pro
             * continua sendo uma assinatura Grátis válida.
             */
            if (
                $subscription
                    ->plan
                    ?->isFree()
            ) {
                $updates['status'] =
                    'active';

                $updates['billing_status'] =
                    'canceled';

                $updates['canceled_at'] =
                    $subscription
                        ->canceled_at
                    ?? now();

                $updates['ends_at'] =
                    null;

                $updates['access_suspended_at'] =
                    null;
            } else {
                $periodEnd =
                    $subscription
                        ->current_period_ends_at;

                $canKeepAccess =
                    $periodEnd
                    && $periodEnd->isFuture()
                    && $subscription
                        ->access_suspended_at === null;

                if ($canKeepAccess) {
                    $updates['status'] =
                        'active';

                    $updates['billing_status'] =
                        'canceling';

                    $updates['canceled_at'] =
                        $subscription
                            ->canceled_at
                        ?? now();

                    $updates['ends_at'] =
                        $periodEnd;
                } else {
                    $updates['status'] =
                        'canceled';

                    $updates['billing_status'] =
                        'canceled';

                    $updates['canceled_at'] =
                        $subscription
                            ->canceled_at
                        ?? now();

                    $updates['ends_at'] =
                        now();

                    $updates['access_suspended_at'] =
                        now();
                }
            }
        }

        $subscription->update(
            $updates
        );
    }

    public function searchAuthorizedPayments(
        string $preapprovalId
    ): array {
        $response = $this
            ->request()
            ->get(
                '/authorized_payments/search',
                [
                    'preapproval_id' =>
                        $preapprovalId,
                ]
            );

        $response->throw();

        $results =
            $response->json(
                'results',
                []
            );

        return is_array($results)
            ? $results
            : [];
    }

    public function getAuthorizedPayment(
        string $authorizedPaymentId
    ): array {
        $response = $this
            ->request()
            ->get(
                '/authorized_payments/'
                . rawurlencode(
                    $authorizedPaymentId
                )
            );

        $response->throw();

        $invoice =
            $response->json();

        if (
            ! is_array($invoice)
            || empty($invoice['id'])
        ) {
            throw new RuntimeException(
                'Fatura recorrente do Mercado Pago inválida.'
            );
        }

        return $invoice;
    }

    public function getPreapproval(
        string $preapprovalId
    ): array {
        $response = $this
            ->request()
            ->get(
                '/preapproval/'
                . rawurlencode(
                    $preapprovalId
                )
            );

        $response->throw();

        $preapproval =
            $response->json();

        if (
            ! is_array($preapproval)
            || empty($preapproval['id'])
        ) {
            throw new RuntimeException(
                'Assinatura recorrente do Mercado Pago inválida.'
            );
        }

        return $preapproval;
    }

    public function syncSubscriptionAuthorizedPayment(
        Subscription $subscription,
        array $invoice,
        array $preapproval
    ): bool {
        $preapprovalId =
            (string) data_get(
                $invoice,
                'preapproval_id',
                ''
            );

        if (
            $preapprovalId === ''
            || $preapprovalId !==
                (string) data_get(
                    $preapproval,
                    'id',
                    ''
                )
            || $preapprovalId !==
                (string) $subscription
                    ->provider_subscription_id
        ) {
            throw new RuntimeException(
                'A cobrança não pertence à assinatura informada.'
            );
        }

        $expectedReference =
            'negozia-subscription-'
            . $subscription->id;

        $reference =
            (string) (
                data_get(
                    $invoice,
                    'external_reference'
                )
                ?: data_get(
                    $preapproval,
                    'external_reference'
                )
            );

        if (
            $reference !== ''
            && $reference !== $expectedReference
        ) {
            throw new RuntimeException(
                'A referência da assinatura recorrente é inválida.'
            );
        }

        $paymentId =
            (string) data_get(
                $invoice,
                'payment.id',
                ''
            );

        $paymentStatus =
            (string) data_get(
                $invoice,
                'payment.status',
                ''
            );

        $paymentDetail =
            (string) data_get(
                $invoice,
                'payment.status_detail',
                ''
            );

        $paymentStatusLabel =
            $paymentDetail !== ''
                ? $paymentStatus
                    . '/'
                    . $paymentDetail
                : $paymentStatus;

        /*
         * Precisamos descobrir se este pagamento já foi
         * aplicado ANTES de sobrescrever provider_payment_id.
         *
         * Caso contrário, qualquer nova renovação aprovada
         * pareceria ser um reprocessamento do mesmo pagamento.
         */
        $alreadyAppliedPayment =
            $paymentId !== ''
            && $subscription
                ->provider_payment_id === $paymentId
            && $subscription
                ->billing_status === 'current'
            && $subscription
                ->payment_provider
                === 'mercadopago_subscription'
            && $subscription
                ->plan
                ?->slug === 'pro';

        $subscription->update([
            'payment_provider' =>
                'mercadopago_subscription',

            'provider_subscription_id' =>
                $preapprovalId,

            'provider_checkout_status' =>
                (string) data_get(
                    $preapproval,
                    'status',
                    ''
                ),

            'provider_payment_id' =>
                $paymentId !== ''
                    ? $paymentId
                    : $subscription
                        ->provider_payment_id,

            'provider_payment_status' =>
                $paymentStatusLabel,
        ]);

        if (
            $paymentStatus !== 'approved'
            || $paymentDetail !== 'accredited'
        ) {
            /*
             * Primeira cobrança recusada:
             * o cliente ainda está no plano Grátis.
             *
             * Guardamos a falha, mas não criamos
             * past_due para uma assinatura que nunca
             * chegou a ativar o Pro.
             */
            if (
                $subscription
                    ->plan
                    ?->isFree()
            ) {
                $subscription->update([
                    'billing_status' =>
                        'payment_failed',
                ]);

                return false;
            }

            /*
             * Em renovações, o Mercado Pago pode colocar
             * a fatura em recycling e tentar cobrar
             * novamente.
             *
             * O Negozia concede 3 dias de carência.
             * Uma cobrança aprovada posteriormente limpa
             * estes campos no fluxo normal de aprovação.
             */
            $invoiceStatus =
                (string) data_get(
                    $invoice,
                    'status',
                    ''
                );

            $isFailedRenewal =
                $paymentStatus === 'rejected'
                || in_array(
                    $invoiceStatus,
                    [
                        'recycling',
                        'processed',
                    ],
                    true
                );

            if ($isFailedRenewal) {
                $pastDueAt =
                    $subscription
                        ->past_due_at
                    ?? now();

                $subscription->update([
                    'status' =>
                        'past_due',

                    'billing_status' =>
                        'overdue',

                    'past_due_at' =>
                        $pastDueAt,

                    'grace_ends_at' =>
                        $pastDueAt
                            ->copy()
                            ->addDays(3),

                    'access_suspended_at' =>
                        null,
                ]);
            } else {
                /*
                 * waiting for gateway / in_process:
                 * ainda não consideramos inadimplência.
                 */
                $subscription->update([
                    'billing_status' =>
                        'processing',
                ]);
            }

            return false;
        }

        /*
         * Reprocessamento do mesmo pagamento não cria
         * um novo período.
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
                    $invoice,
                    'transaction_amount',
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
                'O valor da cobrança recorrente não corresponde ao Negozia Pro.'
            );
        }

        $periodStart =
            filled(
                data_get(
                    $invoice,
                    'debit_date'
                )
            )
                ? Carbon::parse(
                    data_get(
                        $invoice,
                        'debit_date'
                    )
                )->utc()
                : now();

        $nextPaymentDate =
            data_get(
                $preapproval,
                'next_payment_date'
            );

        if (! filled($nextPaymentDate)) {
            throw new RuntimeException(
                'O Mercado Pago não informou a próxima cobrança.'
            );
        }

        $periodEnd =
            Carbon::parse(
                $nextPaymentDate
            )
                ->utc()
                ->subSecond();

        $wasPro =
            $subscription
                ->plan
                ?->slug === 'pro';

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
                        $subscription->starts_at
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
             * Recorrência automática não possui
             * término programado enquanto estiver ativa.
             */
            'ends_at' =>
                null,

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

            'provider_customer_id' =>
                filled(
                    data_get(
                        $preapproval,
                        'payer_id'
                    )
                )
                    ? (string) data_get(
                        $preapproval,
                        'payer_id'
                    )
                    : $subscription
                        ->provider_customer_id,

            'provider_checkout_status' =>
                (string) data_get(
                    $preapproval,
                    'status',
                    'authorized'
                ),

            'provider_payment_status' =>
                'approved/accredited',
        ]);

        return true;
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
