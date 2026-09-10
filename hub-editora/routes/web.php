<?php

use App\Http\Controllers\BlingOAuthController;
use App\Http\Controllers\Webhooks\PagarMeWebhookController;
use App\Http\Controllers\Webhooks\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;

// O hub é só o painel: a raiz leva para ele.
Route::redirect('/', '/admin');

// Autorização OAuth do Bling (só logado no painel; usa a sessão do Filament).
Route::middleware(['web', 'auth'])->prefix('bling')->name('bling.')->group(function () {
    Route::get('connect', [BlingOAuthController::class, 'connect'])->name('connect');
    Route::get('callback', [BlingOAuthController::class, 'callback'])->name('callback');
});

// Entrada dos canais. Sem CSRF (ver bootstrap/app.php); cada um valida do seu jeito.
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('woocommerce', WooCommerceWebhookController::class)->name('woocommerce');
    Route::post('pagarme', PagarMeWebhookController::class)->name('pagarme');
});
