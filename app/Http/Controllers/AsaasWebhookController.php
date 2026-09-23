<?php

namespace App\Http\Controllers;

use App\Models\GatewayWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AsaasWebhookController extends Controller
{
    private const GRACE_DAYS = 3;

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->hasValidToken($request)) {
            return response()->json([
                'received' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $payload = $request->json()->all();

        $eventId = data_get(
            $payload,
            'id'
        );

        $eventType = data_get(
            $payload,
            'event'
        );

        if (
            !is_string($eventId)
            || $eventId === ''
            || !is_string($eventType)
            || $eventType === ''
        ) {
            return response()->json([
                'received' => false,
                'message' =>
                    'Invalid webhook payload.',
            ], 422);
        }

        $webhookEvent =
            GatewayWebhookEvent::firstOrCreate(
                [
                    'provider' =>
                        'asaas',

                    'provider_event_id' =>
                        $eventId,
                ],
                [
                    'event_type' =>
                        $eventType,

                    'payload' =>
                        $payload,
                ]
            );

        if ($webhookEvent->processed_at) {
            return response()->json([
                'received' => true,
                'duplicate' => true,
            ]);
        }

        try {
            DB::transaction(
                function () use (
                    $webhookEvent,
                    $eventType,
                    $payload
                ): void {
                    $webhookEvent->update([
                        'event_type' =>
                            $eventType,

                        'payload' =>
                            $payload,
                    ]);

                    $this->process(
                        $eventType,
                        $payload
                    );

                    $webhookEvent->update([
                        'processed_at' =>
                            now(),
                    ]);
                }
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'received' => false,
                'message' =>
                    'Webhook could not be processed.',
            ], 500);
        }

        return response()->json([
            'received' => true,
        ]);
    }

    private function hasValidToken(
        Request $request
    ): bool {
        $expected = (string) config(
            'services.asaas.webhook_token'
        );

        $received = (string) $request->header(
            'asaas-access-token',
            ''
        );

        if (
            $expected === ''
            || $received === ''
        ) {
            return false;
        }

        return hash_equals(
            $expected,
            $received
        );
    }

    private function process(
        string $eventType,
        array $payload
    ): void {
        if (str_starts_with(
            $eventType,
            'CHECKOUT_'
        )) {
            $this->processCheckoutEvent(
                $eventType,
                $payload
            );

            return;
        }

        if (str_starts_with(
            $eventType,
            'SUBSCRIPTION_'
        )) {
            $this->processSubscriptionEvent(
                $eventType,
                $payload
            );

            return;
        }

        if (str_starts_with(
            $eventType,
            'PAYMENT_'
        )) {
            $this->processPaymentEvent(
                $eventType,
                $payload
            );
        }
    }

    private function processCheckoutEvent(
        string $eventType,
        array $payload
    ): void {
        $checkoutId = data_get(
            $payload,
            'checkout.id'
        );

        if (
            !is_string($checkoutId)
            || $checkoutId === ''
        ) {
            throw new RuntimeException(
                'Checkout ID not found in Asaas webhook.'
            );
        }

        $subscription = Subscription::query()
            ->where(
                'payment_provider',
                'asaas'
            )
            ->where(
                'provider_checkout_id',
                $checkoutId
            )
            ->first();

        if (
            !$subscription
            && $eventType === 'CHECKOUT_CREATED'
        ) {
            return;
        }

        if (!$subscription) {
            throw new RuntimeException(
                'Local subscription not found for Asaas checkout: '
                . $checkoutId
            );
        }

        $checkoutStatus = data_get(
            $payload,
            'checkout.status'
        );

        if ($eventType === 'CHECKOUT_PAID') {
            $proPlan = Plan::query()
                ->where('slug', 'pro')
                ->where('is_active', true)
                ->firstOrFail();

            $periodStartsAt = now();

            $subscription->update([
                'plan_id' =>
                    $proPlan->id,

                'status' =>
                    'active',

                'billing_status' =>
                    'current',

                'starts_at' =>
                    $periodStartsAt,

                'trial_ends_at' =>
                    null,

                'current_period_starts_at' =>
                    $periodStartsAt,

                'current_period_ends_at' =>
                    $periodStartsAt
                        ->copy()
                        ->addMonth()
                        ->subSecond(),

                'past_due_at' =>
                    null,

                'grace_ends_at' =>
                    null,

                'access_suspended_at' =>
                    null,

                'canceled_at' =>
                    null,

                'ends_at' =>
                    null,

                'payment_provider' =>
                    'asaas',

                'provider_customer_id' =>
                    data_get(
                        $payload,
                        'checkout.customer'
                    )
                    ?: $subscription
                        ->provider_customer_id,

                'provider_checkout_status' =>
                    $checkoutStatus
                    ?: 'PAID',
            ]);

            return;
        }

        if (
            $eventType
            === 'CHECKOUT_CANCELED'
        ) {
            $subscription->update([
                'provider_checkout_status' =>
                    $checkoutStatus
                    ?: 'CANCELED',
            ]);

            return;
        }

        if (
            $eventType
            === 'CHECKOUT_EXPIRED'
        ) {
            $subscription->update([
                'provider_checkout_status' =>
                    $checkoutStatus
                    ?: 'EXPIRED',
            ]);

            return;
        }

        if (
            $eventType
            === 'CHECKOUT_CREATED'
        ) {
            $subscription->update([
                'provider_checkout_status' =>
                    $checkoutStatus
                    ?: 'ACTIVE',
            ]);
        }
    }

    private function processSubscriptionEvent(
        string $eventType,
        array $payload
    ): void {
        $providerSubscriptionId =
            data_get(
                $payload,
                'subscription.id'
            );

        if (
            !is_string($providerSubscriptionId)
            || $providerSubscriptionId === ''
        ) {
            throw new RuntimeException(
                'Subscription ID not found in Asaas webhook.'
            );
        }

        $subscription =
            $this->resolveLocalSubscription(
                $payload
            );

        if (!$subscription) {
            return;
        }

        $providerCustomerId = data_get(
            $payload,
            'subscription.customer'
        );

        $updates = [
            'payment_provider' =>
                'asaas',

            'provider_subscription_id' =>
                $providerSubscriptionId,

            'provider_customer_id' =>
                $providerCustomerId
                ?: $subscription
                    ->provider_customer_id,
        ];

        $nextDueDate = data_get(
            $payload,
            'subscription.nextDueDate'
        );

        if (
            is_string($nextDueDate)
            && $nextDueDate !== ''
        ) {
            $nextDue = Carbon::parse(
                $nextDueDate
            )->startOfDay();

            $updates[
                'current_period_ends_at'
            ] = $nextDue
                ->copy()
                ->subSecond();
        }

        /*
         * A inativação/remoção no gateway representa cancelamento
         * da recorrência futura.
         *
         * Se já existe período pago vigente, mantemos o acesso até
         * current_period_ends_at e o comando agendado finaliza depois.
         */
        if (
            in_array(
                $eventType,
                [
                    'SUBSCRIPTION_INACTIVATED',
                    'SUBSCRIPTION_DELETED',
                ],
                true
            )
        ) {
            $periodEnd =
                $subscription
                    ->current_period_ends_at;

            if (
                $periodEnd
                && $periodEnd->isFuture()
            ) {
                $updates['billing_status'] =
                    'canceling';

                $updates['canceled_at'] =
                    now();

                $updates['ends_at'] =
                    $periodEnd;
            } else {
                $updates['status'] =
                    'canceled';

                $updates['billing_status'] =
                    'canceled';

                $updates['canceled_at'] =
                    now();

                $updates['ends_at'] =
                    now();

                $updates[
                    'access_suspended_at'
                ] = now();
            }
        }

        $subscription->update(
            $updates
        );

        if (
            $subscription->status
            === 'canceled'
            && $subscription->business
        ) {
            app(
                SubscriptionService::class
            )->ensureDefaultSubscription(
                $subscription->business
            );
        }
    }

    private function processPaymentEvent(
        string $eventType,
        array $payload
    ): void {
        $payment = data_get(
            $payload,
            'payment'
        );

        if (!is_array($payment)) {
            return;
        }

        /*
         * Nem toda cobrança da conta Asaas pertence ao Fechou.
         * Se não houver subscription, apenas reconhecemos o evento
         * e retornamos HTTP 200 para não penalizar a fila.
         */
        $providerSubscriptionId = data_get(
            $payment,
            'subscription'
        );

        if (
            !is_string($providerSubscriptionId)
            || $providerSubscriptionId === ''
        ) {
            return;
        }

        $subscription =
            $this->resolveLocalPaymentSubscription(
                $payment
            );

        if (!$subscription) {
            return;
        }

        $paymentId = data_get(
            $payment,
            'id'
        );

        $paymentStatus = data_get(
            $payment,
            'status'
        );

        $baseUpdates = [
            'provider_payment_id' =>
                is_string($paymentId)
                ? $paymentId
                : $subscription
                    ->provider_payment_id,

            'provider_payment_status' =>
                is_string($paymentStatus)
                ? $paymentStatus
                : $eventType,
        ];

        if (
            $eventType
            === 'PAYMENT_CREATED'
        ) {
            $subscription->update(
                $baseUpdates
            );

            return;
        }

        if (
            in_array(
                $eventType,
                [
                    'PAYMENT_CONFIRMED',
                    'PAYMENT_RECEIVED',
                ],
                true
            )
        ) {
            $periodStartsAt =
                $this->paymentPeriodStart(
                    $payment
                );

            $subscription->update(
                array_merge(
                    $baseUpdates,
                    [
                        'status' =>
                            'active',

                        'billing_status' =>
                            'current',

                        'current_period_starts_at' =>
                            $periodStartsAt,

                        'current_period_ends_at' =>
                            $periodStartsAt
                                ->copy()
                                ->addMonth()
                                ->subSecond(),

                        'past_due_at' =>
                            null,

                        'grace_ends_at' =>
                            null,

                        'access_suspended_at' =>
                            null,

                        'last_payment_confirmed_at' =>
                            now(),
                    ]
                )
            );

            return;
        }

        if (
            $eventType
            === 'PAYMENT_OVERDUE'
        ) {
            $pastDueAt =
                $subscription->past_due_at
                ?? now();

            $subscription->update(
                array_merge(
                    $baseUpdates,
                    [
                        'status' =>
                            'past_due',

                        'billing_status' =>
                            'overdue',

                        'past_due_at' =>
                            $pastDueAt,

                        'grace_ends_at' =>
                            $pastDueAt
                                ->copy()
                                ->addDays(
                                    self::GRACE_DAYS
                                ),

                        'access_suspended_at' =>
                            null,
                    ]
                )
            );

            return;
        }

        if (
            in_array(
                $eventType,
                [
                    'PAYMENT_REFUNDED',
                    'PAYMENT_CHARGEBACK_REQUESTED',
                    'PAYMENT_RECEIVED_IN_CASH_UNDONE',
                ],
                true
            )
        ) {
            $billingStatus = match (
                $eventType
            ) {
                'PAYMENT_REFUNDED' =>
                    'refunded',

                'PAYMENT_CHARGEBACK_REQUESTED' =>
                    'chargeback',

                default =>
                    'reversed',
            };

            $subscription->update(
                array_merge(
                    $baseUpdates,
                    [
                        'status' =>
                            'past_due',

                        'billing_status' =>
                            $billingStatus,

                        'past_due_at' =>
                            now(),

                        'grace_ends_at' =>
                            now()
                                ->subSecond(),

                        'access_suspended_at' =>
                            now(),
                    ]
                )
            );

            return;
        }

        if (
            in_array(
                $eventType,
                [
                    'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED',
                    'PAYMENT_REPROVED_BY_RISK_ANALYSIS',
                ],
                true
            )
        ) {
            $subscription->update(
                array_merge(
                    $baseUpdates,
                    [
                        'billing_status' =>
                            'payment_failed',
                    ]
                )
            );

            return;
        }

        /*
         * Outros PAYMENT_* relacionados à assinatura são armazenados
         * nos campos de provider, mas não alteram acesso.
         */
        $subscription->update(
            $baseUpdates
        );
    }

    private function paymentPeriodStart(
        array $payment
    ): Carbon {
        $dueDate = data_get(
            $payment,
            'dueDate'
        );

        if (
            is_string($dueDate)
            && $dueDate !== ''
        ) {
            return Carbon::parse(
                $dueDate
            )->startOfDay();
        }

        return now()->startOfDay();
    }

    private function resolveLocalPaymentSubscription(
        array $payment
    ): ?Subscription {
        $providerSubscriptionId = data_get(
            $payment,
            'subscription'
        );

        if (
            is_string($providerSubscriptionId)
            && $providerSubscriptionId !== ''
        ) {
            $subscription =
                Subscription::query()
                    ->where(
                        'payment_provider',
                        'asaas'
                    )
                    ->where(
                        'provider_subscription_id',
                        $providerSubscriptionId
                    )
                    ->orderByDesc('id')
                    ->first();

            if ($subscription) {
                return $subscription;
            }
        }

        $providerCustomerId = data_get(
            $payment,
            'customer'
        );

        if (
            !is_string($providerCustomerId)
            || $providerCustomerId === ''
        ) {
            return null;
        }

        return Subscription::query()
            ->where(
                'payment_provider',
                'asaas'
            )
            ->where(
                'provider_customer_id',
                $providerCustomerId
            )
            ->whereNotNull(
                'provider_subscription_id'
            )
            ->orderByDesc('id')
            ->first();
    }

    private function resolveLocalSubscription(
        array $payload
    ): ?Subscription {
        $providerSubscriptionId = data_get(
            $payload,
            'subscription.id'
        );

        $subscription = Subscription::query()
            ->where(
                'payment_provider',
                'asaas'
            )
            ->where(
                'provider_subscription_id',
                $providerSubscriptionId
            )
            ->first();

        if ($subscription) {
            return $subscription;
        }

        $externalReference = data_get(
            $payload,
            'subscription.externalReference'
        );

        if (
            is_string($externalReference)
            && preg_match(
                '/^fechou-subscription-(\d+)$/',
                $externalReference,
                $matches
            )
        ) {
            $subscription =
                Subscription::find(
                    (int) $matches[1]
                );

            if ($subscription) {
                return $subscription;
            }
        }

        $providerCustomerId = data_get(
            $payload,
            'subscription.customer'
        );

        if (
            !is_string($providerCustomerId)
            || $providerCustomerId === ''
        ) {
            return null;
        }

        return Subscription::query()
            ->where(
                'payment_provider',
                'asaas'
            )
            ->where(
                'provider_customer_id',
                $providerCustomerId
            )
            ->whereNotNull(
                'provider_checkout_id'
            )
            ->orderByDesc('id')
            ->first();
    }
}
