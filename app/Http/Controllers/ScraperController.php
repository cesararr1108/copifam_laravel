<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ScrapeDrogueriasJob;

class ScraperController extends Controller
{
    /**
     * Inicia el proceso de scraping de precios en las tres droguerías externas.
     * 
     * Flujo por producto:
     *  1. Obtener precio Copifam desde v_lista_precios_iva (lista = 1)
     *  2. Hacer UPSERT en tabla shopping con los datos base
     *  3. Despachar ScrapeDrogueriasJob a la cola para obtener precios externos
     */
    public function dispatchScraping(Request $request)
    {
        $onlyMissing = $request->has('only_missing');
        $result = $this->runScrapingProcess($onlyMissing);
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Lógica central que puede invocarse desde el Controlador o desde un Comando Artisan.
     */
    public function runScrapingProcess(bool $onlyMissing = false): array
    {
        try {
            // ── 1. OBTENER PRODUCTOS CON EAN Y PRECIO COPIFAM ────────────────
            // Basado en la consulta solicitada por el usuario
            $sql = "
                SELECT
                    v.codigo,
                    ean = ISNULL(STUFF((
                        SELECT ' ' + alterno
                        FROM copifam_dev.dbo.referencias_alt
                        WHERE codigo = v.codigo
                        FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, ''), ''),
                    r.descripcion,
                    precio_copifam = ROUND(v.precio_venta * ((r.porcentaje_iva/100)+1) * (
                        CASE 
                            WHEN ISNULL(r.und_vta, 1) = 1 THEN r.can_1 
                            WHEN ISNULL(r.und_vta, 1) = 2 THEN r.can_2
                            WHEN ISNULL(r.und_vta, 1) = 3 THEN r.can_3
                            ELSE 1
                        END  
                    ), 0)
                FROM v_lista_precios_iva v 
                INNER JOIN copifam_dev.dbo.referencias r ON r.codigo = v.codigo 
                WHERE ISNULL(r.ref_anulada, 'N') = 'N' 
                AND ISNULL(r.institucional, 0) = 0
                AND r.tipo_5 = 1 
                AND v.lista = 1
            ";

            $productos = DB::connection('sqlsrv')->select($sql);
            
            // Si es re-repaso, buscamos:
            // 1. Productos que ya están en shopping pero les falta algún precio.
            // 2. Productos que NI SIQUIERA están en la tabla shopping todavía.
            if ($onlyMissing) {
                $codigosExistentesCompletos = DB::table('shopping')
                    ->whereNotNull('precio_rebaja')
                    ->whereNotNull('precio_pasteur')
                    ->whereNotNull('precio_alemana')
                    ->whereNotNull('precio_colsubsidio')
                    ->pluck('codigo')
                    ->toArray();
                
                $productos = array_filter($productos, function($p) use ($codigosExistentesCompletos) {
                    // Solo lo procesamos si NO está en la lista de "completos"
                    return !in_array((string)$p->codigo, $codigosExistentesCompletos);
                });
            }

            $count = count($productos);

            if ($count === 0) {
                return [
                    'success' => true,
                    'message' => 'No se encontraron productos para procesar' . ($onlyMissing ? ' (re-repaso)' : '') . '.'
                ];
            }

            $sinEan    = 0;
            $encolados = 0;

            foreach ($productos as $p) {
                $codigo          = (string) $p->codigo;
                $ean             = trim($p->ean ?? '');
                $nombre_producto = trim($p->descripcion ?? '');
                $precioCopifam   = (float) ($p->precio_copifam ?? 0);

                // Guardar todos los EANs encontrados para mejorar la coincidencia en el Job
                $eanCompleto = $ean;

                // ── 2. UPSERT EN TABLA SHOPPING ─────────────────────────────
                DB::table('shopping')->upsert(
                    [
                        'codigo'          => $codigo,
                        'ean'             => $eanCompleto,
                        'nombre_producto' => $nombre_producto,
                        'precio_copifam'  => $precioCopifam,
                    ],
                    uniqueBy: ['codigo'],
                    update:   ['ean', 'nombre_producto', 'precio_copifam']
                );

                // ── 3. DESPACHAR JOB DE SCRAPING EXTERNO ─────────────────────
                if (empty($ean)) {
                    Log::debug("[Scraper] Producto sin EAN, omitiendo: [{$nombre_producto}]");
                    $sinEan++;
                    continue;
                }

                ScrapeDrogueriasJob::dispatch($codigo, $ean, $nombre_producto)
                    ->onQueue('scraping');

                $encolados++;
            }

            Log::info("[Scraper] " . ($onlyMissing ? "[RE-REPASO] " : "") . "{$count} productos procesados | {$encolados} encolados.");

            return [
                'success'  => true,
                'total'    => $count,
                'encolados'=> $encolados,
                'sin_ean'  => $sinEan,
                'message'  => "{$encolados} productos encolados exitosamente.",
            ];

        } catch (\Throwable $e) {
            Log::error("[Scraper] Error: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
