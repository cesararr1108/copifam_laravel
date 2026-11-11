<?php

namespace App\Jobs;

use App\Mail\ReporteLaboratorioMesVencidoMail;
use App\Models\ReporteLaboratorioDestinatario;
use App\Services\ReporteExcelLaboratorioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarReporteLaboratorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos permitidos
     */
    public int $tries = 3;

    /**
     * Tiempo de espera antes de reintentar (en segundos)
     */
    public int $backoff = 60;

    /**
     * Tiempo límite de ejecución del job (en segundos)
     */
    public int $timeout = 600;

    public ReporteLaboratorioDestinatario $destinatario;
    public int $mes;
    public int $ano;

    /**
     * Create a new job instance.
     */
    public function __construct(ReporteLaboratorioDestinatario $destinatario, int $mes, int $ano)
    {
        $this->destinatario = $destinatario;
        $this->mes = $mes;
        $this->ano = $ano;
    }

    /**
     * Execute the job.
     */
    public function handle(ReporteExcelLaboratorioService $excelService): void
    {
        $nit = trim($this->destinatario->nit);
        $laboratorio = trim($this->destinatario->laboratorio);
        $correosPara = $this->destinatario->getCorreosParaArray();
        $correosCc = $this->destinatario->getCorreosCcArray();

        if (empty($correosPara)) {
            Log::warning("No se pudo enviar el reporte para el laboratorio {$laboratorio} (NIT: {$nit}): no tiene correos principales válidos configurados.");
            return;
        }

        Log::info("Iniciando generación de reporte para {$laboratorio} (NIT: {$nit}) - Período: {$this->mes}/{$this->ano}");

        $archivoExcel = null;

        try {
            // 1. Generar archivo Excel con dos hojas (STOCK y COMPRA)
            $archivoExcel = $excelService->generarExcel($nit, $laboratorio, $this->mes, $this->ano);

            // 2. Preparar el mensaje y destinatarios
            $mail = new ReporteLaboratorioMesVencidoMail(
                $laboratorio,
                $nit,
                $this->mes,
                $this->ano,
                $archivoExcel
            );

            $mailable = Mail::to($correosPara);
            if (!empty($correosCc)) {
                $mailable->cc($correosCc);
            }

            // 3. Enviar correo
            $mailable->send($mail);

            Log::info("Reporte enviado exitosamente a {$laboratorio} (NIT: {$nit}). Destinatarios: " . implode(', ', $correosPara) . (empty($correosCc) ? "" : " | CC: " . implode(', ', $correosCc)));

        } catch (\Throwable $e) {
            Log::error("Error al generar o enviar reporte para {$laboratorio} (NIT: {$nit}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            // Limpieza del archivo temporal generado
            if ($archivoExcel && file_exists($archivoExcel)) {
                @unlink($archivoExcel);
            }
        }
    }
}
