<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use RuntimeException;
use Stripe\StripeClient;

class StripeService
{
    public function createProCheckout(
        Business $business,
        Subscription $subscription,
        Plan $plan
    ): array {
        $priceId = trim(
            (string) config(
                'services.stripe.price_pro'
            )
        );

        if ($priceId === '') {
            throw new RuntimeException(
                'O preço do Negozia Pro não está configurado na Stripe.'
            );
        }

        $customerId = $this->ensureCustomer(
            $business,
            $subscription
        );

        $metadata = [
            'business_id' =>
                (string) $business->id,

            'local_subscription_id' =>
                (string) $subscription->id,

            'plan_id' =>
                (string) $plan->id,

            'plan_slug' =>
                (string) $plan->slug,
        ];

        $baseUrl = route(
            'settings.subscription'
        );

        $session = $this
            ->client()
            ->checkout
            ->sessions
            ->create([
                'mode' =>
                    'subscription',

                'customer' =>
                    $customerId,

                'line_items' => [
                    [
                        'price' =>
                            $priceId,

                        'quantity' =>
                            1,
                    ],
                ],

                'success_url' =>
                    $baseUrl
                    . '?checkout=success'
                    . '&session_id={CHECKOUT_SESSION_ID}',

                'cancel_url' =>
                    $baseUrl
                    . '?checkout=canceled',

                'client_reference_id' =>
                    (string) $business->id,

                'metadata' =>
                    $metadata,

                'subscription_data' => [
                    'metadata' =>
                        $metadata,
                ],

                'billing_address_collection' =>
                    'auto',
            ]);

        if (
            !$session->id
            || !$session->url
        ) {
            throw new RuntimeException(
                'A Stripe não retornou uma sessão de pagamento válida.'
            );
        }

        return [
            'id' =>
                $session->id,

            'url' =>
                $session->url,

            'status' =>
                $session->status
                ?? 'open',

            'customer_id' =>
                $customerId,
        ];
    }

    public function ensureCustomer(
        Business $business,
        Subscription $subscription
    ): string {
        $payload = $this->customerPayload(
            $business
        );

        if (
            $subscription->payment_provider
                === 'stripe'
            && $subscription
                ->provider_customer_id
        ) {
            $customerId =
                $subscription
                    ->provider_customer_id;

            $this
                ->client()
                ->customers
                ->update(
                    $customerId,
                    $payload
                );

            return $customerId;
        }

        $customer = $this
            ->client()
            ->customers
            ->create(
                $payload
            );

        if (!$customer->id) {
            throw new RuntimeException(
                'A Stripe não retornou o identificador do cliente.'
            );
        }

        $subscription->update([
            'payment_provider' =>
                'stripe',

            'provider_customer_id' =>
                $customer->id,
        ]);

        return $customer->id;
    }

    private function customerPayload(
        Business $business
    ): array {
        $payload = [
            'name' =>
                trim(
                    (string) $business->name
                ),

            'metadata' => [
                'business_id' =>
                    (string) $business->id,
            ],
        ];

        $email = trim(
            (string) (
                $business->email
                ?: $business->user?->email
            )
        );

        if (
            $email !== ''
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $payload['email'] =
                $email;
        }

        $phone = preg_replace(
            '/\D+/',
            '',
            (string) (
                $business->whatsapp
                ?: $business->phone
            )
        );

        if ($phone !== '') {
            $payload['phone'] =
                '+55' . $phone;
        }

        $address = trim(
            (string) $business->address
        );

        if ($address !== '') {
            $number = trim(
                (string)
                $business->address_number
            );

            $line1 = $number !== ''
                ? $address . ', ' . $number
                : $address;

            $payload['address'] = [
                'line1' =>
                    $line1,

                'line2' =>
                    $business
                        ->address_complement
                    ?: null,

                'city' =>
                    $business->city
                    ?: null,

                'state' =>
                    $business->state
                    ?: null,

                'postal_code' =>
                    $business->postal_code
                    ?: null,

                'country' =>
                    'BR',
            ];

            $payload['address'] =
                array_filter(
                    $payload['address'],
                    fn ($value) =>
                        $value !== null
                        && $value !== ''
                );
        }

        return $payload;
    }

    private function client(): StripeClient
    {
        $secret = trim(
            (string) config(
                'services.stripe.secret'
            )
        );

        if ($secret === '') {
            throw new RuntimeException(
                'A chave da Stripe não está configurada.'
            );
        }

        return new StripeClient(
            $secret
        );
    }

    public function scheduleCancellation(
        \App\Models\Subscription $subscription
    ): void {
        $secret = trim(
            (string) config(
                'services.stripe.secret'
            )
        );

        if ($secret === '') {
            throw new \RuntimeException(
                'A chave da Stripe não está configurada.'
            );
        }

        $subscriptionId = trim(
            (string)
            $subscription->provider_subscription_id
        );

        if ($subscriptionId === '') {
            throw new \RuntimeException(
                'A assinatura da Stripe não foi localizada.'
            );
        }

        $stripe = new \Stripe\StripeClient(
            $secret
        );

        $stripe->subscriptions->update(
            $subscriptionId,
            [
                'cancel_at_period_end' => true,
            ]
        );
    }

}
