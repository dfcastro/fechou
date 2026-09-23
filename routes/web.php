<?php

use App\Http\Controllers\AsaasCheckoutController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\QuotePdfController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post(
    'webhooks/asaas',
    AsaasWebhookController::class
)->name('webhooks.asaas');

Route::get(
    'o/{token}/pdf',
    [QuotePdfController::class, 'publicDownload']
)->name('quotes.public.pdf');

Route::livewire(
    'o/{token}',
    'pages::quotes.public'
)->name('quotes.public');

Route::middleware(['auth'])
    ->group(function () {

        Route::middleware('admin')
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {

                Route::livewire(
                    'metricas',
                    'pages::admin.metrics'
                )->name('metrics');
            });

        Route::livewire(
            'onboarding',
            'pages::onboarding'
        )->name('onboarding');

        Route::livewire(
            'dashboard',
            'pages::dashboard'
        )->name('dashboard');

        Route::livewire(
            'orcamentos',
            'pages::quotes.index'
        )->name('quotes.index');

        Route::livewire(
            'orcamentos/pipeline',
            'pages::quotes.pipeline'
        )->name('quotes.pipeline');


        Route::livewire(
            'relatorios',
            'pages::reports.commercial'
        )->name('reports.commercial');

        Route::livewire(
            'modelos',
            'pages::quote-templates.index'
        )->name('quote-templates.index');

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
            'orcamentos/{quote}/pdf',
            [QuotePdfController::class, 'download']
        )->name('quotes.pdf');

        Route::livewire(
            'orcamentos/{quote}',
            'pages::quotes.show'
        )->name('quotes.show');

        Route::livewire(
            'clientes',
            'pages::clients.index'
        )->name('clients.index');

        Route::livewire(
            'configuracoes/empresa',
            'pages::settings.business'
        )->name('settings.business');

        Route::livewire(
            'configuracoes/cobranca',
            'pages::settings.payment'
        )->name('settings.payment');

        Route::livewire(
            'configuracoes/plano',
            'pages::settings.subscription'
        )->name('settings.subscription');

        Route::post(
            'configuracoes/plano/checkout/asaas',
            [AsaasCheckoutController::class, 'store']
        )
            ->middleware('verified')
            ->name('settings.subscription.checkout.asaas');

        Route::livewire(
            'configuracoes/follow-up',
            'pages::settings.follow-up'
        )->name('settings.follow-up');
    });

require __DIR__ . '/settings.php';
