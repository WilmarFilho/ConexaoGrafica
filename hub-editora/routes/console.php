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

// Amazon pela SP-API (sem webhook para nós): varredura a cada 15 min.
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

// Backup cifrado do banco para fora do servidor (armazenamento S3 em outra região).
Schedule::command('hub:backup')
    ->dailyAt('02:40')
    ->withoutOverlapping()
    ->onOneServer();

// Toda segunda: decifra o backup mais recente e confere o conteúdo (teste de restauração
// automático; alerta os administradores se falhar ou se não houver backup).
Schedule::command('hub:backup-restore --conferir')
    ->weeklyOn(1, '06:15')
    ->onOneServer();

// Revisão trimestral de acessos (dia 1 de jan/abr/jul/out): lista aos administradores
// e desativa contas de expedição sem uso há 90 dias.
Schedule::command('hub:access-review')
    ->quarterlyOn(1, '08:30')
    ->onOneServer();

// Varredura mensal de vulnerabilidades nas dependências (além da que roda antes de cada deploy).
Schedule::command('hub:security-scan')
    ->monthlyOn(5, '07:00')
    ->onOneServer();

// Rastreio: o Melhor Envio não empurra eventos; a cada hora perguntamos.
Schedule::command('hub:tracking')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
