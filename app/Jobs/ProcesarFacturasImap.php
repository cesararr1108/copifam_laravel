<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesarFacturasImap implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $emailNumbers;
    protected $lote;

    public function __construct(array $emailNumbers, int $lote = 1)
    {
        $this->emailNumbers = $emailNumbers;
        $this->lote = $lote;
    }

    public function handle()
    {
        Log::info("[FacturaElectronica] Iniciando procesamiento de batch #{$this->lote} con " . count($this->emailNumbers) . " correos");

        $mailbox  = '{imap.gmail.com:993/imap/ssl}INBOX';
        $usuario  = config('services.imap.user');
        $password = config('services.imap.pass');

        if (empty($usuario) || empty($password)) {
            Log::error("[FacturaElectronica] Credenciales IMAP no configuradas");
            return;
        }

        try {
            $inbox = @imap_open($mailbox, $usuario, $password, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
        } catch (\Throwable $e) {
            Log::error("[FacturaElectronica] Error conectando IMAP: " . $e->getMessage());
            return;
        }

        if (!$inbox) {
            Log::error("[FacturaElectronica] No se pudo conectar al mailbox: " . imap_last_error());
            return;
        }

        $savePath = storage_path('app/facturas/');
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        $procesados = 0;
        $errores = [];

        foreach ($this->emailNumbers as $emailNumber) {
            try {
                $structure = imap_fetchstructure($inbox, $emailNumber);

                if (!isset($structure->parts)) {
                    DB::table('imap_processed_emails')->insertOrIgnore([
                        'email_id'     => $emailNumber,
                        'processed_at' => now(),
                    ]);
                    continue;
                }

                foreach ($structure->parts as $index => $part) {
                    if (empty($part->dparameters)) continue;

                    foreach ($part->dparameters as $object) {
                        if (strtolower($object->attribute) !== 'filename') continue;

                        $filename = $object->value;
                        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') continue;

                        $body = imap_fetchbody($inbox, $emailNumber, $index + 1);
                        if ($part->encoding == 3) {
                            $body = base64_decode($body);
                        }

                        $tempZipPath = tempnam(sys_get_temp_dir(), 'factura_zip_');
                        file_put_contents($tempZipPath, $body);

                        $zip = new \ZipArchive;
                        if ($zip->open($tempZipPath) !== true) {
                            @unlink($tempZipPath);
                            continue;
                        }

                        $xmlContent = null;
                        $xmlName    = null;
                        $pdfContent = null;

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $fileName = $zip->getNameIndex($i);
                            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                            if ($ext === 'xml' && !$xmlContent) {
                                $xmlContent = $zip->getFromName($fileName);
                                $xmlName    = $fileName;
                            } elseif ($ext === 'pdf' && !$pdfContent) {
                                $pdfContent = $zip->getFromName($fileName);
                            }
                        }

                        $zip->close();
                        @unlink($tempZipPath);

                        if (!$xmlContent) {
                            continue;
                        }

                        $pdfPath = null;
                        if ($pdfContent && $xmlName) {
                            $pdfPath = $savePath . pathinfo($xmlName, PATHINFO_FILENAME) . '.pdf';
                            file_put_contents($pdfPath, $pdfContent);
                        }

                        // Procesar XML
                        $resultado = $this->procesarFactura($xmlContent, $pdfPath);

                        if (!$resultado['error']) {
                            $this->guardarDocumento($resultado);
                            $procesados++;
                            Log::info("[FacturaElectronica] UUID {$resultado['UUID']} procesado exitosamente en batch #{$this->lote}");
                        }

                        DB::table('imap_processed_emails')->insertOrIgnore([
                            'email_id'     => $emailNumber,
                            'processed_at' => now(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[FacturaElectronica] Error procesando email {$emailNumber}: " . $e->getMessage());
                DB::table('imap_processed_emails')->insertOrIgnore([
                    'email_id'     => $emailNumber,
                    'processed_at' => now(),
                ]);
            }
        }

        imap_close($inbox);
        Log::info("[FacturaElectronica] Batch #{$this->lote} completado. Procesados: {$procesados}");
    }

    private function procesarFactura(string $xmlContent, ?string $xmlPathPdf): array
    {
        libxml_use_internal_errors(true);

        $xmlContent = trim($xmlContent);
        $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

        $attached = simplexml_load_string($xmlContent);

        if ($attached === false) {
            return ['error' => true, 'mensaje' => 'XML inválido'];
        }

        $parentNode       = $attached->xpath('//*[local-name()="ParentDocumentID"]');
        $parentDocumentID = isset($parentNode[0]) ? (string) $parentNode[0] : null;

        $inner = $attached->xpath('//*[local-name()="Attachment"]//*[local-name()="Description"]');
        if (!isset($inner[0])) {
            return ['error' => true, 'mensaje' => 'No se encontró el XML interno'];
        }

        $invoice = simplexml_load_string((string) $inner[0]);
        if ($invoice === false) {
            return ['error' => true, 'mensaje' => 'XML interno inválido'];
        }

        $get = fn($node, $path) => ($r = $node->xpath($path)) && isset($r[0]) ? (string) $r[0] : null;

        $numeroFactura   = $get($invoice, '//*[local-name()="ID"]');
        $fechaEmision    = $get($invoice, '//*[local-name()="IssueDate"]');
        $UUID            = $get($invoice, '//*[local-name()="UUID"]');
        $nitProveedor    = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="CompanyID"]');
        $nombreProveedor = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="RegistrationName"]');

        $totalAntesDescuentos = 0;
        $totalDescuentos      = 0;
        $baseGravable         = 0;
        $totalIVA             = 0;
        $totalFactura         = 0;
        $items                = [];

        foreach ($invoice->xpath('//*[local-name()="InvoiceLine"]') as $line) {
            $cantidad      = (float) $get($line, '*[local-name()="InvoicedQuantity"]');
            $valorLinea    = (float) $get($line, '*[local-name()="LineExtensionAmount"]');
            $valorUnitario = (float) $get($line, './/*[local-name()="PriceAmount"]');

            $descuento    = 0;
            $pcjDescuento = 0;
            foreach ($line->xpath('.//*[local-name()="AllowanceCharge"]') as $allow) {
                if ($get($allow, './/*[local-name()="ChargeIndicator"]') === 'false') {
                    $descuento    = (float) $get($allow, './/*[local-name()="Amount"]');
                    $pcjDescuento = (float) $get($allow, './/*[local-name()="MultiplierFactorNumeric"]');
                    break;
                }
            }

            $ivaLinea      = 0;
            $porcentajeIVA = 0;
            foreach ($line->xpath('.//*[local-name()="TaxSubtotal"]') as $tax) {
                if ($get($tax, './/*[local-name()="TaxScheme"]/*[local-name()="ID"]') === '01') {
                    $ivaLinea      = (float) $get($tax, './/*[local-name()="TaxAmount"]');
                    $porcentajeIVA = (float) $get($tax, './/*[local-name()="Percent"]');
                    break;
                }
            }

            $codigoProducto = $get($line, './/*[local-name()="StandardItemIdentification"]/*[local-name()="ID"]');
            if (!$codigoProducto) {
                $codigoProducto = $get($line, './/*[local-name()="SellersItemIdentification"]/*[local-name()="ID"]');
            }

            $propiedadesAdicionales = [];
            foreach ($line->xpath('.//*[local-name()="Item"]/*[local-name()="AdditionalItemProperty"]') as $prop) {
                $nameCode = $get($prop, './/*[local-name()="NameCode"]');
                $value    = $get($prop, './/*[local-name()="Value"]');
                if ($nameCode && $value) {
                    $propiedadesAdicionales[$nameCode] = $value;
                }
            }

            $totalAntesDescuentos += $valorUnitario * $cantidad;
            $totalDescuentos      += $descuento;
            $baseGravable         += $valorLinea;
            $totalIVA             += $ivaLinea;
            $totalFactura         += $valorLinea + $ivaLinea;

            $items[] = [
                'id'                  => $get($line, '*[local-name()="ID"]'),
                'cantidad'            => $cantidad,
                'valor_unitario'      => $valorUnitario,
                'valor_total_linea'   => $valorLinea,
                'descripcion'         => $get($line, './/*[local-name()="Description"]'),
                'codigo'              => $codigoProducto,
                'codigo_gtin'         => $propiedadesAdicionales['5171'] ?? null,
                'codigo_cum'          => $propiedadesAdicionales['5172'] ?? null,
                'lote'                => $propiedadesAdicionales['5173'] ?? null,
                'vencimiento'         => $propiedadesAdicionales['5174'] ?? null,
                'porcentaje_iva'      => $porcentajeIVA,
                'valor_iva'           => $ivaLinea,
                'porcentaje_descuento'=> $pcjDescuento,
                'valor_descuento'     => $descuento,
            ];
        }

        return [
            'error'            => false,
            'ParentDocumentID' => $parentDocumentID,
            'numero_factura'   => $numeroFactura,
            'fecha_emision'    => $fechaEmision,
            'UUID'             => $UUID,
            'pdf'              => $xmlPathPdf,
            'proveedor'        => ['nit' => $nitProveedor, 'nombre' => $nombreProveedor],
            'items'            => $items,
            'totales'          => [
                'antes_descuentos' => round($totalAntesDescuentos, 2),
                'total_descuentos' => round($totalDescuentos, 2),
                'base_gravable'    => round($baseGravable, 2),
                'total_iva'        => round($totalIVA, 2),
                'total_factura'    => round($totalFactura, 2),
            ],
        ];
    }

    private function guardarDocumento(array $data): bool
    {
        if (empty($data['UUID'])) return false;

        $existe = DB::selectOne(
            "SELECT TOP 1 id FROM t_documentos_dian_xml WHERE uuid = ?",
            [$data['UUID']]
        );

        if ($existe) return false;

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
            $data['ParentDocumentID'],
            $data['numero_factura'],
            $data['fecha_emision'],
            $data['proveedor']['nit'],
            $data['proveedor']['nombre'],
            $data['totales']['antes_descuentos'],
            $data['totales']['total_descuentos'],
            $data['totales']['base_gravable'],
            $data['totales']['total_iva'],
            $data['totales']['total_factura'],
            json_encode($data['items']),
            $data['pdf']
        ]);

        return true;
    }
}
