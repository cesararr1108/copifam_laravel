<?php

namespace App\Console\Commands;

use App\Jobs\ProcesarReportesMesVencidoJob;
use Illuminate\Console\Command;

class EnviarReportesLaboratoriosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reportes:laboratorios-mes-vencido
                            {--mes= : Mes específico a procesar (1 a 12). Si no se pasa, toma mes anterior}
                            {--ano= : Año específico a procesar (ej. 2026). Si no se pasa, toma año correspondiente}
                            {--nit= : NIT de un laboratorio específico para prueba o envío individual}
                            {--sync : Ejecutar de forma síncrona en la consola sin pasar por la cola de background}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera y envía automáticamente por correo los reportes de Stock y Compras de mes vencido a los laboratorios';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mes = $this->option('mes') ? (int)$this->option('mes') : null;
        $ano = $this->option('ano') ? (int)$this->option('ano') : null;
        $nit = $this->option('nit') ? trim((string)$this->option('nit')) : null;
        $sync = (bool)$this->option('sync');

        $periodoTexto = ($mes && $ano) 
            ? "{$mes}/{$ano}" 
            : "Mes vencido automático (" . now()->subMonth()->format('m/Y') . ")";

        $this->info("================================================================");
        $this->info(" INICIANDO ENVÍO DE REPORTES MENSUALES DE LABORATORIOS");
        $this->info(" Período: {$periodoTexto}");
        if ($nit) {
            $this->info(" NIT objetivo: {$nit}");
        }
        $this->info(" Modo: " . ($sync ? "Síncrono (consola)" : "Cola en background (Jobs)"));
        $this->info("================================================================");

        // Despachar el Job maestro
        if ($sync) {
            ProcesarReportesMesVencidoJob::dispatchSync($mes, $ano, $nit, true);
        } else {
            ProcesarReportesMesVencidoJob::dispatch($mes, $ano, $nit, false);
        }

        $this->newLine();
        $this->info("Proceso despachado correctamente. Revisa los logs en storage/logs/laravel.log.");
        return Command::SUCCESS;
    }
}
