<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturaElectronicaController extends Controller
{
    public function leerCorreo(): JsonResponse
    {
        $inicio = microtime(true);

        try {
            set_time_limit(120);
            ini_set('memory_limit', '512M');

            // Configurar timeouts IMAP
            imap_timeout(IMAP_OPENTIMEOUT, 1000);
            imap_timeout(IMAP_READTIMEOUT, 1000);
            imap_timeout(IMAP_WRITETIMEOUT, 1000);
            imap_timeout(IMAP_CLOSETIMEOUT, 1000);

            $mailbox = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
            $usuario = env('IMAP_USER');
            $password = env('IMAP_PASS');

            if (!$usuario || !$password) {
                return response()->json(['ok' => false, 'error' => 'IMAP credentials not set'], 500);
            }


            $inbox = @imap_open($mailbox, $usuario, $password);
            if (!$inbox) {
                $error = imap_last_error() ?: 'Unknown IMAP error';
                Log::error('[FacturaElectronica] IMAP Error: ' . $error);
                
                // Limpiar errores de IMAP
                imap_errors();
                imap_alerts();
                
                return response()->json(['ok' => false, 'error' => $error], 500);
            }

            $emails = @imap_search($inbox, 'UNFLAGGED');

            if ($emails === false) {
                @imap_close($inbox);
                $error = imap_last_error() ?: 'Search failed';
                Log::error('[FacturaElectronica] IMAP Search Error: ' . $error);
                return response()->json(['ok' => false, 'error' => $error], 500);
            }

            if (!$emails) {
                @imap_close($inbox);
                return response()->json([
                    'ok' => true,
                    'procesados' => 0,
                    'errores' => [],
                    'mensaje' => 'No hay correos sin procesar',
                    'tiempo' => round(microtime(true) - $inicio, 2)
                ]);
            }

            // Limitar a 5 correos
            $emails = array_slice($emails, 0, 5);
            
            Log::info('[FacturaElectronica] Encontrados ' . count($emails) . ' correos sin procesar (limitado a 5)');

            $basePath = storage_path('app/facturas/');
            if (!is_dir($basePath)) mkdir($basePath, 0777, true);

            $procesados = 0;
            $errores = [];

            foreach ($emails as $emailNumber) {
                $emailGuardado = false;  // Bandera para saber si se guardó algo de este email
                
                try {
                    $structure = @imap_fetchstructure($inbox, $emailNumber);
                    if (!isset($structure->parts)) continue;

                    foreach ($structure->parts as $index => $part) {
                        if (empty($part->dparameters)) continue;

                        foreach ($part->dparameters as $object) {
                            if (strtolower($object->attribute) !== 'filename') continue;

                            $filename = $object->value;
                            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') continue;

                            $body = @imap_fetchbody($inbox, $emailNumber, $index + 1);
                            if ($part->encoding == 3) $body = base64_decode($body);
                            if (!$body) continue;

                            $zipPath = $basePath . $filename;
                            file_put_contents($zipPath, $body);

                            $zip = new \ZipArchive();
                            if ($zip->open($zipPath) !== true) {
                                @unlink($zipPath);
                                continue;
                            }

                            $extractPath = $basePath . pathinfo($filename, PATHINFO_FILENAME) . '/';
                            if (!is_dir($extractPath)) mkdir($extractPath, 0777, true);

                            $zip->extractTo($extractPath);
                            $zip->close();
                            @unlink($zipPath);

                            // Buscar XML recursivamente
                            $xmlPath = null;
                            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
                            foreach ($files as $file) {
                                if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                                    $xmlPath = $file->getPathname();
                                    break;
                                }
                            }

                            if (!$xmlPath) continue;

                            $xmlContent = file_get_contents($xmlPath);
                            $resultado = $this->parsearXML($xmlContent);

                            if (!$resultado || $resultado['error']) {
                                $errores[] = "XML parsing error for $filename";
                                continue;
                            }

                            if ($this->guardarEnBD($resultado)) {
                                $procesados++;
                                $emailGuardado = true;  // Marcar que este email tuvo al menos un registro guardado
                                Log::info('[FacturaElectronica] Guardada: ' . $resultado['numero_factura']);
                            } else {
                                $errores[] = "DB error: " . ($resultado['numero_factura'] ?? 'unknown');
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errores[] = $e->getMessage();
                    Log::error("[FacturaElectronica] Error en email: " . $e->getMessage());
                }
                
                // ✅ Solo marcar como destacado si se guardó algo de este email
                if ($emailGuardado) {
                    @imap_setflag_full($inbox, $emailNumber, "\\Flagged");
                    Log::info("[FacturaElectronica] Email $emailNumber marcado como procesado (guardado en BD)");
                }
            }

            @imap_close($inbox);

            return response()->json([
                'ok' => true,
                'procesados' => $procesados,
                'errores' => $errores,
                'tiempo' => round(microtime(true) - $inicio, 2)
            ]);

        } catch (\Exception $e) {
            Log::error('[FacturaElectronica] Error: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function parsearXML($xmlContent)
    {
        libxml_use_internal_errors(true);
        $xmlContent = trim($xmlContent);
        $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

        $attached = simplexml_load_string($xmlContent);
        if ($attached === false) return ['error' => true];

        $get = fn($node, $path) => ($r = $node->xpath($path)) && isset($r[0]) ? (string)$r[0] : null;

        $inner = $attached->xpath('//*[local-name()="Attachment"]//*[local-name()="Description"]');
        if (!isset($inner[0])) return ['error' => true];

        $invoice = simplexml_load_string((string)$inner[0]);
        if ($invoice === false) return ['error' => true];

        $uuid = $get($invoice, '//*[local-name()="UUID"]');
        $numeroFactura = $get($invoice, '//*[local-name()="ID"]');
        $fechaEmision = $get($invoice, '//*[local-name()="IssueDate"]');
        $nitProveedor = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="CompanyID"]');
        $nombreProveedor = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="RegistrationName"]');

        $totalAntesDescuentos = 0;
        $totalDescuentos = 0;
        $baseGravable = 0;
        $totalIVA = 0;

        foreach ($invoice->xpath('//*[local-name()="InvoiceLine"]') as $line) {
            $cantidad = (float)$get($line, '*[local-name()="InvoicedQuantity"]');
            $valorLinea = (float)$get($line, '*[local-name()="LineExtensionAmount"]');
            $valorUnitario = (float)$get($line, './/*[local-name()="PriceAmount"]');

            $descuento = 0;
            foreach ($line->xpath('.//*[local-name()="AllowanceCharge"]') as $allow) {
                if ($get($allow, './/*[local-name()="ChargeIndicator"]') === 'false') {
                    $descuento = (float)$get($allow, './/*[local-name()="Amount"]');
                    break;
                }
            }

            $ivaLinea = 0;
            foreach ($line->xpath('.//*[local-name()="TaxSubtotal"]') as $tax) {
                if ($get($tax, './/*[local-name()="TaxScheme"]/*[local-name()="ID"]') === '01') {
                    $ivaLinea = (float)$get($tax, './/*[local-name()="TaxAmount"]');
                    break;
                }
            }

            $totalAntesDescuentos += $valorUnitario * $cantidad;
            $totalDescuentos += $descuento;
            $baseGravable += $valorLinea;
            $totalIVA += $ivaLinea;
        }

        return [
            'error' => false,
            'UUID' => $uuid,
            'numero_factura' => $numeroFactura,
            'fecha_emision' => $fechaEmision,
            'nit_proveedor' => $nitProveedor,
            'nombre_proveedor' => $nombreProveedor,
            'pdf' => null,
            'totales' => [
                'total_antes_descuentos' => $totalAntesDescuentos,
                'total_descuentos' => $totalDescuentos,
                'base_gravable' => $baseGravable,
                'total_iva' => $totalIVA,
                'total_factura' => $baseGravable + $totalIVA
            ]
        ];
    }

    private function guardarEnBD($data)
    {
        if (!$data['UUID']) return false;

        if (DB::table('t_documentos_dian_xml')->where('uuid', $data['UUID'])->exists()) {
            return false;
        }

        DB::table('t_documentos_dian_xml')->insert([
            'uuid' => $data['UUID'],
            'numero_factura' => $data['numero_factura'],
            'fecha_emision' => $data['fecha_emision'],
            'nit_proveedor' => $data['nit_proveedor'],
            'nombre_proveedor' => $data['nombre_proveedor'],
            'total_antes_descuentos' => $data['totales']['total_antes_descuentos'],
            'total_descuentos' => $data['totales']['total_descuentos'],
            'base_gravable' => $data['totales']['base_gravable'],
            'total_iva' => $data['totales']['total_iva'],
            'total_factura' => $data['totales']['total_factura'],
            'json_items' => json_encode([]),
            'revisada' => 0,
            'pdf' => $data['pdf'] ?? null
        ]);

        return true;
    }
}
