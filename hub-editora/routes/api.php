<?php

use App\Http\Controllers\Api\AppOrdersController;
use App\Http\Middleware\VerifyAppSignature;
use Illuminate\Support\Facades\Route;

/*
 * API servidor→servidor. Sem sessão nem cookies; cada rota exige assinatura.
 */

// App do leitor: o site pergunta os pedidos de quem está logado no app.
Route::get('app/pedidos', AppOrdersController::class)
    ->middleware([VerifyAppSignature::class, 'throttle:120,1'])
    ->name('api.app.pedidos');
