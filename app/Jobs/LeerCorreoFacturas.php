<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeerCorreoFacturas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        Log::info("[LeerCorreoFacturas] Iniciando lectura de correos IMAP");

        $mailbox  = '{imap.gmail.com:993/imap/ssl}INBOX';
        $usuario  = env('IMAP_USER');
        $password = env('IMAP_PASS');

        $inbox = @imap_open($mailbox, $usuario, $password);

        if (!$inbox) {
            Log::error("[LeerCorreoFacturas] Error IMAP: " . imap_last_error());
            throw new \Exception("No se pudo conectar a IMAP: " . imap_last_error());
        }

        try {
            // 📅 Fecha de los últimos 25 días
            $fecha = date('d-M-Y', strtotime('-25 days'));

            // 🔎 Buscar solo correos NO LEÍDOS desde esa fecha
            $emails = @imap_search($inbox, 'UNSEEN SINCE "' . $fecha . '"');

            if (!$emails) {
                Log::info("[LeerCorreoFacturas] No hay correos nuevos");
                return;
            }

            Log::info("[LeerCorreoFacturas] Encontrados " . count($emails) . " correos");

            $savePath = storage_path('app/facturas/');

            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }

            $procesados = 0;
            $errores = 0;

            foreach ($emails as $emailNumber) {
                try {
                    $structure = @imap_fetchstructure($inbox, $emailNumber);

                    if (!isset($structure->parts)) {
                        continue;
                    }

                    foreach ($structure->parts as $index => $part) {

                        if (!empty($part->dparameters)) {

                            foreach ($part->dparameters as $object) {

                                if (strtolower($object->attribute) == 'filename') {

                                    $filename = $object->value;

                                    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) == 'zip') {

                                        $body = @imap_fetchbody($inbox, $emailNumber, $index + 1);

                                        if ($part->encoding == 3) {
                                            $body = base64_decode($body);
                                        }

                                        $zipPath = $savePath . $filename;

                                        file_put_contents($zipPath, $body);

                                        $zip = new \ZipArchive;

                                        if ($zip->open($zipPath) === TRUE) {

                                            $extractPath = $savePath . pathinfo($filename, PATHINFO_FILENAME) . '/';

                                            if (!is_dir($extractPath)) {
                                                mkdir($extractPath, 0777, true);
                                            }

                                            $zip->extractTo($extractPath);
                                            $zip->close();

                                            @unlink($zipPath);

                                            // 🔎 Buscar XML recursivamente
                                            $xmlPath = null;

                                            $files = new \RecursiveIteratorIterator(
                                                new \RecursiveDirectoryIterator($extractPath)
                                            );

                                            foreach ($files as $file) {
                                                if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                                                    $xmlPath = $file->getPathname();
                                                    $pdfContent = $file->getFilename();
                                                    $xmlName = $file->getFilename(); // factura123.xml
                                                    $pdfContent = $extractPath . pathinfo($xmlName, PATHINFO_FILENAME);
                                                    break;
                                                }
                                            }

                                            if ($xmlPath) {

                                                $xmlContent = file_get_contents($xmlPath);

                                                $resultado = $this->procesarFactura($xmlContent, $pdfContent);

                                                if (!$resultado['error']) {

                                                    $guardado = $this->guardarDocumentoDIAN($resultado, $xmlContent);

                                                    if ($guardado) {
                                                        $procesados++;
                                                        Log::info("[LeerCorreoFacturas] Factura guardada: " . $resultado['UUID']);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $errores++;
                    Log::error("[LeerCorreoFacturas] Error procesando email {$emailNumber}: " . $e->getMessage());
                }
            }

            Log::info("[LeerCorreoFacturas] Proceso finalizado. Procesados: {$procesados}, Errores: {$errores}");

        } finally {
            @imap_close($inbox);
        }
    }

    /**
     * 🔹 Parsear XML DIAN (AttachedDocument)
     */
    protected function procesarFactura($xmlContent, $xmlPathPdf)
    {
        libxml_use_internal_errors(true);

        $xmlContent = trim($xmlContent);
        $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

        $attached = simplexml_load_string($xmlContent);

        if ($attached === false) {
            return ['error' => true, 'mensaje' => 'XML inválido (AttachedDocument)'];
        }

        // 🔹 ParentDocumentID
        $parentNode = $attached->xpath('//*[local-name()="ParentDocumentID"]');
        $parentDocumentID = isset($parentNode[0]) ? (string)$parentNode[0] : null;

        // 🔹 XML interno
        $inner = $attached->xpath('//*[local-name()="Attachment"]//*[local-name()="Description"]');
        if (!isset($inner[0])) {
            return ['error' => true, 'mensaje' => 'No se encontró el XML interno'];
        }

        $invoice = simplexml_load_string((string)$inner[0]);
        if ($invoice === false) {
            return ['error' => true, 'mensaje' => 'XML interno inválido'];
        }

        // 🔹 Helper XPath
        $get = function ($node, $path) {
            $result = $node->xpath($path);
            return isset($result[0]) ? (string)$result[0] : null;
        };

        // 🔹 Datos generales
        $numeroFactura = $get($invoice, '//*[local-name()="ID"]');
        $fechaEmision  = $get($invoice, '//*[local-name()="IssueDate"]');
        $UUID          = $get($invoice, '//*[local-name()="UUID"]');

        $nitProveedor    = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="CompanyID"]');
        $nombreProveedor = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="RegistrationName"]');

        // 🔹 Totales acumulados
        $totalAntesDescuentos = 0;
        $totalDescuentos      = 0;
        $baseGravable         = 0;
        $totalIVA             = 0;
        $totalFactura         = 0;

        $items = [];

        $invoiceLines = $invoice->xpath('//*[local-name()="InvoiceLine"]');

        if ($invoiceLines) {
            foreach ($invoiceLines as $line) {

                $cantidad       = (float) $get($line, '*[local-name()="InvoicedQuantity"]');
                $valorLinea     = (float) $get($line, '*[local-name()="LineExtensionAmount"]');
                $valorUnitario  = (float) $get($line, './/*[local-name()="PriceAmount"]');

                // =========================
                // 🔹 DESCUENTO
                // =========================
                $descuento = 0;
                $pcjDescuento = 0;

                $allowances = $line->xpath('.//*[local-name()="AllowanceCharge"]');
                if ($allowances) {
                    foreach ($allowances as $allow) {
                        $chargeIndicator = $get($allow, './/*[local-name()="ChargeIndicator"]');

                        if ($chargeIndicator === 'false') {
                            $descuento     = (float) $get($allow, './/*[local-name()="Amount"]');
                            $pcjDescuento  = (float) $get($allow, './/*[local-name()="MultiplierFactorNumeric"]');
                            break;
                        }
                    }
                }

                // =========================
                // 🔹 IVA
                // =========================
                $ivaLinea = 0;
                $porcentajeIVA = 0;

                $taxSubtotals = $line->xpath('.//*[local-name()="TaxSubtotal"]');

                if ($taxSubtotals) {
                    foreach ($taxSubtotals as $tax) {
                        $schemeID = $get($tax, './/*[local-name()="TaxScheme"]/*[local-name()="ID"]');

                        if ($schemeID === '01') { // IVA
                            $ivaLinea      = (float) $get($tax, './/*[local-name()="TaxAmount"]');
                            $porcentajeIVA = (float) $get($tax, './/*[local-name()="Percent"]');
                            break;
                        }
                    }
                }

                // =========================
                // 🔹 CALCULOS
                // =========================
                $totalAntesDescuentos += $valorUnitario * $cantidad;
                $totalDescuentos      += $descuento;
                $baseGravable         += $valorLinea;
                $totalIVA             += $ivaLinea;
                $totalFactura         += $valorLinea + $ivaLinea;

                // =========================
                // 🔹 ARRAY DE ITEMS
                // =========================
                $items[] = [
                    'id'                 => $get($line, '*[local-name()="ID"]'),
                    'cantidad'           => $cantidad,
                    'valor_unitario'     => $valorUnitario,
                    'valor_total_linea'  => $valorLinea,
                    'descripcion'        => $get($line, './/*[local-name()="Description"]'),
                    'codigo'             => $get($line, './/*[local-name()="StandardItemIdentification"]/*[local-name()="ID"]'),
                    'porcentaje_iva'     => $porcentajeIVA,
                    'valor_iva'          => $ivaLinea,
                    'porcentaje_descuento'=> $pcjDescuento,
                    'valor_descuento'    => $descuento
                ];
            }
        }

        return [
            'error' => false,
            'ParentDocumentID' => $parentDocumentID,
            'numero_factura'   => $numeroFactura,
            'fecha_emision'    => $fechaEmision,
            'UUID'             => $UUID,
            'pdf'              => $xmlPathPdf,
            'proveedor' => [
                'nit'    => $nitProveedor,
                'nombre' => $nombreProveedor
            ],
            'items'   => $items,
            'totales' => [
                'antes_descuentos' => round($totalAntesDescuentos, 2),
                'total_descuentos' => round($totalDescuentos, 2),
                'base_gravable'    => round($baseGravable, 2),
                'total_iva'        => round($totalIVA, 2),
                'total_factura'    => round($totalFactura, 2)
            ]
        ];
    }

    /**
     * 🔹 Guardar en BD validando duplicados
     */
    protected function guardarDocumentoDIAN($data, $xmlCompleto)
    {
        if (!$data['UUID']) {
            return false;
        }

        $existe = DB::table('t_documentos_dian_xml')
            ->where('uuid', $data['UUID'])
            ->first();

        if ($existe) {
            return false;
        }

        DB::table('t_documentos_dian_xml')->insert([
            'uuid'                    => $data['UUID'],
            'pdf'                     => $data['pdf'],
            'parent_document_id'      => $data['ParentDocumentID'],
            'numero_factura'          => $data['numero_factura'],
            'fecha_emision'           => $data['fecha_emision'],
            'nit_proveedor'           => $data['proveedor']['nit'],
            'nombre_proveedor'        => $data['proveedor']['nombre'],

            // 🔹 Totales para conciliación
            'total_antes_descuentos'  => $data['totales']['antes_descuentos'] ?? 0,
            'total_descuentos'        => $data['totales']['total_descuentos'] ?? 0,
            'base_gravable'           => $data['totales']['base_gravable'] ?? 0,
            'total_iva'               => $data['totales']['total_iva'] ?? 0,
            'total_factura'           => $data['totales']['total_factura'] ?? 0,

            'json_items'              => json_encode($data['items']),
            'xml_completo'            => $xmlCompleto,
            'created_at'              => now(),
            'updated_at'              => now()
        ]);

        return true;
    }
}
