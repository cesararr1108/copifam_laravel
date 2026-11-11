<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ScraperController;

class ScrapePrecios extends Command
{
    /**
     * El nombre y firma del comando.
     * --all: Si se pasa, procesa todos los productos. Sin ella, solo procesa los que tienen precios NULL.
     */
    protected $signature = 'app:scrape-precios {--all : Procesa todos los productos en lugar de solo los faltantes}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Repasa y actualiza los precios de las farmacias externas (La Rebaja, Alemana, Pasteur)';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $lockFile = storage_path('framework/cache/scrape-precios.lock');

        // Validar si ya hay un proceso corriendo
        if (file_exists($lockFile)) {
            $pid = file_get_contents($lockFile);

            // Verificar si el proceso sigue vivo
            if ($pid && function_exists('posix_kill') && posix_kill((int) $pid, 0)) {
                $this->warn('Ya hay un proceso en ejecución (PID: ' . $pid . '). Cancelando...');
                return 0;
            } else {
                // Lock viejo (proceso muerto)
                $this->warn(' Se encontró un lock antiguo. Eliminando...');
                unlink($lockFile);
            }
        }

        // Crear lock con el PID actual
        file_put_contents($lockFile, getmypid());

        try {
            $all = $this->option('all');
            $onlyMissing = !$all;

            $this->info($onlyMissing
                ? 'Iniciando REPASO de productos con precios faltantes en la tabla shopping...'
                : 'Iniciando escaneo TOTAL de productos desde SQL Server...');

            // Ejecutar proceso principal
            $result = app(ScraperController::class)->runScrapingProcess($onlyMissing);

            if ($result['success']) {
                $this->info($result['message']);

                if (isset($result['total'])) {
                    $this->table(
                        ['Total Consultados', 'Encolados (Jobs)', 'Sin EAN'],
                        [
                            [$result['total'], $result['encolados'], $result['sin_ean']]
                        ]
                    );
                }
            } else {
                $this->error('Error durante el proceso: ' . ($result['error'] ?? 'Desconocido'));
            }

        } catch (\Throwable $e) {
            $this->error('Excepción: ' . $e->getMessage());
        } finally {
            // Liberar lock SIEMPRE
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }

        return 0;
    }
}