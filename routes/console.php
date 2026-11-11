<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcesarReportesMesVencidoJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Reporte mensual automático de laboratorios (Stock y Compras de mes vencido)
 * Se ejecuta automáticamente el primer día de cada mes a las 06:00 AM
 */
Schedule::job(new ProcesarReportesMesVencidoJob())
    ->monthlyOn(1, '06:00')
    ->name('reportes-laboratorios-mes-vencido')
    ->withoutOverlapping();
