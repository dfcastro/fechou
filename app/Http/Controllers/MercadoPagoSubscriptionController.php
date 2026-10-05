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
use Throwable;

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

    public function destroy(
        MercadoPagoService $mercadoPago,
        SubscriptionService $subscriptions
    ): RedirectResponse {
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

        if (
            ! $subscription
            || $subscription
                ->plan
                ?->slug !== 'pro'
        ) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar uma assinatura Pro ativa.'
            );
        }

        if (
            $subscription
                ->billing_status === 'canceling'
            && $subscription
                ->ends_at
                ?->isFuture()
        ) {
            return back()->with(
                'billing_info',
                'O cancelamento desta assinatura já está agendado.'
            );
        }

        if (
            $subscription
                ->payment_provider
                !== 'mercadopago_subscription'
            || ! $subscription
                ->provider_subscription_id
        ) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar a assinatura recorrente.'
            );
        }

        $periodEnd =
            $subscription
                ->current_period_ends_at;

        if (
            ! $periodEnd
            || ! $periodEnd->isFuture()
        ) {
            return back()->with(
                'billing_error',
                'Não foi possível identificar o fim do período atual.'
            );
        }

        try {
            $preapproval =
                $mercadoPago
                    ->cancelSubscription(
                        $subscription
                    );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return back()->with(
                'billing_error',
                'Não foi possível cancelar a renovação agora. Tente novamente.'
            );
        }

        $subscription->update([
            /*
             * O Mercado Pago cancela novas cobranças,
             * mas o período já pago continua disponível.
             */
            'status' =>
                'active',

            'billing_status' =>
                'canceling',

            'provider_checkout_status' =>
                (string) data_get(
                    $preapproval,
                    'status',
                    'cancelled'
                ),

            'canceled_at' =>
                now(),

            'ends_at' =>
                $periodEnd,

            'past_due_at' =>
                null,

            'grace_ends_at' =>
                null,
        ]);

        return back()->with(
            'billing_info',
            'Renovação cancelada. O Negozia Pro continua disponível até '
            . $periodEnd->format('d/m/Y')
            . '.'
        );
    }

}
