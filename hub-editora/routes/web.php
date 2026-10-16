<?php

use App\Http\Controllers\Webhooks\PagarMeWebhookController;
use App\Http\Controllers\Webhooks\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;

// O hub é só o painel: a raiz leva para ele.
Route::redirect('/', '/admin');

// Política pública de privacidade e tratamento de dados (exigida pela Amazon).
Route::view('/privacidade', 'privacidade')->name('privacidade');
Route::redirect('/politica-de-privacidade', '/privacidade');

// Entrada dos canais. Sem CSRF (ver bootstrap/app.php); cada um valida do seu jeito.
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('woocommerce', WooCommerceWebhookController::class)->name('woocommerce');
    Route::post('pagarme', PagarMeWebhookController::class)->name('pagarme');
});
