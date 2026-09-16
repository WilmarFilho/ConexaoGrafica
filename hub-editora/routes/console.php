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

// Amazon chega pelo Bling, que não tem webhook para nós: varredura a cada 15 min.
Schedule::command('hub:sync amazon')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Catálogo muda pouco: uma vez por dia, de madrugada.
Schedule::command('hub:sync-products woocommerce')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->onOneServer();

// Retenção de dados pessoais (Amazon): apaga 30 dias após o envio.
Schedule::command('hub:purge-pii')
    ->dailyAt('03:30')
    ->onOneServer();

// Revisão quinzenal de segurança por e-mail (dias 1 e 16).
Schedule::command('hub:security-digest')
    ->twiceMonthly(1, 16, '08:00')
    ->onOneServer();

// Rastreio: o Melhor Envio não empurra eventos; a cada hora perguntamos.
Schedule::command('hub:tracking')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
