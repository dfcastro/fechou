<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use App\Models\BusinessAccessGrant;

class SubscriptionService
{
    /*
    |--------------------------------------------------------------------------
    | Assinatura atual
    |--------------------------------------------------------------------------
    */

    public function currentSubscription(
        Business $business
    ): ?Subscription {
        return $business
            ->currentSubscription()
            ->with('plan')
            ->first();
    }


    /*
    |--------------------------------------------------------------------------
    | Plano contratado
    |--------------------------------------------------------------------------
    */

    public function currentPlan(
        Business $business
    ): ?Plan {
        return $this
            ->currentSubscription($business)
                ?->plan;
    }


    /*
    |--------------------------------------------------------------------------
    | Assinatura financeiramente ativa
    |--------------------------------------------------------------------------
    */

    public function hasActiveSubscription(
        Business $business
    ): bool {
        $subscription = $this->currentSubscription(
            $business
        );

        return $subscription?->isActive() === true;
    }


    /*
    |--------------------------------------------------------------------------
    | Direito de uso do plano contratado
    |--------------------------------------------------------------------------
    |
    | isActive() continua representando o estado financeiro/lifecycle estrito.
    |
    | Aqui tratamos o período de tolerância de cobrança:
    | - active / trialing válido: usa o plano contratado;
    | - past_due dentro da tolerância: continua usando o plano contratado;
    | - past_due fora da tolerância: cai para as regras do plano Grátis,
    |   sem apagar dados Pro nem trocar o plan_id contratado.
    |
    */

    public function hasPlanAccess(
        Subscription $subscription
    ): bool {
        /*
         * Pagamentos manuais como Pix possuem período
         * adquirido com data final definida.
         *
         * Mesmo que o status financeiro ainda esteja
         * "active", o acesso termina quando ends_at passa.
         */
        if (
            $subscription->ends_at
            && $subscription->ends_at->isPast()
        ) {
            return false;
        }

        return $subscription->isActive()
            || $subscription->isInGracePeriod();
    }


    public function activeAccessGrant(
        Business $business
    ): ?BusinessAccessGrant {
        return $business
            ->accessGrants()
            ->active()
            ->with('plan')
            ->latest('ends_at')
            ->first();
    }
    public function accessPlan(
        Business $business
    ): ?Plan {
        /*
         * Um benefício administrativo ativo prevalece
         * temporariamente sobre o plano contratado.
         *
         * Não alteramos a assinatura real nem os dados
         * financeiros do Asaas.
         */
        $grant = $this->activeAccessGrant(
            $business
        );

        if ($grant?->plan) {
            return $grant->plan;
        }

        $subscription = $this->currentSubscription(
            $business
        );

        if (!$subscription) {
            return null;
        }

        if ($this->hasPlanAccess($subscription)) {
            return $subscription->plan;
        }

        /*
         * Período pago manualmente encerrado.
         *
         * Mantemos o histórico da assinatura Pro, mas
         * os recursos disponíveis passam a ser os do
         * plano Grátis até uma nova renovação.
         */
        if (
            !$subscription->plan->isFree()
            && $subscription->ends_at
            && $subscription->ends_at->isPast()
        ) {
            return Plan::query()
                ->where('slug', 'free')
                ->where('is_active', true)
                ->first();
        }


        /*
         * Somente assinaturas pagas via gateway suspensas por cobrança
         * recebem fallback para o Grátis.
         *
         * Isso preserva o comportamento atual de trials expirados e
         * outras assinaturas inválidas: nesses casos não liberamos
         * recursos automaticamente.
         */
        if (
            in_array(
                $subscription->payment_provider,
                [
                    'asaas',
                    'stripe',
                    'mercadopago_subscription',
                ],
                true
            )
            && $subscription->status === 'past_due'
            && !$subscription->plan->isFree()
        ) {
            return Plan::query()
                ->where('slug', 'free')
                ->where('is_active', true)
                ->first();
        }

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | Recursos do plano
    |--------------------------------------------------------------------------
    */

    public function hasFeature(
        Business $business,
        PlanFeature|string $feature
    ): bool {
        $plan = $this->accessPlan(
            $business
        );

        if (!$plan) {
            return false;
        }

        return $plan->hasFeature(
            $feature
        );
    }


    public function hasAllFeatures(
        Business $business,
        array $features
    ): bool {
        $plan = $this->accessPlan(
            $business
        );

        if (!$plan) {
            return false;
        }

        return $plan->hasAllFeatures(
            $features
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Criação de proposta
    |--------------------------------------------------------------------------
    */

    public function canCreateQuote(
        Business $business
    ): bool {
        $plan = $this->accessPlan(
            $business
        );

        if (!$plan) {
            return false;
        }

        if ($plan->hasUnlimitedQuotes()) {
            return true;
        }

        return $this->quotesUsed($business)
            < $plan->quote_limit;
    }


    /*
    |--------------------------------------------------------------------------
    | Uso de propostas
    |--------------------------------------------------------------------------
    */

    public function quotesUsed(
        Business $business
    ): int {
        $subscription = $this->currentSubscription(
            $business
        );

        if (!$subscription) {
            return 0;
        }

        [$start, $end] = $this->usagePeriod(
            $subscription
        );

        return $business
            ->quotes()
            ->withTrashed()
            ->whereBetween(
                'created_at',
                [
                    $start,
                    $end,
                ]
            )
            ->count();
    }


    /*
    |--------------------------------------------------------------------------
    | Propostas restantes
    |--------------------------------------------------------------------------
    */

    public function quotesRemaining(
        Business $business
    ): ?int {
        $plan = $this->accessPlan(
            $business
        );

        if (!$plan) {
            return 0;
        }

        if ($plan->hasUnlimitedQuotes()) {
            return null;
        }

        return max(
            0,
            $plan->quote_limit
            - $this->quotesUsed($business)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Período de utilização
    |--------------------------------------------------------------------------
    */

    private function usagePeriod(
        Subscription $subscription
    ): array {
        $start = $subscription
            ->current_period_starts_at
                ?->copy()
            ->startOfDay();

        $end = $subscription
            ->current_period_ends_at
                ?->copy()
            ->endOfDay();

        return [
            $start
            ?? Carbon::now()->startOfMonth(),

            $end
            ?? Carbon::now()->endOfMonth(),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Assinatura padrão
    |--------------------------------------------------------------------------
    */

    public function ensureDefaultSubscription(
        Business $business
    ): Subscription {
        $current = $this->currentSubscription(
            $business
        );

        if ($current) {
            return $current;
        }

        $plan = Plan::query()
            ->where(
                'slug',
                'free'
            )
            ->where(
                'is_active',
                true
            )
            ->firstOrFail();

        return Subscription::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $plan->id,

            'status' =>
                'active',

            'starts_at' =>
                now(),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);
    }
}
