<?php

namespace App\Jobs;

use App\Models\ReporteLaboratorioDestinatario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcesarReportesMesVencidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $mes;
    public ?int $ano;
    public ?string $nit;
    public bool $sincrono;

    /**
     * Create a new job instance.
     *
     * @param int|null $mes Mes específico (opcional, por defecto mes anterior)
     * @param int|null $ano Año específico (opcional, por defecto año anterior o actual)
     * @param string|null $nit NIT específico si se desea procesar solo un laboratorio
     * @param bool $sincrono Si es true, ejecuta los jobs individuales en el mismo proceso (dispatchSync)
     */
    public function __construct(?int $mes = null, ?int $ano = null, ?string $nit = null, bool $sincrono = false)
    {
        $this->mes = $mes;
        $this->ano = $ano;
        $this->nit = $nit ? trim($nit) : null;
        $this->sincrono = $sincrono;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Si no se especifica mes y año, se calcula automáticamente el mes vencido
        $fechaVencida = now()->subMonth();
        $mesProcesar = $this->mes ?? (int)$fechaVencida->format('n');
        $anoProcesar = $this->ano ?? (int)$fechaVencida->format('Y');

        Log::info("=== INICIO PROCESO DE REPORTES DE LABORATORIOS (MES VENCIDO) ===");
        Log::info("Período a procesar: Mes {$mesProcesar} / Año {$anoProcesar}");

        $query = ReporteLaboratorioDestinatario::activos();

        if ($this->nit) {
            $query->where('nit', $this->nit);
            Log::info("Filtrando únicamente por el NIT: {$this->nit}");
        }

        $laboratorios = $query->get();

        if ($laboratorios->isEmpty()) {
            Log::warning("No se encontraron laboratorios activos en la tabla reportes_laboratorios_destinatarios.");
            return;
        }

        Log::info("Se encontraron {$laboratorios->count()} laboratorios activos para procesar.");

        $total = 0;
        foreach ($laboratorios as $destinatario) {
            $total++;
            Log::info("[{$total}/{$laboratorios->count()}] Despachando reporte para: {$destinatario->laboratorio} (NIT: {$destinatario->nit})");

            if ($this->sincrono) {
                EnviarReporteLaboratorioJob::dispatchSync($destinatario, $mesProcesar, $anoProcesar);
            } else {
                EnviarReporteLaboratorioJob::dispatch($destinatario, $mesProcesar, $anoProcesar);
            }
        }

        Log::info("=== FINALIZADO DESPACHO DE REPORTES DE LABORATORIOS ({$total} procesados) ===");
    }
}
