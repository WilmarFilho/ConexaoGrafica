<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Varreduras de segurança por canal. Os webhooks trazem o pedido na hora;
 * isto aqui pega o que eles perderem. Em produção: cron do cPanel chamando
 * `php artisan schedule:run` a cada minuto.
 */
Schedule::command('hub:sync woocommerce')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('hub:sync pagarme')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Catálogo muda pouco: uma vez por dia, de madrugada.
Schedule::command('hub:sync-products woocommerce')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->onOneServer();

// Rastreio: o Melhor Envio não empurra eventos; a cada hora perguntamos.
Schedule::command('hub:tracking')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
