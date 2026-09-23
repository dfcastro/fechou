<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\AsaasService;
use App\Services\SubscriptionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AsaasCheckoutController extends Controller
{
    public function store(
        AsaasService $asaas,
        SubscriptionService $subscriptions
    ): RedirectResponse {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        $subscription =
            $subscriptions->currentSubscription(
                $business
            );

        if (!$subscription) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar sua assinatura atual.'
            );
        }

        if (
            $subscription->plan?->slug === 'pro'
            && $subscription->isActive()
        ) {
            return back()->with(
                'billing_info',
                'O Fechou Pro já está ativo nesta conta.'
            );
        }

        $proPlan = Plan::query()
            ->where('slug', 'pro')
            ->where('is_active', true)
            ->first();

        if (!$proPlan) {
            return back()->with(
                'billing_error',
                'O plano Pro não está disponível no momento.'
            );
        }

        try {
            $checkout =
                $asaas->createProCheckout(
                    $business,
                    $subscription,
                    $proPlan
                );
        } catch (RequestException $exception) {
            report($exception);

            $description = data_get(
                $exception->response?->json(),
                'errors.0.description'
            );

            return back()->with(
                'billing_error',
                $description
                ?: 'O Asaas não conseguiu iniciar o checkout. Tente novamente.'
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with(
                'billing_error',
                $exception->getMessage()
            );
        }

        /*
         * Importante:
         *
         * A criação do checkout NÃO muda o plano para Pro.
         * O upgrade será feito somente após confirmação
         * financeira pelo webhook.
         */
        $subscription->update([
            'payment_provider' => 'asaas',

            'provider_checkout_id' =>
                $checkout['id'],

            'provider_checkout_status' =>
                $checkout['status']
                ?? 'ACTIVE',
        ]);

        return redirect()->away(
            $checkout['link']
        );
    }
}
