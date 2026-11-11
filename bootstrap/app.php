<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middleware para uso en rutas
        $middleware->alias([
            'session.check' => \App\Http\Middleware\CheckSession::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // LECTURA DE CORREOS (Cada 2 minutos)
        $schedule->command('app:procesar-facturas')
            ->everyTwoMinutes()
            ->withoutOverlapping();

        // SINCRONIZACION CARRITO WOOCOMMERCE (6:00 AM, 12:00 PM, 6:00 PM)
        $schedule->command('carrito:actualizar-precios')
            ->cron('0 6,12,18 * * *')
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
