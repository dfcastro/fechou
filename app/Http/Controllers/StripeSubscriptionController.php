<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StripeSubscriptionController extends Controller
{
    public function destroy(
        StripeService $stripe,
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
            !$subscription
            || $subscription->plan?->slug !== 'pro'
        ) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar uma assinatura Pro ativa.'
            );
        }

        if (
            $subscription->billing_status === 'canceling'
            && $subscription->ends_at?->isFuture()
        ) {
            return back()->with(
                'billing_info',
                'O cancelamento desta assinatura já está agendado.'
            );
        }

        if (
            $subscription->payment_provider !== 'stripe'
            || !$subscription->provider_subscription_id
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
            !$periodEnd
            || !$periodEnd->isFuture()
        ) {
            return back()->with(
                'billing_error',
                'Não foi possível identificar o fim do período atual.'
            );
        }

        try {
            $stripe->scheduleCancellation(
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
            'status' =>
                'active',

            'billing_status' =>
                'canceling',

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
