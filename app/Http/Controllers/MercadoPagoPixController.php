<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\MercadoPagoService;
use App\Services\SubscriptionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class MercadoPagoPixController extends Controller
{
    public function store(
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
            && $subscription
                ->payment_provider
                !== 'mercadopago_pix'
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
            $order =
                $mercadoPago
                    ->createPixOrder(
                        $business,
                        $subscription,
                        $proPlan
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
                'O Mercado Pago não conseguiu gerar o Pix. Tente novamente.'
            );
        }

        $payment = data_get(
            $order,
            'transactions.payments.0',
            []
        );

        $subscription->update([
            'payment_provider' => 'mercadopago_pix',

            'provider_subscription_id' => null,

            'provider_checkout_id' => data_get(
                $order,
                'id'
            ),

            'provider_checkout_status' => data_get(
                $order,
                'status'
            ),

            'provider_payment_id' => data_get(
                $payment,
                'id'
            ),

            'provider_payment_status' => data_get(
                $payment,
                'status'
            ),
        ]);

        return redirect()->route(
            'settings.subscription.pix'
        );
    }

    public function show(
        MercadoPagoService $mercadoPago,
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

        if (
            ! $subscription
            || $subscription
                ->payment_provider
                !== 'mercadopago_pix'
            || ! $subscription
                ->provider_checkout_id
        ) {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_error',
                    'Nenhum Pix pendente foi encontrado.'
                );
        }

        try {
            $order =
                $mercadoPago
                    ->getOrder(
                        $subscription
                            ->provider_checkout_id
                    );

            /*
             * Fallback de segurança:
             *
             * se o webhook atrasar ou falhar temporariamente,
             * a própria consulta da página sincroniza um Pix
             * já processado pelo Mercado Pago.
             */
            $paid =
                $mercadoPago
                    ->syncPixOrder(
                        $subscription,
                        $order
                    );

            if ($paid) {
                return redirect()
                    ->route(
                        'settings.subscription'
                    )
                    ->with(
                        'billing_info',
                        'Pix confirmado. O Negozia Pro está ativo por 30 dias.'
                    );
            }
        } catch (
            RequestException
            |RuntimeException
            $exception
        ) {
            report(
                $exception
            );

            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_error',
                    'Não foi possível consultar o Pix.'
                );
        }

        return view(
            'billing.pix',
            [
                'subscription' => $subscription,

                'order' => $order,

                'payment' => data_get(
                    $order,
                    'transactions.payments.0',
                    []
                ),

                'paymentMethod' => data_get(
                    $order,
                    'transactions.payments.0.payment_method',
                    []
                ),
            ]
        );
    }

    public function check(
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
                ->payment_provider
                !== 'mercadopago_pix'
            || ! $subscription
                ->provider_checkout_id
        ) {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_error',
                    'Nenhum Pix pendente foi encontrado.'
                );
        }

        try {
            $order =
                $mercadoPago
                    ->getOrder(
                        $subscription
                            ->provider_checkout_id
                    );

            $paid =
                $mercadoPago
                    ->syncPixOrder(
                        $subscription,
                        $order
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
                'Não foi possível confirmar o pagamento.'
            );
        }

        if ($paid) {
            return redirect()
                ->route(
                    'settings.subscription'
                )
                ->with(
                    'billing_info',
                    'Pix confirmado. O Negozia Pro está ativo por 30 dias.'
                );
        }

        return back()->with(
            'billing_info',
            'O Pix ainda está aguardando pagamento.'
        );
    }
}
