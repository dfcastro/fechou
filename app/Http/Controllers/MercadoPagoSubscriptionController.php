<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\MercadoPagoService;
use App\Services\SubscriptionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class MercadoPagoSubscriptionController extends Controller
{
    public function create(
        SubscriptionService $subscriptions
    ): View|RedirectResponse {
        $business =
            Auth::user()->business;

        abort_unless(
            $business,
            403
        );

        $subscription =
            $subscriptions
                ->currentSubscription(
                    $business
                );

        if (! $subscription) {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_error',
                    'Não foi possível localizar sua assinatura atual.'
                );
        }

        if (
            $subscription
                ->plan
                ?->slug === 'pro'
            && $subscription
                ->isActive()
        ) {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_info',
                    'O Negozia Pro já está ativo nesta conta.'
                );
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
            ->first();

        if (! $proPlan) {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_error',
                    'O plano Pro não está disponível no momento.'
                );
        }

        $publicKey = trim(
            (string) config(
                'services.mercadopago.public_key'
            )
        );

        if ($publicKey === '') {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_error',
                    'A Public Key do Mercado Pago não está configurada.'
                );
        }

        $payerEmail =
            config(
                'services.mercadopago.environment'
            ) === 'production'
                ? trim(
                    (string) (
                        $business->email
                        ?: $business->user?->email
                    )
                )
                : 'test@testuser.com';

        return view(
            'billing.subscription-card',
            [
                'publicKey' =>
                    $publicKey,

                'amount' =>
                    number_format(
                        (float) $proPlan->price,
                        2,
                        '.',
                        ''
                    ),

                'payerEmail' =>
                    $payerEmail,

                'isTest' =>
                    config(
                        'services.mercadopago.environment'
                    ) !== 'production',
            ]
        );
    }

    public function store(
        Request $request,
        MercadoPagoService $mercadoPago,
        SubscriptionService $subscriptions
    ): RedirectResponse {
        $validated = $request->validate([
            'card_token_id' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $business =
            Auth::user()->business;

        abort_unless(
            $business,
            403
        );

        $subscription =
            $subscriptions
                ->currentSubscription(
                    $business
                );

        if (! $subscription) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar sua assinatura atual.'
            );
        }

        if (
            $subscription
                ->plan
                ?->slug === 'pro'
            && $subscription
                ->isActive()
        ) {
            return back()->with(
                'billing_info',
                'O Negozia Pro já está ativo nesta conta.'
            );
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
            ->first();

        if (! $proPlan) {
            return back()->with(
                'billing_error',
                'O plano Pro não está disponível no momento.'
            );
        }

        try {
            $preapproval =
                $mercadoPago
                    ->createProSubscription(
                        $business,
                        $subscription,
                        $validated[
                            'card_token_id'
                        ]
                    );
        } catch (
            RequestException
            |RuntimeException
            $exception
        ) {
            report(
                $exception
            );

            return back()->with(
                'billing_error',
                'O Mercado Pago não conseguiu iniciar a assinatura. Tente novamente.'
            );
        }

        /*
         * A autorização da recorrência ainda não significa
         * que a primeira mensalidade foi confirmada.
         *
         * O Pro será ativado pelo webhook da cobrança.
         */
        $subscription->update([
            'payment_provider' =>
                'mercadopago_subscription',

            'billing_status' =>
                'pending',

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
                    : null,

            'provider_subscription_id' =>
                (string) data_get(
                    $preapproval,
                    'id'
                ),

            'provider_checkout_id' =>
                null,

            'provider_checkout_status' =>
                (string) data_get(
                    $preapproval,
                    'status'
                ),

            'provider_payment_id' =>
                null,

            'provider_payment_status' =>
                null,

            'past_due_at' =>
                null,

            'grace_ends_at' =>
                null,

            'access_suspended_at' =>
                null,
        ]);

        return redirect()
            ->route(
                'settings.subscription'
            )
            ->with(
                'billing_info',
                'Assinatura autorizada. Estamos aguardando a confirmação da primeira cobrança.'
            );
    }
}
