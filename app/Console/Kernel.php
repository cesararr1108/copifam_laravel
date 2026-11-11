<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 1. Lectura de correos cada 2 minutos
        $schedule->command('app:procesar-facturas')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('[Scheduler] Lectura de correos completada');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('[Scheduler] Error en lectura de correos');
            });

        // 2. Sincronización de precios y stock con WooCommerce (6:00 AM, 12:00 PM, 6:00 PM)
        $schedule->command('carrito:actualizar-precios')
            ->cron('0 6,12,18 * * *')
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('[Scheduler] Sincronización de precios y stock con Carrito completada');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('[Scheduler] Error en la sincronización de precios y stock con Carrito');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
