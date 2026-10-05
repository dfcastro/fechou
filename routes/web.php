<?php

use App\Http\Controllers\AsaasCheckoutController;
use App\Http\Controllers\AsaasSubscriptionController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\StripeSubscriptionController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\MercadoPagoPixController;
use App\Http\Controllers\StripeCheckoutController;
use App\Http\Controllers\QuotePdfController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Página pública
|--------------------------------------------------------------------------
*/

Route::view(
    '/',
    'welcome'
)->name('home');


/*
|--------------------------------------------------------------------------
| Webhook Asaas
|--------------------------------------------------------------------------
*/

Route::post(
    'webhooks/asaas',
    AsaasWebhookController::class
)->name('webhooks.asaas');


/*
|--------------------------------------------------------------------------
| Webhook Stripe
|--------------------------------------------------------------------------
*/

Route::post(
    'webhooks/stripe',
    StripeWebhookController::class
)->name('webhooks.stripe');


Route::post(
    'webhooks/mercadopago',
    MercadoPagoWebhookController::class
)->name('webhooks.mercadopago');


/*
|--------------------------------------------------------------------------
| Propostas públicas
|--------------------------------------------------------------------------
*/

Route::get(
    'o/{token}/pdf',
    [QuotePdfController::class, 'publicDownload']
)->name('quotes.public.pdf');

Route::livewire(
    'o/{token}',
    'pages::quotes.public'
)->name('quotes.public');


/*
|--------------------------------------------------------------------------
| Área autenticada
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'track.activity',
    'platform.admin.redirect',
])
    ->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Administração
        |--------------------------------------------------------------------------
        */

        Route::middleware('admin')
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {

                Route::livewire(
                    'metricas',
                    'pages::admin.metrics'
                )->name('metrics');

                Route::livewire(
                    'empresas',
                    'pages::admin.businesses.index'
                )->name('businesses.index');

                Route::livewire(
                    'empresas/{business}',
                    'pages::admin.businesses.show'
                )->name('businesses.show');

                Route::livewire(
                    'acessos',
                    'pages::admin.accesses'
                )->name('accesses');

                Route::livewire(
                    'auditoria',
                    'pages::admin.audit'
                )->name('audit');


            });


        /*
        |--------------------------------------------------------------------------
        | Onboarding
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'onboarding',
            'pages::onboarding'
        )->name('onboarding');


        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'dashboard',
            'pages::dashboard'
        )->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Propostas
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'orcamentos',
            'pages::quotes.index'
        )->name('quotes.index');

        Route::livewire(
            'orcamentos/pipeline',
            'pages::quotes.pipeline'
        )->name('quotes.pipeline');

        Route::livewire(
            'orcamentos/novo',
            'pages::quotes.create'
        )->name('quotes.create');

        Route::livewire(
            'orcamentos/{quote}/editar',
            'pages::quotes.edit'
        )->name('quotes.edit');

        Route::get(
            'orcamentos/{quote}/pdf/visualizar',
            [QuotePdfController::class, 'preview']
        )->name('quotes.pdf.preview');

        Route::get(
            'orcamentos/{quote}/pdf/visualizar/arquivo',
            [QuotePdfController::class, 'previewFile']
        )->name('quotes.pdf.preview.file');

        Route::get(
            'orcamentos/{quote}/pdf',
            [QuotePdfController::class, 'download']
        )->name('quotes.pdf');

        Route::livewire(
            'orcamentos/{quote}',
            'pages::quotes.show'
        )->name('quotes.show');


        /*
        |--------------------------------------------------------------------------
        | Relatórios
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'relatorios',
            'pages::reports.commercial'
        )->name('reports.commercial');


        /*
        |--------------------------------------------------------------------------
        | Modelos
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'modelos',
            'pages::quote-templates.index'
        )->name('quote-templates.index');


        /*
        |--------------------------------------------------------------------------
        | Clientes
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'clientes',
            'pages::clients.index'
        )->name('clients.index');


        /*
        |--------------------------------------------------------------------------
        | Configurações da empresa
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'configuracoes/empresa',
            'pages::settings.business'
        )->name('settings.business');


        /*
        |--------------------------------------------------------------------------
        | Configurações de cobrança
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'configuracoes/cobranca',
            'pages::settings.payment'
        )->name('settings.payment');


        /*
        |--------------------------------------------------------------------------
        | Plano e assinatura
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'configuracoes/plano',
            'pages::settings.subscription'
        )->name('settings.subscription');

        Route::post(
            'configuracoes/plano/checkout/asaas',
            [AsaasCheckoutController::class, 'store']
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.checkout.asaas'
            );

        Route::post(
            'configuracoes/plano/checkout/stripe',
            [StripeCheckoutController::class, 'store']
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.checkout.stripe'
            );


        Route::post(
            'configuracoes/plano/checkout/pix',
            [MercadoPagoPixController::class, 'store']
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.checkout.pix'
            );

        Route::get(
            'configuracoes/plano/pix',
            [MercadoPagoPixController::class, 'show']
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.pix'
            );

        Route::post(
            'configuracoes/plano/pix/verificar',
            [MercadoPagoPixController::class, 'check']
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.pix.check'
            );


        Route::delete(
            'configuracoes/plano/assinatura/stripe',
            [StripeSubscriptionController::class, 'destroy']
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.cancel.stripe'
            );


        Route::delete(
            'configuracoes/plano/assinatura/asaas',
            [
                AsaasSubscriptionController::class,
                'destroy',
            ]
        )
            ->middleware('verified')
            ->name(
                'settings.subscription.cancel.asaas'
            );


        /*
        |--------------------------------------------------------------------------
        | Follow-up
        |--------------------------------------------------------------------------
        */

        Route::livewire(
            'configuracoes/follow-up',
            'pages::settings.follow-up'
        )->name('settings.follow-up');
    });


/*
|--------------------------------------------------------------------------
| Configurações de conta
|--------------------------------------------------------------------------
*/

require __DIR__ . '/settings.php';