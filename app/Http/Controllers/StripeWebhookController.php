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
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    private const GRACE_DAYS = 3;

    public function __invoke(
        Request $request
    ): JsonResponse {
        $secret = trim(
            (string) config(
                'services.stripe.webhook_secret'
            )
        );

        if ($secret === '') {
            return response()->json([
                'received' => false,
                'message' =>
                    'Stripe webhook secret is not configured.',
            ], 500);
        }

        $signature = (string) $request->header(
            'Stripe-Signature',
            ''
        );

        if ($signature === '') {
            return response()->json([
                'received' => false,
                'message' =>
                    'Missing Stripe signature.',
            ], 400);
        }

        $rawPayload =
            $request->getContent();

        try {
            $event = Webhook::constructEvent(
                $rawPayload,
                $signature,
                $secret
            );
        } catch (
            UnexpectedValueException
            | SignatureVerificationException
            $exception
        ) {
            return response()->json([
                'received' => false,
                'message' =>
                    'Invalid Stripe signature.',
            ], 400);
        }

        $payload = json_decode(
            $rawPayload,
            true
        );

        if (!is_array($payload)) {
            return response()->json([
                'received' => false,
                'message' =>
                    'Invalid Stripe payload.',
            ], 422);
        }

        $eventId =
            (string) $event->id;

        $eventType =
            (string) $event->type;

        $webhookEvent =
            GatewayWebhookEvent::firstOrCreate(
                [
                    'provider' =>
                        'stripe',

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

    private function process(
        string $eventType,
        array $payload
    ): void {
        match ($eventType) {
            'checkout.session.completed' =>
                $this->processCheckout(
                    $payload
                ),

            'invoice.paid',
            'invoice.payment_failed' =>
                $this->processInvoice(
                    $eventType,
                    $payload
                ),

            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' =>
                $this->processSubscription(
                    $eventType,
                    $payload
                ),

            default =>
                null,
        };
    }

    private function processCheckout(
        array $payload
    ): void {
        $object =
            $this->eventObject(
                $payload
            );

        $subscription =
            $this->resolveLocalSubscription(
                $object
            );

        if (!$subscription) {
            return;
        }

        $providerSubscriptionId =
            $this->providerSubscriptionId(
                $object
            );

        $providerCustomerId =
            $this->idValue(
                data_get(
                    $object,
                    'customer'
                )
            );

        $subscription->update([
            'payment_provider' =>
                'stripe',

            'provider_checkout_id' =>
                $this->idValue(
                    data_get(
                        $object,
                        'id'
                    )
                )
                ?: $subscription
                    ->provider_checkout_id,

            'provider_checkout_status' =>
                (string) (
                    data_get(
                        $object,
                        'status'
                    )
                    ?: 'complete'
                ),

            'provider_customer_id' =>
                $providerCustomerId
                ?: $subscription
                    ->provider_customer_id,

            'provider_subscription_id' =>
                $providerSubscriptionId
                ?: $subscription
                    ->provider_subscription_id,

            'provider_payment_status' =>
                data_get(
                    $object,
                    'payment_status'
                )
                ?: $subscription
                    ->provider_payment_status,
        ]);

        /*
         * Não ativamos o Pro aqui.
         *
         * A confirmação financeira será feita
         * exclusivamente por invoice.paid.
         */
    }

    private function processInvoice(
        string $eventType,
        array $payload
    ): void {
        $invoice =
            $this->eventObject(
                $payload
            );

        $subscription =
            $this->resolveLocalSubscription(
                $invoice
            );

        if (!$subscription) {
            return;
        }

        $providerSubscriptionId =
            $this->providerSubscriptionId(
                $invoice
            );

        $providerCustomerId =
            $this->idValue(
                data_get(
                    $invoice,
                    'customer'
                )
            );

        $invoiceId =
            $this->idValue(
                data_get(
                    $invoice,
                    'id'
                )
            );

        $baseUpdates = [
            'payment_provider' =>
                'stripe',

            'provider_customer_id' =>
                $providerCustomerId
                ?: $subscription
                    ->provider_customer_id,

            'provider_subscription_id' =>
                $providerSubscriptionId
                ?: $subscription
                    ->provider_subscription_id,

            'provider_payment_id' =>
                $invoiceId
                ?: $subscription
                    ->provider_payment_id,

            'provider_payment_status' =>
                data_get(
                    $invoice,
                    'status'
                )
                ?: $eventType,
        ];

        if ($eventType === 'invoice.paid') {
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

            $period =
                $this->invoicePeriod(
                    $invoice
                );

            $periodStart =
                $period['start']
                ?? now();

            $periodEnd =
                $period['end']
                ?? $periodStart
                    ->copy()
                    ->addMonth()
                    ->subSecond();

            $wasFree =
                $subscription
                    ->plan
                    ?->isFree()
                ?? false;

            $isCanceling =
                $subscription
                    ->billing_status
                === 'canceling';

            $subscription->update(
                array_merge(
                    $baseUpdates,
                    [
                        'plan_id' =>
                            $proPlan->id,

                        'status' =>
                            'active',

                        'billing_status' =>
                            $isCanceling
                                ? 'canceling'
                                : 'current',

                        'starts_at' =>
                            $wasFree
                                ? $periodStart
                                : (
                                    $subscription
                                        ->starts_at
                                    ?: $periodStart
                                ),

                        'trial_ends_at' =>
                            null,

                        'current_period_starts_at' =>
                            $periodStart,

                        'current_period_ends_at' =>
                            $periodEnd,

                        'past_due_at' =>
                            null,

                        'grace_ends_at' =>
                            null,

                        'access_suspended_at' =>
                            null,

                        'last_payment_confirmed_at' =>
                            now(),

                        'canceled_at' =>
                            $isCanceling
                                ? $subscription
                                    ->canceled_at
                                : null,

                        'ends_at' =>
                            $isCanceling
                                ? (
                                    $subscription
                                        ->ends_at
                                    ?: $periodEnd
                                )
                                : null,
                    ]
                )
            );

            return;
        }

        /*
         * Se o primeiro pagamento falhar,
         * a conta ainda está no plano Grátis.
         *
         * Não transformamos uma assinatura
         * gratuita em past_due.
         */
        if (
            $subscription
                ->plan
                ?->isFree()
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

        $pastDueAt =
            $subscription
                ->past_due_at
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
    }

    private function processSubscription(
        string $eventType,
        array $payload
    ): void {
        $stripeSubscription =
            $this->eventObject(
                $payload
            );

        $subscription =
            $this->resolveLocalSubscription(
                $stripeSubscription
            );

        if (!$subscription) {
            return;
        }

        $providerSubscriptionId =
            $this->idValue(
                data_get(
                    $stripeSubscription,
                    'id'
                )
            );

        $providerCustomerId =
            $this->idValue(
                data_get(
                    $stripeSubscription,
                    'customer'
                )
            );

        $period =
            $this->subscriptionPeriod(
                $stripeSubscription
            );

        $updates = [
            'payment_provider' =>
                'stripe',

            'provider_subscription_id' =>
                $providerSubscriptionId
                ?: $subscription
                    ->provider_subscription_id,

            'provider_customer_id' =>
                $providerCustomerId
                ?: $subscription
                    ->provider_customer_id,
        ];

        if (isset($period['start'])) {
            $updates[
                'current_period_starts_at'
            ] = $period['start'];
        }

        if (isset($period['end'])) {
            $updates[
                'current_period_ends_at'
            ] = $period['end'];
        }

        $stripeStatus =
            (string) data_get(
                $stripeSubscription,
                'status',
                ''
            );

        $cancelAtPeriodEnd =
            (bool) data_get(
                $stripeSubscription,
                'cancel_at_period_end',
                false
            );

        if (
            $cancelAtPeriodEnd
            && $eventType
                !== 'customer.subscription.deleted'
        ) {
            $periodEnd =
                $period['end']
                ?? $subscription
                    ->current_period_ends_at;

            $updates['billing_status'] =
                'canceling';

            $updates['canceled_at'] =
                $subscription
                    ->canceled_at
                ?? now();

            $updates['ends_at'] =
                $periodEnd;

            if (
                !$subscription
                    ->plan
                    ?->isFree()
            ) {
                $updates['status'] =
                    'active';
            }
        }

        if (
            $eventType
                === 'customer.subscription.deleted'
            || $stripeStatus === 'canceled'
        ) {
            $periodEnd =
                $period['end']
                ?? $subscription
                    ->current_period_ends_at;

            $canKeepAccess =
                !$subscription
                    ->plan
                    ?->isFree()
                && $periodEnd
                && $periodEnd->isFuture()
                && $subscription
                    ->access_suspended_at === null;

            if ($canKeepAccess) {
                $updates['status'] =
                    'active';

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

    private function resolveLocalSubscription(
        array $object
    ): ?Subscription {
        $localSubscriptionId =
            $this->metadataLocalSubscriptionId(
                $object
            );

        if ($localSubscriptionId) {
            $subscription =
                Subscription::find(
                    $localSubscriptionId
                );

            if ($subscription) {
                return $subscription;
            }
        }

        $providerSubscriptionId =
            $this->providerSubscriptionId(
                $object
            );

        if ($providerSubscriptionId) {
            $subscription =
                Subscription::query()
                    ->where(
                        'payment_provider',
                        'stripe'
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

        $checkoutId =
            $this->idValue(
                data_get(
                    $object,
                    'id'
                )
            );

        if (
            $checkoutId
            && str_starts_with(
                $checkoutId,
                'cs_'
            )
        ) {
            $subscription =
                Subscription::query()
                    ->where(
                        'payment_provider',
                        'stripe'
                    )
                    ->where(
                        'provider_checkout_id',
                        $checkoutId
                    )
                    ->orderByDesc('id')
                    ->first();

            if ($subscription) {
                return $subscription;
            }
        }

        $customerId =
            $this->idValue(
                data_get(
                    $object,
                    'customer'
                )
            );

        if (!$customerId) {
            return null;
        }

        return Subscription::query()
            ->where(
                'payment_provider',
                'stripe'
            )
            ->where(
                'provider_customer_id',
                $customerId
            )
            ->orderByDesc('id')
            ->first();
    }

    private function metadataLocalSubscriptionId(
        array $object
    ): ?int {
        $candidates = [
            data_get(
                $object,
                'metadata.local_subscription_id'
            ),

            data_get(
                $object,
                'parent.subscription_details.metadata.local_subscription_id'
            ),
        ];

        foreach (
            data_get(
                $object,
                'lines.data',
                []
            ) as $line
        ) {
            $candidates[] =
                data_get(
                    $line,
                    'metadata.local_subscription_id'
                );
        }

        foreach ($candidates as $candidate) {
            if (
                is_numeric($candidate)
                && (int) $candidate > 0
            ) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function providerSubscriptionId(
        array $object
    ): ?string {
        $candidates = [
            data_get(
                $object,
                'subscription'
            ),

            data_get(
                $object,
                'parent.subscription_details.subscription'
            ),
        ];

        foreach (
            data_get(
                $object,
                'lines.data',
                []
            ) as $line
        ) {
            $candidates[] =
                data_get(
                    $line,
                    'parent.subscription_item_details.subscription'
                );

            $candidates[] =
                data_get(
                    $line,
                    'parent.invoice_item_details.subscription'
                );
        }

        foreach ($candidates as $candidate) {
            $id =
                $this->idValue(
                    $candidate
                );

            if (
                $id
                && str_starts_with(
                    $id,
                    'sub_'
                )
            ) {
                return $id;
            }
        }

        return null;
    }

    private function invoicePeriod(
        array $invoice
    ): array {
        $lines =
            data_get(
                $invoice,
                'lines.data',
                []
            );

        $expectedPrice =
            (string) config(
                'services.stripe.price_pro'
            );

        $fallback = [];

        foreach ($lines as $line) {
            $period =
                $this->periodFromTimestamps(
                    data_get(
                        $line,
                        'period.start'
                    ),
                    data_get(
                        $line,
                        'period.end'
                    )
                );

            if (!$period) {
                continue;
            }

            if (!$fallback) {
                $fallback = $period;
            }

            $priceId =
                $this->idValue(
                    data_get(
                        $line,
                        'pricing.price_details.price'
                    )
                )
                ?: $this->idValue(
                    data_get(
                        $line,
                        'price'
                    )
                );

            if (
                $expectedPrice !== ''
                && $priceId === $expectedPrice
            ) {
                return $period;
            }
        }

        return $fallback;
    }

    private function subscriptionPeriod(
        array $stripeSubscription
    ): array {
        $expectedPrice =
            (string) config(
                'services.stripe.price_pro'
            );

        $fallback = [];

        foreach (
            data_get(
                $stripeSubscription,
                'items.data',
                []
            ) as $item
        ) {
            $period =
                $this->periodFromTimestamps(
                    data_get(
                        $item,
                        'current_period_start'
                    ),
                    data_get(
                        $item,
                        'current_period_end'
                    )
                );

            if (!$period) {
                continue;
            }

            if (!$fallback) {
                $fallback = $period;
            }

            $priceId =
                $this->idValue(
                    data_get(
                        $item,
                        'price'
                    )
                );

            if (
                $expectedPrice !== ''
                && $priceId === $expectedPrice
            ) {
                return $period;
            }
        }

        if ($fallback) {
            return $fallback;
        }

        return $this->periodFromTimestamps(
            data_get(
                $stripeSubscription,
                'current_period_start'
            ),
            data_get(
                $stripeSubscription,
                'current_period_end'
            )
        );
    }

    private function periodFromTimestamps(
        mixed $start,
        mixed $end
    ): array {
        if (
            !is_numeric($start)
            || !is_numeric($end)
        ) {
            return [];
        }

        $startAt =
            Carbon::createFromTimestamp(
                (int) $start
            );

        $endAt =
            Carbon::createFromTimestamp(
                (int) $end
            );

        if ($endAt->gt($startAt)) {
            $endAt->subSecond();
        }

        return [
            'start' =>
                $startAt,

            'end' =>
                $endAt,
        ];
    }

    private function eventObject(
        array $payload
    ): array {
        $object =
            data_get(
                $payload,
                'data.object'
            );

        if (!is_array($object)) {
            throw new RuntimeException(
                'Stripe event object not found.'
            );
        }

        return $object;
    }

    private function idValue(
        mixed $value
    ): ?string {
        if (
            is_string($value)
            && $value !== ''
        ) {
            return $value;
        }

        if (
            is_array($value)
            && isset($value['id'])
            && is_string($value['id'])
            && $value['id'] !== ''
        ) {
            return $value['id'];
        }

        return null;
    }
}
