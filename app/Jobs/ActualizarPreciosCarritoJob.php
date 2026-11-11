<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\Api\CarritoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActualizarPreciosCarritoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El tiempo máximo en segundos que puede durar el Job.
     */
    public $timeout = 3600;

    /**
     * Crea una nueva instancia del Job.
     */
    public function __construct()
    {
    }

    /**
     * Ejecuta el Job.
     */
    public function handle(): void
    {
        Log::info('[ActualizarPreciosCarritoJob] Iniciando sincronización de precios y stock...');

        $request = new Request();
        $controller = new CarritoController();

        $response = $controller->actualizarPrecios($request, function ($type, $msg) {
            if ($type === 'error') {
                Log::error("[ActualizarPreciosCarritoJob] " . $msg);
            }
        });

        $data = $response->getData(true);

        if (!empty($data['ok'])) {
            Log::info("[ActualizarPreciosCarritoJob] Finalizado. Total: {$data['total']}, Exitosos: {$data['exitosos']}, Errores: {$data['errores']}");
        } else {
            Log::error("[ActualizarPreciosCarritoJob] Error: " . ($data['message'] ?? 'Desconocido'));
        }
    }
}
