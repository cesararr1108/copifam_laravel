<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\CarritoController;
use Illuminate\Http\Request;

class ActualizarPreciosCarrito extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'carrito:actualizar-precios 
                            {--codigo= : Filtrar por código específico de producto COPIFAM}
                            {--id= : Filtrar por ID de producto WooCommerce}
                            {--limit= : Limitar la cantidad de productos a procesar}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Actualiza precios y stock por ubicaciones de productos en WooCommerce (Carrito)';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de precios y stock con el Carrito (WooCommerce)...');

        $request = new Request([
            'codigo' => $this->option('codigo'),
            'id' => $this->option('id'),
            'limit' => $this->option('limit'),
        ]);

        $controller = new CarritoController();
        $response = $controller->actualizarPrecios($request, function ($type, $msg) {
            if ($type === 'ok') {
                $this->info(" [OK] " . $msg);
            } elseif ($type === 'error') {
                $this->error(" [ERROR] " . $msg);
            } else {
                $this->comment(" [INFO] " . $msg);
            }
        });

        $data = $response->getData(true);

        if (!empty($data['ok'])) {
            $this->info('Proceso finalizado correctamente.');
            $this->table(
                ['Total Procesados', 'Exitosos', 'Errores'],
                [[$data['total'] ?? 0, $data['exitosos'] ?? 0, $data['errores'] ?? 0]]
            );

            return 0;
        } else {
            $this->error('Error en el proceso: ' . ($data['message'] ?? 'Desconocido'));
            return 1;
        }
    }
}
