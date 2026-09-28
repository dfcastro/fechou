<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\AsaasService;
use App\Services\SubscriptionService;
use App\Support\BrazilianInput;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AsaasCheckoutController extends Controller
{
    public function store(
        Request $request,
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

        /*
         * Quando o checkout parte do modal da tela de assinatura,
         * validamos e salvamos os dados de cobrança antes de
         * conversar com o Asaas.
         *
         * A validação defensiva do AsaasService continua existindo
         * para chamadas que não passem pelo modal.
         */
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
                            'regex:/^\\d{5}-?\\d{3}$/',
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
                        ]
                        ?? null
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
