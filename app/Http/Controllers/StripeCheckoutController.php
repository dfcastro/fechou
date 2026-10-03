<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use App\Support\BrazilianInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Stripe\Exception\ApiErrorException;

class StripeCheckoutController extends Controller
{
    public function store(
        Request $request,
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

        if (!$subscription) {
            return back()->with(
                'billing_error',
                'Não foi possível localizar sua assinatura atual.'
            );
        }

        if (
            $subscription->plan?->slug
                === 'pro'
            && $subscription->isActive()
        ) {
            return back()->with(
                'billing_info',
                'O Negozia Pro já está ativo nesta conta.'
            );
        }

        if (
            $request->boolean(
                'billing_address_submit'
            )
        ) {
            $validated =
                $request->validate(
                    [
                        'billing_postal_code' => [
                            'required',
                            'string',
                            'regex:/^\d{5}-?\d{3}$/',
                        ],

                        'billing_address' => [
                            'required',
                            'string',
                            'max:255',
                        ],

                        'billing_address_number' => [
                            'required',
                            'string',
                            'max:20',
                        ],

                        'billing_address_complement' => [
                            'nullable',
                            'string',
                            'max:255',
                        ],

                        'billing_province' => [
                            'required',
                            'string',
                            'max:255',
                        ],
                    ],
                    [
                        'billing_postal_code.required' =>
                            'Informe o CEP.',

                        'billing_postal_code.regex' =>
                            'Informe um CEP válido.',

                        'billing_address.required' =>
                            'Informe o endereço.',

                        'billing_address_number.required' =>
                            'Informe o número.',

                        'billing_province.required' =>
                            'Informe o bairro.',
                    ]
                );

            $business->update([
                'postal_code' =>
                    BrazilianInput::cep(
                        $validated[
                            'billing_postal_code'
                        ]
                    ),

                'address' =>
                    trim(
                        $validated[
                            'billing_address'
                        ]
                    ),

                'address_number' =>
                    trim(
                        $validated[
                            'billing_address_number'
                        ]
                    ),

                'address_complement' =>
                    filled(
                        $validated[
                            'billing_address_complement'
                        ] ?? null
                    )
                        ? trim(
                            $validated[
                                'billing_address_complement'
                            ]
                        )
                        : null,

                'province' =>
                    trim(
                        $validated[
                            'billing_province'
                        ]
                    ),
            ]);
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

        if (!$proPlan) {
            return back()->with(
                'billing_error',
                'O plano Pro não está disponível no momento.'
            );
        }

        try {
            $checkout =
                $stripe
                    ->createProCheckout(
                        $business,
                        $subscription,
                        $proPlan
                    );
        } catch (
            ApiErrorException $exception
        ) {
            report(
                $exception
            );

            return back()->with(
                'billing_error',
                'A Stripe não conseguiu iniciar o pagamento. Tente novamente.'
            );
        } catch (
            RuntimeException $exception
        ) {
            report(
                $exception
            );

            return back()->with(
                'billing_error',
                $exception->getMessage()
            );
        }

        /*
         * Criar o checkout não libera o Pro.
         * O plano será ativado apenas pelo webhook.
         */
        $subscription->update([
            'payment_provider' =>
                'stripe',

            'provider_checkout_id' =>
                $checkout['id'],

            'provider_checkout_status' =>
                $checkout['status'],

            'provider_customer_id' =>
                $checkout[
                    'customer_id'
                ],
        ]);

        return redirect()->away(
            $checkout['url']
        );
    }
}
