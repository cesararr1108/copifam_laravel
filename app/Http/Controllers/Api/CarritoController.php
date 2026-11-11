<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarritoController extends Controller
{
    /**
     * Actualiza los precios y el stock por ubicación en WooCommerce (Carrito).
     * 
     * Endpoint API: POST/GET /api/carrito/actualizar-precios
     * 
     * Parámetros opcionales (query o body):
     * - codigo: Filtrar por código de producto en ERP COPIFAM
     * - id: Filtrar por ID de producto en WooCommerce (tipo_7)
     * - limit: Limitar cantidad de registros a procesar
     */
    public function actualizarPrecios(Request $request, ?callable $onProgress = null): JsonResponse
    {
        $ck = config('services.woocommerce.ck', 'ck_cf4c4d5cdefee48f2dd66d8e791c88d9ad7187a5');
        $cs = config('services.woocommerce.cs', 'cs_1b4bb1a1ecd24aee785814c20f1fce6792216cbb');
        $baseUrl = config('services.woocommerce.url', 'https://copifam.com');

        $filterCodigo = $request->input('codigo');
        $filterId = $request->input('id');
        $limit = $request->input('limit');

        try {
            // ── Consulta SQL principal: Precios y Descuentos por Referencia ───
            $sql = "
                WITH datos AS (
                    SELECT 
                        r7.tipo_7 AS id,
                        r.codigo,
                        CASE 
                            WHEN r.und_vta = 1 THEN r.can_1
                            WHEN r.und_vta = 2 THEN r.can_2
                            WHEN r.und_vta = 3 THEN r.can_3
                        END AS und_com,
                        CASE 
                            WHEN r.und_vta = 1 THEN r.und_1
                            WHEN r.und_vta = 2 THEN r.und_2
                            WHEN r.und_vta = 3 THEN r.und_3
                        END AS und_nombre,
                        ROUND(
                            ISNULL(
                                (
                                    SELECT TOP 1 l.precio_venta 
                                    FROM copiweb.dbo.v_lista_precios_iva l WITH (NOLOCK)
                                    WHERE l.lista = 1 
                                      AND l.codigo = r.codigo
                                ), 0
                            ) * ((ISNULL(r.porcentaje_iva, 0) / 100.0) + 1), 
                            0
                        ) AS precio,
                        ISNULL(
                            (
                                SELECT TOP 1 f.descuento
                                FROM t_mer_teleferias_dto f WITH (READPAST)
                                WHERE f.codigo = r.codigo
                                  AND f.lista = 1
                                  AND f.estado = 'A'
                                  AND f.nit = '*'
                                  AND f.bodega = '*'
                                  AND CAST(GETDATE() AS DATE) BETWEEN f.fecha_inicial AND f.fecha_final
                                ORDER BY f.fecha_inicial DESC
                            ), 0
                        ) AS descuento
                    FROM COPIFAM.DBO.REFERENCIAS r WITH (NOLOCK)
                    INNER JOIN COPIFAM.DBO.referencias_tip7 r7 WITH (NOLOCK)
                            ON r7.tipo_7 = r.tipo_7
                    WHERE   ISNULL(r.ref_anulada, 'N') = 'N' 
                      AND ISNULL(r.institucional, 0) = 0
                      AND ISNULL(r7.tipo_7, '') <> ''
                )
                SELECT * FROM datos WHERE 1=1
            ";

            $params = [];

            if (!empty($filterCodigo)) {
                $sql .= " AND codigo = ?";
                $params[] = $filterCodigo;
            }

            if (!empty($filterId)) {
                $sql .= " AND id = ?";
                $params[] = $filterId;
            }

            $connection = config('database.default') === 'sqlsrv' ? 'sqlsrv' : null;

            // Los SET deben ejecutarse en el mismo objeto PDO que el SELECT,
            // de lo contrario el pool puede asignar conexiones distintas y
            // ARITHABORT OFF no estaría activo cuando la vista haga su división.
            $pdo = DB::connection($connection)->getPdo();
            $pdo->exec('SET ARITHABORT OFF; SET ANSI_WARNINGS OFF');

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $codigos = $stmt->fetchAll(\PDO::FETCH_OBJ);

            if (!empty($limit) && is_numeric($limit) && (int) $limit > 0) {
                $codigos = array_slice($codigos, 0, (int) $limit);
            }

            $totalCodigos = count($codigos);

            if ($totalCodigos === 0) {
                return response()->json([
                    'ok' => true,
                    'message' => 'No se encontraron registros para actualizar.',
                    'total' => 0,
                    'exitosos' => 0,
                    'errores' => 0,
                    'response' => null
                ]);
            }

            if ($onProgress) {
                $onProgress('info', "Se encontraron {$totalCodigos} productos para actualizar.");
            }

            $exitosos = 0;
            $errores = 0;
            $detalles = [];
            $lastResponse = null;

            foreach ($codigos as $index => $row) {
                $currentNum = $index + 1;
                $productId  = $row->id;
                $codigo     = $row->codigo;
                $precio     = (float) $row->precio;
                $undCom     = (float) ($row->und_com ?? 1);
                $undNombre  = ucfirst(strtolower(trim($row->und_nombre ?? 'unidades')));
                $descuento  = (float) $row->descuento;

                $url = rtrim($baseUrl, '/') . "/wp-json/wc/v3/products/" . $productId;

                // ── Consulta SQL de Stock por Ciudad/Ubicación ───────────────────
                $sqlStock = "
                    SELECT 
                        U.ubicacion,
                        ISNULL(SUM(V.STOCK), 0) AS stock 
                    FROM COPIFAM.DBO.BODEGAS B WITH (NOLOCK)
                    INNER JOIN COPIFAM.DBO.BODEGAS_UBICACION U WITH (NOLOCK) ON U.BODEGA = B.BODEGA
                    LEFT JOIN COPIFAM.DBO.V_REF_TOTAL V WITH (NOLOCK) 
                        ON V.BODEGA = B.BODEGA 
                       AND V.CODIGO = ?
                       AND V.MES = MONTH(GETDATE()) 
                       AND V.ANO = YEAR(GETDATE())        
                    WHERE ISNULL(B.INACTIVA, 'N') = 'N' 
                      AND U.UBICACION <> ''
                      AND B.BODEGA > 1000 
                      AND B.BODEGA <> 5000
                      AND U.DESCRIPCION = 'IBAGUE'
                    GROUP BY U.UBICACION
                ";

                $stockCiudad = DB::connection($connection)->select($sqlStock, [$codigo]);

                $metaData = [];
                $totalStock = 0;
                foreach ($stockCiudad as $ubi) {
                    $cantStock = (float) $ubi->stock;
                    $totalStock += $cantStock;
                    // Si se vende por caja (und_com > 1), guardar cajas completas por ubicación
                    $stockUbi = ($undCom > 1) ? (int) floor($cantStock / $undCom) : (int) $cantStock;
                    $metaData[] = [
                        "key" => "wcmlim_stock_at_" . trim($ubi->ubicacion),
                        "value" => $stockUbi
                    ];
                }

                // Stock total en unidad de venta: cajas completas si und_com > 1, unidades si und_com = 1
                $totalStockVenta = ($undCom > 1) ? (int) floor($totalStock / $undCom) : (int) $totalStock;

                // Unidad mínima de venta + nombre del ERP → metas en WooCommerce
                // Leídos con get_post_meta($id, 'copifam_und_com', true) y 'copifam_und_nombre'
                $metaData[] = [
                    "key"   => "copifam_und_com",
                    "value" => (int) $undCom
                ];
                $metaData[] = [
                    "key"   => "copifam_und_nombre",
                    "value" => $undNombre
                ];

                // ── Cálculo de precios ────────────────────────────────────────────
                $desc         = $precio * ($descuento / 100);
                $regularPrice = (string) round($precio * $undCom, 0);
                $stockStatus  = $totalStockVenta > 0 ? "instock" : "outofstock";

                if ($descuento > 0) {
                    // Hay descuento: precio rebajado visible, sin fechas para que no expire
                    $salePrice   = (string) round(($precio - $desc) * $undCom, 0);
                    $dateFrom    = '';
                    $dateTo      = '';
                } else {
                    // Sin descuento: limpiar sale_price para que WooCommerce no muestre tachado
                    $salePrice   = '';
                    $dateFrom    = '';
                    $dateTo      = '';
                }

                // Guardar el porcentaje de descuento como meta para mostrarlo en WP
                $metaData[] = [
                    "key"   => "copifam_descuento",
                    "value" => (int) $descuento
                ];

                $jsonData = [
                    "regular_price"      => $regularPrice,
                    "sale_price"         => $salePrice,
                    "date_on_sale_from"  => $dateFrom,
                    "date_on_sale_to"    => $dateTo,
                    "manage_stock"       => true,
                    "stock_quantity"     => $totalStockVenta,
                    "stock_status"       => $stockStatus,
                    "meta_data"          => $metaData
                ];


                // ── Enviar actualización PUT a WooCommerce API (con reintentos) ──────
                $maxIntentos = 3;
                $res         = null;
                $intento     = 0;

                while ($intento < $maxIntentos) {
                    $intento++;
                    try {
                        $res = Http::withBasicAuth($ck, $cs)
                            ->withHeaders(['Content-Type' => 'application/json'])
                            ->withOptions(['verify' => false])
                            ->timeout(30)
                            ->put($url, $jsonData);

                        if ($res->successful()) {
                            break; // Salir del while si fue exitoso
                        }

                        // Respuesta HTTP no exitosa → loguear y reintentar
                        Log::warning("CarritoPrecios intento {$intento}/{$maxIntentos} fallido | ID: {$productId} | HTTP {$res->status()}");

                    } catch (\Throwable $httpEx) {
                        // Error de conexión/timeout → loguear y reintentar
                        Log::warning("CarritoPrecios intento {$intento}/{$maxIntentos} excepción | ID: {$productId} | " . $httpEx->getMessage());
                        $res = null;
                    }

                    if ($intento < $maxIntentos) {
                        sleep(2); // Esperar 2 segundos antes del siguiente intento
                    }
                }

                $fueExitoso = $res && $res->successful();

                if ($fueExitoso) {
                    $exitosos++;
                    $lastResponse = $res->json();
                    $msg = "[{$currentNum}/{$totalCodigos}] ID: {$productId} | Código: {$codigo} | Precio: {$regularPrice} | Oferta: {$salePrice} | Stock: {$totalStock} ({$stockStatus})";

                    Log::info("CarritoPrecios OK: " . $msg);
                    if ($onProgress) {
                        $onProgress('ok', $msg);
                    }

                    $detalles[] = [
                        'id' => $productId,
                        'codigo' => $codigo,
                        'status' => 'OK',
                        'regular_price' => $regularPrice,
                        'sale_price' => $salePrice,
                        'stock_quantity' => $totalStock,
                        'stock_status' => $stockStatus,
                    ];
                } else {
                    $errores++;
                    $httpCode = $res ? $res->status() : 0;
                    $httpBody = $res ? substr($res->body(), 0, 150) : 'Sin respuesta (timeout o error de conexión)';
                    $errMsg   = "[{$currentNum}/{$totalCodigos}] ID: {$productId} | Código: {$codigo} | Falló tras {$intento} intento(s) | HTTP {$httpCode}: {$httpBody}";

                    Log::error("CarritoPrecios ERROR: " . $errMsg);
                    if ($onProgress) {
                        $onProgress('error', $errMsg);
                    }

                    $detalles[] = [
                        'id'           => $productId,
                        'codigo'       => $codigo,
                        'status'       => 'ERROR',
                        'intentos'     => $intento,
                        'http_code'    => $httpCode,
                        'error'        => $httpBody
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Registro actualizado',
                'total' => $totalCodigos,
                'exitosos' => $exitosos,
                'errores' => $errores,
                'response' => $lastResponse,
                'detalles' => $detalles
            ]);

        } catch (\Throwable $e) {
            Log::error('Error al actualizar precios del Carrito: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error al procesar la actualización: ' . $e->getMessage(),
                'response' => null
            ], 500);
        }
    }
}
