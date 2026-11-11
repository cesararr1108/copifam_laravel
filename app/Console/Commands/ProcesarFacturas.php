<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesarFacturas extends Command
{
    protected $signature = 'app:procesar-facturas';
    protected $description = 'Lee correos de Gmail (IMAP) y procesa facturas electrónicas DIAN';

    public function handle()
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
                $this->error('[ERROR] IMAP_USER o IMAP_PASS no configurados en .env');
                //Log::error('[ProcesarFacturas] IMAP credentials not set');
                return 1;
            }

            $this->info('[INFO] Conectando a IMAP...');
            $inbox = imap_open($mailbox, $usuario, $password);
            
            if (!$inbox) {
                $error = imap_last_error() ?: 'Unknown IMAP error';
                $this->error('[ERROR] Error IMAP: ' . $error);
               // Log::error('[ProcesarFacturas] IMAP Error: ' . $error);
                imap_errors();
                imap_alerts();
                return 1;
            }

            $this->info('[OK] Conectado a IMAP');

            // Buscar correos sin procesar,  de los últimos 1 días
        
            $emails = imap_search($inbox, 'UNFLAGGED');
      
            if ($emails === false) {
                imap_close($inbox);
                $error = imap_last_error() ?: 'Search failed';
                $this->error('[ERROR] Error en búsqueda IMAP: ' . $error);
               // Log::error('[ProcesarFacturas] IMAP Search Error: ' . $error);
                return 1;
            }

            if (!$emails) {
                imap_close($inbox);
                $this->info('[OK] No hay correos sin procesar');
                return 0;
            }

            // Limitar a 10 correos por ejecución (últimos 10, más recientes)
            $emails = array_slice($emails, -10);
            $this->info("[INFO] Procesando " . count($emails) . " correos (últimos 10)...");
           // Log::info('[ProcesarFacturas] Encontrados ' . count($emails) . ' correos sin procesar');

            $basePath = storage_path('app/facturas/');
            if (!is_dir($basePath)) mkdir($basePath, 0777, true);

            $procesados = 0;
            $errores = [];

            foreach ($emails as $emailNumber) {
                $emailGuardado = false;
                
                try {
                    $structure = imap_fetchstructure($inbox, $emailNumber);
                    if (!isset($structure->parts)) continue;

                    foreach ($structure->parts as $index => $part) {
                        if (empty($part->dparameters)) continue;

                        foreach ($part->dparameters as $object) {
                            if (strtolower($object->attribute) !== 'filename') continue;

                            $filename = $object->value;
                            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') continue;

                            $body = imap_fetchbody($inbox, $emailNumber, $index + 1);
                            if ($part->encoding == 3) $body = base64_decode($body);
                            if (!$body) {
                                $this->line("    [WARN] ZIP no se pudo descargar (body vacío)");
                                continue;
                            }

                            $zipPath = $basePath . $filename;
                            file_put_contents($zipPath, $body);

                            $zip = new \ZipArchive();
                            if ($zip->open($zipPath) !== true) {
                                $this->line("    [WARN] ZIP corrupto o no válido");
                                @unlink($zipPath);
                                continue;
                            }

                            // Construir ruta de extracción sin barra extra
                            $extractPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
                            if (!is_dir($extractPath)) mkdir($extractPath, 0777, true);
                            
                            // Asegurar permisos de escritura
                            chmod($extractPath, 0777);

                            if ($zip->extractTo($extractPath) === false) {
                                $this->line("    [WARN] Error al extraer ZIP: " . $zip->getStatusString());
                               /// Log::error("[ProcesarFacturas] Error extractTo: {$filename} - " . $zip->getStatusString());
                                $zip->close();
                                @unlink($zipPath);
                                continue;
                            }
                            
                            $zip->close();
                            @unlink($zipPath);

                            // Buscar XML y PDF recursivamente
                            $xmlPath = null;
                            $pdfPath = null;
                            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
                            foreach ($iterator as $file) {
                                if ($file->isFile()) {
                                    $ext = strtolower($file->getExtension());
                                    if ($ext === 'xml' && !$xmlPath) {
                                        $xmlPath = $file->getPathname();
                                    }
                                    if ($ext === 'pdf' && !$pdfPath) {
                                        $pdfPath = $file->getPathname();
                                    }
                                }
                                if ($xmlPath && $pdfPath) break;
                            }

                            if (!$xmlPath) {
                                $this->line("    [WARN] Archivo XML no encontrado en ZIP");
                                continue;
                            }

                            // Validar que el XML se pueda leer
                            $xmlContent = @file_get_contents($xmlPath);
                            if (!$xmlContent || empty($xmlContent)) {
                                $this->line("    [WARN] XML vacío o no legible");
                                continue;
                            }

                            $resultado = $this->parsearXML($xmlContent);

                            if (!$resultado || $resultado['error']) {
                                $mensaje = $resultado['message'] ?? "Error al parsear XML: formato inválido";
                                $errores[] = "XML parsing error for $filename";
                                $this->line("    [ERROR] Error al parsear XML: " . $mensaje);
                                $this->guardarLogError($xmlContent, $mensaje);
                                continue;
                            }

                            // Guardar la ruta del PDF (relativa desde basePath)
                            if ($pdfPath) {
                                $rutaPDFRelativa = str_replace($basePath, '', $pdfPath);
                                $rutaPDFRelativa = ltrim($rutaPDFRelativa, DIRECTORY_SEPARATOR);
                                $resultado['pdf'] = $rutaPDFRelativa;
                            }

                            // Verificar si es DUPLICADO (UUID ya existe)
                            if (DB::table('t_documentos_dian_xml')->where('uuid', $resultado['UUID'])->exists()) {
                                $this->line("    [INFO] Duplicado (UUID ya existe): " . $resultado['numero_factura']);
                                $emailGuardado = true;  // Marcar email como procesado
                                continue;               // Saltar, no agregar a errores
                            }

                            if ($this->guardarEnBD($resultado, $xmlContent)) {
                                $procesados++;
                                $emailGuardado = true;
                                $this->line("    [OK] Guardada: " . $resultado['numero_factura']);
                                Log::info('[ProcesarFacturas] Guardada: ' . $resultado['numero_factura']);
                            } else {
                                $numeroFactura = $resultado['numero_factura'] ?? 'desconocida';
                                $mensaje = "Error al guardar en BD: $numeroFactura";
                                $errores[] = "DB error: " . $numeroFactura;
                                $this->line("    [ERROR] Error al guardar en BD");
                                $this->guardarLogError($xmlContent, $mensaje);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errores[] = $e->getMessage();
                    $this->line("    [ERROR] Error: " . $e->getMessage());
                     Log::error("[ProcesarFacturas] Error en email: " . $e->getMessage());
                }
                
                // Solo marcar como destacado si se guardó algo
                if ($emailGuardado) {
                    @imap_setflag_full($inbox, $emailNumber, "\\Flagged");  // Marcar como procesado
                    @imap_clearflag_full($inbox, $emailNumber, "\\Seen");   // Desmarcar como leído para otra API
                    Log::info("[ProcesarFacturas] Email $emailNumber marcado como procesado");
                }
            }
            @imap_close($inbox);

            $tiempo = round(microtime(true) - $inicio, 2);
            $this->info("[OK] Proceso completado en {$tiempo}s");
            $this->info("[STATS] Procesados: $procesados | Errores: " . count($errores));
            
            if ($errores) {
                $this->line("[WARN] Errores encontrados:");
                foreach ($errores as $error) {
                    $this->line("   - $error");
                }
            }

          //  Log::info('[ProcesarFacturas] Completado: ' . $procesados . ' guardados, ' . count($errores) . ' errores en ' . $tiempo . 's');

            return 0;

        } catch (\Exception $e) {
            $this->error('[ERROR] Error general: ' . $e->getMessage());
            Log::error('[ProcesarFacturas] Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function parsearXML($xmlContent)
    {
        try {
            libxml_use_internal_errors(true);
            $xmlContent = trim($xmlContent);
            $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

            // Usar DOMDocument es más rápido que SimpleXML para lectura
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            
            if (!@$dom->loadXML($xmlContent)) {
                $errors = libxml_get_errors();
                $errorMsg = $errors ? $errors[0]->message : 'XML parsing error';
                return ['error' => true, 'message' => $errorMsg];
            }

            $xpath = new \DOMXPath($dom);
            $get = fn($node, $path) => ($r = $xpath->query($path, $node)) && $r->length > 0 ? $r->item(0)->textContent : null;

            // Buscar nodo Attachment/Description más eficientemente
            $attachments = $xpath->query('//*[local-name()="Attachment"]');
            if ($attachments->length === 0) {
                return ['error' => true, 'message' => 'Descripción del adjunto no encontrada'];
            }

            $description = $xpath->query('.//*[local-name()="Description"]', $attachments->item(0));
            if ($description->length === 0) {
                return ['error' => true, 'message' => 'Descripción del adjunto no encontrada'];
            }

            // Parsear el contenido de Description como XML
            $invoiceXml = $description->item(0)->textContent;
            $invoiceDom = new \DOMDocument();
            if (!@$invoiceDom->loadXML($invoiceXml)) {
                return ['error' => true, 'message' => 'Error al parsear Invoice'];
            }

            $invoiceXpath = new \DOMXPath($invoiceDom);
            $getInv = fn($path) => ($r = $invoiceXpath->query($path)) && $r->length > 0 ? $r->item(0)->textContent : null;

            // Extraer datos de cabecera
            $uuid = $getInv('//*[local-name()="UUID"]');
            $numeroFactura = $getInv('//*[local-name()="ID"]');
            $fechaEmision = $getInv('//*[local-name()="IssueDate"]');
            $nitProveedor = $getInv('//*[local-name()="AccountingSupplierParty"]//*[local-name()="CompanyID"]');
            $nombreProveedor = $getInv('//*[local-name()="AccountingSupplierParty"]//*[local-name()="RegistrationName"]');
            
            // Extraer ID del documento padre (AttachedDocument)
            $parentDocumentID = $getInv('//*[local-name()="AttachedDocument"]//*[local-name()="ID"]');

            $totalAntesDescuentos = 0;
            $totalDescuentos = 0;
            $baseGravable = 0;
            $totalIVA = 0;
            $items = [];

            // Procesar líneas de factura - buscar todas a la vez
            $lines = $invoiceXpath->query('//*[local-name()="InvoiceLine"]');
            
            foreach ($lines as $line) {
                // Extraer valores directamente del nodo actual
                $cantNode = $invoiceXpath->query('*[local-name()="InvoicedQuantity"]', $line);
                $cantidad = (float)($cantNode && $cantNode->length > 0 ? $cantNode->item(0)->textContent : 0);
                
                $valNode = $invoiceXpath->query('*[local-name()="LineExtensionAmount"]', $line);
                $valorLinea = (float)($valNode && $valNode->length > 0 ? $valNode->item(0)->textContent : 0);
                
                $prcNode = $invoiceXpath->query('.//*[local-name()="PriceAmount"]', $line);
                $valorUnitario = (float)($prcNode && $prcNode->length > 0 ? $prcNode->item(0)->textContent : 0);
                
                $idNode = $invoiceXpath->query('*[local-name()="ID"]', $line);
                $lineID = $idNode && $idNode->length > 0 ? $idNode->item(0)->textContent : '';
                
                $descNode = $invoiceXpath->query('.//*[local-name()="Description"]', $line);
                $descripcion = $descNode && $descNode->length > 0 ? $descNode->item(0)->textContent : '';
                
                // Buscar SellersItemIdentification (el ID del producto del vendedor)
                // Si no existe, buscar StandardItemIdentification (algunos proveedores usan esto)
                $codNode = $invoiceXpath->query('.//*[local-name()="SellersItemIdentification"]/*[local-name()="ID"]', $line);
                $codigo = $codNode && $codNode->length > 0 ? $codNode->item(0)->textContent : '';
                
                // Si no encontró SellersItemIdentification, intentar StandardItemIdentification
                if (!$codigo) {
                    $codNode = $invoiceXpath->query('.//*[local-name()="StandardItemIdentification"]/*[local-name()="ID"]', $line);
                    $codigo = $codNode && $codNode->length > 0 ? $codNode->item(0)->textContent : '';
                }

                $descuento = 0;
                $porcentajeDescuento = 0;
                $allowances = $invoiceXpath->query('.//*[local-name()="AllowanceCharge"]', $line);
                
                foreach ($allowances as $allow) {
                    $chargeNode = $invoiceXpath->query('.//*[local-name()="ChargeIndicator"]', $allow);
                    if ($chargeNode && $chargeNode->length > 0 && $chargeNode->item(0)->textContent === 'false') {
                        $amtNode = $invoiceXpath->query('.//*[local-name()="Amount"]', $allow);
                        $descuento = (float)($amtNode && $amtNode->length > 0 ? $amtNode->item(0)->textContent : 0);
                        
                        $multNode = $invoiceXpath->query('.//*[local-name()="MultiplierFactorNumeric"]', $allow);
                        $porcentajeDescuento = (float)($multNode && $multNode->length > 0 ? $multNode->item(0)->textContent : 0);
                        break;
                    }
                }

                $ivaLinea = 0;
                $porcentajeIVA = 0;
                $ibuaLinea = 0;
                $taxSubtotals = $invoiceXpath->query('.//*[local-name()="TaxSubtotal"]', $line);
                
                foreach ($taxSubtotals as $tax) {
                    $taxSchemeNode = $invoiceXpath->query('.//*[local-name()="TaxScheme"]/*[local-name()="ID"]', $tax);
                    if ($taxSchemeNode && $taxSchemeNode->length > 0 && $taxSchemeNode->item(0)->textContent === '01') {
                        $taxAmtNode = $invoiceXpath->query('.//*[local-name()="TaxAmount"]', $tax);
                        $ivaLinea = (float)($taxAmtNode && $taxAmtNode->length > 0 ? $taxAmtNode->item(0)->textContent : 0);
                        
                        $percentNode = $invoiceXpath->query('.//*[local-name()="TaxCategory"]/*[local-name()="Percent"]', $tax);
                        $porcentajeIVA = (float)($percentNode && $percentNode->length > 0 ? $percentNode->item(0)->textContent : 0);
                        break;
                    }
                }

                // Buscar IBUA (ID 34) en TaxTotal
                $taxTotals = $invoiceXpath->query('.//*[local-name()="TaxTotal"]', $line);
                foreach ($taxTotals as $taxTotal) {
                    $ibuaSubtotals = $invoiceXpath->query('.//*[local-name()="TaxSubtotal"]', $taxTotal);
                    foreach ($ibuaSubtotals as $ibuaTax) {
                        $ibuaSchemeNode = $invoiceXpath->query('.//*[local-name()="TaxScheme"]/*[local-name()="ID"]', $ibuaTax);
                        if ($ibuaSchemeNode && $ibuaSchemeNode->length > 0 && $ibuaSchemeNode->item(0)->textContent === '34') {
                            $ibuaAmtNode = $invoiceXpath->query('.//*[local-name()="TaxAmount"]', $ibuaTax);
                            $ibuaLinea = (float)($ibuaAmtNode && $ibuaAmtNode->length > 0 ? $ibuaAmtNode->item(0)->textContent : 0);
                            break 2;
                        }
                    }
                }

                $totalAntesDescuentos += $valorUnitario * $cantidad;
                $totalDescuentos += $descuento;
                $baseGravable += $valorLinea;
                $totalIVA += $ivaLinea;

                // Guardar item con todos los detalles
                $items[] = [
                    'linea' => $lineID,
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                    'cantidad' => $cantidad,
                    'valor_unitario' => $valorUnitario,
                    'valor_total_linea' => $valorLinea,
                    'porcentaje_descuento' => $porcentajeDescuento,
                    'valor_descuento' => $descuento,
                    'porcentaje_iva' => $porcentajeIVA,
                    'valor_iva' => $ivaLinea,
                    'valor_ibua' => $ibuaLinea
                ];
            }

            return [
                'error' => false,
                'UUID' => $uuid,
                'parent_document_id' => $parentDocumentID,
                'numero_factura' => $numeroFactura,
                'fecha_emision' => $fechaEmision,
                'nit_proveedor' => $nitProveedor,
                'nombre_proveedor' => $nombreProveedor,
                'pdf' => null,
                'items' => $items,
                'totales' => [
                    'total_antes_descuentos' => $totalAntesDescuentos,
                    'total_descuentos' => $totalDescuentos,
                    'base_gravable' => $baseGravable,
                    'total_iva' => $totalIVA,
                    'total_factura' => $baseGravable + $totalIVA
                ]
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'Excepción: ' . $e->getMessage()];
        }
    }

    private function guardarEnBD($data, $xmlContent)
    {
        if (!$data['UUID']) {
            $this->guardarLogError($xmlContent, 'Error: UUID vacío');
            return false;
        }

        if (DB::table('t_documentos_dian_xml')->where('uuid', $data['UUID'])->exists()) {
            return false;
        }

        try {
            // INSERT con parámetros explícitos - debe coincidir con schema de BD
            DB::statement("
                INSERT INTO t_documentos_dian_xml (
                    uuid, parent_document_id, numero_factura, fecha_emision,
                    nit_proveedor, nombre_proveedor,
                    total_antes_descuentos, total_descuentos,
                    base_gravable, total_iva, total_factura,
                    json_items, pdf
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    ?, ?
                )
            ", [
                $data['UUID'],
                $data['parent_document_id'] ?? null,
                $data['numero_factura'],
                $data['fecha_emision'],
                $data['nit_proveedor'],
                $data['nombre_proveedor'],
                $data['totales']['total_antes_descuentos'],
                $data['totales']['total_descuentos'],
                $data['totales']['base_gravable'],
                $data['totales']['total_iva'],
                $data['totales']['total_factura'],
                json_encode($data['items'] ?? []),
                $data['pdf'] ?? null
            ]);
            return true;
        } catch (\Throwable $e) {
            
            // Construir mensaje detallado del error SQL
            $errorMsg = "ERROR AL GUARDAR EN BD t_documentos_dian_xml\n";
            $errorMsg .= "═══════════════════════════════════════════════════════════\n";
            $errorMsg .= "Excepción: " . get_class($e) . "\n";
            $errorMsg .= "Código Error: " . $e->getCode() . "\n";
            $errorMsg .= "Mensaje: " . $e->getMessage() . "\n";
            $errorMsg .= "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $errorMsg .= "Factura: " . ($data['numero_factura'] ?? 'desconocida') . "\n";
            $errorMsg .= "UUID: " . $data['UUID'] . "\n";
            
            // Si es QueryException, mostrar SQL completo
            if ($e instanceof \Illuminate\Database\QueryException) {
                $errorMsg .= "\n--- DETALLES SQL ---\n";
                $errorMsg .= "SQL: " . $e->getSql() . "\n";
                $errorMsg .= "Bindings: " . json_encode($e->getBindings(), JSON_PRETTY_PRINT) . "\n";
            }
            $errorMsg .= "═══════════════════════════════════════════════════════════\n";

            // Mostrar en consola (rojo)
            $this->error($errorMsg);

            // Loguear en Laravel
            Log::error("[ProcesarFacturas] " . $errorMsg);

            // Guardar en tabla de errores
            $this->guardarLogError($xmlContent, $errorMsg);

            return false;
        }
    }

    private function guardarLogError($xmlContent, $mensaje)
    {
        // 1. PRIMERO - Loguear en Laravel (nunca falla)
        Log::error("[ProcesarFacturas] " . $mensaje);

        try {
            // 2. LUEGO - Intentar guardar en BD
            DB::table('t_log_reader_xml')->insert([
                'message' => $mensaje,
            ]);
           // Log::info("[ProcesarFacturas] Error registrado en BD correctamente");
        } catch (\Throwable $dbError) {
            // 3. FALLBACK - Si BD falla, guardar en archivo
            $errorLog = storage_path('logs/xml_errors.log');
            
            $logEntry = "\n" . str_repeat("=", 80) . "\n";
            $logEntry .= "[" . date('Y-m-d H:i:s') . "] ERROR XML PROCESSING\n";
            $logEntry .= str_repeat("=", 80) . "\n";
            $logEntry .= "MENSAJE:\n" . $mensaje . "\n\n";
            $logEntry .= "ERROR AL GUARDAR EN t_log_reader_xml:\n";
            $logEntry .= "  - Type: " . get_class($dbError) . "\n";
            $logEntry .= "  - Code: " . $dbError->getCode() . "\n";
            $logEntry .= "  - Message: " . $dbError->getMessage() . "\n\n";
            
            $logEntry .= "XML (primeros 2000 caracteres):\n";
            $logEntry .= "---START---\n" . substr($xmlContent, 0, 2000) . "\n---END---\n";
            $logEntry .= str_repeat("=", 80) . "\n";
            
            file_put_contents($errorLog, $logEntry, FILE_APPEND);
            Log::error("[ProcesarFacturas] Error guardado en archivo: $errorLog");
        }
    }
}

