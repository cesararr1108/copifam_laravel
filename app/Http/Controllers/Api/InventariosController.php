<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventariosController extends Controller
{
    /**
     * GET/POST /api/inventarios/consulta-data
     *
     * Replicates the legacy "consulta-data" case from inventarios.php.
     * Accepts optional filters via POST body (JSON) or query string:
     *   - bodegas[]      array of warehouse codes (int)
     *   - lineas[]       array of line codes (string)
     *   - codigos[]      array of product codes (string)
     *   - flotantes      'S' to return only products with flotante > 0
     */
    public function consultaData(Request $request): JsonResponse
    {
        set_time_limit(300);  // 5 minutos
        ini_set('memory_limit', '512M');
        
        $bodegas = $request->input('bodegas', []);
        $lineas = $request->input('lineas', []);
        $codigos = $request->input('codigos', []);
        $flotantes = $request->input('flotantes', '');
       // $limit = min((int)$request->input('limit', 10000), 50000); // Limitar resultados
        $offset = (int)$request->input('offset', 0);

        $dbDms = 'copifam';

        try {
            // Si hay filtros de bodega, hace JOIN con flotantes; si no, solo consulta base
            $joinFloat = '';
            $selectFlotante = '';
            
            if (!empty($bodegas)) {
                $joinFloat = "
                    LEFT JOIN {$dbDms}.dbo.v_flotantes_trimestral f WITH (NOLOCK)
                        ON f.codigo = v.codigo AND f.bodega = v.cod_bodega
                ";
                $selectFlotante = ",
                    ISNULL(
                        CASE WHEN
                            (DATEDIFF(day, v.ultima_compra, CAST(GETDATE() AS date)) > 90
                             AND DATEDIFF(day, v.ultima_entrada, CAST(GETDATE() AS date)) > 90)
                            AND v.cod_bodega NOT IN (1000,5000,2001,2201,2202,2203,1132,1108,1151,1168,5,60,50,1)
                            AND ISNULL(v.institucional, 0) <> 1
                        THEN ISNULL(v.costo_promedio, 0) * ISNULL(f.stock, 0)
                        ELSE 0
                        END, 0
                    ) AS costo_flotante,
                    ISNULL(
                        CASE WHEN
                            (DATEDIFF(day, v.ultima_compra, CAST(GETDATE() AS date)) > 90
                             AND DATEDIFF(day, v.ultima_entrada, CAST(GETDATE() AS date)) > 90)
                            AND v.cod_bodega NOT IN (1000,5000,2001,2201,2202,2203,1132,1108,1151,1168,5,60,50,1)
                            AND ISNULL(v.institucional, 0) <> 1
                        THEN ISNULL(f.stock, 0)
                        ELSE 0
                        END, 0
                    ) AS flotante
                ";
            } else {
                // Sin bodegas, retorna 0 para flotante
                $selectFlotante = ", 0 AS costo_flotante, 0 AS flotante";
            }

            // Build main SELECT
            $sql = "
                SELECT 
                    v.codigo,
                    v.producto,
                    v.lab,
                    v.linea,
                    v.portafolio_ideal,
                    v.premiun,
                    v.abc_mer,
                    v.refrigerado,
                    v.controlado,
                    v.institucional,
                    v.conversion,
                    v.cod_bodega,
                    v.bodega,
                    v.stock_unidades,
                    v.stock_caja,
                    ROUND(v.costo_promedio * v.conversion, 0) AS costo_promedio,
                    v.costo_caja,
                    v.costo_total,
                    v.ultima_compra,
                    v.ultima_entrada,
                    v.prom_rot_trimestral,
                    v.dias_inventario,
                    v.stock_ideal,
                    v.sobre_Stcok,
                    v.costo_sobrestock
                    {$selectFlotante},
                    v.porcentaje_iva
                FROM {$dbDms}.dbo.v_info_adicional_compras v WITH (NOLOCK)
                {$joinFloat}
                WHERE v.codigo IS NOT NULL
            ";

            // Build WHERE clause
            $params = [];

            if (!empty($bodegas)) {
                $inList = implode(',', array_map('intval', $bodegas));
                $sql .= " AND v.cod_bodega IN ({$inList})";
            }

            if (!empty($lineas)) {
                $placeholders = implode(',', array_fill(0, count($lineas), '?'));
                $sql .= " AND v.linea IN ({$placeholders})";
                $params = array_merge($params, $lineas);
            }

            if (!empty($codigos)) {
                $placeholders = implode(',', array_fill(0, count($codigos), '?'));
                $sql .= " AND v.codigo IN ({$placeholders})";
                $params = array_merge($params, $codigos);
            }

            // Filter by flotante if requested
            if ($flotantes === 'S' && !empty($bodegas)) {
                $sql .= " AND ISNULL(f.stock, 0) > 0";
            }

            // Execute query with extended timeout
            DB::connection('sqlsrv')->statement("SET LOCK_TIMEOUT 10000;");
                
            $rows = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'count' => count($rows),
                //'limit' => $limit,
                'offset' => $offset,
                'data' => $rows
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ], 500);
        }
    }
}
