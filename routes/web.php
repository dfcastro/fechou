<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuotePdfController;

Route::view('/', 'welcome')->name('home');

Route::livewire('o/{token}', 'pages::quotes.public')
    ->name('quotes.public');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::livewire('dashboard', 'pages::dashboard')
        ->name('dashboard');

    Route::get(
        'orcamentos/{quote}/pdf',
        [QuotePdfController::class, 'download']
    )->name('quotes.pdf');

    Route::livewire('orcamentos', 'pages::quotes.index')
        ->name('quotes.index');

    Route::livewire('orcamentos/novo', 'pages::quotes.create')
        ->name('quotes.create');

    Route::livewire('orcamentos/{quote}/editar', 'pages::quotes.edit')
        ->name('quotes.edit');

    Route::get(
        'orcamentos/{quote}/pdf/visualizar',
        [QuotePdfController::class, 'preview']
    )->name('quotes.pdf.preview');


    Route::get(
        'orcamentos/{quote}/pdf',
        [QuotePdfController::class, 'download']
    )->name('quotes.pdf');
    
    Route::livewire('orcamentos/{quote}', 'pages::quotes.show')
        ->name('quotes.show');

    Route::livewire('clientes', 'pages::clients.index')
        ->name('clients.index');

    Route::livewire(
        'configuracoes/empresa',
        'pages::settings.business'
    )->name('settings.business');
});

require __DIR__ . '/settings.php';
