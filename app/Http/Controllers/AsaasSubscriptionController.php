<?php

namespace App\Http\Controllers;

use App\Services\AsaasService;
use App\Services\SubscriptionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AsaasSubscriptionController extends Controller
{
    public function destroy(
        AsaasService $asaas,
        SubscriptionService $subscriptions
    ): RedirectResponse {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        $subscription =
            $subscriptions->currentSubscription(
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
            $subscription->payment_provider !== 'asaas'
            || !$subscription->provider_subscription_id
        ) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar a assinatura no Asaas.'
            );
        }

        try {
            $asaas->cancelSubscription(
                $subscription
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
                ?: 'O Asaas não conseguiu cancelar a assinatura. Tente novamente.'
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with(
                'billing_error',
                $exception->getMessage()
            );
        }

        /*
         * Atualizamos imediatamente o estado local para que
         * o usuário não precise aguardar o webhook para receber
         * feedback visual.
         *
         * SUBSCRIPTION_DELETED continuará sendo a confirmação
         * assíncrona e manterá os dados sincronizados.
         */
        $periodEnd =
            $subscription->current_period_ends_at;

        $canKeepAccess =
            $periodEnd
            && $periodEnd->isFuture()
            && $subscription
                ->access_suspended_at === null
            && (
                $subscription->status === 'active'
                || $subscription->isInGracePeriod()
            );

        if ($canKeepAccess) {
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

                'access_suspended_at' =>
                    null,
            ]);

            return back()->with(
                'billing_info',
                'Cancelamento agendado. Seu Pro permanece disponível até '
                . $periodEnd->format('d/m/Y')
                . '.'
            );
        }

        $subscription->update([
            'status' =>
                'canceled',

            'billing_status' =>
                'canceled',

            'canceled_at' =>
                now(),

            'ends_at' =>
                now(),

            'access_suspended_at' =>
                now(),
        ]);

        $subscriptions->ensureDefaultSubscription(
            $business
        );

        return back()->with(
            'billing_info',
            'Assinatura cancelada.'
        );
    }
}
